<script setup>
import { computed } from 'vue';

const ASSET_TYPE = 'App\\Domain\\Asset\\Models\\Asset';
const INVENTORY_TYPE = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
    /** Line items + totals only (service-ticket-style chrome lives on the parent page). */
    bodyOnly: { type: Boolean, default: false },
});

const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';

const lineItems = computed(() => {
    const raw = props.record?.items ?? props.record?.line_items ?? [];
    return Array.isArray(raw) ? raw : [];
});

const accountDisplayName = computed(() =>
    props.account?.settings?.business_name ?? props.account?.business_name ?? 'Company'
);

const transaction = computed(() => props.record?.transaction ?? null);

const companyName = computed(
    () => transaction.value?.subsidiary?.display_name ?? accountDisplayName.value,
);

const companyLocation = computed(() => transaction.value?.location ?? null);

const companyAddressLines = computed(() => {
    const loc = companyLocation.value;
    if (!loc) return [];
    const lines = [];
    if (loc.display_name) lines.push(loc.display_name);
    const a1 = loc.address_line_1 ?? loc.address_line1;
    if (a1) lines.push(a1);
    const a2 = loc.address_line_2 ?? loc.address_line2;
    if (a2) lines.push(a2);
    const cityLine = [loc.city, loc.state, loc.postal_code].filter(Boolean).join(', ');
    if (cityLine) lines.push(cityLine);
    if (loc.country) lines.push(loc.country);
    return lines;
});

const companyPhone = computed(() => companyLocation.value?.phone ?? null);
const companyEmail = computed(() => companyLocation.value?.email ?? null);

const statusLabel = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    return opts.find(o => o.id == s || o.value === s)?.name ?? s ?? 'Draft';
});

const statusBadgeClass = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    const opt = opts.find(o => o.id == s || o.value === s);
    if (opt?.bgClass) return opt.bgClass;
    const map = {
        draft:   'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        sent:    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        viewed:  'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        paid:    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        void:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    const v = typeof s === 'string' ? s : opts.find(o => o.id == s)?.value;
    return map[v] ?? map.draft;
});

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opts = props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? [];
    const opt = opts.find(o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw));
    return opt?.name ?? raw ?? null;
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

const itemPrimaryLabel  = (item) => item.name ?? '—';
const assetVariantOf    = (item) => item.asset_variant ?? item.assetVariant ?? null;
const variantLabel      = (item) => { const v = assetVariantOf(item); return v ? (v.display_name ?? v.name ?? null) : null; };

const itemableBadge = (item) => {
    if (!item.itemable_type) return null;
    if (item.itemable_type === ASSET_TYPE) return 'Asset';
    if (item.itemable_type === INVENTORY_TYPE) return 'Part';
    return null;
};

const discountCell = (item) => {
    if (item.discount_percent != null) return `${item.discount_percent}%`;
    if (item.discount != null && parseFloat(item.discount) !== 0) return formatCurrency(item.discount);
    return '—';
};

const isCoveredWarranty = (item) => {
    const billableTo = item.billable_to ?? 'customer';
    return billableTo !== 'customer' || !!item.is_warranty;
};

const customerFacingLineTotal = (item) => {
    if (isCoveredWarranty(item)) return 0;
    return item.total ?? item.line_total ?? 0;
};
</script>

<template>
    <!-- Public invoice shell: parent provides header / customer row (matches ServiceTicketReview). -->
    <div v-if="bodyOnly" class="invoice-document-body-only text-gray-900">
        <div v-if="record.notes" class="border-t border-gray-200 px-8 py-6">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Notes
            </h2>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">
                {{ record.notes }}
            </p>
        </div>

        <div class="border-t border-gray-200 px-8 py-6">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Line items
            </h2>
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-900">
                        <th class="py-3 pr-4 text-left text-sm font-semibold text-gray-900">Item</th>
                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Qty</th>
                        <th class="py-3 text-right text-sm font-semibold text-gray-900">Unit price</th>
                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Discount</th>
                        <th class="py-3 text-right text-sm font-semibold text-gray-900">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template v-if="lineItems.length">
                        <tr v-for="item in lineItems" :key="item.id" class="hover:bg-gray-50">
                            <td class="py-3 pr-4 align-top">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ itemPrimaryLabel(item) }}</span>
                                        <span
                                            v-if="itemableBadge(item)"
                                            class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                                        >
                                            {{ itemableBadge(item) }}
                                        </span>
                                    </div>
                                    <div v-if="variantLabel(item)" class="text-sm text-gray-600">
                                        <span class="font-medium text-gray-700">Variant:</span>
                                        {{ variantLabel(item) }}
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-center align-top text-gray-900">{{ item.quantity ?? 1 }}</td>
                            <td class="py-3 text-right align-top text-gray-900">
                                <span v-if="isCoveredWarranty(item)" class="text-sm text-blue-700">Covered under warranty</span>
                                <span v-else>{{ formatCurrency(item.unit_price ?? item.price) }}</span>
                            </td>
                            <td class="py-3 text-center align-top text-gray-900">
                                <span v-if="isCoveredWarranty(item)">—</span>
                                <span v-else>{{ discountCell(item) }}</span>
                            </td>
                            <td class="py-3 text-right align-top font-medium text-gray-900">{{ formatCurrency(customerFacingLineTotal(item)) }}</td>
                        </tr>
                    </template>
                    <tr v-else>
                        <td colspan="5" class="py-8 text-center text-sm text-gray-500">No line items</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-8 py-6">
            <div class="flex justify-end">
                <div class="w-full space-y-3 md:w-1/2 lg:w-1/3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.subtotal) }}</span>
                    </div>
                    <div
                        v-if="record.discount_total && parseFloat(record.discount_total) !== 0"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-600">Discount:</span>
                        <span class="font-medium text-green-700">-{{ formatCurrency(record.discount_total) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.tax_total) }}</span>
                    </div>
                    <div
                        v-if="record.fees_total && parseFloat(record.fees_total) !== 0"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-600">Fees:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(record.fees_total) }}</span>
                    </div>
                    <div class="flex justify-between border-t-2 border-gray-900 pt-3 text-xl font-bold text-gray-900">
                        <span>Total:</span>
                        <span>{{ formatCurrency(record.total) }}</span>
                    </div>
                    <div
                        v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0"
                        class="flex justify-between text-sm text-green-700"
                    >
                        <span>Amount paid:</span>
                        <span>-{{ formatCurrency(record.amount_paid) }}</span>
                    </div>
                    <div
                        v-if="record.amount_due != null"
                        class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-bold text-gray-900"
                    >
                        <span>Amount due:</span>
                        <span>{{ formatCurrency(record.amount_due) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-else
        class="invoice-document-for-print rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8 text-gray-900 print:border-0 print:shadow-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
    >

        <!-- Header: logo + company + status -->
        <div class="flex items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4 sm:gap-6">
                <div v-if="logoUrl" class="shrink-0">
                    <img :src="logoUrl" alt="" class="h-20 w-auto max-w-[150px] object-contain">
                </div>
                <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                    <span class="material-icons text-3xl text-gray-400 dark:text-gray-500">business</span>
                </div>
                <div class="min-w-0 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ companyName }}</h1>
                    <template v-if="companyAddressLines.length">
                        <p v-for="(line, idx) in companyAddressLines" :key="idx" class="leading-snug">{{ line }}</p>
                    </template>
                    <p v-if="companyPhone" class="flex items-center gap-1.5 pt-0.5">
                        <span class="material-icons text-base text-gray-400">phone</span>{{ companyPhone }}
                    </p>
                    <p v-if="companyEmail" class="flex items-center gap-1.5">
                        <span class="material-icons text-base text-gray-400">email</span>{{ companyEmail }}
                    </p>
                </div>
            </div>
            <span :class="['inline-flex h-fit shrink-0 rounded-full px-3 py-1 text-sm font-semibold print:hidden', statusBadgeClass]">
                {{ statusLabel }}
            </span>
        </div>

        <!-- Invoice title + date -->
        <div class="mt-6 flex flex-col gap-1 border-y border-gray-100 py-4 print:border-0 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                {{ record.display_name || `Invoice #${record.sequence ?? record.id}` }}
            </h2>
            <time class="text-base text-gray-500 dark:text-gray-400">
                Date: {{ formatDate(record.created_at) }}
            </time>
        </div>

        <!-- Bill to -->
<!-- Bill To + Payment Terms -->
<div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">

<!-- Bill To -->
<div class="max-w-xl">
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Bill to
    </h2>

    <address class="not-italic text-sm text-gray-700 space-y-0.5 dark:text-gray-300">
        <span v-if="record.customer_name" class="block font-semibold text-gray-900 dark:text-white">
            {{ record.customer_name }}
        </span>

        <span v-if="record.customer_email" class="block">
            {{ record.customer_email }}
        </span>

        <span v-if="record.customer_phone" class="block text-gray-500 dark:text-gray-400">
            {{ record.customer_phone }}
        </span>

        <template v-if="record.billing_address_line1">
            <span class="mt-1 block">{{ record.billing_address_line1 }}</span>
            <span v-if="record.billing_address_line2" class="block">
                {{ record.billing_address_line2 }}
            </span>
            <span class="block">
                {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
            </span>
            <span v-if="record.billing_country" class="block text-gray-500 dark:text-gray-400">
                {{ record.billing_country }}
            </span>
        </template>
    </address>
</div>

<!-- Payment Terms (right side) -->
<div v-if="paymentTermLabel" class="sm:text-right">
    <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Payment terms
    </h3>
    <p class="text-sm font-medium text-gray-900 dark:text-white">
        {{ paymentTermLabel }}
    </p>
</div>

</div>

<!-- Notes (full width below) -->
<div v-if="record.notes" class="mt-8">
<h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
    Notes
</h3>
<p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
    {{ record.notes }}
</p>
</div>

        <!-- Line items -->
        <div class="mt-8 overflow-x-auto rounded-lg border border-gray-100 print:border-0 dark:border-gray-700">
            <table class="w-full text-left text-sm font-medium text-gray-900 dark:text-gray-100">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold sm:px-6">Item</th>
                        <th class="px-4 py-3 font-semibold sm:px-6">Qty</th>
                        <th class="px-4 py-3 font-semibold sm:px-6">Unit price</th>
                        <th class="px-4 py-3 font-semibold sm:px-6">Discount</th>
                        <th class="px-4 py-3 text-right font-semibold sm:px-6 whitespace-nowrap">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="lineItems.length">
                        <tr v-for="item in lineItems" :key="item.id"
                            class="border-b border-gray-100 bg-white print:border-0 dark:border-gray-700 dark:bg-gray-800">
                            <th scope="row" class="px-4 py-4 align-top font-medium sm:px-6">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-base text-gray-900 dark:text-white">{{ itemPrimaryLabel(item) }}</span>
                                        <span v-if="itemableBadge(item)"
                                              class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ itemableBadge(item) }}
                                        </span>
                                    </div>
                                    <div v-if="variantLabel(item)" class="text-xs text-gray-600 dark:text-gray-400">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Variant:</span>
                                        {{ variantLabel(item) }}
                                    </div>
                                </div>
                            </th>
                            <td class="px-4 py-4 align-top sm:px-6">{{ item.quantity ?? 1 }}</td>
                            <td class="px-4 py-4 align-top sm:px-6">
                                <span v-if="isCoveredWarranty(item)" class="text-sm text-blue-700 dark:text-blue-300">Covered under warranty</span>
                                <span v-else>{{ formatCurrency(item.unit_price ?? item.price) }}</span>
                            </td>
                            <td class="px-4 py-4 align-top sm:px-6">
                                <span v-if="isCoveredWarranty(item)">—</span>
                                <span v-else>{{ discountCell(item) }}</span>
                            </td>
                            <td class="px-4 py-4 text-right align-top sm:px-6">{{ formatCurrency(customerFacingLineTotal(item)) }}</td>
                        </tr>
                    </template>
                    <tr v-else>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No line items</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="ms-auto mt-6 max-w-xs">
            <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Summary</h3>
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.subtotal) }}</span>
                </li>
                <li v-if="record.discount_total && parseFloat(record.discount_total) !== 0" class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Discount</span>
                    <span class="font-medium text-green-700 dark:text-green-400">-{{ formatCurrency(record.discount_total) }}</span>
                </li>
                <li class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Tax</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.tax_total) }}</span>
                </li>
                <li v-if="record.fees_total && parseFloat(record.fees_total) !== 0" class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Fees</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.fees_total) }}</span>
                </li>
                <li class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold text-gray-900 print:border-0 dark:border-gray-700 dark:text-white">
                    <span>Total</span>
                    <span>{{ formatCurrency(record.total) }}</span>
                </li>
                <li v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0" class="flex justify-between text-green-700 dark:text-green-400">
                    <span>Amount paid</span>
                    <span>-{{ formatCurrency(record.amount_paid) }}</span>
                </li>
                <li v-if="record.amount_due != null" class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-gray-800 print:border-0 dark:border-gray-700 dark:text-gray-200">
                    <span>Amount due</span>
                    <span>{{ formatCurrency(record.amount_due) }}</span>
                </li>
            </ul>
        </div>

    </div>
</template>