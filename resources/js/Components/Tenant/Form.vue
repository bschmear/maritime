<!-- Form.vue Component with Accordion -->
<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import InputLabel from '@/Components/Tenant/FormComponents/InputLabel.vue';
import TextInput from '@/Components/Tenant/FormComponents/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Tenant/FormComponents/Checkbox.vue';
import Radio from '@/Components/Tenant/FormComponents/Radio.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import DateTimeInput from '@/Components/Tenant/FormComponents/DateTime.vue';
import Rating from '@/Components/Tenant/FormComponents/Rating.vue';
import MorphSelect from '@/Components/Tenant/MorphSelect.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';

const props = defineProps({
    schema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    record: {
        type: Object,
        default: null,
    },
    recordType: {
        type: String,
        default: '',
    },
    recordTitle: {
        type: String,
        default: '',
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    mode: {
        type: String,
        default: 'view',
        validator: (value) => ['view', 'edit', 'create'].includes(value),
    },
    preventRedirect: {
        type: Boolean,
        default: false,
    },
    formId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['submit', 'cancel', 'created', 'updated']);

const isEditMode = computed(() => props.mode === 'edit' || props.mode === 'create');
const isCreateMode = computed(() => props.mode === 'create');

// Handle both old schema format and new nested format (with settings)
const normalizedSchema = computed(() => {
    if (props.schema && props.schema.form) {
        return props.schema.form;
    }
    return props.schema;
});

// Generate unique form ID for this form instance
const formUniqueId = computed(() => {
    return `form-${props.recordType}-${props.record?.id || 'new'}-${Math.random().toString(36).substr(2, 9)}`;
});

// Generate unique field ID for input elements
const getFieldId = (fieldKey) => {
    return `${formUniqueId.value}-field-${fieldKey}`;
};

const getFieldDefinition = (fieldKey) => {
    return props.fieldsSchema[fieldKey] || {};
};

const initializeFormData = () => {
    const formData = {};

    if (props.record) {
        // First, copy all record data
        Object.keys(props.record).forEach(key => {
            const fieldDef = getFieldDefinition(key);
            const value = props.record[key];
            
            // Handle date/datetime fields - convert to proper format for inputs
            if (fieldDef.type === 'datetime' || fieldDef.type === 'date') {
                if (value) {
                    if (value instanceof Date) {
                        formData[key] = fieldDef.type === 'datetime'
                            ? value.toISOString().slice(0, 16)
                            : value.toISOString().split('T')[0];
                    } else if (typeof value === 'string') {
                        const parsedDate = new Date(value);
                        if (!isNaN(parsedDate.getTime())) {
                            formData[key] = fieldDef.type === 'datetime'
                                ? parsedDate.toISOString().slice(0, 16)
                                : parsedDate.toISOString().split('T')[0];
                        } else {
                            formData[key] = value;
                        }
                    } else {
                        formData[key] = value;
                    }
                } else {
                    formData[key] = null;
                }
            } else if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                formData[key] = value === true || value === 1 ? 1 : 0;
            } else {
                formData[key] = value;
            }
        });
    }

    // Initialize any missing fields from schema
    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    if (!(field.key in formData)) {
                        const fieldDef = getFieldDefinition(field.key);
                        const fieldType = fieldDef.type || 'text';
                        
                        // Check for default_value first
                        if (fieldDef.default_value !== undefined && fieldDef.default_value !== null) {
                            formData[field.key] = fieldDef.default_value;
                        }
                        // Check for default_today for date fields
                        else if (fieldType === 'date' && fieldDef.default_today === true) {
                            const today = new Date();
                            formData[field.key] = today.toISOString().split('T')[0]; // YYYY-MM-DD
                        }
                        // Check for default_now for datetime fields
                        else if (fieldType === 'datetime' && fieldDef.default_now === true) {
                            const now = new Date();
                            formData[field.key] = now.toISOString().slice(0, 16); // YYYY-MM-DDTHH:MM
                        }
                        else if (fieldType === 'select' || fieldType === 'record') {
                            // If required and has enum options, auto-select first option
                            if (field.required && fieldDef.enum && props.enumOptions[fieldDef.enum] && props.enumOptions[fieldDef.enum].length > 0) {
                                formData[field.key] = props.enumOptions[fieldDef.enum][0].id;
                            } else {
                                formData[field.key] = null;
                            }
                        } else if (fieldType === 'datetime' || fieldType === 'date' || fieldType === 'time') {
                            formData[field.key] = null;
                        } else if (fieldType === 'rating') {
                            formData[field.key] = 0; // Initialize rating as 0
                        } else if (fieldType === 'checkbox' || fieldType === 'boolean') {
                            formData[field.key] = 0; // Initialize as 0 (unchecked)
                        } else {
                            formData[field.key] = '';
                        }
                    }
                });
            }
        });
    }

    return formData;
};

const form = useForm(initializeFormData());
const isProcessing = ref(false);

watch(() => props.record, (newRecord) => {
    if (newRecord) {
        form.clearErrors();
        Object.keys(newRecord).forEach(key => {
            const fieldDef = getFieldDefinition(key);
            // Convert checkbox values: ensure 0/1 instead of false/true
            if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                form[key] = newRecord[key] === true || newRecord[key] === 1 ? 1 : 0;
            } else if (fieldDef.type === 'datetime' || fieldDef.type === 'date') {
                // Handle date/datetime fields - convert to proper format for components
                const dateValue = newRecord[key];
                if (dateValue) {
                    if (dateValue instanceof Date) {
                        // If it's already a Date object, format it for the input
                        if (fieldDef.type === 'datetime') {
                            form[key] = dateValue.toISOString().slice(0, 16); // YYYY-MM-DDTHH:MM format
                        } else {
                            form[key] = dateValue.toISOString().split('T')[0]; // YYYY-MM-DD format
                        }
                    } else if (typeof dateValue === 'string') {
                        // If it's a string, try to parse it
                        const parsedDate = new Date(dateValue);
                        if (!isNaN(parsedDate.getTime())) {
                            if (fieldDef.type === 'datetime') {
                                form[key] = parsedDate.toISOString().slice(0, 16);
                            } else {
                                form[key] = parsedDate.toISOString().split('T')[0];
                            }
                        } else {
                            form[key] = dateValue; // Keep as is if parsing fails
                        }
                    } else {
                        form[key] = dateValue;
                    }
                } else {
                    form[key] = null;
                }
            } else {
                form[key] = newRecord[key] ?? '';
            }
        });
    }
}, { deep: true, immediate: true });

// Watch form changes to handle conditional field visibility
watch(() => form.data(), (newData, oldData) => {
    // Check if any fields that control conditional visibility have changed
    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    // If this field has conditional logic and is currently hidden, clear its value
                    if (field.conditional && !isFieldVisible(field)) {
                        form[field.key] = getFieldType(field.key) === 'checkbox' || getFieldType(field.key) === 'boolean' ? 0 : '';
                    }
                });
            }
        });
    }
}, { deep: true });

const formGroups = computed(() => {
    if (!normalizedSchema.value) return [];
    // Ensure reactivity to form changes for conditional field visibility
    const currentFormData = form.data();
    return Object.entries(normalizedSchema.value)
        .filter(([key, group]) => group && typeof group === 'object')
        .map(([key, group], index) => ({
            key,
            index,
            label: group.label || key,
            is_address: group.is_address || false,
            // Filter out invalid fields and ensure field.key exists
            filteredFields: (group.fields || []).filter(f => f && typeof f === 'object' && f.key)
        }));
});

// Track which accordion sections are open
const openSections = ref({});

// Initialize all sections as open
watch(() => formGroups.value, (groups) => {
    if (groups.length > 0 && Object.keys(openSections.value).length === 0) {
        groups.forEach(group => {
            openSections.value[group.key] = true;
        });
    }
}, { immediate: true });

const toggleSection = (key) => {
    openSections.value[key] = !openSections.value[key];
};

const getFieldValue = (fieldKey) => form[fieldKey] ?? '';
const getEnumOptions = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    if (fieldDef.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    // Handle record type fields
    if (fieldDef.type === 'record' && fieldDef.typeDomain) {
        const domainKey = `Domain\\${fieldDef.typeDomain}\\Models\\${fieldDef.typeDomain}`;
        return props.enumOptions[domainKey] || [];
    }
    return [];
};
const getEnumLabel = (fieldKey, value) => {
    const options = getEnumOptions(fieldKey);
    // Convert value to string for comparison to handle both integer IDs and string values
    const valueStr = value != null ? String(value) : '';
    const option = options.find(opt => 
        String(opt.id) === valueStr || 
        String(opt.value) === valueStr ||
        opt.id === value ||
        opt.value === value
    );
    return option ? option.name : value;
};

const getRecordDisplayName = (fieldKey, value) => {
    if (!value) return '—';
    
    const fieldDef = getFieldDefinition(fieldKey);
    
    // If it's a record type field, try to get the display name from the loaded relationship
    if (fieldDef.type === 'record' && props.record) {
        // Convert field key like 'assigned_id' to relationship name like 'assigned'
        const relationshipName = fieldKey.replace('_id', '');
        const relatedRecord = props.record[relationshipName];
        
        if (relatedRecord && relatedRecord.display_name) {
            return relatedRecord.display_name;
        }
    }
    
    // Fallback to enum label for backward compatibility
    return getEnumLabel(fieldKey, value);
};
const getFieldType = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    return fieldDef.type || 'text';
};
const getFieldLabel = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    return fieldDef.label || fieldKey;
};
const isFieldRequired = (field) => field.required === true;
const isFieldDisabled = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    return fieldDef.disabled === true || (!isEditMode.value && props.mode === 'view');
};

const isFieldVisible = (field) => {
    // Guard clause - if field is undefined/null, hide it
    if (!field || typeof field !== 'object') {
        return false;
    }

    // Check if field is update_only and we're in create mode
    if (field.update_only === true && isCreateMode.value) {
        return false;
    }

    // Check if field has conditional logic
    if (field.conditional && typeof field.conditional === 'object') {
        const { key, value, operator = 'equals' } = field.conditional;
        const currentValue = form[key];

        switch (operator) {
            case 'equals':
            case 'eq':
                // Handle boolean comparisons for checkboxes (1/0 vs true/false)
                if (typeof value === 'boolean') {
                    const boolCurrentValue = currentValue === 1 || currentValue === true;
                    return boolCurrentValue === value;
                }
                return currentValue === value;
            case 'not_equals':
            case 'neq':
                if (typeof value === 'boolean') {
                    const boolCurrentValue = currentValue === 1 || currentValue === true;
                    return boolCurrentValue !== value;
                }
                return currentValue !== value;
            case 'greater_than':
            case 'gt':
                return currentValue > value;
            case 'less_than':
            case 'lt':
                return currentValue < value;
            case 'contains':
                return String(currentValue).includes(String(value));
            case 'is_empty':
                return !currentValue || currentValue === '';
            case 'is_not_empty':
                return currentValue && currentValue !== '';
            default:
                // Default to equals with boolean handling
                if (typeof value === 'boolean') {
                    const boolCurrentValue = currentValue === 1 || currentValue === true;
                    return boolCurrentValue === value;
                }
                return currentValue === value;
        }
    }

    // No conditional logic, field is always visible
    return true;
};

const getFieldColSpan = (field) => {
    // Check for explicit class override in schema
    if (field.col_span) {
        return field.col_span;
    }
    
    // Check for span number in schema (1-12)
    if (field.span) {
        return `sm:col-span-${field.span}`;
    }

    const fieldType = getFieldType(field.key);
    
    // Full width fields by default
    if (fieldType === 'textarea' || 
        field.key === 'address_line_1' || 
        field.key === 'address_line_2' ||
        fieldType === 'editor') {
        return 'sm:col-span-12';
    }

    // Default to half width (6 columns out of 12)
    return 'sm:col-span-6';
};

// Phone number formatting functions
const formatPhoneNumber = (value) => {
    if (!value) return '';
    // Remove all non-numeric characters
    const numbers = value.replace(/\D/g, '');
    // Format as (XXX) XXX-XXXX
    if (numbers.length <= 3) {
        return numbers;
    } else if (numbers.length <= 6) {
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3)}`;
    } else {
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3, 6)}-${numbers.slice(6, 10)}`;
    }
};

const unformatPhoneNumber = (value) => {
    if (!value) return '';
    // Remove all non-numeric characters
    return value.replace(/\D/g, '');
};

const handlePhoneInput = (fieldKey, event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const oldValue = input.value;
    const unformatted = unformatPhoneNumber(oldValue);
    const formatted = formatPhoneNumber(unformatted);
    
    // Update the form data with unformatted value (for DB storage)
    form[fieldKey] = unformatted;
    
    // Update the input display with formatted value
    input.value = formatted;
    
    // Calculate new cursor position
    // Count digits before cursor in old value
    const digitsBeforeCursor = unformatPhoneNumber(oldValue.slice(0, cursorPosition)).length;
    // Find position in new formatted value where we have the same number of digits
    let newPosition = 0;
    let digitCount = 0;
    for (let i = 0; i < formatted.length && digitCount < digitsBeforeCursor; i++) {
        if (/\d/.test(formatted[i])) {
            digitCount++;
        }
        newPosition = i + 1;
    }
    
    // Set cursor position after Vue updates the DOM
    setTimeout(() => {
        input.setSelectionRange(newPosition, newPosition);
    }, 0);
};

const getFormattedPhoneValue = (fieldKey) => {
    const value = form[fieldKey] || '';
    return formatPhoneNumber(value);
};

// Number formatting functions
const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') return '';
    // specific check to avoid formatting partial decimals like "12." losing the decimal
    const strValue = String(value);
    const parts = strValue.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
};

const unformatNumber = (value) => {
    if (value === null || value === undefined || value === '') return null;
    return String(value).replace(/,/g, '');
};

const handleNumberInput = (fieldKey, event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const oldValue = input.value;
    
    // Remove commas to get raw number
    const unformatted = unformatNumber(oldValue);
    
    // Update form data with raw number (or string representation of it)
    form[fieldKey] = unformatted;
    
    // Format for display
    const formatted = formatNumber(unformatted);
    
    // Only update display if it changed (avoids cursor jump issues on simple appends)
    if (input.value !== formatted) {
         input.value = formatted;

         // Restore cursor position
         // Count distinct numbers before the cursor in the ORIGINAL value
         // We only care about digits, not commas
         let digitCountBeforeCursor = 0;
         for (let i = 0; i < cursorPosition; i++) {
             if (/\d/.test(oldValue[i])) {
                 digitCountBeforeCursor++;
             }
         }

         // Find the new position in the FORMATTED value
         let newPosition = 0;
         let digitsEncountered = 0;
         for (let i = 0; i < formatted.length; i++) {
             if (/\d/.test(formatted[i])) {
                 digitsEncountered++;
             }
             if (digitsEncountered === digitCountBeforeCursor) {
                 // If we've found all our digits, we are basically done.
                 // But if the next char is a comma, we might want to step over it?
                 // Usually standard behavior is fine, let's just break here or check next char.
                 // Actually, we just need the index AFTER this digit.
                 newPosition = i + 1;
                 break;
             }
         }

         // If we had 0 digits before cursor (beginning of string), newPosition is 0
         if (digitCountBeforeCursor === 0) newPosition = 0;
         
         // Set the selection range
         // Vue updates DOM asynchronously sometimes, but setting value directly helps.
         // We verify if we need nextTick or can do it immediately.
         // Usually doing it immediately works if we manipulated input.value directly.
         input.setSelectionRange(newPosition, newPosition);
    }
};

// Address Autocomplete Helpers
const hasAddressTags = (group) => {
    return group.filteredFields && group.filteredFields.some(field => field.tag);
};

const getAddressFieldValue = (group, tag) => {
    if (!group.filteredFields) return '';
    const field = group.filteredFields.find(f => f.tag === tag);
    return field ? (form[field.key] || '') : '';
};

const updateAddressFields = (group, data) => {
    // data keys match the tags we expect: street, unit, city, state, etc.
    // data keys come from AddressAutocomplete emits (camelCase usually: street, unit, city, state, stateCode, postalCode...)
    // But let's check what AddressAutocomplete emits. 
    // It emits: { street, unit, city, state, stateCode, postalCode, country, countryCode, latitude, longitude }
    // Ideally our tags in schema should match these or we map them. 
    // Let's assume schema tags will match these keys (camelCase or snake_case? Component emits camelCase).
    // Let's support mapping if needed, but for now direct match.
    // Wait, component emits `stateCode` but maybe schema uses `state_code`?
    // Let's standardize on snake_case tags for consistency with DB columns, BUT the component emits camelCase props.
    // I will convert the emitted keys to snake_case or just check both.
    
    // Actually, simpler: I'll make the helpers robust.
    
    Object.keys(data).forEach(emittedKey => {
        // We look for a field with tag === emittedKey
        // If emittedKey is 'stateCode', tag might be 'state_code' or 'stateCode'.
        // Let's try to match loosely or define a map.
        let tag = emittedKey;
        if (emittedKey === 'stateCode') tag = 'state_code';
        if (emittedKey === 'postalCode') tag = 'postal_code';
        if (emittedKey === 'countryCode') tag = 'country_code';
        
        // Find field with this tag
        const field = group.filteredFields.find(f => f.tag === tag || f.tag === emittedKey);
        if (field) {
            form[field.key] = data[emittedKey];
        }
    });
};

// File input handling
const handleFileInput = (fieldKey, event) => {
    const file = event.target.files[0];
    if (file) {
        form[fieldKey] = file;
    }
};

const getFileName = (filePath) => {
    if (!filePath) return '';
    return filePath.split('/').pop().split('\\').pop();
};

// Date formatting functions
const formatDate = (value) => {
    if (!value) return '';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        
        // Format as "December 5, 2025"
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }).format(date);
    } catch (e) {
        return value;
    }
};

const formatDateTime = (value) => {
    if (!value) return '';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        
        // Format as "December 5, 2025 at 3:30 PM"
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);
    } catch (e) {
        return value;
    }
};

// Prepare form data with checkbox values converted to 1/0
const prepareFormData = () => {
    const data = { ...form.data() };
    
    // Convert checkbox boolean values to 1/0 for proper submission
    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    const fieldDef = getFieldDefinition(field.key);
                    if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                        // Convert boolean to 1/0
                        data[field.key] = data[field.key] === true || data[field.key] === 1 ? 1 : 0;
                    }
                });
            }
        });
    }
    
    return data;
};

const handleSubmit = () => {
    const formData = prepareFormData();
    
    if (isCreateMode.value) {
        // If preventRedirect is true, use axios to prevent Inertia redirect
        if (props.preventRedirect) {
            isProcessing.value = true;
            axios.post(route(`${props.recordType}.store`), formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then((response) => {
                // Extract record ID from response
                const recordId = response.data?.recordId || response.data?.data?.recordId;
                if (recordId) {
                    form.reset();
                    emit('created', recordId);
                } else {
                    emit('submit');
                }
            })
            .catch((error) => {
                // Handle validation errors
                if (error.response?.status === 422) {
                    form.errors = error.response.data.errors || {};
                } else {
                    form.errors = { general: [error.response?.data?.message || 'An error occurred'] };
                }
            })
            .finally(() => {
                isProcessing.value = false;
            });
        } else {
            // Normal Inertia form submission with redirect
            // Use transform to convert checkbox values
            form.transform((data) => {
                const transformed = { ...data };
                if (props.schema) {
                    Object.values(props.schema).filter(g => g && typeof g === 'object').forEach(group => {
                        if (group.fields && Array.isArray(group.fields)) {
                            group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                                const fieldDef = getFieldDefinition(field.key);
                                if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                                    transformed[field.key] = transformed[field.key] === true || transformed[field.key] === 1 ? 1 : 0;
                                }
                            });
                        }
                    });
                }
                return transformed;
            }).post(route(`${props.recordType}.store`), {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Extract record ID from flash data or URL
                    let recordId = page?.props?.flash?.recordId;
                    if (!recordId) {
                        const urlMatch = page?.url?.match(/\/(\d+)$/);
                        if (urlMatch) {
                            recordId = urlMatch[1];
                        }
                    }
                    if (recordId) {
                        emit('created', recordId);
                    }
                    emit('submit');
                },
                onError: () => {
                    // Errors are handled by form.errors
                },
            });
        }
    } else {
        // If preventRedirect is true, use axios to prevent Inertia redirect
        if (props.preventRedirect) {
            isProcessing.value = true;
            axios.put(route(`${props.recordType}.update`, props.record.id), formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then((response) => {
                // Emit updated event with the updated record data
                const updatedRecord = response.data?.record || response.data?.data?.record;
                if (updatedRecord) {
                    emit('updated', updatedRecord);
                } else {
                    emit('submit');
                }
                isEditMode.value = false;
            })
            .catch((error) => {
                // Handle validation errors
                if (error.response?.status === 422) {
                    form.errors = error.response.data.errors || {};
                } else {
                    form.errors = { general: [error.response?.data?.message || 'An error occurred'] };
                }
            })
            .finally(() => {
                isEditMode.value = false;
                isProcessing.value = false;
            });
        } else {
                        // Normal Inertia form submission with redirect
            form.transform((data) => {
                const transformed = { ...data };
                if (props.schema) {
                    Object.values(props.schema).filter(g => g && typeof g === 'object').forEach(group => {
                        if (group.fields && Array.isArray(group.fields)) {
                            group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                                const fieldDef = getFieldDefinition(field.key);
                                if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                                    transformed[field.key] = transformed[field.key] === true || transformed[field.key] === 1 ? 1 : 0;
                                }
                            });
                        }
                    });
                }
                return transformed;
            }).put(route(`${props.recordType}.update`, props.record.id), {
                preserveScroll: true,
                onSuccess: (page) => {
                    console.log('onSuccess page object:', page);
                    console.log('page.props:', page.props);
                    console.log('page.url:', page.url);
                    console.log('flash messages:', page.props?.flash);
                    isEditMode.value = false;
                    emit('submit');
                    // Reload the record data from the server
                    router.reload({ only: ['record'] });
                },
                onError: (errors) => {
                    console.log('onError:', errors);
                },
                onFinish: () => {
                    console.log('onFinish - request completed');
                },
            });
        }
    }
};

const handleCancel = () => {
    if (isCreateMode.value) {
        emit('cancel');
    } else {
        form.reset();
        emit('cancel');
    }
};

// Expose methods for parent components to trigger form actions
const submitForm = () => {
    handleSubmit();
};

const cancelForm = () => {
    handleCancel();
};

const isFormProcessing = computed(() => form.processing || isProcessing.value);

defineExpose({
    submitForm,
    cancelForm,
    isProcessing: isFormProcessing,
});
</script>

<template>
    <form :id="formId || `form-${recordType}-${record?.id || 'new'}`" @submit.prevent="handleSubmit" v-if="normalizedSchema">
        <!-- Accordion -->
        <div id="accordion-collapse">
            <div v-for="(group, groupIndex) in formGroups" :key="group.key">
                <!-- Accordion Header -->
                <h2 :id="`accordion-heading-${group.index}`">
                    <button
                        type="button"
                        @click="toggleSection(group.key)"
                        class="flex justify-between items-center py-4 px-4 w-full font-medium leading-none text-left text-gray-900 bg-gray-100 sm:px-5 focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-800 dark:text-white hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                        :class="groupIndex > 0 ? 'border-t border-gray-200 dark:border-gray-700' : ''"
                        :aria-expanded="openSections[group.key]"
                        :aria-controls="`accordion-body-${group.index}`"
                    >
                        <span>{{ group.label }}</span>
                        <svg
                            class="w-6 h-6 shrink-0 transition-transform duration-200"
                            :class="openSections[group.key] ? 'rotate-180' : ''"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </h2>

                <!-- Accordion Body -->
                <div
                    v-if="group.filteredFields && group.filteredFields.length > 0"
                    :id="`accordion-body-${group.index}`"
                    v-show="openSections[group.key]"
                    :aria-labelledby="`accordion-heading-${group.index}`"
                >
                    <div class="p-4 border-gray-200 sm:p-5 dark:border-gray-700">
                        <!-- Address Group (Autocomplete) -->
                        <div v-if="group.is_address && group.filteredFields && hasAddressTags(group)" class="mb-4 grid sm:grid-cols-12 gap-4">
                            <div class="sm:col-span-6">
                            <AddressAutocomplete
                                :street="getAddressFieldValue(group, 'street')"
                                :unit="getAddressFieldValue(group, 'unit')"
                                :city="getAddressFieldValue(group, 'city')"
                                :state="getAddressFieldValue(group, 'state')"
                                :state-code="getAddressFieldValue(group, 'state_code')"
                                :postal-code="getAddressFieldValue(group, 'postal_code')"
                                :country="getAddressFieldValue(group, 'country')"
                                :country-code="getAddressFieldValue(group, 'country_code')"
                                :latitude="getAddressFieldValue(group, 'latitude')"
                                :longitude="getAddressFieldValue(group, 'longitude')"
                                :disabled="!isEditMode && !isCreateMode"
                                @update="(data) => updateAddressFields(group, data)"
                            />
                            </div>
                        </div>


                        <!-- Regular Fields -->
                        <div v-else-if="group.filteredFields" class="grid gap-4 sm:grid-cols-12">
                            <template v-for="field in group.filteredFields" :key="field?.key || `field-${Math.random()}`">
                                <div v-if="field && isFieldVisible(field)"
                                     :class="getFieldColSpan(field)">
                                <label :for="getFieldId(field.key)" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ getFieldLabel(field.key) }}
                                    <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                </label>

                                <!-- View Mode -->
                                <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                    <span v-if="getFieldType(field.key) === 'textarea'" class="whitespace-pre-wrap">
                                        {{ getFieldValue(field.key) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'record'">
                                        {{ getRecordDisplayName(field.key, getFieldValue(field.key)) }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'select' && getFieldDefinition(field.key).enum">
                                        {{ getEnumLabel(field.key, getFieldValue(field.key)) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'tel'">
                                        {{ getFormattedPhoneValue(field.key) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'datetime'">
                                        {{ formatDateTime(getFieldValue(field.key)) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'date'">
                                        {{ formatDate(getFieldValue(field.key)) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'time'">
                                        {{ getFieldValue(field.key) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'rating'">
                                        <div class="flex items-center space-x-1">
                                            <template v-for="star in 5" :key="star">
                                                <svg
                                                    class="w-4 h-4"
                                                    :class="star <= getFieldValue(field.key) ? 'text-yellow-400' : 'text-gray-300'"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                </svg>
                                            </template>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                {{ getFieldValue(field.key) || 0 }}/5
                                            </span>
                                        </div>
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'file'">
                                        <span v-if="getFieldValue(field.key)" class="text-sm text-blue-600 dark:text-blue-400 underline">
                                            {{ getFileName(getFieldValue(field.key)) }}
                                        </span>
                                        <span v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            No file uploaded
                                        </span>
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'morph'">
                                        <span v-if="record && record[getFieldDefinition(field.key).id_field]" class="inline-flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                                                {{ getFieldValue(field.key)?.split('\\').pop() || 'Unknown' }}
                                            </span>
                                            <span class="text-gray-400">→</span>
                                            <span class="text-sm">{{ record.relatable?.display_name || '—' }}</span>
                                        </span>
                                        <span v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            Not assigned
                                        </span>
                                    </span>
                                    <span v-else>
                                        {{ getFieldValue(field.key) || '—' }}
                                    </span>
                                </div>

                                <!-- Edit Mode -->
                                <div v-else>
                                    <!-- Phone Input with Formatting -->
                                    <div v-if="getFieldType(field.key) === 'tel'" class="relative">
                                        <input
                                            :id="getFieldId(field.key)"
                                            type="tel"
                                            :value="getFormattedPhoneValue(field.key)"
                                            @input="handlePhoneInput(field.key, $event)"
                                            @blur="handlePhoneInput(field.key, $event)"
                                            :required="isFieldRequired(field)"
                                            :disabled="isFieldDisabled(field.key)"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="(123) 456-7890"
                                        />
                                    </div>
                                    
                                    <!-- Number Input -->
                                    <input
                                        v-else-if="getFieldType(field.key) === 'number'"
                                        :id="getFieldId(field.key)"
                                        :value="formatNumber(form[field.key])"
                                        @input="handleNumberInput(field.key, $event)"
                                        type="text"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Text/Email Input -->
                                    <input
                                        v-else-if="['text', 'email'].includes(getFieldType(field.key))"
                                        :id="getFieldId(field.key)"
                                        v-model="form[field.key]"
                                        :type="getFieldType(field.key)"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Textarea -->
                                    <textarea
                                        v-else-if="getFieldType(field.key) === 'textarea'"
                                        :id="getFieldId(field.key)"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        rows="4"
                                        class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Record Select (with search modal) -->
                                    <RecordSelect
                                        v-else-if="getFieldType(field.key) === 'record'"
                                        :id="getFieldId(field.key)"
                                        :field="getFieldDefinition(field.key)"
                                        v-model="form[field.key]"
                                        :disabled="isFieldDisabled(field.key)"
                                        :enum-options="getEnumOptions(field.key)"
                                        :record="record"
                                        :field-key="field.key"
                                    />

                                    <!-- Select (enum dropdown) -->
                                    <select
                                        v-else-if="getFieldType(field.key) === 'select'"
                                        :id="getFieldId(field.key)"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        :class="[
                                            'bg-gray-50 border border-gray-300 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500',
                                            !form[field.key] ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900'
                                        ]"
                                    >
                                        <option v-if="!isFieldRequired(field)" value="" disabled>Select {{ getFieldLabel(field.key) }}</option>
                                        <option
                                            v-for="option in getEnumOptions(field.key)"
                                            :key="option.id"
                                            :value="option.id"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>

                                    <!-- DateTime -->
                                    <DateTimeInput
                                        v-else-if="getFieldType(field.key) === 'datetime'"
                                        :id="getFieldId(field.key)"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                    />

                                    <!-- Date -->
                                    <DateInput
                                        v-else-if="getFieldType(field.key) === 'date'"
                                        :id="getFieldId(field.key)"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                    />

                                    <!-- Time -->
                                    <input
                                        v-else-if="getFieldType(field.key) === 'time'"
                                        :id="getFieldId(field.key)"
                                        type="time"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Rating -->
                                    <Rating
                                        v-else-if="getFieldType(field.key) === 'rating'"
                                        v-model="form[field.key]"
                                        :disabled="isFieldDisabled(field.key)"
                                        :show-value="false"
                                    />

                                    <!-- File Input -->
                                    <div v-else-if="getFieldType(field.key) === 'file'" class="space-y-2">
                                        <input
                                            :id="getFieldId(field.key)"
                                            type="file"
                                            @change="handleFileInput(field.key, $event)"
                                            :required="isFieldRequired(field)"
                                            :disabled="isFieldDisabled(field.key)"
                                            :accept="getFieldDefinition(field.key).accept || '*/*'"
                                            class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                                        />
                                        <div v-if="form[field.key] && typeof form[field.key] === 'string'" class="text-sm text-gray-600 dark:text-gray-400">
                                            Current file: <span class="font-medium">{{ getFileName(form[field.key]) }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ getFieldDefinition(field.key).help || 'Select a file to upload' }}
                                        </p>
                                    </div>

                                    <!-- Morph Select (Polymorphic Relationship) -->
                                    <MorphSelect
                                        v-else-if="getFieldType(field.key) === 'morph'"
                                        :id="getFieldId(field.key)"
                                        :field="getFieldDefinition(field.key)"
                                        v-model="form[getFieldDefinition(field.key).id_field]"
                                        v-model:selected-type="form[field.key]"
                                        :disabled="isFieldDisabled(field.key)"
                                    />

                                    <!-- Checkbox -->
                                    <div v-else-if="getFieldType(field.key) === 'checkbox' || getFieldType(field.key) === 'boolean'" class="flex items-center">
                                        <!-- Hidden input to ensure false value is submitted when checkbox is unchecked -->
                                        <input
                                            type="hidden"
                                            :name="field.key"
                                            :value="0"
                                        />
                                        <input
                                            :id="getFieldId(field.key)"
                                            v-model="form[field.key]"
                                            type="checkbox"
                                            :name="field.key"
                                            :true-value="1"
                                            :false-value="0"
                                            :disabled="isFieldDisabled(field.key)"
                                            class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                        />
                                    </div>

                                    <p v-if="form.errors[field.key]" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                        {{ form.errors[field.key] }}
                                    </p>
                                </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions (hidden when formId is provided, allowing parent to control buttons) -->
        <div v-if="isEditMode && !formId" class="flex items-center py-4 px-4 space-x-4 sm:px-5">
            <button
                type="submit"
                :disabled="form.processing || isProcessing"
                class="w-full text-white inline-flex items-center justify-center bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg v-if="form.processing || isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="-ml-1 w-5 h-5 sm:mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                {{ (form.processing || isProcessing) ? 'Processing...' : (isCreateMode ? 'Create' : 'Update') }} {{ recordTitle }}
            </button>
            <button
                type="button"
                @click="handleCancel"
                :disabled="form.processing || isProcessing"
                class="w-full inline-flex justify-center text-gray-500 items-center bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-primary-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
