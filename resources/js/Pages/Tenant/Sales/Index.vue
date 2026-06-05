<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ApexPieChart from '@/Components/Charts/ApexPieChart.vue';
import ApexLineChart from '@/Components/Charts/ApexLineChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { getColorClasses } from '@/Utils/colorHelpers';

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    charts: {
        type: Object,
        default: () => ({}),
    },
    quickLinks: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodLabel: {
        type: String,
        default: '',
    },
    salespeople: {
        type: Array,
        default: () => [],
    },
    locations: {
        type: Array,
        default: () => [],
    },
});

const period = ref(props.filters?.period ?? 'month');
const year = ref(Number(props.filters?.year) || new Date().getFullYear());
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const salespersonId = ref(
    props.filters?.salesperson_id != null && props.filters?.salesperson_id !== ''
        ? String(props.filters.salesperson_id)
        : ''
);
const locationId = ref(
    props.filters?.location_id != null && props.filters?.location_id !== ''
        ? String(props.filters.location_id)
        : ''
);

watch(
    () => props.filters,
    (f) => {
        period.value = f?.period ?? 'month';
        year.value = Number(f?.year) || new Date().getFullYear();
        dateFrom.value = f?.date_from ?? '';
        dateTo.value = f?.date_to ?? '';
        salespersonId.value =
            f?.salesperson_id != null && f?.salesperson_id !== '' ? String(f.salesperson_id) : '';
        locationId.value =
            f?.location_id != null && f?.location_id !== '' ? String(f.location_id) : '';
    },
    { deep: true }
);

const periodOptions = [
    { value: 'week', label: 'This week' },
    { value: 'month', label: 'This month' },
    { value: 'quarter', label: 'This quarter' },
    { value: 'year', label: 'This year' },
    { value: 'custom', label: 'Custom range' },
];

const showYearInput = computed(() => ['quarter', 'year'].includes(period.value));
const showCustomDates = computed(() => period.value === 'custom');

const displayPeriodLabel = computed(() => props.periodLabel || 'This month');

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Sales' },
]);

const opportunityChart = computed(() => props.charts?.opportunities_by_status ?? { labels: [], series: [], colors: [] });
const estimateChart = computed(() => props.charts?.estimates_by_status ?? { labels: [], series: [], colors: [] });
const leadsChart = computed(() => props.charts?.leads_overview ?? { labels: [], series: [], colors: [] });
const activityTrend = computed(() => props.charts?.activity_trend ?? { categories: [], series: [], colors: [] });

const hasOpportunityChart = computed(() => opportunityChart.value.series?.some((n) => Number(n) > 0));
const hasEstimateChart = computed(() => estimateChart.value.series?.some((n) => Number(n) > 0));
const hasLeadsChart = computed(() => leadsChart.value.series?.some((n) => Number(n) > 0));
const hasActivityTrend = computed(() => activityTrend.value.categories?.length > 0);

const statColorMap = {
    blue: 'blue',
    amber: 'yellow',
    indigo: 'indigo',
    purple: 'purple',
    green: 'green',
    emerald: 'green',
    primary: 'blue',
};

function formatNumber(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return n.toLocaleString();
}

function formatCurrency(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
}

function statColors(colorKey) {
    return getColorClasses(statColorMap[colorKey] ?? 'blue');
}

function linkCardClasses(colorKey) {
    if (colorKey === 'primary') {
        return 'hover:border-primary-400';
    }
    return getColorClasses(statColorMap[colorKey] ?? 'blue').border;
}

function applyFilters() {
    const query = {
        period: period.value,
    };

    if (showYearInput.value) {
        query.year = year.value;
    }

    if (showCustomDates.value) {
        if (dateFrom.value) {
            query.date_from = dateFrom.value;
        }
        if (dateTo.value) {
            query.date_to = dateTo.value;
        }
    }

    if (salespersonId.value) {
        query.salesperson_id = salespersonId.value;
    }

    if (locationId.value) {
        query.location_id = locationId.value;
    }

    router.get(route('sales.index'), query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    period.value = 'month';
    year.value = new Date().getFullYear();
    dateFrom.value = '';
    dateTo.value = '';
    salespersonId.value = '';
    locationId.value = '';
    router.get(route('sales.index'), { period: 'month' }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Sales" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
            <div class="flex flex-wrap items-start justify-between gap-4 mt-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Sales overview
                    </h2>
                    <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                        Snapshot of leads, pipeline, quotes, and deals from your current records.
                    </p>
                </div>
                <Link
                    :href="route('sales.flow')"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-4 py-2.5 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <span class="material-icons text-xl">account_tree</span>
                    Process map
                </Link>
            </div>
        </template>

        <div class="space-y-8">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[11rem]">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Period</label>
                        <select v-model="period" class="input-style w-full text-base">
                            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="showYearInput" class="w-28">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Year</label>
                        <input
                            v-model.number="year"
                            type="number"
                            min="2000"
                            max="2100"
                            class="input-style w-full text-base"
                        >
                    </div>
                    <template v-if="showCustomDates">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">From</label>
                            <input v-model="dateFrom" type="date" class="input-style text-base">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">To</label>
                            <input v-model="dateTo" type="date" class="input-style text-base">
                        </div>
                    </template>
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Salesperson</label>
                        <select v-model="salespersonId" class="input-style w-full text-base">
                            <option value="">All salespeople</option>
                            <option v-for="person in salespeople" :key="person.id" :value="String(person.id)">
                                {{ person.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">Location</label>
                        <select v-model="locationId" class="input-style w-full text-base">
                            <option value="">All locations</option>
                            <option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">
                                {{ loc.label }}
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
                    Showing metrics for
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ displayPeriodLabel }}</span>
                    <span v-if="salespersonId && salespeople.length">
                        ·
                        {{
                            salespeople.find((p) => String(p.id) === salespersonId)?.label ?? 'Selected salesperson'
                        }}
                    </span>
                    <span v-else> · all salespeople</span>
                    <span v-if="locationId && locations.length">
                        ·
                        {{ locations.find((l) => String(l.id) === locationId)?.label ?? 'Selected location' }}
                    </span>
                    <span v-else> · all locations</span>
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <component
                    :is="stat.href ? Link : 'div'"
                    v-for="stat in summary"
                    :key="stat.key"
                    :href="stat.href || undefined"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 dark:border-gray-700 dark:bg-gray-800"
                    :class="
                        stat.href
                            ? [
                                  'group cursor-pointer',
                                  'hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md',
                                  'hover:bg-primary-50/40 dark:hover:border-primary-500 dark:hover:bg-primary-950/25',
                                  'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
                              ]
                            : ''
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ stat.label }}
                            </p>
                            <p class="mt-2 text-4xl font-bold tabular-nums text-gray-900 dark:text-white">
                                {{ formatNumber(stat.value) }}
                            </p>
                            <p
                                v-if="stat.subvalue != null && stat.subvalue_format === 'currency'"
                                class="mt-1 text-base font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ formatCurrency(stat.subvalue) }} pipeline value
                            </p>
                            <p v-else-if="stat.hint" class="mt-1 text-base text-gray-500 dark:text-gray-400">
                                {{ stat.hint }}
                            </p>
                            <p
                                v-if="stat.href"
                                class="mt-3 inline-flex items-center gap-0.5 text-sm font-semibold text-gray-400 transition-colors group-hover:text-primary-600 dark:text-gray-500 dark:group-hover:text-primary-400"
                            >
                                View all
                                <span
                                    class="material-icons text-base transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                >
                                    arrow_forward
                                </span>
                            </p>
                        </div>
                        <div
                            :class="[
                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition-transform duration-200',
                                statColors(stat.color).bg,
                                stat.href ? 'group-hover:scale-105' : '',
                            ]"
                        >
                            <span :class="['material-icons text-2xl', statColors(stat.color).icon]" aria-hidden="true">
                                {{ stat.icon }}
                            </span>
                        </div>
                    </div>
                </component>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-3">
                    <h3 class="text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Pipeline &amp; deal value
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        New opportunity value created vs. transaction totals opened — {{ displayPeriodLabel }}.
                    </p>
                    <div class="mt-4">
                        <ApexLineChart
                            v-if="hasActivityTrend"
                            :categories="activityTrend.categories"
                            :series="activityTrend.series"
                            :colors="activityTrend.colors"
                            :height="300"
                        />
                        <p v-else class="py-12 text-center text-base text-gray-400 dark:text-gray-500">
                            No sales activity in this period yet.
                        </p>
                    </div>
                </div>

                <div
                    v-if="hasOpportunityChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Opportunities by status
                    </h3>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="opportunityChart.labels"
                            :series="opportunityChart.series"
                            :colors="opportunityChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in opportunityChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: opportunityChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ opportunityChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-if="hasEstimateChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Estimates by status
                    </h3>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="estimateChart.labels"
                            :series="estimateChart.series"
                            :colors="estimateChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in estimateChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: estimateChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ estimateChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-if="hasLeadsChart"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Leads overview
                    </h3>
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <ApexPieChart
                            :labels="leadsChart.labels"
                            :series="leadsChart.series"
                            :colors="leadsChart.colors"
                            :height="200"
                        />
                        <ul class="space-y-2 text-base text-gray-600 dark:text-gray-300">
                            <li
                                v-for="(label, i) in leadsChart.labels"
                                :key="label"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: leadsChart.colors[i] }"
                                />
                                <span>{{ label }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ leadsChart.series[i] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mb-5 pb-5">
                <h3 class="mb-3 text-base font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Quick links
                </h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.title"
                        :href="link.href"
                        class="group block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                        :class="linkCardClasses(link.color)"
                    >
                        <span
                            class="material-icons text-3xl text-primary-600 dark:text-primary-400"
                            :class="link.color !== 'primary' ? statColors(statColorMap[link.color] ?? 'blue').icon : ''"
                        >
                            {{ link.icon }}
                        </span>
                        <h4 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ link.title }}
                        </h4>
                        <p class="mt-1 text-base text-gray-600 dark:text-gray-400">
                            {{ link.description }}
                        </p>
                    </Link>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
