<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import WorkOrderForm from '@/Components/Tenant/WorkOrderForm.vue';
import { computed, ref, watch } from 'vue';

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
    serviceTicketStatusMap: {
        type: Object,
        default: () => ({}),
    },
    estimateThreshold: {
        type: Number,
        default: 20,
    },
});

const selectedStatus = ref(props.record.status);
const statusChanged = ref(false);
const updatingStatus = ref(false);
const showServiceTicketSyncModal = ref(false);

watch(
    () => props.record.status,
    (value) => {
        selectedStatus.value = value;
        statusChanged.value = false;
    },
);

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

const isSublistVisible = (sub) => {
    if (!sub?.conditional || typeof sub.conditional !== 'object') {
        return true;
    }
    const { key, value, operator = 'equals' } = sub.conditional;
    const current = props.record[key];
    const boolCurrent = current === true || current === 1;
    switch (operator) {
        case 'equals':
        case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
        case 'not_equals':
        case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : current != value;
        default:
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
    }
};

const visibleSublists = computed(() => (props.formSchema?.sublists || []).filter(isSublistVisible));

const statusOptions = computed(() => {
    const fieldDef = props.fieldsSchema.status;
    if (fieldDef?.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
});

const serviceTicketStatusOptions = computed(() => {
    return props.enumOptions['App\\Enums\\ServiceTicket\\Status'] || [];
});

const mappedServiceTicketStatusId = computed(() => {
    return props.serviceTicketStatusMap[selectedStatus.value] ?? null;
});

const mappedServiceTicketStatusLabel = computed(() => {
    const id = mappedServiceTicketStatusId.value;
    if (id === null) {
        return '';
    }
    const option = serviceTicketStatusOptions.value.find((opt) => opt.id === id);
    return option?.name ?? '';
});

const shouldPromptServiceTicketSync = computed(() => {
    if (!props.serviceTicket || mappedServiceTicketStatusId.value === null) {
        return false;
    }

    return mappedServiceTicketStatusId.value !== props.serviceTicket.status;
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

const confirmUpdateStatus = async (syncServiceTicketStatus) => {
    showServiceTicketSyncModal.value = false;
    updatingStatus.value = true;

    try {
        await router.patch(route('workorders.update', props.record.id), {
            status: selectedStatus.value,
            sync_service_ticket_status: syncServiceTicketStatus,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
        statusChanged.value = false;
    } catch (error) {
        console.error('Failed to update status:', error);
    } finally {
        updatingStatus.value = false;
    }
};

const updateStatus = () => {
    if (selectedStatus.value === props.record.status) {
        statusChanged.value = false;
        return;
    }

    if (shouldPromptServiceTicketSync.value) {
        showServiceTicketSyncModal.value = true;
        return;
    }

    confirmUpdateStatus(false);
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
                            <button class="w-full inline-flex items-center justify-center px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-md mr-2">arrow_back</span>
                                Back to List
                            </button>
                        </Link>
                        <Link :href="route('workorders.edit', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-md font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-md mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <Link :href="route('invoices.create') + `?work_order_id=${record.id}`">
                            <button class="inline-flex items-center px-4 py-2.5 text-md font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">
                                <span class="material-icons text-md mr-1">request_quote</span>
                                Create Invoice
                            </button>
                        </Link>


                                <button
                                    @click="deleteWorkOrder"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 text-md font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
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
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Type
                                    </label>
                                    <p class="text-md text-gray-900 dark:text-white">
                                        {{ getEnumLabel('type', record.type) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Priority
                                    </label>
                                    <p class="text-md text-gray-900 dark:text-white">
                                        {{ getEnumLabel('priority', record.priority) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Status
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="selectedStatus"
                                            class="flex-1 text-md px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                            @change="statusChanged = true"
                                        >
                                            <option v-for="status in statusOptions" :key="status.id" :value="status.id">
                                                {{ status.name }}
                                            </option>
                                        </select>
                                        <button
                                            v-if="statusChanged"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap disabled:opacity-60"
                                            :disabled="updatingStatus"
                                            @click="updateStatus"
                                        >
                                            <span v-if="updatingStatus" class="material-icons text-md animate-spin">refresh</span>
                                            <span v-else class="material-icons text-md">save</span>
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Timestamps -->
                            <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                        Created
                                    </label>
                                    <p class="text-md text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.created_at) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                        Date completed
                                    </label>
                                    <p class="text-md text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.completed_at) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                        Last Updated
                                    </label>
                                    <p class="text-md text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.updated_at) }}
                                    </p>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="visibleSublists.length > 0 && formSchema"
                class="w-full space-y-4 md:space-y-6"
            >
                <Sublist
                    :key="`work-order-sublist-${record?.id || 'new'}`"
                    :parent-record="record"
                    parent-domain="WorkOrder"
                    :sublists="visibleSublists"
                />
            </div>
        </div>

        <Modal :show="showServiceTicketSyncModal" max-width="md" @close="showServiceTicketSyncModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Update linked service ticket?
                </h3>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    This work order is linked to service ticket
                    <span class="font-medium text-gray-900 dark:text-white">#{{ serviceTicket?.service_ticket_number }}</span>.
                    Do you also want to update the service ticket status to
                    <span class="font-medium text-gray-900 dark:text-white">{{ mappedServiceTicketStatusLabel }}</span>?
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        :disabled="updatingStatus"
                        @click="showServiceTicketSyncModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors"
                        :disabled="updatingStatus"
                        @click="confirmUpdateStatus(false)"
                    >
                        Work order only
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60"
                        :disabled="updatingStatus"
                        @click="confirmUpdateStatus(true)"
                    >
                        Update both
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
