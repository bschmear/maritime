<script setup>
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';

defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
});

const emit = defineEmits(['close']);

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <!-- Teleport to body so print CSS can display:none #app (same pattern as contract preview). -->
    <Teleport to="body">
        <div
            class="invoice-preview-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm sm:p-6 print:static print:inset-auto print:bg-white print:p-0"
            role="dialog"
            aria-modal="true"
            aria-label="Invoice preview"
            @click.self="emit('close')"
        >
            <div
                class="invoice-preview-panel flex max-h-[min(calc(100dvh-2rem),56rem)] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-gray-100 shadow-2xl dark:bg-gray-900 print:max-h-none print:overflow-visible print:shadow-none"
                @click.stop
            >
                <!-- Toolbar (aligned with ServiceTicketPreview action bar) -->
                <div
                    class="invoice-preview-toolbar flex shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:px-6 lg:px-8 print:hidden"
                >
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Customer preview
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            This is how the invoice will appear to the customer
                        </p>
                        <p class="mt-1 truncate text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ record.display_name || `Invoice #${record.sequence ?? record.id}` }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700"
                            @click="handlePrint"
                        >
                            <span class="material-icons text-sm">print</span>
                            Print
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-sm">close</span>
                            Close
                        </button>
                    </div>
                </div>

                <!-- Scrollable document area (same padding rhythm as ServiceTicketPreview) -->
                <div
                    class="invoice-preview-scroll min-h-0 flex-1 overflow-y-auto print:block print:h-auto print:min-h-0 print:flex-none print:overflow-visible"
                >
                    <div
                        id="invoice-print-root"
                        class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:py-0"
                    >
                        <InvoiceDocumentBody
                            :record="record"
                            :account="account"
                            :enum-options="enumOptions"
                            :logo-url="logoUrl"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

