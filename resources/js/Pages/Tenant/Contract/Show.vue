<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import ContractPreview from '@/Components/Tenant/ContractPreview.vue';
import ResolvedLineItemsEstimateStyle from '@/Components/Tenant/ResolvedLineItemsEstimateStyle.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    resolveLineItemsForContract,
    resolveLineItemsGrandTotalWithTax,
    taxRateForResolvedLines,
} from '@/Utils/lineItemsFromEstimate';
import { ref, computed, getCurrentInstance, onMounted } from 'vue';

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) return;
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    contractReviewSms: {
        type: Object,
        default: () => ({ offered: false, hint: null }),
    },
    /** When signed + linked deal has no invoices yet — show “next step” modal (same idea as estimate → create deal). */
    suggestCreateInvoice: { type: Boolean, default: false },
});

const page = usePage();

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

const signedContractReviewHref = computed(() => {
    if (!isSigned.value || !props.record?.uuid || !route().has('contracts.review')) {
        return null;
    }

    return route('contracts.review', props.record.uuid);
});

const signatureMethodOptions = computed(
    () => props.enumOptions?.['App\\Enums\\ServiceTicket\\SignatureMethod'] ?? [],
);

const signatureMethodLabel = (method) => {
    const opt = signatureMethodOptions.value.find((o) => o.id == method);
    return opt?.name ?? method ?? '—';
};

const hasSignatureVisual = computed(
    () => !!props.record?.signature_url
        || (Number(props.record?.signature_method) === 5 && !!props.record?.customer_signature),
);
const isDraft = computed(() => props.record?.status === 'draft');
const isSent = computed(() => props.record?.status === 'pending_approval');

const logoUrl = computed(() => props.account?.logo_url ?? null);

const showSendContractDeliveryModal = ref(false);
const contractDelivery = ref('email');

const openSendContractDeliveryModal = () => {
    contractDelivery.value = 'email';
    showSendContractDeliveryModal.value = true;
};

const closeSendContractDeliveryModal = () => {
    showSendContractDeliveryModal.value = false;
};

const customerContractEmail = computed(() => props.record?.customer?.email ?? props.record?.transaction?.customer_email ?? '');

const contractEmailPreview = computed(() => {
    if (page.props.tenant_sandbox_mode) {
        return page.props.auth?.user?.email ?? '';
    }
    return customerContractEmail.value;
});

const sendContractModalSubtitle = computed(() =>
    page.props.tenant_sandbox_mode
        ? 'Sandbox is on: choose how you want to receive the test. Email and SMS go to you, not the customer.'
        : 'Choose how to notify the customer.',
);

const sendContractForm = useForm({ delivery: 'email' });

const confirmSendContract = () => {
    sendContractForm.delivery = contractDelivery.value;
    sendContractForm.post(route('contracts.send-to-customer', props.record.id), {
        preserveScroll: true,
        onSuccess: (p) => {
            const errs = p.props.errors || {};
            if (!errs.delivery && !errs.error) {
                closeSendContractDeliveryModal();
                showPreview.value = false;
            }
            const flash = p.props.flash;
            if (flash?.success) {
                showToast('success', flash.success);
            }
            const flashErr = flash?.error;
            if (flashErr) {
                showToast('error', Array.isArray(flashErr) ? flashErr[0] : flashErr);
            }
            const err = errs.error ?? errs.delivery;
            if (err) {
                showToast('error', Array.isArray(err) ? err[0] : err);
            }
        },
        onError: () => {
            const d = sendContractForm.errors.delivery;
            if (d) {
                showToast('error', Array.isArray(d) ? d[0] : d);
            }
        },
    });
};

const onPreviewRequestSend = () => {
    openSendContractDeliveryModal();
};

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

// ─── Line items: prefer linked estimate primary version to match estimate; else deal ───
const lineItemsResolution = computed(() => resolveLineItemsForContract(props.record));
const transactionItems = computed(() => lineItemsResolution.value.items);
const lineItemsFromEstimate = computed(() => lineItemsResolution.value.source === 'estimate');

const taxRate = computed(() =>
    taxRateForResolvedLines(
        props.record,
        lineItemsResolution.value.source,
        props.record?.transaction?.tax_rate,
    ),
);

const lineItemsSubtotal = computed(() =>
    resolveLineItemsGrandTotalWithTax(transactionItems.value, taxRate.value),
);

const showPreview = ref(false);
const openPreview = () => { showPreview.value = true; };
const closePreview = () => { showPreview.value = false; };

const showCreateInvoiceModal = ref(false);

const createInvoiceHref = computed(() => {
    const tid = props.record?.transaction_id;
    if (!tid) {
        return route('invoices.create');
    }
    const cid = props.record?.customer?.contact_id ?? '';
    return `${route('invoices.create')}?transaction_id=${tid}&contact_id=${cid}`;
});

const closeCreateInvoiceModal = () => {
    showCreateInvoiceModal.value = false;
};

onMounted(() => {
    if (props.suggestCreateInvoice) {
        showCreateInvoiceModal.value = true;
    }
});
</script>

<template>
    <Head :title="`${contractLabel} — Contract`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 md:gap-3">
                        <h2 class="truncate text-lg font-semibold leading-tight text-gray-800 md:text-xl dark:text-gray-200">
                            {{ contractLabel }}
                        </h2>
                        <span
                            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold md:px-2.5 md:py-1 md:text-sm"
                            :class="[statusInfo.bgClass, statusTextClass]"
                        >
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1 md:gap-2">
                        <Link :href="route('contracts.index')">
                            <button
                                type="button"
                                aria-label="Back to contracts"
                                class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-300 bg-white p-2 text-md font-medium text-gray-700 hover:bg-gray-50 md:gap-1.5 md:px-4 md:py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-xl leading-none md:text-lg">arrow_back</span>
                                <span class="hidden md:inline">Back</span>
                            </button>
                        </Link>
                        <Link v-if="!isSigned" :href="route('contracts.edit', record.id)">
                            <button
                                type="button"
                                aria-label="Edit contract"
                                class="inline-flex items-center justify-center gap-0 rounded-lg bg-blue-600 p-2 text-md font-medium text-white hover:bg-blue-700 md:gap-1.5 md:px-4 md:py-2"
                            >
                                <span class="material-icons text-xl leading-none md:text-lg">edit</span>
                                <span class="hidden md:inline">Edit</span>
                            </button>
                        </Link>
                        <button
                            v-if="isSigned"
                            type="button"
                            aria-label="View signed contract"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-secondary-600 p-2 text-md font-medium text-white hover:bg-secondary-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="openPreview"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">visibility</span>
                            <span class="hidden md:inline">View signed contract</span>
                        </button>
                        <a
                            v-if="signedContractReviewHref"
                            :href="signedContractReviewHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-0 rounded-lg border border-secondary-200 bg-secondary-50 p-2 text-md font-medium text-secondary-800 hover:bg-secondary-100 md:gap-1.5 md:px-4 md:py-2 dark:border-secondary-800 dark:bg-secondary-950/40 dark:text-secondary-100 dark:hover:bg-secondary-900/50"
                            aria-label="Open signed contract in new tab for printing"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">print</span>
                            <span class="hidden md:inline">Print page</span>
                        </a>
                        <button
                            v-else-if="!isSigned"
                            type="button"
                            aria-label="Preview contract"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-purple-600 p-2 text-md font-medium text-white hover:bg-purple-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="openPreview"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">visibility</span>
                            <span class="hidden md:inline">Preview</span>
                        </button>
                        <button
                            v-if="!isSigned && record.status !== 'cancelled' && record.status !== 'expired'"
                            type="button"
                            aria-label="Send contract to customer"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-green-600 p-2 text-md font-medium text-white hover:bg-green-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="openSendContractDeliveryModal"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">send</span>
                            <span class="hidden md:inline">Send to Customer</span>
                        </button>
                        <button
                            type="button"
                            aria-label="Delete contract"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-red-600 p-2 text-md font-medium text-white hover:bg-red-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="deleteContract"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">delete</span>
                            <span class="hidden md:inline">Delete</span>
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
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold"
                                        :class="[statusInfo.bgClass, statusTextClass]"
                                    >
                                        {{ statusInfo.name }}
                                    </span>
                                </div>
                                <p class="text-blue-100 text-md">Contract details &amp; terms</p>
                            </div>
                            <div class="text-right">
                                <div class="text-blue-200 text-sm font-medium">Reference</div>
                                <div class="text-white text-xl font-mono">{{ contractLabel }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- Signed banner -->
                        <div v-if="isSigned" class="flex flex-col gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <span class="material-icons text-green-600 dark:text-green-400">verified</span>
                                <div>
                                    <p class="text-md font-semibold text-green-800 dark:text-green-200">Contract Signed</p>
                                    <p v-if="record.signed_at" class="text-md text-green-600 dark:text-green-400">{{ formatDateTime(record.signed_at) }}</p>
                                    <p v-if="record.signed_name" class="text-md text-green-600 dark:text-green-400">by {{ record.signed_name }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-green-700 px-3 py-2 text-sm font-medium text-white hover:bg-green-800"
                                    @click="openPreview"
                                >
                                    <span class="material-icons text-base">visibility</span>
                                    View signed contract
                                </button>
                                <a
                                    v-if="signedContractReviewHref"
                                    :href="signedContractReviewHref"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 rounded-lg border border-green-300 bg-white px-3 py-2 text-sm font-medium text-green-800 hover:bg-green-50 dark:border-green-700 dark:bg-green-950/40 dark:text-green-100 dark:hover:bg-green-900/50"
                                >
                                    <span class="material-icons text-base">print</span>
                                    Print
                                </a>
                            </div>
                        </div>

                        <!-- Sent banner -->
                        <div v-else-if="isSent" class="flex items-center gap-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3">
                            <span class="material-icons text-blue-600 dark:text-blue-400">send</span>
                            <div>
                                <p class="text-md font-semibold text-blue-800 dark:text-blue-200">Awaiting Signature</p>
                                <p class="text-md text-blue-600 dark:text-blue-400">This contract has been sent and is pending customer signature.</p>
                            </div>
                        </div>

                        <!-- Customer & Relations -->
                        <div class="border-gray-200 dark:border-gray-700 pt-2">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer &amp; Relations
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</p>
                                    <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.customer?.display_name || '—' }}
                                    </Link>
                                    <p v-else class="text-md text-gray-900 dark:text-white">—</p>
                                </div>
                                <div v-if="record.transaction_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Transaction</p>
                                    <Link :href="route('transactions.show', record.transaction_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.transaction?.display_name || `#${record.transaction_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.estimate_id">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Originating Estimate</p>
                                    <Link :href="route('estimates.show', record.estimate_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.estimate?.display_name || `#${record.estimate_id}` }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Contract Details -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Contract Details
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Amount</p>
                                    <p class="text-md font-semibold text-gray-900 dark:text-white">{{ formatCurrency(record.total_amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Currency</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Status</p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold" :class="[paymentInfo.bgClass, paymentTextClass]">
                                        {{ paymentInfo.name }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Required</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ record.signature_required ? 'Yes' : 'No' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div v-if="record.contract_terms || record.payment_terms || record.delivery_terms || record.payment_term" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Terms
                            </h3>
                            <div v-if="record.payment_term != null && record.payment_term !== ''" class="mb-6">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment term</p>
                                <p class="text-md font-semibold text-gray-900 dark:text-white">{{ paymentTermInfo.name }}</p>
                                <p v-if="paymentTermInfo.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ paymentTermInfo.description }}
                                </p>
                            </div>
                            <div v-if="record.contract_terms" class="mb-6">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Contract terms</p>
                                <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.contract_terms }}</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div v-if="record.payment_terms">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Terms</p>
                                    <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.payment_terms }}</p>
                                </div>
                                <div v-if="record.delivery_terms">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Delivery Terms</p>
                                    <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.delivery_terms }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Address (from linked deal when available) -->
                        <div v-if="hasAddress" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Billing Address
                            </h3>
                            <p v-if="billingFromTransaction" class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                Sourced from linked deal
                            </p>
                            <div class="text-md text-gray-900 dark:text-gray-100 space-y-0.5">
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
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Notes
                            </h3>
                            <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</p>
                        </div>

                        <!-- Line Items (estimate when linked, else deal) -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Line Items
                                <span
                                    v-if="lineItemsFromEstimate"
                                    class="ml-2 text-sm font-normal text-gray-400 dark:text-gray-500 normal-case tracking-normal"
                                >
                                    from linked estimate
                                </span>
                                <span
                                    v-else-if="record.transaction_id"
                                    class="ml-2 text-sm font-normal text-gray-400 dark:text-gray-500 normal-case tracking-normal"
                                >
                                    from linked deal
                                </span>
                            </h3>

                            <ResolvedLineItemsEstimateStyle
                                v-if="transactionItems.length > 0"
                                :items="transactionItems"
                                variant="tenant"
                                embedded
                                :format-money="(v) => formatCurrency(v)"
                                :show-summary="true"
                                :summary-tax-rate-percent="taxRate"
                                :summary-grand-total="lineItemsSubtotal"
                                :show-per-line-deal-tax="taxRate > 0"
                                :deal-tax-rate-percent="taxRate"
                            />

                            <div v-else class="text-center py-10 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                                <span class="material-icons text-4xl text-gray-400 dark:text-gray-600 mb-2 block">receipt_long</span>
                                <p class="text-md text-gray-500 dark:text-gray-400">
                                    <template v-if="record.estimate_id && !lineItemsFromEstimate">No line items on the linked estimate</template>
                                    <template v-else-if="record.transaction_id">No line items on the linked deal</template>
                                    <template v-else>No estimate or deal linked for line items</template>
                                </p>
                            </div>
                        </div>

                        <!-- Signature Details -->
                        <div v-if="isSigned" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Authorization &amp; Signature
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div v-if="record.signed_name">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed By</p>
                                        <p class="text-md text-gray-900 dark:text-white">{{ record.signed_name }}</p>
                                    </div>
                                    <div v-if="record.signed_email">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                        <p class="text-md text-gray-900 dark:text-white">{{ record.signed_email }}</p>
                                    </div>
                                    <div v-if="record.signature_method">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Method</p>
                                        <p class="text-md text-gray-900 dark:text-white">{{ signatureMethodLabel(record.signature_method) }}</p>
                                    </div>
                                    <div v-if="record.signed_at">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed At</p>
                                        <p class="text-md text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</p>
                                    </div>
                                    <div v-if="record.signed_ip">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">IP Address</p>
                                        <p class="text-md font-mono text-gray-900 dark:text-white">{{ record.signed_ip }}</p>
                                    </div>
                                    <div v-if="record.signature_hash">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Hash</p>
                                        <p class="text-sm font-mono text-gray-500 dark:text-gray-400 break-all">{{ record.signature_hash }}</p>
                                    </div>
                                    <div v-if="record.docusign_envelope_id">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">DocuSign Envelope</p>
                                        <p class="text-sm font-mono text-gray-500 dark:text-gray-400">{{ record.docusign_envelope_id }}</p>
                                    </div>
                                </div>

                                <div v-if="hasSignatureVisual" class="space-y-3">
                                    <div v-if="record.signature_url">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature</p>
                                        <div class="signature-surface">
                                            <img :src="record.signature_url" alt="Customer Signature" class="max-h-32 w-auto" />
                                        </div>
                                    </div>
                                    <div v-else-if="Number(record.signature_method) === 5 && record.customer_signature">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature</p>
                                        <div class="signature-surface">
                                            <p class="signature-surface-text signature-cursive text-2xl">
                                                {{ record.customer_signature }}
                                            </p>
                                        </div>
                                    </div>
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
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Status</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-semibold" :class="[statusInfo.bgClass, statusTextClass]">
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <div class="p-5 space-y-3 text-md">
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
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Contract Summary</span>
                    </div>
                    <div class="p-5 space-y-3 text-md">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Payment Status</span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-sm font-semibold" :class="[paymentInfo.bgClass, paymentTextClass]">
                                {{ paymentInfo.name }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Signature Required</span>
                            <span class="text-gray-900 dark:text-white">{{ record.signature_required ? 'Yes' : 'No' }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-600 pt-3">
                            <span class="text-gray-900 dark:text-white">Total</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ formatCurrency(record.total_amount) }}</span>
                        </div>
                        <div class="pt-1 text-md text-gray-400 dark:text-gray-500 text-right">
                            {{ record.currency || 'USD' }}
                        </div>
                    </div>
                </div>

                <!-- Related Records -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Related Records</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <Link v-if="record.transaction_id" :href="route('transactions.show', record.transaction_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">handshake</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Transaction</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white truncate">{{ record.transaction?.display_name || `#${record.transaction_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">chevron_right</span>
                        </Link>
                        <Link v-if="record.estimate_id" :href="route('estimates.show', record.estimate_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">description</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estimate</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white truncate">{{ record.estimate?.display_name || `#${record.estimate_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">chevron_right</span>
                        </Link>
                        <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">person</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Customer</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white truncate">{{ record.customer?.display_name || `#${record.customer_id}` }}</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">chevron_right</span>
                        </Link>
                        <a
                            v-if="signedContractReviewHref"
                            :href="signedContractReviewHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 hover:bg-green-50/50 dark:hover:bg-green-900/10 transition-all group"
                        >
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center group-hover:bg-green-100 dark:group-hover:bg-green-900/30 transition-colors">
                                <span class="material-icons text-green-600 dark:text-green-400 text-xl">draw</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Signed contract</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white">View &amp; print</p>
                            </div>
                            <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-green-500 text-lg transition-colors">open_in_new</span>
                        </a>
                        <div v-if="record.document_url">
                            <a :href="record.document_url" target="_blank"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group">
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                    <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">picture_as_pdf</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Document</p>
                                    <p class="text-md font-medium text-gray-900 dark:text-white">View PDF</p>
                                </div>
                                <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">open_in_new</span>
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
                    :logo-url="logoUrl"
                    @close="closePreview"
                    @request-send="onPreviewRequestSend"
                />
            </div>
        </Teleport>

        <Modal :show="showCreateInvoiceModal" max-width="md" @close="closeCreateInvoiceModal">
            <div class="p-6">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40">
                    <span class="material-icons text-2xl text-primary-600 dark:text-primary-300">receipt_long</span>
                </div>
                <h3 class="text-center text-xl font-semibold text-gray-900 dark:text-white">
                    Contract signed
                </h3>
                <p class="mt-2 text-center text-md text-gray-600 dark:text-gray-400">
                    This contract was signed on
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatDateTime(record.signed_at) }}</span>.
                </p>
                <p class="mt-2 text-center text-md text-gray-600 dark:text-gray-400">
                    Next step: create an invoice from the linked deal to bill your customer.
                </p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link
                        :href="createInvoiceHref"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                        @click="closeCreateInvoiceModal"
                    >
                        <span class="material-icons text-base">add</span>
                        Create invoice
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="closeCreateInvoiceModal"
                    >
                        Not now
                    </button>
                </div>
                <p v-if="record.transaction_id" class="mt-4 text-center">
                    <Link
                        :href="route('transactions.show', record.transaction_id)"
                        class="text-md font-medium text-primary-600 hover:underline dark:text-primary-400"
                        @click="closeCreateInvoiceModal"
                    >
                        View deal instead
                    </Link>
                </p>
            </div>
        </Modal>

        <Modal :show="showSendContractDeliveryModal" max-width="md" @close="closeSendContractDeliveryModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Send contract</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ sendContractModalSubtitle }}
                </p>
                <p
                    v-if="page.props.tenant_sandbox_mode"
                    class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                    <span>Uses your login email for the message and your staff user profile phone for SMS (matched by email).</span>
                </p>
                <p v-if="contractEmailPreview" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="page.props.tenant_sandbox_mode">Email will be sent to you at </template>
                    <template v-else>Email goes to </template>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ contractEmailPreview }}</span>
                </p>
                <p v-if="sendContractForm.errors.delivery" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ sendContractForm.errors.delivery }}
                </p>

                <fieldset class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="contractDelivery" type="radio" name="contract_delivery" value="email" class="mt-1 text-primary-600" />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email only</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">Send the contract for signature by email.</span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border p-3"
                        :class="
                            contractReviewSms.offered
                                ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                                : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                        "
                    >
                        <input
                            v-model="contractDelivery"
                            type="radio"
                            name="contract_delivery"
                            value="email_sms"
                            class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                            :disabled="!contractReviewSms.offered"
                        />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email and SMS</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                                Also send a short text with the review link.
                            </span>
                            <span
                                v-if="!contractReviewSms.offered && contractReviewSms.hint"
                                class="mt-1 block text-sm text-amber-800 dark:text-amber-200"
                            >
                                {{ contractReviewSms.hint }}
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeSendContractDeliveryModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex justify-center items-center gap-2 rounded-lg border border-transparent bg-orange-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-orange-700 disabled:opacity-50"
                        :disabled="sendContractForm.processing"
                        @click="confirmSendContract"
                    >
                        <span v-if="sendContractForm.processing" class="material-icons animate-spin text-base">refresh</span>
                        {{ sendContractForm.processing ? 'Sending…' : 'Send' }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
