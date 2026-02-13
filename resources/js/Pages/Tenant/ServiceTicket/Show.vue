<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    formSchema: {
        type: Object,
        default: null,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
        { label: props.record.display_name || props.record.uuid?.substring(0, 8) || 'View' },
    ];
});

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
        let date;
        if (typeof value === 'string') {
            date = new Date(value);
        } else if (value instanceof Date) {
            date = value;
        } else {
            return '—';
        }
        if (isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);
    } catch (error) {
        console.warn('Date formatting error:', error, value);
        return '—';
    }
};

const deleteTicket = () => {
    if (confirm('Are you sure you want to delete this service ticket?')) {
        router.delete(route('servicetickets.destroy', props.record.id));
    }
};
</script>

<template>
    <Head :title="`Service Ticket - ${record.display_name || record.uuid}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Service Ticket Details
                    </h2>

                    <div class="flex items-center space-x-2">
                        <Link :href="route('servicetickets.index')" class="w-full">
                            <button class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back to List
                            </button>
                        </Link>
                        <Link :href="route('servicetickets.edit', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button
                            @click="deleteTicket"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons">delete_forever</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 md:space-y-6">
            <div class="grid gap-4 lg:gap-6 xl:grid-cols-12">
                <!-- Main Ticket Display -->
                <div class="xl:col-span-9 space-y-6">
                    <ServiceTicketForm
                        :record="record"
                        :form-schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :enum-options="enumOptions"
                        :account="account"
                        :timezones="timezones"
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
                                        Status
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('status', record.status) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Expedite
                                    </label>
                                    <span v-if="record.expedite" class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-full text-sm font-medium">
                                        <span class="material-icons text-sm">priority_high</span>
                                        Yes
                                    </span>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">No</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Approved
                                    </label>
                                    <span v-if="record.approved" class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-medium">
                                        <span class="material-icons text-sm">check_circle</span>
                                        Approved
                                    </span>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">Pending</p>
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