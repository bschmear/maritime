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
const approvalError = ref('');

const signForm = useForm({
    signature_method: 'draw',
    signature_data: '',
    signed_name: '',
    recipient_name: '',
    consent: false,
});

const isSigned = computed(() => props.record.signed_at);
const canSign = computed(() => !isSigned.value);


// Group checklist items by category for display
const itemsByCategory = computed(() => {
    const grouped = {};
    (props.record.checklistItems || []).forEach(item => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) {
            grouped[catId] = { id: catId, name: catName, items: [] };
        }
        grouped[catId].items.push(item);
    });
    return Object.values(grouped).sort((a, b) => a.name.localeCompare(b.name));
});

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

    if (signatureMode.value === 'draw' && signaturePadRef.value) {
        const { data } = signaturePadRef.value.saveSignature();
        signForm.signature_data = data;
    } else if (signatureMode.value === 'type') {
        signForm.signature_data = typedSignature.value;
    }

    signForm.signature_method = signatureMode.value;
    signForm.recipient_name = signForm.recipient_name || '';
    signForm.consent = consent.value;

    signForm.post(route('deliveries.sign', props.record.uuid), {
        preserveState: true,
        onSuccess: () => {
            // Reload the page to show signed state
            window.location.reload();
        },
        onError: (errors) => {
            approvalError.value = 'Failed to sign delivery. Please try again.';
            if (errors.signature_data) {
                approvalError.value = errors.signature_data[0];
            }
        }
    });
};

onMounted(() => {
    // Auto-focus the name field
    const nameField = document.querySelector('input[name="signed_name"]');
    if (nameField) {
        nameField.focus();
    }
});
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

                <!-- Delivery Checklist -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Delivery Checklist</h2>

                    <div class="space-y-6">
                        <div
                            v-for="category in itemsByCategory"
                            :key="category.id"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <h3 class="font-semibold text-gray-900 mb-3">{{ category.name }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="item in category.items"
                                    :key="item.id"
                                    class="flex items-center gap-3"
                                >
                                    <div class="flex-shrink-0 w-4 h-4 rounded border-2 flex items-center justify-center" :class="item.completed ? 'bg-green-600 border-green-600' : 'bg-red-600 border-red-600'">
                                        <span v-if="item.completed" class="material-icons text-xs text-white">check</span>
                                        <span v-else class="material-icons text-xs text-white">close</span>
                                    </div>
                                    <span class="text-sm text-gray-900">{{ item.label }}</span>
                                    <span v-if="item.is_required" class="text-red-500 text-xs">*</span>
                                </div>
                            </div>
                        </div>

                        <!-- No checklist items message -->
                        <div v-if="itemsByCategory.length === 0" class="text-center py-8">
                            <div class="text-gray-500">
                                <span class="material-icons text-4xl mb-2">checklist</span>
                                <p class="text-sm">No checklist items have been added to this delivery yet.</p>
                            </div>
                        </div>
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
                            This delivery has been signed and confirmed by {{ record.recipient_name || 'the recipient' }} on {{ formatDate(record.signed_at) }}.
                        </p>
                        <div v-if="record.signature_url" class="text-center">
                            <p class="text-sm text-gray-600 mb-2">Signature:</p>
                            <img :src="record.signature_url" alt="Signature" class="max-w-xs mx-auto border border-gray-300 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Signature Form -->
                <div v-else-if="canSign" class="px-8 py-6 border-t-2 border-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Customer Authorization</h2>

                    <!-- Acknowledgment Text -->
                    <div v-if="account.delivery_ack_text" class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">
                            {{ account.delivery_ack_text.replace('[COMPANY NAME]', record.subsidiary?.display_name || account.name || 'Company Name') }}
                        </p>
                    </div>

                    <!-- Error Display -->
                    <div v-if="approvalError" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-700">{{ approvalError }}</p>
                    </div>

                    <form @submit.prevent="handleSign" class="space-y-6">
                        <!-- Signature Mode Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Signature Method</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="signatureMode = 'draw'"
                                    :class="[
                                        'flex items-center gap-2 p-3 rounded-lg border-2 text-left transition-all',
                                        signatureMode === 'draw'
                                            ? 'border-blue-500 bg-blue-50'
                                            : 'border-gray-200 hover:border-blue-300'
                                    ]"
                                >
                                    <span class="material-icons text-lg">edit</span>
                                    <div>
                                        <p class="text-sm font-medium">Draw Signature</p>
                                        <p class="text-xs text-gray-500">Use mouse or touch to sign</p>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    @click="signatureMode = 'type'"
                                    :class="[
                                        'flex items-center gap-2 p-3 rounded-lg border-2 text-left transition-all',
                                        signatureMode === 'type'
                                            ? 'border-green-500 bg-green-50'
                                            : 'border-gray-200 hover:border-green-300'
                                    ]"
                                >
                                    <span class="material-icons text-lg">keyboard</span>
                                    <div>
                                        <p class="text-sm font-medium">Type Name</p>
                                        <p class="text-xs text-gray-500">Type your name as signature</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Signature Input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                {{ signatureMode === 'draw' ? 'Draw Your Signature' : 'Type Your Name' }}
                            </label>

                            <!-- Draw Signature -->
                            <div v-if="signatureMode === 'draw'" class="border-2 border-gray-300 rounded-lg overflow-hidden">
                                <VueSignaturePad
                                    ref="signaturePadRef"
                                    :options="{ onEnd: () => {} }"
                                    class="w-full h-40 bg-white"
                                />
                                <div class="p-2 bg-gray-50 border-t border-gray-200 text-center">
                                    <button
                                        type="button"
                                        @click="signaturePadRef?.clearSignature()"
                                        class="text-xs text-blue-600 hover:text-blue-800"
                                    >
                                        Clear Signature
                                    </button>
                                </div>
                            </div>

                            <!-- Type Signature -->
                            <div v-else>
                                <input
                                    v-model="typedSignature"
                                    type="text"
                                    placeholder="Enter your full name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Name and Consent -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="signed_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Full Name *
                                </label>
                                <input
                                    id="signed_name"
                                    v-model="signForm.signed_name"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Enter your full name"
                                />
                            </div>
                            <div>
                                <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Recipient Name (Optional)
                                </label>
                                <input
                                    id="recipient_name"
                                    v-model="signForm.recipient_name"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Person receiving the delivery"
                                />
                            </div>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="flex items-start gap-3">
                            <input
                                id="consent"
                                v-model="consent"
                                type="checkbox"
                                class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label for="consent" class="text-sm text-gray-700">
                                I acknowledge receipt of the delivery and confirm that all items listed above have been properly delivered and are in good condition.
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="signForm.processing || !consent"
                                class="w-full inline-flex items-center justify-center px-6 py-3 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <span v-if="signForm.processing" class="material-icons animate-spin mr-2">refresh</span>
                                Confirm Delivery & Sign
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