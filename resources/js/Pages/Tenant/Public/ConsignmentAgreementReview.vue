<script setup>
import { computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicSignatureForm from '@/Components/Tenant/Public/PublicSignatureForm.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    consignmentPolicies: { type: Array, default: () => [] },
    policiesLocked: { type: Boolean, default: false },
});

const unit = computed(() => props.record.asset_unit ?? props.record.assetUnit ?? null);
const subsidiary = computed(() => unit.value?.subsidiary ?? null);

const companyName = computed(
    () => subsidiary.value?.display_name || props.account?.settings?.business_name || props.account?.name || 'Company',
);

const isSigned = computed(() => !!props.record.signed_at);
const canAct = computed(() => !isSigned.value);

const formatDate = (value) => {
    if (!value) {
        return '—';
    }
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) {
            return '—';
        }
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch {
        return '—';
    }
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) {
            return '—';
        }
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

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';

const formatPhoneNumber = (phone) => {
    if (!phone) {
        return '';
    }
    const cleaned = phone.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};

const signAction = computed(() => route('consignment-agreements.sign', props.record.uuid));

const acknowledgementText = computed(() => {
    const fee = props.account?.consignment_fee_percent;
    const feeLine =
        fee != null && fee !== ''
            ? `Consignment fee: ${parseFloat(fee).toLocaleString('en-US', { maximumFractionDigits: 2 })}% of the sale price (unless otherwise agreed in writing).\n\n`
            : '';
    const terms = (props.account?.consignment_terms || '').trim();
    const termsBlock = terms ? `${terms}\n\n` : '';
    return [
        `${feeLine}${termsBlock}By signing below, you confirm that the information on this agreement is accurate to the best of your knowledge and that you agree to consign the described property with ${companyName.value}.`,
        '',
        `${companyName.value} may rely on this electronic signature to the same extent as a handwritten signature.`,
    ].join('\n');
});

const consentLabel = computed(
    () =>
        `I have read and agree to the terms above and authorize ${companyName.value} to rely on my electronic signature.`,
);

const ownerContact = computed(() => props.record.owner_contact ?? props.record.ownerContact ?? null);
const ownerAddress = computed(() => props.record.owner_contact_address ?? props.record.ownerContactAddress ?? null);

const ownerAddressLines = computed(() => {
    const a = ownerAddress.value;
    if (!a) {
        return [];
    }
    const lines = [];
    if (a.address_line_1) {
        lines.push(a.address_line_1);
    }
    if (a.address_line_2) {
        lines.push(a.address_line_2);
    }
    const cityLine = [a.city, [a.state, a.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    if (cityLine) {
        lines.push(cityLine);
    }
    if (a.country) {
        lines.push(a.country);
    }
    return lines;
});

const footerPhone = computed(() => subsidiary.value?.phone || props.account?.phone || '');

const signSubmitClass =
    'inline-flex w-full items-center justify-center gap-2 px-8 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm sm:w-auto';

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

const pricingRows = computed(() => [
    { key: 'boat', label: 'Boat', askingKey: 'asking_boat', minimumKey: 'minimum_boat' },
    { key: 'motor', label: 'Motor', askingKey: 'asking_motor', minimumKey: 'minimum_motor' },
    { key: 'other', label: 'Other', askingKey: 'asking_other', minimumKey: 'minimum_other' },
    { key: 'sold', label: 'Sold', askingKey: 'asking_sold', minimumKey: 'minimum_sold' },
]);
</script>

<template>
    <Head :title="`Consignment #${record.display_name || 'Agreement'}`" />

    <div class="min-h-screen bg-gray-100">
        <div
            id="consignment-agreement-print-root"
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

            <div
                v-if="isSigned"
                class="mb-4 bg-green-600 text-white rounded-t-lg px-6 py-4 print:px-0 flex items-center gap-4 print:rounded-none print:bg-white print:text-green-700 print:border-2 print:border-green-600 print:mb-0"
            >
                <span class="material-icons text-3xl">check_circle</span>
                <div class="flex-1">
                    <h2 class="text-lg font-bold leading-tight">Consignment agreement signed</h2>
                    <p class="text-sm text-green-50 print:text-green-700">
                        Signed on {{ formatDateTime(record.signed_at) }}<span v-if="record.signed_name"> by {{ record.signed_name }}</span>
                    </p>
                </div>
            </div>

            <div class="bg-white shadow-lg print:shadow-none">
                <!-- Company header (matches ServiceTicketReview) -->
                <div class="border-b-4 border-gray-900 px-8 print:px-0 py-6 print:border-b-2">
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
                                    {{ companyName }}
                                </h1>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <p v-if="subsidiary?.address_line_1">
                                        {{ subsidiary.address_line_1 }}
                                        <span v-if="subsidiary?.address_line_2">, {{ subsidiary.address_line_2 }}</span>
                                    </p>
                                    <p v-if="subsidiary?.city">
                                        {{ subsidiary.city }}<span v-if="subsidiary?.state">, {{ subsidiary.state }}</span>
                                        {{ subsidiary?.postal_code }}
                                    </p>
                                    <p v-if="subsidiary?.phone" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ subsidiary.phone }}
                                    </p>
                                    <p v-if="subsidiary?.email" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">email</span>
                                        {{ subsidiary.email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 uppercase">Consignment agreement</div>
                            <div class="text-3xl font-bold text-gray-900 font-mono">
                                #{{ record.display_name || '—' }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ formatDate(record.agreement_date || record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Owner & asset (same grid rhythm as ServiceTicketReview customer / asset) -->
                <div class="px-8 print:px-0 py-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Owner / seller</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="space-y-2">
                                    <div class="font-semibold text-gray-900 text-lg">
                                        {{ ownerContact?.display_name || '—' }}
                                    </div>
                                    <div v-if="ownerContact?.email" class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="material-icons text-sm">email</span>
                                        {{ ownerContact.email }}
                                    </div>
                                    <div v-if="ownerContact?.phone" class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ ownerContact.phone }}
                                    </div>
                                    <div v-if="ownerContact?.mobile" class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="material-icons text-sm">smartphone</span>
                                        {{ ownerContact.mobile }}
                                    </div>
                                    <div v-if="ownerAddressLines.length" class="flex items-start gap-2 text-sm text-gray-600 mt-3">
                                        <span class="material-icons text-sm mt-0.5">location_on</span>
                                        <div class="whitespace-pre-line">{{ ownerAddressLines.join('\n') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="unit">
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="space-y-2">
                                    <div class="font-semibold text-gray-900 text-lg">
                                        {{ unit.display_name || '—' }}
                                    </div>
                                    <div v-if="unit.asset?.make?.display_name" class="text-sm text-gray-600">
                                        <span class="font-medium">Make:</span> {{ unit.asset.make.display_name }}
                                    </div>
                                    <div v-if="unit.asset?.year" class="text-sm text-gray-600">
                                        <span class="font-medium">Year:</span> {{ unit.asset.year }}
                                    </div>
                                    <div v-if="unit.serial_number" class="text-sm text-gray-600">
                                        <span class="font-medium">Serial:</span> {{ unit.serial_number }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agreement details -->
                <div class="px-8 print:px-0 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Agreement details</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm mb-6">
                        <div>
                            <dt class="text-gray-500">Boat title signed &amp; delivered</dt>
                            <dd class="font-medium text-gray-900">{{ record.boat_title_signed_delivered ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Boat</h3>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.boat_description || '—' }}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Motor</h3>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.motor_description || '—' }}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Other</h3>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.other_description || '—' }}</p>
                            </div>
                        </div>
                        <div v-if="record.notes">
                            <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Notes</h3>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-gray-900 whitespace-pre-line">{{ record.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing table -->
                <div class="px-8 print:px-0 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Pricing</h2>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-900">
                                <th class="text-left py-3 text-sm font-semibold text-gray-900">Item</th>
                                <th class="text-right py-3 text-sm font-semibold text-gray-900">Asking</th>
                                <th class="text-right py-3 text-sm font-semibold text-gray-900">Minimum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="row in pricingRows" :key="row.key" class="hover:bg-gray-50">
                                <td class="py-3 font-medium text-gray-900">{{ row.label }}</td>
                                <td class="py-3 text-right text-gray-900">{{ formatCurrency(record[row.askingKey]) }}</td>
                                <td class="py-3 text-right text-gray-900">{{ formatCurrency(record[row.minimumKey]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Policies -->
                <div v-if="consignmentPolicies.length" class="px-8 print:px-0 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Consignment policies</h2>
                    <p v-if="policiesLocked" class="mb-3 text-xs text-gray-500">Policies as agreed at signing</p>
                    <ul class="list-disc space-y-3 pl-5 text-sm text-gray-700">
                        <li v-for="p in consignmentPolicies" :key="p.id" class="whitespace-pre-line">{{ p.body }}</li>
                    </ul>
                </div>

                <!-- Terms notice -->
                <div v-if="account?.consignment_terms" class="px-8 print:px-0 py-6 border-t border-gray-200">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg print:break-inside-avoid">
                        <div class="flex items-start gap-3">
                            <span class="material-icons text-blue-600 text-xl flex-shrink-0">info</span>
                            <div class="text-sm text-blue-900">
                                <p class="font-semibold mb-1">Terms of consignment</p>
                                <p class="whitespace-pre-line leading-relaxed">{{ account.consignment_terms }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signed signature (screen + print) -->
                <div
                    v-if="isSigned && (record.signature_url || (record.signature_method === 5 && record.customer_signature))"
                    class="px-8 print:px-0 py-6 border-t border-gray-200"
                >
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer signature</h2>
                    <div class="flex items-start gap-6 flex-wrap">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
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

                <!-- Authorization (matches ServiceTicketReview customer authorization block) -->
                <div v-if="canAct" class="px-8 print:px-0 py-8 border-t-2 border-gray-900 print:hidden">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Owner authorization</h2>
                    <PublicSignatureForm
                        :action="signAction"
                        submit-label="Sign agreement"
                        :acknowledgement-text="acknowledgementText"
                        :consent-label="consentLabel"
                        :submit-button-class="signSubmitClass"
                    />
                </div>

                <!-- Footer -->
                <div class="px-8 print:px-0 py-4 bg-gray-900 text-white text-center text-xs">
                    <p>Thank you for your business!</p>
                    <p v-if="footerPhone" class="mt-1">
                        Questions? Call us at {{ formatPhoneNumber(footerPhone) }}
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

    #consignment-agreement-print-root {
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
}
</style>
