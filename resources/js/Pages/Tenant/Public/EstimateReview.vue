<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
});

const ASSET_LINE_ITEM_TYPE = 'App\\Domain\\Asset\\Models\\Asset';

const consent = ref(false);
const showDeclineForm = ref(false);
const approvalError = ref('');

const approveForm = useForm({
    approval_note: '',
    consent: false,
});

const declineForm = useForm({
    decline_reason: '',
});

const statusIs = (val, id) => props.record.status == id || props.record.status === val;
const isApproved = computed(() => statusIs('approved', 4) || !!props.record.approved_at);
const isDeclined = computed(() => statusIs('declined', 5) || !!props.record.declined_at);
const canAct = computed(() => !isApproved.value && !isDeclined.value);

const companyName = computed(
    () => props.record.subsidiary?.display_name || props.account?.name || 'Company Name',
);

const locationLine1 = computed(() => {
    const loc = props.record.location;
    if (!loc) return '';
    const a1 = loc.address_line_1 ?? loc.address_line1;
    const a2 = loc.address_line_2 ?? loc.address_line2;
    const parts = [a1, a2].filter(Boolean);
    return parts.join(', ');
});

const locationLine2 = computed(() => {
    const loc = props.record.location;
    if (!loc) return '';
    const city = loc.city;
    const state = loc.state;
    const postal = loc.postal_code ?? loc.postalCode;
    const parts = [city, state].filter(Boolean);
    const line = parts.join(', ');
    return [line, postal].filter(Boolean).join(' ');
});

const formatCurrency = (value) =>
    value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

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

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
};

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    const cleaned = String(phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};

const footerPhone = computed(() => props.record.location?.phone || props.account?.phone || null);

const lineItemTotal = (item) => {
    const addonsTotal = (item.addons ?? []).reduce(
        (sum, a) => sum + Number(a.price || 0) * Number(a.quantity || 1),
        0,
    );
    const storedLine = item.line_total;
    if (storedLine != null && storedLine !== '' && !Number.isNaN(Number(storedLine))) {
        return Number(storedLine) + addonsTotal;
    }
    const qty = Number(item.quantity) || 1;
    const unitPrice = Number(item.unit_price) || 0;
    const discount = Number(item.discount) || 0;
    const base = Math.max(0, qty * unitPrice - discount);
    return base + addonsTotal;
};

const selectedOptionLabel = (opt) => {
    const name = (opt.option_name || '').trim();
    const val = (opt.value_label || '').trim();
    if (name && val) return `${name}: ${val}`;
    return name || val || 'Option';
};

const addonTotal = (addon) => Number(addon.price || 0) * Number(addon.quantity || 1);

const selectedOptionUnitPrice = (opt) => Number(opt?.price ?? 0);

const isAssetLineItem = (item) => item.itemable_type === ASSET_LINE_ITEM_TYPE;

const lineItemVariantLabel = (item) => {
    if (!isAssetLineItem(item) || !item.asset_variant_id) {
        return null;
    }
    return item.variant_display_name?.trim() || `Variant #${item.asset_variant_id}`;
};

const subtotal = computed(
    () => Number(props.record.subtotal) || props.record.line_items?.reduce((s, i) => s + lineItemTotal(i), 0) || 0,
);
const tax = computed(() => Number(props.record.tax) || 0);
const grandTotal = computed(() => Number(props.record.total) || subtotal.value + tax.value);
const taxRate = computed(() => Number(props.record.tax_rate) || 0);

const submitApproval = () => {
    approvalError.value = '';

    if (!consent.value) {
        approvalError.value = 'Please accept the acknowledgement to continue.';
        return;
    }

    approveForm.consent = consent.value;
    approveForm.post(route('estimates.approve', props.record.uuid), { preserveScroll: false });
};

const submitDecline = () => {
    declineForm.post(route('estimates.decline', props.record.uuid));
};

const handlePrint = () => window.print();

const hasLegacySignature = computed(
    () => !!(props.record.signature_url || (props.record.signed_name && String(props.record.signed_name).trim())),
);

const ackText = computed(() => {
    const raw = props.account?.service_ticket_ack_text;
    if (!raw) return '';
    return String(raw).replace('[COMPANY NAME]', companyName.value);
});

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
</script>

<template>
    <Head :title="`Estimate ${record.display_name ?? record.id}`" />

    <div class="min-h-screen bg-gray-100">
        <!-- ======================== DECLINED STATE ======================== -->
        <div v-if="isDeclined" class="min-h-screen flex flex-col">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-red-600 px-8 py-10 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 mb-4">
                            <span class="material-icons text-white text-4xl">cancel</span>
                        </div>
                        <h1 class="text-2xl font-bold text-white">Estimate Declined</h1>
                        <p class="text-red-100 mt-2">
                            Estimate <strong>{{ record.display_name }}</strong> has been declined.
                        </p>
                    </div>
                    <div class="px-8 py-8 space-y-4">
                        <p class="text-sm text-gray-600 text-center">
                            If you have any questions or would like to discuss alternatives, please contact us<span v-if="footerPhone"> at {{ formatPhoneNumber(footerPhone) }}</span>.
                        </p>
                        <div v-if="record.decline_reason" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Reason</p>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ record.decline_reason }}</p>
                        </div>
                        <p class="text-xs text-gray-500 text-center">Declined on {{ formatDateTime(record.declined_at) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== REVIEW / APPROVED (printable document) ==================== -->
        <div v-else>
            <div
                id="estimate-print-root"
                class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:mx-0 print:max-w-none"
            >
                <div class="mb-4 flex justify-end print:hidden">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        @click="handlePrint"
                    >
                        <span class="material-icons text-base">print</span>
                        Print
                    </button>
                </div>

                <!-- Approved Banner -->
                <div
                    v-if="isApproved"
                    class="mb-4 bg-green-600 text-white rounded-t-lg px-6 py-4 print:px-0 flex items-center gap-4 print:rounded-none print:bg-white print:text-green-700 print:border-2 print:border-green-600 print:mb-0"
                >
                    <span class="material-icons text-3xl">check_circle</span>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold leading-tight">Estimate Approved</h2>
                        <p class="text-sm text-green-50 print:text-green-700">
                            Approved on {{ formatDateTime(record.approved_at) }}
                            <span v-if="record.signed_name"> by {{ record.signed_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="bg-white shadow-lg print:shadow-none">
                    <!-- Company Header (matches ServiceTicketReview) -->
                    <div class="border-b-4 border-gray-900 px-8 print:px-0 py-6 print:border-b-2">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-6 min-w-0">
                                <div v-if="logoUrl" class="flex-shrink-0">
                                    <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto max-w-[180px] object-contain" />
                                </div>
                                <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                    <span class="material-icons text-4xl text-gray-400">business</span>
                                </div>
                                <div class="min-w-0">
                                    <h1 class="text-2xl font-bold text-gray-900">
                                        {{ companyName }}
                                    </h1>
                                    <div class="mt-2 text-sm text-gray-600 space-y-1">
                                        <p v-if="locationLine1">{{ locationLine1 }}</p>
                                        <p v-if="locationLine2">{{ locationLine2 }}</p>
                                        <p v-if="record.location?.phone" class="flex items-center gap-1">
                                            <span class="material-icons text-sm">phone</span>
                                            {{ record.location.phone }}
                                        </p>
                                        <p v-if="record.location?.email" class="flex items-center gap-1">
                                            <span class="material-icons text-sm">email</span>
                                            {{ record.location.email }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-medium text-gray-600 uppercase">Estimate</div>
                                <div class="text-3xl font-bold text-gray-900 font-mono">
                                    {{ record.display_name }}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    {{ formatDate(record.issue_date || record.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer & estimate meta -->
                    <div class="px-8 print:px-0 py-6 bg-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="space-y-2">
                                        <div class="font-semibold text-gray-900 text-lg">
                                            {{ record.customer?.display_name ?? record.customer_name ?? '—' }}
                                        </div>
                                        <div
                                            v-if="record.customer?.email || record.customer_email"
                                            class="flex items-center gap-2 text-sm text-gray-600"
                                        >
                                            <span class="material-icons text-sm">email</span>
                                            {{ record.customer?.email || record.customer_email }}
                                        </div>
                                        <div
                                            v-if="record.customer?.phone || record.customer_phone"
                                            class="flex items-center gap-2 text-sm text-gray-600"
                                        >
                                            <span class="material-icons text-sm">phone</span>
                                            {{ record.customer?.phone || record.customer_phone }}
                                        </div>
                                        <div
                                            v-if="record.billing_address_line1 || record.billing_city"
                                            class="flex items-start gap-2 text-sm text-gray-600 mt-3"
                                        >
                                            <span class="material-icons text-sm mt-0.5">location_on</span>
                                            <div>
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
                            </div>
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Estimate Details</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200 space-y-3 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-gray-500">Sales contact</span>
                                        <span class="font-medium text-gray-900 text-right">{{ record.user?.display_name ?? record.user?.name ?? '—' }}</span>
                                    </div>
                                    <div v-if="record.issue_date" class="flex justify-between gap-4">
                                        <span class="text-gray-500">Issue date</span>
                                        <span class="font-medium text-gray-900">{{ formatDate(record.issue_date) }}</span>
                                    </div>
                                    <div v-if="record.expiration_date" class="flex justify-between gap-4">
                                        <span class="text-gray-500">Valid until</span>
                                        <span class="font-medium text-gray-900">{{ formatDate(record.expiration_date) }}</span>
                                    </div>
                                    <div v-if="record.opportunity?.display_name" class="flex justify-between gap-4">
                                        <span class="text-gray-500">Opportunity</span>
                                        <span class="font-medium text-gray-900 text-right">{{ record.opportunity.display_name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div v-if="record.line_items?.length" class="px-8 print:px-0 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Line Items</h2>
                        <table class="w-full">
                            <thead>
                                <tr class="border-b-2 border-gray-900">
                                    <th class="text-left py-3 text-sm font-semibold text-gray-900">Description</th>
                                    <th class="text-left py-3 text-sm font-semibold text-gray-900 min-w-[6rem]">Variant</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900">Qty</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900">Unit Price</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template v-for="item in record.line_items" :key="item.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 pr-4">
                                            <div class="font-medium text-gray-900">{{ item.name || '—' }}</div>
                                        </td>
                                        <td class="py-3 text-sm text-gray-700">
                                            <span v-if="lineItemVariantLabel(item)" class="font-medium text-gray-900">
                                                {{ lineItemVariantLabel(item) }}
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="py-3 text-right text-gray-900">{{ item.quantity }}</td>
                                        <td class="py-3 text-right text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="py-3 text-right font-medium text-gray-900">{{ formatCurrency(lineItemTotal(item)) }}</td>
                                    </tr>
                                    <tr
                                        v-for="(opt, optIdx) in item.selected_options || []"
                                        :key="'opt-' + item.id + '-' + optIdx"
                                        class="bg-sky-50/70 dark:bg-sky-900/20"
                                    >
                                        <td class="pl-8 pr-4 py-2 text-sm text-gray-700" colspan="2">
                                            <span class="text-sky-600/90 dark:text-sky-400 mr-1">↳</span>{{ selectedOptionLabel(opt) }}
                                        </td>
                                        <td class="py-2 text-right text-sm text-gray-600">1</td>
                                        <td class="py-2 text-right text-sm text-gray-600">{{ formatCurrency(selectedOptionUnitPrice(opt)) }}</td>
                                        <td class="py-2 text-right text-sm font-medium text-gray-800">
                                            {{ formatCurrency(selectedOptionUnitPrice(opt)) }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="addon in item.addons || []"
                                        :key="'addon-' + addon.id"
                                        class="bg-gray-50"
                                    >
                                        <td class="pl-8 pr-4 py-2 text-sm text-gray-600 italic" colspan="2">
                                            ↳ {{ addon.name }}
                                        </td>
                                        <td class="py-2 text-right text-sm text-gray-600">{{ addon.quantity }}</td>
                                        <td class="py-2 text-right text-sm text-gray-600">{{ formatCurrency(addon.price) }}</td>
                                        <td class="py-2 text-right text-sm font-medium text-gray-700">{{ formatCurrency(addonTotal(addon)) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="px-8 print:px-0 py-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex justify-end">
                            <div class="w-full md:w-1/2 lg:w-1/3 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium text-gray-900">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div v-if="taxRate > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax ({{ taxRate }}%):</span>
                                    <span class="font-medium text-gray-900">{{ formatCurrency(tax) }}</span>
                                </div>
                                <div class="flex justify-between text-xl font-bold border-t-2 border-gray-900 pt-3">
                                    <span class="text-gray-900">Total:</span>
                                    <span class="text-gray-900">{{ formatCurrency(grandTotal) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes / Terms -->
                    <div
                        v-if="record.notes || record.terms"
                        class="px-8 print:px-0 py-6 border-t border-gray-200 print:break-inside-avoid"
                    >
                        <div v-if="record.notes" class="mb-6">
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Notes</h2>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.notes }}</p>
                            </div>
                        </div>
                        <div v-if="record.terms">
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Terms &amp; Conditions</h2>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.terms }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Legacy signature / approval note (approved) -->
                    <div
                        v-if="isApproved && hasLegacySignature"
                        class="px-8 print:px-0 py-6 border-t border-gray-200 print:break-inside-avoid"
                    >
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Signature</h2>
                        <div class="flex items-start gap-6 flex-wrap">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <img
                                    v-if="record.signature_url"
                                    :src="record.signature_url"
                                    alt="Customer Signature"
                                    class="max-h-24 w-auto"
                                />
                                <p v-else class="signature-cursive text-3xl text-gray-900">{{ record.signed_name }}</p>
                            </div>
                            <div class="text-sm text-gray-600 space-y-1 pt-1">
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

                    <div
                        v-if="isApproved && record.approval_note"
                        class="px-8 print:px-0 py-6 border-t border-gray-200"
                    >
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Approval note</h2>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ record.approval_note }}</p>
                    </div>

                    <!-- Customer authorization (pending only) -->
                    <div v-if="canAct" class="px-8 print:px-0 py-8 border-t-2 border-gray-900 print:hidden">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Customer Authorization</h2>

                        <div v-if="ackText" class="mb-8 p-5 bg-gray-50 border border-gray-200 rounded-lg">
                            <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">{{ ackText }}</p>
                        </div>

                        <label class="flex items-start gap-3 cursor-pointer mb-5">
                            <input
                                v-model="consent"
                                type="checkbox"
                                class="mt-0.5 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                            />
                            <span class="text-sm text-gray-700 leading-relaxed">
                                I have reviewed this estimate and confirm that the details and pricing look correct. I understand this approval allows the process to move forward and that a formal agreement may follow.
                            </span>
                        </label>

                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Approval note (optional)</label>
                            <textarea
                                v-model="approveForm.approval_note"
                                rows="2"
                                placeholder="Any additional notes or comments…"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-gray-900 focus:ring-0 resize-none"
                            />
                        </div>

                        <p v-if="approvalError" class="text-sm text-red-600 mb-4">{{ approvalError }}</p>

                        <div v-if="Object.keys(approveForm.errors).length" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="text-sm text-red-700 space-y-1">
                                <li v-for="(error, key) in approveForm.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <button
                                type="button"
                                :disabled="approveForm.processing"
                                class="inline-flex items-center justify-center gap-2 px-8 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm"
                                @click="submitApproval"
                            >
                                <span v-if="approveForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                                <span v-else class="material-icons text-sm">check_circle</span>
                                {{ approveForm.processing ? 'Submitting…' : 'Approve Estimate' }}
                            </button>
                            <button
                                v-if="!showDeclineForm"
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-gray-600 hover:text-red-600 transition-colors"
                                @click="showDeclineForm = true"
                            >
                                Decline
                            </button>
                        </div>

                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2 max-h-0"
                            enter-to-class="opacity-100 translate-y-0 max-h-96"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0 max-h-96"
                            leave-to-class="opacity-0 -translate-y-2 max-h-0"
                        >
                            <div v-if="showDeclineForm" class="mt-6 p-6 bg-red-50 border border-red-200 rounded-lg overflow-hidden">
                                <h3 class="text-sm font-semibold text-red-900 mb-3">Decline estimate</h3>
                                <p class="text-sm text-red-700 mb-4">Please provide a reason so we can assist you.</p>
                                <textarea
                                    v-model="declineForm.decline_reason"
                                    rows="3"
                                    placeholder="Reason for declining (required)…"
                                    class="w-full px-4 py-3 border border-red-300 rounded-lg text-sm focus:border-red-500 focus:ring-0 mb-4"
                                />
                                <p v-if="declineForm.errors.decline_reason" class="text-xs text-red-600 mb-2">
                                    {{ declineForm.errors.decline_reason }}
                                </p>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        :disabled="!declineForm.decline_reason?.trim() || declineForm.processing"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 rounded-lg transition-colors"
                                        @click="submitDecline"
                                    >
                                        <span v-if="declineForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                                        {{ declineForm.processing ? 'Submitting…' : 'Confirm decline' }}
                                    </button>
                                    <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="showDeclineForm = false">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 print:px-0 py-4 bg-gray-900 text-white text-center text-xs">
                        <p>Thank you for your business!</p>
                        <p v-if="footerPhone" class="mt-1">Questions? Call us at {{ formatPhoneNumber(footerPhone) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}

@media print {
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
    }

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
        margin: 0.35in 0.15in;
    }

    #estimate-print-root {
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
}
</style>
