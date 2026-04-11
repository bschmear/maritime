<script setup>
import { useForm } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useTaxRateByAddress } from '@/composables/useTaxRateByAddress';
import { computed, ref, watch } from 'vue';

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
});

const emit = defineEmits(['saved', 'cancelled', 'cancel']);

const isView = computed(() => props.mode === 'show');

const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Term';

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);

const resolveEnumId = (enumKey, value) => {
    if (value == null) return value;
    const opts = props.enumOptions?.[enumKey] ?? [];
    if (typeof value === 'string') {
        const opt = opts.find(o => o.value === value);
        return opt ? opt.id : value;
    }
    return value;
};

const pseudoRecord = computed(() =>
    props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null)
);

const form = useForm({
    customer_id:           props.record?.customer_id           ?? props.initialData?.customer_id           ?? null,
    transaction_id:        props.record?.transaction_id        ?? props.initialData?.transaction_id        ?? null,
    contract_id:           props.record?.contract_id           ?? props.initialData?.contract_id           ?? null,
    status:                resolveEnumId(STATUS_ENUM_KEY, props.record?.status ?? props.initialData?.status ?? 'draft'),
    currency:              props.record?.currency              ?? props.initialData?.currency              ?? 'USD',
    payment_term:          props.record?.payment_term          ?? props.initialData?.payment_term          ?? '1',
    due_at:                props.record?.due_at                ?? props.initialData?.due_at                ?? '',
    customer_name:         props.record?.customer_name         ?? props.initialData?.customer_name         ?? '',
    customer_email:        props.record?.customer_email        ?? props.initialData?.customer_email        ?? '',
    customer_phone:        props.record?.customer_phone        ?? props.initialData?.customer_phone        ?? '',
    billing_address_line1: props.record?.billing_address_line1 ?? props.initialData?.billing_address_line1 ?? '',
    billing_address_line2: props.record?.billing_address_line2 ?? props.initialData?.billing_address_line2 ?? '',
    billing_city:          props.record?.billing_city          ?? props.initialData?.billing_city          ?? '',
    billing_state:         props.record?.billing_state         ?? props.initialData?.billing_state         ?? '',
    billing_postal:        props.record?.billing_postal        ?? props.initialData?.billing_postal        ?? '',
    billing_country:       props.record?.billing_country       ?? props.initialData?.billing_country       ?? '',
    notes:                 props.record?.notes                 ?? props.initialData?.notes                 ?? '',
});

// ── Address helpers ──────────────────────────────────────────────────────────
const { fetchTaxRate, isFetching: isFetchingTaxRate } = useTaxRateByAddress();

const showAddressConfirm = ref(false);
const pendingCustomerAddress = ref(null);

const applyAddressToForm = (src) => {
    form.billing_address_line1 = src.billing_address_line1 || src.address_line_1 || '';
    form.billing_address_line2 = src.billing_address_line2 || src.address_line_2 || '';
    form.billing_city          = src.billing_city          || src.city           || '';
    form.billing_state         = src.billing_state         || src.state          || '';
    form.billing_postal        = src.billing_postal        || src.postal_code    || '';
    form.billing_country       = src.billing_country       || src.country        || '';
};

const handleCustomerSelected = (customer) => {
    if (customer.name)  form.customer_name  = customer.name;
    if (customer.email) form.customer_email = customer.email;
    if (customer.phone) form.customer_phone = customer.phone;

    const street = customer.address_line_1 || customer.billing_address_line1 || '';
    if (!street) return;
    pendingCustomerAddress.value = customer;
    showAddressConfirm.value = true;
};

const confirmUseBillingAddress = () => {
    applyAddressToForm(pendingCustomerAddress.value);
    showAddressConfirm.value = false;
    pendingCustomerAddress.value = null;
};

const dismissAddressConfirm = () => {
    showAddressConfirm.value = false;
    pendingCustomerAddress.value = null;
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
    if (newState) {
        await fetchTaxRate({
            state:       newState,
            city:        form.billing_city          || undefined,
            postal_code: form.billing_postal        || undefined,
            line1:       form.billing_address_line1 || undefined,
            country:     form.billing_country       || undefined,
        });
    }
});

// ── Formatting ───────────────────────────────────────────────────────────────
const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const statusBadgeClass = computed(() => {
    const status = statusOptions.value.find(o => o.id == form.status)?.value ?? form.status ?? '';
    const map = {
        draft:     'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        sent:      'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        viewed:    'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
        paid:      'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        overdue:   'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        cancelled: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        void:      'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-400',
    };
    return map[status] ?? map.draft;
});

// ── Styles ───────────────────────────────────────────────────────────────────
const inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent';
const textareaClass = `${inputClass} resize-none`;
const disabledClass = 'opacity-60 cursor-not-allowed';

// ── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    if (isView.value) return;
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

                            <!-- Customer & Relations -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer &amp; Relations
                                    </h3>

                                    <!-- Customer -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Customer <span class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="customer_id"
                                            :field="fieldsSchema?.customer_id || { type: 'relationship', relationship_type: 'customers', label: 'Customer', required: true }"
                                            v-model="form.customer_id"
                                            :record="pseudoRecord"
                                            field-key="customer_id"
                                            :disabled="isView"
                                            @record-selected="handleCustomerSelected"
                                        />
                                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.customer_id }}</p>
                                    </div>

                                    <!-- Transaction -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Transaction</label>
                                        <RecordSelect
                                            id="transaction_id"
                                            :field="fieldsSchema?.transaction_id || { type: 'relationship', relationship_type: 'transactions', label: 'Transaction' }"
                                            v-model="form.transaction_id"
                                            :record="pseudoRecord"
                                            field-key="transaction_id"
                                            :disabled="isView"
                                        />
                                        <p v-if="form.errors.transaction_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.transaction_id }}</p>
                                    </div>

                                    <!-- Contract -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contract</label>
                                        <RecordSelect
                                            id="contract_id"
                                            :field="fieldsSchema?.contract_id || { type: 'relationship', relationship_type: 'contracts', label: 'Contract' }"
                                            v-model="form.contract_id"
                                            :record="pseudoRecord"
                                            field-key="contract_id"
                                            :disabled="isView"
                                        />
                                        <p v-if="form.errors.contract_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.contract_id }}</p>
                                    </div>
                                </div>

                                <!-- Invoice Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Invoice Details
                                    </h3>

                                    <!-- Status -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.status" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                            <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.status }}</p>
                                    </div>

                                    <!-- Due Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Due Date</label>
                                        <input
                                            type="date"
                                            v-model="form.due_at"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                        />
                                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.due_at }}</p>
                                    </div>

                                    <!-- Payment Term -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Payment Terms</label>
                                        <select v-model="form.payment_term" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                            <option value="">— Select —</option>
                                            <option v-for="opt in paymentTermOptions" :key="opt.id ?? opt.value" :value="opt.value ?? opt.id">{{ opt.name }}</option>
                                        </select>
                                        <p v-if="form.errors.payment_term" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_term }}</p>
                                    </div>

                                    <!-- Currency -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Currency</label>
                                        <input
                                            type="text"
                                            v-model="form.currency"
                                            :disabled="isView"
                                            :class="[inputClass, isView ? disabledClass : '']"
                                            placeholder="USD"
                                        />
                                        <p v-if="form.errors.currency" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.currency }}</p>
                                    </div>
                                </div>
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
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Billing Address
                                    </h3>
                                    <span v-if="isFetchingTaxRate" class="text-xs text-primary-600 dark:text-primary-400 animate-pulse">
                                        Fetching tax rate…
                                    </span>
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
                        </div>
                    </div>

                    <!-- Notes Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Notes</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Visible to the customer on the invoice</p>
                        </div>
                        <div class="p-6">
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                :disabled="isView"
                                :class="[textareaClass, isView ? disabledClass : '']"
                                placeholder="Add a note or message for the customer..."
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
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
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.subtotal) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Discount</span>
                                    <span class="font-medium text-gray-900 dark:text-white">-{{ formatCurrency(record.discount_total) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.tax_total) }}</span>
                                </li>
                                <li class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Fees</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.fees_total) }}</span>
                                </li>
                                <li class="flex justify-between font-bold text-base text-gray-900 dark:text-white pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <span>Total</span>
                                    <span>{{ formatCurrency(record.total) }}</span>
                                </li>
                                <li v-if="record.amount_paid" class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Amount Paid</span>
                                    <span>{{ formatCurrency(record.amount_paid) }}</span>
                                </li>
                                <li v-if="record.amount_due != null" class="flex justify-between font-bold text-primary-600 dark:text-primary-400 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span>Amount Due</span>
                                    <span>{{ formatCurrency(record.amount_due) }}</span>
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
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-[140px]">
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
                                    {{ paymentTermOptions.find(o => (o.value ?? o.id) == form.payment_term)?.name ?? '—' }}
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
                                    <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(record.total) }}</span>
                                </div>
                                <div v-if="record.amount_due != null" class="flex justify-between items-center">
                                    <span class="text-gray-500 dark:text-gray-400">Amount Due</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(record.amount_due) }}</span>
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

        <!-- ============================
             Billing Address Confirm Modal
             ============================ -->
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
                    v-if="showAddressConfirm && pendingCustomerAddress"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                    @click.self="dismissAddressConfirm"
                >
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                        <div class="flex items-start gap-3 px-6 pt-6 pb-4">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                                <span class="material-icons text-[20px] text-primary-600 dark:text-primary-400">location_on</span>
                            </div>
                            <div>
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white">Use customer's address?</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Set this as the billing address for the invoice</p>
                            </div>
                        </div>
                        <div class="mx-6 mb-5 px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300 space-y-0.5">
                            <p v-if="pendingCustomerAddress.address_line_1" class="font-medium">{{ pendingCustomerAddress.address_line_1 }}</p>
                            <p v-if="pendingCustomerAddress.address_line_2" class="text-gray-500 dark:text-gray-400">{{ pendingCustomerAddress.address_line_2 }}</p>
                            <p>
                                <span v-if="pendingCustomerAddress.city">{{ pendingCustomerAddress.city }}, </span>
                                <span v-if="pendingCustomerAddress.state">{{ pendingCustomerAddress.state }} </span>
                                <span v-if="pendingCustomerAddress.postal_code">{{ pendingCustomerAddress.postal_code }}</span>
                            </p>
                            <p v-if="pendingCustomerAddress.country" class="text-gray-500 dark:text-gray-400">{{ pendingCustomerAddress.country }}</p>
                        </div>
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                            <button type="button" @click="dismissAddressConfirm"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                No, keep blank
                            </button>
                            <button type="button" @click="confirmUseBillingAddress"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                                Yes, use this address
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
