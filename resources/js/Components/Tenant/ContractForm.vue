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

const STATUS_ENUM_KEY = 'App\\Enums\\Contract\\ContractStatus';
const PAYMENT_ENUM_KEY = 'App\\Enums\\Contract\\ContractPaymentStatus';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentStatusOptions = computed(() => props.enumOptions?.[PAYMENT_ENUM_KEY] ?? []);
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
    customer_id:            props.record?.customer_id            ?? props.initialData?.customer_id            ?? null,
    estimate_id:            props.record?.estimate_id            ?? props.initialData?.estimate_id            ?? null,
    transaction_id:         props.record?.transaction_id         ?? props.initialData?.transaction_id         ?? null,
    status:                 resolveEnumId(STATUS_ENUM_KEY,   props.record?.status   ?? props.initialData?.status   ?? 'draft'),
    payment_status:         resolveEnumId(PAYMENT_ENUM_KEY,  props.record?.payment_status ?? props.initialData?.payment_status ?? 'pending'),
    total_amount:           props.record?.total_amount           ?? props.initialData?.total_amount           ?? '',
    currency:               props.record?.currency               ?? props.initialData?.currency               ?? 'USD',
    payment_term:           props.record?.payment_term           ?? props.initialData?.payment_term           ?? 'due_on_receipt',
    payment_terms:          props.record?.payment_terms          ?? props.initialData?.payment_terms          ?? '',
    delivery_terms:         props.record?.delivery_terms         ?? props.initialData?.delivery_terms         ?? '',
    contract_terms:         props.record?.contract_terms         ?? props.initialData?.contract_terms         ?? '',
    notes:                  props.record?.notes                  ?? props.initialData?.notes                  ?? '',
    signature_required:     props.record?.signature_required     ?? props.initialData?.signature_required     ?? true,
    billing_address_line1:  props.record?.billing_address_line1  ?? props.initialData?.billing_address_line1  ?? '',
    billing_address_line2:  props.record?.billing_address_line2  ?? props.initialData?.billing_address_line2  ?? '',
    billing_city:           props.record?.billing_city           ?? props.initialData?.billing_city           ?? '',
    billing_state:          props.record?.billing_state          ?? props.initialData?.billing_state          ?? '',
    billing_postal:         props.record?.billing_postal         ?? props.initialData?.billing_postal         ?? '',
    billing_country:        props.record?.billing_country        ?? props.initialData?.billing_country        ?? '',
    billing_latitude:       props.record?.billing_latitude       ?? props.initialData?.billing_latitude       ?? null,
    billing_longitude:      props.record?.billing_longitude      ?? props.initialData?.billing_longitude      ?? null,
});

// ── Billing Address ──────────────────────────────────────────────────────────
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
    form.billing_latitude      = src.billing_latitude      ?? src.latitude       ?? null;
    form.billing_longitude     = src.billing_longitude     ?? src.longitude      ?? null;
};

const handleCustomerSelected = (customer) => {
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
    form.billing_state         = data.stateCode   || data.state || '';
    form.billing_postal        = data.postalCode  ?? '';
    form.billing_country       = data.countryCode || data.country || '';
    form.billing_latitude      = data.latitude    ?? null;
    form.billing_longitude     = data.longitude   ?? null;
};

watch(() => form.billing_state, async (newState) => {
    if (newState) {
        const rate = await fetchTaxRate({
            state:       newState,
            city:        form.billing_city          || undefined,
            postal_code: form.billing_postal        || undefined,
            line1:       form.billing_address_line1 || undefined,
            country:     form.billing_country       || undefined,
            latitude:    form.billing_latitude      ?? undefined,
            longitude:   form.billing_longitude     ?? undefined,
        });
        // contracts don't have a tax_rate field but you can extend here if needed
    }
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent';
const textareaClass = `${inputClass} resize-none`;
const disabledClass = 'opacity-60 cursor-not-allowed';

const submit = () => {
    if (isView.value) return;

    if (props.mode === 'edit') {
        form.put(route('contracts.update', props.record.id), {
            onSuccess: () => emit('saved'),
        });
    } else {
        form.post(route('contracts.store'), {
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
                                        {{ mode === 'edit' ? 'EDIT CONTRACT' : mode === 'show' ? 'CONTRACT' : 'NEW CONTRACT' }}
                                    </h1>
                                    <p class="text-primary-100 text-sm mt-1">
                                        {{ mode === 'edit' ? 'Update contract details' : mode === 'show' ? 'Contract details & terms' : 'Create a new contract' }}
                                    </p>
                                </div>
                                <div v-if="record?.contract_number || record?.sequence" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ record.contract_number || `#${record.sequence}` }}</div>
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

                                    <!-- Estimate -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Originating Estimate</label>
                                        <RecordSelect
                                            id="estimate_id"
                                            :field="fieldsSchema?.estimate_id || { type: 'relationship', relationship_type: 'estimates', label: 'Estimate' }"
                                            v-model="form.estimate_id"
                                            :record="pseudoRecord"
                                            field-key="estimate_id"
                                            :disabled="isView"
                                        />
                                        <p v-if="form.errors.estimate_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.estimate_id }}</p>
                                    </div>
                                </div>

                                <!-- Contract Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Contract Details
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

                                    <!-- Payment Status -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Payment Status <span class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.payment_status" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                            <option v-for="opt in paymentStatusOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                        </select>
                                        <p v-if="form.errors.payment_status" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_status }}</p>
                                    </div>

                                    <!-- Total Amount -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Total Amount <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                            <input
                                                type="number"
                                                v-model="form.total_amount"
                                                min="0"
                                                step="0.01"
                                                :disabled="isView"
                                                :class="[inputClass, 'pl-7', isView ? disabledClass : '']"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <p v-if="form.errors.total_amount" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.total_amount }}</p>
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

                                    <!-- Signature Required -->
                                    <div class="flex items-center gap-3">
                                        <input
                                            id="signature_required"
                                            type="checkbox"
                                            v-model="form.signature_required"
                                            :disabled="isView"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <label for="signature_required" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Signature Required
                                        </label>
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
                                    :latitude="form.billing_latitude"
                                    :longitude="form.billing_longitude"
                                    :disabled="isView"
                                    @update="handleAddressUpdate"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Notes Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Terms &amp; Notes</h2>
                        </div>
                        <div class="p-6 space-y-5">

                            <!-- Payment Term (dropdown) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Payment Term</label>
                                <select v-model="form.payment_term" :disabled="isView" :class="[inputClass, isView ? disabledClass : '']">
                                    <option value="">— Select —</option>
                                    <option v-for="opt in paymentTermOptions" :key="opt.id ?? opt.value" :value="opt.value ?? opt.id">{{ opt.name }}</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Standard payment schedule (e.g. Net 30)</p>
                                <p v-if="form.errors.payment_term" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_term }}</p>
                            </div>

                            <!-- Contract Terms -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contract Terms</label>
                                <textarea
                                    v-model="form.contract_terms"
                                    rows="4"
                                    :disabled="isView"
                                    :class="[textareaClass, isView ? disabledClass : '']"
                                    placeholder="General agreement and body terms..."
                                />
                                <p v-if="form.errors.contract_terms" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.contract_terms }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- Payment Terms -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Payment Terms</label>
                                    <textarea
                                        v-model="form.payment_terms"
                                        rows="3"
                                        :disabled="isView"
                                        :class="[textareaClass, isView ? disabledClass : '']"
                                        placeholder="Detailed payment terms text..."
                                    />
                                    <p v-if="form.errors.payment_terms" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_terms }}</p>
                                </div>

                                <!-- Delivery Terms -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Delivery Terms</label>
                                    <textarea
                                        v-model="form.delivery_terms"
                                        rows="3"
                                        :disabled="isView"
                                        :class="[textareaClass, isView ? disabledClass : '']"
                                        placeholder="Delivery terms and conditions..."
                                    />
                                    <p v-if="form.errors.delivery_terms" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.delivery_terms }}</p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    :disabled="isView"
                                    :class="[textareaClass, isView ? disabledClass : '']"
                                    placeholder="Internal notes..."
                                />
                                <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Info Card (show mode only, if signed) -->
                    <div v-if="isView && record?.signed_at" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Signature Info</h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div v-if="record.signed_name">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed By</p>
                                <p class="text-sm text-gray-900 dark:text-white">{{ record.signed_name }}</p>
                            </div>
                            <div v-if="record.signed_email">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Email</p>
                                <p class="text-sm text-gray-900 dark:text-white">{{ record.signed_email }}</p>
                            </div>
                            <div v-if="record.signed_at">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed At</p>
                                <p class="text-sm text-gray-900 dark:text-white">{{ new Date(record.signed_at).toLocaleString() }}</p>
                            </div>
                            <div v-if="record.signed_ip">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">IP Address</p>
                                <p class="text-sm font-mono text-gray-900 dark:text-white">{{ record.signed_ip }}</p>
                            </div>
                            <div v-if="record.signature_hash" class="sm:col-span-2">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signature Hash</p>
                                <p class="text-xs font-mono text-gray-500 dark:text-gray-400 break-all">{{ record.signature_hash }}</p>
                            </div>
                            <div v-if="record.docusign_envelope_id" class="sm:col-span-2">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">DocuSign Envelope</p>
                                <p class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ record.docusign_envelope_id }}</p>
                            </div>
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
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ form.processing ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Contract') }}
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

                    <!-- Contract Summary -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Contract Summary</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Status</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    {{ statusOptions.find(o => o.id == form.status)?.name ?? form.status ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Payment Status</span>
                                <span class="text-gray-900 dark:text-white font-medium">
                                    {{ paymentStatusOptions.find(o => o.id == form.payment_status)?.name ?? form.payment_status ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Signature Required</span>
                                <span class="text-gray-900 dark:text-white">{{ form.signature_required ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-600">
                                <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">
                                    {{ formatCurrency(form.total_amount) }}
                                </span>
                            </div>
                            <div class="pt-1 text-xs text-gray-400 dark:text-gray-500 text-right">
                                {{ form.currency || 'USD' }}
                            </div>
                        </div>
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
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white">Use customer's address?</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Set this as the billing address for the contract</p>
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
