<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import InvoicePreview from '@/Components/Tenant/InvoicePreview.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref, watch } from 'vue';

const page = usePage();
const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) return;
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') root.createToast(type, String(message));
}

watch(() => page.props.flash?.success, (msg) => { if (msg) showToast('success', msg); }, { immediate: true });
watch(() => page.props.flash?.error,   (msg) => { if (msg) showToast('error', msg); },   { immediate: true });

const props = defineProps({
    record:                { type: Object, required: true },
    enumOptions:           { type: Object, default: () => ({}) },
    account:               { type: Object, default: null },
    enabledPaymentMethods: { type: Array,  default: () => [] },
    formSchema:            { type: Object, default: () => ({}) },
    domainName:            { type: String, default: 'Invoice' },
});

const STATUS_ENUM_KEY       = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const ASSET_TYPE            = 'App\\Domain\\Asset\\Models\\Asset';
const INVENTORY_TYPE        = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';

const previewOpen = ref(false);

// ─── Enum lookups ─────────────────────────────────────────────────────────────
const statusOptions      = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);

const statusInfo = computed(() => {
    const s = props.record?.status;
    return statusOptions.value.find(o => o.id == s || o.value === s)
        ?? { id: 0, value: s, name: s ?? 'Unknown', color: 'gray', bgClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' };
});

const STATUS_TEXT = {
    gray:   'text-gray-700 dark:text-gray-300',
    blue:   'text-blue-700 dark:text-blue-300',
    indigo: 'text-indigo-700 dark:text-indigo-300',
    yellow: 'text-yellow-700 dark:text-yellow-300',
    amber:  'text-amber-700 dark:text-amber-300',
    green:  'text-green-700 dark:text-green-300',
    red:    'text-red-700 dark:text-red-300',
    slate:  'text-slate-700 dark:text-slate-300',
};
const statusTextClass = computed(() => STATUS_TEXT[statusInfo.value?.color] ?? 'text-gray-700 dark:text-gray-300');

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opt = paymentTermOptions.value.find(o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw));
    return opt?.name ?? raw ?? '—';
});

// ─── Line items ───────────────────────────────────────────────────────────────
const lineItems = computed(() => {
    const raw = props.record?.items ?? props.record?.line_items ?? [];
    return Array.isArray(raw) ? raw : [];
});

const assetLines     = computed(() => lineItems.value.filter(li => li.itemable_type === ASSET_TYPE));
const inventoryLines = computed(() => lineItems.value.filter(li => li.itemable_type === INVENTORY_TYPE));
const otherLines     = computed(() => lineItems.value.filter(li => li.itemable_type !== ASSET_TYPE && li.itemable_type !== INVENTORY_TYPE));

const itemableBadge = (item) => {
    if (!item.itemable_type) return null;
    if (item.itemable_type === ASSET_TYPE) return 'Asset';
    if (item.itemable_type === INVENTORY_TYPE) return 'Part';
    return null;
};

const variantLabel = (item) => {
    const v = item.asset_variant ?? item.assetVariant ?? null;
    return v ? (v.display_name ?? v.name ?? null) : null;
};

const unitLabel = (item) => {
    const u = item.asset_unit ?? item.assetUnit ?? null;
    const raw = u?.display_name;
    if (!raw) return null;
    const parts = String(raw).split(' - ');
    return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
};

const discountCell = (item) => {
    if (item.discount_percent != null) return `${item.discount_percent}%`;
    if (item.discount != null && parseFloat(item.discount) !== 0) return formatCurrency(item.discount);
    return '—';
};

// ─── Payment methods ──────────────────────────────────────────────────────────
const methodLabelByCode = computed(() => {
    const map = new Map();
    for (const m of props.enabledPaymentMethods || []) {
        if (m?.code) map.set(m.code, m.label ?? m.code);
    }
    return map;
});

const acceptedPaymentMethodsText = computed(() => {
    const list = props.enabledPaymentMethods || [];
    const raw  = props.record?.allowed_methods;
    if (raw == null) return list.length ? list.map(m => m.label ?? m.code).join(', ') : 'None enabled';
    if (!Array.isArray(raw) || raw.length === 0) return 'None selected';
    return raw.map(code => typeof code === 'string' ? (methodLabelByCode.value.get(code) ?? code) : code).join(', ');
});

const surchargePercentText = computed(() => {
    const v = props.record?.surcharge_percent;
    if (v == null || v === '') return null;
    const n = Number(v);
    if (Number.isNaN(n) || n === 0) return null;
    return `${n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`;
});

const partialPaymentSummary = computed(() => {
    if (!props.record?.allow_partial_payment) return { allowed: false, minimumText: null };
    const min = props.record?.minimum_partial_amount;
    return {
        allowed: true,
        minimumText: (min != null && min !== '' && Number(min) !== 0) ? formatCurrency(min) : null,
    };
});

// ─── Computed state ───────────────────────────────────────────────────────────
const isPaid     = computed(() => !!props.record?.paid_at);
const isOverdue  = computed(() => !isPaid.value && props.record?.due_at && new Date(props.record.due_at) < new Date() && props.record?.amount_due > 0);

const invoiceLabel = computed(() => props.record?.display_name || `Invoice #${props.record?.sequence ?? props.record?.id}`);

const logoUrl = computed(() => props.account?.logo_url ?? null);

// ─── Links ────────────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
    { label: 'Home',     href: route('dashboard') },
    { label: 'Invoices', href: route('invoices.index') },
    { label: invoiceLabel.value },
]);

const contactShowHref     = computed(() => props.record.contact_id     ? route('contacts.show',     props.record.contact_id)     : null);
const transactionShowHref = computed(() => props.record.transaction_id ? route('transactions.show', props.record.transaction_id) : null);
const contractShowHref    = computed(() => props.record.contract_id    ? route('contracts.show',    props.record.contract_id)    : null);

// ─── Formatters ───────────────────────────────────────────────────────────────
const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const formatDateTime = (val) => {
    if (!val) return '—';
    try { return new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); }
    catch { return '—'; }
};

// ─── Actions ──────────────────────────────────────────────────────────────────
const sendToCustomer = () => {
    if (!confirm('Email the customer a link to view this invoice?')) return;
    router.post(route('invoices.send-to-customer', props.record.id));
};

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const showRecordPaymentModal = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method_code: 'check',
    reference_number: '',
    memo: '',
});

const manualMethodOptions = [
    { value: 'check', label: 'Check' },
    { value: 'cash', label: 'Cash' },
    { value: 'wire', label: 'Wire transfer' },
    { value: 'ach', label: 'ACH / bank transfer' },
];

const canRecordManualPayment = computed(() => {
    const s = props.record?.status;
    if (['void', 'draft', 'paid'].includes(s)) return false;
    const due = parseFloat(props.record?.amount_due);
    return !Number.isNaN(due) && due > 0.009;
});

const openRecordPaymentModal = () => {
    paymentForm.reset();
    paymentForm.clearErrors();
    const due = props.record?.amount_due;
    paymentForm.amount = due != null && due !== '' ? Number(due).toFixed(2) : '';
    paymentForm.payment_method_code = 'check';
    paymentForm.reference_number = '';
    paymentForm.memo = '';
    showRecordPaymentModal.value = true;
};

const submitManualPayment = () => {
    paymentForm.post(route('invoices.apply-manual-payment', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRecordPaymentModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="invoiceLabel" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ invoiceLabel }}
                        </h2>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                              :class="[statusInfo.bgClass, statusTextClass]">
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                @click="previewOpen = true">
                            <span class="material-icons text-[16px]">visibility</span>
                            Customer preview
                        </button>
                        <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2  text-sm font-medium text-white bg-secondary-600 hover:bg-secondary-700 rounded-lg transition-colors"
                                @click="sendToCustomer">
                            <span class="material-icons text-[16px]">send</span>
                            Send to customer
                        </button>
                        <button v-if="canRecordManualPayment"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-100 bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700 hover:bg-amber-200 dark:hover:bg-amber-900/60 rounded-lg transition-colors"
                                @click="openRecordPaymentModal">
                            <span class="material-icons text-[16px]">payments</span>
                            Record payment
                        </button>
                        <a :href="route('invoices.edit', record.id)"
                           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <InvoicePreview
            v-if="previewOpen"
            :record="record"
            :account="account"
            :enum-options="enumOptions"
            :logo-url="logoUrl"
            @close="previewOpen = false"
        />

        <div class="w-full flex flex-col space-y-6 p-4">

            <!-- Paid / Overdue banner -->
            <div v-if="isPaid"
                 class="flex items-center gap-3 px-5 py-3.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                <span class="material-icons text-green-500 dark:text-green-400">check_circle</span>
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                    Invoice paid on <span class="font-semibold">{{ formatDate(record.paid_at) }}</span>.
                </p>
            </div>
            <div v-else-if="isOverdue"
                 class="flex items-center gap-3 px-5 py-3.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                <span class="material-icons text-red-500 dark:text-red-400">warning</span>
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                    This invoice is <span class="font-semibold">overdue</span>. Due date was <span class="font-semibold">{{ formatDate(record.due_at) }}</span>.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ── Main column ───────────────────────────────────────── -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header card -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <h1 class="text-2xl font-bold text-white">INVOICE</h1>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                              :class="[statusInfo.bgClass, statusTextClass]">
                                            {{ statusInfo.name }}
                                        </span>
                                    </div>
                                    <p class="text-primary-100 text-sm">Customer invoice details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ invoiceLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: Customer & Billing -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer &amp; Billing
                                    </h3>

                                    <div v-if="record.customer_name">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</div>
                                        <Link v-if="contactShowHref" :href="contactShowHref"
                                              class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ record.customer_name }}
                                        </Link>
                                        <div v-else class="text-sm font-medium text-gray-900 dark:text-white">{{ record.customer_name }}</div>
                                    </div>

                                    <div v-if="record.customer_email">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</div>
                                        <a :href="`mailto:${record.customer_email}`"
                                           class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ record.customer_email }}
                                        </a>
                                    </div>

                                    <div v-if="record.customer_phone">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Phone</div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.customer_phone }}</div>
                                    </div>

                                    <div v-if="record.billing_address_line1 || record.billing_city">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Billing Address</div>
                                        <div class="text-sm text-gray-900 dark:text-white space-y-0.5">
                                            <div v-if="record.billing_address_line1">{{ record.billing_address_line1 }}</div>
                                            <div v-if="record.billing_address_line2">{{ record.billing_address_line2 }}</div>
                                            <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                                                {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                            </div>
                                            <div v-if="record.billing_country" class="text-gray-500 dark:text-gray-400">{{ record.billing_country }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Invoice details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Invoice Details
                                    </h3>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Invoice Date</div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</div>
                                    </div>

                                    <div v-if="record.due_at">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Due Date</div>
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.due_at) }}</div>
                                            <span v-if="isOverdue"
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                                Overdue
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Terms</div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ paymentTermLabel }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Currency</div>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes + Terms -->
                            <div v-if="record.notes"
                                 class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.notes }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Line items -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Line Items</h2>
                        </div>

                        <div v-if="lineItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <tr v-for="item in lineItems" :key="item.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-5 py-3.5 align-top">
                                            <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ item.name ?? '—' }}</span>
                                                <span v-if="itemableBadge(item)"
                                                      class="rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                    {{ itemableBadge(item) }}
                                                </span>
                                            </div>
                                            <div v-if="variantLabel(item)" class="text-xs text-gray-500 dark:text-gray-400">
                                                Variant: {{ variantLabel(item) }}
                                            </div>
                                            <div v-if="unitLabel(item)" class="text-xs text-gray-500 dark:text-gray-400">
                                                Unit: {{ unitLabel(item) }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-right align-top text-gray-700 dark:text-gray-300">{{ item.quantity ?? 1 }}</td>
                                        <td class="px-5 py-3.5 text-right align-top text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price ?? item.price) }}</td>
                                        <td class="px-5 py-3.5 text-right align-top text-gray-500 dark:text-gray-400">{{ discountCell(item) }}</td>
                                        <td class="px-5 py-3.5 text-right align-top font-semibold text-gray-900 dark:text-white">{{ formatCurrency(item.total ?? item.line_total) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr v-if="record.discount_total && parseFloat(record.discount_total) !== 0">
                                        <td colspan="4" class="px-5 py-2 text-right text-xs text-gray-500 dark:text-gray-400">Discount</td>
                                        <td class="px-5 py-2 text-right text-xs font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.discount_total) }}</td>
                                    </tr>
                                    <tr v-if="record.tax_total">
                                        <td colspan="4" class="px-5 py-2 text-right text-xs text-gray-500 dark:text-gray-400">Tax</td>
                                        <td class="px-5 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(record.tax_total) }}</td>
                                    </tr>
                                    <tr v-if="record.fees_total && parseFloat(record.fees_total) !== 0">
                                        <td colspan="4" class="px-5 py-2 text-right text-xs text-gray-500 dark:text-gray-400">Fees</td>
                                        <td class="px-5 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(record.fees_total) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-5 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Total</td>
                                        <td class="px-5 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(record.total) }}</td>
                                    </tr>
                                    <tr v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0">
                                        <td colspan="4" class="px-5 py-2 text-right text-xs text-gray-500 dark:text-gray-400">Amount paid</td>
                                        <td class="px-5 py-2 text-right text-xs font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.amount_paid) }}</td>
                                    </tr>
                                    <tr v-if="record.amount_due != null">
                                        <td colspan="4" class="px-5 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">Amount Due</td>
                                        <td class="px-5 py-3 text-right text-base font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(record.amount_due) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <span class="material-icons text-[40px] text-gray-300 dark:text-gray-600 mb-3">receipt_long</span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">No line items on this invoice</p>
                        </div>
                    </div>

                </div>

                <!-- ── Sidebar ────────────────────────────────────────────── -->
                <div class="lg:col-span-4 space-y-4">

                    <!-- Actions -->
                    <!-- <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</span>
                        </div>
                        <div class="p-4 space-y-2">
                            <button type="button"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                    @click="previewOpen = true">
                                <span class="material-icons text-[16px]">visibility</span>
                                Customer preview
                            </button>
                            <button type="button"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                                    @click="sendToCustomer">
                                <span class="material-icons text-[16px]">send</span>
                                Send to customer
                            </button>
                            <a :href="route('invoices.edit', record.id)"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                                <span class="material-icons text-[16px]">edit</span>
                                Edit invoice
                            </a>
                        </div>
                    </div> -->

                    <!-- Invoice Total -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Total</span>
                        </div>
                        <div class="p-4 space-y-2.5 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.subtotal) }}</span>
                            </div>
                            <div v-if="record.discount_total && parseFloat(record.discount_total) !== 0"
                                 class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Discount</span>
                                <span class="font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.discount_total) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Tax</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.tax_total) }}</span>
                            </div>
                            <div v-if="record.fees_total && parseFloat(record.fees_total) !== 0"
                                 class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Fees</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.fees_total) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2.5 border-t border-gray-100 dark:border-gray-700">
                                <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(record.total) }}</span>
                            </div>
                            <template v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0">
                                <div class="flex justify-between items-center text-green-600 dark:text-green-400">
                                    <span>Amount paid</span>
                                    <span class="font-medium">-{{ formatCurrency(record.amount_paid) }}</span>
                                </div>
                            </template>
                            <div v-if="record.amount_due != null"
                                 class="flex justify-between items-center pt-2.5 border-t border-gray-100 dark:border-gray-700">
                                <span class="font-semibold text-gray-900 dark:text-white">Amount Due</span>
                                <span class="text-base font-bold" :class="isPaid ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white'">
                                    {{ formatCurrency(record.amount_due) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">credit_card</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Terms</span>
                                <span class="text-xs font-medium text-gray-900 dark:text-white">{{ paymentTermLabel }}</span>
                            </li>
                            <li class="flex items-start gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0 mt-0.5">account_balance</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-gray-500 dark:text-gray-400">Accepted methods</p>
                                    <p class="text-xs font-medium text-gray-900 dark:text-white mt-0.5">{{ acceptedPaymentMethodsText }}</p>
                                </div>
                            </li>
                            <li v-if="surchargePercentText" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">percent</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Surcharge</span>
                                <span class="text-xs font-medium text-gray-900 dark:text-white">{{ surchargePercentText }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">pie_chart</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Partial payments</span>
                                <span class="text-xs font-medium text-gray-900 dark:text-white">
                                    {{ partialPaymentSummary.allowed ? 'Allowed' : 'Not allowed' }}
                                    <template v-if="partialPaymentSummary.allowed && partialPaymentSummary.minimumText">
                                        (min. {{ partialPaymentSummary.minimumText }})
                                    </template>
                                </span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">payments</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Currency</span>
                                <span class="text-xs font-medium text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Invoice Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Info</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                            <li v-if="contactShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">person</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Contact</span>
                                <Link :href="contactShowHref" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li v-if="transactionShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">handshake</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Deal</span>
                                <Link :href="transactionShowHref" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li v-if="contractShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">description</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Contract</span>
                                <Link :href="contractShowHref" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">calendar_today</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">update</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.updated_at) }}</span>
                            </li>
                            <li v-if="record.sent_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">send</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Sent</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.sent_at) }}</span>
                            </li>
                            <li v-if="record.viewed_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">visibility</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Viewed</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.viewed_at) }}</span>
                            </li>
                            <li v-if="record.paid_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-green-500 shrink-0">check_circle</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Paid</span>
                                <span class="text-xs font-medium text-green-600 dark:text-green-400">{{ formatDate(record.paid_at) }}</span>
                            </li>
                        </ul>

                        <!-- Timeline -->
                        <div class="px-5 pt-4 pb-5 border-t border-gray-50 dark:border-gray-700/60">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Timeline</p>
                            <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700 space-y-3">
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px] text-green-500">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white">Created</p>
                                        <time class="text-[11px] text-gray-400">{{ formatDate(record.created_at) }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.sent_at ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white">Sent</p>
                                        <time class="text-[11px] text-gray-400">{{ record.sent_at ? formatDate(record.sent_at) : 'Pending' }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.viewed_at ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white">Viewed</p>
                                        <time class="text-[11px] text-gray-400">{{ record.viewed_at ? formatDate(record.viewed_at) : 'Not yet' }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.paid_at ? 'text-green-600' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white">Paid</p>
                                        <time class="text-[11px] text-gray-400">{{ record.paid_at ? formatDate(record.paid_at) : 'Pending' }}</time>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>

                </div>
            </div>

            <Sublist
                v-if="visibleSublists.length > 0 && domainName"
                class="mt-6"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="visibleSublists"
            />
        </div>

        <Modal :show="showRecordPaymentModal" max-width="lg" @close="showRecordPaymentModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Record manual payment</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    Apply check, cash, wire, or ACH to this invoice. The customer balance and invoice status will update.
                </p>
                <form class="space-y-4" @submit.prevent="submitManualPayment">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Amount</label>
                        <input v-model="paymentForm.amount"
                               type="text"
                               inputmode="decimal"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-sm"
                               required>
                        <p v-if="paymentForm.errors.amount" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ paymentForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Method</label>
                        <select v-model="paymentForm.payment_method_code"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-sm">
                            <option v-for="opt in manualMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reference # <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <input v-model="paymentForm.reference_number"
                               type="text"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-sm"
                               placeholder="Check number, confirmation, etc.">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Memo <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <textarea v-model="paymentForm.memo"
                                  rows="2"
                                  class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                                @click="showRecordPaymentModal = false">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50"
                                :disabled="paymentForm.processing">
                            {{ paymentForm.processing ? 'Saving…' : 'Record payment' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </TenantLayout>
</template>