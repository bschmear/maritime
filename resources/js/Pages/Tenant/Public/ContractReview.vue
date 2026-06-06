<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { VueSignaturePad } from 'vue-signature-pad';
import { lineAssetSelectedOptions, selectedOptionLabel } from '@/Utils/lineItemsFromEstimate';

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

const selectedOptionUnitPrice = (opt) => Number(opt?.price ?? 0);
const optionRowTaxable = (opt) => opt.taxable !== false && opt.taxable !== 0 && opt.taxable !== '0';
const taxOnAssetOption = (opt) => {
    if (taxRate.value <= 0) return 0;
    if (!optionRowTaxable(opt)) return 0;
    return roundMoney(selectedOptionUnitPrice(opt) * (taxRate.value / 100));
};

const grandTotal = computed(() => {
    let total = 0;
    for (const item of transactionItems.value) {
        total += lineBaseTotal(item) + taxOnItem(item);
        for (const opt of lineAssetSelectedOptions(item)) {
            total += selectedOptionUnitPrice(opt) + taxOnAssetOption(opt);
        }
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
</script>

<template>
    <Head :title="`Contract ${record.contract_number}`" />

    <div class="min-h-screen bg-gray-100">
        <div
            id="contract-print-root"
            class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:mx-0 print:max-w-none print:p-0"
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

            <!-- Signed banner (screen + print, matches approved estimate / service ticket) -->
            <div
                v-if="isSigned"
                class="mb-4 flex items-center gap-4 rounded-t-lg bg-green-600 px-6 py-4 text-white print:mb-0 print:rounded-none print:border-2 print:border-green-600 print:bg-white print:px-0 print:text-green-700"
            >
                <span class="material-icons text-3xl">check_circle</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold leading-tight">Contract signed</h2>
                    <p class="text-sm text-green-50 print:text-green-700">
                        Signed on {{ formatDateTime(record.signed_at) }}<span v-if="record.signed_name"> by {{ record.signed_name }}</span>.
                    </p>
                </div>
            </div>

            <div class="bg-white shadow-lg print:shadow-none">

                <!-- Company Header (matches EstimateReview / ServiceTicketReview) -->
                <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2 print:px-0">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 flex-1 items-start gap-6">
                            <div v-if="logoUrl" class="flex-shrink-0">
                                <img :src="logoUrl" alt="Company Logo" class="h-20 w-auto max-w-[180px] object-contain" />
                            </div>
                            <div v-else class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded bg-gray-200">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-2xl font-bold text-gray-900">{{ companyName }}</h1>
                                <div v-if="locationPreview" class="mt-2 space-y-1 text-sm text-gray-600">
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
                        <div class="shrink-0 text-right">
                            <div class="text-sm font-medium uppercase text-gray-600">Contract</div>
                            <div class="font-mono text-3xl font-bold text-gray-900">
                                {{ record.contract_number }}
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                {{ formatDate(record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-gray-50 px-8 py-6 print:px-0">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Customer Information</h2>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <div class="space-y-2">
                                    <div class="text-lg font-semibold text-gray-900">{{ customerDisplayName }}</div>
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
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Contract Value</h2>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <div class="text-3xl font-bold text-gray-900">{{ formatCurrency(displayTotal) }}</div>
                                <div class="mt-1 text-sm text-gray-600">{{ record.currency || 'USD' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div v-if="transactionItems.length > 0" class="border-t border-gray-200 px-8 py-6 print:px-0">
                    <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Line Items</h2>
                    <table class="w-full border border-gray-200 text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-700">Item</th>
                                <th class="w-12 px-3 py-2 text-right text-xs font-semibold uppercase text-gray-700">Qty</th>
                                <th class="w-24 px-3 py-2 text-right text-xs font-semibold uppercase text-gray-700">Price</th>
                                <th class="w-24 px-3 py-2 text-right text-xs font-semibold uppercase text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template v-for="item in transactionItems" :key="item.id">
                                <tr>
                                    <td class="px-3 py-2 align-top">
                                        <div class="font-medium text-gray-900">{{ item.name }}</div>
                                        <div v-if="item.description" class="mt-0.5 text-xs text-gray-500">{{ item.description }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700">{{ item.quantity ?? 1 }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900">
                                        {{ formatCurrency(lineBaseTotal(item) + taxOnItem(item)) }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(opt, oix) in lineAssetSelectedOptions(item)"
                                    :key="`opt-${item.id}-${opt.id ?? oix}`"
                                    class="bg-sky-50/80"
                                >
                                    <td class="px-3 py-1.5 pl-8 align-top">
                                        <div class="text-xs text-gray-600">
                                            <span class="mr-0.5 text-sky-700">↳</span>{{ selectedOptionLabel(opt) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-xs text-gray-500">—</td>
                                    <td class="px-3 py-1.5 text-right text-xs text-gray-600">{{ formatCurrency(opt.price) }}</td>
                                    <td class="px-3 py-1.5 text-right text-xs text-gray-600">
                                        {{ formatCurrency(selectedOptionUnitPrice(opt) + taxOnAssetOption(opt)) }}
                                    </td>
                                </tr>
                                <tr v-for="(addon, aix) in (item.addons ?? [])" :key="`addon-${item.id}-${addon.id ?? aix}`" class="bg-gray-50">
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

                    <div class="mt-4 flex justify-end">
                        <div class="w-full space-y-2 md:w-1/3">
                            <div v-if="taxRate > 0" class="flex justify-between text-sm">
                                <span class="text-gray-600">Tax ({{ taxRate }}%):</span>
                                <span class="font-medium text-gray-900">included</span>
                            </div>
                            <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-xl font-bold">
                                <span class="text-gray-900">Total:</span>
                                <span class="text-gray-900">{{ formatCurrency(displayTotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract Terms (description) -->
                <div v-if="record.description" class="border-t border-gray-200 px-8 py-6 print:px-0">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Terms &amp; Conditions</h2>
                    <div class="prose prose-sm max-w-none">
                        <p class="whitespace-pre-line text-gray-900">{{ record.description }}</p>
                    </div>
                </div>

                <!-- Billing Address -->
                <div v-if="record.billing_address_line1 || record.transaction?.billing_address_line1" class="border-t border-gray-200 px-8 py-6 print:px-0">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Billing Address</h2>
                    <div class="space-y-1 text-sm text-gray-700">
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

                <!-- Customer signature: recorded (included when printing) -->
                <div
                    v-if="isSigned"
                    class="border-t-2 border-gray-900 px-8 py-8 print:break-inside-avoid print:px-0"
                >
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-900">Customer Signature</h2>

                    <div
                        v-if="account.contract_ack_text || account.service_ticket_ack_text"
                        class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-5"
                    >
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">
                            {{ (account.contract_ack_text || account.service_ticket_ack_text).replace('[COMPANY NAME]', companyName) }}
                        </p>
                    </div>

                    <div class="mb-6 flex justify-center">
                        <div v-if="record.signature_url" class="signature-surface">
                            <img
                                :src="record.signature_url"
                                alt="Customer signature"
                                class="max-h-40 w-auto max-w-full print:max-h-52"
                            />
                        </div>
                        <div
                            v-else-if="Number(record.signature_method) === 5 && record.customer_signature"
                            class="signature-surface px-8 py-6"
                        >
                            <p class="signature-surface-text signature-cursive text-center text-4xl">{{ record.customer_signature }}</p>
                        </div>
                        <p v-else class="text-center text-sm text-gray-500">Signature on file.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <span class="text-gray-500">Print name</span>
                            <p class="font-medium text-gray-900">{{ record.signed_name || '—' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Signed on</span>
                            <p class="font-medium text-gray-900">{{ formatDateTime(record.signed_at) }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Contract total</span>
                            <p class="font-medium text-gray-900">{{ formatCurrency(displayTotal) }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Contract</span>
                            <p class="font-medium text-gray-900">{{ record.contract_number }}</p>
                        </div>
                    </div>
                </div>

                <!-- Customer signature: capture (hidden when printing) -->
                <div v-else class="border-t-2 border-gray-900 px-8 py-8 print:hidden print:px-0">
                    <h2 class="mb-6 text-sm font-semibold uppercase tracking-wide text-gray-900">Customer Signature</h2>

                    <!-- Acknowledgement Text -->
                    <div v-if="account.contract_ack_text || account.service_ticket_ack_text" class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-5">
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">
                            {{ (account.contract_ack_text || account.service_ticket_ack_text).replace('[COMPANY NAME]', companyName) }}
                        </p>
                    </div>

                    <!-- Signature Mode Toggle -->
                    <div class="mb-6">
                        <label class="mb-3 block text-sm font-medium text-gray-700">Signature</label>
                        <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                            <button
                                type="button"
                                @click="signatureMode = 'draw'"
                                :class="[
                                    'flex items-center gap-2 px-5 py-2.5 text-sm font-medium transition-colors',
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
                                    'flex items-center gap-2 border-l border-gray-300 px-5 py-2.5 text-sm font-medium transition-colors',
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
                    <div v-show="signatureMode === 'draw'" class="relative mb-6">
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
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700" @click="undoSignature">
                                <span class="material-icons text-sm">undo</span>
                                Undo
                            </button>
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700" @click="clearSignature">
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
                            class="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg transition-colors focus:border-gray-900 focus:ring-0"
                        />
                        <div
                            v-if="typedSignature.trim()"
                            class="mt-4 flex items-end justify-center rounded-lg border-2 border-gray-200 bg-white px-6 py-8"
                        >
                            <div class="w-full text-center">
                                <p class="signature-cursive inline-block min-w-[200px] border-b border-gray-300 pb-2 text-4xl text-gray-900">
                                    {{ typedSignature }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Print Name -->
                    <div class="mb-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Print Name</label>
                        <input
                            v-model="signForm.signed_name"
                            type="text"
                            placeholder="Your full legal name"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-gray-900 focus:ring-0"
                        />
                        <p v-if="signForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ signForm.errors.signed_name }}</p>
                    </div>

                    <!-- Consent -->
                    <div class="mb-8">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="consent"
                                type="checkbox"
                                class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                            />
                            <span class="text-sm leading-relaxed text-gray-700">
                                I have read and agree to the terms of this contract. By signing, I authorize this contract to proceed as outlined above.
                            </span>
                        </label>
                        <p v-if="signForm.errors.consent" class="ml-8 mt-1 text-sm text-red-600">{{ signForm.errors.consent }}</p>
                    </div>

                    <!-- Error -->
                    <div v-if="signError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="flex items-center gap-2 text-sm text-red-700">
                            <span class="material-icons text-sm">error_outline</span>
                            {{ signError }}
                        </div>
                    </div>

                    <div v-if="Object.keys(signForm.errors).length" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                        <ul class="space-y-1 text-sm text-red-700">
                            <li v-for="(error, key) in signForm.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-8 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="signForm.processing || !consent"
                            @click="submitSign"
                        >
                            <span v-if="signForm.processing" class="material-icons animate-spin text-sm">refresh</span>
                            <span v-else class="material-icons text-sm">draw</span>
                            {{ signForm.processing ? 'Submitting...' : 'Sign Contract' }}
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-900 px-8 py-4 text-center text-xs text-white print:px-0">
                    <p>Thank you for your business!</p>
                    <p v-if="locationPreview?.phone" class="mt-1">
                        Questions? Call us at {{ formatPhoneNumber(locationPreview.phone) }}
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

    #contract-print-root {
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
}
</style>
