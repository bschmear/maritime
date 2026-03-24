<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContractPreview from '@/Components/Tenant/ContractPreview.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
});

const STATUS_ENUM_KEY = 'App\\Enums\\Contract\\ContractStatus';
const PAYMENT_ENUM_KEY = 'App\\Enums\\Contract\\ContractPaymentStatus';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentOptions = computed(() => props.enumOptions?.[PAYMENT_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);

/** Label + description for {@link record.payment_term} (string value e.g. due_on_receipt). */
const paymentTermInfo = computed(() => {
    const raw = props.record?.payment_term;
    if (raw == null || raw === '') {
        return { name: '—', description: '' };
    }
    const opt = paymentTermOptions.value.find(
        (o) => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw),
    );
    if (opt) {
        return { name: opt.name, description: opt.description || '' };
    }
    return {
        name: String(raw).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
        description: '',
    };
});

const statusInfo = computed(() => {
    const s = props.record?.status;
    return statusOptions.value.find(o => o.id == s || o.value == s)
        ?? { id: 0, value: s, name: s ?? 'Unknown', color: 'gray', bgClass: 'bg-gray-100 dark:bg-gray-700' };
});

const paymentInfo = computed(() => {
    const s = props.record?.payment_status;
    return paymentOptions.value.find(o => o.id == s || o.value == s)
        ?? { id: 0, value: s, name: s ?? '—', color: 'gray', bgClass: 'bg-gray-100 dark:bg-gray-700' };
});

const STATUS_TEXT = {
    gray:   'text-gray-700 dark:text-gray-300',
    blue:   'text-blue-700 dark:text-blue-300',
    yellow: 'text-yellow-700 dark:text-yellow-300',
    green:  'text-green-700 dark:text-green-300',
    red:    'text-red-700 dark:text-red-300',
    orange: 'text-orange-700 dark:text-orange-300',
    purple: 'text-purple-700 dark:text-purple-300',
    slate:  'text-slate-700 dark:text-slate-300',
};

const statusTextClass = computed(() => STATUS_TEXT[statusInfo.value?.color] ?? 'text-gray-700 dark:text-gray-300');
const paymentTextClass = computed(() => STATUS_TEXT[paymentInfo.value?.color] ?? 'text-gray-700 dark:text-gray-300');

const isSigned = computed(() => props.record?.status === 'signed' || !!props.record?.signed_at);
const isDraft = computed(() => props.record?.status === 'draft');
const isSent = computed(() => props.record?.status === 'sent');

const contractLabel = computed(() =>
    props.record?.display_name || `Contract #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contracts', href: route('contracts.index') },
    { label: contractLabel.value },
]);

const hasAnyBilling = (src) => !!(
    src?.billing_address_line1
    || src?.billing_address_line2
    || src?.billing_city
    || src?.billing_state
    || src?.billing_postal
    || src?.billing_country
);

/** Prefer deal snapshot when contract is linked to a transaction (same as contract preview). */
const billingAddressSource = computed(() => {
    const t = props.record.transaction;
    if (props.record.transaction_id && t && hasAnyBilling(t)) {
        return t;
    }
    return props.record;
});

const billingFromTransaction = computed(() => {
    const t = props.record.transaction;
    return !!(props.record.transaction_id && t && hasAnyBilling(t));
});

const hasAddress = computed(() => hasAnyBilling(billingAddressSource.value));

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch { return '—'; }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};

const formatCurrency = (value, currency) => {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return String(value);
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || props.record?.currency || 'USD' }).format(n);
    } catch {
        return `${currency || 'USD'} ${n.toFixed(2)}`;
    }
};

const deleteContract = () => {
    if (confirm('Delete this contract? This cannot be undone.')) {
        router.delete(route('contracts.destroy', props.record.id));
    }
};

// ─── Line items sourced from the linked transaction ────────────────────────
const transactionItems = computed(() => props.record?.transaction?.items ?? []);

const taxRate = computed(() => Number(props.record?.transaction?.tax_rate) || 0);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const lineBaseTotal = (item) =>
    Number(item.unit_price || 0) * Number(item.quantity || 1);

const taxOnItemBase = (item) => {
    if (taxRate.value <= 0) return 0;
    const taxable = item.taxable !== false && item.taxable !== 0 && item.taxable !== '0';
    if (!taxable) return 0;
    return roundMoney(lineBaseTotal(item) * (taxRate.value / 100));
};

const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);

const taxOnAddon = (addon) => {
    if (taxRate.value <= 0) return 0;
    const taxable = addon.taxable !== false && addon.taxable !== 0 && addon.taxable !== '0';
    if (!taxable) return 0;
    return roundMoney(addonPreTaxTotal(addon) * (taxRate.value / 100));
};

const lineItemsSubtotal = computed(() => {
    let total = 0;
    for (const item of transactionItems.value) {
        total += lineBaseTotal(item) + taxOnItemBase(item);
        for (const addon of (item.addons ?? [])) {
            total += addonPreTaxTotal(addon) + taxOnAddon(addon);
        }
    }
    return roundMoney(total);
});

const showPreview = ref(false);
const openPreview = () => { showPreview.value = true; };
const closePreview = () => { showPreview.value = false; };
</script>

<template>
    <Head :title="`${contractLabel} — Contract`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ contractLabel }}
                        </h2>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                            :class="[statusInfo.bgClass, statusTextClass]"
                        >
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('contracts.index')">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                <span class="material-icons text-base">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link v-if="!isSigned" :href="route('contracts.edit', record.id)">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                <span class="material-icons text-base">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button v-if="!isSigned" type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700"
                            @click="openPreview">
                            <span class="material-icons text-base">visibility</span>
                            Preview
                        </button>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click="deleteContract">
                            <span class="material-icons text-base">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            <!-- ─── MAIN COLUMN ──────────────────────────────────────── -->
            <div class="space-y-6 lg:col-span-8">

                <!-- Main detail card -->
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">

                    <!-- Blue header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <h1 class="text-2xl font-bold text-white">CONTRACT</h1>
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                        :class="[statusInfo.bgClass, statusTextClass]"
                                    >
                                        {{ statusInfo.name }}
                                    </span>
                                </div>
                                <p class="text-blue-100 text-sm">Contract details &amp; terms</p>
                            </div>
                            <div class="text-right">
                                <div class="text-blue-200 text-xs font-medium">Reference</div>
                                <div class="text-white text-lg font-mono">{{ contractLabel }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- Signed banner -->
                        <div v-if="isSigned" class="flex items-center gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3">
                            <span class="material-icons text-green-600 dark:text-green-400">verified</span>
                            <div>
                                <p class="text-sm font-semibold text-green-800 dark:text-green-200">Contract Signed</p>
                                <p v-if="record.signed_at" class="text-sm text-green-600 dark:text-green-400">{{ formatDateTime(record.signed_at) }}</p>
                                <p v-if="record.signed_name" class="text-sm text-green-600 dark:text-green-400">by {{ record.signed_name }}</p>
                            </div>
                        </div>

                        <!-- Sent banner -->
                        <div v-else-if="isSent" class="flex items-center gap-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3">
                            <span class="material-icons text-blue-600 dark:text-blue-400">send</span>
                            <div>
                                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Awaiting Signature</p>
                                <p class="text-sm text-blue-600 dark:text-blue-400">This contract has been sent and is pending customer signature.</p>
                            </div>
                        </div>

                        <!-- Customer & Relations -->
                        <div class="border-gray-200 dark:border-gray-700 pt-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer &amp; Relations
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</p>
                                    <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.customer?.display_name || '—' }}
                                    </Link>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">—</p>
                                </div>
                                <div v-if="record.transaction_id">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Transaction</p>
                                    <Link :href="route('transactions.show', record.transaction_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.transaction?.display_name || `#${record.transaction_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.estimate_id">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Originating Estimate</p>
                                    <Link :href="route('estimates.show', record.estimate_id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.estimate?.display_name || `#${record.estimate_id}` }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Contract Details -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Contract Details
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Amount</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(record.total_amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Currency</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Status</p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="[paymentInfo.bgClass, paymentTextClass]">
                                        {{ paymentInfo.name }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Required</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.signature_required ? 'Yes' : 'No' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div v-if="record.contract_terms || record.payment_terms || record.delivery_terms || record.payment_term" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Terms
                            </h3>
                            <div v-if="record.payment_term != null && record.payment_term !== ''" class="mb-6">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment term</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ paymentTermInfo.name }}</p>
                                <p v-if="paymentTermInfo.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ paymentTermInfo.description }}
                                </p>
                            </div>
                            <div v-if="record.contract_terms" class="mb-6">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Contract terms</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.contract_terms }}</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div v-if="record.payment_terms">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Terms</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.payment_terms }}</p>
                                </div>
                                <div v-if="record.delivery_terms">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Delivery Terms</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.delivery_terms }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Address (from linked deal when available) -->
                        <div v-if="hasAddress" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Billing Address
                            </h3>
                            <p v-if="billingFromTransaction" class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                Sourced from linked deal
                            </p>
                            <div class="text-sm text-gray-900 dark:text-gray-100 space-y-0.5">
                                <div v-if="billingAddressSource.billing_address_line1">{{ billingAddressSource.billing_address_line1 }}</div>
                                <div v-if="billingAddressSource.billing_address_line2">{{ billingAddressSource.billing_address_line2 }}</div>
                                <div v-if="billingAddressSource.billing_city || billingAddressSource.billing_state || billingAddressSource.billing_postal">
                                    {{ [billingAddressSource.billing_city, billingAddressSource.billing_state, billingAddressSource.billing_postal].filter(Boolean).join(', ') }}
                                </div>
                                <div v-if="billingAddressSource.billing_country">{{ billingAddressSource.billing_country }}</div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="record.notes" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Notes
                            </h3>
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</p>
                        </div>

                        <!-- Line Items (sourced from linked transaction) -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Line Items
                                <span v-if="record.transaction_id" class="ml-2 text-xs font-normal text-gray-400 dark:text-gray-500 normal-case tracking-normal">sourced from transaction</span>
                            </h3>

                            <div v-if="transactionItems.length > 0" class="overflow-x-auto -mx-6 sm:mx-0">
                                <div class="inline-block min-w-full align-middle">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Item</th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs w-20">Taxable</th>
                                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs w-16">Qty</th>
                                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs w-28">Unit Price</th>
                                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs w-24">Tax</th>
                                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs w-28">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <template v-for="row in transactionItems" :key="row.id">
                                                <!-- Main line item -->
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900 dark:text-white">{{ row.name }}</div>
                                                        <div v-if="row.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ row.description }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                                        {{ row.taxable !== false && row.taxable !== 0 ? 'Yes' : 'No' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ row.quantity }}</td>
                                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.unit_price) }}</td>
                                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ formatCurrency(taxOnItemBase(row)) }}</td>
                                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineBaseTotal(row) + taxOnItemBase(row)) }}</td>
                                                </tr>
                                                <!-- Add-on rows -->
                                                <tr v-for="addon in (row.addons ?? [])" :key="'addon-' + addon.id"
                                                    class="bg-blue-50/30 dark:bg-blue-900/10">
                                                    <td class="px-4 py-2 pl-10 text-xs text-gray-600 dark:text-gray-400 italic">
                                                        ↳ {{ addon.name || 'Add-on' }}
                                                        <span v-if="addon.notes" class="block text-gray-400 not-italic">{{ addon.notes }}</span>
                                                    </td>
                                                    <td class="px-4 py-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                                        {{ addon.taxable !== false && addon.taxable !== 0 ? 'Yes' : 'No' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                                    <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                                    <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(taxOnAddon(addon)) }}</td>
                                                    <td class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <!-- Totals footer -->
                                        <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-300 dark:border-gray-600">
                                            <tr v-if="taxRate > 0">
                                                <td colspan="5" class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">
                                                    Tax rate: {{ taxRate }}%
                                                </td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                                    Total
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm font-bold text-blue-600 dark:text-blue-400">
                                                    {{ formatCurrency(lineItemsSubtotal) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div v-else class="text-center py-10 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                                <span class="material-icons text-4xl text-gray-400 dark:text-gray-600 mb-2 block">receipt_long</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ record.transaction_id ? 'No line items on the linked transaction' : 'No transaction linked to this contract' }}
                                </p>
                            </div>
                        </div>

                        <!-- Signature Details -->
                        <div v-if="isSigned" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Signature Details
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div v-if="record.signed_name">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed By</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.signed_name }}</p>
                                </div>
                                <div v-if="record.signed_email">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ record.signed_email }}</p>
                                </div>
                                <div v-if="record.signed_at">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed At</p>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</p>
                                </div>
                                <div v-if="record.signed_ip">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">IP Address</p>
                                    <p class="text-sm font-mono text-gray-900 dark:text-white">{{ record.signed_ip }}</p>
                                </div>
                                <div v-if="record.signature_hash">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Hash</p>
                                    <p class="text-xs font-mono text-gray-500 dark:text-gray-400 break-all">{{ record.signature_hash }}</p>
                                </div>
                                <div v-if="record.docusign_envelope_id">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">DocuSign Envelope</p>
                                    <p class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ record.docusign_envelope_id }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ─── SIDEBAR ───────────────────────────────────────────── -->
            <div class="space-y-4 lg:col-span-4">

                <!-- Status & Dates -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Status</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="[statusInfo.bgClass, statusTextClass]">
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-gray-900 dark:text-white text-right">{{ formatDateTime(record.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Updated</span>
                            <span class="text-gray-900 dark:text-white text-right">{{ formatDateTime(record.updated_at) }}</span>
                        </div>
                        <div v-if="record.signed_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Signed</span>
                            <span class="font-medium text-green-600 dark:text-green-400 text-right">{{ formatDateTime(record.signed_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contract Summary -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Contract Summary</span>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Payment Status</span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="[paymentInfo.bgClass, paymentTextClass]">
                                {{ paymentInfo.name }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Signature Required</span>
                            <span class="text-gray-900 dark:text-white">{{ record.signature_required ? 'Yes' : 'No' }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold border-t border-gray-200 dark:border-gray-600 pt-3">
                            <span class="text-gray-900 dark:text-white">Total</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ formatCurrency(record.total_amount) }}</span>
                        </div>
                        <div class="pt-1 text-sm text-gray-400 dark:text-gray-500 text-right">
                            {{ record.currency || 'USD' }}
                        </div>
                    </div>
                </div>

                <!-- Related Records -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Related Records</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <Link v-if="record.transaction_id" :href="route('transactions.show', record.transaction_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">handshake</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Transaction</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ record.transaction?.display_name || `#${record.transaction_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-base transition-colors">chevron_right</span>
                        </Link>
                        <Link v-if="record.estimate_id" :href="route('estimates.show', record.estimate_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">description</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estimate</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ record.estimate?.display_name || `#${record.estimate_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-base transition-colors">chevron_right</span>
                        </Link>
                        <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">person</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Customer</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ record.customer?.display_name || `#${record.customer_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-base transition-colors">chevron_right</span>
                        </Link>
                        <div v-if="record.document_url">
                            <a :href="record.document_url" target="_blank"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                    <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">picture_as_pdf</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Document</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">View PDF</p>
                                </div>
                                <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-base transition-colors">open_in_new</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Teleport to="body">
            <div v-if="showPreview" class="contract-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <ContractPreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>