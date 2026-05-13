<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicDocumentHeader from '@/Components/Tenant/Public/PublicDocumentHeader.vue';
import PublicSignatureForm from '@/Components/Tenant/Public/PublicSignatureForm.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    consignmentPolicies: { type: Array, default: () => [] },
});

const unit = computed(() => props.record.asset_unit ?? props.record.assetUnit ?? null);

const companyName = computed(
    () => unit.value?.subsidiary?.display_name || props.account?.settings?.business_name || props.account?.name || 'Company',
);

const isSigned = computed(() => !!props.record.signed_at);

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch {
        return '—';
    }
};

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';

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

const handlePrint = () => window.print();
</script>

<template>
    <Head :title="`Consignment — ${record.display_name ?? 'Agreement'}`" />

    <div class="min-h-screen bg-gray-100">
        <div class="max-w-3xl mx-auto w-full px-4 py-8 space-y-6">
            <PublicDocumentHeader
                :logo-url="logoUrl"
                :title="companyName"
                :subtitle="isSigned ? 'Signed consignment agreement' : 'Consignment agreement'"
                right-label="Reference"
                :right-value="record.display_name || '—'"
            />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Agreement details</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Date</dt>
                        <dd class="font-medium text-gray-900">{{ formatDate(record.agreement_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Boat title signed &amp; delivered</dt>
                        <dd class="font-medium text-gray-900">{{ record.boat_title_signed_delivered ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Boat</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ record.boat_description || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Motor</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ record.motor_description || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Other</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ record.other_description || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Owner / seller</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Name</dt>
                        <dd class="font-medium text-gray-900">{{ record.owner_seller_name || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Address</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ record.owner_address || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Phone 1</dt>
                        <dd class="text-gray-900">{{ record.owner_phone_1 || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Phone 2</dt>
                        <dd class="text-gray-900">{{ record.owner_phone_2 || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Notes</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ record.notes || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Asking price</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Boat</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.asking_boat) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Motor</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.asking_motor) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Other</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.asking_other) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Sold</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.asking_sold) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Minimum price</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Boat</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.minimum_boat) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Motor</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.minimum_motor) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Other</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.minimum_other) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Sold</p>
                        <p class="font-medium text-gray-900">{{ formatCurrency(record.minimum_sold) }}</p>
                    </div>
                </div>
            </div>

            <div v-if="consignmentPolicies.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Consignment policies</h3>
                <ul class="list-disc space-y-2 pl-5 text-sm text-gray-700">
                    <li v-for="p in consignmentPolicies" :key="p.id" class="whitespace-pre-line">{{ p.body }}</li>
                </ul>
            </div>

            <div v-if="account?.consignment_terms" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Terms of consignment</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ account.consignment_terms }}</p>
            </div>

            <div v-if="isSigned" class="bg-white rounded-xl shadow-sm border border-emerald-200 p-6">
                <div class="flex items-center gap-3 text-emerald-800 mb-4">
                    <span class="material-icons text-2xl">check_circle</span>
                    <div>
                        <p class="font-semibold">Signed</p>
                        <p class="text-sm text-emerald-700">
                            {{ record.signed_name }} — {{ formatDate(record.signed_at) }}
                        </p>
                    </div>
                </div>
                <div v-if="record.signature_url" class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <p class="text-xs text-gray-500 mb-2">Signature on file</p>
                    <img :src="record.signature_url" alt="Signature" class="max-h-32 object-contain" />
                </div>
                <div
                    v-else-if="record.customer_signature"
                    class="border border-gray-200 rounded-lg p-6 bg-white text-center"
                >
                    <p class="text-xs text-gray-500 mb-2">Typed signature</p>
                    <p class="text-3xl signature-cursive text-gray-900">{{ record.customer_signature }}</p>
                </div>
            </div>

            <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 print:hidden">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">Owner signature</h3>
                <PublicSignatureForm
                    :action="signAction"
                    submit-label="Sign agreement"
                    :acknowledgement-text="acknowledgementText"
                    :consent-label="consentLabel"
                />
            </div>

            <div class="flex justify-end print:hidden">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-primary-700 hover:text-primary-800"
                    @click="handlePrint"
                >
                    <span class="material-icons text-base">print</span>
                    Print
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}

@media print {
    .print\:hidden {
        display: none !important;
    }
    body {
        background: white;
    }
}
</style>
