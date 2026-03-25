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
const signError = ref('');

const signForm = useForm({
    signature_method: 'draw',
    signature_data: '',
    signed_name: '',
    consent: false,
});

const isSigned = computed(() => !!props.record.signed_at);

const formatCurrency = (value) => {
    if (value == null) return '$0.00';
    return `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

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
        return d.toLocaleDateString('en-US', {
            month: 'long', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    } catch { return '—'; }
};

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    const cleaned = phone.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    return match ? `(${match[1]}) ${match[2]}-${match[3]}` : phone;
};

const accountDisplayName = computed(() =>
    props.account?.settings?.business_name || props.account?.business_name || 'Company'
);

const companyName = computed(() =>
    props.record.transaction?.subsidiary?.display_name || accountDisplayName.value
);

const locationPreview = computed(() => {
    const loc = props.record?.transaction?.location;
    if (!loc) return null;
    const line1 = loc.address_line_1 ?? loc.address_line1 ?? '';
    const line2 = loc.address_line_2 ?? loc.address_line2 ?? '';
    const city = loc.city ?? '';
    const state = loc.state ?? '';
    const postal = loc.postal_code ?? '';
    const phone = loc.phone ?? '';
    const email = loc.email ?? '';
    if (!line1 && !city && !phone && !email) return null;
    return { line1, line2, city, state, postal, phone, email };
});

const customerDisplayName = computed(() =>
    props.record.customer?.display_name || props.record.transaction?.customer_name || '—'
);
const customerEmail = computed(() =>
    props.record.customer?.email || props.record.transaction?.customer_email || null
);
const customerPhone = computed(() =>
    props.record.customer?.phone || props.record.transaction?.customer_phone || null
);

const transactionItems = computed(() => props.record?.transaction?.items ?? []);

const roundMoney = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const taxRate = computed(() => Number(props.record?.transaction?.tax_rate) || 0);
const lineBaseTotal = (item) => Number(item.unit_price || 0) * Number(item.quantity || 1);
const taxOnItem = (item) => {
    if (taxRate.value <= 0) return 0;
    const taxable = item.taxable !== false && item.taxable !== 0 && item.taxable !== '0';
    return taxable ? roundMoney(lineBaseTotal(item) * (taxRate.value / 100)) : 0;
};
const addonBaseTotal = (a) => Number(a.price || 0) * Number(a.quantity || 1);
const taxOnAddon = (addon) => {
    if (taxRate.value <= 0) return 0;
    const taxable = addon.taxable !== false && addon.taxable !== 0 && addon.taxable !== '0';
    return taxable ? roundMoney(addonBaseTotal(addon) * (taxRate.value / 100)) : 0;
};

const grandTotal = computed(() => {
    let total = 0;
    for (const item of transactionItems.value) {
        total += lineBaseTotal(item) + taxOnItem(item);
        for (const addon of (item.addons ?? [])) {
            total += addonBaseTotal(addon) + taxOnAddon(addon);
        }
    }
    return roundMoney(total);
});

const displayTotal = computed(() =>
    props.record.total_amount != null
        ? props.record.total_amount
        : grandTotal.value
);

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature = () => signaturePadRef.value?.undoSignature();

const submitSign = () => {
    signError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) return;
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            signError.value = 'Please draw your signature before submitting.';
            return;
        }
        signForm.signature_data = data;
        signForm.signature_method = 'draw';
    } else {
        if (!typedSignature.value.trim()) {
            signError.value = 'Please type your signature before submitting.';
            return;
        }
        signForm.signature_data = typedSignature.value.trim();
        signForm.signature_method = 'type';
    }

    if (!signForm.signed_name.trim()) {
        signError.value = 'Please enter your printed name.';
        return;
    }

    if (!consent.value) {
        signError.value = 'Please accept the acknowledgement to continue.';
        return;
    }

    signForm.consent = consent.value;
    signForm.post(route('contracts.sign', props.record.uuid), {
        preserveScroll: false,
    });
};

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};
</script>

<template>
    <Head :title="`Contract ${record.contract_number}`" />

    <div class="min-h-screen bg-gray-100">

        <!-- ======================== SIGNED STATE ======================== -->
        <div v-if="isSigned" class="min-h-screen flex flex-col">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-green-600 px-8 py-10 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 mb-4">
                            <span class="material-icons text-white text-4xl">check_circle</span>
                        </div>
                        <h1 class="text-2xl font-bold text-white">Contract Signed</h1>
                        <p class="text-green-100 mt-2">
                            Thank you for signing contract <strong>{{ record.contract_number }}</strong>
                        </p>
                    </div>
                    <div class="px-8 py-8 space-y-4">
                        <!-- Signature display -->
                        <div v-if="record.signature_url" class="flex justify-center">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <img :src="record.signature_url" alt="Your Signature" class="max-h-24 w-auto" />
                            </div>
                        </div>
                        <div v-else-if="record.signature_method === 5 && record.customer_signature" class="flex justify-center">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-8 py-4">
                                <p class="signature-cursive text-3xl text-gray-900">{{ record.customer_signature }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Signed on</span>
                                <p class="font-medium text-gray-900">{{ formatDateTime(record.signed_at) }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Signed by</span>
                                <p class="font-medium text-gray-900">{{ record.signed_name || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Total</span>
                                <p class="font-medium text-gray-900">{{ formatCurrency(displayTotal) }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Contract</span>
                                <p class="font-medium text-gray-900">{{ record.contract_number }}</p>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-600">
                                A copy of this contract has been recorded. If you have any questions,
                                please contact us<span v-if="locationPreview?.phone"> at {{ formatPhoneNumber(locationPreview.phone) }}</span>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================== REVIEW & SIGN STATE ======================== -->
        <div v-else>
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
                <div class="bg-white shadow-lg print:shadow-none">

                    <!-- Company Header -->
                    <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-6">
                                <div v-if="logoUrl" class="flex-shrink-0">
                                    <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto object-contain" />
                                </div>
                                <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                    <span class="material-icons text-4xl text-gray-400">business</span>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900">{{ companyName }}</h1>
                                    <div v-if="locationPreview" class="mt-2 text-sm text-gray-600 space-y-1">
                                        <p v-if="locationPreview.line1">
                                            {{ locationPreview.line1 }}<span v-if="locationPreview.line2">, {{ locationPreview.line2 }}</span>
                                        </p>
                                        <p v-if="locationPreview.city">
                                            {{ locationPreview.city }}<span v-if="locationPreview.state">, {{ locationPreview.state }}</span>
                                            {{ locationPreview.postal }}
                                        </p>
                                        <p v-if="locationPreview.phone" class="flex items-center gap-1">
                                            <span class="material-icons text-sm">phone</span>
                                            {{ locationPreview.phone }}
                                        </p>
                                        <p v-if="locationPreview.email" class="flex items-center gap-1">
                                            <span class="material-icons text-sm">email</span>
                                            {{ locationPreview.email }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-600 uppercase">Contract</div>
                                <div class="text-3xl font-bold text-gray-900 font-mono">
                                    {{ record.contract_number }}
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
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="space-y-2">
                                        <div class="font-semibold text-gray-900 text-lg">{{ customerDisplayName }}</div>
                                        <div v-if="customerEmail" class="flex items-center gap-2 text-sm text-gray-600">
                                            <span class="material-icons text-sm">email</span>
                                            {{ customerEmail }}
                                        </div>
                                        <div v-if="customerPhone" class="flex items-center gap-2 text-sm text-gray-600">
                                            <span class="material-icons text-sm">phone</span>
                                            {{ customerPhone }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Contract Value</h2>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <div class="text-3xl font-bold text-gray-900">{{ formatCurrency(displayTotal) }}</div>
                                    <div class="text-sm text-gray-600 mt-1">{{ record.currency || 'USD' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div v-if="transactionItems.length > 0" class="px-8 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Line Items</h2>
                        <table class="w-full text-sm border border-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 uppercase text-xs">Item</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700 uppercase text-xs w-12">Qty</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700 uppercase text-xs w-24">Price</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700 uppercase text-xs w-24">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template v-for="item in transactionItems" :key="item.id">
                                    <tr>
                                        <td class="px-3 py-2 align-top">
                                            <div class="font-medium text-gray-900">{{ item.name }}</div>
                                            <div v-if="item.description" class="text-xs text-gray-500 mt-0.5">{{ item.description }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">{{ item.quantity ?? 1 }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right font-medium text-gray-900">
                                            {{ formatCurrency(lineBaseTotal(item) + taxOnItem(item)) }}
                                        </td>
                                    </tr>
                                    <tr v-for="addon in (item.addons ?? [])" :key="`addon-${addon.id}`" class="bg-gray-50">
                                        <td class="px-3 py-1.5 pl-8 align-top">
                                            <div class="text-xs text-gray-600">↳ {{ addon.name }}</div>
                                        </td>
                                        <td class="px-3 py-1.5 text-right text-xs text-gray-600">{{ addon.quantity ?? 1 }}</td>
                                        <td class="px-3 py-1.5 text-right text-xs text-gray-600">{{ formatCurrency(addon.price) }}</td>
                                        <td class="px-3 py-1.5 text-right text-xs text-gray-600">
                                            {{ formatCurrency(addonBaseTotal(addon) + taxOnAddon(addon)) }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Totals -->
                        <div class="flex justify-end mt-4">
                            <div class="w-full md:w-1/3 space-y-2">
                                <div v-if="taxRate > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax ({{ taxRate }}%):</span>
                                    <span class="font-medium text-gray-900">included</span>
                                </div>
                                <div class="flex justify-between text-xl font-bold border-t-2 border-gray-900 pt-2">
                                    <span class="text-gray-900">Total:</span>
                                    <span class="text-gray-900">{{ formatCurrency(displayTotal) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contract Terms (description) -->
                    <div v-if="record.description" class="px-8 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Terms &amp; Conditions</h2>
                        <div class="prose prose-sm max-w-none">
                            <p class="text-gray-900 whitespace-pre-line">{{ record.description }}</p>
                        </div>
                    </div>

                    <!-- Billing Address -->
                    <div v-if="record.billing_address_line1 || record.transaction?.billing_address_line1" class="px-8 py-6 border-t border-gray-200">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Billing Address</h2>
                        <div class="text-sm text-gray-700 space-y-1">
                            <p>{{ record.billing_address_line1 || record.transaction?.billing_address_line1 }}</p>
                            <p v-if="record.billing_address_line2 || record.transaction?.billing_address_line2">
                                {{ record.billing_address_line2 || record.transaction?.billing_address_line2 }}
                            </p>
                            <p>
                                {{ record.billing_city || record.transaction?.billing_city }},
                                {{ record.billing_state || record.transaction?.billing_state }}
                                {{ record.billing_postal || record.transaction?.billing_postal }}
                            </p>
                        </div>
                    </div>

                    <!-- ==================== SIGNATURE SECTION ==================== -->
                    <div class="px-8 py-8 border-t-2 border-gray-900 print:hidden">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Customer Signature</h2>

                        <!-- Acknowledgement Text -->
                        <div v-if="account.contract_ack_text || account.service_ticket_ack_text" class="mb-8 p-5 bg-gray-50 border border-gray-200 rounded-lg">
                            <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">
                                {{ (account.contract_ack_text || account.service_ticket_ack_text).replace('[COMPANY NAME]', companyName) }}
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
                                <button @click="undoSignature" type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                                    <span class="material-icons text-sm">undo</span>
                                    Undo
                                </button>
                                <button @click="clearSignature" type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition-colors">
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
                                v-model="signForm.signed_name"
                                type="text"
                                placeholder="Your full legal name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-0 transition-colors"
                            />
                            <p v-if="signForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ signForm.errors.signed_name }}</p>
                        </div>

                        <!-- Consent -->
                        <div class="mb-8">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    v-model="consent"
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                />
                                <span class="text-sm text-gray-700 leading-relaxed">
                                    I have read and agree to the terms of this contract. By signing, I authorize this contract to proceed as outlined above.
                                </span>
                            </label>
                            <p v-if="signForm.errors.consent" class="mt-1 text-sm text-red-600 ml-8">{{ signForm.errors.consent }}</p>
                        </div>

                        <!-- Error -->
                        <div v-if="signError" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center gap-2 text-sm text-red-700">
                                <span class="material-icons text-sm">error_outline</span>
                                {{ signError }}
                            </div>
                        </div>

                        <div v-if="Object.keys(signForm.errors).length" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="text-sm text-red-700 space-y-1">
                                <li v-for="(error, key) in signForm.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center gap-4">
                            <button
                                @click="submitSign"
                                :disabled="signForm.processing"
                                class="inline-flex items-center gap-2 px-8 py-3 text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm"
                            >
                                <span v-if="signForm.processing" class="material-icons text-sm animate-spin">refresh</span>
                                <span v-else class="material-icons text-sm">draw</span>
                                {{ signForm.processing ? 'Submitting...' : 'Sign Contract' }}
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-4 bg-gray-900 text-white text-center text-xs">
                        <p>Thank you for your business!</p>
                        <p v-if="locationPreview?.phone" class="mt-1">
                            Questions? Call us at {{ formatPhoneNumber(locationPreview.phone) }}
                        </p>
                    </div>

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

    @page {
        margin: 0.5in;
    }
}
</style>
