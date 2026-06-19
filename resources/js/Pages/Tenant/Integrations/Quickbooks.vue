<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QuickBooksImport from '@/Components/Tenant/QuickBooksImport.vue';
import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    breadcrumbs: {
        type: Object,
        default: () => ({}),
    },
    integration: {
        type: Object,
        required: true,
    },
    hasQuickbooksToken: {
        type: Boolean,
        default: false,
    },
    isQuickbooksEnabled: {
        type: Boolean,
        default: false,
    },
    currentIntegration: {
        type: Object,
        default: null,
    },
    oauthNotice: {
        type: Object,
        default: null,
    },
    quickbooks: {
        type: Object,
        default: () => ({}),
    },
    syncSettings: {
        type: Object,
        default: () => ({
            sync_contacts: false,
            sync_invoices: false,
            sync_payments: false,
            sync_bills: false,
            sync_bill_payments: false,
        }),
    },
});

const page = usePage();
const quickBooksImportRef = ref(null);
const quickBooksServiceImportRef = ref(null);
const quickBooksChartOfAccountsImportRef = ref(null);
const quickBooksVendorImportRef = ref(null);
const quickBooksBillImportRef = ref(null);
const quickBooksBillPaymentImportRef = ref(null);

const importingCustomers = ref(false);
const importingServices = ref(false);
const importingVendors = ref(false);
const importingChartOfAccounts = ref(false);
const importingBills = ref(false);
const importingBillPayments = ref(false);

/** QuickBooks integration sync_status values */
const SYNC_STATUS_PENDING = 1;
const SYNC_STATUS_SYNCING = 2;
const SYNC_STATUS_FAILED = 4;

const syncOperation = ref(props.currentIntegration?.sync_operation ?? null);
const importErrorMessage = ref(props.currentIntegration?.sync_error_message ?? null);
const pageImportActive = ref(false);
const clearingImportStatus = ref(false);
let pagePollTimer = null;

const SYNC_OPERATION_LABELS = {
    customers: 'customers',
    services: 'service items',
    vendors: 'vendors',
    chart_of_accounts: 'chart of accounts',
    bills: 'bills',
    bill_payments: 'bill payments',
    import: 'data',
};

const anyImporting = computed(() => (
    importingCustomers.value
    || importingServices.value
    || importingVendors.value
    || importingChartOfAccounts.value
    || importingBills.value
    || importingBillPayments.value
    || pageImportActive.value
));

const importStatusMessage = computed(() => {
    const activeOperation = (
        importingVendors.value ? 'vendors'
        : importingCustomers.value ? 'customers'
            : importingServices.value ? 'services'
                : importingChartOfAccounts.value ? 'chart_of_accounts'
                    : importingBills.value ? 'bills'
                        : importingBillPayments.value ? 'bill_payments'
                            : syncOperation.value
    );

    const label = SYNC_OPERATION_LABELS[activeOperation] ?? null;
    if (label) {
        return `Currently importing ${label} from QuickBooks…`;
    }

    return 'Currently importing from QuickBooks…';
});

function stopPageImportPolling() {
    if (pagePollTimer !== null) {
        clearInterval(pagePollTimer);
        pagePollTimer = null;
    }
}

function applyImportStatus(data) {
    if (data?.sync_operation) {
        syncOperation.value = data.sync_operation;
    }

    importErrorMessage.value = data?.sync_error_message ?? null;

    const status = data?.sync_status ?? null;
    const isActive = status === SYNC_STATUS_SYNCING || status === SYNC_STATUS_PENDING;

    pageImportActive.value = isActive;

    if (!isActive) {
        stopPageImportPolling();
        syncImportingFlagsForOperation(data?.sync_operation, false);
    }
}

function syncImportingFlagsForOperation(operation, active) {
    if (!operation) {
        return;
    }

    const flagMap = {
        customers: importingCustomers,
        services: importingServices,
        vendors: importingVendors,
        chart_of_accounts: importingChartOfAccounts,
        bills: importingBills,
        bill_payments: importingBillPayments,
    };

    const flag = flagMap[operation];
    if (flag) {
        flag.value = active;
    }
}

async function checkPageImportStatus() {
    try {
        const { data } = await axios.get(route('quickbooks.import-status'));
        applyImportStatus(data);
    } catch {
        // Ignore transient polling errors.
    }
}

function startPageImportPolling() {
    stopPageImportPolling();
    pageImportActive.value = true;
    void checkPageImportStatus();
    pagePollTimer = setInterval(checkPageImportStatus, 2000);
}

function resumeImportStatusIfNeeded() {
    const status = props.currentIntegration?.sync_status ?? null;
    const operation = props.currentIntegration?.sync_operation ?? null;

    if (status === SYNC_STATUS_FAILED) {
        if (operation) {
            syncOperation.value = operation;
        }
        importErrorMessage.value = props.currentIntegration?.sync_error_message ?? importErrorMessage.value;

        return;
    }

    if (status !== SYNC_STATUS_SYNCING && status !== SYNC_STATUS_PENDING) {
        return;
    }

    if (operation) {
        syncOperation.value = operation;
        syncImportingFlagsForOperation(operation, true);
    }

    startPageImportPolling();
}

async function clearStuckImport() {
    if (clearingImportStatus.value) {
        return;
    }

    clearingImportStatus.value = true;

    try {
        const { data } = await axios.post(route('quickbooks.import-status.clear'));
        applyImportStatus(data);
        syncOperation.value = null;
        importErrorMessage.value = null;
        importingCustomers.value = false;
        importingServices.value = false;
        importingVendors.value = false;
        importingChartOfAccounts.value = false;
        importingBills.value = false;
        importingBillPayments.value = false;
    } catch {
        // Ignore — user can refresh after manual DB fix.
    } finally {
        clearingImportStatus.value = false;
    }
}

onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.has('qbo_connected') || url.searchParams.has('qbo_error')) {
        url.searchParams.delete('qbo_connected');
        url.searchParams.delete('qbo_error');
        const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams.toString()}` : '');
        window.history.replaceState({}, '', next);
    }

    resumeImportStatusIfNeeded();
});

onUnmounted(stopPageImportPolling);

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];
    const links = props.breadcrumbs?.links ?? [];
    for (const link of links) {
        if (link?.url && link?.name) {
            items.push({ label: link.name, href: link.url });
        }
    }
    if (props.breadcrumbs?.current) {
        items.push({ label: props.breadcrumbs.current });
    }
    return items;
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => {
    const fromFlash = page.props.flash?.error;
    if (fromFlash) {
        return fromFlash;
    }
    const errs = page.props.errors;
    if (!errs || typeof errs !== 'object') {
        return null;
    }
    const flat = Object.values(errs).flat().filter(Boolean);
    return flat.length ? flat.join(' ') : null;
});

function disableIntegration() {
    if (!window.confirm('Disable QuickBooks Online for this workspace? Your connection will be kept and you can turn it back on later.')) {
        return;
    }
    router.delete(route('quickbooks.destroy'));
}

const enabling = ref(false);

function enableIntegration() {
    if (enabling.value) {
        return;
    }
    enabling.value = true;
    router.patch(route('quickbooks.enable'), {}, {
        preserveScroll: true,
        onFinish: () => {
            enabling.value = false;
        },
    });
}

function formatDate(value) {
    if (!value) return null;
    try {
        return new Date(value).toLocaleString();
    } catch (_e) {
        return value;
    }
}

const qbConnectedAt = computed(() => formatDate(props.quickbooks?.connected_at));
const qbTokenExpiresAt = computed(() => formatDate(props.quickbooks?.token_expires_at));
const qbRefreshExpiresAt = computed(() => formatDate(props.quickbooks?.refresh_token_expires_at));
const isQuickbooksEnabled = computed(() => props.isQuickbooksEnabled);
const isQuickbooksDisabled = computed(() => props.hasQuickbooksToken && !isQuickbooksEnabled.value);
const importsAvailable = computed(() => props.hasQuickbooksToken && isQuickbooksEnabled.value);

const syncForm = useForm({
    sync_contacts: props.syncSettings?.sync_contacts ?? false,
    sync_invoices: props.syncSettings?.sync_invoices ?? false,
    sync_payments: props.syncSettings?.sync_payments ?? false,
    sync_bills: props.syncSettings?.sync_bills ?? false,
    sync_bill_payments: props.syncSettings?.sync_bill_payments ?? false,
});

function saveSyncSettings() {
    syncForm.patch(route('quickbooks.settings'), {
        preserveScroll: true,
    });
}

const importButtonClass = 'inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700';
const billImportButtonClass = 'inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="integration.name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ integration.name }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ integration.description }}
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-6">
            <div
                v-if="oauthNotice?.type === 'success'"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ oauthNotice.message }}
            </div>
            <div
                v-if="oauthNotice?.type === 'error'"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ oauthNotice.message }}
            </div>
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ flashError }}
            </div>

            <div
                v-if="hasQuickbooksToken && anyImporting"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-100"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-center gap-3">
                    <span class="material-icons animate-spin text-lg leading-none text-blue-600 dark:text-blue-300" aria-hidden="true">sync</span>
                    <span>{{ importStatusMessage }}</span>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-900 hover:bg-blue-100 disabled:opacity-60 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100 dark:hover:bg-blue-900/40"
                    :disabled="clearingImportStatus"
                    @click="clearStuckImport"
                >
                    {{ clearingImportStatus ? 'Clearing…' : 'Clear stuck import' }}
                </button>
            </div>

            <div
                v-if="hasQuickbooksToken && importErrorMessage && !anyImporting"
                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-900/20 dark:text-red-100"
                role="alert"
            >
                <div>
                    <p class="font-medium">Import failed</p>
                    <p class="mt-1">{{ importErrorMessage }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-900 hover:bg-red-100 disabled:opacity-60 dark:border-red-800 dark:bg-red-950 dark:text-red-100 dark:hover:bg-red-900/40"
                    :disabled="clearingImportStatus"
                    @click="clearStuckImport"
                >
                    {{ clearingImportStatus ? 'Clearing…' : 'Dismiss' }}
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <template v-if="hasQuickbooksToken">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                QuickBooks Online is linked to this workspace.
                                <span v-if="isQuickbooksEnabled">Sync and imports are active.</span>
                                <span v-else>The integration is disabled — Helmful will not sync with QuickBooks until you turn it back on.</span>
                            </p>
                        </div>
                        <span
                            class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="isQuickbooksEnabled
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'"
                        >
                            {{ isQuickbooksEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <div
                        v-if="isQuickbooksDisabled"
                        class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-100"
                    >
                        QuickBooks is disabled. Bills and bill payments will be saved in Helmful only until you enable the integration again.
                        <button
                            type="button"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-60"
                            :disabled="enabling"
                            @click="enableIntegration"
                        >
                            <span v-if="enabling" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                            {{ enabling ? 'Enabling…' : 'Enable QuickBooks' }}
                        </button>
                    </div>

                    <p
                        v-if="currentIntegration?.last_synced_at"
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Last sync: {{ currentIntegration.last_synced_at }}
                    </p>

                    <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                        <div v-if="quickbooks?.realm_id" class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Realm ID</dt>
                            <dd class="font-mono text-xs text-gray-900 dark:text-gray-100">{{ quickbooks.realm_id }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Environment</dt>
                            <dd class="text-gray-900 dark:text-white">{{ quickbooks?.environment || '—' }}</dd>
                        </div>
                        <div v-if="quickbooks?.company_name" class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Company</dt>
                            <dd class="text-right text-gray-900 dark:text-white">{{ quickbooks.company_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Connected</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbConnectedAt || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Access token expires</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbTokenExpiresAt || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Refresh token expires</dt>
                            <dd class="text-gray-900 dark:text-white">{{ qbRefreshExpiresAt || '—' }}</dd>
                        </div>
                    </dl>

                    <div
                        v-if="importsAvailable"
                        class="mt-4 space-y-4 border-t border-gray-100 pt-4 dark:border-gray-700"
                    >
                        <div>
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Pages
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    :href="route('contacts.index')"
                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Go to contacts
                                </Link>
                                <Link
                                    :href="route('serviceitems.index')"
                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Service items
                                </Link>
                                <Link
                                    :href="route('vendors.index')"
                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Vendors
                                </Link>
                                <Link
                                    :href="route('bills.index')"
                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Bills
                                </Link>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Import from QuickBooks
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :class="importButtonClass"
                                    :disabled="importingCustomers"
                                    @click="quickBooksImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingCustomers" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingCustomers ? 'Importing customers…' : 'Import customers' }}
                                </button>
                                <button
                                    type="button"
                                    :class="importButtonClass"
                                    :disabled="importingServices"
                                    @click="quickBooksServiceImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingServices" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingServices ? 'Importing services…' : 'Import services' }}
                                </button>
                                <button
                                    type="button"
                                    :class="importButtonClass"
                                    :disabled="importingVendors"
                                    @click="quickBooksVendorImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingVendors" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingVendors ? 'Importing vendors…' : 'Import vendors' }}
                                </button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                Import bill data
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Import vendors first, then chart of accounts, bills, and bill payments.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :class="billImportButtonClass"
                                    :disabled="importingChartOfAccounts"
                                    @click="quickBooksChartOfAccountsImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingChartOfAccounts" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingChartOfAccounts ? 'Importing chart of accounts…' : 'Chart of accounts' }}
                                </button>
                                <button
                                    type="button"
                                    :class="billImportButtonClass"
                                    :disabled="importingBills"
                                    @click="quickBooksBillImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingBills" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingBills ? 'Importing bills…' : 'Bills' }}
                                </button>
                                <button
                                    type="button"
                                    :class="billImportButtonClass"
                                    :disabled="importingBillPayments"
                                    @click="quickBooksBillPaymentImportRef?.openImportModal?.()"
                                >
                                    <span v-if="importingBillPayments" class="material-icons animate-spin text-base leading-none" aria-hidden="true">sync</span>
                                    {{ importingBillPayments ? 'Importing bill payments…' : 'Bill payments' }}
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                            <button
                                type="button"
                                class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="disableIntegration"
                            >
                                Disable integration
                            </button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Connect your QuickBooks Online company to import customers as contacts with a customer or lead profile (export to QBO can build on this connection later).
                    </p>
                    <a
                        :href="route('quickbooks.connect')"
                        class="mt-4 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Connect with QuickBooks
                    </a>
                </template>
            </div>

            <div
                v-if="hasQuickbooksToken"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Sync options</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Choose what to sync automatically with QuickBooks Online.
                </p>
                <form class="mt-4 space-y-3" @submit.prevent="saveSyncSettings">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncForm.sync_contacts"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Sync contacts</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Push new contacts to QuickBooks when created.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncForm.sync_invoices"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Sync invoices</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Push to QuickBooks before sending the invoice to the customer.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncForm.sync_payments"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Sync payment records</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                Allow pulling payments recorded in QuickBooks onto synced invoices.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncForm.sync_bills"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Sync bills</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                When enabled, new bills are pushed to QuickBooks and linked back on success. When disabled, bills stay in Helmful only.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncForm.sync_bill_payments"
                            type="checkbox"
                            class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Sync bill payments</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                When enabled, new bill payments are pushed to QuickBooks and linked back on success. When disabled, payments stay in Helmful only.
                            </span>
                        </span>
                    </label>
                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="syncForm.processing"
                        >
                            {{ syncForm.processing ? 'Saving…' : 'Save sync options' }}
                        </button>
                    </div>
                </form>
            </div>

            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksImportRef"
                v-model:importing="importingCustomers"
            />
            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksServiceImportRef"
                v-model:importing="importingServices"
                record-type="serviceitem"
            />
            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksChartOfAccountsImportRef"
                v-model:importing="importingChartOfAccounts"
                record-type="chartofaccounts"
            />
            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksVendorImportRef"
                v-model:importing="importingVendors"
                record-type="vendor"
            />
            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksBillImportRef"
                v-model:importing="importingBills"
                record-type="bill"
            />
            <QuickBooksImport
                v-if="importsAvailable"
                ref="quickBooksBillPaymentImportRef"
                v-model:importing="importingBillPayments"
                record-type="billpayment"
            />
        </div>
    </TenantLayout>
</template>
