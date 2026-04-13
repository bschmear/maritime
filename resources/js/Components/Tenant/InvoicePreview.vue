<script setup>
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';

defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex flex-col bg-black/50 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Invoice preview"
        @click.self="emit('close')"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-1 min-h-0 flex-col rounded-lg bg-gray-100 shadow-xl">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    Customer preview — {{ record.display_name || `Invoice #${record.sequence ?? record.id}` }}
                </h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                    @click="emit('close')"
                >
                    <span class="material-icons text-[18px]">close</span>
                    Close
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                <InvoiceDocumentBody
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    :logo-url="logoUrl"
                />
            </div>
        </div>
    </div>
</template>
