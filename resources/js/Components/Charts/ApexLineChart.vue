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
let themeObserver = null;

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
};

const isDark = () => document.documentElement.classList.contains('dark');

/** Axis, grid, and legend colors readable in light and dark UI. */
function themeStyles() {
    if (isDark()) {
        return {
            foreColor: '#e5e7eb',
            labelColor: '#d1d5db',
            axisColor: '#9ca3af',
            gridColor: 'rgba(156, 163, 175, 0.28)',
            legendColor: '#f3f4f6',
        };
    }

    return {
        foreColor: '#374151',
        labelColor: '#6b7280',
        axisColor: '#9ca3af',
        gridColor: 'rgba(107, 114, 128, 0.22)',
        legendColor: '#374151',
    };
}

const hasData = computed(() => (props.categories?.length ?? 0) > 0);

const apexSeries = computed(() =>
    (props.series || []).map((s) => ({
        name: s.name ?? 'Series',
        data: (s.data || []).map((v) => Number(v) || 0),
    }))
);

function buildChartOptions() {
    const theme = themeStyles();
    const manyCategories = (props.categories?.length ?? 0) > 14;

    return {
        chart: {
            type: 'line',
            height: props.height,
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            foreColor: theme.foreColor,
            animations: { enabled: true, speed: 250 },
            fontFamily: 'inherit',
        },
        stroke: { width: 2.5, curve: 'smooth' },
        colors: props.colors.length ? [...props.colors] : undefined,
        dataLabels: { enabled: false },
        xaxis: {
            categories: [...(props.categories || [])],
            axisBorder: {
                show: true,
                color: theme.axisColor,
            },
            axisTicks: {
                show: true,
                color: theme.axisColor,
            },
            labels: {
                rotate: manyCategories ? -45 : 0,
                rotateAlways: manyCategories,
                style: {
                    colors: theme.labelColor,
                    fontSize: '14px',
                    fontWeight: 500,
                },
            },
        },
        yaxis: {
            labels: {
                style: {
                    colors: theme.labelColor,
                    fontSize: '14px',
                    fontWeight: 500,
                },
                formatter: (val) => formatMoney(val),
            },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '14px',
            fontWeight: 500,
            labels: {
                colors: theme.legendColor,
            },
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
            borderColor: theme.gridColor,
            strokeDashArray: 4,
            xaxis: {
                lines: { show: true },
            },
            yaxis: {
                lines: { show: true },
            },
        },
    };
}

const chartOptions = computed(() => buildChartOptions());

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
    if (!chart) {
        return;
    }
    chart.updateOptions(buildChartOptions(), false, true);
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
    themeObserver = new MutationObserver(handleThemeChange);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => {
    document.removeEventListener('rerender-charts', handleThemeChange);
    themeObserver?.disconnect();
    themeObserver = null;
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
