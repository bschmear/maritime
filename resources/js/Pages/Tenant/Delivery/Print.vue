<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const hasChecklist = computed(() => (props.checklistItems || []).length > 0);

/** Vue templates cannot call `window.*` — use script functions. */
function triggerPrint(mode) {
    const cls = `print-mode-${mode}`;
    document.documentElement.classList.add(cls);
    const cleanup = () => {
        document.documentElement.classList.remove(cls);
        window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup);
    window.print();
}

function handleClose() {
    if (window.opener) {
        window.close();
        return;
    }
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('deliveries.show', props.record.id));
}
</script>

<template>
    <Head :title="`Print - ${record.display_name}`" />

    <div class="min-h-screen bg-white">
        <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                <span class="truncate font-medium text-gray-800">{{ record.display_name }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-500">Print:</span>
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1.5 text-xs font-medium hover:bg-gray-50"
                        @click="triggerPrint('customer')"
                    >
                        Customer copy
                    </button>
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1.5 text-xs font-medium hover:bg-gray-50"
                        @click="triggerPrint('internal')"
                    >
                        Internal copy
                    </button>
                    <button
                        v-if="hasChecklist"
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1.5 text-xs font-medium hover:bg-gray-50"
                        @click="triggerPrint('checklist')"
                    >
                        Checklist only
                    </button>
                    <button
                        type="button"
                        class="rounded border border-gray-800 bg-gray-900 px-2 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                        @click="triggerPrint('all')"
                    >
                        All pages
                    </button>
                    <span class="mx-1 hidden h-5 w-px bg-gray-200 sm:inline" aria-hidden="true" />
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1.5 hover:bg-gray-50"
                        @click="handleClose"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 print:space-y-0 print:py-0">
            <div
                id="delivery-print-customer"
                class="customer-document bg-white shadow print:shadow-none print:break-after-page"
            >
                <DeliveryDocumentBody :record="record" :account="account" variant="customer" />
            </div>

            <div id="delivery-print-internal" class="internal-document bg-white shadow print:shadow-none">
                <DeliveryDocumentBody :record="record" :account="account" variant="internal" />
            </div>

            <div
                v-if="hasChecklist"
                id="delivery-checklist-print-root"
                class="checklist-document bg-white px-6 py-6 shadow print:shadow-none print:px-4 print:break-before-page"
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
                        <div class="font-mono text-lg font-bold text-gray-900">{{ record.display_name }}</div>
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

<style>
/**
 * Print targets: `triggerPrint` adds `print-mode-*` on `<html>` before `window.print()`.
 * Screen always shows every section; only the print preview / PDF is filtered.
 */
@media print {
    html.print-mode-customer .internal-document,
    html.print-mode-customer .checklist-document {
        display: none !important;
    }

    html.print-mode-internal .customer-document,
    html.print-mode-internal .checklist-document {
        display: none !important;
    }

    html.print-mode-checklist .customer-document,
    html.print-mode-checklist .internal-document {
        display: none !important;
    }

    /* print-mode-all: show everything (no extra rules) */
}
</style>
