<script setup>
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage();

const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';
const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    canPayOnline: { type: Boolean, default: false },
    paymentConstraints: {
        type: Object,
        default: () => ({
            allow_partial_payment: false,
            minimum_partial_amount: null,
            amount_due: 0,
            amount_paid: 0,
            surcharge_percent: 0,
        }),
    },
});

const title = computed(() =>
    props.record?.display_name ? `Invoice ${props.record.display_name}` : 'Invoice',
);

const accountDisplayName = computed(() =>
    props.account?.settings?.business_name ?? props.account?.business_name ?? 'Company Name',
);

/** Same shape as service ticket: subsidiary + location on record or nested under transaction. */
const headerSubsidiary = computed(
    () => props.record?.transaction?.subsidiary ?? props.record?.subsidiary ?? null,
);

const headerLocation = computed(
    () => props.record?.transaction?.location ?? props.record?.location ?? null,
);

const companyPhone = computed(() => headerLocation.value?.phone ?? null);
const companyEmail = computed(() => headerLocation.value?.email ?? null);

const paymentTermLabel = computed(() => {
    const raw = props.record?.payment_term;
    const opts = props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? [];
    const opt = opts.find(o => o.value === raw || String(o.value) === String(raw) || String(o.id) === String(raw));
    return opt?.name ?? raw ?? null;
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
    if (opt?.bgClass) return opt.bgClass;
    const map = {
        draft: 'bg-gray-100 text-gray-700',
        sent: 'bg-blue-100 text-blue-700',
        viewed: 'bg-indigo-100 text-indigo-700',
        partial: 'bg-amber-100 text-amber-800',
        paid: 'bg-green-100 text-green-700',
        void: 'bg-red-100 text-red-700',
    };
    const v = typeof s === 'string' ? s : opts.find(o => o.id == s)?.value;
    return map[v] ?? map.draft;
});

const invoiceHeaderTitle = computed(
    () => props.record.display_name || `#${props.record.sequence ?? props.record.id}`,
);

const currencyLabel = computed(() => {
    const c = props.record?.currency_code ?? props.record?.currency;
    return c ? String(c).toUpperCase() : null;
});

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch {
        return '—';
    }
};

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    const cleaned = phone.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const due = computed(() => Number(props.paymentConstraints?.amount_due ?? props.record?.amount_due ?? 0));
const paid = computed(() => Number(props.paymentConstraints?.amount_paid ?? props.record?.amount_paid ?? 0));
const allowPartial = computed(() => props.paymentConstraints?.allow_partial_payment === true);
const minPartial = computed(() =>
    props.paymentConstraints?.minimum_partial_amount != null
        ? Number(props.paymentConstraints.minimum_partial_amount)
        : null,
);
const surchargePct = computed(() => Number(props.paymentConstraints?.surcharge_percent ?? 0));

const payForm = useForm({
    amount: due.value > 0 ? Math.round(due.value * 100) / 100 : 0,
});

watch(due, (v) => {
    if (v > 0 && !allowPartial.value) {
        payForm.amount = Math.round(v * 100) / 100;
    }
});

const principalNum = computed(() => {
    const n = Number(payForm.amount);
    return Number.isFinite(n) ? n : 0;
});

const surchargeAmount = computed(() => {
    if (surchargePct.value <= 0 || principalNum.value <= 0) {
        return 0;
    }
    return Math.round(principalNum.value * (surchargePct.value / 100) * 100) / 100;
});

const totalCharged = computed(() =>
    Math.round((principalNum.value + surchargeAmount.value) * 100) / 100,
);

const amountError = computed(() => {
    if (!props.canPayOnline || due.value <= 0) {
        return null;
    }
    const p = principalNum.value;
    if (p <= 0) {
        return 'Enter an amount greater than zero.';
    }
    if (p > due.value + 0.01) {
        return 'Amount cannot exceed the balance due.';
    }
    if (!allowPartial.value && Math.abs(p - due.value) > 0.02) {
        return 'This invoice must be paid in full.';
    }
    if (allowPartial.value && minPartial.value != null && p + 0.0001 < minPartial.value) {
        return `Minimum payment is ${formatCurrency(minPartial.value)}.`;
    }
    if (totalCharged.value > 0 && totalCharged.value * 100 < 50) {
        return 'Total charge (including any surcharge) must be at least $0.50.';
    }
    return null;
});

const payDisabled = computed(
    () =>
        !props.canPayOnline
        || due.value <= 0
        || payForm.processing
        || amountError.value != null,
);

const setPayInFull = () => {
    payForm.amount = Math.round(due.value * 100) / 100;
};

const submitPay = () => {
    if (payDisabled.value) {
        return;
    }
    payForm.post(route('invoices.pay', { uuid: props.record.uuid }), {
        preserveScroll: true,
    });
};

const hasPortalLogin = computed(() => route().has('portal.login'));
const hasPortalRegister = computed(() => route().has('portal.register'));
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-100">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <p class="mb-4 text-center text-sm text-gray-500">
                Invoice from
                {{ account?.settings?.business_name ?? account?.business_name ?? 'your service provider' }}
            </p>

            <div
                v-if="page.props.flash?.success"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ page.props.flash.error }}
            </div>

            <div class="bg-white shadow-lg print:shadow-none">
                <!-- Company header (match ServiceTicketReview) -->
                <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-6">
                            <div v-if="logoUrl" class="flex-shrink-0">
                                <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto max-w-[180px] object-contain">
                            </div>
                            <div v-else class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded bg-gray-200">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ headerSubsidiary?.display_name || accountDisplayName }}
                                </h1>
                                <div class="mt-2 space-y-1 text-sm text-gray-600">
                                    <p v-if="headerLocation && (headerLocation.address_line1 || headerLocation.address_line_1)">
                                        {{ headerLocation.address_line1 || headerLocation.address_line_1 }}<span
                                            v-if="headerLocation.address_line2 || headerLocation.address_line_2"
                                        >, {{ headerLocation.address_line2 || headerLocation.address_line_2 }}</span>
                                    </p>
                                    <p v-if="headerLocation?.city">
                                        {{ headerLocation.city }}<span v-if="headerLocation.state">, {{ headerLocation.state }}</span> {{ headerLocation.postal_code }}
                                    </p>
                                    <p v-if="companyPhone" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ formatPhoneNumber(companyPhone) }}
                                    </p>
                                    <p v-if="companyEmail" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">email</span>
                                        {{ companyEmail }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium uppercase text-gray-600">
                                Invoice
                            </div>
                            <div class="font-mono text-3xl font-bold text-gray-900">
                                {{ invoiceHeaderTitle }}
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ formatDate(record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer & invoice details -->
                <div class="bg-gray-50 px-8 py-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Customer information
                            </h2>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <div class="space-y-2 text-sm text-gray-600">
                                    <div class="text-lg font-semibold text-gray-900">
                                        {{ record.customer_name || '—' }}
                                    </div>
                                    <div v-if="record.customer_email" class="flex items-center gap-2">
                                        <span class="material-icons text-sm">email</span>
                                        {{ record.customer_email }}
                                    </div>
                                    <div v-if="record.customer_phone" class="flex items-center gap-2">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ record.customer_phone }}
                                    </div>
                                    <div
                                        v-if="record.billing_address_line1"
                                        class="mt-3 flex items-start gap-2"
                                    >
                                        <span class="material-icons mt-0.5 text-sm">location_on</span>
                                        <div>
                                            <div>{{ record.billing_address_line1 }}</div>
                                            <div v-if="record.billing_address_line2">
                                                {{ record.billing_address_line2 }}
                                            </div>
                                            <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                                                {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                            </div>
                                            <div v-if="record.billing_country" class="text-gray-500">
                                                {{ record.billing_country }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Invoice details
                            </h2>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <dl class="space-y-3 text-sm">
                                    <div class="flex flex-col gap-0.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Status
                                        </dt>
                                        <dd>
                                            <span
                                                :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', statusBadgeClass]"
                                            >
                                                {{ statusLabel }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="record.due_at" class="flex flex-col gap-0.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Due date
                                        </dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ formatDate(record.due_at) }}
                                        </dd>
                                    </div>
                                    <div v-if="paymentTermLabel" class="flex flex-col gap-0.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Payment terms
                                        </dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ paymentTermLabel }}
                                        </dd>
                                    </div>
                                    <div v-if="currencyLabel" class="flex flex-col gap-0.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Currency
                                        </dt>
                                        <dd class="font-medium text-gray-900">
                                            {{ currencyLabel }}
                                        </dd>
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Amount due
                                        </dt>
                                        <dd class="text-base font-bold text-gray-900">
                                            {{ formatCurrency(due) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <InvoiceDocumentBody
                    body-only
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                />

                <!-- Portal + payment (in document body; full width like service ticket) -->
                <div class="border-t-2 border-gray-900 px-8 py-8 print:hidden">
                    <div
                        v-if="hasPortalLogin || hasPortalRegister"
                        class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-5 sm:p-6"
                    >
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-900">
                            Customer portal
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            View estimates, invoices, and more
                        </p>
                        <div class="mt-4 flex flex-col gap-2 border-t border-gray-200 pt-4 sm:flex-row sm:flex-wrap">
                            <Link
                                v-if="hasPortalLogin"
                                :href="route('portal.login')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                            >
                                <span class="material-icons text-[18px]">person</span>
                                Sign in
                            </Link>
                            <Link
                                v-if="hasPortalRegister"
                                :href="route('portal.register')"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800"
                            >
                                <span class="material-icons text-[18px]">person_add</span>
                                Create account
                            </Link>
                        </div>
                    </div>

                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-900">
                        Payment
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Balance &amp; pay online
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">Paid to date</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ formatCurrency(paid) }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">Outstanding</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">
                                {{ formatCurrency(due) }}
                            </p>
                        </div>
                    </div>

                    <template v-if="canPayOnline && due > 0">
                        <div class="mt-5 border-t border-gray-200 pt-5">
                            <p class="text-sm text-gray-600">
                                Pay securely with card. You will be redirected to complete payment.
                            </p>

                            <div v-if="allowPartial" class="mt-4">
                                <label
                                    for="pay-amount"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Amount
                                </label>
                                <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                    <input
                                        id="pay-amount"
                                        v-model.number="payForm.amount"
                                        type="number"
                                        min="0.01"
                                        :max="due"
                                        step="0.01"
                                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:max-w-[11rem] sm:text-sm"
                                    >
                                    <button
                                        type="button"
                                        class="text-left text-sm font-medium text-gray-900 underline decoration-gray-400 hover:text-gray-700 sm:text-center"
                                        @click="setPayInFull"
                                    >
                                        Pay full balance
                                    </button>
                                </div>
                                <p v-if="minPartial != null" class="mt-1 text-xs text-gray-500">
                                    Minimum {{ formatCurrency(minPartial) }}
                                </p>
                            </div>

                            <div
                                v-if="surchargePct > 0 && principalNum > 0"
                                class="mt-4 space-y-1 border-t border-gray-200 pt-4 text-sm text-gray-600"
                            >
                                <div class="flex justify-between">
                                    <span>Card surcharge ({{ surchargePct }}%)</span>
                                    <span>{{ formatCurrency(surchargeAmount) }}</span>
                                </div>
                                <div class="flex justify-between font-medium text-gray-900">
                                    <span>Total charged</span>
                                    <span>{{ formatCurrency(totalCharged) }}</span>
                                </div>
                            </div>

                            <p v-if="amountError" class="mt-3 text-sm text-red-600">
                                {{ amountError }}
                            </p>
                            <p v-if="payForm.errors.amount" class="mt-3 text-sm text-red-600">
                                {{ payForm.errors.amount }}
                            </p>

                            <button
                                type="button"
                                class="stripe-button mt-5 inline-flex w-full max-w-md items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="payDisabled"
                                @click="submitPay"
                            >
                                <span
                                    class="material-icons text-[20px]"
                                    :class="{ 'animate-spin': payForm.processing }"
                                >{{ payForm.processing ? 'sync' : 'lock' }}</span>
                                {{ payForm.processing ? 'Starting…' : (allowPartial ? 'Pay now' : 'Pay with card') }}
                            </button>
                        </div>
                    </template>

                    <p
                        v-else-if="due > 0"
                        class="mt-5 border-t border-gray-200 pt-4 text-sm text-gray-500"
                    >
                        Online card payment isn’t available for this invoice. Use the instructions you
                        received or contact
                        {{ account?.settings?.business_name ?? account?.business_name ?? 'us' }}.
                    </p>

                    <p
                        v-else
                        class="mt-5 border-t border-gray-200 pt-4 text-sm font-medium text-green-700"
                    >
                        Paid in full. Thank you.
                    </p>
                </div>

                <div class="bg-gray-900 px-8 py-4 text-center text-xs text-white">
                    <p>Thank you for your business!</p>
                    <p v-if="companyPhone" class="mt-1">
                        Questions? Call us at {{ formatPhoneNumber(companyPhone) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .min-h-screen {
        min-height: auto !important;
        background: white !important;
    }

    .shadow-lg,
    .shadow-sm {
        box-shadow: none !important;
    }

    .bg-gray-100 {
        background: white !important;
    }

    @page {
        margin: 0.5in;
    }
}
</style>
