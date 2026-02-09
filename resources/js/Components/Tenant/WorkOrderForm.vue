<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        default: null
    },
    recordType: {
        type: String,
        required: true
    },
    formSchema: {
        type: Object,
        required: true
    },
    fieldsSchema: {
        type: Object,
        required: true
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    },
    mode: {
        type: String,
        default: 'create', // 'create', 'edit', 'show'
        validator: (value) => ['create', 'edit', 'show'].includes(value)
    }
});

const emit = defineEmits(['saved', 'cancelled']);

const pluralTitle = computed(() => {
    // Convert WorkOrder to Work Orders
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

// Helper functions
const getEnumOptions = (fieldKey) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
};

const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const isFieldRequired = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.required === true;
};

const isFieldDisabled = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.disabled === true;
};

const isFieldReadonly = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.readOnly === true || props.mode === 'show';
};

const isFieldDisabledByFilter = (fieldKey) => {
    const field = props.fieldsSchema[fieldKey];
    if (field && field.filterby) {
        // Check if the filter field has a value
        const filterFieldValue = form[field.filterby];
        return !filterFieldValue || filterFieldValue === '';
    }
    return false;
};

const getFieldFilterValue = (fieldKey) => {
    const field = props.fieldsSchema[fieldKey];
    if (field && field.filterby) {
        return form[field.filterby] || null;
    }
    return null;
};

const formatCurrency = (value) => {
    return value != null ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '$0.00';
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
};

// Initialize form with schema-driven defaults or existing record data
const formData = {};
Object.keys(props.fieldsSchema).forEach(key => {
    const field = props.fieldsSchema[key];

    // Use existing record data if in edit mode
    if (props.record && props.record[key] !== undefined) {
        formData[key] = props.record[key];
    } else if (field.default !== undefined && field.default !== null) {
        formData[key] = field.default;
    } else if (field.default_value !== undefined && field.default_value !== null) {
        formData[key] = field.default_value;
    } else if (field.type === 'boolean' || field.type === 'checkbox') {
        formData[key] = false;
    } else if (field.type === 'select') {
        // For select fields with enums, use explicit default or auto-select first option
        const enumOptions = getEnumOptions(key);
        if (enumOptions && enumOptions.length > 0) {
            // Check if there's an explicit default value
            if (field.default !== undefined && field.default !== null) {
                // Find the option with matching value
                const defaultOption = enumOptions.find(opt => opt.value === field.default);
                if (defaultOption) {
                    formData[key] = defaultOption.id;
                } else {
                    formData[key] = enumOptions[0].id;
                }
            } else {
                formData[key] = enumOptions[0].id;
            }
        } else {
            formData[key] = null;
        }
    } else if (field.type === 'record') {
        formData[key] = null;
    } else {
        formData[key] = '';
    }
});

const form = useForm(formData);

// Watch for subsidiary changes to clear dependent fields
watch(() => form.subsidiary_id, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        // Clear location when subsidiary changes
        form.location_id = null;
    }
});

// Watch for asset unit changes to auto-populate customer
watch(() => form.asset_unit_id, async (newValue, oldValue) => {
    if (newValue && newValue !== oldValue) {
        try {
            // Fetch the asset unit data to get its customer
            const response = await axios.get(route('assetunits.show', newValue));
            const assetUnit = response.data;

            // If the asset unit has a customer and we don't have one set, auto-populate it
            if (assetUnit.customer_id && !form.customer_id) {
                form.customer_id = assetUnit.customer_id;
            }
        } catch (error) {
            console.error('Failed to fetch asset unit data:', error);
        }
    }
});

// Initialize form data properly for asset_unit_id
if (props.record && props.record.asset_unit_id !== undefined) {
    formData.asset_unit_id = props.record.asset_unit_id;
}

const submit = () => {
    // Ensure all form data is sent by using transform
    form.transform((data) => {
        const allData = { ...form.data() };
        // Override with the current values to ensure everything is sent
        Object.keys(allData).forEach(key => {
            if (form[key] !== undefined) {
                allData[key] = form[key];
            }
        });
        return allData;
    });

    console.log('Submitting form data:', form.data());
    console.log('Route:', props.mode === 'edit' ? route('workorders.update', props.record.id) : route('workorders.store'));

    if (props.mode === 'edit') {
        form.put(route('workorders.update', props.record.id), {
            onSuccess: (page) => {
                console.log('Update successful', page);
                // Redirect to show page after successful update
                window.location.href = route('workorders.show', props.record.id);
            },
            onError: (errors) => {
                console.error('Update failed with errors:', errors);
            }
        });
    } else {
        form.post(route('workorders.store'), {
            onSuccess: (page) => {
                console.log('Create successful', page);
                // Redirect to show page after successful creation
                if (page.props.workorder) {
                    window.location.href = route('workorders.show', page.props.workorder.id);
                }
            },
            onError: (errors) => {
                console.error('Create failed with errors:', errors);
            }
        });
    }
};

const saveDraft = () => {
    form.draft = true;
    form.status = 1; // Draft status ID
    submit();
};

const saveAndOpen = () => {
    form.draft = false;
    form.status = 2; // Open status ID
    submit();
};

const handleCancel = () => {
    emit('cancelled');
};
</script>

<template>
        <div class="w-full flex flex-col space-y-4 md:space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-4 lg:grid-cols-12">
            <!-- Main Work Order Form -->
            <div
                :class="{
                    'lg:col-span-9': mode !== 'show',
                    'lg:col-span-12': mode === 'show',
                    'space-y-6': true
                }"
            >
                <!-- Work Order Header -->
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-white">{{ mode === 'edit' ? 'EDIT' : 'WORK ORDER' }}</h1>
                                <p class="text-blue-100 text-sm mt-1">{{ mode === 'edit' ? 'Update Work Order' : 'Service Request Form' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-sm font-medium">WO #</div>
                                <div class="text-white text-lg font-mono">{{ form.work_order_number || record?.work_order_number || 'Auto-generated' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Customer & Unit Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Customer Information
                                </h3>

                                <!-- Customer Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.customer_id?.label || 'Customer' }} {{ isFieldRequired('customer_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'customer_id'"
                                        :field="fieldsSchema.customer_id"
                                        v-model="form.customer_id"
                                        :disabled="isFieldReadonly('customer_id')"
                                        :enum-options="getEnumOptions('customer_id')"
                                        field-key="customer_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.customer?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Asset Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.asset_unit_id?.label || 'Asset' }}
                                        {{ isFieldRequired('asset_unit_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'asset_unit_id'"
                                        :field="fieldsSchema.asset_unit_id"
                                        v-model="form.asset_unit_id"
                                        :disabled="isFieldReadonly('asset_unit_id')"
                                        :enum-options="getEnumOptions('asset_unit_id')"
                                        field-key="asset_unit_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.asset_unit?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Subsidiary Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.subsidiary_id?.label || 'Subsidiary' }}
                                        {{ isFieldRequired('subsidiary_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'subsidiary_id'"
                                        :field="fieldsSchema.subsidiary_id"
                                        v-model="form.subsidiary_id"
                                        :disabled="isFieldReadonly('subsidiary_id')"
                                        :enum-options="getEnumOptions('subsidiary_id')"
                                        field-key="subsidiary_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.subsidiary?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Location Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.location_id?.label || 'Location' }}
                                        {{ isFieldRequired('location_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'location_id'"
                                        :field="fieldsSchema.location_id"
                                        v-model="form.location_id"
                                        :disabled="isFieldReadonly('location_id') || isFieldDisabledByFilter('location_id')"
                                        :enum-options="getEnumOptions('location_id')"
                                        field-key="location_id"
                                        :filter-by="fieldsSchema.location_id.filterby || null"
                                        :filter-value="getFieldFilterValue('location_id')"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.location?.display_name || '—' }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Assignment & Scheduling
                                </h3>

                                <!-- Assigned User Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.assigned_user_id?.label || 'Assigned Technician' }}
                                        {{ isFieldRequired('assigned_user_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'assigned_user_id'"
                                        :field="fieldsSchema.assigned_user_id"
                                        v-model="form.assigned_user_id"
                                        :disabled="isFieldReadonly('assigned_user_id')"
                                        :enum-options="getEnumOptions('assigned_user_id')"
                                        field-key="assigned_user_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.assigned_user?.name || 'Unassigned' }}
                                    </p>
                                </div>

                                <!-- Scheduled Date/Time -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.scheduled_start_at?.label || 'Scheduled Date/Time' }}
                                        {{ isFieldRequired('scheduled_start_at') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.scheduled_start_at"
                                        type="datetime-local"
                                        :readonly="isFieldReadonly('scheduled_start_at')"
                                        class="input-style"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record?.scheduled_start_at) }}
                                    </p>
                                </div>

                                <!-- Due Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.due_at?.label || 'Due Date' }}
                                        {{ isFieldRequired('due_at') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.due_at"
                                        type="datetime-local"
                                        :readonly="isFieldReadonly('due_at')"
                                        class="input-style"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record?.due_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Work Order Details -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                Work Order Details
                            </h3>

                            <div class="space-y-4">
                                <!-- Display Name / Title -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.display_name?.label || 'Summary' }} {{ isFieldRequired('display_name') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.display_name"
                                        type="text"
                                        :placeholder="fieldsSchema.display_name?.placeholder || ''"
                                        :readonly="isFieldReadonly('display_name')"
                                        class="input-style"
                                        :required="isFieldRequired('display_name')"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Description -->
                                <div v-if="mode !== 'show' || record?.description">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.description?.label || 'Description' }}
                                        {{ isFieldRequired('description') ? '*' : '' }}
                                    </label>
                                    <textarea
                                        v-if="mode !== 'show'"
                                        v-model="form.description"
                                        rows="4"
                                        :placeholder="fieldsSchema.description?.help || ''"
                                        :readonly="isFieldReadonly('description')"
                                        class="input-style resize-none"
                                    ></textarea>
                                    <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">
                                        {{ record?.description || '—' }}
                                    </p>
                                </div>

                                <!-- Internal Notes -->
                                <div v-if="mode !== 'show' || record?.internal_notes">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.internal_notes?.label || 'Internal Notes' }}
                                        {{ isFieldRequired('internal_notes') ? '*' : '' }}
                                    </label>
                                    <textarea
                                        v-if="mode !== 'show'"
                                        v-model="form.internal_notes"
                                        rows="3"
                                        :placeholder="fieldsSchema.internal_notes?.help || ''"
                                        :readonly="isFieldReadonly('internal_notes')"
                                        class="input-style resize-none"
                                    ></textarea>
                                    <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">
                                        {{ record?.internal_notes }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="material-icons text-xs align-middle">lock</span>
                                        These notes are internal only and will not appear on customer-facing documents
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Time & Costs -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                Time & Costs
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Estimated Hours -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.estimated_hours?.label || 'Estimated Hours' }}
                                        {{ isFieldRequired('estimated_hours') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model.number="form.estimated_hours"
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        :readonly="isFieldReadonly('estimated_hours')"
                                        class="input-style"
                                        :disabled="isFieldDisabled('estimated_hours')"
                                    />
                                    <p v-else class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ record?.estimated_hours || '0' }}
                                    </p>
                                </div>

                                <!-- Actual Hours -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.actual_hours?.label || 'Actual Hours' }}
                                        {{ isFieldRequired('actual_hours') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model.number="form.actual_hours"
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        :readonly="isFieldReadonly('actual_hours')"
                                        class="input-style"
                                        :disabled="isFieldDisabled('actual_hours')"
                                    />
                                    <p v-else class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ record?.actual_hours || '0' }}
                                    </p>
                                </div>

                                <!-- Labor Cost -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.labor_cost?.label || 'Labor Cost' }}
                                        {{ isFieldRequired('labor_cost') ? '*' : '' }}
                                    </label>
                                    <div v-if="mode !== 'show'" class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            v-model.number="form.labor_cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :readonly="isFieldReadonly('labor_cost')"
                                            class="input-style pl-8"
                                            :disabled="isFieldDisabled('labor_cost')"
                                        />
                                    </div>
                                    <p v-else class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(record?.labor_cost) }}
                                    </p>
                                </div>

                                <!-- Parts Cost -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.parts_cost?.label || 'Parts Cost' }}
                                        {{ isFieldRequired('parts_cost') ? '*' : '' }}
                                    </label>
                                    <div v-if="mode !== 'show'" class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            v-model.number="form.parts_cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :readonly="isFieldReadonly('parts_cost')"
                                            class="input-style pl-8"
                                            :disabled="isFieldDisabled('parts_cost')"
                                        />
                                    </div>
                                    <p v-else class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(record?.parts_cost) }}
                                    </p>
                                </div>

                                <!-- Total Cost -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.total_cost?.label || 'Total Cost' }}
                                        {{ isFieldRequired('total_cost') ? '*' : '' }}
                                    </label>
                                    <div v-if="mode !== 'show'" class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            v-model.number="form.total_cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :readonly="isFieldReadonly('total_cost')"
                                            class="input-style pl-8 font-semibold"
                                            :disabled="isFieldDisabled('total_cost')"
                                        />
                                    </div>
                                    <p v-else class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(record?.total_cost) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Flags & Options -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                Additional Options
                            </h3>

                            <div class="flex flex-wrap gap-3" v-if="mode !== 'show'">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.billable"
                                        type="checkbox"
                                        :disabled="isFieldReadonly('billable')"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.billable?.label || 'Billable' }}</span>
                                </label>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.warranty"
                                        type="checkbox"
                                        :disabled="isFieldReadonly('warranty')"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.warranty?.label || 'Warranty Work' }}</span>
                                </label>
                            </div>
                            <div v-else class="flex flex-wrap gap-3">
                                <span v-if="record?.billable" class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-medium">
                                    <span class="material-icons text-sm">check_circle</span>
                                    {{ fieldsSchema.billable?.label || 'Billable' }}
                                </span>
                                <span v-if="record?.warranty" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm font-medium">
                                    <span class="material-icons text-sm">verified_user</span>
                                    {{ fieldsSchema.warranty?.label || 'Warranty Work' }}
                                </span>
                                <span v-if="!record?.billable && !record?.warranty" class="text-sm text-gray-500 dark:text-gray-400 italic">
                                    No additional flags
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Sidebar -->
            <div class="lg:col-span-3 w-full" v-if="mode !== 'show'">
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                    <div class="flex justify-between items-center p-4 sm:px-5 font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                        Actions
                    </div>

                    <div class="p-4 sm:p-5 space-y-6">
                            <!-- Classification -->
                            <div class="space-y-4">
                                <!-- Type Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Type
                                    </label>
                                    <select
                                        v-if="mode !== 'show'"
                                        v-model="form.type"
                                        :disabled="isFieldReadonly('type')"
                                        class="input-style"
                                    >
                                        <option v-for="option in getEnumOptions('type')" :key="option.id" :value="option.id">
                                            {{ option.name }}
                                        </option>
                                    </select>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('type', record?.type) }}
                                    </p>
                                </div>

                                <!-- Priority Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Priority
                                    </label>
                                    <div v-if="mode !== 'show'" class="grid grid-cols-2 gap-2">
                                        <button
                                            v-for="priority in getEnumOptions('priority')"
                                            :key="priority.id"
                                            type="button"
                                            :disabled="isFieldReadonly('priority')"
                                            @click="form.priority = priority.id"
                                            :class="[
                                                'flex flex-col items-center gap-1 p-2 rounded-lg border-2 transition-all text-xs font-medium',
                                                form.priority === priority.id
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                                            ]"
                                        >
                                            <span class="material-icons text-lg" :class="`text-${priority.color}-500`">
                                                priority_high
                                            </span>
                                            <span :class="form.priority === priority.id ? 'text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'">
                                                {{ priority.name }}
                                            </span>
                                        </button>
                                    </div>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('priority', record?.priority) }}
                                    </p>
                                </div>

                                <!-- Status Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Status
                                    </label>
                                    <select
                                        v-if="mode !== 'show'"
                                        v-model="form.status"
                                        :disabled="isFieldReadonly('status')"
                                        class="input-style"
                                    >
                                        <option v-for="option in getEnumOptions('status')" :key="option.id" :value="option.id">
                                            {{ option.name }}
                                        </option>
                                    </select>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('status', record?.status) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Save Actions -->
                            <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700" v-if="mode !== 'show'">
                                <button
                                    @click="saveAndOpen"
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing" class="material-icons text-sm mr-2 animate-spin">refresh</span>
                                    <span v-else class="material-icons text-sm mr-2">check_circle</span>
                                    {{ mode === 'edit' ? (form.processing ? 'Updating...' : 'Update & Continue') : (form.processing ? 'Creating...' : 'Create & Open') }}
                                </button>

                                <button
                                    @click="saveDraft"
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span class="material-icons text-sm mr-2">save</span>
                                    Save as Draft
                                </button>

                                <button
                                    @click="handleCancel"
                                    type="button"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                                >
                                    <span class="material-icons text-sm mr-2">close</span>
                                    Cancel
                                </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </form>
    </div>
</template>
