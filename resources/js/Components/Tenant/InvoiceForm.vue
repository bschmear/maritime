<script setup>
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import ContactAddressAutocomplete from '@/Components/ContactAddressAutocomplete.vue';
import InvoiceLineItemsEditor from '@/Components/Tenant/InvoiceLineItemsEditor.vue';
import { useTaxRateByAddress } from '@/composables/useTaxRateByAddress';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['create', 'edit', 'show'].includes(v),
    },
    initialData: { type: Object, default: () => ({}) },
    transaction: { type: Object, default: null },
    workOrder: { type: Object, default: null },
    enabledPaymentMethods: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved', 'cancelled', 'cancel']);

const isView = computed(() => props.mode === 'show');

/**
 * Invoice is being created from an existing Transaction — fields populated by
 * BuildInvoicePrefillFromTransaction should be read-only so the user can't
 * drift away from the deal they're invoicing.
 */
const fromTransaction = computed(() => props.mode === 'create' && !!props.transaction?.id);
const fromWorkOrder = computed(() => props.mode === 'create' && !!props.workOrder?.id);
/** View mode OR a field that's mirrored from the source transaction. */
const txLocked = computed(() => isView.value || fromTransaction.value);

const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const CURRENCY_ENUM_KEY = 'App\\Enums\\Payments\\Currency';

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);
const currencyOptions = computed(() => {
    const list = props.enumOptions?.[CURRENCY_ENUM_KEY] ?? [];
    return list.length ? list : [{ id: 1, value: 'USD', name: 'US Dollar' }];
});

const resolveEnumId = (enumKey, value) => {
    if (value == null) return value;
    const opts = props.enumOptions?.[enumKey] ?? [];
    if (typeof value === 'string') {
        const opt = opts.find(o => o.value === value);
        return opt ? opt.id : value;
    }
    return value;
};

/** Relation stubs merged onto `record` / `initialData` for RecordSelect labels (contact / transaction / contract). */
function relationStubsFromPayload(d) {
    if (!d || typeof d !== 'object') return {};
    return {
        ...(d.contact ? { contact: d.contact } : {}),
        ...(d.transaction ? { transaction: d.transaction } : {}),
        ...(d.contract ? { contract: d.contract } : {}),
        ...(d.work_order ? { workOrder: d.work_order } : {}),
        ...(d.subsidiary ? { subsidiary: d.subsidiary } : {}),
        ...(d.location ? { location: d.location } : {}),
    };
}

const relationOverlay = ref(relationStubsFromPayload(props.initialData));

const pseudoRecord = computed(() => {
    const base = props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null);
    const overlay = relationOverlay.value || {};
    if (base && Object.keys(base).length) {
        return { ...base, ...overlay };
    }
    if (Object.keys(overlay).length > 0) {
        return { ...overlay };
    }
    return base;
});

const dueAtDefaultYmd = () => {
    const d = new Date();
    d.setDate(d.getDate() + 30);
    return d.toISOString().slice(0, 10);
};

/** Laravel sends datetimes; `<input type="date">` needs `YYYY-MM-DD`. */
const toDateInputValue = (val) => {
    if (val == null || val === '') {
        return '';
    }
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}/.test(val.trim())) {
        return val.trim().slice(0, 10);
    }
    const d = new Date(val);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return d.toISOString().slice(0, 10);
};

const resolveCurrencyValue = (raw) => {
    if (raw == null || raw === '') return 'USD';
    const opts = currencyOptions.value;
    if (typeof raw === 'number' || (typeof raw === 'string' && /^\d+$/.test(String(raw)))) {
        const opt = opts.find((o) => o.id == raw);
        return opt?.value ?? 'USD';
    }
    const hit = opts.find((o) => o.value === raw);
    return hit?.value ?? (typeof raw === 'string' && raw.length <= 3 ? raw : 'USD');
};

const inferTaxRateFromItems = (items) => {
    const row = (items || []).find((i) => i.taxable && Number(i.tax_rate) > 0);
    return row != null ? Number(row.tax_rate) : 0;
};

const buildInitialAllowedMethods = () => {
    const list = props.enabledPaymentMethods || [];
    const codes = new Set(list.map((m) => m.code));
    const raw = props.record?.allowed_methods;
    let arr = [];
    if (Array.isArray(raw)) {
        arr = raw.filter((c) => typeof c === 'string' && codes.has(c));
    }
    if (arr.length > 0) {
        return arr;
    }
    if (props.mode === 'create') {
        return list.map((m) => m.code);
    }
    if (props.mode === 'edit' && (raw === null || raw === undefined)) {
        return list.map((m) => m.code);
    }
    return [];
};

const form = useForm({
    contact_id:            props.record?.contact_id            ?? props.initialData?.contact_id            ?? null,
    transaction_id:      props.record?.transaction_id        ?? props.initialData?.transaction_id        ?? null,
    contract_id:         props.record?.contract_id           ?? props.initialData?.contract_id           ?? null,
    work_order_id:       props.record?.work_order_id         ?? props.initialData?.work_order_id         ?? null,
    subsidiary_id:       props.record?.subsidiary_id         ?? props.initialData?.subsidiary_id         ?? null,
    location_id:         props.record?.location_id          ?? props.initialData?.location_id          ?? null,
    status:              resolveEnumId(STATUS_ENUM_KEY, props.record?.status ?? props.initialData?.status ?? 'draft'),
    currency: resolveCurrencyValue(
        props.record?.currency ?? props.initialData?.currency ?? 'USD',
    ),
    payment_term:        resolveEnumId(
        PAYMENT_TERM_ENUM_KEY,
        props.record?.payment_term
            ?? props.initialData?.payment_term
            ?? props.account?.default_payment_term
            ?? 'due_on_receipt',
    ),
    due_at: (() => {
        const raw = props.record?.due_at ?? props.initialData?.due_at;
        if (raw != null && raw !== '') {
            return toDateInputValue(raw);
        }
        return props.mode === 'create' ? dueAtDefaultYmd() : '';
    })(),
    customer_name:       props.record?.customer_name         ?? props.initialData?.customer_name         ?? '',
    customer_email:      props.record?.customer_email        ?? props.initialData?.customer_email        ?? '',
    customer_phone:      props.record?.customer_phone        ?? props.initialData?.customer_phone        ?? '',
    billing_address_line1: props.record?.billing_address_line1 ?? props.initialData?.billing_address_line1 ?? '',
    billing_address_line2: props.record?.billing_address_line2 ?? props.initialData?.billing_address_line2 ?? '',
    billing_city:        props.record?.billing_city          ?? props.initialData?.billing_city          ?? '',
    billing_state:       props.record?.billing_state         ?? props.initialData?.billing_state         ?? '',
    billing_postal:      props.record?.billing_postal        ?? props.initialData?.billing_postal        ?? '',
    billing_country:     props.record?.billing_country       ?? props.initialData?.billing_country       ?? '',
    notes:               props.record?.notes                 ?? props.initialData?.notes                 ?? '',
    tax_rate:
        props.initialData?.tax_rate != null
            ? Number(props.initialData.tax_rate)
            : inferTaxRateFromItems(props.record?.items ?? []),
    subtotal:            props.record?.subtotal              ?? props.initialData?.subtotal              ?? '0',
    tax_total:           props.record?.tax_total             ?? props.initialData?.tax_total             ?? '0',
    total:               props.record?.total                 ?? props.initialData?.total                 ?? '0',
    fees_total:
        props.record?.fees_total != null
            ? Number(props.record.fees_total)
            : Number(props.initialData?.fees_total ?? 0),

    allowed_methods: buildInitialAllowedMethods(),
    allow_partial_payment: props.record?.allow_partial_payment ?? false,
    surcharge_percent:
        props.record?.surcharge_percent != null && props.record?.surcharge_percent !== ''
            ? Number(props.record.surcharge_percent)
            : '',
    minimum_partial_amount:
        props.record?.minimum_partial_amount != null && props.record?.minimum_partial_amount !== ''
            ? Number(props.record.minimum_partial_amount)
            : '',
});

const lineItemsRef = ref(null);
const sourceType = ref(
    form.work_order_id ? 'work_order' : (form.transaction_id ? 'transaction' : 'transaction'),
);
const lineItemsReadonly = computed(
    () => isView.value || props.mode === 'edit' || !!form.transaction_id || !!form.work_order_id,
);
const lineItemsInitialItems = computed(() => {
    if (props.record?.items?.length) return props.record.items;
    return props.initialData?.items ?? [];
});

const applyPrefillPayload = async (d) => {
    if (d == null || typeof d !== 'object') return;
    if (d.contact_id != null) form.contact_id = d.contact_id;
    if (d.transaction_id != null) form.transaction_id = d.transaction_id;
    if (d.work_order_id != null) form.work_order_id = d.work_order_id;
    if ('contract_id' in d) {
        form.contract_id = d.contract_id ?? null;
    }
    if ('work_order' in d && d.work_order) {
        relationOverlay.value = {
            ...relationOverlay.value,
            workOrder: d.work_order,
        };
    }
    if ('subsidiary_id' in d) {
        form.subsidiary_id = d.subsidiary_id ?? null;
    }
    if ('location_id' in d) {
        form.location_id = d.location_id ?? null;
    }
    if ('subsidiary' in d && d.subsidiary) {
        relationOverlay.value = { ...relationOverlay.value, subsidiary: d.subsidiary };
    }
    if ('location' in d && d.location) {
        relationOverlay.value = { ...relationOverlay.value, location: d.location };
    }
    if (d.currency != null) form.currency = resolveCurrencyValue(d.currency);
    if (d.tax_rate != null) form.tax_rate = Number(d.tax_rate);
    if (d.fees_total != null) form.fees_total = Number(d.fees_total);
    if (d.customer_name !== undefined) form.customer_name = d.customer_name ?? '';
    if (d.customer_email !== undefined) form.customer_email = d.customer_email ?? '';
    if (d.customer_phone !== undefined) form.customer_phone = d.customer_phone ?? '';
    if (d.billing_address_line1 !== undefined) form.billing_address_line1 = d.billing_address_line1 ?? '';
    if (d.billing_address_line2 !== undefined) form.billing_address_line2 = d.billing_address_line2 ?? '';
    if (d.billing_city !== undefined) form.billing_city = d.billing_city ?? '';
    if (d.billing_state !== undefined) form.billing_state = d.billing_state ?? '';
    if (d.billing_postal !== undefined) form.billing_postal = d.billing_postal ?? '';
    if (d.billing_country !== undefined) form.billing_country = d.billing_country ?? '';
    if (d.payment_term != null) {
        form.payment_term = resolveEnumId(PAYMENT_TERM_ENUM_KEY, d.payment_term);
    }

    const stubs = relationStubsFromPayload(d);
    relationOverlay.value = {
        ...relationOverlay.value,
        ...stubs,
    };
    // Full transaction prefill without a linked contract — drop stale contract stub / id.
    if (d.transaction_id != null && Array.isArray(d.items) && !d.contract) {
        const { contract: _omit, ...rest } = relationOverlay.value;
        relationOverlay.value = rest;
    }

    await nextTick();
    await nextTick();
    lineItemsRef.value?.hydrateFromItems(d.items ?? [], { preserveIds: false });
};

const toggleAllowedMethod = (code, checked) => {
    const set = new Set(form.allowed_methods || []);
    if (checked) {
        set.add(code);
    } else {
        set.delete(code);
    }
    form.allowed_methods = [...set];
};

watch(
    () => [form.transaction_id, form.work_order_id],
    ([transactionId, workOrderId]) => {
        if (transactionId) {
            sourceType.value = 'transaction';
            return;
        }
        if (workOrderId) {
            sourceType.value = 'work_order';
            return;
        }
        sourceType.value = 'transaction';
    },
    { immediate: true },
);

watch(
    () => sourceType.value,
    (type) => {
        if (type === 'transaction') {
            form.work_order_id = null;
        } else if (type === 'work_order') {
            form.transaction_id = null;
            form.contract_id = null;
        }
    },
);

watch(
    () => form.allow_partial_payment,
    (v) => {
        if (!v) {
            form.minimum_partial_amount = '';
        }
    },
);

watch(
    () => form.transaction_id,
    async (tid) => {
        if (sourceType.value !== 'transaction' || props.mode !== 'create' || isView.value || !tid) return;
        try {
            const { data } = await axios.get(route('invoices.prefill-from-transaction', tid));
            await applyPrefillPayload(data);
        } catch (e) {
            console.error(e);
        }
    },
);

// ── Address helpers ──────────────────────────────────────────────────────────
const { fetchTaxRate, isFetching: isFetchingTaxRate } = useTaxRateByAddress('invoices.address-tax-rate');

const showAddressPicker = ref(false);
const contactAddresses = ref([]);
const isFetchingAddresses = ref(false);
const addressPickerContactId = ref(null);
const postingContactAddress = ref(false);

const applyAddressToForm = (src) => {
    form.billing_address_line1 = src.billing_address_line1 || src.address_line_1 || '';
    form.billing_address_line2 = src.billing_address_line2 || src.address_line_2 || '';
    form.billing_city          = src.billing_city          || src.city           || '';
    form.billing_state         = src.billing_state         || src.state          || '';
    form.billing_postal        = src.billing_postal        || src.postal_code    || '';
    form.billing_country       = src.billing_country       || src.country        || '';
};

const fillCustomerFromContact = (contact) => {
    const name =
        contact.display_name
        || [contact.first_name, contact.last_name].filter(Boolean).join(' ')
        || contact.name
        || '';
    if (name) form.customer_name = name;
    if (contact.email) form.customer_email = contact.email;
    const phone = contact.phone || contact.mobile;
    if (phone) form.customer_phone = phone;
};

const fetchContactAddressesForPicker = async (contactId) => {
    isFetchingAddresses.value = true;
    contactAddresses.value = [];
    try {
        const res = await fetch(route('contacts.addresses.index', { contact: contactId }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            contactAddresses.value = data.addresses ?? [];
        }
    } catch {
        contactAddresses.value = [];
    } finally {
        isFetchingAddresses.value = false;
    }
};

const handleContactSelected = async (contact) => {
    if (!contact?.id) return;

    fillCustomerFromContact(contact);
    addressPickerContactId.value = contact.id;
    showAddressPicker.value = true;
    await fetchContactAddressesForPicker(contact.id);
};

/** Re-open the contact address modal from the billing section (same picker as on contact change). */
const openBillingContactAddressPicker = async () => {
    if (!form.contact_id || isView.value) return;
    addressPickerContactId.value = form.contact_id;
    showAddressPicker.value = true;
    await fetchContactAddressesForPicker(form.contact_id);
};

const selectContactAddress = (addr) => {
    applyAddressToForm(addr);
    dismissAddressPicker();
};

const dismissAddressPicker = () => {
    showAddressPicker.value = false;
    contactAddresses.value = [];
    addressPickerContactId.value = null;
};

const onInvoiceContactAddressSaved = (payload) => {
    if (!addressPickerContactId.value) return;
    postingContactAddress.value = true;
    router.post(route('contacts.addresses.store', addressPickerContactId.value), payload, {
        preserveScroll: true,
        onFinish: () => {
            postingContactAddress.value = false;
        },
        onSuccess: () => {
            applyAddressToForm({
                address_line_1: payload.address_line_1,
                address_line_2: payload.address_line_2 ?? null,
                city: payload.city,
                state: payload.state,
                postal_code: payload.postal_code,
                country: payload.country,
            });
            dismissAddressPicker();
        },
    });
};

const handleAddressUpdate = (data) => {
    form.billing_address_line1 = data.street      ?? '';
    form.billing_address_line2 = data.unit        ?? '';
    form.billing_city          = data.city        ?? '';
    form.billing_state         = data.stateCode   || data.state   || '';
    form.billing_postal        = data.postalCode  ?? '';
    form.billing_country       = data.countryCode || data.country || '';
};

watch(() => form.billing_state, async (newState) => {
    if (!newState) return;
    const rate = await fetchTaxRate({
        state:       newState,
        city:        form.billing_city          || undefined,
        postal_code: form.billing_postal        || undefined,
        line1:       form.billing_address_line1 || undefined,
        country:     form.billing_country       || undefined,
    });
    if (rate != null && !Number.isNaN(Number(rate))) {
        form.tax_rate = Number(rate);
    }
});

// ── Formatting ───────────────────────────────────────────────────────────────
const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const summarySubtotal = computed(() => Number(form.subtotal || 0));
const summaryTaxTotal = computed(() => Number(form.tax_total || 0));
const summaryFeesTotal = computed(() => Number(form.fees_total || 0));
const summaryDiscountTotal = computed(() => Number(props.record?.discount_total || 0));
const summaryTotal = computed(() => Number(form.total || 0));
const summaryAmountPaid = computed(() => Number(props.record?.amount_paid || 0));
const summaryAmountDue = computed(() => Math.max(0, summaryTotal.value - summaryAmountPaid.value));

    const statusBadgeClass = computed(() => {
    const status = statusOptions.value.find(o => o.id == form.status)?.value ?? form.status ?? '';

    const map = {
        draft:   'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        sent:    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        viewed:  'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        paid:    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        void:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };

    return map[status] ?? map.draft;
});

// ── Styles ───────────────────────────────────────────────────────────────────
const inputClass = 'w-full input-style';
const textareaClass = `${inputClass} resize-none`;
const disabledClass = 'opacity-60 cursor-not-allowed';

// ── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    if (isView.value) return;
    form.transform((data) => {
        const statusOpt = statusOptions.value.find((o) => o.id == data.status);
        const next = { ...data, status: statusOpt?.value ?? data.status };
        if (sourceType.value === 'work_order' && !next.work_order_id && props.workOrder?.id) {
            next.work_order_id = props.workOrder.id;
        }
        next.allow_partial_payment = Boolean(next.allow_partial_payment);
        next.surcharge_percent =
            next.surcharge_percent === '' || next.surcharge_percent == null
                ? null
                : Number(next.surcharge_percent);
        next.minimum_partial_amount =
            !next.allow_partial_payment || next.minimum_partial_amount === '' || next.minimum_partial_amount == null
                ? null
                : Number(next.minimum_partial_amount);
        next.allowed_methods = Array.isArray(next.allowed_methods)
            ? next.allowed_methods.filter((c) => typeof c === 'string' && c !== '')
            : [];
        if (props.mode === 'create') {
            next.items = lineItemsRef.value?.buildItemsForSubmit(Number(form.tax_rate) || 0) ?? [];
        }
        return next;
    });
    if (props.mode === 'edit') {
        form.put(route('invoices.update', props.record.id), {
            onSuccess: () => emit('saved'),
        });
    } else {
        form.post(route('invoices.store'), {
            onSuccess: () => emit('saved'),
        });
    }
};

const handleCancel = () => {
    emit('cancelled');
    emit('cancel');
};
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <!-- Banner: creating from transaction ─────────────────────────────── -->
        <div
            v-if="fromTransaction"
            class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 px-4 py-3 flex items-start gap-3"
        >
            <span class="material-icons text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">receipt_long</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                    Creating invoice from Transaction →
                    <a
                        :href="route('transactions.show', transaction.id)"
                        class="underline decoration-dotted hover:text-blue-700 dark:hover:text-blue-100"
                    >{{ transaction.display_name || `#${transaction.id}` }}</a>
                </p>
                <p class="text-xs text-blue-800/80 dark:text-blue-300/80 mt-0.5">
                    Fields populated from the transaction are locked. To change them, update the transaction or start from a different one.
                </p>
            </div>
        </div>

        <div
            v-if="fromWorkOrder"
            class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 px-4 py-3 flex items-start gap-3"
        >
            <span class="material-icons text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5">build</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">
                    Creating invoice from Work Order →
                    <a
                        :href="route('workorders.show', workOrder.id)"
                        class="underline decoration-dotted hover:text-indigo-700 dark:hover:text-indigo-100"
                    >{{ workOrder.work_order_number ? `#${workOrder.work_order_number}` : (workOrder.display_name || `#${workOrder.id}`) }}</a>
                </p>
                <p class="text-xs text-indigo-800/80 dark:text-indigo-300/80 mt-0.5">
                    Work order details and billable line items were prefilled. You can still adjust this invoice before saving.
                </p>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ============================
                     Main Column
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ mode === 'edit' ? 'EDIT INVOICE' : mode === 'show' ? 'INVOICE' : 'NEW INVOICE' }}
                                    </h1>
                                    <p class="text-primary-100 text-sm mt-1">
                                        {{ mode === 'edit' ? 'Update invoice details' : mode === 'show' ? 'Invoice details & billing' : 'Create a new invoice' }}
                                    </p>
                                </div>
                                <div v-if="record?.sequence" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Invoice #</div>
                                    <div class="text-white text-lg font-mono">{{ record.sequence }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Customer & Relations + Invoice details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Contact &amp; relations
                                    </h3>

                                    <!-- Contact -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Contact <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="contact_id"
                                            :field="fieldsSchema?.contact_id || { type: 'record', typeDomain: 'Contact', label: 'Contact', required: true }"
                                            v-model="form.contact_id"
                                            :record="pseudoRecord"
                                            field-key="contact_id"
                                            :disabled="txLocked"
                                            @record-selected="handleContactSelected"
                                        />
                                        <p v-if="form.errors.contact_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.contact_id }}</p>
                                    </div>

                                    <!-- Transaction -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Invoice Source</label>
                                        <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-700 rounded-lg gap-1">
                                            <button
                                                type="button"
                                                @click="sourceType = 'transaction'"
                                                :disabled="isView"
                                                class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                                                :class="sourceType === 'transaction'
                                                    ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow'
                                                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                                            >
                                                Transaction
                                            </button>
                                            <button
                                                type="button"
                                                @click="sourceType = 'work_order'"
                                                :disabled="isView"
                                                class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                                                :class="sourceType === 'work_order'
                                                    ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow'
                                                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                                            >
                                                Work Order
                                            </button>
                                        </div>

                                        <div v-if="sourceType === 'transaction'" class="mt-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Transaction</label>
                                            <RecordSelect
                                                id="transaction_id"
                                                :field="fieldsSchema?.transaction_id || { type: 'record', typeDomain: 'Transaction', label: 'Transaction' }"
                                                v-model="form.transaction_id"
                                                :record="pseudoRecord"
                                                field-key="transaction_id"
                                                :disabled="txLocked"
                                            />
                                            <p v-if="form.errors.transaction_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.transaction_id }}</p>
                                        </div>

                                        <div v-else class="mt-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Work Order</label>
                                            <RecordSelect
                                                id="work_order_id"
                                                :field="fieldsSchema?.work_order_id || { type: 'record', typeDomain: 'WorkOrder', label: 'Work Order' }"
                                                v-model="form.work_order_id"
                                                :record="pseudoRecord"
                                                field-key="work_order_id"
                                                :disabled="isView"
                                            />
                                            <p v-if="form.errors.work_order_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.work_order_id }}</p>
                                        </div>
                                    </div>

                                    <!-- Contract -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contract</label>
                                        <RecordSelect
                                            id="contract_id"
                                            :field="fieldsSchema?.contract_id || { type: 'record', typeDomain: 'Contract', label: 'Contract' }"
                                            v-model="form.contract_id"
                                            :record="pseudoRecord"
                                            field-key="contract_id"
                                            :disabled="txLocked"
                                        />
                                        <p v-if="form.errors.contract_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.contract_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Subsidiary</label>
                                        <RecordSelect
                                            id="invoice_subsidiary_id"
                                            :field="fieldsSchema?.subsidiary_id || { type: 'record', typeDomain: 'Subsidiary', label: 'Subsidiary' }"
                                            v-model="form.subsidiary_id"
                                            :record="pseudoRecord"
                                            field-key="subsidiary_id"
                                            :disabled="isView || txLocked"
                                        />
                                        <p v-if="form.errors.subsidiary_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.subsidiary_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Location</label>
                                        <RecordSelect
                                            id="invoice_location_id"
                                            :field="fieldsSchema?.location_id || { type: 'record', typeDomain: 'Location', label: 'Location' }"
                                            v-model="form.location_id"
                                            :record="pseudoRecord"
                                            field-key="location_id"
                                            :disabled="isView || txLocked"
                                        />
                                        <p v-if="form.errors.location_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.location_id }}</p>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Invoice details
                                    </h3>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.status" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                            <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.status }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Due date</label>
                                        <input
                                            type="date"
                                            v-model="form.due_at"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                        />
                                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.due_at }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Payment terms</label>
                                        <select v-model="form.payment_term" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                            <option v-for="opt in paymentTermOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                        </select>
                                        <p v-if="form.errors.payment_term" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_term }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Currency</label>
                                        <select
                                            v-model="form.currency"
                                            :disabled="txLocked"
                                            :class="[inputClass, txLocked ? disabledClass : '']"
                                        >
                                            <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.currency" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.currency }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer payment (full width below Contact & relations + Invoice details) -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 w-full space-y-4">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Customer payment
                                </h3>
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full">
                                    <div class="lg:col-span-7 space-y-4">
                                        <p
                                            v-if="!enabledPaymentMethods?.length"
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No payment methods are enabled for this account. Enable methods under
                                            Account → Payments.
                                        </p>
                                        <div v-else class="space-y-2">
                                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                                Payment methods accepted on this invoice
                                            </p>
                                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                                <label
                                                    v-for="m in enabledPaymentMethods"
                                                    :key="m.code"
                                                    class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                        :checked="form.allowed_methods.includes(m.code)"
                                                        :disabled="isView"
                                                        @change="toggleAllowedMethod(m.code, $event.target.checked)"
                                                    >
                                                    <span>{{ m.label }}</span>
                                                </label>
                                            </div>
                                            <p v-if="form.errors.allowed_methods" class="text-xs text-red-600 dark:text-red-400">
                                                {{ form.errors.allowed_methods }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="lg:col-span-5 space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                Payment surcharge (%)
                                            </label>
                                            <input
                                                v-model="form.surcharge_percent"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                :disabled="isView"
                                                placeholder="Optional"
                                                :class="[inputClass, isView ? disabledClass : '']"
                                            >
                                            <p v-if="form.errors.surcharge_percent" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                {{ form.errors.surcharge_percent }}
                                            </p>
                                        </div>

                                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                            <input
                                                v-model="form.allow_partial_payment"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                :disabled="isView"
                                            >
                                            Allow partial payments
                                        </label>
                                        <p v-if="form.errors.allow_partial_payment" class="text-xs text-red-600 dark:text-red-400">
                                            {{ form.errors.allow_partial_payment }}
                                        </p>

                                        <div v-if="form.allow_partial_payment">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                Minimum partial amount
                                            </label>
                                            <input
                                                v-model="form.minimum_partial_amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Optional"
                                                :disabled="isView"
                                                :class="[inputClass, isView ? disabledClass : '']"
                                            >
                                            <p v-if="form.errors.minimum_partial_amount" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                {{ form.errors.minimum_partial_amount }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                    Notes
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                    Visible to the customer on the invoice
                                </p>
                                <textarea
                                    v-model="form.notes"
                                    rows="4"
                                    :disabled="isView"
                                    :class="[textareaClass, isView ? disabledClass : '']"
                                    placeholder="Add a note or message for the customer..."
                                />
                                <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
                            </div>

                            <!-- Customer Snapshot -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                    Customer Snapshot
                                    <span class="normal-case font-normal text-gray-400 dark:text-gray-500 ml-1">(captured at invoice time)</span>
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Name</label>
                                        <input
                                            type="text"
                                            v-model="form.customer_name"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                            placeholder="Customer name"
                                        />
                                        <p v-if="form.errors.customer_name" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email</label>
                                        <input
                                            type="email"
                                            v-model="form.customer_email"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                            placeholder="email@example.com"
                                        />
                                        <p v-if="form.errors.customer_email" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_email }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Phone</label>
                                        <input
                                            type="text"
                                            v-model="form.customer_phone"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                            placeholder="+1 (555) 000-0000"
                                        />
                                        <p v-if="form.errors.customer_phone" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_phone }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Address -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Billing Address
                                    </h3>
                                    <div class="flex items-center gap-3">
                                        <button
                                            v-if="!isView && form.contact_id"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                            @click="openBillingContactAddressPicker"
                                        >
                                            <span class="material-icons text-[16px]">person_pin_circle</span>
                                            Choose from contact
                                        </button>
                                        <span v-if="isFetchingTaxRate" class="text-xs text-primary-600 dark:text-primary-400 animate-pulse">
                                            Fetching tax rate…
                                        </span>
                                    </div>
                                </div>
                                <AddressAutocomplete
                                    :street="form.billing_address_line1"
                                    :unit="form.billing_address_line2"
                                    :city="form.billing_city"
                                    :state="form.billing_state"
                                    :stateCode="form.billing_state"
                                    :postalCode="form.billing_postal"
                                    :country="form.billing_country"
                                    :disabled="isView"
                                    @update="handleAddressUpdate"
                                />
                            </div>

                            <p
                                v-if="!isView && form.transaction_id"
                                class="text-sm text-yellow-500 dark:text-yellow-400 -mt-2 mb-2"
                            >
                                Line items come from this transaction and can’t be edited on the invoice.
                            </p>
                            <InvoiceLineItemsEditor
                                ref="lineItemsRef"
                                :form="form"
                                :readonly="lineItemsReadonly"
                                :initial-items="lineItemsInitialItems"
                                :show-totals-panel="false"
                            />
                        </div>
                    </div>

                    <!-- Read-only totals (show/edit mode only) -->
                    <div v-if="record && (mode === 'show' || mode === 'edit')" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Order Summary</h2>
                        </div>
                        <div class="p-6">
                            <ul class="space-y-2 text-sm max-w-xs ms-auto">
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(summarySubtotal) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Discount</span>
                                    <span class="font-medium text-gray-900 dark:text-white">-{{ formatCurrency(summaryDiscountTotal) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(summaryTaxTotal) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Fees</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(summaryFeesTotal) }}</span>
                                </li>
                                <li class="flex justify-between font-bold text-base text-gray-900 dark:text-white pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <span>Total</span>
                                    <span>{{ formatCurrency(summaryTotal) }}</span>
                                </li>
                                <li v-if="summaryAmountPaid > 0" class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Amount Paid</span>
                                    <span>{{ formatCurrency(summaryAmountPaid) }}</span>
                                </li>
                                <li class="flex justify-between font-bold text-primary-600 dark:text-primary-400 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span>Amount Due</span>
                                    <span>{{ formatCurrency(summaryAmountDue) }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Activity timeline (show mode) -->
                    <div v-if="isView && (record?.sent_at || record?.viewed_at || record?.paid_at)" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Activity</h2>
                        </div>
                        <div class="p-6">
                            <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700 space-y-4">
                                <li class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[20px] text-green-500">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Invoice created</h4>
                                        <time class="text-xs text-gray-500 dark:text-gray-400">{{ record?.created_at ? new Date(record.created_at).toLocaleDateString() : '—' }}</time>
                                    </div>
                                </li>
                                <li v-if="record?.sent_at" class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[20px] text-green-500">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Invoice sent</h4>
                                        <time class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(record.sent_at).toLocaleDateString() }}</time>
                                    </div>
                                </li>
                                <li v-if="record?.viewed_at" class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[20px] text-blue-500">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Invoice viewed</h4>
                                        <time class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(record.viewed_at).toLocaleDateString() }}</time>
                                    </div>
                                </li>
                                <li v-if="record?.paid_at" class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span class="material-icons text-[20px] text-green-600">check_circle</span>
                                    </span>
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Invoice paid</h4>
                                        <time class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(record.paid_at).toLocaleDateString() }}</time>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden ">
                        <div class="flex justify-between items-center px-5 py-4 bg-gray-700 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <button
                                v-if="!isView"
                                type="submit"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <span class="material-icons text-[18px]" :class="{ 'animate-spin': form.processing }">
                                    {{ form.processing ? 'sync' : 'check' }}
                                </span>
                                {{ form.processing ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Invoice') }}
                            </button>
                            <button
                                v-if="!isView"
                                type="button"
                                @click="handleCancel"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Line-item totals (same fields as editor; lives in sidebar on invoice form) -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Invoice totals</span>
                        </div>
                        <div class="p-5 space-y-2 text-sm">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal (lines)</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right">{{ formatCurrency(form.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Tax ({{ Number(form.tax_rate) || 0 }}%)</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right">{{ formatCurrency(form.tax_total) }}</span>
                            </div>
                            <div class="flex justify-between items-center gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Fees</span>
                                <input
                                    v-if="!lineItemsReadonly"
                                    v-model.number="form.fees_total"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :class="[inputClass, 'w-28 py-1.5 text-right text-sm']"
                                >
                                <span v-else class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(form.fees_total) }}</span>
                            </div>
                            <div class="flex justify-between gap-2 text-base font-bold border-t border-gray-200 dark:border-gray-600 pt-3 text-gray-900 dark:text-white">
                                <span>Total</span>
                                <span>{{ formatCurrency(form.total) }}</span>
                            </div>
                            <div v-if="!lineItemsReadonly" class="pt-3 border-t border-gray-200 dark:border-gray-600">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Tax rate (%)</label>
                                <input
                                    v-model.number="form.tax_rate"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    max="100"
                                    :class="inputClass"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Summary -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Invoice Summary</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Status</span>
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold', statusBadgeClass]">
                                    {{ statusOptions.find(o => o.id == form.status)?.name ?? form.status ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Payment Terms</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    {{ paymentTermOptions.find(o => o.id == form.payment_term)?.name ?? '—' }}
                                </span>
                            </div>
                            <div v-if="form.due_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Due Date</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    {{ new Date(form.due_at).toLocaleDateString() }}
                                </span>
                            </div>
                            <template v-if="record">
                                <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-500 dark:text-gray-400">Total</span>
                                    <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(summaryTotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500 dark:text-gray-400">Amount Due</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(summaryAmountDue) }}</span>
                                </div>
                            </template>
                            <div class="pt-1 text-xs text-gray-400 dark:text-gray-500 text-right">
                                {{ form.currency || 'USD' }}
                            </div>
                        </div>
                    </div>

                    <!-- Read-only invoice meta (show mode) -->
                    <div v-if="isView && record" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Details</span>
                        </div>
                        <ul class="p-5 space-y-3 text-sm divide-y divide-gray-100 dark:divide-gray-700">
                            <li v-if="record.sequence" class="flex items-center gap-2 pt-0">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">description</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Invoice #</span>
                                <span class="font-mono text-gray-900 dark:text-white">{{ record.sequence }}</span>
                            </li>
                            <li v-if="record.created_at" class="flex items-center gap-2 pt-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">calendar_today</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                <span class="text-gray-900 dark:text-white">{{ new Date(record.created_at).toLocaleDateString() }}</span>
                            </li>
                            <li v-if="record.due_at" class="flex items-center gap-2 pt-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">event</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Due Date</span>
                                <span class="text-gray-900 dark:text-white">{{ new Date(record.due_at).toLocaleDateString() }}</span>
                            </li>
                            <li class="flex items-center gap-2 pt-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">payments</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Currency</span>
                                <span class="text-gray-900 dark:text-white">{{ record.currency || 'USD' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        <!-- Contact billing address picker / add address -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddressPicker"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissAddressPicker"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Billing address</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        <template v-if="isFetchingAddresses">Loading addresses…</template>
                                        <template v-else-if="contactAddresses.length > 0">Select one of this contact’s saved addresses</template>
                                        <template v-else>This contact has no saved addresses yet. Add one to save it on the contact and use it here.</template>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 mt-0.5" @click="dismissAddressPicker">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div v-if="isFetchingAddresses" class="flex justify-center py-12">
                            <svg class="w-8 h-8 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <div v-else-if="contactAddresses.length > 0" class="px-6 pb-2 space-y-2 max-h-80 overflow-y-auto">
                            <button
                                v-for="addr in contactAddresses"
                                :key="addr.id"
                                type="button"
                                class="w-full text-left px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors group"
                                @click="selectContactAddress(addr)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm space-y-0.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ addr.address_line_1 }}</p>
                                            <span
                                                v-if="addr.is_primary"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                            >Primary</span>
                                            <span
                                                v-if="addr.label"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300"
                                            >{{ addr.label }}</span>
                                        </div>
                                        <p v-if="addr.address_line_2" class="text-gray-500 dark:text-gray-400">{{ addr.address_line_2 }}</p>
                                        <p class="text-gray-600 dark:text-gray-300">
                                            <span v-if="addr.city">{{ addr.city }}<span v-if="addr.state || addr.postal_code">, </span></span>
                                            <span v-if="addr.state">{{ addr.state }} </span>
                                            <span v-if="addr.postal_code">{{ addr.postal_code }}</span>
                                        </p>
                                        <p v-if="addr.country" class="text-gray-500 dark:text-gray-400">{{ addr.country }}</p>
                                    </div>
                                    <svg class="w-4 h-4 flex-shrink-0 text-gray-300 group-hover:text-primary-500 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>

                        <div v-else class="px-6 pb-2 space-y-4">
                            <ContactAddressAutocomplete
                                :disabled="postingContactAddress"
                                button-label="Add address to contact"
                                @saved="onInvoiceContactAddressSaved"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 mt-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                @click="dismissAddressPicker"
                            >
                                Skip, fill manually
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
