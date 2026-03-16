<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    estimate:  { type: Object, required: true },
    account:   { type: Object, default: null },
    logoUrl:   { type: String, default: null },
    reviewUrl: { type: String, default: null },
    statuses:  { type: Array, default: () => [] },
});

// ── State ──────────────────────────────────────────────────────────────────
const signatureMode   = ref('draw');
const signaturePadRef = ref(null);
const typedSignature  = ref('');
const consent         = ref(false);
const showDeclineForm = ref(false);
const showSignForm    = ref(false);
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

// ── Computed ────────────────────────────────────────────────────────────────
// Resolve the current status option from the enum array (matches by id OR string value)
const currentStatus = computed(() => {
    const s = props.estimate.status;
    return props.statuses.find(o => o.id == s || o.value === s) ?? null;
});

const isApproved = computed(() => currentStatus.value?.value === 'approved' || !!props.estimate.signed_at);
const isDeclined = computed(() => currentStatus.value?.value === 'declined' || !!props.estimate.declined_at);
const canAct     = computed(() => {
    if (isApproved.value || isDeclined.value) return false;
    const v = currentStatus.value?.value;
    return v === 'sent' || v === 'pending_approval';
});

const statusLabel = computed(() => currentStatus.value?.name ?? props.estimate.status ?? 'Draft');

// Tailwind-safe text classes keyed by the color name from EstimateStatus::color()
const STATUS_TEXT = {
    gray: 'text-gray-700', blue: 'text-blue-700', yellow: 'text-yellow-800',
    green: 'text-green-700', red: 'text-red-700', orange: 'text-orange-700',
    purple: 'text-purple-700', slate: 'text-slate-700',
};
const statusClass = computed(() => {
    const color = currentStatus.value?.color ?? 'gray';
    return [currentStatus.value?.bgClass ?? 'bg-gray-100', STATUS_TEXT[color] ?? 'text-gray-700'].join(' ');
});

const subtotal   = computed(() => Number(props.estimate.subtotal) || 0);
const tax        = computed(() => Number(props.estimate.tax) || 0);
const grandTotal = computed(() => Number(props.estimate.total) || subtotal.value + tax.value);
const taxRate    = computed(() => Number(props.estimate.tax_rate) || 0);

// ── Helpers ────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
    v != null ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '$0.00';

const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch { return '—'; }
};

const formatDateTime = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};

const lineItemTotal = (item) => {
    const qty  = Number(item.quantity) || 1;
    const price = Number(item.unit_price) || 0;
    const disc  = Number(item.discount) || 0;
    const base  = Math.max(0, qty * price - disc);
    const addonsTotal = (item.addons ?? []).reduce(
        (sum, a) => sum + Number(a.price || 0) * Number(a.quantity || 1), 0
    );
    return base + addonsTotal;
};

const addonTotal = (addon) =>
    Number(addon.price || 0) * Number(addon.quantity || 1);

// ── Signature Actions ───────────────────────────────────────────────────────
const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature  = () => signaturePadRef.value?.undoSignature();

const submitApproval = () => {
    approvalError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) return;
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) { approvalError.value = 'Please draw your signature.'; return; }
        approveForm.signature_data   = data;
        approveForm.signature_method = 'draw';
    } else {
        if (!typedSignature.value.trim()) { approvalError.value = 'Please type your signature.'; return; }
        approveForm.signature_data   = typedSignature.value.trim();
        approveForm.signature_method = 'type';
    }

    if (!approveForm.signed_name.trim()) { approvalError.value = 'Please enter your full name.'; return; }
    if (!consent.value) { approvalError.value = 'Please accept the acknowledgement.'; return; }

    approveForm.consent = consent.value;
    approveForm.post(route('estimates.approve', props.estimate.uuid), { preserveScroll: false });
};

const submitDecline = () => {
    declineForm.post(route('estimates.decline', props.estimate.uuid));
};

const signaturePadOptions = { penColor: '#1a1a2e', minWidth: 1, maxWidth: 3 };
</script>

<template>
    <ClientPortalLayout title="Estimate">
        <Head :title="`${estimate.display_name} - Estimate`" />

        <!-- Back Link -->
        <div class="mb-4">
            <Link :href="route('portal.estimates')" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
                <span class="material-icons text-base">arrow_back</span>
                Back to Estimates
            </Link>
        </div>

        <!-- Header Card -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 flex items-center justify-between">
                <div>
                    <p class="text-primary-200 text-xs font-medium uppercase tracking-wide">Estimate</p>
                    <h1 class="text-white text-2xl font-bold font-mono">{{ estimate.display_name }}</h1>
                </div>
                <span class="px-3 py-1.5 rounded-full text-xs font-semibold" :class="statusClass">
                    {{ statusLabel }}
                </span>
            </div>

            <div class="px-6 py-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm border-b border-gray-100">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Total</p>
                    <p class="font-bold text-gray-900 text-lg">{{ formatCurrency(grandTotal) }}</p>
                </div>
                <div v-if="estimate.issue_date">
                    <p class="text-xs text-gray-400 mb-0.5">Issue Date</p>
                    <p class="font-medium text-gray-700">{{ formatDate(estimate.issue_date) }}</p>
                </div>
                <div v-if="estimate.expiration_date">
                    <p class="text-xs text-gray-400 mb-0.5">Valid Until</p>
                    <p class="font-medium text-gray-700">{{ formatDate(estimate.expiration_date) }}</p>
                </div>
                <div v-if="estimate.user">
                    <p class="text-xs text-gray-400 mb-0.5">Sales Contact</p>
                    <p class="font-medium text-gray-700">{{ estimate.user.display_name ?? estimate.user.name }}</p>
                </div>
            </div>

            <!-- CTA row for actionable estimates -->
            <div v-if="canAct" class="px-6 py-3 bg-amber-50 border-b border-amber-100 flex items-center justify-between gap-4">
                <p class="text-sm text-amber-800 font-medium">This estimate is awaiting your approval.</p>
                <button
                    @click="showSignForm = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    <span class="material-icons text-base">draw</span>
                    Review &amp; Sign
                </button>
            </div>

            <!-- Approved confirmation banner -->
            <div v-if="isApproved" class="px-6 py-3 bg-green-50 border-b border-green-100 flex items-center gap-2">
                <span class="material-icons text-green-600 text-base">check_circle</span>
                <p class="text-sm text-green-800">
                    <span class="font-semibold">Approved</span> by {{ estimate.signed_name }} on {{ formatDateTime(estimate.signed_at) }}
                </p>
            </div>

            <!-- Declined banner -->
            <div v-if="isDeclined" class="px-6 py-3 bg-red-50 border-b border-red-100 flex items-center gap-2">
                <span class="material-icons text-red-600 text-base">cancel</span>
                <p class="text-sm text-red-800">
                    <span class="font-semibold">Declined</span> on {{ formatDateTime(estimate.declined_at) }}
                    <span v-if="estimate.decline_reason"> — {{ estimate.decline_reason }}</span>
                </p>
            </div>
        </div>

        <!-- Line Items -->
        <div v-if="estimate.line_items?.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900 text-sm">Line Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template v-for="item in estimate.line_items" :key="item.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ item.name || '—' }}</p>
                                    <p v-if="item.description" class="text-xs text-gray-500 mt-0.5">{{ item.description }}</p>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ item.quantity }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900">{{ formatCurrency(lineItemTotal(item)) }}</td>
                            </tr>
                            <tr
                                v-for="addon in item.addons"
                                :key="'addon-' + addon.id"
                                class="bg-gray-50"
                            >
                                <td class="pl-10 pr-5 py-2 text-sm text-gray-600">
                                    <span class="text-gray-400 mr-1">↳</span>{{ addon.name }}
                                </td>
                                <td class="px-5 py-2 text-right text-sm text-gray-600">{{ addon.quantity }}</td>
                                <td class="px-5 py-2 text-right text-sm text-gray-600">{{ formatCurrency(addon.price) }}</td>
                                <td class="px-5 py-2 text-right text-sm text-gray-600">{{ formatCurrency(addonTotal(addon)) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
                <div class="ml-auto max-w-xs space-y-1.5 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ formatCurrency(subtotal) }}</span></div>
                    <div v-if="taxRate > 0" class="flex justify-between text-gray-600"><span>Tax ({{ taxRate }}%)</span><span>{{ formatCurrency(tax) }}</span></div>
                    <div class="flex justify-between font-bold text-gray-900 text-base border-t border-gray-300 pt-2 mt-1"><span>Total</span><span>{{ formatCurrency(grandTotal) }}</span></div>
                </div>
            </div>
        </div>

        <!-- Notes / Terms -->
        <div v-if="estimate.notes || estimate.terms" class="bg-white rounded-xl border border-gray-200 p-5 mb-6 space-y-4">
            <div v-if="estimate.notes">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ estimate.notes }}</p>
            </div>
            <div v-if="estimate.terms">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Terms &amp; Conditions</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ estimate.terms }}</p>
            </div>
        </div>

        <!-- Signature (already approved) -->
        <div v-if="isApproved && estimate.signed_name" class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Your Signature</h3>
            <div v-if="estimate.signature_url" class="border border-gray-200 rounded-lg p-2 bg-gray-50 mb-3">
                <img :src="estimate.signature_url" alt="Signature" class="max-h-20 mx-auto" />
            </div>
            <p v-else class="signature-cursive text-4xl text-gray-800 text-center py-3 border-b border-gray-200 mb-3">
                {{ estimate.signed_name }}
            </p>
            <div class="text-xs text-gray-500 flex gap-4">
                <span>Signed by: <strong>{{ estimate.signed_name }}</strong></span>
                <span>Date: {{ formatDateTime(estimate.signed_at) }}</span>
            </div>
        </div>

        <!-- Link to public review page (always visible if available) -->
        <div v-if="reviewUrl && !isApproved && !isDeclined" class="bg-white rounded-xl border border-gray-200 p-5 mb-6 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-900">Public Review Link</p>
                <p class="text-xs text-gray-500 mt-0.5">Share this link to allow signing without a portal account.</p>
            </div>
            <a :href="reviewUrl" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary-50 hover:bg-primary-100 text-primary-700 text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                <span class="material-icons text-base">open_in_new</span>
                Open Review Page
            </a>
        </div>

        <!-- ── Inline Sign Form (modal-like panel) ── -->
        <Transition name="slide">
            <div v-if="showSignForm && canAct" class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-semibold text-gray-900">Sign &amp; Approve Estimate</h3>
                    <button @click="showSignForm = false" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>

                <!-- Mode toggle -->
                <div class="flex gap-3 mb-5">
                    <button @click="signatureMode = 'draw'" class="flex-1 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="signatureMode === 'draw' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
                        Draw
                    </button>
                    <button @click="signatureMode = 'type'" class="flex-1 py-2 text-sm font-medium rounded-lg border transition-colors"
                        :class="signatureMode === 'type' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
                        Type
                    </button>
                </div>

                <!-- Draw -->
                <div v-if="signatureMode === 'draw'" class="mb-4">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 relative">
                        <VueSignaturePad ref="signaturePadRef" width="100%" height="120px" :options="signaturePadOptions" />
                        <p class="absolute bottom-2 left-0 right-0 text-center text-xs text-gray-400 pointer-events-none select-none">Sign here</p>
                    </div>
                    <div class="flex gap-3 mt-1">
                        <button @click="undoSignature" class="text-xs text-gray-400 hover:text-gray-600 underline">Undo</button>
                        <button @click="clearSignature" class="text-xs text-gray-400 hover:text-gray-600 underline">Clear</button>
                    </div>
                </div>

                <!-- Type -->
                <div v-else class="mb-4">
                    <input v-model="typedSignature" type="text" placeholder="Type your full name"
                        class="w-full signature-cursive text-3xl border-b-2 border-gray-300 focus:border-primary-500 outline-none py-2 bg-transparent" />
                </div>

                <!-- Print name -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Print Full Name</label>
                    <input v-model="approveForm.signed_name" type="text" placeholder="Your full name"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" />
                </div>

                <!-- Consent -->
                <label class="flex items-start gap-2 cursor-pointer mb-4">
                    <input v-model="consent" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span class="text-xs text-gray-600 leading-relaxed">I authorize this estimate and understand this constitutes my electronic signature and approval.</span>
                </label>

                <p v-if="approvalError" class="text-xs text-red-600 mb-3">{{ approvalError }}</p>

                <button @click="submitApproval" :disabled="approveForm.processing"
                    class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                    <span class="material-icons text-base" v-if="!approveForm.processing">check_circle</span>
                    <svg v-else class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    {{ approveForm.processing ? 'Submitting…' : 'Approve Estimate' }}
                </button>

                <div class="mt-3 text-center">
                    <button @click="showDeclineForm = !showDeclineForm" class="text-xs text-gray-400 hover:text-red-500 underline transition-colors">
                        {{ showDeclineForm ? 'Cancel decline' : 'I need to decline' }}
                    </button>
                </div>

                <Transition name="slide">
                    <div v-if="showDeclineForm" class="mt-4 border border-red-200 rounded-lg p-4 bg-red-50">
                        <p class="text-xs font-semibold text-red-700 mb-2">Please tell us why you're declining:</p>
                        <textarea v-model="declineForm.decline_reason" rows="3" placeholder="Reason for declining (required)…"
                            class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm outline-none bg-white resize-none focus:ring-1 focus:ring-red-400" />
                        <button @click="submitDecline" :disabled="!declineForm.decline_reason.trim() || declineForm.processing"
                            class="mt-2 w-full py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
                            {{ declineForm.processing ? 'Submitting…' : 'Confirm Decline' }}
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>

    </ClientPortalLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap');

.signature-cursive { font-family: 'Dancing Script', cursive; }

.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; overflow: hidden; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; }
.slide-enter-to, .slide-leave-from { max-height: 600px; opacity: 1; }
</style>
