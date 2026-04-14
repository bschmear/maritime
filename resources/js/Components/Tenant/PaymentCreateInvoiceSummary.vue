<script setup>
import { computed } from 'vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    invoiceEnumOptions: { type: Array, default: () => [] },
});

const statusMeta = computed(() => {
    const s = props.invoice?.status;
    const list = props.invoiceEnumOptions ?? [];
    return list.find((o) => o.value === s || String(o.id) === String(s))
        ?? { name: s, bgClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' };
});

const formatCurrency = (value) => {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-US', {
        style: 'currency',
        currency: props.invoice?.currency || 'USD',
    });
};

const dueDateLabel = computed(() => {
    const iso = props.invoice?.due_at;
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return null;
    }
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden lg:sticky lg:top-6">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Selected invoice
            </h3>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <div class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ invoice.display_name || `INV-${invoice.sequence}` }}
                </div>
                <div v-if="invoice.customer_name" class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">
                    {{ invoice.customer_name }}
                </div>
                <span
                    class="inline-flex mt-2 items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                    :class="statusMeta.bgClass"
                >
                    {{ statusMeta.name }}
                </span>
            </div>

            <dl class="space-y-3 text-sm border-t border-gray-100 dark:border-gray-700 pt-4">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Invoice total</dt>
                    <dd class="font-medium text-gray-900 dark:text-white tabular-nums">{{ formatCurrency(invoice.total) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Amount paid</dt>
                    <dd class="font-medium text-green-700 dark:text-green-400 tabular-nums">{{ formatCurrency(invoice.amount_paid) }}</dd>
                </div>
                <div class="flex justify-between gap-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-600">
                    <dt class="text-gray-700 dark:text-gray-300 font-medium">Outstanding balance</dt>
                    <dd class="font-bold text-primary-600 dark:text-primary-400 tabular-nums text-base">
                        {{ formatCurrency(invoice.amount_due) }}
                    </dd>
                </div>
                <div v-if="dueDateLabel" class="flex justify-between gap-3 text-xs">
                    <dt class="text-gray-500 dark:text-gray-400">Due date</dt>
                    <dd class="text-gray-800 dark:text-gray-200">{{ dueDateLabel }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
