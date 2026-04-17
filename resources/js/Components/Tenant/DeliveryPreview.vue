<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DeliveryDocumentBody from '@/Components/Tenant/DeliveryDocumentBody.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    checklistItems: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const sending = ref(false);

const handlePrint = () => {
    try {
        window.open(route('deliveries.print', props.record.id), '_blank');
    } catch {
        setTimeout(() => window.print(), 50);
    }
};

const handleSendSignatureRequest = () => {
    if (!confirm('Send signature request to the customer?')) return;
    sending.value = true;
    router.post(route('deliveries.send-signature-request', props.record.id), {}, {
        preserveState: true,
        onSuccess: () => { alert('Signature request sent successfully!'); sending.value = false; },
        onError: () => { alert('Failed to send signature request.'); sending.value = false; },
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Action Bar -->
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Preview</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This is how the delivery document will appear.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="$emit('close')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg"
                    >
                        <span class="material-icons text-sm">close</span>
                        Close
                    </button>
                    <button
                        v-if="!record.signed_at"
                        @click="handleSendSignatureRequest"
                        :disabled="sending"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50 rounded-lg"
                    >
                        <span v-if="sending" class="material-icons text-sm animate-spin">refresh</span>
                        <span v-else class="material-icons text-sm">send</span>
                        {{ sending ? 'Sending…' : 'Send Signature Request' }}
                    </button>
                    <button
                        @click="handlePrint"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg"
                    >
                        <span class="material-icons text-sm">print</span>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Printable document -->
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div id="delivery-print-root" class="bg-white shadow-lg print:shadow-none">
                <DeliveryDocumentBody :record="record" :account="account" />
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .sticky { display: none !important; }
    .shadow-lg { box-shadow: none !important; }
    @page { margin: 0.5in; }
}
</style>
