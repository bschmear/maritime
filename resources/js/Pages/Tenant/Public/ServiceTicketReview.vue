<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
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

// --- Price helpers (same logic as ServiceTicketPreview) ---

const formatCurrency = (value) => {
    return value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch { return '—'; }
};

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    // Remove all non-numeric characters
    const cleaned = phone.replace(/\D/g, '');
    // Format as (XXX) XXX-XXXX
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone; // Return original if it doesn't match expected format
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', {
            month: 'long', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    } catch { return '—'; }
};

const getBillingTypeLabel = (billingType) => {
    const options = props.enumOptions?.billing_type || [];
    const option = options.find(opt => opt.value === billingType);
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
        case 3: default: total = quantity * rate; break;
    }
    if (item.warranty) total = 0;
    return total;
};

const billableLineItems = computed(() =>
    props.record.service_items?.filter(item => item.billable !== false) || []
);

const subtotal = computed(() =>
    billableLineItems.value.reduce((sum, item) => sum + calculateLineItemPrice(item), 0)
);

const taxAmount = computed(() => {
    const rate = Number(props.record.tax_rate) || 0;
    return subtotal.value * (rate / 100);
});

const grandTotal = computed(() => subtotal.value + taxAmount.value);

const estimateVariance = computed(() => {
    const threshold = Number(props.account.estimate_threshold_percent) || 20;
    return (grandTotal.value * threshold) / 100;
});

// --- Actions ---

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
    declineForm.post(route('service-tickets.decline', props.record.uuid));
};

const handlePrint = () => window.print();

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};
</script>

<template>
    <Head :title="`Service Ticket ${record.service_ticket_number}`" />

    <div class="min-h-screen bg-gray-100">
        <!-- ======================== DECLINED STATE ======================== -->
        <div v-if="isDeclined" class="min-h-screen flex flex-col">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-red-600 px-8 py-10 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 mb-4">
                            <span class="material-icons text-white text-4xl">cancel</span>
                        </div>
                        <h1 class="text-2xl font-bold text-white">Service Ticket Declined</h1>
                        <p class="text-red-100 mt-2">
                            Service ticket <strong>{{ record.service_ticket_number }}</strong> has been declined.
                        </p>
                    </div>
                    <div class="px-8 py-8">
                        <p class="text-sm text-gray-600 text-center">
                            If you have any questions or would like to discuss alternatives,
                            please contact us<span v-if="record.location?.phone"> at {{ record.location.phone }}</span>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== REVIEW / APPROVED STATE ==================== -->
        <div v-else>
            <div id="service-ticket-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
                <!-- Approved Banner (screen + print) -->
                <div
                    v-if="isApproved"
                    class="mb-4 bg-green-600 text-white rounded-t-lg px-6 py-4 flex items-center gap-4 print:rounded-none print:bg-white print:text-green-700 print:border-2 print:border-green-600 print:mb-0"
                >
                    <span class="material-icons text-3xl">check_circle</span>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold leading-tight">Service Ticket Approved</h2>
                        <p class="text-sm text-green-50 print:text-green-700">
                            Approved on {{ formatDateTime(record.signed_at) }}<span v-if="record.signed_name"> by {{ record.signed_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="bg-white shadow-lg print:shadow-none">

                    <!-- Company Header -->
                    <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-6">
                                <div v-if="logoUrl" class="flex-shrink-0">
                                    <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto max-w-[180px] object-contain" />
                                </div>
                                <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                    <span class="material-icons text-4xl text-gray-400">business</span>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900">
                                        {{ record.subsidiary?.display_name || 'Company Name' }}
                                    </h1>
                                    <div class="mt-2 text-sm text-gray-600 space-y-1">
                                        <p v-if="record.location?.address_line1">
                                            {{ record.location.address_line1 }}
                                            <span v-if="record.location?.address_line2">, {{ record.location.address_line2 }}</span>
                                        </p>
                                        <p v-if="record.location?.city">
                                            {{ record.location.city }}<span v-if="record.location?.state">, {{ record.location.state }}</span> {{ record.location?.postal_code }}
                                        </p>
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
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-600 uppercase">Service Ticket</div>
                                <div class="text-3xl font-bold text-gray-900 font-mono">
                                    #{{ record.service_ticket_number }}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    {{ formatDate(record.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer & Asset Information -->
                    <div class="px-8 py-6 bg-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="space-y-2">
                                        <div class="font-semibold text-gray-900 text-lg">
                                            {{ record.customer?.display_name || '—' }}
                                        </div>
                                        <div v-if="record.customer?.email" class="flex items-center gap-2 text-sm text-gray-600">
                                            <span class="material-icons text-sm">email</span>
                                            {{ record.customer.email }}
                                        </div>
                                        <div v-if="record.customer?.phone" class="flex items-center gap-2 text-sm text-gray-600">
                                            <span class="material-icons text-sm">phone</span>
                                            {{ record.customer.phone }}
                                        </div>
                                        <div v-if="record.customer?.address_line1" class="flex items-start gap-2 text-sm text-gray-600 mt-3">
                                            <span class="material-icons text-sm mt-0.5">location_on</span>
                                            <div>
                                                <div>{{ record.customer.address_line1 }}</div>
                                                <div v-if="record.customer?.address_line2">{{ record.customer.address_line2 }}</div>
                                                <div v-if="record.customer?.city">
                                                    {{ record.customer.city }}<span v-if="record.customer?.state">, {{ record.customer.state }}</span> {{ record.customer?.postal_code }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Asset Information -->
                            <div v-if="record.asset_unit">
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset Information</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="space-y-2">
                                        <div class="font-semibold text-gray-900 text-lg">
                                            {{ record.asset_unit?.display_name || '—' }}
                                        </div>
                                        <div v-if="record.asset_unit?.asset?.make?.display_name" class="text-sm text-gray-600">
                                            <span class="font-medium">Make:</span> {{ record.asset_unit.asset.make.display_name }}
                                        </div>
                                        <div v-if="record.asset_unit?.asset?.year" class="text-sm text-gray-600">
                                            <span class="font-medium">Year:</span> {{ record.asset_unit.asset.year }}
                                        </div>
                                        <div v-if="record.asset_unit?.serial_number" class="text-sm text-gray-600">
                                            <span class="font-medium">Serial:</span> {{ record.asset_unit.serial_number }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Repair Description -->
                    <div class="px-8 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Repair Description</h2>
                        <div class="prose prose-sm max-w-none">
                            <p class="text-gray-900 whitespace-pre-line">{{ record.repair_description || '—' }}</p>
                        </div>
                    </div>

                    <!-- Service Items -->
                    <div class="px-8 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Service Items</h2>

                        <table class="w-full">
                            <thead>
                                <tr class="border-b-2 border-gray-900">
                                    <th class="text-left py-3 text-sm font-semibold text-gray-900">Description</th>
                                    <th class="text-center py-3 text-sm font-semibold text-gray-900">Qty</th>
                                    <th class="text-center py-3 text-sm font-semibold text-gray-900">Type</th>
                                    <th class="text-center py-3 text-sm font-semibold text-gray-900">Est Hrs</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900">Rate</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in billableLineItems" :key="index" class="hover:bg-gray-50">
                                    <td class="py-3 pr-4">
                                        <div class="font-medium text-gray-900">{{ item.display_name }}</div>
                                        <div v-if="item.description && item.description !== item.display_name" class="text-sm text-gray-600 mt-1">
                                            {{ item.description }}
                                        </div>
                                        <div v-if="item.warranty" class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium mt-1">
                                            <span class="material-icons text-xs">verified_user</span>
                                            Warranty
                                        </div>
                                    </td>
                                    <td class="py-3 text-center text-gray-900">{{ item.quantity }}</td>
                                    <td class="py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ getBillingTypeLabel(item.billing_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-center text-gray-900">{{ item.estimated_hours ?? 0 }}</td>
                                    <td class="py-3 text-right text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="py-3 text-right font-medium text-gray-900">{{ formatCurrency(calculateLineItemPrice(item)) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="billableLineItems.length === 0" class="text-center py-8 text-gray-500">
                            No billable items
                        </div>
                    </div>

                    <!-- Estimate Variance Notice -->
                    <div v-if="account.estimate_threshold_percent" class="px-8 pb-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <span class="material-icons text-blue-600 text-xl flex-shrink-0">info</span>
                                <div class="text-sm text-blue-900">
                                    <p class="font-semibold mb-1">Estimate Variance Notice</p>
                                    <p>
                                        Our estimate may vary by {{ account.estimate_threshold_percent }}% (up to {{ formatCurrency(estimateVariance) }}).
                                        If the final cost exceeds this threshold, customer verification will be required before proceeding with additional work.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="px-8 py-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex justify-end">
                            <div class="w-full md:w-1/2 lg:w-1/3 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium text-gray-900">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax ({{ record.tax_rate }}%):</span>
                                    <span class="font-medium text-gray-900">{{ formatCurrency(taxAmount) }}</span>
                                </div>
                                <div class="flex justify-between text-xl font-bold border-t-2 border-gray-900 pt-3">
                                    <span class="text-gray-900">Total:</span>
                                    <span class="text-gray-900">{{ formatCurrency(grandTotal) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== SIGNATURE (APPROVED STATE) ==================== -->
                    <div
                        v-if="isApproved && (record.signature_url || (record.signature_method === 5 && record.customer_signature))"
                        class="px-8 py-6 border-t border-gray-200"
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
                                <p
                                    v-else
                                    class="signature-cursive text-3xl text-gray-900"
                                >
                                    {{ record.customer_signature }}
                                </p>
                            </div>
                            <div class="text-sm text-gray-600 space-y-1 pt-1">
                                <div><span class="text-gray-500">Signed by:</span> <span class="font-medium text-gray-900">{{ record.signed_name || '—' }}</span></div>
                                <div><span class="text-gray-500">Date:</span> <span class="font-medium text-gray-900">{{ formatDateTime(record.signed_at) }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== AUTHORIZATION SECTION (REVIEW ONLY) ==================== -->
                    <div v-if="canAct" class="px-8 py-8 border-t-2 border-gray-900 print:hidden">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Customer Authorization</h2>

                        <!-- Acknowledgement Text -->
                        <div v-if="account.service_ticket_ack_text" class="mb-8 p-5 bg-gray-50 border border-gray-200 rounded-lg">
                            <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">
                                {{ account.service_ticket_ack_text.replace('[COMPANY NAME]', record.subsidiary?.display_name || 'Company Name') }}
                            </p>
                        </div>

                        <!-- Signature Mode Toggle -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Signature</label>
                            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden">
                                <button
                                    @click="signatureMode = 'draw'"
                                    :class="[
                                        'px-5 py-2.5 text-sm font-medium transition-colors flex items-center gap-2',
                                        signatureMode === 'draw'
                                            ? 'bg-gray-900 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50',
                                    ]"
                                >
                                    <span class="material-icons text-sm">draw</span>
                                    Draw
                                </button>
                                <button
                                    @click="signatureMode = 'type'"
                                    :class="[
                                        'px-5 py-2.5 text-sm font-medium transition-colors flex items-center gap-2 border-l border-gray-300',
                                        signatureMode === 'type'
                                            ? 'bg-gray-900 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50',
                                    ]"
                                >
                                    <span class="material-icons text-sm">keyboard</span>
                                    Type
                                </button>
                            </div>
                        </div>

                        <!-- Draw Signature -->
                        <div v-show="signatureMode === 'draw'" class="mb-6">
                            <div class="border-2 border-gray-300 rounded-lg overflow-hidden bg-white relative">
                                <VueSignaturePad
                                    ref="signaturePadRef"
                                    width="100%"
                                    height="200px"
                                    :options="signaturePadOptions"
                                />
                                <div class="absolute bottom-4 left-4 right-4 border-b border-gray-300 pointer-events-none"></div>
                            </div>
                            <div class="flex items-center gap-3 mt-2">
                                <button
                                    @click="undoSignature"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                                >
                                    <span class="material-icons text-sm">undo</span>
                                    Undo
                                </button>
                                <button
                                    @click="clearSignature"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                                >
                                    <span class="material-icons text-sm">clear</span>
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Type Signature -->
                        <div v-show="signatureMode === 'type'" class="mb-6">
                            <input
                                v-model="typedSignature"
                                type="text"
                                placeholder="Type your full name"
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-lg focus:border-gray-900 focus:ring-0 transition-colors"
                            />
                            <!-- Live cursive preview -->
                            <div
                                v-if="typedSignature.trim()"
                                class="mt-4 border-2 border-gray-200 rounded-lg bg-white px-6 py-8 flex items-end justify-center"
                            >
                                <div class="text-center w-full">
                                    <p class="signature-cursive text-4xl text-gray-900 border-b border-gray-300 pb-2 inline-block min-w-[200px]">
                                        {{ typedSignature }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Print Name -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Print Name</label>
                            <input
                                v-model="approveForm.signed_name"
                                type="text"
                                placeholder="Your full legal name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-0 transition-colors"
                            />
                            <p v-if="approveForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ approveForm.errors.signed_name }}</p>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="mb-8">
                            <label class="flex items-start gap-3 cursor-pointer group">
                                <input
                                    v-model="consent"
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900 transition-colors"
                                />
                                <span class="text-sm text-gray-700 leading-relaxed">
                                    I acknowledge that I have reviewed the service details, line items, and estimated costs above.
                                    By signing, I authorize the work described to proceed as outlined.
                                </span>
                            </label>
                            <p v-if="approveForm.errors.consent" class="mt-1 text-sm text-red-600 ml-8">{{ approveForm.errors.consent }}</p>
                        </div>

                        <!-- Error Message -->
                        <div v-if="approvalError" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center gap-2 text-sm text-red-700">
                                <span class="material-icons text-sm">error_outline</span>
                                {{ approvalError }}
                            </div>
                        </div>

                        <!-- Server Errors -->
                        <div v-if="Object.keys(approveForm.errors).length" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="text-sm text-red-700 space-y-1">
                                <li v-for="(error, key) in approveForm.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-4">
                            <button
                                @click="submitApproval"
                                :disabled="approveForm.processing"
                                class="inline-flex items-center gap-2 px-8 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm"
                            >
                                <span v-if="approveForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                                <span v-else class="material-icons text-sm">check_circle</span>
                                {{ approveForm.processing ? 'Submitting...' : 'Approve & Sign' }}
                            </button>
                            <button
                                v-if="!showDeclineForm"
                                @click="showDeclineForm = true"
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-gray-600 hover:text-red-600 transition-colors"
                            >
                                Decline
                            </button>
                        </div>

                        <!-- Decline Form (expandable) -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2 max-h-0"
                            enter-to-class="opacity-100 translate-y-0 max-h-96"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0 max-h-96"
                            leave-to-class="opacity-0 -translate-y-2 max-h-0"
                        >
                            <div v-if="showDeclineForm" class="mt-6 p-6 bg-red-50 border border-red-200 rounded-lg overflow-hidden">
                                <h3 class="text-sm font-semibold text-red-900 mb-3">Decline Service Ticket</h3>
                                <p class="text-sm text-red-700 mb-4">
                                    Are you sure you want to decline this service ticket? This action cannot be undone.
                                </p>
                                <textarea
                                    v-model="declineForm.decline_reason"
                                    rows="3"
                                    placeholder="Reason for declining (optional)"
                                    class="w-full px-4 py-3 border border-red-300 rounded-lg text-sm focus:border-red-500 focus:ring-0 mb-4"
                                ></textarea>
                                <div class="flex items-center gap-3">
                                    <button
                                        @click="submitDecline"
                                        :disabled="declineForm.processing"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 rounded-lg transition-colors"
                                    >
                                        <span v-if="declineForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                                        {{ declineForm.processing ? 'Submitting...' : 'Confirm Decline' }}
                                    </button>
                                    <button
                                        @click="showDeclineForm = false"
                                        type="button"
                                        class="px-6 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-4 bg-gray-900 text-white text-center text-xs">
                        <p>Thank you for your business!</p>
                        <p v-if="record.location?.phone" class="mt-1">
                            Questions? Call us at {{ formatPhoneNumber(record.location.phone) }}
                        </p>
                    </div>
                </div>

                <!-- Print button (approved state, screen only) -->
                <div v-if="isApproved" class="mt-6 flex justify-center print:hidden">
                    <button
                        @click="handlePrint"
                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors shadow-sm"
                    >
                        <span class="material-icons text-sm">print</span>
                        Print Copy
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}

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
