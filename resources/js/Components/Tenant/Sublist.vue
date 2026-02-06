<script setup>
import Modal from '@/Components/Modal.vue';
import Form from '@/Components/Tenant/Form.vue';
import FiltersModal from '@/Components/Tenant/FiltersModal.vue';
import ImageGallery from '@/Components/Tenant/ImageGallery.vue';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    // Parent record information
    parentRecord: {
        type: Object,
        required: true,
    },
    parentDomain: {
        type: String,
        required: true,
    },
    // Sublist configuration
    sublists: {
        type: Array,
        default: () => [],
    },
});


// Sublist State
const activeTab = ref(null);
const sublistData = ref([]);
const sublistTableSchema = ref(null); // Table schema with columns array
const sublistFieldsSchema = ref({}); // Fields schema for data types
const sublistPagination = ref(null);
const isLoadingSublist = ref(false);
const activeFilters = ref([]);
const showFiltersModal = ref(false);
const updatingItems = ref(new Set()); // Track which items are being updated
const defaultFiltersLoaded = ref(false); // Track if default filters have been loaded

// Sublist Create Modal State
const showSublistCreateModal = ref(false);
const sublistCreateFormData = ref(null);
const isLoadingSublistForm = ref(false);

// Sublist Edit Modal State
const showSublistEditModal = ref(false);
const sublistEditRecord = ref(null);
const isLoadingEditRecord = ref(false);

// Cache for sublist schemas (keyed by domain name)
const sublistSchemaCache = ref({});

const formatRouteName = (domain) => {
    // Convert "InventoryUnit" to "inventoryunits.index"
    const lowercase = domain.toLowerCase();
    const plural = lowercase.endsWith('s') ? lowercase : lowercase + 's';
    return `${plural}.index`;
};

// Helper function to get field definition (handles nested fields structure)
const getFieldDef = (fieldKey) => {
    if (!sublistFieldsSchema.value) return null;
    
    // Check if fieldsSchema has a nested 'fields' property (wrapped structure)
    if (sublistFieldsSchema.value.fields) {
        return sublistFieldsSchema.value.fields[fieldKey];
    }
    
    // Otherwise, it's already unwrapped
    return sublistFieldsSchema.value[fieldKey];
};

// Helper functions for formatting table data
const getFieldType = (fieldKey) => {
    const fieldDef = getFieldDef(fieldKey);
    return fieldDef?.type || 'text';
};

const getEnumLabel = (fieldKey, value) => {
    if (!value) return null;
    
    const fieldDef = getFieldDef(fieldKey);
    if (!fieldDef?.enum) return null;
    
    const options = sublistCreateFormData.value?.enumOptions?.[fieldDef.enum];
    if (!options) return null;
    
    const option = options.find(opt => opt.id === value || opt.value === value);
    return option ? option.name : null;
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        
        // Format: Jan 15, 2026
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch (e) {
        return value;
    }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        // Less than 1 minute ago
        if (diffMins < 1 && diffMs >= 0) {
            return 'Just now';
        }
        
        // Less than 60 minutes ago
        if (diffMins < 60 && diffMs >= 0) {
            return `${diffMins} ${diffMins === 1 ? 'minute' : 'minutes'} ago`;
        }
        
        // Less than 24 hours ago
        if (diffHours < 24 && diffMs >= 0) {
            return `${diffHours} ${diffHours === 1 ? 'hour' : 'hours'} ago`;
        }
        
        // Less than 7 days ago
        if (diffDays < 7 && diffMs >= 0) {
            return `${diffDays} ${diffDays === 1 ? 'day' : 'days'} ago`;
        }
        
        // Older or future dates: Jan 15, 2026 at 3:45 PM
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        return value;
    }
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

// Get the display value for a record field
const getRecordDisplayValue = (item, fieldKey) => {
    const fieldDef = getFieldDef(fieldKey);
    if (!fieldDef || fieldDef.type !== 'record') {
        return null;
    }
    
    // Get the relationship name
    const relationshipName = fieldDef.relationship || fieldKey.replace('_id', '');
    const relatedRecord = item[relationshipName];
    
    if (!relatedRecord || typeof relatedRecord !== 'object') {
        return null;
    }
    
    // Use displayField from field definition, or default to display_name
    const displayField = fieldDef.displayField || 'display_name';
    const displayValue = relatedRecord[displayField];
    
    // Fallback to display_name if displayField doesn't exist
    return displayValue || relatedRecord.display_name || null;
};

// Get the URL for a record
const getRecordUrl = (item, fieldKey) => {
    const fieldDef = getFieldDef(fieldKey);
    if (!fieldDef || fieldDef.type !== 'record') return null;
    
    // Get the relationship name and related record
    const relationshipName = fieldDef.relationship || fieldKey.replace('_id', '');
    const relatedRecord = item[relationshipName];
    
    if (!relatedRecord || !relatedRecord.id) return null;
    
    // Build the route name from the typeDomain
    const domain = fieldDef.typeDomain;
    const lowercase = domain.toLowerCase();
    const plural = lowercase.endsWith('s') ? lowercase : lowercase + 's';
    const routeName = `${plural}.show`;
    
    // Get the param name (singular form)
    const paramName = lowercase.replace(/s$/, '');
    
    try {
        return route(routeName, { [paramName]: relatedRecord.id });
    } catch (e) {
        console.error(`Could not generate route for ${routeName}:`, e);
        return null;
    }
};

// Filter management functions
const applyFilters = (filters) => {
    activeFilters.value = filters;
    showFiltersModal.value = false;

    // Refetch data with new filters (skip for image galleries)
    if (activeTab.value && !activeTab.value.modelRelationship) {
        fetchSublistData(activeTab.value);
    }
};

const removeFilter = (index) => {
    const newFilters = [...activeFilters.value];
    newFilters.splice(index, 1);
    applyFilters(newFilters);
};

const clearAllFilters = () => {
    applyFilters([]);
};

const getFilterLabel = (filter) => {
    const fieldConfig = getFieldDef(filter.field) || {};
    const fieldLabel = fieldConfig.label || filter.field;
    
    let valueLabel = '';
    if (filter.operator === 'between') {
        if (typeof filter.value === 'object') {
            valueLabel = `${filter.value.start || filter.value.min} - ${filter.value.end || filter.value.max}`;
        }
    } else if (['is_empty', 'is_not_empty', 'today', 'this_week', 'this_month', 'is_true', 'is_false'].includes(filter.operator)) {
        valueLabel = '';
    } else {
        // For select fields, get the label from enum options
        if (fieldConfig.enum && sublistCreateFormData.value?.enumOptions?.[fieldConfig.enum]) {
            const option = sublistCreateFormData.value.enumOptions[fieldConfig.enum].find(opt => 
                String(opt.id) === String(filter.value) || String(opt.value) === String(filter.value)
            );
            valueLabel = option ? option.name : filter.value;
        } else {
            valueLabel = filter.value;
        }
    }
    
    const operatorLabels = {
        'contains': 'contains',
        'equals': 'is',
        'starts_with': 'starts with',
        'ends_with': 'ends with',
        'is_empty': 'is empty',
        'is_not_empty': 'is not empty',
        'not_equals': 'is not',
        'any_of': 'is any of',
        'none_of': 'is none of',
        'before': 'before',
        'after': 'after',
        'between': 'between',
        'today': 'is today',
        'this_week': 'is this week',
        'this_month': 'is this month',
        'greater_than': 'greater than',
        'less_than': 'less than',
        'is_true': 'is true',
        'is_false': 'is false',
    };
    
    const operatorLabel = operatorLabels[filter.operator] || filter.operator;
    
    return `${fieldLabel} ${operatorLabel}${valueLabel ? ` ${valueLabel}` : ''}`;
};

// Computed property for table columns
const tableColumns = computed(() => {
    return sublistTableSchema.value?.columns || [];
});

const fetchSublistData = async (sublist, page = 1) => {
    if (!sublist) return;

    // Skip data fetching for image galleries (polymorphic relationships)
    // The ImageGallery component handles its own data
    if (sublist.modelRelationship) {
        return;
    }

    isLoadingSublist.value = true;
    try {
        const routeName = formatRouteName(sublist.domain);
        
        // Find the foreign key field from the loaded schema
        let foreignKey = null;
        if (sublistCreateFormData.value?.fieldsSchema) {
            foreignKey = findParentReferenceFieldFromSchema(sublistCreateFormData.value.fieldsSchema);
        }
        
        // If we couldn't find a matching field, log error and return
        if (!foreignKey) {
            console.error(`No field found in ${sublist.domain} that references ${props.parentDomain}`);
            sublistData.value = [];
            isLoadingSublist.value = false;
            return;
        }
        
        // Build filters - always use structured format for consistency
        const allFilters = [
            // Parent filter (always include to filter by parent record)
            { field: foreignKey, operator: 'equals', value: props.parentRecord.id },
            // Add all active filters
            ...activeFilters.value
        ];

        const response = await axios.get(route(routeName), {
            params: {
                filters: JSON.stringify(allFilters),
                page: page,
                per_page: 10
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        sublistData.value = response.data.records || [];
        sublistTableSchema.value = response.data.schema || null; // Table schema with columns array
        sublistFieldsSchema.value = response.data.fieldsSchema || {}; // Fields schema for data types
        sublistPagination.value = response.data.meta;
        
        // Load default filters from schema on first load only
        if (!defaultFiltersLoaded.value && activeFilters.value.length === 0 && sublistTableSchema.value?.filters) {
            defaultFiltersLoaded.value = true;
            // Ensure each filter has an id for UI management
            activeFilters.value = sublistTableSchema.value.filters.map((f, index) => ({
                ...f,
                id: f.id || `default-${index}-${Date.now()}`
            }));
            // Refetch with default filters applied
            fetchSublistData(sublist, page);
            return;
        }

    } catch (error) {
        console.error('Error fetching sublist data:', error);
        sublistData.value = [];
    } finally {
        isLoadingSublist.value = false;
    }
};

/**
 * Find the field key that references the parent domain from a fields schema
 */
const findParentReferenceFieldFromSchema = (fieldsSchema) => {
    // Handle wrapped schema structure
    const fields = fieldsSchema.fields || fieldsSchema;
    
    for (const [fieldKey, fieldDef] of Object.entries(fields)) {
        if (fieldDef && fieldDef.typeDomain === props.parentDomain) {
            return fieldKey;
        }
    }
    
    console.error(`[Sublist] No field found with typeDomain matching ${props.parentDomain}`);
    return null;
};

/**
 * Get fields from the sublist schema that reference the parent domain
 * Returns an array of field keys
 */
const getParentReferenceFields = () => {
    if (!sublistCreateFormData.value?.fieldsSchema) {
        return [];
    }
    
    // Handle wrapped schema structure
    const fields = sublistCreateFormData.value.fieldsSchema.fields || sublistCreateFormData.value.fieldsSchema;
    const parentReferenceFields = [];
    
    for (const [fieldKey, fieldDef] of Object.entries(fields)) {
        // Check if this field's typeDomain matches the parent domain
        if (fieldDef.typeDomain === props.parentDomain) {
            parentReferenceFields.push({
                key: fieldKey,
                definition: fieldDef,
                relationshipName: fieldDef.relationship || fieldKey.replace('_id', '')
            });
        }
    }
    
    return parentReferenceFields;
};

/**
 * Parse auto-fill source notation (e.g., "InventoryItem.default_cost")
 * Returns { domain, field } or null if invalid
 */
const parseAutoFillSource = (source) => {
    if (!source || typeof source !== 'string') return null;
    
    const parts = source.split('.');
    if (parts.length !== 2) return null;
    
    return {
        domain: parts[0],
        field: parts[1]
    };
};

/**
 * Get initial data for sublist creation
 * Automatically sets fields that reference the parent domain
 * Also handles auto_fill configuration from sublistConditions
 */
const getSublistInitialData = () => {
    const initialData = {};
    const parentReferenceFields = getParentReferenceFields();
    
    // 1. Auto-fill parent reference fields
    for (const field of parentReferenceFields) {
        // Set the field ID
        initialData[field.key] = props.parentRecord.id;
        
        // Set the relationship data for RecordSelect display
        initialData[field.relationshipName] = {
            id: props.parentRecord.id,
            display_name: props.parentRecord.display_name,
            // Include all parent record fields for conditionals
            ...props.parentRecord
        };
    }
    
    // 2. Handle auto_fill configuration from sublistConditions
    const formSchema = sublistCreateFormData.value?.formSchema;
    if (formSchema?.sublistConditions?.auto_fill) {
        const autoFillConfig = formSchema.sublistConditions.auto_fill;
        
        for (const [targetField, source] of Object.entries(autoFillConfig)) {
            const parsed = parseAutoFillSource(source);
            
            if (!parsed) {
                console.warn(`[Sublist Auto-fill] Invalid format: ${source}`);
                continue;
            }
            
            // Check if the source domain matches the parent domain
            if (parsed.domain !== props.parentDomain) {
                continue; // Skip silently - different parent domain
            }
            
            // Get the value from the parent record
            const sourceValue = props.parentRecord[parsed.field];
            
            if (sourceValue !== undefined && sourceValue !== null) {
                initialData[targetField] = sourceValue;
            }
        }
    }
    
    return initialData;
};

/**
 * Get modified fields schema for sublist creation
 * Disables fields that reference the parent domain (they're auto-filled)
 */
const getSublistFieldsSchema = () => {
    if (!sublistCreateFormData.value?.fieldsSchema) return {};

    const fieldsSchema = { ...sublistCreateFormData.value.fieldsSchema };
    const parentReferenceFields = getParentReferenceFields();
    
    // Disable all fields that reference the parent domain
    for (const field of parentReferenceFields) {
        if (fieldsSchema[field.key]) {
            fieldsSchema[field.key] = {
                ...fieldsSchema[field.key],
                disabled: true,
                locked: true
            };
        }
    }
    
    return fieldsSchema;
};

/**
 * Get modified enum options for sublist creation
 * Injects the parent record into the options for parent reference fields
 */
const getSublistEnumOptions = () => {
    if (!sublistCreateFormData.value?.enumOptions) return {};

    const enumOptions = { ...sublistCreateFormData.value.enumOptions };
    const parentReferenceFields = getParentReferenceFields();
    
    // Inject the parent record into enumOptions for each parent reference field
    for (const field of parentReferenceFields) {
        // The enum options key for record fields is the field key itself
        // Ensure the parent record is in the options array
        const parentRecordOption = {
            id: props.parentRecord.id,
            value: props.parentRecord.id,
            name: props.parentRecord.display_name,
            display_name: props.parentRecord.display_name
        };
        
        // If there are existing options, add to them; otherwise create new array
        if (enumOptions[field.key]) {
            // Check if parent is already in options
            const existingIndex = enumOptions[field.key].findIndex(opt => opt.id === props.parentRecord.id);
            if (existingIndex === -1) {
                enumOptions[field.key] = [parentRecordOption, ...enumOptions[field.key]];
            }
        } else {
            enumOptions[field.key] = [parentRecordOption];
        }
    }
    
    return enumOptions;
};


const handleTabChange = async (sublist) => {
    activeTab.value = sublist;
    
    // Reset filters and state for new tab
    activeFilters.value = [];
    defaultFiltersLoaded.value = false;
    
    // For image galleries (polymorphic relationships), skip normal data fetching
    // The ImageGallery component handles its own data
    if (sublist.modelRelationship) {
        return;
    }
    
    // Load the schema for this sublist (includes enum options for inline editing)
    await loadSublistSchema(sublist);
    
    // Then fetch the data
    fetchSublistData(sublist);
};

/**
 * Load the schema for a sublist domain
 * This is needed to find the correct foreign key field
 */
const loadSublistSchema = async (sublist) => {
    if (!sublist) return;
    
    const domain = sublist.domain;
    
    // Check cache first
    if (sublistSchemaCache.value[domain]) {
        sublistCreateFormData.value = sublistSchemaCache.value[domain];
        return;
    }
    
    try {
        const response = await axios.get(route('records.select-form', { type: domain }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        
        // Cache it
        sublistSchemaCache.value[domain] = response.data;
        sublistCreateFormData.value = response.data;
    } catch (error) {
        console.error('[Sublist] Error loading schema:', error);
    }
};

const openSublistCreateModal = async () => {
    if (!sublistCreateFormData.value && activeTab.value) {
        await loadSublistSchema(activeTab.value);
    }
    showSublistCreateModal.value = true;
};

const closeSublistCreateModal = () => {
    showSublistCreateModal.value = false;
};

const handleSublistItemCreated = (recordId) => {
    // Skip data fetching for image galleries (they handle their own updates)
    if (activeTab.value && !activeTab.value.modelRelationship) {
        fetchSublistData(activeTab.value);
    }
    showSublistCreateModal.value = false;
    // Don't reset sublistCreateFormData - we're caching it now
};

// Edit modal functions
const openSublistEditModal = async (item) => {
    // Show modal immediately with loading state
    showSublistEditModal.value = true;
    isLoadingEditRecord.value = true;
    
    if (!sublistCreateFormData.value && activeTab.value) {
        await loadSublistSchema(activeTab.value);
    }
    
    // Fetch the full record with all fields (not just table columns)
    try {
        const routeBase = activeTab.value.domain.toLowerCase();
        const routePlural = routeBase.endsWith('s') ? routeBase : routeBase + 's';
        const routeName = `${routePlural}.show`;
        const paramName = routePlural.replace(/s$/, '');
        
        const url = route(routeName, { [paramName]: item.id });
        
        const response = await axios.get(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        // Use the full record data from the API
        sublistEditRecord.value = response.data.record || item;
    } catch (error) {
        console.error('[Sublist] Error fetching full record:', error);
        // Fallback to using the table data
        sublistEditRecord.value = item;
    } finally {
        isLoadingEditRecord.value = false;
    }
};

const closeSublistEditModal = () => {
    showSublistEditModal.value = false;
    sublistEditRecord.value = null;
};

const handleSublistItemUpdated = () => {
    if (activeTab.value) {
        fetchSublistData(activeTab.value, sublistPagination.value?.current_page || 1);
    }
    showSublistEditModal.value = false;
    sublistEditRecord.value = null;
};

// Navigate to record in new window
const navigateToRecord = (item) => {
    if (!activeTab.value) return;
    
    const routeBase = activeTab.value.domain.toLowerCase();
    const routePlural = routeBase.endsWith('s') ? routeBase : routeBase + 's';
    const routeName = `${routePlural}.show`;
    const paramName = routePlural.replace(/s$/, '');
    
    try {
        const url = route(routeName, { [paramName]: item.id });
        window.open(url, '_blank');
    } catch (error) {
        console.error('[Sublist] Error generating route:', error);
    }
};

// Inline editing for select fields
const handleInlineSelectChange = async (item, fieldKey, newValue) => {
    if (!activeTab.value) return;
    
    // Don't update if value is the same
    if (item[fieldKey] == newValue) {
        return;
    }
    
    // Add global row loading state
    const rowUpdateKey = `${item.id}-updating`;
    updatingItems.value.add(rowUpdateKey);
    
    try {
        // Convert InventoryUnit -> inventoryunits
        const routeBase = activeTab.value.domain.toLowerCase();
        const routePlural = routeBase.endsWith('s') ? routeBase : routeBase + 's';
        const routeName = `${routePlural}.update`;
        
        // Route param is singular: inventoryunit
        const paramName = routePlural.replace(/s$/, '');
        
        const updateUrl = route(routeName, { [paramName]: item.id });
        
        // Convert string values to integers for enum fields
        const fieldDef = getFieldDef(fieldKey);
        let processedValue = newValue;
        if (fieldDef?.type === 'select' && fieldDef?.enum) {
            processedValue = parseInt(newValue, 10);
        }
        
        const response = await axios.put(updateUrl, {
            [fieldKey]: processedValue
        }, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        // Update local data with response data (includes relationships)
        if (response.data?.record) {
            const itemIndex = sublistData.value.findIndex(i => i.id === item.id);
            if (itemIndex !== -1) {
                sublistData.value[itemIndex] = response.data.record;
            }
        } else {
            // Fallback: just update the field
            const itemIndex = sublistData.value.findIndex(i => i.id === item.id);
            if (itemIndex !== -1) {
                sublistData.value[itemIndex][fieldKey] = processedValue;
            }
        }
    } catch (error) {
        console.error('[Sublist] Error updating field:', error);
        // Revert the change by refetching data (skip for image galleries)
        if (activeTab.value && !activeTab.value.modelRelationship) {
            await fetchSublistData(activeTab.value, sublistPagination.value?.current_page || 1);
        }
    } finally {
        updatingItems.value.delete(rowUpdateKey);
    }
};

// Get enum options for a field
const getEnumOptions = (fieldKey) => {
    const fieldDef = getFieldDef(fieldKey);
    if (!fieldDef?.enum) {
        return [];
    }
    
    return sublistCreateFormData.value?.enumOptions?.[fieldDef.enum] || [];
};

// Initialize first tab if available
onMounted(() => {
    if (props.sublists.length > 0) {
        handleTabChange(props.sublists[0]);
    }
});
</script>

<template>
    <div class="w-full">
        <!-- Mobile Select Dropdown -->
        <div class="sm:hidden">
            <label for="tabs" class="sr-only">Select Sublist</label>
            <select 
                id="tabs" 
                class="input-style bg-primary-600" 
                @change="handleTabChange(sublists[$event.target.selectedIndex])"
            >
                <option 
                    v-for="(sublist, index) in sublists" 
                    :key="index" 
                    :selected="activeTab === sublist"
                >
                    {{ sublist.label }}
                </option>
            </select>
        </div>

        <!-- Sublist Container -->
        <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg w-full overflow-hidden">
            <!-- Header -->
            <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                Related Records
            </div>

            <!-- Tabs -->
            <div class="p-4 sm:p-5 space-x-2 border-b border-gray-200 dark:border-gray-600">
                <ul class="record-sublist">
                    <li v-for="(sublist, index) in sublists" :key="index" class="me-2">
                        <a 
                            href="#" 
                            @click.prevent="handleTabChange(sublist)"
                            class="record-sublist-item"
                            :class="{ 'active': activeTab === sublist }"
                        >
                            {{ sublist.label }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Content Area -->
            <div class="relative min-h-[200px]">
                <!-- Loading Spinner -->
                <div 
                    v-if="isLoadingSublist" 
                    class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 z-10"
                >
                    <svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                
                <!-- Image Gallery (for polymorphic image relationships) -->
                <div v-if="activeTab?.modelRelationship" class="p-4 sm:p-5">
                    <ImageGallery
                        :parent-id="parentRecord.id"
                        :parent-type="parentDomain"
                        :domain="activeTab.domain"
                        :model-relationship="activeTab.modelRelationship"
                    />
                </div>
                
                <!-- Data Table -->
                <div v-else-if="sublistData.length > 0 || activeFilters.length > 0">
                    <!-- Toolbar -->
                    <div class="flex flex-col gap-3 p-4 sm:p-5 ">
                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center">
                            <button
                                @click="showFiltersModal = true"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filters
                                <span v-if="activeFilters.length > 0" class="ml-2 px-1.5 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-full">
                                    {{ activeFilters.length }}
                                </span>
                            </button>
                            
                            <button
                                @click="openSublistCreateModal"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add New {{ activeTab?.label || 'Record' }}
                            </button>
                        </div>
                        
                        <!-- Active Filters -->
                        <div v-if="activeFilters.length > 0" class="flex flex-wrap items-center gap-2 mt-2">
                            <span
                                v-for="(filter, index) in activeFilters"
                                :key="index"
                                class="inline-flex items-center bg-primary-100 border border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 text-xs font-medium ps-1.5 pe-0.5 py-0.5 rounded gap-1"
                            >
                                <span>{{ getFilterLabel(filter) }}</span>
                                <button
                                    @click="removeFilter(index)"
                                    type="button"
                                    class="inline-flex items-center p-0.5 text-sm bg-transparent rounded-xs hover:bg-primary-200 dark:hover:bg-primary-800/50"
                                    aria-label="Remove"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                            <button
                                @click="clearAllFilters"
                                class="text-xs text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 underline"
                            >
                                Clear all
                            </button>
                        </div>
                    </div>

                    <div v-if="sublistData.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th 
                                    v-for="column in tableColumns" 
                                    :key="column.key" 
                                    class="px-6 py-3"
                                >
                                    {{ column.label }}
                                </th>
                                
                                <th class="px-6 py-3 text-right ">
                                    <!-- Actions -->
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="item in sublistData" 
                                :key="item.id" 
                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 relative"
                                :class="{ 'opacity-50 pointer-events-none': updatingItems.has(`${item.id}-updating`) }"
                            >

                                <td 
                                    v-for="column in tableColumns" 
                                    :key="column.key" 
                                    class="px-6 py-4 relative"
                                >
                                    <!-- Record type field with link -->
                                    <template v-if="getFieldType(column.key) === 'record'">
                                        <a 
                                            v-if="getRecordUrl(item, column.key)"
                                            :href="getRecordUrl(item, column.key)"
                                            class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline"
                                            target="_blank"
                                        >
                                            {{ getRecordDisplayValue(item, column.key) || '—' }}
                                        </a>
                                        <span v-else>
                                            {{ getRecordDisplayValue(item, column.key) || '—' }}
                                        </span>
                                    </template>
                                    <template v-else-if="getFieldType(column.key) === 'boolean'">
                                        {{ item[column.key] ? 'Yes' : 'No' }}
                                    </template>
                                    <template v-else-if="getFieldType(column.key) === 'date'">
                                        {{ formatDate(item[column.key]) }}
                                    </template>
                                    <template v-else-if="getFieldType(column.key) === 'datetime'">
                                        {{ formatDateTime(item[column.key]) }}
                                    </template>
                                    <template v-else-if="getFieldType(column.key) === 'currency'">
                                        {{ formatCurrency(item[column.key]) }}
                                    </template>
                                    <template v-else-if="getFieldType(column.key) === 'select'">
                                        <div class="relative inline-block">
                                            <!-- Badge Display -->
                                            <span 
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium border cursor-pointer transition-colors"
                                                :class="[
                                                    'bg-primary-50 text-primary-700 border-primary-300',
                                                    'dark:bg-primary-900/30 dark:text-primary-400 dark:border-primary-700',
                                                    'hover:bg-primary-100 dark:hover:bg-primary-900/50'
                                                ]"
                                            >
                                                {{ getEnumLabel(column.key, item[column.key]) || '—' }}
                                            </span>
                                            <!-- Hidden Select Overlay -->
                                            <select
                                                :value="item[column.key]"
                                                @change="handleInlineSelectChange(item, column.key, $event.target.value)"
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            >
                                                <option 
                                                    v-for="option in getEnumOptions(column.key)" 
                                                    :key="option.id || option.value" 
                                                    :value="option.id || option.value"
                                                >
                                                    {{ option.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </template>
                                    <template v-else>
                                        {{ item[column.key] || '—' }}
                                    </template>
                                </td>
                                <!-- Actions Column -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Edit Button -->
                                        <button
                                            @click="openSublistEditModal(item)"
                                            class="inline-flex items-center justify-center w-8 h-8 text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                                            title="Edit"
                                        >
                                            <i class="material-icons text-[18px]">edit</i>
                                        </button>
                                        
                                        <!-- Navigate Button -->
                                        <button
                                            @click="navigateToRecord(item)"
                                            class="inline-flex items-center justify-center w-8 h-8 text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                                            title="Open in new tab"
                                        >
                                            <i class="material-icons text-[18px]">open_in_new</i>
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        </tbody>
                    </table>
                    </div>
                    
                    <!-- No results with filters -->
                    <div v-else class="text-center py-8">
                        <div class="text-gray-500 dark:text-gray-400 mb-4">
                            No records match your filters.
                        </div>
                        <button
                            @click="clearAllFilters"
                            class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 underline"
                        >
                            Clear filters
                        </button>
                    </div>
                    
                    <!-- Pagination -->
                    <div 
                        v-if="sublistPagination && sublistPagination.last_page > 1" 
                        class="flex justify-between items-center mt-4"
                    >
                        <button
                            @click="activeTab && !activeTab.modelRelationship && fetchSublistData(activeTab, sublistPagination.current_page - 1)"
                            :disabled="sublistPagination.current_page === 1"
                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Page {{ sublistPagination.current_page }} of {{ sublistPagination.last_page }}
                        </span>
                        <button
                            @click="activeTab && !activeTab.modelRelationship && fetchSublistData(activeTab, sublistPagination.current_page + 1)"
                            :disabled="sublistPagination.current_page === sublistPagination.last_page"
                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>

                <!-- Empty State (for non-image-gallery sublists) -->
                <div v-else-if="!isLoadingSublist && !activeTab?.modelRelationship" class="text-center py-8">
                    <div class="text-gray-500 dark:text-gray-400 mb-4">
                        No {{ activeTab?.label || 'records' }} found.
                    </div>
                    <button
                        @click="openSublistCreateModal"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New {{ activeTab?.label || 'Record' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sublist Create Modal -->
    <Modal :show="showSublistCreateModal" @close="closeSublistCreateModal" :max-width="'4xl'">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Create New {{ activeTab?.label || 'Record' }}
            </h3>
            <button
                @click="closeSublistCreateModal"
                type="button"
                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto ">
            <div v-if="isLoadingSublistForm" class="flex justify-center py-8">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <Form
                v-else-if="sublistCreateFormData"
                :schema="sublistCreateFormData.formSchema"
                :fields-schema="getSublistFieldsSchema()"
                :record="null"
                :record-type="sublistCreateFormData.recordType"
                :record-title="activeTab?.label || 'Record'"
                :enum-options="getSublistEnumOptions()"
                :initial-data="getSublistInitialData()"
                mode="create"
                :prevent-redirect="true"
                @created="handleSublistItemCreated"
                @cancel="closeSublistCreateModal"
            />
        </div>
    </Modal>
    
    <!-- Sublist Edit Modal -->
    <Modal :show="showSublistEditModal" @close="closeSublistEditModal" :max-width="'4xl'">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Edit {{ activeTab?.label || 'Record' }}
            </h3>
            <button
                @click="closeSublistEditModal"
                type="button"
                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto ">
            <div v-if="isLoadingEditRecord" class="flex justify-center py-8">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <Form
                v-else-if="sublistCreateFormData && sublistEditRecord"
                :schema="sublistCreateFormData.formSchema"
                :fields-schema="getSublistFieldsSchema()"
                :record="sublistEditRecord"
                :record-type="sublistCreateFormData.recordType"
                :enum-options="sublistCreateFormData.enumOptions"
                :image-urls="sublistCreateFormData.imageUrls || {}"
                :prevent-redirect="true"
                :mode="'edit'"
                @updated="handleSublistItemUpdated"
                @cancel="closeSublistEditModal"
            />
        </div>
    </Modal>
    
    <!-- Filters Modal -->
    <Modal :show="showFiltersModal" @close="showFiltersModal = false" max-width="4xl">
        <div class="p-6">
            <FiltersModal
                v-if="sublistFieldsSchema && Object.keys(sublistFieldsSchema).length > 0"
                :fields-schema="sublistFieldsSchema"
                :enum-options="sublistCreateFormData?.enumOptions || {}"
                :columns="tableColumns"
                :active-filters="activeFilters"
                @apply="applyFilters"
                @close="showFiltersModal = false"
            />
        </div>
    </Modal>
</template>
