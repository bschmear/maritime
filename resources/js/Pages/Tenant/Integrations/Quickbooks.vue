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
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h2>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Accounting integration</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('integrations')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                        >
                            <span class="material-icons text-[15px]">arrow_back</span>
                            All integrations
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl space-y-5 px-4 py-6">

            <!-- Flash / OAuth notices -->
            <div
                v-if="oauthNotice?.type === 'success' || flashSuccess"
                class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-green-600 dark:text-green-400">check_circle</span>
                {{ oauthNotice?.type === 'success' ? oauthNotice.message : flashSuccess }}
            </div>
            <div
                v-if="oauthNotice?.type === 'error' || flashError"
                class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
            >
                <span class="material-icons mt-0.5 shrink-0 text-[16px] text-red-600 dark:text-red-400">error</span>
                {{ oauthNotice?.type === 'error' ? oauthNotice.message : flashError }}
            </div>

            <!-- Import in progress -->
            <div
                v-if="hasQuickbooksToken && anyImporting"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-md text-blue-900 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-100"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-center gap-3">
                    <span class="material-icons animate-spin text-[18px] text-blue-600 dark:text-blue-300" aria-hidden="true">sync</span>
                    <span>{{ importStatusMessage }}</span>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-md font-medium text-blue-900 hover:bg-blue-100 disabled:opacity-60 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100 dark:hover:bg-blue-900/40"
                    :disabled="clearingImportStatus"
                    @click="clearStuckImport"
                >
                    {{ clearingImportStatus ? 'Clearing...' : 'Clear stuck import' }}
                </button>
            </div>

            <!-- Import error -->
            <div
                v-if="hasQuickbooksToken && importErrorMessage && !anyImporting"
                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-md text-red-900 dark:border-red-900 dark:bg-red-900/20 dark:text-red-100"
                role="alert"
            >
                <div>
                    <p class="font-semibold">Import failed</p>
                    <p class="mt-1">{{ importErrorMessage }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center rounded-lg border border-red-300 bg-white px-3 py-1.5 text-md font-medium text-red-900 hover:bg-red-100 disabled:opacity-60 dark:border-red-800 dark:bg-red-950 dark:text-red-100 dark:hover:bg-red-900/40"
                    :disabled="clearingImportStatus"
                    @click="clearStuckImport"
                >
                    {{ clearingImportStatus ? 'Clearing...' : 'Dismiss' }}
                </button>
            </div>

            <!-- ── What is QuickBooks ───────────────────────────────── -->
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">

                <!-- Header strip -->
                <div class="flex items-center gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-700">
                        <span class="material-icons text-[22px] text-green-600 dark:text-green-400">account_balance</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">QuickBooks Online</h3>
                            <span
                                v-if="hasQuickbooksToken && isQuickbooksEnabled"
                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-md font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                                Connected &amp; enabled
                            </span>
                            <span
                                v-else-if="hasQuickbooksToken"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-md font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                                Connected — disabled
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-md font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Not connected
                            </span>
                        </div>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Cloud accounting by Intuit</p>
                    </div>
                    <a
                        href="https://quickbooks.intuit.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-md font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        quickbooks.intuit.com
                        <span class="material-icons text-[13px]">open_in_new</span>
                    </a>
                </div>

                <!-- Body -->
                <div class="px-6 py-5">
                    <p class="text-md leading-relaxed text-gray-700 dark:text-gray-300">
                        QuickBooks Online is the most widely used cloud accounting platform for small and mid-size businesses. Connecting it to Helmful keeps your books in sync automatically: contacts, invoices, bills, and payments flow between both systems so you never have to enter the same data twice.
                    </p>

                    <!-- Benefits grid -->
                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">people</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Contacts and customers</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Import QuickBooks customers as Helmful contacts, or push new contacts across automatically.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">receipt_long</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Invoices and payments</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Invoices created in Helmful push to QuickBooks, and payments recorded there sync back.</p>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                            <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">request_quote</span>
                            <h4 class="mt-2 text-md font-semibold text-gray-900 dark:text-white">Bills and vendors</h4>
                            <p class="mt-1 text-md leading-relaxed text-gray-500 dark:text-gray-400">Bills and bill payments created in Helmful post to QuickBooks and link back on success.</p>
                        </div>
                    </div>

                    <!-- Sign-up callout (only when not connected) -->
                    <div
                        v-if="!hasQuickbooksToken"
                        class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-100 bg-primary-50 px-4 py-3.5 dark:border-primary-900/50 dark:bg-primary-900/20"
                    >
                        <div>
                            <p class="text-md font-medium text-primary-900 dark:text-primary-200">Don't have a QuickBooks account yet?</p>
                            <p class="mt-0.5 text-md text-primary-700 dark:text-primary-300">Sign up with Intuit and come back to connect your company once your account is ready.</p>
                        </div>
                        <a
                            href="https://quickbooks.intuit.com/signup"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                        >
                            Create an account
                            <span class="material-icons text-[15px]">open_in_new</span>
                        </a>
                    </div>

                    <!-- Connect CTA (not yet connected) -->
                    <div v-if="!hasQuickbooksToken" class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <p class="text-md text-gray-600 dark:text-gray-300">
                            Connect your QuickBooks Online company to start syncing. You'll be taken to Intuit to authorize access and returned here when done.
                        </p>
                        <a
                            :href="route('quickbooks.connect')"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-md font-semibold text-white hover:bg-primary-700"
                        >
                            <span class="material-icons text-[18px]">link</span>
                            Connect with QuickBooks
                        </a>
                    </div>

                    <!-- Connected: disabled warning -->
                    <div
                        v-if="hasQuickbooksToken && isQuickbooksDisabled"
                        class="mt-5 flex flex-wrap items-start justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3.5 dark:border-amber-900 dark:bg-amber-900/20"
                    >
                        <div>
                            <p class="text-md font-semibold text-amber-900 dark:text-amber-200">Integration is disabled</p>
                            <p class="mt-1 text-md text-amber-800 dark:text-amber-300">Bills and bill payments are being saved in Helmful only. QuickBooks will not receive any data until you enable the integration again.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-60"
                            :disabled="enabling"
                            @click="enableIntegration"
                        >
                            <span v-if="enabling" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                            {{ enabling ? 'Enabling...' : 'Enable QuickBooks' }}
                        </button>
                    </div>

                    <!-- Connected: metadata -->
                    <div v-if="hasQuickbooksToken" class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                            <div v-if="quickbooks?.company_name">
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Company</dt>
                                <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ quickbooks.company_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Environment</dt>
                                <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ quickbooks?.environment || '—' }}</dd>
                            </div>
                            <div v-if="quickbooks?.realm_id">
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Realm ID</dt>
                                <dd class="mt-1 font-mono text-md text-gray-900 dark:text-gray-100">{{ quickbooks.realm_id }}</dd>
                            </div>
                            <div>
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Connected</dt>
                                <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ qbConnectedAt || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Token expires</dt>
                                <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ qbTokenExpiresAt || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Refresh expires</dt>
                                <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ qbRefreshExpiresAt || '—' }}</dd>
                            </div>
                        </dl>
                        <p v-if="currentIntegration?.last_synced_at" class="mt-3 text-md text-gray-500 dark:text-gray-400">
                            Last sync: {{ currentIntegration.last_synced_at }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- ── Sync options (connected) ─────────────────────────── -->
            <section
                v-if="hasQuickbooksToken"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Sync options</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Choose what syncs automatically between Helmful and QuickBooks Online.</p>
                </div>
                <form class="space-y-0 divide-y divide-gray-50 px-6 dark:divide-gray-700/60" @submit.prevent="saveSyncSettings">
                    <label class="flex cursor-pointer items-start gap-3 py-4">
                        <input v-model="syncForm.sync_contacts" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                        <span>
                            <span class="block text-md font-semibold text-gray-900 dark:text-white">Contacts</span>
                            <span class="block text-md text-gray-500 dark:text-gray-400">Push new contacts to QuickBooks when created in Helmful.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 py-4">
                        <input v-model="syncForm.sync_invoices" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                        <span>
                            <span class="block text-md font-semibold text-gray-900 dark:text-white">Invoices</span>
                            <span class="block text-md text-gray-500 dark:text-gray-400">Push invoices to QuickBooks before sending them to the customer.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 py-4">
                        <input v-model="syncForm.sync_payments" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                        <span>
                            <span class="block text-md font-semibold text-gray-900 dark:text-white">Payment records</span>
                            <span class="block text-md text-gray-500 dark:text-gray-400">Pull payments recorded in QuickBooks onto synced invoices in Helmful.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 py-4">
                        <input v-model="syncForm.sync_bills" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                        <span>
                            <span class="block text-md font-semibold text-gray-900 dark:text-white">Bills</span>
                            <span class="block text-md text-gray-500 dark:text-gray-400">New bills are pushed to QuickBooks and linked back on success. When off, bills stay in Helmful only.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 py-4">
                        <input v-model="syncForm.sync_bill_payments" type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" />
                        <span>
                            <span class="block text-md font-semibold text-gray-900 dark:text-white">Bill payments</span>
                            <span class="block text-md text-gray-500 dark:text-gray-400">New bill payments are pushed to QuickBooks and linked back on success. When off, they stay in Helmful only.</span>
                        </span>
                    </label>
                    <div class="py-4">
                        <button
                            type="submit"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="syncForm.processing"
                        >
                            {{ syncForm.processing ? 'Saving...' : 'Save sync options' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- ── Imports (connected) ─────────────────────────────── -->
            <section
                v-if="importsAvailable"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Import from QuickBooks</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Pull existing records from QuickBooks into Helmful. Run imports in the order shown.</p>
                </div>

                <div class="space-y-5 px-6 py-5">

                    <!-- General imports -->
                    <div>
                        <p class="mb-3 text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Contacts, services, and vendors</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" :class="importButtonClass" :disabled="importingCustomers" @click="quickBooksImportRef?.openImportModal?.()">
                                <span v-if="importingCustomers" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingCustomers ? 'Importing customers...' : 'Import customers' }}
                            </button>
                            <button type="button" :class="importButtonClass" :disabled="importingServices" @click="quickBooksServiceImportRef?.openImportModal?.()">
                                <span v-if="importingServices" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingServices ? 'Importing services...' : 'Import services' }}
                            </button>
                            <button type="button" :class="importButtonClass" :disabled="importingVendors" @click="quickBooksVendorImportRef?.openImportModal?.()">
                                <span v-if="importingVendors" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingVendors ? 'Importing vendors...' : 'Import vendors' }}
                            </button>
                        </div>
                    </div>

                    <!-- Bill data imports -->
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-md font-semibold text-gray-900 dark:text-white">Bill data</p>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Import vendors first, then chart of accounts, bills, and bill payments in order.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" :class="billImportButtonClass" :disabled="importingChartOfAccounts" @click="quickBooksChartOfAccountsImportRef?.openImportModal?.()">
                                <span v-if="importingChartOfAccounts" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingChartOfAccounts ? 'Importing...' : 'Chart of accounts' }}
                            </button>
                            <button type="button" :class="billImportButtonClass" :disabled="importingBills" @click="quickBooksBillImportRef?.openImportModal?.()">
                                <span v-if="importingBills" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingBills ? 'Importing...' : 'Bills' }}
                            </button>
                            <button type="button" :class="billImportButtonClass" :disabled="importingBillPayments" @click="quickBooksBillPaymentImportRef?.openImportModal?.()">
                                <span v-if="importingBillPayments" class="material-icons animate-spin text-[16px]" aria-hidden="true">sync</span>
                                {{ importingBillPayments ? 'Importing...' : 'Bill payments' }}
                            </button>
                        </div>
                    </div>

                    <!-- Quick links -->
                    <div>
                        <p class="mb-3 text-md font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" style="font-size: 0.7rem;">Go to</p>
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('contacts.index')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Contacts
                            </Link>
                            <Link :href="route('serviceitems.index')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Service items
                            </Link>
                            <Link :href="route('vendors.index')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Vendors
                            </Link>
                            <Link :href="route('bills.index')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Bills
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Danger zone ─────────────────────────────────────── -->
            <section
                v-if="hasQuickbooksToken && importsAvailable"
                class="rounded-xl border border-red-100 bg-white shadow-sm dark:border-red-900/40 dark:bg-gray-800"
            >
                <div class="border-b border-red-100 px-6 py-4 dark:border-red-900/40">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Disconnect</h3>
                    <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">Disabling the integration hides shipping features from navigation. Your data in Helmful is not affected.</p>
                </div>
                <div class="px-6 py-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-md font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/30"
                        @click="disableIntegration"
                    >
                        <span class="material-icons text-[15px]">link_off</span>
                        Disable integration
                    </button>
                </div>
            </section>

        </div>

        <!-- Hidden import modal triggers -->
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksImportRef" v-model:importing="importingCustomers" />
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksServiceImportRef" v-model:importing="importingServices" record-type="serviceitem" />
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksChartOfAccountsImportRef" v-model:importing="importingChartOfAccounts" record-type="chartofaccounts" />
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksVendorImportRef" v-model:importing="importingVendors" record-type="vendor" />
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksBillImportRef" v-model:importing="importingBills" record-type="bill" />
        <QuickBooksImport v-if="importsAvailable" ref="quickBooksBillPaymentImportRef" v-model:importing="importingBillPayments" record-type="billpayment" />

    </TenantLayout>
</template>