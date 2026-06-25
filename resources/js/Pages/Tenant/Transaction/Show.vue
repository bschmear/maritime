<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import {
    lineAssetSelectedOptions,
    lineItemPreTaxTotal,
    resolveLineItemsForTransaction,
    taxRateForResolvedLines,
} from '@/Utils/lineItemsFromEstimate';
import ResolvedLineItemsEstimateStyle from '@/Components/Tenant/ResolvedLineItemsEstimateStyle.vue';
import TransactionAssetUnitStatusModal from '@/Components/Tenant/TransactionAssetUnitStatusModal.vue';
import {
    buildAssetUnitStatusDraft,
    collectAssetUnitsFromLineItems,
} from '@/Utils/transactionAssetUnits';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const statusEnumKey = 'App\\Enums\\Transaction\\TransactionStatus';
const invoiceStatusEnumKey = 'App\\Enums\\Invoice\\Status';
const deliveryStatusEnumKey = 'App\\Enums\\Deliveries\\Status';
const unitStatusOptions = computed(() => props.enumOptions.asset_unit_status ?? []);
const transactionStatusOptions = computed(() => props.enumOptions[statusEnumKey] ?? []);

const createInvoiceHref = computed(
    () => route('invoices.create') + `?transaction_id=${props.record.id}&contact_id=${props.record.customer?.contact_id || ''}`,
);

const transactionInvoices = computed(() => props.record.invoices ?? []);
const transactionDeliveries = computed(() => props.record.deliveries ?? []);

const resolvedTransactionStatus = computed(() => {
    const raw = props.record.status;
    const opts = props.enumOptions[statusEnumKey] || [];
    const opt = opts.find((o) => o.value === raw || o.id === raw || String(o.id) === String(raw));
    const value = opt?.value ?? (typeof raw === 'string' ? raw : null);
    return { raw, value, opt };
});

/** Matches App\Enums\Transaction\TransactionStatus::id() */
const TRANSACTION_STATUS_ID = { pending: 1, processing: 2, completed: 3, failed: 4, cancelled: 5 };

const isCompleted = computed(() => {
    const { value, raw } = resolvedTransactionStatus.value;
    if (value === 'completed' || value === 'won' || raw === 'won' || raw === 'completed') {
        return true;
    }
    const n = Number(raw);
    return Number.isFinite(n) && n === TRANSACTION_STATUS_ID.completed;
});

/** Completed deals cannot be deleted or have new contracts/invoices added from this page. */
const canModifyDealStructure = computed(() => !isCompleted.value);

const contractBlocksInvoice = computed(() => {
    if (!props.record.needs_contract) return false;
    const contract = props.record.contract;
    if (!contract?.id) return true;
    return contract.status !== 'signed';
});

const createInvoiceBlockMessage = computed(() => {
    if (!props.record.needs_contract) return null;
    const contract = props.record.contract;
    if (!contract?.id) {
        return 'Needs Contract is selected. Create and sign a contract before creating an invoice, or turn off Needs Contract on the deal.';
    }
    if (contract.status !== 'signed') {
        return 'Needs Contract is selected. The contract must be signed before you can create an invoice, or turn off Needs Contract on the deal.';
    }
    return null;
});

const canCreateInvoice = computed(() => canModifyDealStructure.value && !contractBlocksInvoice.value);

const isFailed = computed(() => {
    if (isCompleted.value) {
        return false;
    }
    const { value, raw } = resolvedTransactionStatus.value;
    if (value === 'failed' || value === 'lost' || raw === 'lost' || raw === 'failed') {
        return true;
    }
    const n = Number(raw);
    return Number.isFinite(n) && n === TRANSACTION_STATUS_ID.failed;
});

const isCancelled = computed(() => {
    const { value, raw } = resolvedTransactionStatus.value;
    if (value === 'cancelled' || raw === 'cancelled') {
        return true;
    }
    const n = Number(raw);
    return Number.isFinite(n) && n === TRANSACTION_STATUS_ID.cancelled;
});

const canMarkDealComplete = computed(() => {
    if (isCompleted.value || isFailed.value || isCancelled.value) return false;
    if (props.record.needs_contract && props.record.contract?.status !== 'signed') return false;
    if (props.record.needs_delivery) {
        if (!transactionDeliveries.value.some((d) => d.status === 'delivered')) return false;
    }
    if (!transactionInvoices.value.some((inv) => inv.status && !['draft', 'void'].includes(inv.status))) return false;
    const tickets = props.record.service_tickets ?? [];
    if (tickets.length && !tickets.every((t) => [4, 5].includes(Number(t.status)))) return false;
    return true;
});

const showAssetUnitStatusModal = ref(false);
const assetUnitStatusRows = ref([]);
const completingDeal = ref(false);
const pendingTerminalStatus = ref(null);
const pendingTerminalStatusId = ref(TRANSACTION_STATUS_ID.completed);

const csrfToken = () => {
    const fromPage = page.props?.csrf_token;
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return fromPage ?? meta;
};

const patchTransactionStatus = (statusValue, assetUnitStatuses = null) => {
    completingDeal.value = true;
    const payload = { status: statusValue };
    if (assetUnitStatuses?.length) {
        payload.asset_unit_statuses = assetUnitStatuses;
    }
    const token = csrfToken();
    router.patch(route('transactions.update', props.record.id), payload, {
        preserveScroll: true,
        headers: token ? { 'X-CSRF-TOKEN': String(token) } : {},
        onFinish: () => {
            completingDeal.value = false;
        },
        onError: (errors) => {
            const msg = errors?.status;
            const text = Array.isArray(msg) ? msg[0] : msg ?? Object.values(errors)[0];
            const s = Array.isArray(text) ? text[0] : text;
            if (s) window.alert(s);
        },
    });
};

const beginTerminalStatusChange = (statusValue, statusId) => {
    const units = collectAssetUnitsFromLineItems(items.value);
    if (!units.length) {
        patchTransactionStatus(statusValue);
        return;
    }
    pendingTerminalStatus.value = statusValue;
    pendingTerminalStatusId.value = statusId;
    assetUnitStatusRows.value = buildAssetUnitStatusDraft(units, statusId);
    showAssetUnitStatusModal.value = true;
};

const closeAssetUnitStatusModal = () => {
    showAssetUnitStatusModal.value = false;
    pendingTerminalStatus.value = null;
    pendingTerminalStatusId.value = TRANSACTION_STATUS_ID.completed;
};

const confirmAssetUnitStatuses = (statuses) => {
    const statusValue = pendingTerminalStatus.value;
    showAssetUnitStatusModal.value = false;
    pendingTerminalStatus.value = null;
    if (!statusValue) {
        return;
    }
    patchTransactionStatus(statusValue, statuses);
};

function markDealComplete() {
    if (!canMarkDealComplete.value) return;
    beginTerminalStatusChange('completed', TRANSACTION_STATUS_ID.completed);
}

const invoiceStatusMeta = (status) => {
    const opts = props.enumOptions[invoiceStatusEnumKey] || [];
    const opt = opts.find((o) => o.value === status || o.id === status);
    if (opt) {
        return { label: opt.name, bgClass: opt.bgClass || 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
    }
    return { label: status || '—', bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
};

const deliveryStatusMeta = (status) => {
    const opts = props.enumOptions[deliveryStatusEnumKey] || [];
    const opt = opts.find((o) => o.value === status || o.id === status);
    if (opt) {
        return { label: opt.name, bgClass: opt.bgClass || 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
    }
    const label = status ? String(status).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '—';
    return { label, bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
};

const deliveryLabel = (delivery) =>
    delivery?.display_name || (delivery?.sequence ? `DLV-${delivery.sequence}` : delivery?.id ? `DLV-${delivery.id}` : 'Delivery');

const invoiceLabel = (invoice) =>
    invoice?.display_name || (invoice?.sequence ? `INV-${invoice.sequence}` : invoice?.id ? `INV-${invoice.id}` : 'Invoice');

const statusMeta = computed(() => {
    const raw = props.record.status;
    const opts = props.enumOptions[statusEnumKey] || [];
    const opt = opts.find((o) => o.value === raw || o.id === raw || String(o.id) === String(raw));
    if (opt) {
        return { label: opt.name, bgClass: opt.bgClass || 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
    }
    const map = {
        open:        { label: 'Pending',     bgClass: 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200' },
        pending:     { label: 'Pending',     bgClass: 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200' },
        active:      { label: 'Processing',  bgClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
        processing:  { label: 'Processing',  bgClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
        won:         { label: 'Completed',   bgClass: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
        completed:   { label: 'Completed',   bgClass: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
        lost:        { label: 'Failed',      bgClass: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
        failed:      { label: 'Failed',      bgClass: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
        cancelled:   { label: 'Cancelled',   bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' },
        1:           { label: 'Pending',     bgClass: 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200' },
        2:           { label: 'Processing',  bgClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' },
        3:           { label: 'Completed',   bgClass: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' },
        4:           { label: 'Failed',      bgClass: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
        5:           { label: 'Cancelled',   bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' },
    };
    const key = Object.prototype.hasOwnProperty.call(map, raw)
        ? raw
        : (Number.isFinite(Number(raw)) ? Number(raw) : raw);
    return map[key] || { label: raw ?? '—', bgClass: 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-200' };
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Transactions', href: route('transactions.index') },
    { label: props.record.title || `Deal #${props.record.sequence}` },
]);

const lineItemsResolution = computed(() => resolveLineItemsForTransaction(props.record));
const items = computed(() => lineItemsResolution.value.items);
/** When linked estimate has lines, match the estimate view; else use deal line items. */
const lineItemsFromEstimate = computed(() => lineItemsResolution.value.source === 'estimate');

const lineBaseTotal = (item) => lineItemPreTaxTotal(item);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const addonPreTaxTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);

const selectedOptionUnitPrice = (opt) => Number(opt?.price ?? 0);

const optionRowTaxable = (opt) => opt.taxable !== false && opt.taxable !== 0 && opt.taxable !== '0';

const taxOnAssetOption = (opt) =>
    taxOnAddon({ price: selectedOptionUnitPrice(opt), quantity: 1, taxable: optionRowTaxable(opt) });

const effectiveTaxRatePercent = computed(() =>
    taxRateForResolvedLines(props.record, lineItemsResolution.value.source, props.record.tax_rate),
);

const dealTaxRatePercent = () => effectiveTaxRatePercent.value;

const taxOnItemBase = (item) => {
    const r = dealTaxRatePercent();
    const taxable = item.taxable !== false && item.taxable !== 0 && item.taxable !== '0';
    if (!taxable || r <= 0) return 0;
    return roundMoney(lineBaseTotal(item) * (r / 100));
};

const taxOnAddon = (addon) => {
    const r = dealTaxRatePercent();
    const taxable = addon.taxable !== false && addon.taxable !== 0 && addon.taxable !== '0';
    if (!taxable || r <= 0) return 0;
    return roundMoney(addonPreTaxTotal(addon) * (r / 100));
};

function formatMoney(amount, currency) {
    if (amount == null || amount === '') return '—';
    const n = Number(amount);
    if (Number.isNaN(n)) return String(amount);
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || props.record.currency || 'USD' }).format(n);
    } catch {
        return `${currency || 'USD'} ${n.toFixed(2)}`;
    }
}

function formatDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return iso; }
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch { return iso; }
}

const deleteTransaction = () => {
    if (!canModifyDealStructure.value) {
        return;
    }
    if (confirm('Delete this deal? It will be removed from your list.')) {
        router.delete(route('transactions.destroy', props.record.id));
    }
};

// Related records — build a list of whatever is linked
const relatedRecords = computed(() => {
    const links = [];
    if (props.record.estimate_id) {
        links.push({
            key: 'estimate',
            icon: 'description',
            label: 'Estimate',
            name: props.record.estimate?.display_name || `#${props.record.estimate_id}`,
            href: route('estimates.show', props.record.estimate_id),
            meta: props.record.estimate?.status_label || null,
            amount: props.record.estimate?.total ?? null,
        });
    }
    if (props.record.opportunity_id) {
        links.push({
            key: 'opportunity',
            icon: 'trending_up',
            label: 'Opportunity',
            name: props.record.opportunity?.display_name || `#${props.record.opportunity_id}`,
            href: route('opportunities.show', props.record.opportunity_id),
            meta: props.record.opportunity?.status_label || null,
            amount: props.record.opportunity?.amount ?? null,
        });
    }
    // Contract belongs to the deal via `contracts.transaction_id` (hasOne), not `transactions.contract_id`.
    const contractId = props.record.contract?.id ?? props.record.contract_id;
    if (contractId) {
        links.push({
            key: 'contract',
            icon: 'gavel',
            label: 'Contract',
            // Matches Contract model: $appends display_name / getDisplayNameAttribute()
            name: props.record.contract?.display_name || `#${contractId}`,
            href: route('contracts.show', contractId),
            meta: props.record.contract?.status_label || null,
            amount: props.record.contract?.total_amount ?? null,
        });
    }

    for (const invoice of transactionInvoices.value) {
        const status = invoiceStatusMeta(invoice.status);
        links.push({
            key: `invoice-${invoice.id}`,
            icon: 'receipt_long',
            label: 'Invoice',
            name: invoice.display_name || `INV-${invoice.sequence || invoice.id}`,
            href: route('invoices.show', invoice.id),
            meta: status.label,
            metaClass: status.bgClass,
            amount: invoice.total ?? null,
            currency: invoice.currency || props.record.currency || 'USD',
        });
    }

    for (const delivery of transactionDeliveries.value) {
        const status = deliveryStatusMeta(delivery.status);
        links.push({
            key: `delivery-${delivery.id}`,
            icon: 'local_shipping',
            label: 'Delivery',
            name: deliveryLabel(delivery),
            href: route('deliveries.show', delivery.id),
            meta: status.label,
            metaClass: status.bgClass,
        });
    }

    return links;
});

// ─── Stepper ──────────────────────────────────────────────────────────────
//
// Estimate status — stored as unsignedTinyInteger (EstimateStatus ids):
//   1=Draft  2=PendingApproval  3=Approved  4=Declined  5=Expired  6=Cancelled
//
// Contract status — stored as string (ContractStatus values):
//   draft | pending_approval | signed | cancelled | expired
//
// Delivery status — stored as string (Deliveries\Status values):
//   scheduled | requested | en_route | delivered | cancelled | rescheduled
//
const stepperSteps = computed(() => {
    const steps = [];

    // ── Estimate (shown only when linked) ─────────────────────────────────
    if (props.record.estimate_id) {
        const status = props.record.estimate?.status; // integer id
        const done    = status === 3;                  // Approved
        const pending = status === 2;                  // PendingApproval
        steps.push({
            key:   'estimate',
            label: 'Estimate',
            icon:  'description',
            state: done ? 'complete' : pending ? 'pending' : 'current',
            href:  route('estimates.show', props.record.estimate_id),
        });
    }

    // ── Deal (always complete — you are on this page) ─────────────────────
    // steps.push({
    //     key:   'deal',
    //     label: 'Deal',
    //     icon:  'handshake',
    //     state: 'complete',
    //     href:  null,
    // });

    // ── Contract ──────────────────────────────────────────────────────────
    // Contract has `transaction_id` FK; loaded as hasOne from Transaction.
    const contract       = props.record.contract;
    const contractStatus = contract?.status ?? null;
    if (props.record.needs_contract) {
        const done    = contractStatus === 'signed';
        const pending = ['draft', 'pending_approval'].includes(contractStatus);
        steps.push({
            key:   'contract',
            label: 'Contract',
            icon:  'gavel',
            state: done ? 'complete' : pending ? 'pending' : 'todo',
            href:  contract?.id ? route('contracts.show', contract.id) : null,
        });
    } else {
        steps.push({
            key:   'contract',
            label: 'Contract',
            icon:  'gavel',
            state: contractStatus === 'signed' ? 'complete' : 'optional',
            href:  contract?.id ? route('contracts.show', contract.id) : null,
        });
    }

    steps.push({
        key:   'invoice',
        label: 'Invoice',
        icon:  'receipt_long',
        state: (() => {
            const invoices = transactionInvoices.value;
            if (invoices.length === 0) return 'todo';
            const hasIssued = invoices.some((inv) => inv.status && !['draft', 'void'].includes(inv.status));
            if (!hasIssued) {
                return invoices.some((inv) => inv.status === 'draft') ? 'pending' : 'todo';
            }
            const allSettled = invoices.every((inv) => ['paid', 'void'].includes(inv.status));
            if (allSettled) return 'complete';
            if (invoices.some((inv) => ['sent', 'viewed', 'partial'].includes(inv.status))) return 'current';
            return 'pending';
        })(),
        href:  transactionInvoices.value.length > 0
            ? route('invoices.show', transactionInvoices.value[0].id)
            : (canCreateInvoice.value ? createInvoiceHref.value : null),
        createLabel: 'Create Invoice',
        count: transactionInvoices.value.length || undefined,
    });

    // ── Service Ticket ────────────────────────────────────────────────────
    const tickets = props.record.service_tickets ?? [];
    const hasTickets = tickets.length > 0;
    const ticketDone = hasTickets && tickets.every((t) => [4, 5].includes(Number(t.status)));
    const ticketActive = hasTickets && tickets.some((t) => [2, 3].includes(Number(t.status)));
    steps.push({
        key:   'service_ticket',
        label: 'Service',
        icon:  'build',
        state: !hasTickets ? 'optional' : ticketDone ? 'complete' : ticketActive ? 'current' : 'pending',
        href:  hasTickets ? route('servicetickets.show', tickets[0].id) : null,
        count: tickets.length,
    });

    // ── Delivery ──────────────────────────────────────────────────────────
    if (props.record.needs_delivery) {
        const dels = props.record.deliveries ?? [];
        const delivered = dels.some((d) => d.status === 'delivered');
        const inFlight = dels.some((d) => d.status && !['delivered', 'cancelled'].includes(d.status));
        steps.push({
            key:   'delivery',
            label: 'Delivery',
            icon:  'local_shipping',
            state: delivered ? 'complete' : inFlight ? 'pending' : dels.length ? 'current' : 'todo',
            href:  dels.length > 0 ? route('deliveries.show', dels[0].id) : null,
        });
    } else {
        steps.push({
            key:   'delivery',
            label: 'Delivery',
            icon:  'local_shipping',
            state: 'optional',
            href:  null,
        });
    }

    // ── Completed (deal pipeline status) ──────────────────────────────────
    const issuedInvoice = transactionInvoices.value.some((inv) => inv.status && !['draft', 'void'].includes(inv.status));
    const ticketsOk = !hasTickets || ticketDone;
    const deliveryOk = !props.record.needs_delivery || (props.record.deliveries ?? []).some((d) => d.status === 'delivered');
    const contractOk = !props.record.needs_contract || contractStatus === 'signed';
    const gatesReady = contractOk && deliveryOk && issuedInvoice && ticketsOk;

    steps.push({
        key:   'completed',
        label: 'Completed',
        icon:  'check_circle',
        state: isCompleted.value ? 'complete' : gatesReady ? 'current' : 'todo',
        href:  null,
    });

    if (isCompleted.value) {
        return steps.map((s) => ({ ...s, state: 'complete' }));
    }

    return steps;
});

// ─── Grand total (all items + addons + boat option premiums, including tax) ─
const computedGrandTotal = computed(() => {
    let total = 0;
    for (const item of items.value) {
        total += lineBaseTotal(item) + taxOnItemBase(item);
        for (const opt of lineAssetSelectedOptions(item)) {
            total += selectedOptionUnitPrice(opt) + taxOnAssetOption(opt);
        }
        for (const addon of (item.addons || [])) {
            total += addonPreTaxTotal(addon) + taxOnAddon(addon);
        }
    }
    return roundMoney(total);
});

const computedTaxFromDisplayedLines = computed(() => {
    let t = 0;
    for (const item of items.value) {
        t += taxOnItemBase(item);
        for (const opt of lineAssetSelectedOptions(item)) {
            t += taxOnAssetOption(opt);
        }
        for (const addon of (item.addons || [])) {
            t += taxOnAddon(addon);
        }
    }
    return roundMoney(t);
});

const computedPreTaxFromDisplayedLines = computed(() => {
    let s = 0;
    for (const item of items.value) {
        s += lineBaseTotal(item);
        for (const opt of lineAssetSelectedOptions(item)) {
            s += selectedOptionUnitPrice(opt);
        }
        for (const addon of (item.addons || [])) {
            s += addonPreTaxTotal(addon);
        }
    }
    return roundMoney(s);
});

/** Stored rollup on `transactions` is missing for some deals; derive from resolved line rows. */
const dealSummaryUsesDerivedTotals = computed(
    () => items.value.length > 0 && !(Number(props.record.total) > 0),
);

const dealSummarySubtotal = computed(() =>
    dealSummaryUsesDerivedTotals.value
        ? computedPreTaxFromDisplayedLines.value
        : Number(props.record.subtotal || 0),
);

const dealSummaryTax = computed(() =>
    dealSummaryUsesDerivedTotals.value
        ? computedTaxFromDisplayedLines.value
        : Number(props.record.tax_total != null && props.record.tax_total !== '' ? props.record.tax_total : 0),
);

const dealSummaryTotal = computed(() =>
    dealSummaryUsesDerivedTotals.value
        ? roundMoney(
            computedGrandTotal.value
                - Number(props.record.discount_total || 0)
                + Number(props.record.fees_total || 0),
        )
        : Number(props.record.total || 0),
);

// ─── Create contract modal ─────────────────────────────────────────────────
const createContractModal = ref(false);

const contractForm = useForm({
    transaction_id: props.record.id,
    customer_id: props.record.customer_id,
    estimate_id: props.record.estimate_id ?? null,
    total_amount: 0,
    currency: props.record.currency || 'USD',
    status: 1,
    payment_status: 1,
    notes: '',
    signature_required: true,
});

const openCreateContractModal = () => {
    if (!canModifyDealStructure.value) {
        return;
    }
    contractForm.total_amount = computedGrandTotal.value;
    createContractModal.value = true;
};

const submitContract = () => {
    contractForm.post(route('contracts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createContractModal.value = false;
        },
    });
};

// ─── Optional step confirm modal ──────────────────────────────────────────
const confirmModal = ref({ show: false, type: null });

const openOptionalModal = (type) => {
    if (!canModifyDealStructure.value) {
        return;
    }
    confirmModal.value = { show: true, type };
};

const confirmAddStep = () => {
    router.patch(route('transactions.update', props.record.id), {
        [confirmModal.value.type === 'contract' ? 'needs_contract' : 'needs_delivery']: true,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const type = confirmModal.value.type;
            confirmModal.value = { show: false, type: null };
            if (type === 'contract') {
                openCreateContractModal();
            }
        },
    });
};
</script>

<template>
    <Head :title="record.title || `Deal #${record.sequence}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex  gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-lg font-semibold text-gray-800 md:text-xl dark:text-gray-200">
                            {{ record.title || `Deal #${record.sequence}` }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium md:px-2.5 md:py-0.5 md:text-md" :class="statusMeta.bgClass">
                                {{ statusMeta.label }}
                            </span>
                            <span v-if="record.closed_at" class="text-sm text-gray-500 md:text-md dark:text-gray-400">
                                Closed {{ formatDateTime(record.closed_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1 md:gap-2">
                        <Link :href="route('transactions.index')">
                            <button
                                type="button"
                                aria-label="Back to deals"
                                class="inline-flex items-center justify-center gap-0 rounded-lg border border-gray-300 bg-white p-2 text-md font-medium text-gray-700 hover:bg-gray-50 md:gap-1.5 md:px-4 md:py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-xl leading-none md:text-lg">arrow_back</span>
                                <span class="hidden md:inline">Back</span>
                            </button>
                        </Link>
                        <Link :href="route('transactions.edit', record.id)">
                            <button
                                type="button"
                                aria-label="Edit deal"
                                class="inline-flex items-center justify-center gap-0 rounded-lg bg-blue-600 p-2 text-md font-medium text-white hover:bg-blue-700 md:gap-1.5 md:px-4 md:py-2"
                            >
                                <span class="material-icons text-xl leading-none md:text-lg">edit</span>
                                <span class="hidden md:inline">Edit</span>
                            </button>
                        </Link>
                        <button
                            v-if="canMarkDealComplete"
                            type="button"
                            aria-label="Mark deal completed"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-green-600 p-2 text-md font-medium text-white hover:bg-green-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="markDealComplete"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">task_alt</span>
                            <span class="hidden md:inline">Mark completed</span>
                        </button>
                        <button
                            v-if="canModifyDealStructure"
                            type="button"
                            aria-label="Delete deal"
                            class="inline-flex items-center justify-center gap-0 rounded-lg bg-red-600 p-2 text-md font-medium text-white hover:bg-red-700 md:gap-1.5 md:px-4 md:py-2"
                            @click="deleteTransaction"
                        >
                            <span class="material-icons text-xl leading-none md:text-lg">delete</span>
                            <span class="hidden md:inline">Delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Flash messages -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 p-4 text-md text-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-lg bg-red-50 p-4 text-md text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ flash.error }}
        </div>

<!-- ─── Deal Progress Stepper ─────────────────────────────────────────── -->
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg px-6 py-5 hidden md:block">
    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Deal Progress</h3>
    <div class="relative flex items-start justify-between">

        <!-- Connector line behind the icons -->
        <div class="absolute top-4 left-0 right-0 h-px bg-gray-200 dark:bg-gray-700 z-0" aria-hidden="true"></div>

        <template v-for="(step, index) in stepperSteps" :key="step.key">
            <div class="relative z-10 flex flex-col items-center text-center"
                 :class="stepperSteps.length <= 4 ? 'w-1/4' : stepperSteps.length === 5 ? 'w-1/5' : 'w-1/6'">

                <!-- Icon bubble -->
                <component
                    :is="step.href ? 'a' : step.state === 'optional' ? 'button' : 'div'"
                    :href="step.href || undefined"
                    @click="step.state === 'optional' && canModifyDealStructure ? openOptionalModal(step.key) : undefined"
                    class="flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all"
                    :class="{
                        'bg-green-500 border-green-500 text-white': step.state === 'complete',
                        'bg-blue-600 border-blue-600 text-white ring-4 ring-blue-100 dark:ring-blue-900/40': step.state === 'current',
                        'bg-amber-400 border-amber-400 text-white': step.state === 'pending',
                        'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 hover:border-blue-400 hover:text-blue-500 transition-colors': step.state === 'todo' && step.href,
                        'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500': step.state === 'todo' && !step.href,
                        'bg-white dark:bg-gray-800 border-dashed border-gray-300 dark:border-gray-600 text-gray-300 dark:text-gray-600 hover:border-blue-400 hover:text-blue-400 cursor-pointer': step.state === 'optional',
                    }"
                >
                    <!-- complete -->
                    <span v-if="step.state === 'complete'" class="material-icons text-md">check</span>
                    <!-- pending -->
                    <span v-else-if="step.state === 'pending'" class="material-icons text-md">hourglass_top</span>
                    <!-- optional -->
                    <span v-else-if="step.state === 'optional'" class="material-icons text-md">add</span>
                    <!-- todo / current -->
                    <span v-else class="material-icons text-md">{{ step.icon }}</span>
                </component>

                <!-- Label -->
                <span class="mt-2 text-sm font-medium leading-tight"
                    :class="{
                        'text-green-600 dark:text-green-400': step.state === 'complete',
                        'text-blue-600 dark:text-blue-400': step.state === 'current',
                        'text-amber-600 dark:text-amber-400': step.state === 'pending',
                        'text-gray-400 dark:text-gray-500': step.state === 'todo',
                        'text-gray-300 dark:text-gray-600': step.state === 'optional',
                    }">
                    {{ step.label }}
                </span>

                <!-- Sub-label -->
                <span class="mt-0.5 text-sm leading-tight"
                    :class="{
                        'text-green-500 dark:text-green-500': step.state === 'complete',
                        'text-blue-500 dark:text-blue-500': step.state === 'current',
                        'text-amber-500 dark:text-amber-500': step.state === 'pending',
                        'text-gray-300 dark:text-gray-600': step.state === 'todo' || step.state === 'optional',
                    }">
                    <template v-if="step.state === 'complete'">Done</template>
                    <template v-else-if="step.state === 'current'">In progress</template>
                    <template v-else-if="step.state === 'pending'">In progress</template>
                    <template v-else-if="step.state === 'optional'">Optional</template>
                    <template v-else>Pending</template>
                </span>
            </div>
        </template>
    </div>
</div>

<!-- ─── Optional Step Confirm Modal ───────────────────────────────────── -->
<div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="confirmModal.show = false">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                <span class="material-icons text-blue-600 dark:text-blue-400">{{ confirmModal.type === 'contract' ? 'gavel' : 'local_shipping' }}</span>
            </div>
            <div>
                <p class="text-md font-semibold text-gray-900 dark:text-white">
                    Add {{ confirmModal.type === 'contract' ? 'Contract' : 'Delivery' }}?
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">This will mark the deal as requiring a {{ confirmModal.type }}.</p>
            </div>
        </div>
        <div class="flex gap-3 justify-end mt-6">
            <button type="button" @click="confirmModal.show = false"
                class="px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                Cancel
            </button>
            <button type="button" @click="confirmAddStep"
                class="px-4 py-2 text-md font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                Yes, add {{ confirmModal.type }}
            </button>
        </div>
    </div>
</div>

<!-- ─── Create Contract Modal ──────────────────────────────────────────── -->
<div v-if="createContractModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="createContractModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg mx-4">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                    <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">gavel</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create Contract</h3>
            </div>
            <button type="button" @click="createContractModal = false"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <span class="material-icons">close</span>
            </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="submitContract" class="p-6 space-y-4">

            <!-- Validation errors -->
            <div v-if="Object.keys(contractForm.errors).length" class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-md text-red-700 dark:text-red-300 space-y-1">
                <p v-for="(msg, field) in contractForm.errors" :key="field">{{ msg }}</p>
            </div>

            <!-- Total amount -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">
                    Contract Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 text-md">$</span>
                    <input
                        v-model.number="contractForm.total_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 pl-7 pr-4 py-2 text-md text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </div>
                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Pre-filled from deal line items total</p>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">Notes</label>
                <textarea
                    v-model="contractForm.notes"
                    rows="3"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-md text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                    placeholder="Optional contract notes…"
                />
            </div>

            <!-- Signature required -->
            <div class="flex items-center gap-3">
                <input
                    id="sig-required"
                    v-model="contractForm.signature_required"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                />
                <label for="sig-required" class="text-md text-gray-700 dark:text-gray-300 cursor-pointer">
                    Signature required
                </label>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="createContractModal = false"
                    class="px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="contractForm.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 text-md font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg transition-colors">
                    <span v-if="contractForm.processing" class="material-icons text-md animate-spin">refresh</span>
                    <span v-else class="material-icons text-md">save</span>
                    Create Contract
                </button>
            </div>

        </form>
    </div>
</div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            <!-- ─── MAIN COLUMN ─────────────────────────────────────────── -->
            <div class="space-y-6 lg:col-span-8">

                <!-- Main detail card -->
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">

                    <!-- Blue header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-xl font-bold text-white">{{ record.title || `Deal #${record.sequence}` }}</h1>
                                <p class="text-blue-100 text-md mt-0.5">Deal Record</p>
                            </div>
                            <div class="text-right">
                                <p class="text-blue-200 text-md mb-0.5">Deal #</p>
                                <p class="text-white text-xl font-mono font-semibold">{{ record.sequence || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- Completed / Failed banners -->
                        <div v-if="isCompleted" class="flex items-center gap-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3">
                            <span class="material-icons text-green-600 dark:text-green-400">emoji_events</span>
                            <div>
                                <p class="text-md font-semibold text-green-800 dark:text-green-200">Deal completed</p>
                                <p v-if="record.won_at" class="text-md text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</p>
                            </div>
                        </div>
                        <div v-if="isFailed" class="flex items-center gap-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                            <span class="material-icons text-red-600 dark:text-red-400">sentiment_dissatisfied</span>
                            <div>
                                <p class="text-md font-semibold text-red-800 dark:text-red-200">Deal failed</p>
                                <p v-if="record.lost_at" class="text-md text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</p>
                                <p v-if="record.loss_reason_category" class="text-md text-red-600 dark:text-red-400 mt-0.5">
                                    Category: {{ record.loss_reason_category }}
                                </p>
                            </div>
                        </div>

                        <!-- Customer & Relations -->
                        <div class=" border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer &amp; Relations
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</p>
                                    <Link v-if="record.customer_id" :href="route('customers.show', record.customer_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.customer?.display_name || '—' }}
                                    </Link>
                                    <p v-else class="text-md text-gray-900 dark:text-white">—</p>
                                </div>
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Salesperson</p>
                                    <Link v-if="record.user" :href="route('users.show', record.user)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.user?.display_name || `#${record.user}` }}
                                    </Link>
                                    <p v-else class="text-md text-gray-900 dark:text-white">—</p>
                                </div>
                                <div v-if="record.estimate_id">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Estimate</p>
                                    <Link :href="route('estimates.show', record.estimate_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.estimate?.display_name || `#${record.estimate_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.opportunity_id">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Opportunity</p>
                                    <Link :href="route('opportunities.show', record.opportunity_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.opportunity?.display_name || `#${record.opportunity_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.subsidiary_id">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Subsidiary</p>
                                    <Link :href="route('subsidiaries.show', record.subsidiary_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.subsidiary?.display_name || `#${record.subsidiary_id}` }}
                                    </Link>
                                </div>
                                <div v-if="record.location_id">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Location</p>
                                    <Link :href="route('locations.show', record.location_id)" class="text-md font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ record.location?.display_name || `#${record.location_id}` }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Next steps -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Next steps
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-md">

                                <!-- Contract -->
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Needs contract</p>
                                    <p class="text-gray-900 dark:text-gray-100 mb-2">{{ record.needs_contract ? 'Yes' : 'No' }}</p>
                                    <template v-if="record.needs_contract">
                                        <a v-if="record.contract?.id"
                                            :href="route('contracts.show', record.contract.id)"
                                            class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            <span class="material-icons text-md">gavel</span>
                                            View Contract
                                        </a>
                                        <button
                                            v-else-if="canModifyDealStructure"
                                            type="button"
                                            @click="openCreateContractModal"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                        >
                                            <span class="material-icons text-md">add</span>
                                            Create Contract
                                        </button>
                                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            Contract cannot be added after the deal is completed.
                                        </p>
                                    </template>
                                </div>

                                <!-- Delivery -->
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Needs delivery</p>
                                    <p class="text-gray-900 dark:text-gray-100 mb-2">{{ record.needs_delivery ? 'Yes' : 'No' }}</p>
                                    <template v-if="record.needs_delivery">
                                        <div v-if="transactionDeliveries.length > 0" class="space-y-2 mb-2">
                                            <Link
                                                v-for="delivery in transactionDeliveries"
                                                :key="delivery.id"
                                                :href="route('deliveries.show', delivery.id)"
                                                class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                            >
                                                <span class="material-icons text-md">local_shipping</span>
                                                View Delivery{{ transactionDeliveries.length > 1 ? ` (${deliveryLabel(delivery)})` : '' }}
                                            </Link>
                                        </div>
                                        <Link
                                            v-if="transactionDeliveries.length === 0"
                                            :href="route('deliveries.create', { transaction_id: record.id })"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                        >
                                            <span class="material-icons text-md">add</span>
                                            Schedule Delivery
                                        </Link>
                                    </template>
                                </div>

                                <!-- MSO -->
                                <div v-if="isCompleted && record.mso_needed">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">MSO</p>
                                    <p class="text-gray-900 dark:text-gray-100 mb-2">{{ record.mso_created ? 'Complete' : 'Pending' }}</p>
                                    <Link
                                        v-if="!record.mso_created"
                                        :href="route('mso.index')"
                                        class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        <span class="material-icons text-md">description</span>
                                        MSO
                                    </Link>
                                </div>

                                <!-- Invoice -->
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Invoice</p>
                                    <div v-if="transactionInvoices.length > 0" class="space-y-2 mb-2">
                                        <Link
                                            v-for="invoice in transactionInvoices"
                                            :key="invoice.id"
                                            :href="route('invoices.show', invoice.id)"
                                            class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                        >
                                            <span class="material-icons text-md">receipt_long</span>
                                            View Invoice{{ transactionInvoices.length > 1 ? ` (${invoiceLabel(invoice)})` : '' }}
                                        </Link>
                                    </div>
                                    <Link
                                        v-if="canCreateInvoice && transactionInvoices.length === 0"
                                        :href="createInvoiceHref"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                    >
                                        <span class="material-icons text-md">add</span>
                                        Create Invoice
                                    </Link>
                                    <p v-else-if="transactionInvoices.length === 0 && createInvoiceBlockMessage" class="text-sm text-amber-700 dark:text-amber-300">
                                        {{ createInvoiceBlockMessage }}
                                    </p>
                                    <p v-else-if="transactionInvoices.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                        Invoices cannot be added after the deal is completed.
                                    </p>
                                </div>

                                <!-- Service Tickets -->
                                <div class="sm:col-span-2">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Service Tickets</p>
                                    <div v-if="record.service_tickets && record.service_tickets.length > 0" class="space-y-2 mb-3">
                                        <a
                                            v-for="ticket in record.service_tickets"
                                            :key="ticket.id"
                                            :href="route('servicetickets.show', ticket.id)"
                                            class="flex items-center justify-between p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="material-icons text-lg text-gray-400 group-hover:text-blue-500">build</span>
                                                <span class="text-md font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                                    {{ ticket.service_ticket_number || `#${ticket.id}` }}
                                                </span>
                                            </div>
                                            <span class="material-icons text-gray-300 group-hover:text-blue-400 text-md">chevron_right</span>
                                        </a>
                                    </div>
                                    <a :href="route('servicetickets.create', { transaction_id: record.id })"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 transition-colors">
                                        <span class="material-icons text-md">add</span>
                                        Create Service Ticket
                                    </a>
                                </div>

                            </div>
                        </div>

                        <!-- Customer Snapshot -->
                        <div class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Customer Snapshot
                            </h3>
                            <p class="text-md text-gray-500 dark:text-gray-400 mb-4 -mt-2">Preserved at time of deal creation</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Name</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ record.customer_name || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ record.customer_email || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Phone</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ formatPhoneNumber(record.customer_phone) || '—' }}</p>
                                </div>
                                <div v-if="record.billing_address_line1 || record.billing_city">
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Billing Address</p>
                                    <div class="text-md text-gray-900 dark:text-gray-100 space-y-0.5">
                                        <div v-if="record.billing_address_line1">{{ record.billing_address_line1 }}</div>
                                        <div v-if="record.billing_address_line2">{{ record.billing_address_line2 }}</div>
                                        <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                                            {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                        </div>
                                        <div v-if="record.billing_country">{{ record.billing_country }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    
                        <!-- Tax -->
                        <div v-if="record.tax_rate != null || record.tax_jurisdiction" class="border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Tax
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-md">
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Tax Rate</div>
                                    <div class="text-gray-900 dark:text-gray-100">{{ record.tax_rate != null ? `${record.tax_rate}%` : '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Jurisdiction</div>
                                    <div class="text-gray-900 dark:text-gray-100">{{ record.tax_jurisdiction || '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="record.notes" class=" border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                Notes
                            </h3>
                            <p class="text-md text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</p>
                        </div>


                        <!-- Loss Tracking -->
                        <div v-if="record.loss_reason || record.loss_reason_category" class="border-red-200 dark:border-red-800 pt-6">
                            <h3 class="text-md font-semibold text-red-700 dark:text-red-300 uppercase tracking-wide border-b border-red-200 dark:border-red-800 pb-2 mb-4">
                                Loss Tracking
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Category</p>
                                    <p class="text-md text-gray-900 dark:text-white">{{ record.loss_reason_category || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reason</p>
                                    <p class="text-md text-gray-900 dark:text-white whitespace-pre-line">{{ record.loss_reason || '—' }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ─── SIDEBAR ────────────────────────────────────────────── -->
            <div class="space-y-4 lg:col-span-4">

                <!-- Status & Dates -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Status</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-md font-medium" :class="statusMeta.bgClass">
                            {{ statusMeta.label }}
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
                        <div v-if="record.won_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Won</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ formatDateTime(record.won_at) }}</span>
                        </div>
                        <div v-if="record.lost_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Lost</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ formatDateTime(record.lost_at) }}</span>
                        </div>
                        <div v-if="record.closed_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Closed</span>
                            <span class="text-gray-900 dark:text-white">{{ formatDateTime(record.closed_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Deal Summary -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Deal Summary</span>
                    </div>
                    <div class="p-5 space-y-3 text-md">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(dealSummarySubtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Tax ({{ record.tax_rate != null ? record.tax_rate : '—' }}%)</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(dealSummaryTax) }}</span>
                        </div>
                        <div v-if="Number(record.discount_total) > 0" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Discount</span>
                            <span class="font-medium text-red-600 dark:text-red-400">−{{ formatMoney(record.discount_total) }}</span>
                        </div>
                        <div v-if="Number(record.fees_total) > 0" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Fees</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatMoney(record.fees_total) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-600 pt-3">
                            <span class="text-gray-900 dark:text-white">Total</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ formatMoney(dealSummaryTotal) }}</span>
                        </div>
                        <div class="pt-1 text-md text-gray-400 dark:text-gray-500 text-right">
                            {{ record.currency || 'USD' }} · {{ items.length }} line item{{ items.length !== 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>

                <!-- Invoices -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Invoices</span>
                        <Link
                            v-if="canCreateInvoice && transactionInvoices.length === 0"
                            :href="createInvoiceHref"
                            class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            <span class="material-icons text-base">add</span>
                            Create
                        </Link>
                    </div>
                    <div v-if="transactionInvoices.length === 0" class="p-5 text-sm text-gray-500 dark:text-gray-400">
                        No invoices linked to this deal yet.
                    </div>
                    <div v-else class="p-4 space-y-2">
                        <Link
                            v-for="invoice in transactionInvoices"
                            :key="invoice.id"
                            :href="route('invoices.show', invoice.id)"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group"
                        >
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/30 transition-colors">
                                <span class="material-icons text-emerald-600 dark:text-emerald-400 text-xl">receipt_long</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-md font-medium text-gray-900 dark:text-white truncate">
                                    {{ invoice.display_name || `INV-${invoice.sequence || invoice.id}` }}
                                </p>
                                <span
                                    class="inline-flex mt-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="invoiceStatusMeta(invoice.status).bgClass"
                                >
                                    {{ invoiceStatusMeta(invoice.status).label }}
                                </span>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-md font-semibold text-gray-900 dark:text-white">
                                    {{ formatMoney(invoice.total, invoice.currency) }}
                                </p>
                                <p v-if="Number(invoice.amount_due) > 0" class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ formatMoney(invoice.amount_due, invoice.currency) }} due
                                </p>
                                <span class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">chevron_right</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Related Records -->
                <div v-if="relatedRecords.length > 0" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <span class="text-md font-semibold text-gray-900 dark:text-white">Related Records</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <component
                            :is="rel.href ? Link : 'div'"
                            v-for="rel in relatedRecords"
                            :key="rel.key"
                            :href="rel.href || undefined"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all group"
                            :class="{ 'cursor-default': !rel.href }"
                        >
                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 transition-colors">
                                <span class="material-icons text-blue-600 dark:text-blue-400 text-xl">{{ rel.icon }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-md font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ rel.label }}</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white truncate">{{ rel.name }}</p>
                                <p v-if="rel.meta">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="rel.metaClass || 'text-gray-500 dark:text-gray-400'"
                                    >
                                        {{ rel.meta }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p v-if="rel.amount != null" class="text-md font-semibold text-gray-900 dark:text-white">{{ formatMoney(rel.amount, rel.currency) }}</p>
                                <span v-if="rel.href" class="material-icons text-gray-300 dark:text-gray-600 group-hover:text-blue-500 text-lg transition-colors">chevron_right</span>
                            </div>
                        </component>
                    </div>
                </div>


            </div>
        </div>

        <!-- Line items: full width below main column + sidebar (sublist-style section) -->
        <div class="mt-6 w-full">
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-3.5 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <h2 class="text-md font-semibold text-gray-900 dark:text-white">
                        Line Items
                        <span
                            v-if="lineItemsFromEstimate"
                            class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400"
                        >
                            (from linked estimate)
                        </span>
                        <span
                            v-else-if="record.estimate_id"
                            class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400"
                        >
                            (saved on this deal)
                        </span>
                    </h2>
                    <p v-if="record.estimate_id" class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">
                        Boat options and line edits are managed on the deal.
                        <Link :href="route('transactions.edit', record.id)" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">Edit transaction</Link>
                        to change boat options, taxes, or add-ons.
                    </p>
                </div>
                <div class="p-6">
                    <ResolvedLineItemsEstimateStyle
                        v-if="items.length > 0"
                        :items="items"
                        variant="tenant"
                        embedded
                        :format-money="(v) => formatMoney(v)"
                        :show-summary="false"
                        show-per-line-deal-tax
                        :deal-tax-rate-percent="effectiveTaxRatePercent"
                    />
                    <div v-else class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
                        <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">receipt_long</span>
                        <p class="text-md text-gray-500 dark:text-gray-400">No line items on this deal</p>
                    </div>
                </div>
            </div>
        </div>

        <TransactionAssetUnitStatusModal
            :show="showAssetUnitStatusModal"
            v-model:rows="assetUnitStatusRows"
            :transaction-status-id="pendingTerminalStatusId"
            :status-options="transactionStatusOptions"
            :unit-status-options="unitStatusOptions"
            :processing="completingDeal"
            @close="closeAssetUnitStatusModal"
            @confirm="confirmAssetUnitStatuses"
        />
    </TenantLayout>
</template>
