<script setup>
import { ref, computed } from 'vue';
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
const approvalError = ref('');

const signForm = useForm({
    signature_method: 'draw',
    signature_data: '',
    signed_name: '',
    /** Same as printed name for this flow; kept for API compatibility. */
    recipient_name: '',
    consent: false,
});

const isSigned = computed(() => props.record.signed_at);
const canSign = computed(() => !isSigned.value);

const companyName = computed(
    () => props.record.subsidiary?.display_name || props.account?.name || 'Company Name',
);

/** Account setting or sensible default copy for deliveries (not contract language). */
const deliveryAckBody = computed(() => {
    const raw = props.account?.delivery_ack_text;
    const tag = '[COMPANY NAME]';
    if (raw && String(raw).trim()) {
        return String(raw).split(tag).join(companyName.value);
    }
    return [
        `By signing below, you confirm that you (or your authorized representative) received the delivery described on this page, including the items listed above, in satisfactory condition unless you note otherwise in writing with ${companyName.value} at the time of delivery.`,
        '',
        `${companyName.value} may rely on this electronic signature to the same extent as a handwritten signature.`,
    ].join('\n');
});

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature = () => signaturePadRef.value?.undoSignature();

const lineItems = computed(() => (Array.isArray(props.record?.items) ? props.record.items : []));

const itemName = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    return unit?.asset?.display_name ?? variant?.display_name ?? item.name ?? 'Asset';
};

const itemVariantLabel = (item) => (item.asset_variant ?? item.assetVariant)?.display_name ?? null;

const itemUnitLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) {
        return item.serial_number_snapshot ?? null;
    }
    return unit.display_name ?? unit.serial_number ?? unit.hin ?? unit.sku ?? null;
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    } catch (e) {
        return '—';
    }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch (e) {
        return '—';
    }
};

const handleSign = () => {
    approvalError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) {
            approvalError.value = 'Signature pad is not ready. Please try again.';
            return;
        }
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            approvalError.value = 'Please draw your signature before submitting.';
            return;
        }
        signForm.signature_data = data;
        signForm.signature_method = 'draw';
    } else {
        const typed = typedSignature.value.trim();
        if (!typed) {
            approvalError.value = 'Please type your signature before submitting.';
            return;
        }
        signForm.signature_data = typed;
        signForm.signature_method = 'type';
    }

    if (!signForm.signed_name.trim()) {
        approvalError.value = 'Please enter your printed name.';
        return;
    }

    if (!consent.value) {
        approvalError.value = 'Please accept the acknowledgement to continue.';
        return;
    }

    signForm.consent = consent.value;
    signForm.recipient_name = signForm.signed_name.trim();

    signForm.post(route('deliveries.sign', props.record.uuid), {
        preserveState: true,
        onSuccess: () => {
            window.location.reload();
        },
        onError: (errors) => {
            approvalError.value = 'Failed to sign delivery. Please try again.';
            if (errors.signature_data) {
                approvalError.value = errors.signature_data[0];
            }
        },
    });
};
</script>

<template>
    <Head title="Delivery Review & Signature" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow-lg">
                <!-- Header -->
                <div class="border-b-4 border-gray-900 px-8 py-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-6">
                            <!-- Company Logo -->
                            <div v-if="logoUrl" class="flex-shrink-0">
                                <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>

                            <!-- Company Info -->
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ record.subsidiary?.display_name || account.name || 'Company Name' }}
                                </h1>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <p v-if="record.location?.address_line_1 || record.location?.address_line1">
                                        {{ record.location.address_line_1 || record.location.address_line1 }}
                                        <span v-if="record.location?.address_line_2 || record.location?.address_line2"
                                            >,
                                            {{ record.location.address_line_2 || record.location.address_line2 }}</span
                                        >
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

                        <!-- Delivery Number -->
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 uppercase">Delivery</div>
                            <div class="text-3xl font-bold text-gray-900 font-mono">
                                {{ record.display_name }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ formatDate(record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="px-8 py-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Customer Details -->
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

                        <!-- Primary asset (when no multi-item list) -->
                        <div v-if="(record.asset_unit || record.assetUnit) && lineItems.length === 0">
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset Information</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="space-y-2">
                                    <div class="font-semibold text-gray-900 text-lg">
                                        {{ (record.asset_unit || record.assetUnit)?.display_name || '—' }}
                                    </div>
                                    <div
                                        v-if="(record.asset_unit || record.assetUnit)?.asset?.make?.display_name"
                                        class="text-sm text-gray-600"
                                    >
                                        <span class="font-medium">Make:</span>
                                        {{ (record.asset_unit || record.assetUnit).asset.make.display_name }}
                                    </div>
                                    <div v-if="(record.asset_unit || record.assetUnit)?.asset?.year" class="text-sm text-gray-600">
                                        <span class="font-medium">Year:</span> {{ (record.asset_unit || record.assetUnit).asset.year }}
                                    </div>
                                    <div v-if="(record.asset_unit || record.assetUnit)?.serial_number" class="text-sm text-gray-600">
                                        <span class="font-medium">Serial:</span> {{ (record.asset_unit || record.assetUnit).serial_number }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Information -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Delivery Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-600">Scheduled:</span>
                            <div class="text-sm text-gray-900">{{ formatDateTime(record.scheduled_at) }}</div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Estimated Arrival:</span>
                            <div class="text-sm text-gray-900">{{ formatDateTime(record.estimated_arrival_at) }}</div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Delivered:</span>
                            <div class="text-sm text-gray-900">{{ record.delivered_at ? formatDateTime(record.delivered_at) : '—' }}</div>
                        </div>
                    </div>
                    <div v-if="record.address_line_1" class="mt-4">
                        <span class="text-sm font-medium text-gray-600">Delivery Address:</span>
                        <div class="text-sm text-gray-900 mt-1">
                            <div>{{ record.address_line_1 }}</div>
                            <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
                            <div>{{ record.city }}, {{ record.state }} {{ record.postal_code }}</div>
                        </div>
                    </div>
                </div>

                <!-- Line items included in this delivery -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Items in this delivery</h2>
                    <div v-if="lineItems.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
                        No line items are recorded for this delivery.
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Asset</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Variant</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Unit / serial</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="item in lineItems" :key="item.id">
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ itemName(item) }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ itemVariantLabel(item) ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ itemUnitLabel(item) ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Already Signed -->
                <div v-if="isSigned" class="px-8 py-6 border-t-2 border-gray-900 bg-green-50">
                    <div class="text-center">
                        <div class="flex items-center justify-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="material-icons text-2xl text-green-600">check_circle</span>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-green-900 mb-2">Delivery Confirmed</h3>
                        <p class="text-sm text-green-700 mb-4">
                            This delivery has been signed and confirmed by {{ record.recipient_name || 'the recipient' }} on
                            {{ formatDate(record.signed_at) }}.
                        </p>
                        <div v-if="record.signature_url" class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Signature:</p>
                            <img :src="record.signature_url" alt="Signature" class="max-w-xs mx-auto border border-gray-300 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Signature Form (aligned with contract customer signature UX) -->
                <div v-else-if="canSign" class="px-8 py-8 border-t-2 border-gray-900 print:hidden">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Customer Signature</h2>

                    <div class="mb-8 p-5 bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">{{ deliveryAckBody }}</p>
                    </div>

                    <div v-if="approvalError" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-700">{{ approvalError }}</p>
                    </div>

                    <form @submit.prevent="handleSign" class="space-y-6">
                        <!-- Signature mode (matches ContractReview) -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Signature</label>
                            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden">
                                <button
                                    type="button"
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
                                    type="button"
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

                        <!-- Draw -->
                        <div v-show="signatureMode === 'draw'" class="mb-6">
                            <div class="relative overflow-hidden rounded-lg border-2 border-gray-300 bg-white">
                                <VueSignaturePad
                                    ref="signaturePadRef"
                                    width="100%"
                                    height="200px"
                                    :options="signaturePadOptions"
                                />
                                <div class="pointer-events-none absolute bottom-4 left-4 right-4 border-b border-gray-300"></div>
                            </div>
                            <div class="mt-2 flex items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
                                    @click="undoSignature"
                                >
                                    <span class="material-icons text-sm">undo</span>
                                    Undo
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
                                    @click="clearSignature"
                                >
                                    <span class="material-icons text-sm">clear</span>
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Type (cursive preview like ContractReview) -->
                        <div v-show="signatureMode === 'type'" class="mb-6">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Type your name</label>
                            <input
                                v-model="typedSignature"
                                type="text"
                                autocomplete="name"
                                placeholder="Type your full name"
                                class="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg transition-colors focus:border-gray-900 focus:ring-0"
                            />
                            <div
                                v-if="typedSignature.trim()"
                                class="mt-4 flex items-end justify-center rounded-lg border-2 border-gray-200 bg-white px-6 py-8"
                            >
                                <div class="w-full text-center">
                                    <p
                                        class="signature-cursive inline-block min-w-[200px] border-b border-gray-300 pb-2 text-4xl text-gray-900"
                                    >
                                        {{ typedSignature }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Print name (single legal name field — recipient is set to this on submit) -->
                        <div class="mb-6">
                            <label for="signed_name" class="mb-2 block text-sm font-medium text-gray-700">Print Name</label>
                            <input
                                id="signed_name"
                                v-model="signForm.signed_name"
                                type="text"
                                autocomplete="name"
                                placeholder="Your full legal name"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-gray-900 focus:ring-0"
                            />
                            <p v-if="signForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ signForm.errors.signed_name }}</p>
                        </div>

                        <!-- Consent (delivery-specific) -->
                        <div class="mb-8">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input
                                    id="consent"
                                    v-model="consent"
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm leading-relaxed text-gray-700">
                                    I acknowledge receipt of the goods listed above. I confirm they were delivered as described and, to the best of my knowledge, are in
                                    good condition, or I have noted any exceptions with the driver before signing. I authorize
                                    <strong>{{ companyName }}</strong> to rely on this electronic signature for this delivery.
                                </span>
                            </label>
                            <p v-if="signForm.errors.consent" class="ml-8 mt-1 text-sm text-red-600">{{ signForm.errors.consent }}</p>
                        </div>

                        <div v-if="Object.keys(signForm.errors).length" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                            <ul class="space-y-1 text-sm text-red-700">
                                <li v-for="(error, key) in signForm.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

                        <div class="flex items-center gap-4">
                            <button
                                type="submit"
                                :disabled="signForm.processing || !consent"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-8 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span v-if="signForm.processing" class="material-icons animate-spin text-sm">refresh</span>
                                <span v-else class="material-icons text-sm">draw</span>
                                {{ signForm.processing ? 'Submitting…' : 'Confirm delivery & sign' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-gray-900 text-white text-center text-xs">
                    <p>Thank you for your business!</p>
                    <p v-if="record.location?.phone" class="mt-1">
                        Questions? Call us at {{ record.location.phone }}
                    </p>
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
    body * {
        visibility: hidden;
    }

    .bg-white {
        background-color: white !important;
    }

    .shadow-lg {
        box-shadow: none !important;
    }

    @page {
        margin: 0.5in;
    }
}
</style>