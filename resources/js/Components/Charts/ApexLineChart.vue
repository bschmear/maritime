<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import ApexCharts from 'apexcharts';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    /** Apex-style series: [{ name: string, data: number[] }] */
    series: {
        type: Array,
        default: () => [],
    },
    colors: {
        type: Array,
        default: () => ['#16a34a', '#dc2626', '#2563eb'],
    },
    height: {
        type: Number,
        default: 320,
    },
});

const chartEl = ref(null);
let chart = null;

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
};

const isDark = () => document.documentElement.classList.contains('dark');

const hasData = computed(() => (props.categories?.length ?? 0) > 0);

const apexSeries = computed(() =>
    (props.series || []).map((s) => ({
        name: s.name ?? 'Series',
        data: (s.data || []).map((v) => Number(v) || 0),
    }))
);

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: props.height,
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
        animations: { enabled: true, speed: 250 },
        fontFamily: 'inherit',
    },
    stroke: { width: 2, curve: 'smooth' },
    colors: props.colors.length ? [...props.colors] : undefined,
    dataLabels: { enabled: false },
    xaxis: {
        categories: [...(props.categories || [])],
        labels: {
            rotate: (props.categories?.length ?? 0) > 14 ? -45 : 0,
            rotateAlways: (props.categories?.length ?? 0) > 14,
        },
    },
    yaxis: {
        labels: {
            formatter: (val) => formatMoney(val),
        },
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        fontWeight: 500,
    },
    tooltip: {
        shared: true,
        intersect: false,
        theme: isDark() ? 'dark' : 'light',
        y: {
            formatter: (val) => formatMoney(val),
        },
    },
    markers: {
        size: 0,
        hover: { size: 5 },
    },
    grid: {
        borderColor: 'rgba(128, 128, 128, 0.15)',
        strokeDashArray: 4,
    },
}));

const buildFullOptions = () => ({
    ...chartOptions.value,
    series: apexSeries.value,
});

const renderChart = async () => {
    await nextTick();
    if (!chartEl.value || !hasData.value) {
        chart?.destroy();
        chart = null;
        return;
    }

    const full = buildFullOptions();

    if (chart) {
        chart.updateOptions(
            {
                ...chartOptions.value,
                series: apexSeries.value,
            },
            true,
            true
        );
        await chart.updateSeries(apexSeries.value, true);
        return;
    }

    chart = new ApexCharts(chartEl.value, full);
    await chart.render();
};

const destroyChart = () => {
    chart?.destroy();
    chart = null;
};

const handleThemeChange = () => {
    if (chart) {
        chart.updateOptions({
            tooltip: { theme: isDark() ? 'dark' : 'light' },
        });
    }
};

watch(
    () => [props.categories, props.series, props.colors, props.height],
    () => {
        renderChart();
    },
    { deep: true }
);

onMounted(() => {
    renderChart();
    document.addEventListener('rerender-charts', handleThemeChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('rerender-charts', handleThemeChange);
    destroyChart();
});
</script>

<template>
    <div class="w-full">
        <div v-show="hasData" ref="chartEl" class="w-full" />
        <div
            v-if="!hasData"
            class="flex items-center justify-center rounded-lg border border-dashed border-gray-200 py-16 text-sm text-gray-400 dark:border-gray-700 dark:text-gray-500"
            :style="{ minHeight: `${height}px` }"
        >
            No data in this date range
        </div>
    </div>
</template>
