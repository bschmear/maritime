<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import DeliveryDocumentBody from '@/Components/Tenant/DeliveryDocumentBody.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: () => ({}) },
    checklistItems: { type: Array, default: () => [] },
});

const itemsByCategory = computed(() => {
    const g = {};
    (props.checklistItems || []).forEach((item) => {
        const id = item.category_id ?? item.category?.id ?? 'uncategorized';
        const name = item.category?.name ?? 'Other';
        if (!g[id]) g[id] = { id, name, items: [] };
        g[id].items.push(item);
    });
    return Object.values(g).sort((a, b) => a.name.localeCompare(b.name));
});

onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <Head :title="`Print - ${record.display_name}`" />

    <div class="min-h-screen bg-white">
        <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 px-4 py-2 text-sm">
                <span class="truncate font-medium text-gray-800">{{ record.display_name }}</span>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="rounded border border-gray-300 px-2 py-1.5 hover:bg-gray-50" @click="window.close()">Close</button>
                    <button type="button" class="rounded bg-green-600 px-2 py-1.5 text-white hover:bg-green-700" @click="window.print()">Print</button>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 print:space-y-0 print:py-0">
            <div id="delivery-print-root" class="bg-white shadow print:shadow-none">
                <DeliveryDocumentBody :record="record" :account="account" />
            </div>

            <div
                v-if="checklistItems.length"
                id="delivery-checklist-print-root"
                class="bg-white px-6 py-6 shadow print:shadow-none print:px-4"
            >
                <header class="mb-4 flex items-end justify-between gap-4 border-b-2 border-gray-900 pb-2 text-sm">
                    <div>
                        <h1 class="text-base font-bold text-gray-900">Delivery Checklist</h1>
                        <p class="mt-0.5 text-xs text-gray-600">
                            {{ record.customer?.display_name || record.customer?.contact?.display_name }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Delivery</div>
                        <div class="text-lg font-bold font-mono text-gray-900">{{ record.display_name }}</div>
                    </div>
                </header>

                <section v-for="cat in itemsByCategory" :key="cat.id" class="mb-4 break-inside-avoid">
                    <h2 class="mb-2 border-b border-gray-300 pb-0.5 text-sm font-semibold text-gray-900">{{ cat.name }}</h2>
                    <ul class="space-y-1.5">
                        <li v-for="item in cat.items" :key="item.id" class="flex gap-2 text-xs text-gray-900">
                            <span class="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-2 border-gray-900 bg-white" />
                            <span>{{ item.label }}<span v-if="item.is_required" class="font-semibold text-red-600"> *</span></span>
                        </li>
                    </ul>
                </section>

                <div class="mt-8 grid grid-cols-2 gap-6 text-xs text-gray-600">
                    <div>
                        <div class="mb-1 h-10 border-b border-gray-900"></div>
                        Technician Signature
                    </div>
                    <div>
                        <div class="mb-1 h-10 border-b border-gray-900"></div>
                        Date
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
