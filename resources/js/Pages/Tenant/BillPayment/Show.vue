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
    recordType: { type: String, default: 'bill-payments' },
    recordTitle: { type: String, default: 'Bill payment' },
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

const pushing = ref(false);
const pulling = ref(false);

const paymentLabel = computed(() => props.record.display_name || `Payment #${props.record.id}`);
const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));
const isEditRestricted = computed(() => !!props.editRestrictions?.restricted);
const editButtonLabel = computed(() => (isEditRestricted.value ? 'Link records' : 'Edit'));

const canPush = computed(() =>
    props.quickbooks?.connected && !props.record.quickbooks_bill_payment_id,
);
const canPull = computed(() =>
    props.quickbooks?.connected
    && props.quickbooks?.sync_bill_payments_enabled
    && !!props.record.quickbooks_bill_payment_id,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Bill payments', href: indexHref.value },
    { label: paymentLabel.value },
]);

const paymentLines = computed(() => {
    const lines = props.record.lines ?? [];

    return lines.map((line) => ({
        id: line.id,
        amount: line.amount,
        bill: line.bill ?? null,
        billId: line.bill_id ?? line.bill?.id ?? null,
        billLabel: line.bill?.display_name
            ?? (line.bill?.sequence != null ? `BILL-${line.bill.sequence}` : null),
        billBalance: line.bill?.balance ?? null,
        billStatus: line.bill?.status ?? null,
    }));
});

const payAccountLabel = computed(() => {
    if (props.record.pay_type === 'CreditCard') {
        return props.record.cc_account_ref_name || '—';
    }

    return props.record.bank_account_ref_name || '—';
});

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

function billShowHref(billId) {
    if (!billId) {
        return null;
    }

    try {
        return route('bills.show', buildResourceRouteParams('bills', billId));
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

function pushToQuickbooks() {
    pushing.value = true;
    router.post(route('bill-payments.push-to-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pushing.value = false; },
    });
}

function pullFromQuickbooks() {
    pulling.value = true;
    router.post(route('bill-payments.pull-from-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { pulling.value = false; },
    });
}
</script>

<template>
    <Head :title="paymentLabel" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ paymentLabel }}</h2>
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
                <div class="lg:col-span-8 space-y-6">
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="mb-1 flex flex-wrap items-center gap-3">
                                        <h1 class="text-2xl font-bold text-white">PAYMENT</h1>
                                        <span
                                            v-if="record.quickbooks_bill_payment_id"
                                            class="inline-flex items-center rounded-full bg-white/20 px-2.5 py-1 text-sm font-semibold text-white"
                                        >
                                            QuickBooks
                                        </span>
                                    </div>
                                    <p class="text-base text-primary-100">Vendor bill payment</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-primary-200">Reference</div>
                                    <div class="font-mono text-lg text-white">{{ paymentLabel }}</div>
                                    <div v-if="record.doc_number" class="mt-1 text-sm text-primary-100">
                                        Ref #{{ record.doc_number }}
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
                                    Payment details
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Payment date
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.txn_date) }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Pay type
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.pay_type || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Currency
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.currency_code || 'USD' }}</div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Pay from account
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ payAccountLabel }}</div>
                                    </div>
                                    <div v-if="record.ap_account_ref_name">
                                        <div class="mb-1 text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            AP account
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.ap_account_ref_name }}</div>
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

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Bills paid</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bills this payment was applied to.
                            </p>
                        </div>

                        <div v-if="paymentLines.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Status
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Bill balance
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Applied
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    <tr v-for="line in paymentLines" :key="line.id">
                                        <td class="px-5 py-3 text-sm">
                                            <Link
                                                v-if="billShowHref(line.billId)"
                                                :href="billShowHref(line.billId)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                {{ line.billLabel || `Bill #${line.billId}` }}
                                            </Link>
                                            <span v-else class="text-gray-900 dark:text-white">—</span>
                                        </td>
                                        <td class="px-5 py-3 text-sm capitalize text-gray-700 dark:text-gray-300">
                                            {{ line.billStatus || '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                            {{ formatCurrency(line.billBalance) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-green-600 dark:text-green-400">
                                            {{ formatCurrency(line.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <td colspan="3" class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                            Total applied
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
                            <p class="text-md text-gray-400 dark:text-gray-500">No bills linked to this payment</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-4">
                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Payment total
                            </span>
                        </div>
                        <div class="space-y-2.5 p-4 text-md">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Amount</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(record.total_amt) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Bills paid</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ paymentLines.length }}</span>
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
                                <span class="text-gray-500 dark:text-gray-400">Payment ID</span>
                                <span class="text-right font-mono text-gray-900 dark:text-white">
                                    {{ record.quickbooks_bill_payment_id || '—' }}
                                </span>
                            </li>
                            <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Sync</span>
                                <span class="text-right text-gray-900 dark:text-white">
                                    {{ quickbooks?.sync_bill_payments_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </li>
                        </ul>
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
