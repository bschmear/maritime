<script setup>
import PublicDocumentHeader from '@/Components/Tenant/Public/PublicDocumentHeader.vue';
import PublicDocumentLineItemCard from '@/Components/Tenant/Public/PublicDocumentLineItemCard.vue';
import PublicDocumentLineItemField from '@/Components/Tenant/Public/PublicDocumentLineItemField.vue';
import PublicSignatureForm from '@/Components/Tenant/Public/PublicSignatureForm.vue';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    consignmentPolicies: { type: Array, default: () => [] },
    policiesLocked: { type: Boolean, default: false },
    previewMode: { type: Boolean, default: false },
    signAction: { type: String, default: '' },
});

const unit = computed(() => props.record.asset_unit ?? props.record.assetUnit ?? null);
const subsidiary = computed(() => unit.value?.subsidiary ?? null);

const companyName = computed(
    () => subsidiary.value?.display_name || props.account?.settings?.business_name || props.account?.name || 'Company',
);

const isSigned = computed(() => !!props.record.signed_at);
const canAct = computed(() => !props.previewMode && !isSigned.value);

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

const pricingRows = computed(() => [
    { key: 'boat', label: 'Boat', askingKey: 'asking_boat', minimumKey: 'minimum_boat' },
    { key: 'motor', label: 'Motor', askingKey: 'asking_motor', minimumKey: 'minimum_motor' },
    { key: 'other', label: 'Other', askingKey: 'asking_other', minimumKey: 'minimum_other' },
    { key: 'sold', label: 'Sold', askingKey: 'asking_sold', minimumKey: 'minimum_sold' },
]);
</script>

<template>
    <div
        id="consignment-agreement-print-root"
        class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:mx-0 print:max-w-none print:p-0"
    >
        <div
            v-if="previewMode"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden"
        >
            <span class="font-semibold">Sample preview</span>
            — Owner and boat details below are placeholders. Policy bullets, terms, and fee reflect your current settings.
        </div>

        <div
            v-if="isSigned"
            class="mb-4 flex items-center gap-4 rounded-t-lg bg-green-600 px-6 py-4 text-white print:mb-0 print:rounded-none print:border-2 print:border-green-600 print:bg-white print:px-0 print:text-green-700"
        >
            <span class="material-icons text-3xl">check_circle</span>
            <div class="flex-1">
                <h2 class="text-lg font-bold leading-tight">Consignment agreement signed</h2>
                <p class="text-sm text-green-50 print:text-green-700">
                    Signed on {{ formatDateTime(record.signed_at) }}<span v-if="record.signed_name"> by {{ record.signed_name }}</span>
                </p>
            </div>
        </div>

        <div
            class="overflow-x-hidden bg-white shadow-lg print:shadow-none"
            :class="previewMode ? 'consignment-document-preview' : 'dark:bg-gray-900'"
        >
            <PublicDocumentHeader
                :logo-url="logoUrl"
                document-label="Consignment agreement"
                :document-number="`#${record.display_name || '—'}`"
                :document-date="formatDate(record.agreement_date || record.created_at)"
                dark
            >
                <template #company>
                    <h1 class="text-xl font-bold text-gray-900 break-words dark:text-white sm:text-2xl">
                        {{ companyName }}
                    </h1>
                    <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <p v-if="subsidiary?.address_line_1">
                            {{ subsidiary.address_line_1 }}
                            <span v-if="subsidiary?.address_line_2">, {{ subsidiary.address_line_2 }}</span>
                        </p>
                        <p v-if="subsidiary?.city">
                            {{ subsidiary.city }}<span v-if="subsidiary?.state">, {{ subsidiary.state }}</span>
                            {{ subsidiary?.postal_code }}
                        </p>
                        <p v-if="subsidiary?.phone" class="flex items-center gap-1 break-all">
                            <span class="material-icons shrink-0 text-sm">phone</span>
                            {{ subsidiary.phone }}
                        </p>
                        <p v-if="subsidiary?.email" class="flex items-center gap-1 break-all">
                            <span class="material-icons shrink-0 text-sm">email</span>
                            {{ subsidiary.email }}
                        </p>
                    </div>
                </template>
            </PublicDocumentHeader>

            <div class="px-4 sm:px-8 print:px-0 py-6 bg-gray-50 dark:bg-gray-800/50 print:bg-white">
                <div class="consignment-owner-asset-grid grid grid-cols-1 md:grid-cols-2 print:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Owner / seller</h2>
                        <div class="bg-white rounded-lg p-4 border border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                            <div class="space-y-2">
                                <div class="font-semibold text-gray-900 text-lg dark:text-white">
                                    {{ ownerContact?.display_name || '—' }}
                                </div>
                                <div v-if="ownerContact?.email" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="material-icons text-sm">email</span>
                                    {{ ownerContact.email }}
                                </div>
                                <div v-if="ownerContact?.phone" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="material-icons text-sm">phone</span>
                                    {{ ownerContact.phone }}
                                </div>
                                <div v-if="ownerContact?.mobile" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="material-icons text-sm">smartphone</span>
                                    {{ ownerContact.mobile }}
                                </div>
                                <div v-if="ownerAddressLines.length" class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300 mt-3">
                                    <span class="material-icons text-sm mt-0.5">location_on</span>
                                    <div class="whitespace-pre-line">{{ ownerAddressLines.join('\n') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="unit">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset</h2>
                        <div class="bg-white rounded-lg p-4 border border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                            <div class="space-y-2">
                                <div class="font-semibold text-gray-900 text-lg dark:text-white">
                                    {{ unit.display_name || '—' }}
                                </div>
                                <div v-if="unit.asset?.make?.display_name" class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Make:</span> {{ unit.asset.make.display_name }}
                                </div>
                                <div v-if="unit.asset?.year" class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Year:</span> {{ unit.asset.year }}
                                </div>
                                <div v-if="unit.serial_number" class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Serial:</span> {{ unit.serial_number }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-8 print:px-0 py-6 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Agreement details</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm mb-6">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Boat title signed &amp; delivered</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ record.boat_title_signed_delivered ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Boat</h3>
                        <p class="text-gray-900 whitespace-pre-line dark:text-gray-100">{{ record.boat_description || '—' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Motor</h3>
                        <p class="text-gray-900 whitespace-pre-line dark:text-gray-100">{{ record.motor_description || '—' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Other</h3>
                        <p class="text-gray-900 whitespace-pre-line dark:text-gray-100">{{ record.other_description || '—' }}</p>
                    </div>
                    <div v-if="record.notes">
                        <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Notes</h3>
                        <p class="text-gray-900 whitespace-pre-line dark:text-gray-100">{{ record.notes }}</p>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-8 print:px-0 py-6 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Pricing</h2>

                <div class="mb-4 space-y-3 md:hidden print:hidden">
                    <PublicDocumentLineItemCard
                        v-for="row in pricingRows"
                        :key="`m-${row.key}`"
                        :title="row.label"
                    >
                        <PublicDocumentLineItemField label="Asking" :value="formatCurrency(record[row.askingKey])" />
                        <PublicDocumentLineItemField label="Minimum" :value="formatCurrency(record[row.minimumKey])" />
                    </PublicDocumentLineItemCard>
                </div>

                <table class="hidden w-full md:table print:table">
                    <thead>
                        <tr class="border-b-2 border-gray-900 dark:border-gray-100">
                            <th class="text-left py-3 text-sm font-semibold text-gray-900 dark:text-white">Item</th>
                            <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-white">Asking</th>
                            <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-white">Minimum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="row in pricingRows" :key="row.key" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 font-medium text-gray-900 dark:text-white">{{ row.label }}</td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(record[row.askingKey]) }}</td>
                            <td class="py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(record[row.minimumKey]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="consignmentPolicies.length" class="px-4 sm:px-8 print:px-0 py-6 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Consignment policies</h2>
                <p v-if="policiesLocked" class="mb-3 text-xs text-gray-500">Policies as agreed at signing</p>
                <ul class="list-disc space-y-3 pl-5 text-sm text-gray-700 dark:text-gray-300">
                    <li v-for="p in consignmentPolicies" :key="p.id" class="whitespace-pre-line">{{ p.body }}</li>
                </ul>
            </div>

            <div v-if="account?.consignment_terms" class="px-4 sm:px-8 print:px-0 py-6 border-t border-gray-200 dark:border-gray-700">
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg print:break-inside-avoid dark:bg-blue-950/30 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <span class="material-icons text-blue-600 text-xl flex-shrink-0 dark:text-blue-400">info</span>
                        <div class="text-sm text-blue-900 dark:text-blue-100">
                            <p class="font-semibold mb-1">Terms of consignment</p>
                            <p class="whitespace-pre-line leading-relaxed">{{ account.consignment_terms }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="isSigned && (record.signature_url || (record.signature_method === 5 && record.customer_signature))"
                class="px-4 sm:px-8 print:px-0 py-6 border-t border-gray-200 dark:border-gray-700"
            >
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer signature</h2>
                <div class="flex items-start gap-6 flex-wrap">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-gray-800 dark:border-gray-600">
                        <img
                            v-if="record.signature_url"
                            :src="record.signature_url"
                            alt="Customer signature"
                            class="max-h-24 w-auto"
                        />
                        <p v-else class="signature-cursive text-3xl text-gray-900 dark:text-white">
                            {{ record.customer_signature }}
                        </p>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1 pt-1">
                        <div>
                            <span class="text-gray-500">Signed by:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ record.signed_name || '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Date:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(record.signed_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!isSigned"
                class="px-4 sm:px-8 print:px-0 py-8 border-t-2 border-gray-900 print:break-inside-avoid dark:border-gray-100"
            >
                <div v-if="canAct" class="print:hidden">
                    <h2 class="mb-6 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:text-white">Owner authorization</h2>
                    <PublicSignatureForm
                        :action="signAction"
                        submit-label="Sign agreement"
                        :acknowledgement-text="acknowledgementText"
                        :consent-label="consentLabel"
                        :submit-button-class="signSubmitClass"
                    />
                </div>

                <div
                    v-if="previewMode || canAct"
                    class="consignment-manual-signing"
                    :class="canAct ? 'hidden print:block' : ''"
                >
                    <h2 class="mb-6 text-sm font-semibold uppercase tracking-wide text-gray-900">Owner authorization</h2>
                    <div class="mb-6 whitespace-pre-line border border-gray-200 bg-white p-4 text-sm leading-relaxed text-gray-800 print:border-gray-400">
                        {{ acknowledgementText }}
                    </div>
                    <div class="mb-8 flex items-start gap-2 text-sm text-gray-800">
                        <span
                            class="consignment-signature-checkbox mt-0.5 inline-block h-4 w-4 shrink-0 border border-gray-700 bg-white"
                            aria-hidden="true"
                        />
                        <span>{{ consentLabel }}</span>
                    </div>
                    <div class="consignment-manual-signing-grid grid gap-8 sm:grid-cols-2 print:grid-cols-2">
                        <div>
                            <div class="consignment-signature-line h-12 border-b-2 border-gray-900" />
                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Owner signature</p>
                        </div>
                        <div>
                            <div
                                class="consignment-signature-line min-h-[3rem] border-b-2 border-gray-900 pb-1 text-sm text-gray-900"
                            >
                                <span v-if="previewMode">{{ ownerContact?.display_name || '—' }}</span>
                            </div>
                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Printed name</p>
                        </div>
                        <div>
                            <div
                                class="consignment-signature-line min-h-[3rem] border-b-2 border-gray-900 pb-1 text-sm text-gray-900"
                            >
                                <span v-if="previewMode">{{ formatDate(record.agreement_date || record.created_at) }}</span>
                            </div>
                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Date</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="consignment-agreement-footer px-4 sm:px-8 print:px-0 py-4 bg-gray-900 text-white text-center text-xs">
                <p>Thank you for your business!</p>
                <p v-if="footerPhone" class="mt-1">
                    Questions? Call us at {{ formatPhoneNumber(footerPhone) }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}
</style>

<style>
/* Preview page card: always light/print styling inside the dark preview shell. */
.consignment-document-preview {
    background-color: #ffffff !important;
    color: #111827;
    color-scheme: light;
}

.consignment-document-preview .dark\:bg-gray-900,
.consignment-document-preview .dark\:bg-gray-800\/50,
.consignment-document-preview .dark\:bg-gray-800,
.consignment-document-preview .dark\:bg-gray-700,
.consignment-document-preview .dark\:bg-blue-950\/30 {
    background-color: #ffffff !important;
}

.consignment-document-preview .bg-gray-50 {
    background-color: #f9fafb !important;
}

.consignment-document-preview .bg-blue-50 {
    background-color: #eff6ff !important;
}

.consignment-document-preview .dark\:text-white,
.consignment-document-preview .dark\:text-gray-100 {
    color: #111827 !important;
}

.consignment-document-preview .dark\:text-gray-300 {
    color: #374151 !important;
}

.consignment-document-preview .dark\:text-gray-400 {
    color: #6b7280 !important;
}

.consignment-document-preview .dark\:text-blue-100 {
    color: #1e3a8a !important;
}

.consignment-document-preview .dark\:border-gray-100,
.consignment-document-preview .dark\:border-gray-700,
.consignment-document-preview .dark\:border-gray-600,
.consignment-document-preview .dark\:border-blue-800 {
    border-color: #e5e7eb !important;
}

.consignment-document-preview .dark\:divide-gray-700 > :not([hidden]) ~ :not([hidden]) {
    border-color: #e5e7eb !important;
}

.consignment-document-preview .consignment-agreement-footer {
    background-color: #111827 !important;
    color: #ffffff !important;
}

@media print {
    .consignment-owner-asset-grid,
    .consignment-manual-signing-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .consignment-signature-line,
    .consignment-signature-checkbox {
        border-color: #111827 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
