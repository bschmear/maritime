<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import ServiceTicketPreview from '@/Components/Tenant/ServiceTicketPreview.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { buildRecordShowUrl } from '@/Utils/resourceRoutes.js';
import { computed, ref, watch } from 'vue';

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
    workOrders: {
        type: Array,
        default: () => [],
    },
    serviceTicketApprovalSms: {
        type: Object,
        default: () => ({ offered: false, hint: null }),
    },
});

const logoUrl = computed(() => props.account?.logo_url ?? null);

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
        { label: props.record.service_ticket_number || 'View' },
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

// Quick action state
const selectedStatus = ref(props.record.status);
const statusChanged = ref(false);
const updatingStatus = ref(false);
const approving = ref(false);
const showWorkOrderCompleteModal = ref(false);
const showApproveConfirmModal = ref(false);

watch(
    () => props.record.status,
    (value) => {
        selectedStatus.value = value;
        statusChanged.value = false;
    },
);

// Preview state
const showPreview = ref(false);

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

// Service items and revisions are covered elsewhere on the ticket UI.
const ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS = new Set(['InventoryImage', 'Document']);

const visibleSublists = computed(() =>
    (props.formSchema?.sublists || [])
        .filter(isSublistVisible)
        .filter((sub) => ALLOWED_SERVICE_TICKET_SUBLIST_DOMAINS.has(sub?.domain)),
);

// Status options from enum
const statusOptions = computed(() => {
    return props.enumOptions['App\\Enums\\ServiceTicket\\Status'] || [];
});

const TERMINAL_WORK_ORDER_STATUSES = new Set([7, 8, 9]);

const completedStatusId = computed(() => {
    const option = statusOptions.value.find((status) => status.value === 'completed');
    return option?.id ?? 4;
});

const inProgressStatusId = computed(() => {
    const option = statusOptions.value.find((status) => status.value === 'in_progress');
    return option?.id ?? 3;
});

const getStatusLabel = (statusId) => {
    const option = statusOptions.value.find((status) => Number(status.id) === Number(statusId));
    return option?.name ?? 'Unknown';
};

const currentStatusLabel = computed(() => getStatusLabel(props.record.status));
const manualApproveStatusLabel = computed(() => getStatusLabel(inProgressStatusId.value));
const approveWillChangeStatus = computed(
    () => Number(props.record.status) !== Number(inProgressStatusId.value),
);

const openWorkOrders = computed(() => {
    return props.workOrders.filter((workOrder) => !TERMINAL_WORK_ORDER_STATUSES.has(Number(workOrder.status)));
});

const shouldPromptWorkOrderComplete = computed(() => {
    return Number(selectedStatus.value) === completedStatusId.value && openWorkOrders.value.length > 0;
});

const openWorkOrderNumbersLabel = computed(() => {
    return openWorkOrders.value
        .map((workOrder) => `#${workOrder.work_order_number}`)
        .join(', ');
});

const confirmUpdateStatus = async (syncWorkOrderStatus, extraPayload = {}) => {
    showWorkOrderCompleteModal.value = false;
    updatingStatus.value = true;

    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            status: selectedStatus.value,
            sync_work_order_status: syncWorkOrderStatus,
            ...extraPayload,
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

    if (shouldPromptWorkOrderComplete.value) {
        showWorkOrderCompleteModal.value = true;
        return;
    }

    confirmUpdateStatus(false);
};

const openApproveConfirmModal = () => {
    showApproveConfirmModal.value = true;
};

const confirmApproveTicket = async () => {
    showApproveConfirmModal.value = false;
    approving.value = true;
    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            approved: true,
            status: inProgressStatusId.value,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    } catch (error) {
        console.error('Failed to approve ticket:', error);
    } finally {
        approving.value = false;
    }
};

const deleteTicket = () => {
    if (confirm('Are you sure you want to delete this service ticket?')) {
        router.delete(route('servicetickets.destroy', props.record.id));
    }
};

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};

// Check if the service ticket has been approved/signed and cannot be edited
const isLocked = computed(() => {
    return props.record.approved || props.record.signed_at || props.record.customer_signature;
});

const linkedTransaction = computed(() => {
    const id = props.record.transaction?.id ?? props.record.transaction_id;
    if (!id) return null;

    const tx = props.record.transaction;
    const title = tx?.title
        || tx?.display_name
        || (tx?.sequence ? `Deal #${tx.sequence}` : `Deal #${id}`);

    return { id, title };
});

const isSigned = computed(() =>
    Boolean(
        props.record.approved
        || props.record.signed_at
        || props.record.customer_signature
        || props.record.signature_url,
    ),
);

const relatedRecords = computed(() => {
    const out = [];

    if (props.record.customer?.id) {
        out.push({
            label: 'Customer',
            name: props.record.customer.display_name ?? `Customer #${props.record.customer.id}`,
            href: route('customers.show', props.record.customer.id),
            icon: 'person',
        });
    }

    if (linkedTransaction.value) {
        out.push({
            label: 'Deal',
            name: linkedTransaction.value.title,
            href: route('transactions.show', linkedTransaction.value.id),
            icon: 'handshake',
        });
    }

    for (const workOrder of props.workOrders) {
        out.push({
            label: props.workOrders.length > 1 ? 'Work order' : 'Work order',
            name: workOrder.display_name ?? `WO-${workOrder.work_order_number ?? workOrder.id}`,
            href: route('workorders.show', workOrder.id),
            icon: 'assignment',
        });
    }

    const assetUnitHref = props.record.asset_unit?.id
        ? buildRecordShowUrl('AssetUnit', props.record.asset_unit.id)
        : null;
    if (assetUnitHref) {
        out.push({
            label: 'Asset unit',
            name: props.record.asset_unit.display_name ?? `Unit #${props.record.asset_unit.id}`,
            href: assetUnitHref,
            icon: 'directions_boat',
        });
    }

    if (isSigned.value && route().has('service-tickets.review')) {
        out.push({
            label: 'Signed service ticket',
            name: `ST-${props.record.service_ticket_number ?? props.record.id}`,
            href: route('service-tickets.review', props.record.uuid),
            icon: 'draw',
            external: true,
        });
    }

    return out;
});
</script>

<template>
    <Head :title="`Service Ticket - ${record.display_name || record.uuid}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="min-w-0 flex-1 truncate text-lg font-semibold leading-tight text-gray-800 md:text-xl dark:text-gray-200">
                        Service Ticket Details
                    </h2>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1 md:gap-2">
                        <Link :href="route('servicetickets.index')">
                            <button
                                type="button"
                                aria-label="Back to service tickets"
                                class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-300 bg-white p-2 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 md:gap-1.5 md:px-4 md:py-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">arrow_back</span>
                                <span class="hidden md:inline">Back to List</span>
                            </button>
                        </Link>
                        <Link v-if="workOrders.length > 0" :href="route('workorders.show', workOrders[0].id)">
                            <button
                                type="button"
                                aria-label="View work order"
                                class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-amber-600 p-2 text-md font-medium text-white transition-colors hover:bg-amber-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">assignment</span>
                                <span class="hidden md:inline">View Work Order</span>
                            </button>
                        </Link>
                        <Link v-else :href="route('workorders.create') + '?service_ticket_id=' + record.id">
                            <button
                                type="button"
                                aria-label="Create work order"
                                class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-amber-600 p-2 text-md font-medium text-white transition-colors hover:bg-amber-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">add_circle</span>
                                <span class="hidden md:inline">Create Work Order</span>
                            </button>
                        </Link>
                        <button
                            type="button"
                            aria-label="Customer preview"
                            class="inline-flex items-center justify-center gap-0 whitespace-nowrap rounded-lg bg-secondary-600 p-2 text-md font-medium text-white transition-colors hover:bg-secondary-700 md:gap-1.5 md:px-4 md:py-2.5"
                            @click="openPreview"
                        >
                            <span class="material-icons text-xl leading-none md:text-md">visibility</span>
                            <span class="hidden md:inline">Customer Preview</span>
                        </button>
                        <Link v-if="!isLocked" :href="route('servicetickets.edit', record.id)">
                            <button
                                type="button"
                                aria-label="Edit service ticket"
                                class="inline-flex items-center justify-center gap-0 rounded-lg bg-blue-600 p-2 text-md font-medium text-white transition-colors hover:bg-blue-700 md:gap-1.5 md:px-4 md:py-2.5"
                            >
                                <span class="material-icons text-xl leading-none md:text-md">edit</span>
                                <span class="hidden md:inline">Edit</span>
                            </button>
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            aria-label="Ticket is locked"
                            class="inline-flex cursor-not-allowed items-center justify-center gap-0 rounded-lg bg-gray-200 p-2 text-md font-medium text-gray-400 md:gap-1.5 md:px-4 md:py-2.5"
                        >
                            <span class="material-icons text-xl leading-none md:text-md">lock</span>
                            <span class="hidden md:inline">Locked</span>
                        </button>
                        <button
                            type="button"
                            aria-label="Delete service ticket"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-red-600 p-2 text-md font-medium text-white transition-colors hover:bg-red-700 md:gap-1.5 md:px-3 md:py-2.5"
                            @click="deleteTicket"
                        >
                            <span class="material-icons text-xl leading-none">delete_forever</span>
                            <span class="hidden md:inline">Delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="border-t  border-gray-200 dark:border-gray-700  top-0 z-10 shadow-md bg-white dark:bg-gray-900 mb-4 dark:mb-0">
            <div class="w-full px-4 py-4 sm:py-5">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-6">
                    <div class="flex min-w-0 flex-1 flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Status:</span>
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="selectedStatus"
                                    class="text-md px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all flex-1 sm:flex-none"
                                    @change="statusChanged = true"
                                >
                                    <option v-for="status in statusOptions" :key="status.id" :value="status.id">
                                        {{ status.name }}
                                    </option>
                                </select>
                                <button
                                    v-if="statusChanged"
                                    @click="updateStatus"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-md font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="updatingStatus"
                                >
                                    <span v-if="updatingStatus" class="material-icons text-md animate-spin">refresh</span>
                                    <span v-else class="material-icons text-md">save</span>
                                    Update
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between sm:justify-start gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Expedite:</span>
                            <span v-if="record.expedite" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded-md text-md font-semibold shadow-sm whitespace-nowrap">
                                <span class="material-icons text-lg">priority_high</span>
                                Yes
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md text-md font-medium whitespace-nowrap">
                                No
                            </span>
                        </div>
                        <Link
                            v-if="linkedTransaction"
                            :href="route('transactions.show', linkedTransaction.id)"
                            class="flex min-w-0 max-w-full items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 transition-colors hover:bg-blue-100 group dark:border-blue-800 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 sm:max-w-md sm:py-2.5"
                        >
                            <span class="material-icons shrink-0 text-lg text-blue-500 dark:text-blue-400">handshake</span>
                            <span class="min-w-0 truncate text-md font-semibold text-blue-700 group-hover:underline dark:text-blue-300">{{ linkedTransaction.title }}</span>
                        </Link>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Approved:</span>
                            <div class="flex items-center gap-2">
                                <span v-if="record.approved" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-md text-md font-semibold shadow-sm whitespace-nowrap">
                                    <span class="material-icons text-lg">check_circle</span>
                                    Approved
                                </span>
                                <button
                                    v-if="!record.approved"
                                    @click="openApproveConfirmModal"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-md font-medium hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="approving"
                                >
                                    <span v-if="approving" class="material-icons text-md animate-spin">refresh</span>
                                    <span v-else class="material-icons text-md">check_circle</span>
                                    Approve
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-row sm:gap-4 lg:gap-6 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-sm sm:text-md font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.created_at) }}</span>
                        </div>
                        <div class="border-l border-gray-300 dark:border-gray-600 sm:hidden"></div>
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</span>
                            <span class="text-sm sm:text-md font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form + sidebar -->
        <div class="w-full min-w-0 !pt-0 !mt-0">
            <div class="grid min-w-0 gap-6 lg:grid-cols-12">
                <div class="min-w-0 lg:col-span-8 space-y-4 md:space-y-6">
                    <ServiceTicketForm
                        :record="record"
                        :form-schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :enum-options="enumOptions"
                        :account="account"
                        :timezones="timezones"
                        :service-ticket-approval-sms="serviceTicketApprovalSms"
                        mode="show"
                    />
                </div>

                <div class="min-w-0 lg:col-span-4 space-y-4">
                    <div class="sticky top-[140px] space-y-4">
                        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quick links</span>
                            </div>
                            <div class="p-5 space-y-3">
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-secondary-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-secondary-700"
                                    @click="openPreview"
                                >
                                    <span class="material-icons text-base">visibility</span>
                                    Customer preview
                                </button>
                                <Link
                                    v-if="linkedTransaction"
                                    :href="route('transactions.show', linkedTransaction.id)"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-800 transition hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100 dark:hover:bg-blue-900/50"
                                >
                                    <span class="material-icons text-base">handshake</span>
                                    View deal
                                </Link>
                                <Link
                                    v-if="workOrders.length > 0"
                                    :href="route('workorders.show', workOrders[0].id)"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    <span class="material-icons text-base">assignment</span>
                                    {{ workOrders.length > 1 ? 'View work orders' : 'View work order' }}
                                </Link>
                                <Link
                                    v-else
                                    :href="route('workorders.create') + '?service_ticket_id=' + record.id"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-900/50"
                                >
                                    <span class="material-icons text-base">add_circle</span>
                                    Create work order
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="relatedRecords.length"
                            class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Related records</span>
                            </div>
                            <ul class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <li
                                    v-for="rel in relatedRecords"
                                    :key="`${rel.label}-${rel.href}`"
                                    class="flex min-w-0 items-center justify-between gap-3 px-5 py-3"
                                >
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span class="material-icons mt-0.5 shrink-0 text-lg text-gray-400">{{ rel.icon }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ rel.label }}</div>
                                            <div
                                                class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                :title="rel.name"
                                            >
                                                {{ rel.name }}
                                            </div>
                                        </div>
                                    </div>
                                    <component
                                        :is="rel.external ? 'a' : Link"
                                        :href="rel.href"
                                        :target="rel.external ? '_blank' : undefined"
                                        :rel="rel.external ? 'noopener noreferrer' : undefined"
                                        class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                        :aria-label="`Open ${rel.label}`"
                                    >
                                        <span class="material-icons text-base">{{ rel.external ? 'open_in_new' : 'chevron_right' }}</span>
                                    </component>
                                </li>
                            </ul>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ticket info</span>
                            </div>
                            <ul class="divide-y divide-gray-50 text-sm dark:divide-gray-700/60">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-base text-gray-400">tag</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Ticket #</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ record.service_ticket_number || '—' }}</span>
                                </li>
                                <li v-if="isSigned" class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-base text-green-500">verified</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Signed</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</span>
                                </li>
                                <li v-if="record.signed_name" class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-base text-gray-400">draw</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Signed by</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ record.signed_name }}</span>
                                </li>
                            </ul>
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
                :key="`service-ticket-sublist-${record?.id || 'new'}`"
                :parent-record="record"
                parent-domain="ServiceTicket"
                :sublists="visibleSublists"
            />
        </div>

        <!-- Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="service-ticket-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <ServiceTicketPreview
                    :record="record"
                    :account="account"
                    :logo-url="logoUrl"
                    :enum-options="enumOptions"
                    :service-ticket-approval-sms="serviceTicketApprovalSms"
                    @close="closePreview"
                />
            </div>
        </Teleport>

        <Modal :show="showApproveConfirmModal" max-width="md" @close="showApproveConfirmModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Skip customer approval and approve manually?
                </h3>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    This records staff approval without waiting for the customer to review and sign the estimate.
                </p>
                <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li class="flex items-start gap-2">
                        <span class="material-icons mt-0.5 text-base text-green-600 dark:text-green-400">check_circle</span>
                        <span><span class="font-medium text-gray-900 dark:text-white">Approved</span> will be set to yes.</span>
                    </li>
                    <li v-if="approveWillChangeStatus" class="flex items-start gap-2">
                        <span class="material-icons mt-0.5 text-base text-blue-600 dark:text-blue-400">swap_horiz</span>
                        <span>
                            Status will change from
                            <span class="font-medium text-gray-900 dark:text-white">{{ currentStatusLabel }}</span>
                            to
                            <span class="font-medium text-gray-900 dark:text-white">{{ manualApproveStatusLabel }}</span>.
                        </span>
                    </li>
                    <li v-else class="flex items-start gap-2">
                        <span class="material-icons mt-0.5 text-base text-blue-600 dark:text-blue-400">info</span>
                        <span>
                            Status will remain
                            <span class="font-medium text-gray-900 dark:text-white">{{ currentStatusLabel }}</span>.
                        </span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-icons mt-0.5 text-base text-amber-600 dark:text-amber-400">lock</span>
                        <span>The ticket will be locked and can no longer be edited.</span>
                    </li>
                </ul>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        :disabled="approving"
                        @click="showApproveConfirmModal = false"
                    >
                        No, cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-60"
                        :disabled="approving"
                        @click="confirmApproveTicket"
                    >
                        <span v-if="approving" class="material-icons text-md animate-spin mr-1">refresh</span>
                        Yes, approve manually
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showWorkOrderCompleteModal" max-width="md" @close="showWorkOrderCompleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Mark linked work order complete?
                </h3>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    <template v-if="openWorkOrders.length === 1">
                        There is an open work order
                        <span class="font-medium text-gray-900 dark:text-white">{{ openWorkOrderNumbersLabel }}</span>
                        attached to this service ticket. Would you like to mark it as complete?
                    </template>
                    <template v-else>
                        There are
                        <span class="font-medium text-gray-900 dark:text-white">{{ openWorkOrders.length }} open work orders</span>
                        attached ({{ openWorkOrderNumbersLabel }}). Would you like to mark them as complete?
                    </template>
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        :disabled="updatingStatus"
                        @click="showWorkOrderCompleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors"
                        :disabled="updatingStatus"
                        @click="confirmUpdateStatus(false)"
                    >
                        Service ticket only
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
