<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { computed, ref } from 'vue';

const props = defineProps({
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
    }
});

const pluralTitle = computed(() => {
    // Convert WorkOrder to Work Orders
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: pluralTitle.value, href: route('workorders.index') },
        { label: 'Create' },
    ];
});

// Helper functions
const getEnumOptions = (fieldKey) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
};

const isFieldRequired = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.required === true;
};

const isFieldDisabled = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.disabled === true;
};

// Initialize form with schema-driven defaults
const formData = {};
Object.keys(props.fieldsSchema).forEach(key => {
    const field = props.fieldsSchema[key];
    if (field.default !== undefined && field.default !== null) {
        formData[key] = field.default;
    } else if (field.default_value !== undefined && field.default_value !== null) {
        formData[key] = field.default_value;
    } else if (field.type === 'boolean' || field.type === 'checkbox') {
        formData[key] = false;
    } else if (field.type === 'select') {
        // For select fields with enums, auto-select the first option
        const enumOptions = getEnumOptions(key);
        if (enumOptions && enumOptions.length > 0) {
            formData[key] = enumOptions[0].id;
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
    }).post(route('workorders.store'));
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
    window.location.href = route('workorders.index');
};
</script>

<template>
    <Head title="Create Work Order" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New {{ pluralTitle }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-4 md:space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-4 xl:grid-cols-12">
                <!-- Main Work Order Form -->
                <div class="xl:col-span-9 space-y-6">
                    <!-- Work Order Header -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 dark:from-indigo-700 dark:to-indigo-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">WORK ORDER</h1>
                                    <p class="text-indigo-100 text-sm mt-1">Service Request Form</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-white text-sm font-medium">WO #</div>
                                    <div class="text-white text-lg font-mono">{{ form.work_order_number || 'Auto-generated' }}</div>
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
                                            Customer {{ isFieldRequired('customer_id') ? '*' : '' }}
                                        </label>
                                        <RecordSelect
                                            :id="'customer_id'"
                                            :field="props.fieldsSchema.customer_id"
                                            v-model="form.customer_id"
                                            :disabled="false"
                                            :enum-options="getEnumOptions('customer_id')"
                                            field-key="customer_id"
                                        />
                                    </div>

                                    <!-- Inventory Item Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Unit/Boat
                                        </label>
                                        <RecordSelect
                                            :id="'inventory_item_id'"
                                            :field="props.fieldsSchema.inventory_item_id"
                                            v-model="form.inventory_item_id"
                                            :disabled="false"
                                            :enum-options="getEnumOptions('inventory_item_id')"
                                            field-key="inventory_item_id"
                                        />
                                    </div>

                                    <!-- Location Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Location
                                        </label>
                                        <RecordSelect
                                            :id="'location_id'"
                                            :field="props.fieldsSchema.location_id"
                                            v-model="form.location_id"
                                            :disabled="false"
                                            :enum-options="getEnumOptions('location_id')"
                                            field-key="location_id"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Assignment & Scheduling
                                    </h3>

                                    <!-- Assigned User Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Assigned Technician
                                        </label>
                                        <RecordSelect
                                            :id="'assigned_user_id'"
                                            :field="props.fieldsSchema.assigned_user_id"
                                            v-model="form.assigned_user_id"
                                            :disabled="false"
                                            :enum-options="getEnumOptions('assigned_user_id')"
                                            field-key="assigned_user_id"
                                        />
                                    </div>

                                    <!-- Scheduled Date/Time -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Scheduled Date/Time
                                        </label>
                                        <input
                                            v-model="form.scheduled_start_at"
                                            type="datetime-local"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        />
                                    </div>

                                    <!-- Due Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Due Date
                                        </label>
                                        <input
                                            v-model="form.due_at"
                                            type="datetime-local"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        />
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
                                            Summary {{ isFieldRequired('display_name') ? '*' : '' }}
                                        </label>
                                        <input
                                            v-model="form.display_name"
                                            type="text"
                                            :placeholder="props.fieldsSchema.display_name?.placeholder || ''"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :required="isFieldRequired('display_name')"
                                        />
                                    </div>

                                    <!-- Description -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Description
                                        </label>
                                        <textarea
                                            v-model="form.description"
                                            rows="4"
                                            :placeholder="props.fieldsSchema.description?.help || ''"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                        ></textarea>
                                    </div>

                                    <!-- Internal Notes -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Internal Notes
                                        </label>
                                        <textarea
                                            v-model="form.internal_notes"
                                            rows="3"
                                            :placeholder="props.fieldsSchema.internal_notes?.help || ''"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                        ></textarea>
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
                                            Estimated Hours
                                        </label>
                                        <input
                                            v-model.number="form.estimated_hours"
                                            type="number"
                                            step="0.25"
                                            min="0"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :disabled="isFieldDisabled('estimated_hours')"
                                        />
                                    </div>

                                    <!-- Actual Hours -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Actual Hours
                                        </label>
                                        <input
                                            v-model.number="form.actual_hours"
                                            type="number"
                                            step="0.25"
                                            min="0"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            :disabled="isFieldDisabled('actual_hours')"
                                            readonly
                                        />
                                    </div>

                                    <!-- Labor Cost -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Labor Cost
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                            <input
                                                v-model.number="form.labor_cost"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                :disabled="isFieldDisabled('labor_cost')"
                                                readonly
                                            />
                                        </div>
                                    </div>

                                    <!-- Parts Cost -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Parts Cost
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                            <input
                                                v-model.number="form.parts_cost"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                :disabled="isFieldDisabled('parts_cost')"
                                                readonly
                                            />
                                        </div>
                                    </div>

                                    <!-- Total Cost -->
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Total Cost
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                            <input
                                                v-model.number="form.total_cost"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-semibold"
                                                :disabled="isFieldDisabled('total_cost')"
                                                readonly
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Flags & Options -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                    Additional Options
                                </h3>

                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.billable"
                                            type="checkbox"
                                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Billable</span>
                                    </label>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.warranty"
                                            type="checkbox"
                                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Warranty Work</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Sidebar -->
                <div class="xl:col-span-3 w-full">
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
                                            v-model="form.type"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        >
                                            <option v-for="option in getEnumOptions('type')" :key="option.id" :value="option.id">
                                                {{ option.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Priority Selection -->
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                            Priority
                                        </label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                v-for="priority in getEnumOptions('priority')"
                                                :key="priority.id"
                                                type="button"
                                                @click="form.priority = priority.id"
                                                :class="[
                                                    'flex flex-col items-center gap-1 p-2 rounded-lg border-2 transition-all text-xs font-medium',
                                                    form.priority === priority.id
                                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                                                ]"
                                            >
                                                <span class="material-icons text-lg" :class="`text-${priority.color}-500`">
                                                    priority_high
                                                </span>
                                                <span :class="form.priority === priority.id ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400'">
                                                    {{ priority.name }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Status Selection -->
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                            Status
                                        </label>
                                        <select
                                            v-model="form.status"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        >
                                            <option v-for="option in getEnumOptions('status')" :key="option.id" :value="option.id">
                                                {{ option.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Save Actions -->
                                <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <button
                                        @click="saveAndOpen"
                                        type="button"
                                        :disabled="form.processing"
                                        class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        <span v-if="form.processing" class="material-icons text-sm mr-2 animate-spin">refresh</span>
                                        <span v-else class="material-icons text-sm mr-2">check_circle</span>
                                        {{ form.processing ? 'Creating...' : 'Create & Open' }}
                                    </button>

                                    <button
                                        @click="saveDraft"
                                        type="button"
                                        :disabled="form.processing"
                                        class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        <span class="material-icons text-sm mr-2">save</span>
                                        Save as Draft
                                    </button>

                                    <a
                                        :href="route('workorders.index')"
                                        class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                                    >
                                        <span class="material-icons text-sm mr-2">close</span>
                                        Cancel
                                    </a>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
