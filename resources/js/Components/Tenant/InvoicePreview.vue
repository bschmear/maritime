<script setup>
import { ref } from 'vue';
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';

defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
});

const emit = defineEmits(['close', 'request-send']);

const printing = ref(false);

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};
</script>

<template>
    <!-- Shell matches ContractPreview / ServiceTicketPreview: full-height gray canvas, sticky chrome, paper card below. -->
    <div class="invoice-preview-shell min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-2 lg:py-4">
                <div class="flex items-center justify-between gap-2 lg:gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white lg:text-lg truncate">
                            Customer Preview
                        </h2>
                        <p class="hidden text-sm text-gray-500 dark:text-gray-400 lg:block mt-0.5">
                            This is how the invoice will appear to the customer
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-3">
                        <button
                            type="button"
                            aria-label="Close preview"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 lg:px-4"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-[18px]">close</span>
                            <span class="hidden lg:inline">Close</span>
                        </button>

                        <button
                            type="button"
                            aria-label="Send invoice link to customer"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="emit('request-send')"
                        >
                            <span class="material-icons text-[18px]">send</span>
                            <span class="hidden lg:inline">Send to customer</span>
                        </button>

                        <button
                            type="button"
                            :aria-label="printing ? 'Preparing print' : 'Print preview'"
                            :aria-busy="printing"
                            :disabled="printing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons animate-spin text-[18px]">refresh</span>
                            <span v-else class="material-icons text-[18px]">print</span>
                            <span class="hidden lg:inline">{{ printing ? 'Preparing…' : 'Print' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="invoice-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <InvoiceDocumentBody
                :record="record"
                :account="account"
                :enum-options="enumOptions"
                :logo-url="logoUrl"
            />
        </div>
    </div>
</template>
