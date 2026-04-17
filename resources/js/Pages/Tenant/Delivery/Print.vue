<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import DeliveryDocumentBody from '@/Components/Tenant/DeliveryDocumentBody.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    checklistItems: { type: Array, default: () => [] },
});

const itemsByCategory = computed(() => {
    const grouped = {};
    (props.checklistItems || []).forEach((item) => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) grouped[catId] = { id: catId, name: catName, items: [] };
        grouped[catId].items.push(item);
    });
    return Object.values(grouped).sort((a, b) => a.name.localeCompare(b.name));
});

const doPrint = () => window.print();
const doClose = () => window.close();

onMounted(() => {
    setTimeout(() => window.print(), 300);
});
</script>

<template>
    <Head :title="`Print - ${record.display_name}`" />

    <div class="min-h-screen bg-white">
        <!-- Toolbar (screen only) -->
        <div class="no-print border-b border-gray-200 bg-white sticky top-0 z-10">
            <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Printable view</div>
                    <div class="text-lg font-semibold">{{ record.display_name }}</div>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="doClose" class="px-3 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50">Close</button>
                    <button type="button" @click="doPrint" class="px-3 py-2 text-sm text-white bg-green-600 hover:bg-green-700 rounded">
                        Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Delivery document -->
        <div class="max-w-5xl mx-auto py-8">
            <div id="delivery-print-root" class="bg-white shadow print:shadow-none">
                <DeliveryDocumentBody :record="record" :account="account" />
            </div>
        </div>

        <!-- Checklist page (separate printed page) -->
        <div v-if="checklistItems.length" class="max-w-5xl mx-auto pb-12">
            <div id="delivery-checklist-print-root" class="bg-white shadow print:shadow-none px-8 py-10">
                <div class="border-b-2 border-gray-900 pb-4 mb-6">
                    <div class="flex items-end justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Delivery Checklist</h1>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ record.customer?.display_name || record.customer?.contact?.display_name || '' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-600 uppercase">Delivery</div>
                            <div class="text-xl font-bold text-gray-900 font-mono">{{ record.display_name }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div v-for="cat in itemsByCategory" :key="cat.id" class="break-inside-avoid">
                        <h2 class="text-base font-semibold text-gray-900 border-b border-gray-300 pb-1 mb-3">
                            {{ cat.name }}
                        </h2>
                        <ul class="space-y-2">
                            <li v-for="item in cat.items" :key="item.id" class="flex items-start gap-3">
                                <span class="flex-shrink-0 w-5 h-5 border-2 border-gray-900 rounded-sm mt-0.5 bg-white"></span>
                                <div class="flex-1 text-sm text-gray-900">
                                    {{ item.label }}
                                    <span v-if="item.is_required" class="text-red-600 font-semibold ml-1">*</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Signoff -->
                <div class="mt-12 grid grid-cols-2 gap-8">
                    <div>
                        <div class="border-b border-gray-900 h-12"></div>
                        <div class="text-xs text-gray-600 mt-1">Technician Signature</div>
                    </div>
                    <div>
                        <div class="border-b border-gray-900 h-12"></div>
                        <div class="text-xs text-gray-600 mt-1">Date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print { display: none !important; }
}
</style>
