<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { useQuickBooksApSyncOverlay } from '@/composables/useQuickBooksApSyncOverlay';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';

const page = usePage();
const inertiaApp = getCurrentInstance();
const { handleCreateFlash } = useQuickBooksApSyncOverlay();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

watch(() => page.props.flash?.success, (msg) => { if (msg) showToast('success', msg); }, { immediate: true });
watch(() => page.props.flash?.error, (msg) => { if (msg) showToast('error', msg); }, { immediate: true });
watch(() => page.props.flash?.warning, (msg) => { if (msg) showToast('warning', msg); }, { immediate: true });

watch(
    () => page.props.flash,
    (flash) => handleCreateFlash(flash),
    { immediate: true, deep: true },
);

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'bills' },
    recordTitle: { type: String, default: 'Bill' },
    enumOptions: { type: Object, default: () => ({}) },
    quickbooks: { type: Object, default: () => ({}) },
    editRestrictions: {
        type: Object,
        default: () => ({
            restricted: false,
            allowedFields: ['vendor_id'],
            reason: null,
        }),
    },
});

const STATUS_ENUM = 'App\\Enums\\Bill\\Status';

const pushing = ref(false);
const pulling = ref(false);
const payingBill = ref(false);

const billLabel = computed(() => props.record.display_name || `Bill #${props.record.id}`);
const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));
const isEditRestricted = computed(() => !!props.editRestrictions?.restricted);
const editButtonLabel = computed(() => (isEditRestricted.value ? 'Link records' : 'Edit'));

const canPush = computed(() =>
    props.quickbooks?.connected && !props.record.quickbooks_bill_id,
);
const canPayBill = computed(() =>
    props.record.status !== 'paid'
    && parseFloat(props.record.balance || 0) > 0.009,
);
const canPull = computed(() =>
    props.quickbooks?.connected
    && props.quickbooks?.sync_bills_enabled
    && !!props.record.quickbooks_bill_id,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bills', href: indexHref.value },
    { label: billLabel.value },
]);

const statusInfo = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM] ?? [];
    const hit = opts.find(
        (o) => o.value === props.record.status || String(o.id) === String(props.record.status),
    );

    return hit ?? {
        name: props.record.status || '—',
        bgClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };
});

const lineItems = computed(() => props.record.items ?? []);

const paymentApplications = computed(() => {
    const lines = props.record.bill_payment_lines ?? props.record.billPaymentLines ?? [];

    return lines
        .map((line) => {
            const payment = line.bill_payment ?? line.billPayment ?? null;

            return {
                lineId: line.id,
                amount: line.amount,
                payment,
                paymentId: payment?.id ?? null,
                paymentLabel: payment?.display_name
                    ?? (payment?.sequence != null ? `BPAY-${payment.sequence}` : null),
                txnDate: payment?.txn_date ?? null,
                payType: payment?.pay_type ?? null,
                docNumber: payment?.doc_number ?? null,
            };
        })
        .filter((row) => row.paymentId != null || parseFloat(row.amount || 0) !== 0);
});

const amountPaid = computed(() =>
    paymentApplications.value.reduce((sum, row) => sum + parseFloat(row.amount || 0), 0),
);

const quickbooksBillUrl = computed(() => props.record.meta?.quickbooks_bill_url ?? null);

const vendorHref = computed(() => {
    const vendorId = props.record.vendor_id ?? props.record.vendor?.id;
    if (!vendorId) {
        return null;
    }

    try {
        return route('vendors.show', vendorId);
    } catch {
        return null;
    }
});

const apAccount = computed(() => props.record.ap_chart_of_account ?? props.record.apChartOfAccount ?? null);

const apAccountHref = computed(() => {
    if (!apAccount.value?.id) {
        return null;
    }

    try {
        return route('chart-of-accounts.show', apAccount.value.id);
    } catch {
        return null;
    }
});

const apAccountLabel = computed(() =>
    props.record.ap_account_ref_name
    || apAccount.value?.fully_qualified_name
    || apAccount.value?.name
    || '—',
);

function chartOfAccountHref(account) {
    if (!account?.id) {
        return null;
    }

    try {
        return route('chart-of-accounts.show', account.id);
    } catch {
        return null;
    }
}

function formatCurrency(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `$${parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    try {
        const raw = typeof value === 'string' ? value.split('T')[0] : value;
        return new Date(`${raw}T00:00:00`).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

function accountLabel(item) {
    const account = item.chart_of_account ?? item.chartOfAccount;
    if (account?.fully_qualified_name) {
        return account.fully_qualified_name;
    }
    if (account?.name) {
        return account.name;
    }
    if (item.expense_account_ref_name) {
        return item.expense_account_ref_name;
    }

    return '—';
}

function paymentShowHref(paymentId) {
    if (!paymentId) {
        return null;
    }

    try {
        return route('bill-payments.show', buildResourceRouteParams('bill-payments', paymentId));
    } catch {
        return null;
    }
}

function pushToQuickbooks() {
    pushing.value = true;
    router.post(route('bills.push-to-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pushing.value = false; },
    });
}

function payBill() {
    payingBill.value = true;
    router.post(route('bills.pay', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { payingBill.value = false; },
    });
}

function pullFromQuickbooks() {
    pulling.value = true;
    router.post(route('bills.pull-from-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pulling.value = false; },
    });
}
</script>

<template>
    <Head :title="billLabel" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ billLabel }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canPush"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="pushing"
                        @click="pushToQuickbooks"
                    >
                        <span v-if="pushing" class="material-icons animate-spin text-base leading-none">sync</span>
                        {{ pushing ? 'Syncing…' : 'Sync to QuickBooks' }}
                    </button>
                    <button
                        v-if="canPayBill"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900"
                        :disabled="payingBill"
                        @click="payBill"
                    >
                        <span v-if="payingBill" class="material-icons animate-spin text-base leading-none">sync</span>
                        {{ payingBill ? 'Paying…' : 'Pay bill' }}
                    </button>
                    <button
                        v-if="canPull"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="pulling"
                        @click="pullFromQuickbooks"
                    >
                        <span v-if="pulling" class="material-icons animate-spin text-base leading-none">sync</span>
                        {{ pulling ? 'Refreshing…' : 'Refresh from QuickBooks' }}
                    </button>
                    <Link
                        :href="editHref"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <span class="material-icons text-base leading-none">{{ isEditRestricted ? 'link' : 'edit' }}</span>
                        {{ editButtonLabel }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Main column -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Header card -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="mb-1 flex flex-wrap items-center gap-3">
                                        <h1 class="text-2xl font-bold text-white">BILL</h1>
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold"
                                            :class="statusInfo.bgClass"
                                        >
                                            {{ statusInfo.name }}
                                        </span>
                                    </div>
                                    <p class="text-base text-primary-100">Accounts payable bill</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-primary-200">Reference</div>
                                    <div class="font-mono text-lg text-white">{{ billLabel }}</div>
                                    <div v-if="record.doc_number" class="mt-1 text-sm text-primary-100">
                                        Doc #{{ record.doc_number }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6 p-6">
                            <section class="space-y-4">
                                <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Vendor
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Vendor
                                        </div>
                                        <Link
                                            v-if="vendorHref"
                                            :href="vendorHref"
                                            class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ record.vendor?.display_name || 'View vendor' }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-900 dark:text-white">
                                            {{ record.vendor?.display_name || '—' }}
                                        </div>
                                    </div>
                                    <div v-if="record.quickbooks_vendor_id">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            QBO vendor ID
                                        </div>
                                        <div class="font-mono text-sm text-gray-900 dark:text-white">
                                            {{ record.quickbooks_vendor_id }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-4">
                                <h3 class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Bill details
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill date
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.txn_date) }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Due date
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.due_date) }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Currency
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.currency_code || 'USD' }}</div>
                                    </div>
                                    <div v-if="record.ap_account_ref_name || apAccount">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            AP account
                                        </div>
                                        <Link
                                            v-if="apAccountHref"
                                            :href="apAccountHref"
                                            class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                        >
                                            {{ apAccountLabel }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-900 dark:text-white">
                                            {{ apAccountLabel }}
                                        </div>
                                    </div>
                                    <div v-if="record.department_ref_name">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Department
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.department_ref_name }}</div>
                                    </div>
                                    <div v-if="record.chart_of_account || record.chartOfAccount">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Primary account
                                        </div>
                                        <Link
                                            v-if="chartOfAccountHref(record.chart_of_account ?? record.chartOfAccount)"
                                            :href="chartOfAccountHref(record.chart_of_account ?? record.chartOfAccount)"
                                            class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                        >
                                            {{ (record.chart_of_account ?? record.chartOfAccount)?.fully_qualified_name
                                                || (record.chart_of_account ?? record.chartOfAccount)?.name
                                                || '—' }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-900 dark:text-white">
                                            {{ (record.chart_of_account ?? record.chartOfAccount)?.fully_qualified_name
                                                || (record.chart_of_account ?? record.chartOfAccount)?.name
                                                || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section
                                v-if="record.private_note"
                                class="border-t border-gray-200 pt-5 dark:border-gray-700"
                            >
                                <div class="mb-2 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Memo
                                </div>
                                <div class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">
                                    {{ record.private_note }}
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Expense line items -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Line items</h2>
                        </div>

                        <div v-if="lineItems.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Description
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Account
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Qty
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Rate
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="item in lineItems" :key="item.id">
                                        <td class="px-5 py-3 text-sm text-gray-900 dark:text-white">
                                            <div class="font-medium">{{ item.description || '—' }}</div>
                                            <div v-if="item.detail_type" class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ item.detail_type }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            <Link
                                                v-if="chartOfAccountHref(item.chart_of_account ?? item.chartOfAccount)"
                                                :href="chartOfAccountHref(item.chart_of_account ?? item.chartOfAccount)"
                                                class="font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                            >
                                                {{ accountLabel(item) }}
                                            </Link>
                                            <span v-else>{{ accountLabel(item) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                            {{ item.quantity ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                            {{ item.unit_price != null ? formatCurrency(item.unit_price) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(item.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <td colspan="4" class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                            Total
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-bold text-primary-600 dark:text-primary-400">
                                            {{ formatCurrency(record.total_amt) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-6 py-12 text-center">
                            <span class="material-icons mb-3 text-[40px] text-gray-300 dark:text-gray-600">receipt_long</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No line items on this bill</p>
                        </div>
                    </div>

                    <!-- Payments applied to this bill -->
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Payments</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bill payments applied to this bill from QuickBooks or recorded here.
                            </p>
                        </div>

                        <div v-if="paymentApplications.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Payment
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Date
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Type
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Reference
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Applied
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="row in paymentApplications" :key="row.lineId">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="paymentShowHref(row.paymentId)"
                                                :href="paymentShowHref(row.paymentId)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ row.paymentLabel || `Payment #${row.paymentId}` }}
                                            </Link>
                                            <span v-else class="text-gray-900 dark:text-white">—</span>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ formatDate(row.txnDate) }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ row.payType || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ row.docNumber || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-green-600 dark:text-green-400">
                                            {{ formatCurrency(row.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-6 py-12 text-center">
                            <span class="material-icons mb-3 text-[40px] text-gray-300 dark:text-gray-600">payments</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No payments applied to this bill yet</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4 lg:col-span-4">
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Bill total
                            </span>
                        </div>
                        <div class="space-y-2.5 p-4 text-md">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Total</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.total_amt) }}</span>
                            </div>
                            <div
                                v-if="amountPaid > 0"
                                class="flex items-center justify-between text-green-600 dark:text-green-400"
                            >
                                <span>Amount paid</span>
                                <span class="font-medium">-{{ formatCurrency(amountPaid) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-gray-700">
                                <span class="font-semibold text-gray-900 dark:text-white">Balance</span>
                                <span
                                    class="text-lg font-bold"
                                    :class="parseFloat(record.balance || 0) <= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-gray-900 dark:text-white'"
                                >
                                    {{ formatCurrency(record.balance) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                QuickBooks
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Bill ID</span>
                                <span class="text-right font-mono text-gray-900 dark:text-white">
                                    {{ record.quickbooks_bill_id || '—' }}
                                </span>
                            </li>
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Sync</span>
                                <span class="text-right text-gray-900 dark:text-white">
                                    {{ quickbooks?.sync_bills_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </li>
                        </ul>
                        <div v-if="quickbooksBillUrl" class="border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                            <a
                                :href="quickbooksBillUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Open in QuickBooks
                                <span class="material-icons text-base leading-none">open_in_new</span>
                            </a>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Record
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.created_at) }}</span>
                            </li>
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span class="text-gray-500 dark:text-gray-400">Updated</span>
                                <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.updated_at) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
