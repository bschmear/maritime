<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    locationSections: { type: Array, default: () => [] },
    enumOptions: { type: Object, default: () => ({}) },
    chartData: { type: Object, default: () => ({}) },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Service Yard' },
]);

const ticketChart = computed(() => props.chartData?.service_tickets_by_status ?? { labels: [], series: [], colors: [] });
const workOrderChart = computed(() => props.chartData?.work_orders_by_status ?? { labels: [], series: [], colors: [] });

const hasTicketChart = computed(() => ticketChart.value.series?.some((n) => Number(n) > 0));
const hasWorkOrderChart = computed(() => workOrderChart.value.series?.some((n) => Number(n) > 0));

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

const statusBadgeClass = (bgClass) => bgClass || 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
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

        <div
            v-if="hasTicketChart || hasWorkOrderChart"
            class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2"
        >
            <div
                v-if="hasTicketChart"
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Service tickets by status
                </h3>
                <ApexPieChart
                    :labels="ticketChart.labels"
                    :series="ticketChart.series"
                    :colors="ticketChart.colors"
                    :height="220"
                />
            </div>
            <div
                v-if="hasWorkOrderChart"
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Work orders by status
                </h3>
                <ApexPieChart
                    :labels="workOrderChart.labels"
                    :series="workOrderChart.series"
                    :colors="workOrderChart.colors"
                    :height="220"
                />
            </div>
        </div>

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
                        {{ section.standalone_work_orders.length }} standalone work order{{ section.standalone_work_orders.length === 1 ? '' : 's' }}
                    </p>
                </div>

                <div class="grid gap-6 p-5 lg:grid-cols-2">
                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Service tickets
                        </h4>
                        <ul v-if="section.service_tickets.length" class="space-y-3">
                            <li
                                v-for="ticket in section.service_tickets"
                                :key="ticket.id"
                                class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="route('servicetickets.show', ticket.id)"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ ticket.number }} — {{ ticket.title }}
                                    </Link>
                                    <span
                                        class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                        :class="statusBadgeClass(ticket.status_bg_class)"
                                    >
                                        {{ ticket.status }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="ticket.customer_name">{{ ticket.customer_name }}</span>
                                    <span v-if="ticket.asset_label">
                                        <span v-if="ticket.customer_name"> · </span>{{ ticket.asset_label }}
                                    </span>
                                </p>
                                <p class="text-[10px] text-gray-400">Updated {{ formatWhen(ticket.updated_at) }}</p>

                                <ul
                                    v-if="ticket.work_orders?.length"
                                    class="mt-2 space-y-1.5 border-l-2 border-primary-200 pl-3 dark:border-primary-800"
                                >
                                    <li
                                        v-for="wo in ticket.work_orders"
                                        :key="wo.id"
                                        class="text-sm"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Link
                                                :href="route('workorders.show', wo.id)"
                                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                WO-{{ wo.number }} — {{ wo.title }}
                                            </Link>
                                            <span
                                                class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium"
                                                :class="statusBadgeClass(wo.status_bg_class)"
                                            >
                                                {{ wo.status }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="wo.technician_name || wo.scheduled_start_at"
                                            class="text-[10px] text-gray-400"
                                        >
                                            <span v-if="wo.technician_name">{{ wo.technician_name }}</span>
                                            <span v-if="wo.scheduled_start_at">
                                                <span v-if="wo.technician_name"> · </span>
                                                {{ formatWhen(wo.scheduled_start_at) }}
                                            </span>
                                        </p>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400">None</p>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Standalone work orders
                        </h4>
                        <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">
                            Not on an open service ticket — includes blocked work orders whose ticket is completed or closed.
                        </p>
                        <ul v-if="section.standalone_work_orders.length" class="space-y-2">
                            <li
                                v-for="wo in section.standalone_work_orders"
                                :key="wo.id"
                                class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/30"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="route('workorders.show', wo.id)"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        WO-{{ wo.number }} — {{ wo.title }}
                                    </Link>
                                    <span
                                        class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                        :class="statusBadgeClass(wo.status_bg_class)"
                                    >
                                        {{ wo.status }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="wo.customer_name">{{ wo.customer_name }}</span>
                                    <span v-if="wo.technician_name">
                                        <span v-if="wo.customer_name"> · </span>{{ wo.technician_name }}
                                    </span>
                                </p>
                                <p class="text-[10px] text-gray-400">Updated {{ formatWhen(wo.updated_at) }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400">None</p>
                    </div>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
