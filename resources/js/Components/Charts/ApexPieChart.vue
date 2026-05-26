<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import ApexCharts from 'apexcharts';

const props = defineProps({
    series: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Array,
        default: () => [],
    },
    colors: {
        type: Array,
        default: () => [],
    },
    height: {
        type: Number,
        default: 112,
    },
    /** When true, tooltips format values as USD currency (useful on reports). */
    currencyTooltip: {
        type: Boolean,
        default: false,
    },
});

const chartEl = ref(null);
let chart = null;

const hasData = computed(() => props.series.some((value) => Number(value) > 0));

const isDark = () => document.documentElement.classList.contains('dark');

const formatCurrency = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
};

const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: props.height,
        width: props.height,
        sparkline: { enabled: false },
        animations: { enabled: true, speed: 300 },
        toolbar: { show: false },
        background: 'transparent',
    },
    series: props.series.map((v) => Number(v) || 0),
    labels: [...props.labels],
    colors: props.colors.length ? [...props.colors] : undefined,
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { width: 2, colors: ['transparent'] },
    tooltip: {
        enabled: true,
        theme: isDark() ? 'dark' : 'light',
        y: {
            formatter: (value) => (props.currencyTooltip ? formatCurrency(value) : `${value}`),
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
            },
        },
    },
    states: {
        hover: { filter: { type: 'lighten', value: 0.08 } },
        active: { filter: { type: 'none' } },
    },
}));

const renderChart = async () => {
    await nextTick();
    if (!chartEl.value || !hasData.value) {
        chart?.destroy();
        chart = null;
        return;
    }

    if (chart) {
        chart.updateOptions(chartOptions.value, true, true);
        chart.updateSeries(chartOptions.value.series);
        return;
    }

    chart = new ApexCharts(chartEl.value, chartOptions.value);
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
    () => [props.series, props.labels, props.colors, props.height, props.currencyTooltip],
    () => {
        if (!hasData.value) {
            destroyChart();
            return;
        }
        renderChart();
    },
    { deep: true },
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
    <div class="flex shrink-0 items-center justify-center" :style="{ width: `${height}px`, height: `${height}px` }">
        <div v-show="hasData" ref="chartEl" class="h-full w-full" />
        <div
            v-if="!hasData"
            class="flex h-full w-full items-center justify-center rounded-full border-2 border-dashed border-gray-200 dark:border-gray-700"
        >
            <span class="text-[10px] text-gray-400 dark:text-gray-500">No data</span>
        </div>
    </div>
</template>
