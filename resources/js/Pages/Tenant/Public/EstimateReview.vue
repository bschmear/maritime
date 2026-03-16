<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    record:  { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
});

const signatureMode   = ref('draw');
const signaturePadRef = ref(null);
const typedSignature  = ref('');
const consent         = ref(false);
const showDeclineForm = ref(false);
const approvalError   = ref('');

const approveForm = useForm({
    signature_method: 'draw',
    signature_data:   '',
    signed_name:      '',
    consent:          false,
});

const declineForm = useForm({
    decline_reason: '',
});

// status may be stored as integer id (4) or string ('approved') depending on context
const statusIs = (val, id) => props.record.status == id || props.record.status === val;
const isApproved = computed(() => statusIs('approved', 4) || !!props.record.signed_at);
const isDeclined = computed(() => statusIs('declined', 5) || !!props.record.declined_at);
const canAct     = computed(() => !isApproved.value && !isDeclined.value);

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
    } catch { return '—'; }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};

const lineItemTotal = (item) => {
    const qty       = Number(item.quantity) || 1;
    const unitPrice = Number(item.unit_price) || 0;
    const discount  = Number(item.discount) || 0;
    const base      = Math.max(0, qty * unitPrice - discount);
    const addonsTotal = (item.addons ?? []).reduce(
        (sum, a) => sum + Number(a.price || 0) * Number(a.quantity || 1), 0
    );
    return base + addonsTotal;
};

const addonTotal = (addon) =>
    Number(addon.price || 0) * Number(addon.quantity || 1);

const subtotal   = computed(() => Number(props.record.subtotal) || props.record.line_items?.reduce((s, i) => s + lineItemTotal(i), 0) || 0);
const tax        = computed(() => Number(props.record.tax) || 0);
const grandTotal = computed(() => Number(props.record.total) || subtotal.value + tax.value);
const taxRate    = computed(() => Number(props.record.tax_rate) || 0);

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature  = () => signaturePadRef.value?.undoSignature();

const submitApproval = () => {
    approvalError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) return;
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            approvalError.value = 'Please draw your signature before approving.';
            return;
        }
        approveForm.signature_data   = data;
        approveForm.signature_method = 'draw';
    } else {
        if (!typedSignature.value.trim()) {
            approvalError.value = 'Please type your signature before approving.';
            return;
        }
        approveForm.signature_data   = typedSignature.value.trim();
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
    approveForm.post(route('estimates.approve', props.record.uuid), { preserveScroll: false });
};

const submitDecline = () => {
    declineForm.post(route('estimates.decline', props.record.uuid));
};

const handlePrint = () => window.print();

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};
</script>

<template>
    <Head :title="`Estimate ${record.display_name ?? record.id}`" />

    <div class="min-h-screen bg-gray-100">

        <!-- ======================== APPROVED STATE ======================== -->
        <div v-if="isApproved" class="min-h-screen flex flex-col">
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 text-white py-10 px-4 text-center print:bg-emerald-600">
                <div class="max-w-2xl mx-auto">
                    <div class="flex justify-center mb-4">
                        <span class="material-icons text-5xl">check_circle</span>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">Estimate Approved</h1>
                    <p class="text-emerald-100 text-lg">{{ record.display_name }}</p>
                    <p class="text-emerald-200 text-sm mt-2">Approved on {{ formatDateTime(record.signed_at) }}</p>
                </div>
            </div>

            <div class="flex-1 max-w-2xl mx-auto w-full px-4 py-8 space-y-6">
                <!-- Signature display -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Approval Signature</h3>
                    <div v-if="record.signature_url" class="border border-gray-200 rounded-lg p-2 bg-gray-50">
                        <img :src="record.signature_url" alt="Signature" class="max-h-24 mx-auto" />
                    </div>
                    <p v-else-if="record.signed_name" class="signature-cursive text-4xl text-gray-800 text-center py-4">
                        {{ record.signed_name }}
                    </p>
                    <div class="mt-3 text-sm text-gray-500 grid grid-cols-2 gap-2">
                        <div><span class="font-medium">Signed by:</span> {{ record.signed_name }}</div>
                        <div><span class="font-medium">Date:</span> {{ formatDateTime(record.signed_at) }}</div>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Summary</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ formatCurrency(subtotal) }}</span></div>
                        <div v-if="taxRate > 0" class="flex justify-between text-gray-600"><span>Tax ({{ taxRate }}%)</span><span>{{ formatCurrency(tax) }}</span></div>
                        <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2 mt-2"><span>Total</span><span>{{ formatCurrency(grandTotal) }}</span></div>
                    </div>
                </div>

                <div class="text-center print:hidden">
                    <button @click="handlePrint" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                        <span class="material-icons text-base">print</span> Print / Save PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================== DECLINED STATE ======================== -->
        <div v-else-if="isDeclined" class="min-h-screen flex flex-col">
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white py-10 px-4 text-center">
                <div class="max-w-2xl mx-auto">
                    <div class="flex justify-center mb-4">
                        <span class="material-icons text-5xl">cancel</span>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">Estimate Declined</h1>
                    <p class="text-red-100 text-lg">{{ record.display_name }}</p>
                    <p class="text-red-200 text-sm mt-2">Declined on {{ formatDateTime(record.declined_at) }}</p>
                </div>
            </div>
            <div class="max-w-2xl mx-auto w-full px-4 py-8">
                <div v-if="record.decline_reason" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Reason Provided</h3>
                    <p class="text-gray-700">{{ record.decline_reason }}</p>
                </div>
            </div>
        </div>

        <!-- ======================== REVIEW STATE ======================== -->
        <div v-else class="max-w-3xl mx-auto px-4 py-8 space-y-6">

            <!-- Company Header -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-10 w-auto object-contain" />
                        <div>
                            <p class="text-white font-bold text-lg leading-tight">{{ account?.name ?? 'Company' }}</p>
                            <p class="text-primary-200 text-sm">Estimate for Approval</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-primary-200 text-xs">Estimate #</p>
                        <p class="text-white font-mono font-bold text-xl">{{ record.display_name }}</p>
                    </div>
                </div>
            </div>

            <!-- Customer & Estimate Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Estimate Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500 text-xs">Customer</p>
                        <p class="font-medium text-gray-900">{{ record.customer?.display_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs">Sales Contact</p>
                        <p class="font-medium text-gray-900">{{ record.user?.display_name ?? record.user?.name ?? '—' }}</p>
                    </div>
                    <div v-if="record.issue_date">
                        <p class="text-gray-500 text-xs">Issue Date</p>
                        <p class="font-medium text-gray-900">{{ formatDate(record.issue_date) }}</p>
                    </div>
                    <div v-if="record.expiration_date">
                        <p class="text-gray-500 text-xs">Valid Until</p>
                        <p class="font-medium text-gray-900">{{ formatDate(record.expiration_date) }}</p>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div v-if="record.line_items?.length" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Line Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="item in record.line_items" :key="item.id">
                                <tr>
                                    <td class="px-6 py-3">
                                        <p class="font-medium text-gray-900">{{ item.name || '—' }}</p>
                                        <p v-if="item.description" class="text-xs text-gray-500 mt-0.5">{{ item.description }}</p>
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-700">{{ item.quantity }}</td>
                                    <td class="px-6 py-3 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="px-6 py-3 text-right font-medium text-gray-900">{{ formatCurrency(lineItemTotal(item)) }}</td>
                                </tr>
                                <tr
                                    v-for="addon in item.addons"
                                    :key="'addon-' + addon.id"
                                    class="bg-gray-50"
                                >
                                    <td class="pl-10 pr-6 py-2 text-sm text-gray-600">
                                        <span class="text-gray-400 mr-1">↳</span>{{ addon.name }}
                                    </td>
                                    <td class="px-6 py-2 text-right text-sm text-gray-600">{{ addon.quantity }}</td>
                                    <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatCurrency(addon.price) }}</td>
                                    <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatCurrency(addonTotal(addon)) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <!-- Totals -->
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <div class="ml-auto max-w-xs space-y-1 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span><span>{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div v-if="taxRate > 0" class="flex justify-between text-gray-600">
                            <span>Tax ({{ taxRate }}%)</span><span>{{ formatCurrency(tax) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-gray-900 border-t border-gray-300 pt-2 mt-2">
                            <span>Total</span><span>{{ formatCurrency(grandTotal) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes / Terms -->
            <div v-if="record.notes || record.terms" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div v-if="record.notes">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ record.notes }}</p>
                </div>
                <div v-if="record.terms">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Terms &amp; Conditions</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ record.terms }}</p>
                </div>
            </div>

            <!-- ── Approval & Signature Section ── -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-1">Authorization &amp; Signature</h3>
                <p class="text-sm text-gray-500 mb-6">By signing below, you authorize the work described in this estimate.</p>

                <!-- Signature Mode Toggle -->
                <div class="flex gap-3 mb-5">
                    <button
                        @click="signatureMode = 'draw'"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border transition-colors"
                        :class="signatureMode === 'draw'
                            ? 'bg-primary-600 text-white border-primary-600'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >
                        Draw Signature
                    </button>
                    <button
                        @click="signatureMode = 'type'"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border transition-colors"
                        :class="signatureMode === 'type'
                            ? 'bg-primary-600 text-white border-primary-600'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >
                        Type Signature
                    </button>
                </div>

                <!-- Draw -->
                <div v-if="signatureMode === 'draw'" class="mb-5">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 relative">
                        <VueSignaturePad
                            ref="signaturePadRef"
                            width="100%"
                            height="140px"
                            :options="signaturePadOptions"
                        />
                        <p class="absolute bottom-2 left-0 right-0 text-center text-xs text-gray-400 pointer-events-none select-none">
                            Sign here
                        </p>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <button @click="undoSignature" class="text-xs text-gray-500 hover:text-gray-700 underline">Undo</button>
                        <button @click="clearSignature" class="text-xs text-gray-500 hover:text-gray-700 underline">Clear</button>
                    </div>
                </div>

                <!-- Type -->
                <div v-else class="mb-5">
                    <input
                        v-model="typedSignature"
                        type="text"
                        placeholder="Type your full name"
                        class="w-full signature-cursive text-3xl border-b-2 border-gray-300 focus:border-primary-500 outline-none py-3 px-1 bg-transparent text-gray-800"
                    />
                    <p class="text-xs text-gray-400 mt-1">Your typed name will serve as your electronic signature.</p>
                </div>

                <!-- Print Name -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Print Full Name</label>
                    <input
                        v-model="approveForm.signed_name"
                        type="text"
                        placeholder="Enter your full name"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent outline-none"
                    />
                </div>

                <!-- Consent -->
                <label class="flex items-start gap-3 cursor-pointer mb-5">
                    <input v-model="consent" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span class="text-sm text-gray-600 leading-relaxed">
                        I have reviewed this estimate and authorize the described work to be performed.
                        I understand this constitutes my electronic approval and signature.
                    </span>
                </label>

                <!-- Error -->
                <p v-if="approvalError" class="text-sm text-red-600 mb-4">{{ approvalError }}</p>

                <!-- Submit Approve -->
                <button
                    @click="submitApproval"
                    :disabled="approveForm.processing"
                    class="w-full py-3 px-6 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                    <span class="material-icons text-base" v-if="!approveForm.processing">check_circle</span>
                    <svg v-else class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    {{ approveForm.processing ? 'Submitting…' : 'Approve Estimate' }}
                </button>

                <!-- Decline Toggle -->
                <div class="mt-4 text-center">
                    <button
                        @click="showDeclineForm = !showDeclineForm"
                        class="text-sm text-gray-400 hover:text-red-500 underline transition-colors"
                    >
                        {{ showDeclineForm ? 'Cancel' : 'I need to decline this estimate' }}
                    </button>
                </div>

                <!-- Decline Form -->
                <Transition name="slide">
                    <div v-if="showDeclineForm" class="mt-5 border border-red-200 rounded-lg p-5 bg-red-50">
                        <h4 class="text-sm font-semibold text-red-700 mb-3">Decline Estimate</h4>
                        <p class="text-xs text-red-600 mb-3">Please provide a reason so we can better assist you.</p>
                        <textarea
                            v-model="declineForm.decline_reason"
                            rows="3"
                            placeholder="Reason for declining (required)…"
                            class="w-full border border-red-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 outline-none bg-white resize-none"
                        />
                        <p v-if="declineForm.errors.decline_reason" class="text-xs text-red-600 mt-1">{{ declineForm.errors.decline_reason }}</p>
                        <button
                            @click="submitDecline"
                            :disabled="!declineForm.decline_reason.trim() || declineForm.processing"
                            class="mt-3 w-full py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                        >
                            {{ declineForm.processing ? 'Submitting…' : 'Confirm Decline' }}
                        </button>
                    </div>
                </Transition>
            </div>

        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}

.slide-enter-active,
.slide-leave-active {
    transition: all 0.25s ease;
    overflow: hidden;
}
.slide-enter-from,
.slide-leave-to {
    max-height: 0;
    opacity: 0;
}
.slide-enter-to,
.slide-leave-from {
    max-height: 400px;
    opacity: 1;
}

@media print {
    .no-print { display: none !important; }
    body { background: white; }
}
</style>
