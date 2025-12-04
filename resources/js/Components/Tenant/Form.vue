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
import Date from '@/Components/Tenant/FormComponents/Date.vue';
import DateTime from '@/Components/Tenant/FormComponents/DateTime.vue';

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

const getFieldDefinition = (fieldKey) => {
    return props.fieldsSchema[fieldKey] || {};
};

const initializeFormData = () => {
    const formData = {};

    if (props.record) {
        Object.assign(formData, props.record);
    }

    if (props.schema) {
        Object.values(props.schema).forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.forEach(field => {
                    if (!(field.key in formData)) {
                        const fieldDef = getFieldDefinition(field.key);
                        const fieldType = fieldDef.type || 'text';
                        if (fieldType === 'select') {
                            // If required and has enum options, auto-select first option
                            if (field.required && fieldDef.enum && props.enumOptions[fieldDef.enum] && props.enumOptions[fieldDef.enum].length > 0) {
                                formData[field.key] = props.enumOptions[fieldDef.enum][0].id;
                            } else {
                                formData[field.key] = null;
                            }
                        } else if (fieldType === 'datetime' || fieldType === 'date') {
                            formData[field.key] = null;
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
            } else {
                form[key] = newRecord[key] ?? '';
            }
        });
    }
}, { deep: true });

const formGroups = computed(() => {
    if (!props.schema) return [];
    return Object.entries(props.schema).map(([key, group], index) => ({
        key,
        index,
        ...group,
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

// Prepare form data with checkbox values converted to 1/0
const prepareFormData = () => {
    const data = { ...form.data() };
    
    // Convert checkbox boolean values to 1/0 for proper submission
    if (props.schema) {
        Object.values(props.schema).forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.forEach(field => {
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
                    Object.values(props.schema).forEach(group => {
                        if (group.fields && Array.isArray(group.fields)) {
                            group.fields.forEach(field => {
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
            form.transform((data) => {
                const transformed = { ...data };
                if (props.schema) {
                    Object.values(props.schema).forEach(group => {
                        if (group.fields && Array.isArray(group.fields)) {
                            group.fields.forEach(field => {
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
                onSuccess: () => emit('submit'),
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
    <form :id="formId || `form-${recordType}-${record?.id || 'new'}`" @submit.prevent="handleSubmit" v-if="schema">
        <!-- Accordion -->
        <div id="accordion-collapse">
            <div v-for="(group, groupIndex) in formGroups" :key="group.key">
                <!-- Accordion Header -->
                <h2 :id="`accordion-heading-${group.index}`">
                    <button
                        type="button"
                        @click="toggleSection(group.key)"
                        class="flex justify-between items-center py-4 px-4 w-full font-medium leading-none text-left text-gray-900 bg-gray-50 sm:px-5 focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-800 dark:text-white hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600"
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
                    :id="`accordion-body-${group.index}`"
                    v-show="openSections[group.key]"
                    :aria-labelledby="`accordion-heading-${group.index}`"
                >
                    <div class="p-4 border-gray-200 sm:p-5 dark:border-gray-700">
                        <!-- Address Group -->
                        <div v-if="group.is_address" class="grid gap-4 sm:grid-cols-2">
                            <div v-for="field in group.fields" :key="field.key"
                                 :class="field.key === 'address_line_1' || field.key === 'address_line_2' ? 'sm:col-span-2' : ''">
                                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ getFieldLabel(field.key) }}
                                    <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                </label>

                                <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                    <span v-if="getFieldType(field.key) === 'tel'">
                                        {{ getFormattedPhoneValue(field.key) || '—' }}
                                    </span>
                                    <span v-else>
                                        {{ getFieldValue(field.key) || '—' }}
                                    </span>
                                </div>

                                <div v-else>
                                    <input
                                        v-model="form[field.key]"
                                        :type="getFieldType(field.key)"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />
                                    <p v-if="form.errors[field.key]" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                        {{ form.errors[field.key] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Regular Fields -->
                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div v-for="field in group.fields" :key="field.key"
                                 :class="getFieldType(field.key) === 'textarea' ? 'sm:col-span-2' : ''">
                                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ getFieldLabel(field.key) }}
                                    <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                </label>

                                <!-- View Mode -->
                                <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                    <span v-if="getFieldType(field.key) === 'textarea'" class="whitespace-pre-wrap">
                                        {{ getFieldValue(field.key) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'select' && getFieldDefinition(field.key).enum">
                                        {{ getEnumLabel(field.key, getFieldValue(field.key)) || '—' }}
                                    </span>
                                    <span v-else-if="getFieldType(field.key) === 'tel'">
                                        {{ getFormattedPhoneValue(field.key) || '—' }}
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
                                    
                                    <!-- Text/Email/Number Input -->
                                    <input
                                        v-else-if="['text', 'email', 'number'].includes(getFieldType(field.key))"
                                        v-model="form[field.key]"
                                        :type="getFieldType(field.key)"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Textarea -->
                                    <textarea
                                        v-else-if="getFieldType(field.key) === 'textarea'"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                        rows="4"
                                        class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    />

                                    <!-- Select -->
                                    <select
                                        v-else-if="getFieldType(field.key) === 'select'"
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
                                    <DateTime
                                        v-else-if="getFieldType(field.key) === 'datetime'"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
                                        :disabled="isFieldDisabled(field.key)"
                                    />

                                    <!-- Date -->
                                    <Date
                                        v-else-if="getFieldType(field.key) === 'date'"
                                        v-model="form[field.key]"
                                        :required="isFieldRequired(field)"
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
