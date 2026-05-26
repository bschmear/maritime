<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import DeliveryReviewDocument from '@/Components/Tenant/DeliveryReviewDocument.vue';

defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
});

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
    <Head title="Delivery Review & Signature" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <div
            id="delivery-print-root"
            class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:mx-0 print:max-w-none print:p-0"
        >
            <div class="mb-4 flex justify-end print:hidden">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="handlePrint"
                >
                    <span class="material-icons text-base">print</span>
                    Print
                </button>
            </div>

            <DeliveryReviewDocument
                :record="record"
                :account="account"
                :logo-url="logoUrl"
                mode="public"
            />
        </div>
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

    #delivery-print-root {
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
}
</style>
