<script setup>
import MsoBatchModal from '@/Components/Tenant/MsoBatchModal.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tab: {
        type: String,
        default: 'pending',
    },
    transactions: {
        type: Object,
        default: null,
    },
    records: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const isPendingTab = computed(() => props.tab !== 'existing');

const showModal = ref(false);
const activeTransactionId = ref(null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Sales', href: route('sales.index') },
    { label: 'MSO' },
]);

const pendingRows = computed(() => props.transactions?.data ?? []);
const existingRows = computed(() => props.records?.data ?? []);

const pagination = computed(() => (isPendingTab.value ? props.transactions : props.records));

function switchTab(nextTab) {
    router.get(route('mso.index'), { tab: nextTab }, { preserveState: false, preserveScroll: true });
}

function openCreateMso(transactionId) {
    activeTransactionId.value = transactionId;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    activeTransactionId.value = null;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return value;
    }
}

function statusBadgeClass(status) {
    if (status === 'submitted') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }
    if (status === 'not_required') {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    }
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
}

function recordHref(record) {
    if (record.status === 'draft' && record.transaction_id && record.transaction_line_item_id) {
        return route('mso.create', {
            transaction_id: record.transaction_id,
            line_item_id: record.transaction_line_item_id,
        });
    }

    return route('mso.show', record.id);
}
</script>

<template>
    <Head title="MSO" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">MSO</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                            Manufacturer's Statement of Origin paperwork for completed deals and asset units.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-0 dark:border-gray-700">
                        <button
                            type="button"
                            class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="isPendingTab
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                            @click="switchTab('pending')"
                        >
                            Pending
                        </button>
                        <button
                            type="button"
                            class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors"
                            :class="!isPendingTab
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                            @click="switchTab('existing')"
                        >
                            Existing
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="flash.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ flash.error }}
        </div>

        <div v-if="isPendingTab" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Deal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Completed</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Units</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="!pendingRows.length">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No pending MSOs. Completed deals with asset units will appear here.
                        </td>
                    </tr>
                    <tr v-for="row in pendingRows" :key="row.id">
                        <td class="px-4 py-3 text-sm">
                            <Link
                                :href="route('transactions.show', row.id)"
                                class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                {{ row.display_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ row.customer_name || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(row.closed_at) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.asset_unit_lines_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                                @click="openCreateMso(row.id)"
                            >
                                Create MSO
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">MSO</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Deal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Asset unit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Submitted</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="!existingRows.length">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No MSO records yet. Create one from the Pending tab.
                        </td>
                    </tr>
                    <tr v-for="row in existingRows" :key="row.id">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ row.display_name }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="statusBadgeClass(row.status)"
                            >
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <Link
                                v-if="row.transaction_id"
                                :href="route('transactions.show', row.transaction_id)"
                                class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                {{ row.transaction_display_name || `Deal #${row.transaction_id}` }}
                            </Link>
                            <span v-else class="text-gray-500 dark:text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            {{ row.asset_unit_display_name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(row.submitted_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="recordHref(row)"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                View
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagination?.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in pagination.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="link.active
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200'"
                v-html="link.label"
            />
        </div>

        <MsoBatchModal
            :show="showModal"
            :transaction-id="activeTransactionId"
            @close="closeModal"
            @submitted="closeModal"
        />
    </TenantLayout>
</template>
