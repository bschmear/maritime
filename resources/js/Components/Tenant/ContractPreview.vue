<script setup>
import { computed, ref } from 'vue';
import {
    lineItemPreTaxTotal,
    lineUnitDisplay,
    lineUnitId,
    lineVariantDisplay,
    lineVariantId,
    resolveLineItemsForContract,
    taxRateForResolvedLines,
} from '@/Utils/lineItemsFromEstimate';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const printing = ref(false);

const statusEnumKey = 'App\\Enums\\Contract\\ContractStatus';
const paymentEnumKey = 'App\\Enums\\Contract\\ContractPaymentStatus';
const paymentTermEnumKey = 'App\\Enums\\Payments\\Terms';

const formatCurrency = (value) => {
    if (value == null) return '$0.00';
    return `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch { return '—'; }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('en-US', {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true,
        }).format(date);
    } catch { return '—'; }
};

const statusOption = computed(() => {
    const opts = props.enumOptions[statusEnumKey] || [];
    return opts.find(o => o.value === props.record.status || o.id === props.record.status);
});

const paymentOption = computed(() => {
    const opts = props.enumOptions[paymentEnumKey] || [];
    return opts.find(o => o.value === props.record.payment_status || o.id === props.record.payment_status);
});

const paymentTermInfo = computed(() => {
    const raw = props.record?.payment_term;
    if (raw == null || raw === '') return { name: '—', description: '' };
    const opts = props.enumOptions[paymentTermEnumKey] || [];
    const opt = opts.find(o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw));
    if (opt) return { name: opt.name, description: opt.description || '' };
    return { name: String(raw).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()), description: '' };
});

const accountDisplayName = computed(() =>
    props.account?.settings?.business_name || props.account?.business_name || 'Company'
);

const hasAnyBilling = (src) => !!(
    src?.billing_address_line1 || src?.billing_address_line2 ||
    src?.billing_city || src?.billing_state ||
    src?.billing_postal || src?.billing_country
);

const billingAddressSource = computed(() => {
    const t = props.record.transaction;
    if (props.record.transaction_id && t && hasAnyBilling(t)) return t;
    return props.record;
});

const billingFromTransaction = computed(() => {
    const t = props.record.transaction;
    return !!(props.record.transaction_id && t && hasAnyBilling(t));
});

const customerDisplayName = computed(() =>
    props.record.customer?.display_name || props.record.transaction?.customer_name || '—'
);
const customerEmail = computed(() =>
    props.record.customer?.email || props.record.transaction?.customer_email || null
);
const customerPhone = computed(() =>
    props.record.customer?.phone || props.record.transaction?.customer_phone || null
);

const lineItemsResolution = computed(() => resolveLineItemsForContract(props.record));
const transactionItems = computed(() => lineItemsResolution.value.items);
const lineItemsFromEstimate = computed(() => lineItemsResolution.value.source === 'estimate');

/** Location on linked deal (DB snake_case + optional camelCase from APIs). */
const transactionLocationPreview = computed(() => {
    const loc = props.record?.transaction?.location;
    if (!loc) return null;
    const line1 = loc.address_line_1 ?? loc.address_line1 ?? '';
    const line2 = loc.address_line_2 ?? loc.address_line2 ?? '';
    const city = loc.city ?? '';
    const state = loc.state ?? '';
    const postal = loc.postal_code ?? '';
    const phone = loc.phone ?? '';
    const email = loc.email ?? '';
    if (!line1 && !city && !phone && !email) return null;
    return { line1, line2, city, state, postal, phone, email };
});
const taxRate = computed(() =>
    taxRateForResolvedLines(
        props.record,
        lineItemsResolution.value.source,
        props.record?.transaction?.tax_rate,
    ),
);
const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const lineBaseTotal = (item) => lineItemPreTaxTotal(item);
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

const isSigned = computed(() => !!props.record.signed_at);

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};
</script>

<template>
    <div class="contract-preview-shell min-h-screen bg-gray-100 dark:bg-gray-900">

        <!-- Toolbar (screen only; not sticky — scrolls away like normal content) -->
        <div class="relative z-10 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Contract preview</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Read-only summary for sharing or printing</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-sm">close</span>
                            Close
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 rounded-lg transition-colors"
                            :disabled="printing"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons text-sm animate-spin">refresh</span>
                            <span v-else class="material-icons text-sm">print</span>
                            {{ printing ? 'Preparing…' : 'Print' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document -->
        <div id="contract-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div id="contract-print-document" class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

                <!-- Header (document title — flows once; not repeated on later pages) -->
                <div class="contract-print-doc-header border-b-4 border-gray-900 dark:border-gray-100 px-8 py-6 print:border-b-2 print:break-inside-avoid">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-6">
                            <div v-if="account.logo_url" class="flex-shrink-0">
                                <img :src="account.logo_url" alt="Logo" class="h-20 w-auto object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center print:hidden">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ accountDisplayName }}</h1>
                                <p
                                    v-if="record.transaction?.subsidiary?.display_name"
                                    class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    {{ record.transaction.subsidiary.display_name }}
                                </p>
                                <div
                                    v-if="transactionLocationPreview"
                                    class="mt-2 text-sm text-gray-600 dark:text-gray-400 space-y-1"
                                >
                                    <p v-if="transactionLocationPreview.line1">
                                        {{ transactionLocationPreview.line1
                                        }}<span v-if="transactionLocationPreview.line2">, {{ transactionLocationPreview.line2 }}</span>
                                    </p>
                                    <p v-if="transactionLocationPreview.city">
                                        {{ transactionLocationPreview.city
                                        }}<span v-if="transactionLocationPreview.state">, {{ transactionLocationPreview.state }}</span>
                                        <template v-if="transactionLocationPreview.postal"> {{ transactionLocationPreview.postal }}</template>
                                    </p>
                                    <p v-if="transactionLocationPreview.phone" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ transactionLocationPreview.phone }}
                                    </p>
                                    <p v-if="transactionLocationPreview.email" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">email</span>
                                        {{ transactionLocationPreview.email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Contract</div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white font-mono">
                                {{ record.display_name || record.contract_number || `#${record.id}` }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ formatDate(record.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Status badges -->
                <div class="px-8 py-4 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                    <span v-if="statusOption" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statusOption.bgClass || 'bg-gray-200 dark:bg-gray-700'">
                        {{ statusOption.name }}
                    </span>
                    <span v-if="paymentOption" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="paymentOption.bgClass || 'bg-gray-200 dark:bg-gray-700'">
                        Payment: {{ paymentOption.name }}
                    </span>
                    <span v-if="record.signature_required" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                        Signature required
                    </span>
                </div>

                <!-- Customer + Amount -->
                <div class="px-8 py-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-900/40 print:bg-white print:border-b print:border-gray-200">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer</h2>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 space-y-1">
                            <div class="font-semibold text-lg text-gray-900 dark:text-white">{{ customerDisplayName }}</div>
                            <div v-if="customerEmail" class="text-sm text-gray-600 dark:text-gray-400">{{ customerEmail }}</div>
                            <div v-if="customerPhone" class="text-sm text-gray-600 dark:text-gray-400">{{ customerPhone }}</div>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Amount</h2>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(record.total_amount) }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ record.currency || 'USD' }}</div>
                            <div v-if="record.estimate" class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Estimate: {{ record.estimate.display_name || `EST-${record.estimate.sequence}` }}
                            </div>
                            <div v-if="record.transaction_id" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Deal: {{ record.transaction?.title || record.transaction?.customer_name || `#${record.transaction_id}` }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div class="px-8 py-6 border-t border-gray-200 dark:border-gray-700 print:break-inside-avoid">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Line items
                        <span v-if="lineItemsFromEstimate" class="ml-1 font-normal normal-case text-gray-400">(from estimate)</span>
                        <span v-else-if="record.transaction_id" class="ml-1 font-normal normal-case text-gray-400">(from deal)</span>
                    </h2>
                    <div v-if="transactionItems.length > 0" class="overflow-x-auto print:overflow-visible">
                        <table class="min-w-full text-sm border border-gray-200 dark:border-gray-700 print:border-gray-300">
                            <thead class="bg-gray-100 dark:bg-gray-900/50 print:bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400 text-sm uppercase">Item</th>
                                    <th class="px-3 py-2 text-left text-sm uppercase text-gray-600 dark:text-gray-400 min-w-[6rem]">Variant</th>
                                    <th class="px-3 py-2 text-left text-sm uppercase text-gray-600 dark:text-gray-400 min-w-[6rem]">Unit</th>
                                    <th class="px-3 py-2 text-center text-sm uppercase text-gray-600 dark:text-gray-400 w-16">Tax</th>
                                    <th class="px-3 py-2 text-right text-sm uppercase text-gray-600 dark:text-gray-400 w-12">Qty</th>
                                    <th class="px-3 py-2 text-right text-sm uppercase text-gray-600 dark:text-gray-400 w-24">Price</th>
                                    <th class="px-3 py-2 text-right text-sm uppercase text-gray-600 dark:text-gray-400 w-20">Tax $</th>
                                    <th class="px-3 py-2 text-right text-sm uppercase text-gray-600 dark:text-gray-400 w-24">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template v-for="row in transactionItems" :key="row.id">
                                    <tr>
                                        <td class="px-3 py-2 align-top">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ row.name }}</div>
                                            <div v-if="row.description" class="text-sm text-gray-500 mt-0.5">{{ row.description }}</div>
                                        </td>
                                        <td class="px-3 py-2 align-top text-sm text-gray-600 dark:text-gray-300">
                                            <span v-if="lineVariantId(row)" class="font-medium text-gray-800 dark:text-gray-200">{{ lineVariantDisplay(row) }}</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-3 py-2 align-top text-sm text-gray-600 dark:text-gray-300">
                                            <span v-if="lineUnitId(row)" class="font-medium text-gray-800 dark:text-gray-200">{{ lineUnitDisplay(row) }}</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-3 py-2 text-center text-sm text-gray-600">{{ row.taxable !== false && row.taxable !== 0 ? 'Y' : 'N' }}</td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ +row.quantity }}</td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ formatCurrency(row.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(taxOnItemBase(row)) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineBaseTotal(row) + taxOnItemBase(row)) }}</td>
                                    </tr>
                                    <tr v-for="addon in (row.addons ?? [])" :key="'a-' + addon.id" class="bg-gray-50/80 dark:bg-gray-900/20">
                                        <td class="px-3 py-1.5 pl-6 text-sm italic text-gray-600 dark:text-gray-400">↳ {{ addon.name || 'Add-on' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-400">—</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-400">—</td>
                                        <td class="px-3 py-1.5 text-center text-sm text-gray-600">{{ addon.taxable !== false && addon.taxable !== 0 ? 'Y' : 'N' }}</td>
                                        <td class="px-3 py-1.5 text-right text-sm text-gray-800 dark:text-gray-300">{{ +addon.quantity }}</td>
                                        <td class="px-3 py-1.5 text-right text-sm text-gray-800 dark:text-gray-300">{{ formatCurrency(addon.price) }}</td>
                                        <td class="px-3 py-1.5 text-right text-sm text-gray-600 dark:text-gray-500">{{ formatCurrency(taxOnAddon(addon)) }}</td>
                                        <td class="px-3 py-1.5 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(addonPreTaxTotal(addon) + taxOnAddon(addon)) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30">
                                <tr v-if="taxRate > 0">
                                    <td colspan="7" class="px-3 py-1 text-right text-sm text-gray-500">Tax rate: {{ taxRate }}%</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">Subtotal (lines)</td>
                                    <td class="px-3 py-2 text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ record.transaction_id ? 'No line items on the linked deal.' : 'No deal linked — no line items to show.' }}
                    </p>
                </div>

                <!-- Billing address -->
                <div class="px-8 py-6 border-t border-gray-200 dark:border-gray-700 print:break-inside-avoid">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Billing address</h2>
                    <p v-if="billingFromTransaction" class="text-sm text-gray-500 dark:text-gray-400 mb-2">Sourced from linked deal</p>
                    <div class="text-sm text-gray-800 dark:text-gray-200 space-y-1">
                        <div v-if="billingAddressSource.billing_address_line1">{{ billingAddressSource.billing_address_line1 }}</div>
                        <div v-if="billingAddressSource.billing_address_line2">{{ billingAddressSource.billing_address_line2 }}</div>
                        <div v-if="billingAddressSource.billing_city || billingAddressSource.billing_state || billingAddressSource.billing_postal">
                            {{ [billingAddressSource.billing_city, billingAddressSource.billing_state, billingAddressSource.billing_postal].filter(Boolean).join(', ') }}
                        </div>
                        <div v-if="billingAddressSource.billing_country">{{ billingAddressSource.billing_country }}</div>
                        <div v-if="!billingAddressSource.billing_address_line1 && !billingAddressSource.billing_city" class="text-gray-500">—</div>
                    </div>
                </div>

                <!-- Terms & notes -->
                <div class="px-8 py-6 border-t border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Terms &amp; notes</h2>
                    <div class="space-y-4 text-sm text-gray-800 dark:text-gray-200">
                        <div v-if="record.payment_term != null && record.payment_term !== ''">
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Payment term</div>
                            <p class="font-semibold">{{ paymentTermInfo.name }}</p>
                            <p v-if="paymentTermInfo.description" class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ paymentTermInfo.description }}</p>
                        </div>
                        <div v-if="record.contract_terms">
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Contract terms</div>
                            <p class="whitespace-pre-line">{{ record.contract_terms }}</p>
                        </div>
                        <div v-if="record.payment_terms">
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Payment terms</div>
                            <p class="whitespace-pre-line">{{ record.payment_terms }}</p>
                        </div>
                        <div v-if="record.delivery_terms">
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Delivery terms</div>
                            <p class="whitespace-pre-line">{{ record.delivery_terms }}</p>
                        </div>
                        <div v-if="record.notes">
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Notes</div>
                            <p class="whitespace-pre-line">{{ record.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Signature section ───────────────────────────────────── -->
                <div class="px-8 py-6 border-t-2 border-gray-300 dark:border-gray-600 print:break-inside-avoid">

                    <!-- SIGNED state -->
                    <div v-if="isSigned">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Signature</h2>
                        <div class="flex flex-col sm:flex-row gap-6">
                            <!-- Signature image if available -->
                            <div v-if="record.signature_file" class="flex-shrink-0">
                                <p class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Signature</p>
                                <div class="border border-gray-300 dark:border-gray-600 rounded p-2 bg-white inline-block">
                                    <img :src="record.signature_file" alt="Signature" class="h-16 w-auto object-contain" />
                                </div>
                            </div>
                            <!-- Signature metadata -->
                            <div class="space-y-2 text-sm">
                                <div v-if="record.signed_name" class="flex gap-3">
                                    <span class="text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">Signed by</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ record.signed_name }}</span>
                                </div>
                                <div v-if="record.signed_email" class="flex gap-3">
                                    <span class="text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">Email</span>
                                    <span class="text-gray-800 dark:text-gray-200">{{ record.signed_email }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">Date</span>
                                    <span class="text-gray-800 dark:text-gray-200">{{ formatDateTime(record.signed_at) }}</span>
                                </div>
                                <div v-if="record.signed_ip" class="flex gap-3">
                                    <span class="text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">IP</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-400 text-xs">{{ record.signed_ip }}</span>
                                </div>
                                <div v-if="record.signature_hash" class="flex gap-3">
                                    <span class="text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">Hash</span>
                                    <span class="font-mono text-gray-400 dark:text-gray-500 text-xs break-all">{{ record.signature_hash }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Verified stamp for print -->
                        <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200 text-sm font-medium">
                            <span class="material-icons text-base print:hidden">verified</span>
                            ✓ Electronically signed
                        </div>
                    </div>

                    <!-- UNSIGNED state — print-friendly signature block -->
                    <div v-else>
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-6">Signature</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 print:hidden">
                            This contract has not yet been signed.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                            <!-- Customer signature -->
                            <div class="space-y-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer / Authorized Representative</p>
                                <!-- Signature line -->
                                <div>
                                    <div class="border-b-2 border-gray-400 dark:border-gray-500 h-12 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Signature</p>
                                </div>
                                <!-- Name line -->
                                <div>
                                    <div class="border-b border-gray-300 dark:border-gray-600 h-8 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Print Name</p>
                                </div>
                                <!-- Date line -->
                                <div>
                                    <div class="border-b border-gray-300 dark:border-gray-600 h-8 w-48"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Date</p>
                                </div>
                                <!-- Pre-fill customer name if known -->
                                <p v-if="customerDisplayName !== '—'" class="text-xs text-gray-400 -mt-2">
                                    {{ customerDisplayName }}
                                </p>
                            </div>

                            <!-- Company signature -->
                            <div class="space-y-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ accountDisplayName }}</p>
                                <div>
                                    <div class="border-b-2 border-gray-400 dark:border-gray-500 h-12 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Authorized Signature</p>
                                </div>
                                <div>
                                    <div class="border-b border-gray-300 dark:border-gray-600 h-8 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Print Name &amp; Title</p>
                                </div>
                                <div>
                                    <div class="border-b border-gray-300 dark:border-gray-600 h-8 w-48"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Date</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-8 text-xs text-gray-400 dark:text-gray-500">
                            By signing above, all parties agree to the terms and conditions set forth in this contract dated {{ formatDate(record.created_at) }}.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ accountDisplayName }} · {{ record.display_name || record.contract_number || `Contract #${record.id}` }} · Generated {{ formatDate(new Date().toISOString()) }}
                    </p>
                </div>

            </div>
        </div>
    </div>


</template>
