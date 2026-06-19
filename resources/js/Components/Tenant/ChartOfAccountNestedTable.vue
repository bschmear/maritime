<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    accountTree: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ search: '', account_type: null, active: null }),
    },
    accountTypes: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, roots: 0 }),
    },
    recordType: { type: String, default: 'chart-of-accounts' },
});

const searchQuery = ref(props.filters.search ?? '');
const accountTypeFilter = ref(props.filters.account_type ?? '');
const activeFilter = ref(props.filters.active ?? '');

const expandedIds = ref(new Set());

const hasActiveFilters = computed(() =>
    Boolean(
        (props.filters.search && props.filters.search.trim())
        || props.filters.account_type
        || props.filters.active !== null && props.filters.active !== '',
    ),
);

function collectExpandableIds(nodes, ids = new Set()) {
    for (const node of nodes) {
        if (node.has_children) {
            ids.add(node.id);
            collectExpandableIds(node.children ?? [], ids);
        }
    }

    return ids;
}

function defaultExpandedIds() {
    if (hasActiveFilters.value) {
        return collectExpandableIds(props.accountTree);
    }

    return new Set(
        props.accountTree.filter((node) => node.has_children).map((node) => node.id),
    );
}

watch(
    () => props.accountTree,
    () => {
        expandedIds.value = defaultExpandedIds();
    },
    { immediate: true, deep: true },
);

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters.search ?? '';
        accountTypeFilter.value = filters.account_type ?? '';
        activeFilter.value = filters.active ?? '';
    },
    { deep: true },
);

function flattenTree(nodes, depth = 0, rows = []) {
    for (const node of nodes) {
        rows.push({ ...node, depth });

        if (node.has_children && expandedIds.value.has(node.id)) {
            flattenTree(node.children ?? [], depth + 1, rows);
        }
    }

    return rows;
}

const visibleRows = computed(() => flattenTree(props.accountTree));

const visibleCount = computed(() => visibleRows.value.length);

function toggleExpanded(id) {
    const next = new Set(expandedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedIds.value = next;
}

function expandAll() {
    expandedIds.value = collectExpandableIds(props.accountTree);
}

function collapseAll() {
    expandedIds.value = new Set();
}

function applyFilters() {
    const params = {};

    const search = searchQuery.value?.trim();
    if (search) {
        params.search = search;
    }

    if (accountTypeFilter.value) {
        params.account_type = accountTypeFilter.value;
    }

    if (activeFilter.value !== '' && activeFilter.value !== null) {
        params.active = activeFilter.value;
    }

    router.get(route(`${props.recordType}.index`), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handleSearchSubmit(event) {
    event.preventDefault();
    applyFilters();
}

function clearSearch() {
    searchQuery.value = '';
    applyFilters();
}

function onAccountTypeChange(event) {
    accountTypeFilter.value = event.target.value;
    applyFilters();
}

function onActiveChange(event) {
    activeFilter.value = event.target.value;
    applyFilters();
}

function showHref(id) {
    try {
        return route(`${props.recordType}.show`, id);
    } catch {
        return null;
    }
}

function accountLabel(row) {
    return row.display_name || row.name || row.fully_qualified_name || `Account #${row.id}`;
}

function formatBoolean(value) {
    return value ? 'Yes' : 'No';
}
</script>

<template>
    <section class="flex w-full min-w-0 max-w-full grow flex-col space-y-4">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <div class="space-y-1 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <span class="inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    Total accounts
                </span>
                <h2 class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ stats.total }}</h2>
            </div>
            <div class="space-y-1 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                    Active
                </span>
                <h2 class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ stats.active }}</h2>
            </div>
            <div class="space-y-1 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <span class="inline-block rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-primary-800 dark:bg-primary-900/40 dark:text-primary-200">
                    Top-level
                </span>
                <h2 class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ stats.roots }}</h2>
            </div>
        </div>

        <div class="flex w-full min-w-0 max-w-full grow flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Account hierarchy</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Nested by parent account from QuickBooks
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="expandAll"
                    >
                        Expand all
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="collapseAll"
                    >
                        Collapse all
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-b border-gray-50 px-5 py-3 dark:border-gray-700/60 lg:flex-row lg:flex-wrap lg:items-center">
                <form class="w-full min-w-0 max-w-96 shrink-0" @submit="handleSearchSubmit">
                    <div class="relative w-full">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search accounts..."
                            class="block w-full min-w-0 rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-20 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden"
                            @input="(event) => { if (!event.target.value) clearSearch(); }"
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center">
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="mr-1 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                @click="clearSearch"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <button
                                type="submit"
                                class="h-full rounded-r-lg bg-primary-600 px-3 text-xs font-medium text-white transition-colors hover:bg-primary-700"
                            >
                                Search
                            </button>
                        </div>
                    </div>
                </form>

                <select
                    :value="accountTypeFilter"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @change="onAccountTypeChange"
                >
                    <option value="">All account types</option>
                    <option v-for="type in accountTypes" :key="type" :value="type">{{ type }}</option>
                </select>

                <select
                    :value="activeFilter"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @change="onActiveChange"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active only</option>
                    <option value="0">Inactive only</option>
                </select>
            </div>

            <div class="w-full min-w-0 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/60">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Account
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Full name
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Account type
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Detail type
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Active
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        <tr v-if="visibleRows.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                <span class="material-icons mb-2 block text-[36px] text-gray-300 dark:text-gray-600">account_tree</span>
                                No accounts match your filters.
                            </td>
                        </tr>
                        <tr
                            v-for="row in visibleRows"
                            :key="row.id"
                            class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-700/30"
                        >
                            <td class="px-5 py-3 text-sm">
                                <div class="flex min-w-[240px] items-center gap-1.5" :style="{ paddingLeft: `${row.depth * 1.25}rem` }">
                                    <button
                                        v-if="row.has_children"
                                        type="button"
                                        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                        :aria-label="expandedIds.has(row.id) ? 'Collapse' : 'Expand'"
                                        @click="toggleExpanded(row.id)"
                                    >
                                        <span
                                            class="material-icons text-[18px] leading-none transition-transform"
                                            :class="expandedIds.has(row.id) ? 'rotate-90' : ''"
                                        >
                                            chevron_right
                                        </span>
                                    </button>
                                    <span v-else class="inline-block h-6 w-6 shrink-0" aria-hidden="true" />

                                    <Link
                                        v-if="showHref(row.id)"
                                        :href="showHref(row.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                                    >
                                        {{ accountLabel(row) }}
                                    </Link>
                                    <span v-else class="font-medium text-gray-900 dark:text-white">{{ accountLabel(row) }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ row.fully_qualified_name || '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ row.account_type || '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ row.detail_type || '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="row.active
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ formatBoolean(row.active) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Showing {{ visibleCount }} visible row{{ visibleCount === 1 ? '' : 's' }}
            </div>
        </div>
    </section>
</template>
