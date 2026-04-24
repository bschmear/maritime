<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    logs: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    filteredFleet: { type: Object, default: null },
});

const searchQuery = ref(props.filters?.search ?? '');

watch(
    () => props.filters,
    (f) => {
        searchQuery.value = f?.search ?? '';
    },
    { deep: true },
);

const applySearch = () => {
    const params = {};
    if (searchQuery.value?.trim()) params.search = searchQuery.value.trim();
    if (props.filters?.fleet_id) params.fleet_id = props.filters.fleet_id;
    router.get(route('fleet.maintenance.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFleetFilter = () => {
    const params = {};
    if (searchQuery.value?.trim()) params.search = searchQuery.value.trim();
    router.get(route('fleet.maintenance.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

let debounce;
watch(searchQuery, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applySearch(), 350);
});

const formatDate = (v) => {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatMoney = (n) => {
    if (n == null || n === '') return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(n));
};

const rows = computed(() => props.logs?.data ?? []);
const unitColumn = computed(() => !props.filteredFleet);

const subtitle = computed(() => {
    if (props.filteredFleet) {
        return `Service records for ${props.filteredFleet.display_name}. Clear the filter to see all units.`;
    }
    return 'All service records across your fleet. Open a unit to add entries or view history for that vehicle only.';
});

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home', href: route('dashboard') },
        { label: 'Fleet', href: route('fleet.index') },
    ];
    if (props.filteredFleet) {
        items.push({
            label: props.filteredFleet.display_name,
            href: route('fleet.show', props.filteredFleet.id),
        });
    }
    items.push({ label: 'Maintenance reports' });
    return items;
});
</script>

<template>
    <Head title="Fleet maintenance" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Maintenance reports
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>
        </template>

        <div class="space-y-4 p-4">
            <div
                v-if="filteredFleet"
                class="flex flex-col gap-3 rounded-lg border border-primary-200 bg-primary-50/80 px-4 py-3 dark:border-primary-800 dark:bg-primary-900/20 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                        Filtered to one unit
                    </p>
                    <p class="mt-0.5 truncate text-md font-medium text-gray-900 dark:text-white">
                        {{ filteredFleet.display_name }}
                        <span v-if="filteredFleet.license_plate" class="font-mono text-gray-600 dark:text-gray-400">
                            · {{ filteredFleet.license_plate }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-shrink-0 flex-wrap gap-2">
                    <Link
                        :href="route('fleet.show', filteredFleet.id)"
                        class="inline-flex items-center gap-1 rounded-lg border border-primary-300 bg-white px-3 py-1.5 text-sm font-medium text-primary-800 hover:bg-primary-50 dark:border-primary-700 dark:bg-gray-800 dark:text-primary-200 dark:hover:bg-gray-700"
                    >
                        <span class="material-icons text-md">open_in_new</span>
                        Fleet record
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="clearFleetFilter"
                    >
                        <span class="material-icons text-md">filter_alt_off</span>
                        Show all units
                    </button>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative max-w-md flex-1">
                        <span class="material-icons pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-gray-400">
                            search
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="filteredFleet ? 'Search type, notes, record #…' : 'Search by unit, plate, type, notes…'"
                            class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-3 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Date</th>
                                <th v-if="unitColumn" class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Unit
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Cost</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Mileage</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="log in rows" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="whitespace-nowrap px-4 py-3 text-md text-gray-900 dark:text-gray-100">
                                    {{ formatDate(log.performed_at) }}
                                </td>
                                <td v-if="unitColumn" class="px-4 py-3 text-md">
                                    <Link
                                        v-if="log.fleet"
                                        :href="route('fleet.show', log.fleet.id)"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ log.fleet.display_name }}
                                    </Link>
                                    <span v-else class="text-gray-400">—</span>
                                    <div v-if="log.fleet?.license_plate" class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ log.fleet.license_plate }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-md text-gray-800 dark:text-gray-200">
                                    {{
                                        (log.maintenance_types || []).map((t) => t.display_name).join(', ') || '—'
                                    }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-md text-gray-700 dark:text-gray-300">
                                    {{ formatMoney(log.cost) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-md text-gray-600 dark:text-gray-400">
                                    {{ log.mileage != null ? log.mileage : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-md">
                                    <Link
                                        :href="route('fleet.maintenance.show', log.id)"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="unitColumn ? 6 : 5" class="px-4 py-12 text-center text-md text-gray-500 dark:text-gray-400">
                                    No maintenance records yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="rows.length && logs?.total > 0"
                    class="flex flex-col items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-md text-gray-600 dark:border-gray-700 dark:text-gray-400 sm:flex-row"
                >
                    <div>
                        Showing {{ (logs.current_page - 1) * logs.per_page + 1 }}–{{
                            Math.min(logs.current_page * logs.per_page, logs.total)
                        }}
                        of {{ logs.total }}
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="logs.prev_page_url"
                            :href="logs.prev_page_url"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="logs.next_page_url"
                            :href="logs.next_page_url"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
