<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
});

const signatureMode = ref('draw');
const signaturePadRef = ref(null);
const typedSignature = ref('');
const consent = ref(false);
const showDeclineForm = ref(false);
const approvalError = ref('');

const approveForm = useForm({
    signature_method: 'draw',
    signature_data: '',
    signed_name: '',
    consent: false,
});

const declineForm = useForm({
    decline_reason: '',
});

const isApproved = computed(() => props.record.approved);
const isDeclined = computed(() => !!props.record.declined_at);
const canAct = computed(() => !isApproved.value && !isDeclined.value);

const reviewHref = () => route('service-tickets.review', props.record.uuid);

const openPrintReview = () => {
    const u = new URL(reviewHref(), window.location.origin);
    u.searchParams.set('autoprint', '1');
    window.open(u.toString(), '_blank', 'noopener,noreferrer');
};

const formatCurrency = (value) => {
    return value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
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

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', {
            month: 'long', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    } catch {
        return '—';
    }
};

const getBillingTypeLabel = (billingType) => {
    const options = props.enumOptions?.billing_type || [];
    const option = options.find((opt) => opt.value === billingType);
    return option?.name || 'Unknown';
};

const calculateLineItemPrice = (item) => {
    const rate = Number(item.unit_price) || 0;
    const quantity = Number(item.quantity) || 1;
    const estimatedHours = Number(item.estimated_hours) || 0;
    let total = 0;
    switch (item.billing_type) {
        case 1: total = estimatedHours * rate; break;
        case 2: total = rate; break;
        case 3:
        default: total = quantity * rate; break;
    }
    if (item.warranty) total = 0;
    return total;
};

const billableLineItems = computed(() =>
    props.record.service_items?.filter((item) => item.billable !== false) || [],
);

const subtotal = computed(() =>
    billableLineItems.value.reduce((sum, item) => sum + calculateLineItemPrice(item), 0),
);

const taxAmount = computed(() => {
    const rate = Number(props.record.tax_rate) || 0;
    return subtotal.value * (rate / 100);
});

const grandTotal = computed(() => subtotal.value + taxAmount.value);

const ticketImages = computed(() => props.record.images || []);

const estimateVariance = computed(() => {
    const threshold = Number(props.account?.estimate_threshold_percent) || 20;
    return (grandTotal.value * threshold) / 100;
});

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature = () => signaturePadRef.value?.undoSignature();

const submitApproval = () => {
    approvalError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) return;
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            approvalError.value = 'Please draw your signature before approving.';
            return;
        }
        approveForm.signature_data = data;
        approveForm.signature_method = 'draw';
    } else {
        if (!typedSignature.value.trim()) {
            approvalError.value = 'Please type your signature before approving.';
            return;
        }
        approveForm.signature_data = typedSignature.value.trim();
        approveForm.signature_method = 'type';
    }

    if (!approveForm.signed_name.trim()) {
        approvalError.value = 'Please enter your printed name.';
        return;
    }

    if (!consent.value) {
        approvalError.value = 'Please accept the acknowledgement to continue.';
        return;
    }

    approveForm.consent = consent.value;
    approveForm.post(route('service-tickets.approve', props.record.uuid), {
        preserveScroll: false,
    });
};

const submitDecline = () => {
    declineForm.post(route('service-tickets.decline', props.record.uuid), {
        preserveScroll: false,
    });
};

const handlePrint = () => window.print();

onMounted(() => {
    try {
        const q = new URLSearchParams(window.location.search);
        if (q.get('autoprint') === '1') {
            setTimeout(() => window.print(), 800);
        }
    } catch {
        /* ignore */
    }
});

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};
</script>

<template>
    <ClientPortalLayout title="Service ticket">
        <Head :title="`Service ticket ${record.service_ticket_number}`" />

        <div id="portal-service-ticket-print">
        <!-- Declined -->
        <div v-if="isDeclined" class="space-y-4">
            <Link
                :href="route('portal.servicetickets')"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 print:hidden"
            >
                <span class="material-icons text-lg">arrow_back</span>
                All service tickets
            </Link>
            <div class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
                <div class="bg-red-600 px-6 py-8 text-center text-white">
                    <span class="material-icons mb-2 text-4xl">cancel</span>
                    <h1 class="text-xl font-bold">Service ticket declined</h1>
                    <p class="mt-2 text-sm text-red-100">
                        Ticket <strong>#{{ record.service_ticket_number }}</strong> was declined.
                    </p>
                </div>
                <div class="space-y-4 px-5 py-6 text-sm text-gray-600">
                    <p v-if="record.decline_reason" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-800">
                        <span class="font-medium text-gray-700">Reason:</span>
                        {{ record.decline_reason }}
                    </p>
                    <p class="text-center">
                        Questions?
                        <span v-if="record.location?.phone">Call {{ formatPhoneNumber(record.location.phone) }}.</span>
                        <span v-else>Please contact us.</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Review / approved -->
        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <Link
                    :href="route('portal.servicetickets')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700"
                >
                    <span class="material-icons text-lg">arrow_back</span>
                    All service tickets
                </Link>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="reviewHref()"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                    >
                        <span class="material-icons text-sm">open_in_new</span>
                        Standalone review
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="handlePrint"
                    >
                        <span class="material-icons text-sm">print</span>
                        Print
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="openPrintReview"
                    >
                        <span class="material-icons text-sm">print</span>
                        Print (full layout)
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div
                    v-if="isApproved"
                    class="flex items-center gap-3 border-b border-green-200 bg-green-600 px-5 py-4 text-white print:border-green-600 print:bg-white print:text-green-800"
                >
                    <span class="material-icons text-3xl print:text-green-700">check_circle</span>
                    <div>
                        <h2 class="text-lg font-bold leading-tight">Approved</h2>
                        <p class="text-sm text-green-50 print:text-green-700">
                            {{ formatDateTime(record.signed_at) }}<span v-if="record.signed_name"> · {{ record.signed_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="border-b border-gray-100 px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Service ticket</p>
                            <h1 class="text-xl font-semibold text-gray-900">
                                #{{ record.service_ticket_number }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-500">{{ formatDate(record.created_at) }}</p>
                            <p v-if="record.subsidiary?.display_name" class="mt-2 text-sm text-gray-600">
                                {{ record.subsidiary.display_name }}
                            </p>
                        </div>
                        <div v-if="logoUrl" class="shrink-0">
                            <img :src="logoUrl" alt="" class="h-12 w-auto max-w-[140px] object-contain" />
                        </div>
                    </div>
                </div>

                <div v-if="record.location" class="border-b border-gray-100 px-5 py-4 text-sm text-gray-600">
                    <p v-if="record.location.address_line1">
                        {{ record.location.address_line1 }}
                        <span v-if="record.location.address_line2">, {{ record.location.address_line2 }}</span>
                    </p>
                    <p v-if="record.location.city">
                        {{ record.location.city }}<span v-if="record.location.state">, {{ record.location.state }}</span>
                        {{ record.location.postal_code }}
                    </p>
                    <p v-if="record.location.phone" class="mt-1">{{ formatPhoneNumber(record.location.phone) }}</p>
                    <p v-if="record.location.email">{{ record.location.email }}</p>
                </div>

                <div v-if="record.customer || record.asset_unit" class="grid gap-4 border-b border-gray-100 px-5 py-5 md:grid-cols-2">
                    <div v-if="record.customer">
                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Customer</h2>
                        <p class="font-medium text-gray-900">{{ record.customer.display_name || '—' }}</p>
                        <p v-if="record.customer.email" class="text-sm text-gray-600">{{ record.customer.email }}</p>
                        <p v-if="record.customer.phone" class="text-sm text-gray-600">{{ record.customer.phone }}</p>
                    </div>
                    <div v-if="record.asset_unit">
                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Asset</h2>
                        <p class="font-medium text-gray-900">{{ record.asset_unit.display_name || '—' }}</p>
                        <p v-if="record.asset_unit.asset?.make?.display_name" class="text-sm text-gray-600">
                            {{ record.asset_unit.asset.make.display_name }}
                            <span v-if="record.asset_unit.asset.year"> · {{ record.asset_unit.asset.year }}</span>
                        </p>
                        <p v-if="record.asset_unit.serial_number" class="text-sm text-gray-600">
                            Serial: {{ record.asset_unit.serial_number }}
                        </p>
                    </div>
                </div>

                <div class="border-b border-gray-100 px-5 py-5">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Repair description</h2>
                    <p class="whitespace-pre-line text-gray-900">{{ record.repair_description || '—' }}</p>
                </div>

                <div v-if="ticketImages.length" class="border-b border-gray-100 px-5 py-5">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Photos</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        <a
                            v-for="img in ticketImages"
                            :key="img.id"
                            :href="img.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-100"
                        >
                            <img
                                :src="img.url"
                                :alt="img.display_name || 'Ticket photo'"
                                class="h-full w-full object-cover transition group-hover:opacity-95"
                                loading="lazy"
                            />
                            <span
                                v-if="img.is_primary"
                                class="absolute left-1 top-1 rounded bg-primary-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white"
                            >
                                Primary
                            </span>
                        </a>
                    </div>
                </div>

                <!-- Line items -->
                <div class="border-b border-gray-100 px-5 py-5">
                    <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Service items</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr class="border-b-2 border-gray-900">
                                    <th class="py-2 text-left text-xs font-semibold text-gray-900">Description</th>
                                    <th class="py-2 text-center text-xs font-semibold text-gray-900">Qty</th>
                                    <th class="py-2 text-center text-xs font-semibold text-gray-900">Type</th>
                                    <th class="py-2 text-center text-xs font-semibold text-gray-900">Est hrs</th>
                                    <th class="py-2 text-right text-xs font-semibold text-gray-900">Rate</th>
                                    <th class="py-2 text-right text-xs font-semibold text-gray-900">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in billableLineItems" :key="item.id ?? index">
                                    <td class="py-3 pr-3">
                                        <div class="font-medium text-gray-900">{{ item.display_name }}</div>
                                        <div
                                            v-if="item.description && item.description !== item.display_name"
                                            class="mt-1 text-sm text-gray-600"
                                        >
                                            {{ item.description }}
                                        </div>
                                        <div
                                            v-if="item.warranty"
                                            class="mt-1 inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"
                                        >
                                            <span class="material-icons text-xs">verified_user</span>
                                            Warranty
                                        </div>
                                    </td>
                                    <td class="py-3 text-center text-sm text-gray-900">{{ item.quantity }}</td>
                                    <td class="py-3 text-center">
                                        <span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                            {{ getBillingTypeLabel(item.billing_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-center text-sm text-gray-900">{{ item.estimated_hours ?? 0 }}</td>
                                    <td class="py-3 text-right text-sm text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="py-3 text-right text-sm font-medium text-gray-900">
                                        {{ formatCurrency(calculateLineItemPrice(item)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="billableLineItems.length === 0" class="py-8 text-center text-sm text-gray-500">
                        No billable items
                    </div>
                </div>

                <div v-if="account?.estimate_threshold_percent" class="border-b border-gray-100 px-5 py-4">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        <p class="mb-1 font-semibold">Estimate variance notice</p>
                        <p>
                            Our estimate may vary by {{ account.estimate_threshold_percent }}% (up to {{ formatCurrency(estimateVariance) }}).
                            If the final cost exceeds this threshold, customer verification may be required before additional work.
                        </p>
                    </div>
                </div>

                <div class="border-b border-gray-100 bg-gray-50 px-5 py-5">
                    <div class="ml-auto w-full max-w-sm space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium text-gray-900">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax ({{ record.tax_rate ?? 0 }}%)</span>
                            <span class="font-medium text-gray-900">{{ formatCurrency(taxAmount) }}</span>
                        </div>
                        <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-bold text-gray-900">
                            <span>Total</span>
                            <span>{{ formatCurrency(grandTotal) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Signature (approved) -->
                <div
                    v-if="isApproved && (record.signature_url || (record.signature_method === 5 && record.customer_signature))"
                    class="border-b border-gray-100 px-5 py-5"
                >
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Customer signature</h2>
                    <div class="flex flex-wrap items-start gap-6">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <img
                                v-if="record.signature_url"
                                :src="record.signature_url"
                                alt="Customer signature"
                                class="max-h-24 w-auto"
                            />
                            <p v-else class="signature-cursive text-3xl text-gray-900">
                                {{ record.customer_signature }}
                            </p>
                        </div>
                        <div class="space-y-1 pt-1 text-sm text-gray-600">
                            <div>
                                <span class="text-gray-500">Signed by:</span>
                                <span class="font-medium text-gray-900">{{ record.signed_name || '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Date:</span>
                                <span class="font-medium text-gray-900">{{ formatDateTime(record.signed_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Authorize -->
                <div v-if="canAct" class="px-5 py-8 print:hidden">
                    <h2 class="mb-6 text-sm font-semibold uppercase tracking-wide text-gray-900">Customer authorization</h2>

                    <div v-if="account?.service_ticket_ack_text" class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">
                            {{ account.service_ticket_ack_text.replace('[COMPANY NAME]', record.subsidiary?.display_name || 'Company Name') }}
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="mb-3 block text-sm font-medium text-gray-700">Signature</label>
                        <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                            <button
                                type="button"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors',
                                    signatureMode === 'draw' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                ]"
                                @click="signatureMode = 'draw'"
                            >
                                <span class="material-icons text-sm">draw</span>
                                Draw
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'flex items-center gap-2 border-l border-gray-300 px-4 py-2.5 text-sm font-medium transition-colors',
                                    signatureMode === 'type' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                ]"
                                @click="signatureMode = 'type'"
                            >
                                <span class="material-icons text-sm">keyboard</span>
                                Type
                            </button>
                        </div>
                    </div>

                    <div v-show="signatureMode === 'draw'" class="mb-6">
                        <div class="relative overflow-hidden rounded-lg border-2 border-gray-300 bg-white">
                            <VueSignaturePad
                                ref="signaturePadRef"
                                width="100%"
                                height="200px"
                                :options="signaturePadOptions"
                            />
                            <div class="pointer-events-none absolute bottom-4 left-4 right-4 border-b border-gray-300" />
                        </div>
                        <div class="mt-2 flex gap-4">
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700" @click="undoSignature">
                                <span class="material-icons text-sm">undo</span>
                                Undo
                            </button>
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700" @click="clearSignature">
                                <span class="material-icons text-sm">clear</span>
                                Clear
                            </button>
                        </div>
                    </div>

                    <div v-show="signatureMode === 'type'" class="mb-6">
                        <input
                            v-model="typedSignature"
                            type="text"
                            placeholder="Type your full name"
                            class="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg focus:border-gray-900 focus:ring-0"
                        />
                        <div
                            v-if="typedSignature.trim()"
                            class="mt-4 flex items-end justify-center rounded-lg border-2 border-gray-200 bg-white px-6 py-8"
                        >
                            <p class="signature-cursive inline-block min-w-[200px] border-b border-gray-300 pb-2 text-center text-4xl text-gray-900">
                                {{ typedSignature }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Print name</label>
                        <input
                            v-model="approveForm.signed_name"
                            type="text"
                            placeholder="Your full legal name"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-gray-900 focus:ring-0"
                        />
                        <p v-if="approveForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ approveForm.errors.signed_name }}</p>
                    </div>

                    <div class="mb-6">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input v-model="consent" type="checkbox" class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
                            <span class="text-sm leading-relaxed text-gray-700">
                                I acknowledge that I have reviewed the service details, line items, and estimated costs above.
                                By signing, I authorize the work described to proceed as outlined.
                            </span>
                        </label>
                        <p v-if="approveForm.errors.consent" class="mt-1 text-sm text-red-600">{{ approveForm.errors.consent }}</p>
                    </div>

                    <div v-if="approvalError" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {{ approvalError }}
                    </div>
                    <div v-if="Object.keys(approveForm.errors).length" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                        <ul class="space-y-1 text-sm text-red-700">
                            <li v-for="(error, key) in approveForm.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            :disabled="approveForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitApproval"
                        >
                            <span v-if="approveForm.processing" class="material-icons animate-spin text-sm">refresh</span>
                            <span v-else class="material-icons text-sm">check_circle</span>
                            {{ approveForm.processing ? 'Submitting…' : 'Approve & sign' }}
                        </button>
                        <button
                            v-if="!showDeclineForm"
                            type="button"
                            class="text-sm font-medium text-gray-600 hover:text-red-600"
                            @click="showDeclineForm = true"
                        >
                            Decline
                        </button>
                    </div>

                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="max-h-0 -translate-y-2 opacity-0"
                        enter-to-class="max-h-96 translate-y-0 opacity-100"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="max-h-96 translate-y-0 opacity-100"
                        leave-to-class="max-h-0 -translate-y-2 opacity-0"
                    >
                        <div v-if="showDeclineForm" class="mt-6 overflow-hidden rounded-lg border border-red-200 bg-red-50 p-5">
                            <h3 class="mb-2 text-sm font-semibold text-red-900">Decline this ticket</h3>
                            <p class="mb-3 text-sm text-red-800">This cannot be undone.</p>
                            <textarea
                                v-model="declineForm.decline_reason"
                                rows="3"
                                placeholder="Reason (optional)"
                                class="mb-4 w-full rounded-lg border border-red-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-0"
                            />
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    :disabled="declineForm.processing"
                                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                    @click="submitDecline"
                                >
                                    <span v-if="declineForm.processing" class="material-icons animate-spin text-sm">refresh</span>
                                    {{ declineForm.processing ? 'Submitting…' : 'Confirm decline' }}
                                </button>
                                <button type="button" class="text-sm font-medium text-gray-600 hover:text-gray-900" @click="showDeclineForm = false">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <div v-if="record.location?.phone" class="bg-gray-900 px-5 py-3 text-center text-xs text-white print:hidden">
                    Questions? {{ formatPhoneNumber(record.location.phone) }}
                </div>
            </div>
        </div>
        </div>
    </ClientPortalLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}

@media print {
    .print\:hidden {
        display: none !important;
    }
}
</style>
