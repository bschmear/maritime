<script setup>
import { computed, ref } from 'vue';
import ResolvedLineItemsEstimateStyle from '@/Components/Tenant/ResolvedLineItemsEstimateStyle.vue';
import {
    lineAssetSelectedOptions,
    lineItemPreTaxTotal,
    resolveLineItemsForContract,
    taxRateForResolvedLines,
} from '@/Utils/lineItemsFromEstimate';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    /** Same pattern as ServiceTicketPreview (falls back to account.logo_url). */
    logoUrl: { type: String, default: null },
});

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

const emit = defineEmits(['close', 'request-send']);

const printing = ref(false);

const statusEnumKey = 'App\\Enums\\Contract\\ContractStatus';
const paymentEnumKey = 'App\\Enums\\Contract\\ContractPaymentStatus';
const paymentTermEnumKey = 'App\\Enums\\Payments\\Terms';

const formatCurrency = (value) => {
    if (value == null || value === '') return '$0.00';
    const n = parseFloat(value);
    if (Number.isNaN(n)) return String(value);
    const cur = props.record?.currency || 'USD';
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
    } catch {
        return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
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

const selectedOptionUnitPrice = (opt) => Number(opt?.price ?? 0);
const optionRowTaxable = (opt) => opt.taxable !== false && opt.taxable !== 0 && opt.taxable !== '0';
const taxOnAssetOption = (opt) =>
    taxOnAddon({ price: selectedOptionUnitPrice(opt), quantity: 1, taxable: optionRowTaxable(opt) });

const lineItemsSubtotal = computed(() => {
    let total = 0;
    for (const item of transactionItems.value) {
        total += lineBaseTotal(item) + taxOnItemBase(item);
        for (const opt of lineAssetSelectedOptions(item)) {
            total += selectedOptionUnitPrice(opt) + taxOnAssetOption(opt);
        }
        for (const addon of (item.addons ?? [])) {
            total += addonPreTaxTotal(addon) + taxOnAddon(addon);
        }
    }
    return roundMoney(total);
});

const isSigned = computed(() => !!props.record.signed_at);

const footerPhone = computed(
    () => transactionLocationPreview.value?.phone || props.account?.phone || null,
);

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
        <!-- Action bar — dark chrome like ServiceTicketPreview; document below stays light for “paper” -->
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-2 lg:py-4">
                <div class="flex items-center justify-between gap-2 lg:gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white lg:text-lg truncate">
                            Customer Preview
                        </h2>
                        <p class="hidden text-sm text-gray-500 dark:text-gray-400 lg:block mt-0.5">
                            This is how the contract will appear to the customer
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-3">
                        <button
                            type="button"
                            aria-label="Close preview"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 lg:px-4"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-[18px]">close</span>
                            <span class="hidden lg:inline">Close</span>
                        </button>

                        <button
                            type="button"
                            aria-label="Send contract to customer"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="emit('request-send')"
                        >
                            <span class="material-icons text-[18px]">send</span>
                            <span class="hidden lg:inline">Send Contract</span>
                        </button>

                        <button
                            type="button"
                            :aria-label="printing ? 'Preparing print' : 'Print preview'"
                            :aria-busy="printing"
                            :disabled="printing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons animate-spin text-[18px]">refresh</span>
                            <span v-else class="material-icons text-[18px]">print</span>
                            <span class="hidden lg:inline">{{ printing ? 'Preparing…' : 'Print' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Printable document -->
        <div id="contract-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div id="contract-print-document" class="bg-white shadow-lg print:shadow-none">

                <!-- Header (document title — flows once; not repeated on later pages) -->
                <div class="contract-print-doc-header border-b-4 border-gray-900 px-8 py-6 print:border-b-2 print:break-inside-avoid">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-6">
                            <div v-if="effectiveLogoUrl" class="flex-shrink-0">
                                <img :src="effectiveLogoUrl" alt="Company Logo" class="h-20 w-auto max-w-[150px] object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center print:hidden">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ accountDisplayName }}</h1>
                                <p
                                    v-if="record.transaction?.subsidiary?.display_name"
                                    class="mt-1 text-sm font-semibold text-gray-700"
                                >
                                    {{ record.transaction.subsidiary.display_name }}
                                </p>
                                <div
                                    v-if="transactionLocationPreview"
                                    class="mt-2 text-sm text-gray-600 space-y-1"
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
                            <div class="text-sm font-medium text-gray-600 uppercase tracking-wide">Contract</div>
                            <div class="text-3xl font-bold text-gray-900 font-mono">
                                {{ record.display_name || record.contract_number || `#${record.id}` }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">{{ formatDate(record.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Status badges -->
                <div class="px-8 py-4 flex flex-wrap gap-2 border-b border-gray-200">
                    <span v-if="statusOption" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statusOption.bgClass || 'bg-gray-200'">
                        {{ statusOption.name }}
                    </span>
                    <span v-if="paymentOption" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="paymentOption.bgClass || 'bg-gray-200'">
                        Payment: {{ paymentOption.name }}
                    </span>
                    <span v-if="record.signature_required" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                        Signature required
                    </span>
                </div>

                <!-- Customer + Amount -->
                <div class="px-8 py-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 print:bg-white print:border-b print:border-gray-200">
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="font-semibold text-lg text-gray-900">{{ customerDisplayName }}</div>
                            <div v-if="customerEmail" class="text-sm text-gray-600">{{ customerEmail }}</div>
                            <div v-if="customerPhone" class="text-sm text-gray-600">{{ customerPhone }}</div>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Amount</h2>
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="text-3xl font-bold text-gray-900">{{ formatCurrency(record.total_amount) }}</div>
                            <div class="text-sm text-gray-600 mt-1">{{ record.currency || 'USD' }}</div>
                            <div v-if="record.estimate" class="text-sm text-gray-500 mt-2">
                                Estimate: {{ record.estimate.display_name || `EST-${record.estimate.sequence}` }}
                            </div>
                            <div v-if="record.transaction_id" class="text-sm text-gray-500 mt-1">
                                Deal: {{ record.transaction?.title || record.transaction?.customer_name || `#${record.transaction_id}` }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div class="px-8 py-6 border-t border-gray-200 print:break-inside-avoid">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Line items
                        <span v-if="lineItemsFromEstimate" class="ml-1 font-normal normal-case text-gray-400">(from estimate)</span>
                        <span v-else-if="record.transaction_id" class="ml-1 font-normal normal-case text-gray-400">(from deal)</span>
                    </h2>
                    <ResolvedLineItemsEstimateStyle
                        v-if="transactionItems.length > 0"
                        :items="transactionItems"
                        variant="paper"
                        embedded
                        :format-money="formatCurrency"
                        :show-summary="true"
                        :summary-tax-rate-percent="taxRate"
                        :summary-grand-total="lineItemsSubtotal"
                    />
                    <p v-else class="text-sm text-gray-500">
                        {{ record.transaction_id ? 'No line items on the linked deal.' : 'No deal linked — no line items to show.' }}
                    </p>
                </div>

                <!-- Billing address -->
                <div class="px-8 py-6 border-t border-gray-200 print:break-inside-avoid">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Billing address</h2>
                    <p v-if="billingFromTransaction" class="text-sm text-gray-500 mb-2">Sourced from linked deal</p>
                    <div class="text-sm text-gray-800 space-y-1">
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
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Terms &amp; notes</h2>
                    <div class="space-y-4 text-sm text-gray-800">
                        <div v-if="record.payment_term != null && record.payment_term !== ''">
                            <div class="font-medium text-gray-900 mb-1">Payment term</div>
                            <p class="font-semibold">{{ paymentTermInfo.name }}</p>
                            <p v-if="paymentTermInfo.description" class="mt-1 text-xs text-gray-600">{{ paymentTermInfo.description }}</p>
                        </div>
                        <div v-if="record.contract_terms">
                            <div class="font-medium text-gray-900 mb-1">Contract terms</div>
                            <p class="whitespace-pre-line">{{ record.contract_terms }}</p>
                        </div>
                        <div v-if="record.payment_terms">
                            <div class="font-medium text-gray-900 mb-1">Payment terms</div>
                            <p class="whitespace-pre-line">{{ record.payment_terms }}</p>
                        </div>
                        <div v-if="record.delivery_terms">
                            <div class="font-medium text-gray-900 mb-1">Delivery terms</div>
                            <p class="whitespace-pre-line">{{ record.delivery_terms }}</p>
                        </div>
                        <div v-if="record.notes">
                            <div class="font-medium text-gray-900 mb-1">Notes</div>
                            <p class="whitespace-pre-line">{{ record.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Signature section ───────────────────────────────────── -->
                <div class="px-8 py-6 border-t-2 border-gray-300 print:break-inside-avoid">

                    <!-- SIGNED state -->
                    <div v-if="isSigned">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Signature</h2>
                        <div class="flex flex-col sm:flex-row gap-6">
                            <!-- Signature image if available -->
                            <div v-if="record.signature_file" class="flex-shrink-0">
                                <p class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Signature</p>
                                <div class="border border-gray-300 rounded p-2 bg-white inline-block">
                                    <img :src="record.signature_file" alt="Signature" class="h-16 w-auto object-contain" />
                                </div>
                            </div>
                            <!-- Signature metadata -->
                            <div class="space-y-2 text-sm">
                                <div v-if="record.signed_name" class="flex gap-3">
                                    <span class="text-gray-500 w-24 flex-shrink-0">Signed by</span>
                                    <span class="font-semibold text-gray-900">{{ record.signed_name }}</span>
                                </div>
                                <div v-if="record.signed_email" class="flex gap-3">
                                    <span class="text-gray-500 w-24 flex-shrink-0">Email</span>
                                    <span class="text-gray-800">{{ record.signed_email }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-gray-500 w-24 flex-shrink-0">Date</span>
                                    <span class="text-gray-800">{{ formatDateTime(record.signed_at) }}</span>
                                </div>
                                <div v-if="record.signed_ip" class="flex gap-3">
                                    <span class="text-gray-500 w-24 flex-shrink-0">IP</span>
                                    <span class="font-mono text-gray-600 text-xs">{{ record.signed_ip }}</span>
                                </div>
                                <div v-if="record.signature_hash" class="flex gap-3">
                                    <span class="text-gray-500 w-24 flex-shrink-0">Hash</span>
                                    <span class="font-mono text-gray-400 text-xs break-all">{{ record.signature_hash }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Verified stamp for print -->
                        <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                            <span class="material-icons text-base print:hidden">verified</span>
                            ✓ Electronically signed
                        </div>
                    </div>

                    <!-- UNSIGNED state — print-friendly signature block -->
                    <div v-else>
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-6">Signature</h2>
                        <p class="text-sm text-gray-500 mb-6 print:hidden">
                            This contract has not yet been signed.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                            <!-- Customer signature -->
                            <div class="space-y-4">
                                <p class="text-sm font-medium text-gray-700">Customer / Authorized Representative</p>
                                <!-- Signature line -->
                                <div>
                                    <div class="border-b-2 border-gray-400 h-12 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Signature</p>
                                </div>
                                <!-- Name line -->
                                <div>
                                    <div class="border-b border-gray-300 h-8 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Print Name</p>
                                </div>
                                <!-- Date line -->
                                <div>
                                    <div class="border-b border-gray-300 h-8 w-48"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Date</p>
                                </div>
                                <!-- Pre-fill customer name if known -->
                                <p v-if="customerDisplayName !== '—'" class="text-xs text-gray-400 -mt-2">
                                    {{ customerDisplayName }}
                                </p>
                            </div>

                            <!-- Company signature -->
                            <div class="space-y-4">
                                <p class="text-sm font-medium text-gray-700">{{ accountDisplayName }}</p>
                                <div>
                                    <div class="border-b-2 border-gray-400 h-12 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Authorized Signature</p>
                                </div>
                                <div>
                                    <div class="border-b border-gray-300 h-8 w-full"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Print Name &amp; Title</p>
                                </div>
                                <div>
                                    <div class="border-b border-gray-300 h-8 w-48"></div>
                                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Date</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-8 text-xs text-gray-400">
                            By signing above, all parties agree to the terms and conditions set forth in this contract dated {{ formatDate(record.created_at) }}.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-gray-900 text-white text-center text-xs">
                    <p>Thank you for your business!</p>
                    <p v-if="footerPhone" class="mt-1">
                        Questions? Call us at {{ footerPhone }}
                    </p>
                </div>

            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .sticky {
        display: none !important;
    }

    .bg-white {
        background-color: white !important;
    }

    .shadow-lg {
        box-shadow: none !important;
    }

    @page {
        margin: 0.5in;
    }
}
</style>
