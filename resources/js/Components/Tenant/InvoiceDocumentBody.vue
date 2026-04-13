<script setup>
import { computed } from 'vue';

const ASSET_TYPE = 'App\\Domain\\Asset\\Models\\Asset';
const INVENTORY_TYPE = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
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

const fromLines = computed(() => {
    const t = props.record?.transaction;
    const lines = [];
    if (t?.subsidiary?.display_name) {
        lines.push(t.subsidiary.display_name);
    }
    if (t?.location?.display_name) {
        lines.push(t.location.display_name);
    }
    if (lines.length) {
        return lines;
    }
    return [accountDisplayName.value];
});

const statusLabel = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    return opts.find(o => o.id == s || o.value === s)?.name ?? s ?? 'Draft';
});

const statusBadgeClass = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM_KEY] ?? [];
    const s = props.record?.status;
    const opt = opts.find(o => o.id == s || o.value === s);
    if (opt?.bgClass) {
        return opt.bgClass;
    }
    const map = {
        draft: 'bg-gray-100 text-gray-700',
        sent: 'bg-blue-100 text-blue-800',
        viewed: 'bg-purple-100 text-purple-800',
        partial: 'bg-yellow-100 text-yellow-900',
        paid: 'bg-green-100 text-green-800',
        void: 'bg-slate-200 text-slate-600',
    };
    const v = typeof s === 'string' ? s : opts.find(o => o.id == s)?.value;
    return map[v] ?? map.draft;
});

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opts = props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? [];
    const opt = opts.find(
        o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw),
    );
    return opt?.name ?? raw ?? '—';
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

const itemPrimaryLabel = (item) => item.name ?? item.description ?? '—';

const itemSecondary = (item) => {
    if (item.name && item.description && item.description !== item.name) {
        return item.description;
    }
    return null;
};

const itemableBadge = (item) => {
    if (!item.itemable_type) {
        return null;
    }
    if (item.itemable_type === ASSET_TYPE) {
        return 'Asset';
    }
    if (item.itemable_type === INVENTORY_TYPE) {
        return 'Part';
    }
    return null;
};

const itemableName = (item) => {
    const ib = item.itemable;
    if (!ib) {
        return null;
    }
    return ib.display_name ?? ib.name ?? null;
};

const discountCell = (item) => {
    if (item.discount_percent != null) {
        return `${item.discount_percent}%`;
    }
    if (item.discount != null && parseFloat(item.discount) !== 0) {
        return formatCurrency(item.discount);
    }
    return '—';
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8 text-gray-900">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt=""
                    class="h-10 w-auto max-w-[160px] object-contain"
                >
                <div v-else class="text-lg font-semibold text-gray-900">
                    {{ accountDisplayName }}
                </div>
            </div>
            <span
                :class="['inline-flex shrink-0 px-3 py-1 rounded-full text-sm font-semibold', statusBadgeClass]"
            >
                {{ statusLabel }}
            </span>
        </div>

        <div class="mt-6 flex flex-col gap-1 border-y border-gray-100 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-bold text-gray-900">
                {{ record.display_name || `Invoice #${record.sequence ?? record.id}` }}
            </h1>
            <time class="text-base text-gray-500">
                Date: {{ formatDate(record.created_at) }}
            </time>
        </div>

        <div class="mt-8 flex flex-col gap-8 sm:flex-row sm:justify-between">
            <div class="sm:w-64">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">From</h2>
                <address class="not-italic text-sm text-gray-700 space-y-0.5">
                    <template v-for="(line, idx) in fromLines" :key="idx">
                        <span class="block" :class="idx === 0 ? 'font-semibold text-gray-900' : ''">{{ line }}</span>
                    </template>
                </address>
            </div>
            <div class="sm:w-64">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Bill to</h2>
                <address class="not-italic text-sm text-gray-700 space-y-0.5">
                    <span v-if="record.customer_name" class="block font-semibold text-gray-900">{{ record.customer_name }}</span>
                    <span v-if="record.customer_email" class="block">{{ record.customer_email }}</span>
                    <span v-if="record.customer_phone" class="block text-gray-500">{{ record.customer_phone }}</span>
                    <template v-if="record.billing_address_line1">
                        <span class="mt-1 block">{{ record.billing_address_line1 }}</span>
                        <span v-if="record.billing_address_line2" class="block">{{ record.billing_address_line2 }}</span>
                        <span class="block">
                            {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                        </span>
                        <span v-if="record.billing_country" class="block text-gray-500">{{ record.billing_country }}</span>
                    </template>
                </address>
            </div>
        </div>

        <div class="mt-8 overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-left text-sm font-medium text-gray-900">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
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
                        <tr
                            v-for="item in lineItems"
                            :key="item.id"
                            class="border-b border-gray-100 bg-white"
                        >
                            <th scope="row" class="px-4 py-4 align-top font-medium sm:px-6">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-base text-gray-900">{{ itemPrimaryLabel(item) }}</span>
                                        <span
                                            v-if="itemableBadge(item)"
                                            class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                        >
                                            {{ itemableBadge(item) }}
                                        </span>
                                    </div>
                                    <div v-if="itemSecondary(item)" class="text-xs font-normal text-gray-500">
                                        {{ itemSecondary(item) }}
                                    </div>
                                    <div v-if="itemableName(item)" class="text-xs text-gray-600">
                                        {{ itemableName(item) }}
                                    </div>
                                </div>
                            </th>
                            <td class="px-4 py-4 align-top sm:px-6">{{ item.quantity ?? 1 }}</td>
                            <td class="px-4 py-4 align-top sm:px-6">{{ formatCurrency(item.unit_price ?? item.price) }}</td>
                            <td class="px-4 py-4 align-top sm:px-6">{{ discountCell(item) }}</td>
                            <td class="px-4 py-4 text-right align-top sm:px-6">
                                {{ formatCurrency(item.total ?? item.line_total) }}
                            </td>
                        </tr>
                    </template>
                    <tr v-else>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">
                            No line items
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ms-auto mt-6 max-w-xs">
            <h3 class="mb-3 font-semibold text-gray-900">Summary</h3>
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(record.subtotal) }}</span>
                </li>
                <li
                    v-if="record.discount_total && parseFloat(record.discount_total) !== 0"
                    class="flex justify-between"
                >
                    <span class="text-gray-500">Discount</span>
                    <span class="font-medium text-green-700">-{{ formatCurrency(record.discount_total) }}</span>
                </li>
                <li class="flex justify-between">
                    <span class="text-gray-500">Tax</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(record.tax_total) }}</span>
                </li>
                <li
                    v-if="record.fees_total && parseFloat(record.fees_total) !== 0"
                    class="flex justify-between"
                >
                    <span class="text-gray-500">Fees</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(record.fees_total) }}</span>
                </li>
                <li class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold text-gray-900">
                    <span>Total</span>
                    <span>{{ formatCurrency(record.total) }}</span>
                </li>
                <li
                    v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0"
                    class="flex justify-between text-green-700"
                >
                    <span>Amount paid</span>
                    <span>-{{ formatCurrency(record.amount_paid) }}</span>
                </li>
                <li
                    v-if="record.amount_due != null"
                    class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-primary-600"
                >
                    <span>Amount due</span>
                    <span>{{ formatCurrency(record.amount_due) }}</span>
                </li>
            </ul>
        </div>

        <div v-if="record.notes" class="mt-8 border-t border-gray-100 pt-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</h3>
            <p class="whitespace-pre-line text-sm text-gray-600">{{ record.notes }}</p>
        </div>

        <div class="mt-6 text-xs text-gray-500">
            <span class="font-medium text-gray-600">Terms:</span>
            {{ paymentTermLabel }}
        </div>
    </div>
</template>
