<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    locationSections: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Service Yard' },
]);

const formatWhen = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
};
</script>

<template>
    <Head title="Service Yard" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Service Yard</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Open service tickets and work orders by location.
                        </p>
                    </div>
                    <Link
                        :href="route('serviceyard.scheduling')"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <span class="material-icons text-lg" aria-hidden="true">calendar_view_week</span>
                        Scheduler
                    </Link>
                </div>
            </div>
        </template>

        <div v-if="!locationSections.length" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-600 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">No open service tickets or work orders right now.</p>
        </div>

        <div v-else class="space-y-8">
            <section
                v-for="section in locationSections"
                :key="section.location.id ?? 'unassigned'"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ section.location.display_name }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ section.service_tickets.length }} ticket{{ section.service_tickets.length === 1 ? '' : 's' }}
                        ·
                        {{ section.work_orders.length }} work order{{ section.work_orders.length === 1 ? '' : 's' }}
                    </p>
                </div>

                <div class="grid gap-6 p-5 lg:grid-cols-2">
                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Service tickets
                        </h4>
                        <ul v-if="section.service_tickets.length" class="space-y-2">
                            <li
                                v-for="ticket in section.service_tickets"
                                :key="ticket.id"
                                class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
                            >
                                <Link
                                    :href="route('servicetickets.show', ticket.id)"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    {{ ticket.number }} — {{ ticket.title }}
                                </Link>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ ticket.status }}
                                    <span v-if="ticket.customer_name"> · {{ ticket.customer_name }}</span>
                                    <span v-if="ticket.asset_label"> · {{ ticket.asset_label }}</span>
                                </p>
                                <p class="text-[10px] text-gray-400">Updated {{ formatWhen(ticket.updated_at) }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400">None</p>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Work orders
                        </h4>
                        <ul v-if="section.work_orders.length" class="space-y-2">
                            <li
                                v-for="wo in section.work_orders"
                                :key="wo.id"
                                class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
                            >
                                <Link
                                    :href="route('workorders.show', wo.id)"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    WO-{{ wo.number }} — {{ wo.title }}
                                </Link>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ wo.status }}
                                    <span v-if="wo.customer_name"> · {{ wo.customer_name }}</span>
                                    <span v-if="wo.technician_name"> · {{ wo.technician_name }}</span>
                                </p>
                                <p class="text-[10px] text-gray-400">
                                    <span v-if="wo.scheduled_start_at">Scheduled {{ formatWhen(wo.scheduled_start_at) }}</span>
                                    <span v-else>Updated {{ formatWhen(wo.updated_at) }}</span>
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400">None</p>
                    </div>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
