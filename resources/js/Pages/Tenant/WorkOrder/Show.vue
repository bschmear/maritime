<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderForm from '@/Components/Tenant/WorkOrderForm.vue';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true
    },
    recordType: {
        type: String,
        default: 'WorkOrder'
    },
    fieldsSchema: {
        type: Object,
        default: () => ({})
    },
    formSchema: {
        type: Object,
        default: null
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    serviceTicket: {
        type: Object,
        default: null,
    },
    estimateThreshold: {
        type: Number,
        default: 20,
    },
});

const pluralTitle = computed(() => {
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: pluralTitle.value, href: route('workorders.index') },
        { label: props.record.work_order_number || 'View' },
    ];
});

// Helper functions
const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const formatDateTime = (value) => {
    if (!value) return '—';

    try {
        // Handle different date formats
        let date;
        if (typeof value === 'string') {
            // Try parsing as ISO string or other formats
            date = new Date(value);
        } else if (value instanceof Date) {
            date = value;
        } else {
            return '—';
        }

        if (isNaN(date.getTime())) return '—';

        // Format as "Dec 5, 2024 at 3:30 PM"
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }).format(date);
    } catch (error) {
        console.warn('Date formatting error:', error, value);
        return '—';
    }
};

const deleteWorkOrder = () => {
    if (confirm('Are you sure you want to delete this work order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('workorders.destroy', props.record.id);
        form.innerHTML = `
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
};
</script>

<template>
    <Head :title="`${pluralTitle} - ${record.work_order_number || record.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ pluralTitle }} Details
                    </h2>

                    <div class="flex items-center space-x-2">
                        <Link :href="route('workorders.index')" class="w-full">
                            <button class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back to List
                            </button>
                        </Link>
                        <Link :href="route('workorders.edit', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>


                                <button
                                    @click="deleteWorkOrder"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                    <span class="material-icons ">delete_forever</span>
                                </button>


                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 md:space-y-6">
            <div class="grid gap-4 lg:gap-6  xl:grid-cols-12">
                <!-- Main Work Order Display -->
                <div class="xl:col-span-9 space-y-6">
                    <WorkOrderForm
                        :record="record"
                        :record-type="recordType"
                        :form-schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :enum-options="enumOptions"
                        :account="account"
                        :timezones="timezones"
                        :service-ticket="serviceTicket"
                        :estimate-threshold="estimateThreshold"
                        mode="show"
                    />
                </div>

                <!-- Actions Sidebar -->
                <div class="xl:col-span-3">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                        <div class="flex justify-between items-center p-4 sm:px-5 font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                            Actions
                        </div>

                        <div class="p-4 sm:p-5 space-y-6">
                            <!-- Classification -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Type
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('type', record.type) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Priority
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('priority', record.priority) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Status
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('status', record.status) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Timestamps -->
                            <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                        Created
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.created_at) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                        Last Updated
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.updated_at) }}
                                    </p>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
