<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import InvoicePreview from '@/Components/Tenant/InvoicePreview.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { lineAssetSelectedOptions, selectedOptionLabel } from '@/Utils/lineItemsFromEstimate';

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
    quickbooks: {
        type: Object,
        default: () => ({
            connected: false,
            sync_payments: false,
            sync_invoices: false,
        }),
    },
    invoiceViewSms: {
        type: Object,
        default: () => ({ offered: false, hint: null }),
    },
});

const STATUS_ENUM_KEY       = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const ASSET_TYPE            = 'App\\Domain\\Asset\\Models\\Asset';
const INVENTORY_TYPE        = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';
const INVOICE_ADDON_NAME_SEP = ' — ';

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

/** Stable order for grouping flattened add-on rows under their parent line. */
const sortedLineItems = computed(() => {
    const list = [...lineItems.value];
    return list.sort((a, b) => {
        const pa = Number(a.position);
        const pb = Number(b.position);
        if (!Number.isNaN(pa) && !Number.isNaN(pb) && pa !== pb) {
            return pa - pb;
        }
        return (Number(a.id) || 0) - (Number(b.id) || 0);
    });
});

const isIndentedInvoiceAddonRow = (primary, row) => {
    if (!primary?.name || !row?.name) return false;
    if (row.itemable_type) return false;
    const n = String(row.name);
    return n.startsWith(String(primary.name) + INVOICE_ADDON_NAME_SEP);
};

const inferTaxRateFromItems = (items) => {
    const row = (items || []).find((i) => i.taxable && Number(i.tax_rate) > 0);
    return row != null ? Number(row.tax_rate) : null;
};

const invoiceTaxRatePercent = computed(() => inferTaxRateFromItems(lineItems.value));

const invoiceTaxRateLabel = computed(() => {
    const r = invoiceTaxRatePercent.value;
    if (r == null || r === 0) return '—';
    return `${r}%`;
});

const isLineTaxable = (item) => {
    const t = item?.taxable;
    return t === true || t === 1 || t === '1';
};

const groupedInvoiceLineItems = computed(() => {
    const groups = [];
    for (const row of sortedLineItems.value) {
        const last = groups[groups.length - 1];
        if (last && isIndentedInvoiceAddonRow(last.primary, row)) {
            last.flatAddons.push(row);
        } else {
            groups.push({ primary: row, flatAddons: [] });
        }
    }
    return groups;
});

const flatAddonDisplayName = (primaryName, row) => {
    const prefix = String(primaryName) + INVOICE_ADDON_NAME_SEP;
    const n = String(row.name ?? '');
    return n.startsWith(prefix) ? n.slice(prefix.length) : (row.name ?? '—');
};

/** Boat options: invoice rows created from a deal line include `transaction_line_item`. */
const invoiceLineBoatOptions = (item) => {
    if (item.itemable_type !== ASSET_TYPE) return [];
    const tli = item.transaction_line_item ?? item.transactionLineItem;
    const options = tli ? lineAssetSelectedOptions(tli) : lineAssetSelectedOptions(item);
    const group = groupedInvoiceLineItems.value.find((g) => g.primary.id === item.id);
    if (!group?.flatAddons?.length) {
        return options;
    }

    const persistedChildNames = new Set(
        group.flatAddons.map((row) => flatAddonDisplayName(group.primary.name, row)),
    );

    return options.filter((opt) => !persistedChildNames.has(selectedOptionLabel(opt)));
};

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

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const linePreTaxSubtotal = (item) => {
    if (item?.subtotal != null && item.subtotal !== '') {
        return Number(item.subtotal);
    }
    const qty = Number(item?.quantity ?? 1);
    const unit = Number(item?.unit_price ?? item?.price ?? 0);
    const discount = Number(item?.discount ?? 0);
    return Math.max(0, qty * unit - discount);
};

const optionRowTaxable = (opt) => opt?.taxable !== false && opt?.taxable !== 0 && opt?.taxable !== '0';

const lineTaxRatePercent = (item) => {
    const rowRate = Number(item?.tax_rate);
    if (rowRate > 0) return rowRate;
    return invoiceTaxRatePercent.value ?? 0;
};

const lineTaxAmount = (item) => {
    if (!isLineTaxable(item)) return null;
    if (item?.tax_amount != null && item.tax_amount !== '') {
        return roundMoney(item.tax_amount);
    }
    const rate = lineTaxRatePercent(item);
    if (rate <= 0) return 0;
    return roundMoney(linePreTaxSubtotal(item) * (rate / 100));
};

const taxOnInvoiceBoatOption = (opt) => {
    if (!optionRowTaxable(opt)) return null;
    const rate = Number(opt?.tax_rate) > 0 ? Number(opt.tax_rate) : (invoiceTaxRatePercent.value ?? 0);
    if (rate <= 0) return 0;
    return roundMoney(Number(opt?.price ?? 0) * (rate / 100));
};

const lineTaxCell = (item) => {
    const amt = lineTaxAmount(item);
    return amt == null ? '—' : formatCurrency(amt);
};

const boatOptionTaxCell = (opt) => {
    const amt = taxOnInvoiceBoatOption(opt);
    return amt == null ? '—' : formatCurrency(amt);
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

const billingAddressDisplay = computed(() => {
    const r = props.record;
    const line1 = String(r?.billing_address_line1 ?? '').trim();
    if (line1 !== '') {
        return {
            line1,
            line2: String(r?.billing_address_line2 ?? '').trim() || null,
            cityStatePostal: [r?.billing_city, r?.billing_state, r?.billing_postal].filter(Boolean).join(', ') || null,
            country: String(r?.billing_country ?? '').trim() || null,
        };
    }

    const addr = r?.contact?.primary_address ?? r?.contact?.primaryAddress;
    if (!addr) {
        return null;
    }

    const contactLine1 = String(addr.address_line_1 ?? '').trim();
    if (contactLine1 === '') {
        return null;
    }

    return {
        line1: contactLine1,
        line2: String(addr.address_line_2 ?? '').trim() || null,
        cityStatePostal: [addr.city, addr.state, addr.postal_code].filter(Boolean).join(', ') || null,
        country: String(addr.country ?? '').trim() || null,
    };
});
const transactionShowHref = computed(() => props.record.transaction_id ? route('transactions.show', props.record.transaction_id) : null);
const contractShowHref    = computed(() => props.record.contract_id    ? route('contracts.show',    props.record.contract_id)    : null);
const subsidiaryShowHref  = computed(() => props.record.subsidiary_id  ? route('subsidiaries.show', props.record.subsidiary_id)  : null);
const locationShowHref    = computed(() => props.record.location_id    ? route('locations.show',   props.record.location_id)    : null);

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

// ─── Send to customer (email / email + SMS) ───────────────────────────────────
const showInvoiceDeliveryModal = ref(false);
const invoiceDelivery = ref('email');

const openInvoiceDeliveryModal = () => {
    invoiceDelivery.value = 'email';
    showInvoiceDeliveryModal.value = true;
};

const closeInvoiceDeliveryModal = () => {
    showInvoiceDeliveryModal.value = false;
};

const customerInvoiceEmail = computed(() =>
    props.record?.customer_email ?? props.record?.contact?.email ?? '',
);

const invoiceEmailPreview = computed(() => {
    if (page.props.tenant_sandbox_mode) {
        return page.props.auth?.user?.email ?? '';
    }

    return customerInvoiceEmail.value;
});

const invoiceDeliveryModalSubtitle = computed(() =>
    page.props.tenant_sandbox_mode
        ? 'Sandbox is on: choose how you want to receive the test. Email and SMS go to you, not the customer.'
        : 'Choose how to notify the customer.',
);

const willSyncQuickbooksBeforeSend = computed(() =>
    props.quickbooks?.sync_invoices
    && props.quickbooks?.connected
    && !props.record?.quickbooks_invoice_id,
);

const sendToCustomerForm = useForm({ delivery: 'email' });

const confirmSendToCustomer = () => {
    sendToCustomerForm.delivery = invoiceDelivery.value;
    sendToCustomerForm.post(route('invoices.send-to-customer', props.record.id), {
        preserveScroll: true,
        onSuccess: (p) => {
            const errs = p.props.errors || {};
            if (!errs.delivery && !errs.error) {
                closeInvoiceDeliveryModal();
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
            const d = sendToCustomerForm.errors.delivery;
            if (d) {
                showToast('error', Array.isArray(d) ? d[0] : d);
            }
            const e = sendToCustomerForm.errors.error;
            if (e) {
                showToast('error', Array.isArray(e) ? e[0] : e);
            }
        },
    });
};

// ─── Actions ──────────────────────────────────────────────────────────────────

const pullingQuickbooksPayments = ref(false);

const canPullQuickbooksPayments = computed(() =>
    props.quickbooks?.connected
    && props.quickbooks?.sync_payments
    && props.record?.quickbooks_invoice_id,
);

const canPushToQuickbooks = computed(() =>
    props.quickbooks?.connected
    && !props.record?.quickbooks_invoice_id
    && !props.record?.paid_at
    && props.record?.status !== 'paid'
    && !props.record?.sent_at,
);

const hasQuickbooksModalActions = computed(
    () => canPushToQuickbooks.value
        || !!props.record?.quickbooks_invoice_url
        || canPullQuickbooksPayments.value,
);

const pushingToQuickbooks = ref(false);

const pushToQuickbooks = () => {
    pushingToQuickbooks.value = true;
    router.post(route('invoices.push-to-quickbooks', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            pushingToQuickbooks.value = false;
        },
    });
};

const pullQuickbooksPayments = () => {
    if (!confirm('Import payments recorded in QuickBooks for this invoice?')) return;
    pullingQuickbooksPayments.value = true;
    router.post(route('invoices.pull-quickbooks-payments', props.record.id), {}, {
        onFinish: () => { pullingQuickbooksPayments.value = false; },
    });
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

// ─── Header actions modal (below lg) & delete ─────────────────────────────────
const showActionsModal = ref(false);

const closeActionsModal = () => {
    showActionsModal.value = false;
};

const isQuickbooksSynced = computed(() => !!props.record?.quickbooks_invoice_id);

const showDeleteModal = ref(false);
const removalDisposition = ref('delete');
const syncQuickbooksRemoval = ref(false);
const quickbooksRemovalOperation = ref('auto');
const deletingInvoice = ref(false);

const openDeleteModal = () => {
    closeActionsModal();
    removalDisposition.value = 'delete';
    syncQuickbooksRemoval.value = false;
    quickbooksRemovalOperation.value = 'auto';
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    if (deletingInvoice.value) return;
    showDeleteModal.value = false;
};

const confirmRemoveInvoice = () => {
    deletingInvoice.value = true;
    router.post(route('invoices.remove', props.record.id), {
        disposition: removalDisposition.value,
        quickbooks: syncQuickbooksRemoval.value,
        quickbooks_operation: quickbooksRemovalOperation.value,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            closeDeleteModal();
            const msg = page.props.flash?.success;
            if (msg) showToast('success', msg);
        },
        onError: (errors) => {
            const err = errors.error ?? errors.message;
            if (err) {
                showToast('error', Array.isArray(err) ? err[0] : err);
            }
        },
        onFinish: () => {
            deletingInvoice.value = false;
        },
    });
};

const removalSubmitLabel = computed(() => {
    if (deletingInvoice.value) {
        return removalDisposition.value === 'void' ? 'Voiding…' : 'Deleting…';
    }

    return removalDisposition.value === 'void' ? 'Void invoice' : 'Delete invoice';
});

const runHeaderAction = (fn) => {
    closeActionsModal();
    fn();
};
</script>

<template>
    <Head :title="invoiceLabel" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 md:gap-3">
                        <h2 class="truncate text-lg font-semibold leading-tight text-gray-800 md:text-xl dark:text-gray-200">
                            {{ invoiceLabel }}
                        </h2>
                        <span
                            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold md:px-2.5 md:py-1 md:text-sm"
                            :class="[statusInfo.bgClass, statusTextClass]"
                        >
                            {{ statusInfo.name }}
                        </span>
                    </div>
                    <!-- Large screens: primary actions (Edit + QuickBooks live outside / in gear) -->
                    <div class="hidden shrink-0 flex-wrap items-center justify-end gap-2 lg:flex">
                        <button
                            type="button"
                            aria-label="Customer preview"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            @click="previewOpen = true"
                        >
                            <span class="material-icons text-[16px]">visibility</span>
                            Customer preview
                        </button>
                        <button
                            type="button"
                            aria-label="Send invoice to customer"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-secondary-600 px-4 py-2 text-md font-medium text-white transition-colors hover:bg-secondary-700 disabled:opacity-50"
                            :disabled="sendToCustomerForm.processing"
                            @click="openInvoiceDeliveryModal"
                        >
                            <span class="material-icons text-[16px]">send</span>
                            {{ sendToCustomerForm.processing ? 'Sending…' : (record.sent_at ? 'Resend to customer' : 'Send to customer') }}
                        </button>
                        <button
                            v-if="canRecordManualPayment"
                            type="button"
                            aria-label="Record payment"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-100 px-4 py-2 text-md font-medium text-gray-800 transition-colors hover:bg-amber-200 dark:border-amber-700 dark:bg-amber-900/40 dark:text-gray-100 dark:hover:bg-amber-900/60"
                            @click="openRecordPaymentModal"
                        >
                            <span class="material-icons text-[16px]">payments</span>
                            Record payment
                        </button>
                    </div>

                    <!-- Edit: always visible -->
                    <Link
                        :href="route('invoices.edit', record.id)"
                        aria-label="Edit invoice"
                        class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary-600 p-2.5 text-md font-medium text-white transition-colors hover:bg-primary-700 lg:px-4 lg:py-2"
                    >
                        <span class="material-icons text-xl leading-none lg:text-[16px]">edit</span>
                        <span class="hidden lg:inline">Edit</span>
                    </Link>

                    <!-- Gear + delete (large screens) -->
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            aria-label="Invoice actions"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2.5 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            @click="showActionsModal = true"
                        >
                            <span class="material-icons text-xl leading-none">settings</span>
                        </button>
                        <button
                            type="button"
                            aria-label="Delete invoice"
                            class="hidden items-center justify-center rounded-lg border border-gray-300 bg-white p-2.5 text-red-600 transition-colors hover:border-red-300 hover:bg-red-50 lg:inline-flex dark:border-gray-600 dark:bg-gray-700 dark:text-red-400 dark:hover:border-red-800 dark:hover:bg-red-900/30"
                            @click="openDeleteModal"
                        >
                            <span class="material-icons text-xl leading-none">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Teleport to="body">
            <div
                v-if="pushingToQuickbooks"
                class="fixed inset-0 z-[110] flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm dark:bg-gray-950/70"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-8 text-center shadow-xl dark:bg-gray-800">
                    <span class="material-icons animate-spin text-5xl text-primary-600 dark:text-primary-400">sync</span>
                    <p class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Syncing with QuickBooks</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Please wait while your invoice is sent to QuickBooks…</p>
                </div>
            </div>

            <div v-if="previewOpen" class="invoice-preview-overlay fixed inset-0 z-[100] overflow-y-auto">
                <InvoicePreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    :logo-url="logoUrl"
                    @close="previewOpen = false"
                    @request-send="openInvoiceDeliveryModal"
                />
            </div>
        </Teleport>

        <div class="w-full flex flex-col space-y-6 ">

            <!-- Paid / Overdue banner -->
            <div v-if="isPaid"
                 class="flex items-center gap-3 px-5 py-3.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                <span class="material-icons text-green-500 dark:text-green-400">check_circle</span>
                <p class="text-md font-medium text-green-800 dark:text-green-200">
                    Invoice paid on <span class="font-semibold">{{ formatDate(record.paid_at) }}</span>.
                </p>
            </div>
            <div v-else-if="isOverdue"
                 class="flex items-center gap-3 px-5 py-3.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                <span class="material-icons text-red-500 dark:text-red-400">warning</span>
                <p class="text-md font-medium text-red-800 dark:text-red-200">
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
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold"
                                              :class="[statusInfo.bgClass, statusTextClass]">
                                            {{ statusInfo.name }}
                                        </span>
                                    </div>
                                    <p class="text-primary-100 text-md">Customer invoice details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-sm font-medium">Reference</div>
                                    <div class="text-white text-xl font-mono">{{ invoiceLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-8">
                            <section class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Customer &amp; Billing
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="record.customer_name">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</div>
                                        <Link v-if="contactShowHref" :href="contactShowHref"
                                              class="text-md font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ record.customer_name }}
                                        </Link>
                                        <div v-else class="text-md font-medium text-gray-900 dark:text-white">{{ record.customer_name }}</div>
                                    </div>

                                    <div v-if="record.customer_email">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</div>
                                        <a :href="`mailto:${record.customer_email}`"
                                           class="text-md text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ record.customer_email }}
                                        </a>
                                    </div>

                                    <div class="">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Billing Address</div>
                                        <div v-if="billingAddressDisplay" class="text-md text-gray-900 dark:text-white space-y-0.5">
                                            <div>{{ billingAddressDisplay.line1 }}</div>
                                            <div v-if="billingAddressDisplay.line2">{{ billingAddressDisplay.line2 }}</div>
                                            <div v-if="billingAddressDisplay.cityStatePostal">{{ billingAddressDisplay.cityStatePostal }}</div>
                                            <div v-if="billingAddressDisplay.country" class="text-gray-500 dark:text-gray-400">{{ billingAddressDisplay.country }}</div>
                                        </div>
                                        <div v-else class="text-md text-gray-500 dark:text-gray-400">—</div>
                                    </div>

                                    <div v-if="record.customer_phone">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Phone</div>
                                        <div class="text-md text-gray-900 dark:text-white">{{ formatPhoneNumber(record.customer_phone) || '—' }}</div>
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Invoice Details
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Invoice Date</div>
                                        <div class="text-md text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</div>
                                    </div>

                                    <div v-if="record.due_at">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Due Date</div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <div class="text-md text-gray-900 dark:text-white">{{ formatDate(record.due_at) }}</div>
                                            <span v-if="isOverdue"
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                                Overdue
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Payment Terms</div>
                                        <div class="text-md text-gray-900 dark:text-white">{{ paymentTermLabel }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Currency</div>
                                        <div class="text-md text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Tax Rate</div>
                                        <div class="text-md text-gray-900 dark:text-white">{{ invoiceTaxRateLabel }}</div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="record.notes"
                                 class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</div>
                                <div class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.notes }}</div>
                            </section>
                        </div>
                    </div>

                    <!-- Line items -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-md font-semibold text-gray-900 dark:text-white">Line Items</h2>
                        </div>

                        <div v-if="lineItems.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <!-- Mobile: stacked line cards -->
                            <div class="block lg:hidden">
                                <div
                                    v-for="(group, gIdx) in groupedInvoiceLineItems"
                                    :key="`inv-m-${group.primary.id}-${gIdx}`"
                                    class="p-4 space-y-3"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-base text-gray-900 dark:text-white">{{ group.primary.name ?? '—' }}</span>
                                                <span v-if="itemableBadge(group.primary)"
                                                      class="rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                    {{ itemableBadge(group.primary) }}
                                                </span>
                                            </div>
                                            <div v-if="variantLabel(group.primary)" class="text-sm text-gray-500 dark:text-gray-400">
                                                Variant: {{ variantLabel(group.primary) }}
                                            </div>
                                            <div v-if="unitLabel(group.primary)" class="text-sm text-gray-500 dark:text-gray-400">
                                                Unit: {{ unitLabel(group.primary) }}
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-semibold text-base text-gray-900 dark:text-white tabular-nums">
                                                {{ formatCurrency(group.primary.total ?? group.primary.line_total) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Line total</div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Qty</div>
                                            <div class="text-gray-900 dark:text-white">{{ group.primary.quantity ?? 1 }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Unit price</div>
                                            <div class="text-gray-900 dark:text-white tabular-nums">{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Discount</div>
                                            <div class="text-gray-900 dark:text-white tabular-nums">{{ discountCell(group.primary) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Taxable</div>
                                            <div class="flex items-center pt-0.5">
                                                <span
                                                    v-if="isLineTaxable(group.primary)"
                                                    class="material-icons text-blue-600 dark:text-blue-400 text-[22px]"
                                                    title="Taxable"
                                                >check_box</span>
                                                <span v-else class="text-gray-400 dark:text-gray-500 text-sm">—</span>
                                            </div>
                                        </div>
                                        <div v-if="isLineTaxable(group.primary)">
                                            <div class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Tax</div>
                                            <div class="text-gray-900 dark:text-white tabular-nums">{{ lineTaxCell(group.primary) }}</div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="invoiceLineBoatOptions(group.primary).length > 0"
                                        class="pl-3 space-y-2 border-l-2 border-sky-200 dark:border-sky-700"
                                    >
                                        <div
                                            v-for="(opt, optIdx) in invoiceLineBoatOptions(group.primary)"
                                            :key="`m-opt-${group.primary.id}-${optIdx}`"
                                            class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            <span><span class="text-sky-600/90 dark:text-sky-400 mr-1">↳</span>{{ selectedOptionLabel(opt) }}</span>
                                            <div class="text-right shrink-0 tabular-nums">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(opt.price) }}</div>
                                                <div v-if="optionRowTaxable(opt)" class="text-xs text-gray-500 dark:text-gray-400">
                                                    Tax {{ boatOptionTaxCell(opt) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="group.flatAddons.length > 0"
                                        class="pl-3 space-y-2 border-l-2 border-primary-200 dark:border-primary-700"
                                    >
                                        <div
                                            v-for="(add, addIdx) in group.flatAddons"
                                            :key="`m-ad-${add.id}-${addIdx}`"
                                            class="flex flex-wrap items-center justify-between gap-2 text-sm"
                                        >
                                            <span class="text-gray-600 dark:text-gray-400 italic min-w-0">
                                                ↳ {{ flatAddonDisplayName(group.primary.name, add) }}
                                            </span>
                                            <div class="text-right shrink-0 tabular-nums">
                                                <div class="font-medium text-gray-900 dark:text-white">
                                                    {{ formatCurrency(add.total ?? add.line_total) }}
                                                </div>
                                                <div v-if="isLineTaxable(add)" class="text-xs text-gray-500 dark:text-gray-400">
                                                    Tax {{ lineTaxCell(add) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop: wide table -->
                            <div class="hidden lg:block overflow-x-auto">
                            <table class="w-full text-md min-w-[640px]">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-5 py-3 text-center text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-16">Taxable</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Tax</th>
                                        <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <template v-for="(group, gIdx) in groupedInvoiceLineItems" :key="`grp-${group.primary.id}-${gIdx}`">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-5 py-3.5 align-top">
                                                <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ group.primary.name ?? '—' }}</span>
                                                    <span v-if="itemableBadge(group.primary)"
                                                          class="rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-sm font-medium text-gray-600 dark:text-gray-300">
                                                        {{ itemableBadge(group.primary) }}
                                                    </span>
                                                </div>
                                                <div v-if="variantLabel(group.primary)" class="text-sm text-gray-500 dark:text-gray-400">
                                                    Variant: {{ variantLabel(group.primary) }}
                                                </div>
                                                <div v-if="unitLabel(group.primary)" class="text-sm text-gray-500 dark:text-gray-400">
                                                    Unit: {{ unitLabel(group.primary) }}
                                                </div>
                                            </td>
                                            <td class="px-5 py-3.5 text-right align-top text-gray-700 dark:text-gray-300">{{ group.primary.quantity ?? 1 }}</td>
                                            <td class="px-5 py-3.5 text-right align-top text-gray-700 dark:text-gray-300">{{ formatCurrency(group.primary.unit_price ?? group.primary.price) }}</td>
                                            <td class="px-5 py-3.5 text-right align-top text-gray-500 dark:text-gray-400">{{ discountCell(group.primary) }}</td>
                                            <td class="px-5 py-3.5 text-center align-top">
                                                <span
                                                    v-if="isLineTaxable(group.primary)"
                                                    class="material-icons text-blue-600 dark:text-blue-400 text-[22px] inline-block"
                                                    title="Taxable"
                                                >check_box</span>
                                            </td>
                                            <td class="px-5 py-3.5 text-right align-top text-gray-600 dark:text-gray-300 tabular-nums">{{ lineTaxCell(group.primary) }}</td>
                                            <td class="px-5 py-3.5 text-right align-top font-semibold text-gray-900 dark:text-white">{{ formatCurrency(group.primary.total ?? group.primary.line_total) }}</td>
                                        </tr>
                                        <tr
                                            v-for="(opt, optIdx) in invoiceLineBoatOptions(group.primary)"
                                            :key="`d-opt-${group.primary.id}-${optIdx}`"
                                            class="bg-sky-50/40 dark:bg-sky-900/10"
                                        >
                                            <td class="pl-10 pr-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 italic">
                                                ↳ {{ selectedOptionLabel(opt) }}
                                            </td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-500 dark:text-gray-400">1</td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatCurrency(opt.price) }}</td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-400 dark:text-gray-500">—</td>
                                            <td class="px-5 py-2.5 text-center text-sm text-gray-400 dark:text-gray-500">—</td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-600 dark:text-gray-300 tabular-nums">{{ boatOptionTaxCell(opt) }}</td>
                                            <td class="px-5 py-2.5 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatCurrency(opt.price) }}</td>
                                        </tr>
                                        <tr
                                            v-for="(add, addIdx) in group.flatAddons"
                                            :key="`d-ad-${add.id}-${addIdx}`"
                                            class="bg-blue-50/30 dark:bg-blue-900/10"
                                        >
                                            <td class="pl-10 pr-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 italic">
                                                ↳ {{ flatAddonDisplayName(group.primary.name, add) }}
                                            </td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-600 dark:text-gray-300">{{ add.quantity ?? 1 }}</td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-600 dark:text-gray-300">{{ formatCurrency(add.unit_price ?? add.price) }}</td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-500 dark:text-gray-400">{{ discountCell(add) }}</td>
                                            <td class="px-5 py-2.5 text-center align-top">
                                                <span
                                                    v-if="isLineTaxable(add)"
                                                    class="material-icons text-blue-600 dark:text-blue-400 text-[20px] inline-block"
                                                    title="Taxable"
                                                >check_box</span>
                                            </td>
                                            <td class="px-5 py-2.5 text-right text-sm text-gray-600 dark:text-gray-300 tabular-nums">{{ lineTaxCell(add) }}</td>
                                            <td class="px-5 py-2.5 text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ formatCurrency(add.total ?? add.line_total) }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr v-if="record.discount_total && parseFloat(record.discount_total) !== 0">
                                        <td colspan="6" class="px-5 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Discount</td>
                                        <td class="px-5 py-2 text-right text-sm font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.discount_total) }}</td>
                                    </tr>
                                    <tr v-if="record.tax_total">
                                        <td colspan="6" class="px-5 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Tax</td>
                                        <td class="px-5 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(record.tax_total) }}</td>
                                    </tr>
                                    <tr v-if="record.fees_total && parseFloat(record.fees_total) !== 0">
                                        <td colspan="6" class="px-5 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Fees</td>
                                        <td class="px-5 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(record.fees_total) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="px-5 py-3 text-right text-md font-semibold text-gray-700 dark:text-gray-300">Total</td>
                                        <td class="px-5 py-3 text-right text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(record.total) }}</td>
                                    </tr>
                                    <tr v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0">
                                        <td colspan="6" class="px-5 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Amount paid</td>
                                        <td class="px-5 py-2 text-right text-sm font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.amount_paid) }}</td>
                                    </tr>
                                    <tr v-if="record.amount_due != null">
                                        <td colspan="6" class="px-5 py-3 text-right text-md font-bold text-gray-900 dark:text-white">Amount Due</td>
                                        <td class="px-5 py-3 text-right text-lg font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(record.amount_due) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <span class="material-icons text-[40px] text-gray-300 dark:text-gray-600 mb-3">receipt_long</span>
                            <p class="text-md text-gray-400 dark:text-gray-500">No line items on this invoice</p>
                        </div>
                    </div>

                </div>

                <!-- ── Sidebar ────────────────────────────────────────────── -->
                <div class="lg:col-span-4 space-y-4">

                    <!-- Actions -->
                    <!-- <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</span>
                        </div>
                        <div class="p-4 space-y-2">
                            <button type="button"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                    @click="previewOpen = true">
                                <span class="material-icons text-[16px]">visibility</span>
                                Customer preview
                            </button>
                            <button type="button"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                                    @click="sendToCustomer">
                                <span class="material-icons text-[16px]">send</span>
                                Send to customer
                            </button>
                            <a :href="route('invoices.edit', record.id)"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                                <span class="material-icons text-[16px]">edit</span>
                                Edit invoice
                            </a>
                        </div>
                    </div> -->

                    <!-- Invoice Total -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Total</span>
                        </div>
                        <div class="p-4 space-y-2.5 text-md">
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
                                <span class="text-lg font-bold text-gray-900 dark:text-white">Total</span>
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
                                <span class="text-lg font-bold" :class="isPaid ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white'">
                                    {{ formatCurrency(record.amount_due) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-md">
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">credit_card</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Terms</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ paymentTermLabel }}</span>
                            </li>
                            <li class="flex items-start gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0 mt-0.5">account_balance</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-gray-500 dark:text-gray-400">Accepted methods</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white mt-0.5">{{ acceptedPaymentMethodsText }}</p>
                                </div>
                            </li>
                            <li v-if="surchargePercentText" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">percent</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Surcharge</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ surchargePercentText }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">pie_chart</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Partial payments</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ partialPaymentSummary.allowed ? 'Allowed' : 'Not allowed' }}
                                    <template v-if="partialPaymentSummary.allowed && partialPaymentSummary.minimumText">
                                        (min. {{ partialPaymentSummary.minimumText }})
                                    </template>
                                </span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">payments</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Currency</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Invoice Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Info</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-md">
                            <li v-if="contactShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">person</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Contact</span>
                                <Link :href="contactShowHref" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li v-if="transactionShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">handshake</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Deal</span>
                                <Link :href="transactionShowHref" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li v-if="contractShowHref" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">description</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Contract</span>
                                <Link :href="contractShowHref" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                            </li>
                            <li v-if="subsidiaryShowHref" class="flex items-start gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0 mt-0.5">corporate_fare</span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-gray-500 dark:text-gray-400">Subsidiary</span>
                                    <p
                                        v-if="record.subsidiary?.display_name"
                                        class="text-sm font-medium text-gray-900 dark:text-white mt-0.5 truncate"
                                    >
                                        {{ record.subsidiary.display_name }}
                                    </p>
                                </div>
                                <Link :href="subsidiaryShowHref" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline shrink-0">View</Link>
                            </li>
                            <li v-if="locationShowHref" class="flex items-start gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0 mt-0.5">place</span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-gray-500 dark:text-gray-400">Location</span>
                                    <p
                                        v-if="record.location?.display_name"
                                        class="text-sm font-medium text-gray-900 dark:text-white mt-0.5 truncate"
                                    >
                                        {{ record.location.display_name }}
                                    </p>
                                </div>
                                <Link :href="locationShowHref" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline shrink-0">View</Link>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">calendar_today</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">update</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.updated_at) }}</span>
                            </li>
                            <li v-if="record.sent_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">send</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Sent</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.sent_at) }}</span>
                            </li>
                            <li v-if="record.viewed_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400 shrink-0">visibility</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Viewed</span>
                                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.viewed_at) }}</span>
                            </li>
                            <li v-if="record.paid_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-green-500 shrink-0">check_circle</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Paid</span>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">{{ formatDate(record.paid_at) }}</span>
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
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Created</p>
                                        <time class="text-[11px] text-gray-400">{{ formatDate(record.created_at) }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.sent_at ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Sent</p>
                                        <time class="text-[11px] text-gray-400">{{ record.sent_at ? formatDate(record.sent_at) : 'Pending' }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.viewed_at ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Viewed</p>
                                        <time class="text-[11px] text-gray-400">{{ record.viewed_at ? formatDate(record.viewed_at) : 'Not yet' }}</time>
                                    </div>
                                </li>
                                <li class="ms-5">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[16px]" :class="record.paid_at ? 'text-green-600' : 'text-gray-300 dark:text-gray-600'">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Paid</p>
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
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Record manual payment</h3>
                <p class="text-md text-gray-500 dark:text-gray-400 mb-5">
                    Apply check, cash, wire, or ACH to this invoice. The customer balance and invoice status will update.
                </p>
                <form class="space-y-4" @submit.prevent="submitManualPayment">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Amount</label>
                        <input v-model="paymentForm.amount"
                               type="text"
                               inputmode="decimal"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-md"
                               required>
                        <p v-if="paymentForm.errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ paymentForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Method</label>
                        <select v-model="paymentForm.payment_method_code"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-md">
                            <option v-for="opt in manualMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reference # <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <input v-model="paymentForm.reference_number"
                               type="text"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-md"
                               placeholder="Check number, confirmation, etc.">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Memo <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <textarea v-model="paymentForm.memo"
                                  rows="2"
                                  class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-md"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                class="px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                                @click="showRecordPaymentModal = false">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50"
                                :disabled="paymentForm.processing">
                            {{ paymentForm.processing ? 'Saving…' : 'Record payment' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Send to customer: email vs email + SMS -->
        <Modal :show="showInvoiceDeliveryModal" max-width="md" @close="closeInvoiceDeliveryModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ record.sent_at ? 'Resend invoice to customer' : 'Send invoice to customer' }}
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ invoiceDeliveryModalSubtitle }}
                </p>
                <p
                    v-if="page.props.tenant_sandbox_mode"
                    class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                    <span>Uses your login email for the message and your staff user profile phone for SMS (matched by email).</span>
                </p>
                <p
                    v-if="willSyncQuickbooksBeforeSend"
                    class="mt-2 flex gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-950 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                >
                    <span class="material-icons shrink-0 text-base text-blue-600 dark:text-blue-400" aria-hidden="true">sync</span>
                    <span>This invoice will be synced to QuickBooks before it is sent to the customer.</span>
                </p>
                <p v-if="invoiceEmailPreview" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="page.props.tenant_sandbox_mode">Email will be sent to you at </template>
                    <template v-else>Email goes to </template>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ invoiceEmailPreview }}</span>
                </p>
                <p v-if="sendToCustomerForm.errors.delivery" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ sendToCustomerForm.errors.delivery }}
                </p>
                <p v-if="sendToCustomerForm.errors.error" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ sendToCustomerForm.errors.error }}
                </p>

                <fieldset class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="invoiceDelivery" type="radio" name="invoice_delivery" value="email" class="mt-1 text-primary-600" />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email only</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">Send the invoice link by email.</span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border p-3"
                        :class="
                            invoiceViewSms.offered
                                ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                                : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                        "
                    >
                        <input
                            v-model="invoiceDelivery"
                            type="radio"
                            name="invoice_delivery"
                            value="email_sms"
                            class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                            :disabled="!invoiceViewSms.offered"
                        />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email and SMS</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                                Also send a short text with the invoice link.
                            </span>
                            <span
                                v-if="!invoiceViewSms.offered && invoiceViewSms.hint"
                                class="mt-1 block text-sm text-amber-800 dark:text-amber-200"
                            >
                                {{ invoiceViewSms.hint }}
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        :disabled="sendToCustomerForm.processing"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeInvoiceDeliveryModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="sendToCustomerForm.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-secondary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-700 disabled:opacity-50"
                        @click="confirmSendToCustomer"
                    >
                        <span v-if="sendToCustomerForm.processing" class="material-icons animate-spin text-base">refresh</span>
                        {{ sendToCustomerForm.processing ? 'Sending…' : 'Send' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Invoice actions (below lg) -->
        <Modal :show="showActionsModal" max-width="sm" @close="closeActionsModal">
            <div class="p-6">
                <h3 class="text-center text-lg font-semibold text-gray-900 dark:text-white">
                    Invoice actions
                </h3>
                <p class="mt-1 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ invoiceLabel }}
                </p>
                <div v-if="hasQuickbooksModalActions" class="mt-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        QuickBooks Actions
                    </h4>
                    <div class="mt-2 space-y-1">
                        <button
                            v-if="canPushToQuickbooks"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="pushingToQuickbooks"
                            @click="runHeaderAction(pushToQuickbooks)"
                        >
                            <span class="material-icons text-[20px] text-gray-400">cloud_upload</span>
                            {{ pushingToQuickbooks ? 'Syncing…' : 'Sync with QuickBooks' }}
                        </button>
                        <a
                            v-if="record.quickbooks_invoice_url"
                            :href="record.quickbooks_invoice_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="closeActionsModal"
                        >
                            <span class="material-icons text-[20px] text-gray-400">open_in_new</span>
                            Open in QuickBooks
                        </a>
                        <button
                            v-if="canPullQuickbooksPayments"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="pullingQuickbooksPayments"
                            @click="runHeaderAction(pullQuickbooksPayments)"
                        >
                            <span class="material-icons text-[20px] text-gray-400">sync</span>
                            {{ pullingQuickbooksPayments ? 'Pulling…' : 'Pull QBO payments' }}
                        </button>
                    </div>
                </div>

                <div class="mt-5 lg:hidden">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        General Actions
                    </h4>
                    <div class="mt-2 space-y-1">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="runHeaderAction(() => { previewOpen = true; })"
                        >
                            <span class="material-icons text-[20px] text-gray-400">visibility</span>
                            Customer preview
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="sendToCustomerForm.processing"
                            @click="runHeaderAction(openInvoiceDeliveryModal)"
                        >
                            <span class="material-icons text-[20px] text-gray-400">send</span>
                            {{ record.sent_at ? 'Resend to customer' : 'Send to customer' }}
                        </button>
                        <button
                            v-if="canRecordManualPayment"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="runHeaderAction(openRecordPaymentModal)"
                        >
                            <span class="material-icons text-[20px] text-gray-400">payments</span>
                            Record payment
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            @click="openDeleteModal"
                        >
                            <span class="material-icons text-[20px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="closeActionsModal"
                >
                    Cancel
                </button>
            </div>
        </Modal>

        <!-- Remove / void invoice -->
        <Modal :show="showDeleteModal" max-width="md" @close="closeDeleteModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Remove invoice</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Choose how to handle <span class="font-medium text-gray-900 dark:text-white">{{ invoiceLabel }}</span>.
                </p>

                <fieldset class="mt-4 space-y-2">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        In Helmful
                    </legend>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="removalDisposition" type="radio" name="removal_disposition" value="delete" class="mt-1 text-primary-600" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-medium text-gray-900 dark:text-white">Delete</span>
                            <span class="mt-0.5 block text-gray-500 dark:text-gray-400">Remove from your invoice list (can be restored from the database).</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="removalDisposition" type="radio" name="removal_disposition" value="void" class="mt-1 text-primary-600" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-medium text-gray-900 dark:text-white">Void</span>
                            <span class="mt-0.5 block text-gray-500 dark:text-gray-400">Keep the record but mark it void and zero the balance due.</span>
                        </span>
                    </label>
                </fieldset>

                <div v-if="isQuickbooksSynced" class="mt-4 space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="syncQuickbooksRemoval"
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-primary-600 dark:border-gray-600"
                        />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Also update in QuickBooks</span>
                    </label>
                    <fieldset v-if="syncQuickbooksRemoval" class="space-y-2 pl-7">
                        <legend class="sr-only">QuickBooks action</legend>
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="quickbooksRemovalOperation" type="radio" name="qbo_removal" value="auto" class="mt-0.5 text-primary-600" />
                            <span>
                                <span class="font-medium">Auto</span>
                                <span class="block text-gray-500 dark:text-gray-400">Try delete, then void if QuickBooks requires it.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="quickbooksRemovalOperation" type="radio" name="qbo_removal" value="void" class="mt-0.5 text-primary-600" />
                            <span>
                                <span class="font-medium">Void in QuickBooks</span>
                                <span class="block text-gray-500 dark:text-gray-400">For sent or recorded invoices.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="quickbooksRemovalOperation" type="radio" name="qbo_removal" value="delete" class="mt-0.5 text-primary-600" />
                            <span>
                                <span class="font-medium">Delete in QuickBooks</span>
                                <span class="block text-gray-500 dark:text-gray-400">For unused or draft invoices only.</span>
                            </span>
                        </label>
                    </fieldset>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="deletingInvoice"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-50"
                        :class="removalDisposition === 'void' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-red-600 hover:bg-red-700'"
                        :disabled="deletingInvoice"
                        @click="confirmRemoveInvoice"
                    >
                        <span v-if="deletingInvoice" class="material-icons animate-spin text-base">refresh</span>
                        {{ removalSubmitLabel }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
