<script setup>
import ConsignmentAgreementDocument from '@/Components/Tenant/ConsignmentAgreementDocument.vue';
import { computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    consignmentPolicies: { type: Array, default: () => [] },
    policiesLocked: { type: Boolean, default: false },
});

const signAction = computed(() => route('consignment-agreements.sign', props.record.uuid));

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
    <Head :title="`Consignment #${record.display_name || 'Agreement'}`" />

    <div class="min-h-screen bg-gray-100">
        <div class="mb-4 flex justify-end max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 print:hidden">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                @click="handlePrint"
            >
                <span class="material-icons text-base">print</span>
                Print
            </button>
        </div>

        <ConsignmentAgreementDocument
            :record="record"
            :account="account"
            :logo-url="logoUrl"
            :consignment-policies="consignmentPolicies"
            :policies-locked="policiesLocked"
            :sign-action="signAction"
        />
    </div>
</template>

<style>
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
