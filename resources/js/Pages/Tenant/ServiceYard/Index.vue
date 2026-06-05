<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { getColorClasses } from '@/Utils/colorHelpers';

const props = defineProps({
    locationSections: { type: Array, default: () => [] },
    enumOptions: { type: Object, default: () => ({}) },
    chartData: { type: Object, default: () => ({}) },
    summary: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    locations: { type: Array, default: () => [] },
    technicians: { type: Array, default: () => [] },
});

const locationId = ref(
    props.filters?.location_id != null && props.filters?.location_id !== ''
        ? String(props.filters.location_id)
        : ''
);
const technicianId = ref(
    props.filters?.technician_id != null && props.filters?.technician_id !== ''
        ? String(props.filters.technician_id)
        : ''
);

watch(
    () => props.filters,
    (f) => {
        locationId.value =
            f?.location_id != null && f?.location_id !== '' ? String(f.location_id) : '';
        technicianId.value =
            f?.technician_id != null && f?.technician_id !== '' ? String(f.technician_id) : '';
    },
    { deep: true }
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Service Yard' },
]);

const ticketChart = computed(() => props.chartData?.service_tickets_by_status ?? { labels: [], series: [], colors: [] });
const workOrderChart = computed(() => props.chartData?.work_orders_by_status ?? { labels: [], series: [], colors: [] });
const hoursChart = computed(() => props.chartData?.work_orders_hours_variance ?? { labels: [], series: [], colors: [] });

const hasTicketChart = computed(() => ticketChart.value.series?.some((n) => Number(n) > 0));
const hasWorkOrderChart = computed(() => workOrderChart.value.series?.some((n) => Number(n) > 0));
const hasHoursChart = computed(() => hoursChart.value.series?.some((n) => Number(n) > 0));
const hasAnyChart = computed(() => hasTicketChart.value || hasWorkOrderChart.value || hasHoursChart.value);

const statColorMap = {
    blue: 'blue',
    indigo: 'indigo',
    red: 'red',
    green: 'green',
};

function applyFilters() {
    const query = {};
    if (locationId.value) {
        query.location_id = locationId.value;
    }
    if (technicianId.value) {
        query.technician_id = technicianId.value;
    }
    router.get(route('serviceyard.index'), query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    locationId.value = '';
    technicianId.value = '';
    router.get(route('serviceyard.index'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatWhen(iso) {
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
}

function formatHours(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return n.toLocaleString(undefined, { maximumFractionDigits: 1 });
}

function formatNumber(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return n.toLocaleString();
}

function statColors(colorKey) {
    return getColorClasses(statColorMap[colorKey] ?? 'blue');
}

function statusBadgeClass(bgClass) {
    return bgClass || 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
}

const hoursBadgeClasses = {
    under: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    on: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    over: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    awaiting_actual: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
};

function hoursBadgeClass(bucket) {
    return hoursBadgeClasses[bucket] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
}
</script>

<template>
    <Head title="Service Yard" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Service yard overview</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                            Open service tickets and work orders by location, with hours vs estimate at a glance.
                        </p>
                    </div>
                    <Link
                        :href="route('serviceyard.scheduling')"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-base font-medium text-white hover:bg-primary-700"
                    >
                        <span class="material-icons text-xl" aria-hidden="true">calendar_view_week</span>
                        Scheduler
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-8">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Location</label>
                        <select v-model="locationId" class="input-style w-full text-base">
                            <option value="">All locations</option>
                            <option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">
                                {{ loc.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Technician</label>
                        <select v-model="technicianId" class="input-style w-full text-base">
                            <option value="">All technicians</option>
                            <option v-for="tech in technicians" :key="tech.id" :value="String(tech.id)">
                                {{ tech.label }}
                            </option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2.5 text-base font-medium text-white hover:bg-primary-700"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Showing open yard work
                    <span v-if="locationId && locations.length">
                        · {{ locations.find((l) => String(l.id) === locationId)?.label ?? 'Selected location' }}
                    </span>
                    <span v-else> · all locations</span>
                    <span v-if="technicianId && technicians.length">
                        · {{ technicians.find((t) => String(t.id) === technicianId)?.label ?? 'Selected technician' }}
                    </span>
                    <span v-else> · all technicians</span>
                </p>
            </div>

            <div v-if="summary.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <component
                    :is="stat.href ? Link : 'div'"
                    v-for="stat in summary"
                    :key="stat.key"
                    :href="stat.href || undefined"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    :class="
                        stat.href
                            ? 'group transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md'
                            : ''
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ stat.label }}
                            </p>
                            <p class="mt-1 text-4xl font-bold tabular-nums text-gray-900 dark:text-white">
                                {{ formatNumber(stat.value) }}
                            </p>
                            <p v-if="stat.hint" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ stat.hint }}
                            </p>
                        </div>
                        <div
                            :class="[
                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg',
                                statColors(stat.color).bg,
                                stat.href ? 'transition-transform duration-200 group-hover:scale-105' : '',
                            ]"
                        >
                            <span
                                :class="['material-icons text-2xl', statColors(stat.color).icon]"
                                aria-hidden="true"
                            >
                                {{ stat.icon }}
                            </span>
                        </div>
                    </div>
                </component>
            </div>

            <div
                v-if="hasAnyChart"
                class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-if="hasTicketChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Service tickets by status
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Open and in progress</p>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="ticketChart.labels"
                            :series="ticketChart.series"
                            :colors="ticketChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in ticketChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: ticketChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ ticketChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div
                    v-if="hasWorkOrderChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Work orders by status
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Active yard work orders</p>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="workOrderChart.labels"
                            :series="workOrderChart.series"
                            :colors="workOrderChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in workOrderChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: workOrderChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ workOrderChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div
                    v-if="hasHoursChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2 xl:col-span-1"
                >
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Hours vs estimate
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Compare logged actual hours to estimated hours on open work orders
                    </p>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="hoursChart.labels"
                            :series="hoursChart.series"
                            :colors="hoursChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in hoursChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: hoursChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ hoursChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div v-if="!locationSections.length" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-600 dark:bg-gray-800">
                <p class="text-base text-gray-600 dark:text-gray-300">
                    No open service tickets or work orders match these filters.
                </p>
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
                                            class="inline-flex items-center rounded px-2 py-0.5 text-sm font-medium"
                                            :class="statusBadgeClass(ticket.status_bg_class)"
                                        >
                                            {{ ticket.status }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="ticket.customer_name">{{ ticket.customer_name }}</span>
                                        <span v-if="ticket.asset_label">
                                            <span v-if="ticket.customer_name"> · </span>{{ ticket.asset_label }}
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-400">Updated {{ formatWhen(ticket.updated_at) }}</p>

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
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 text-sm font-medium"
                                                    :class="statusBadgeClass(wo.status_bg_class)"
                                                >
                                                    {{ wo.status }}
                                                </span>
                                                <span
                                                    v-if="wo.hours_variance_label"
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 text-sm font-medium"
                                                    :class="hoursBadgeClass(wo.hours_variance)"
                                                >
                                                    {{ wo.hours_variance_label }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <span v-if="wo.estimated_hours != null">Est. {{ formatHours(wo.estimated_hours) }}h</span>
                                                <span v-if="wo.actual_hours != null">
                                                    <span v-if="wo.estimated_hours != null"> · </span>
                                                    Actual {{ formatHours(wo.actual_hours) }}h
                                                </span>
                                            </p>
                                            <p
                                                v-if="wo.technician_name || wo.scheduled_start_at"
                                                class="text-sm text-gray-400"
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
                            <p class="mb-2 text-sm text-gray-400 dark:text-gray-500">
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
                                            class="inline-flex items-center rounded px-2 py-0.5 text-sm font-medium"
                                            :class="statusBadgeClass(wo.status_bg_class)"
                                        >
                                            {{ wo.status }}
                                        </span>
                                        <span
                                            v-if="wo.hours_variance_label"
                                            class="inline-flex items-center rounded px-2 py-0.5 text-sm font-medium"
                                            :class="hoursBadgeClass(wo.hours_variance)"
                                        >
                                            {{ wo.hours_variance_label }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="wo.customer_name">{{ wo.customer_name }}</span>
                                        <span v-if="wo.technician_name">
                                            <span v-if="wo.customer_name"> · </span>{{ wo.technician_name }}
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="wo.estimated_hours != null">Est. {{ formatHours(wo.estimated_hours) }}h</span>
                                        <span v-if="wo.actual_hours != null">
                                            <span v-if="wo.estimated_hours != null"> · </span>
                                            Actual {{ formatHours(wo.actual_hours) }}h
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-400">Updated {{ formatWhen(wo.updated_at) }}</p>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-gray-400">None</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </TenantLayout>
</template>
