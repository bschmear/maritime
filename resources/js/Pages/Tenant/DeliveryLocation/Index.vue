<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    deliveryLocations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

let debounce;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('delivery-locations.index'), {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Delivery Locations' },
]);
</script>

<template>
    <Head title="Delivery Locations" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Delivery Locations</h2>
                    <Link
                        :href="route('delivery-locations.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md"
                    >
                        <span class="material-icons text-base">add</span>
                        New Location
                    </Link>
                </div>
            </div>
        </template>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, city…"
                    class="flex-1 min-w-[240px] rounded-md border-gray-300 text-sm"
                />
                <select v-model="status" class="rounded-md border-gray-300 text-sm">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">City / State</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Contact</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Active</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="loc in deliveryLocations.data" :key="loc.id">
                            <td class="px-4 py-2">
                                <Link :href="route('delivery-locations.show', loc.id)" class="text-primary-600 hover:underline text-sm font-medium">
                                    {{ loc.display_name ?? loc.name }}
                                </Link>
                                <div v-if="loc.address_line_1" class="text-xs text-gray-500">{{ loc.address_line_1 }}</div>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                <span v-if="loc.city">{{ loc.city }}</span><span v-if="loc.state">, {{ loc.state }}</span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                <div v-if="loc.contact_name">{{ loc.contact_name }}</div>
                                <div v-if="loc.contact_phone" class="text-xs text-gray-500">{{ loc.contact_phone }}</div>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <span :class="[
                                    'inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full',
                                    loc.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                                ]">{{ loc.active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Link :href="route('delivery-locations.edit', loc.id)" class="text-gray-500 hover:text-primary-600">
                                    <span class="material-icons text-base">edit</span>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!deliveryLocations.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No locations yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deliveryLocations.links && deliveryLocations.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-1">
                <Link
                    v-for="link in deliveryLocations.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1 text-sm rounded',
                        link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                        !link.url ? 'opacity-40 pointer-events-none' : ''
                    ]"
                />
            </div>
        </div>
    </TenantLayout>
</template>
