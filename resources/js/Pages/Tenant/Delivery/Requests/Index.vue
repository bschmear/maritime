<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    deliveries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    locationOptions: { type: Array, default: () => [] },
    canCreateDelivery: { type: Boolean, default: false },
    pendingCount: { type: Number, default: 0 },
});

const locationFilter = ref(props.filters?.location_id ? String(props.filters.location_id) : '');

watch(
    () => props.filters?.location_id,
    (v) => { locationFilter.value = v ? String(v) : ''; },
);

const applyFilters = () => {
    router.get(route('deliveries.requests.index'), {
        location_id: locationFilter.value || undefined,
    }, { preserveState: true });
};

const formatDateTime = (v) => {
    if (!v) return '—';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString();
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Delivery requests' },
]);
</script>

<template>
    <Head title="Delivery requests" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Delivery requests</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ pendingCount }} pending approval</p>
                    </div>
                    <Link
                        v-if="!canCreateDelivery"
                        :href="route('deliveries.requests.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-md font-medium text-white hover:bg-amber-700"
                    >
                        <span class="material-icons text-lg">add</span>
                        Create delivery request
                    </Link>
                    <Link
                        v-else
                        :href="route('deliveries.requests.create')"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-300 px-4 py-2 text-md font-medium text-amber-800 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-950/40"
                    >
                        <span class="material-icons text-lg">add</span>
                        New request
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-4">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Depart-from location</label>
                    <select v-model="locationFilter" class="input-style min-w-[220px]" @change="applyFilters">
                        <option value="">All locations</option>
                        <option v-for="loc in locationOptions" :key="loc.id" :value="String(loc.id)">{{ loc.display_name }}</option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Delivery</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Requested</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Scheduled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="d in deliveries.data" :key="d.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 text-sm">
                                <Link :href="route('deliveries.show', d.id)" class="font-medium text-primary-700 hover:underline dark:text-primary-300">
                                    {{ d.display_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ d.customer?.display_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ d.location?.display_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ d.requested_by?.display_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatDateTime(d.scheduled_at) }}</td>
                        </tr>
                        <tr v-if="!deliveries.data?.length">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No pending delivery requests.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
