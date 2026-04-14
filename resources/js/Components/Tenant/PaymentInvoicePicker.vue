<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash-es';

const props = defineProps({
    invoiceEnumOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const searchQuery = ref('');
const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 });
const loading = ref(false);
const errorMessage = ref('');

const statusMeta = (statusValue) => {
    const list = props.invoiceEnumOptions ?? [];
    return list.find((o) => o.value === statusValue || String(o.id) === String(statusValue))
        ?? { name: statusValue, bgClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' };
};

const formatCurrency = (value, currency = 'USD') => {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('en-US', { style: 'currency', currency: currency || 'USD' });
};

const formatDue = (iso) => {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return null;
    }
};

async function fetchPage(page = 1) {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get(route('payments.create.eligible-invoices'), {
            params: {
                page,
                per_page: meta.value.per_page,
                search: searchQuery.value.trim() || undefined,
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        rows.value = data.data ?? [];
        meta.value = { ...meta.value, ...(data.meta ?? {}) };
    } catch (e) {
        console.error(e);
        errorMessage.value = 'Could not load invoices. Try again.';
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

const debouncedSearch = debounce(() => {
    fetchPage(1);
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

onMounted(() => {
    fetchPage(1);
});

function selectRow(inv) {
    emit('select', inv);
}
</script>

<template>
    <div class="w-full max-w-lg mx-auto">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 mb-4">
                <span class="material-icons text-[28px]">receipt_long</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Choose an invoice
            </h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Only invoices that are <span class="text-gray-700 dark:text-gray-300">Sent</span>,
                <span class="text-gray-700 dark:text-gray-300">Viewed</span>, or
                <span class="text-gray-700 dark:text-gray-300">Partially paid</span> appear here.
            </p>
        </div>

        <div class="relative mb-4">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <span class="material-icons text-gray-400 text-[20px]">search</span>
            </div>
            <input
                v-model="searchQuery"
                type="search"
                class="input-style pl-10 w-full"
                placeholder="Search by invoice #, customer, or id…"
                autocomplete="off"
            >
        </div>

        <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400 mb-3">{{ errorMessage }}</p>

        <div
            v-if="loading && rows.length === 0"
            class="flex justify-center py-16 text-gray-500 dark:text-gray-400 text-sm"
        >
            Loading invoices…
        </div>

        <ul
            v-else-if="rows.length > 0"
            class="rounded-xl border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-sm max-h-[min(28rem,50vh)] overflow-y-auto"
        >
            <li v-for="inv in rows" :key="inv.id">
                <button
                    type="button"
                    class="w-full text-left px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors flex flex-col gap-1"
                    @click="selectRow(inv)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ inv.display_name || `INV-${inv.sequence}` }}
                        </span>
                        <span
                            class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide"
                            :class="statusMeta(inv.status).bgClass || 'bg-gray-100 text-gray-700'"
                        >
                            {{ statusMeta(inv.status).name }}
                        </span>
                    </div>
                    <div v-if="inv.customer_name" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ inv.customer_name }}
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-600 dark:text-gray-300 mt-1">
                        <span>Due: <strong class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(inv.amount_due, inv.currency) }}</strong></span>
                        <span v-if="formatDue(inv.due_at)">Due date: {{ formatDue(inv.due_at) }}</span>
                    </div>
                </button>
            </li>
        </ul>

        <div
            v-else-if="!loading"
            class="text-center py-12 text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-200 dark:border-gray-600 rounded-xl"
        >
            No matching invoices. Try another search.
        </div>

        <div
            v-if="meta.last_page > 1"
            class="flex items-center justify-between mt-4 text-xs text-gray-500 dark:text-gray-400"
        >
            <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="px-2 py-1 rounded border border-gray-200 dark:border-gray-600 disabled:opacity-40"
                    :disabled="meta.current_page <= 1 || loading"
                    @click="fetchPage(meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="px-2 py-1 rounded border border-gray-200 dark:border-gray-600 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page || loading"
                    @click="fetchPage(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
