<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import ApexCharts from 'apexcharts';
import { getColorClasses, getPrimaryColor, getSecondaryColor, getTertiaryColor } from '@/Utils/colorHelpers';

const props = defineProps({
    recordsSections: {
        type: Array,
        required: true,
    },
});

const getTotalSalesChartOptions = () => {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return {
        colors: [
            getPrimaryColor(),
            getSecondaryColor(),
            getTertiaryColor()
        ],
        series: [
            {
                name: "Boats",
                color: getPrimaryColor(),
                data: [
                    { x: "Mon", y: 631 },
                    { x: "Tue", y: 600 },
                    { x: "Wed", y: 540 },
                    { x: "Thu", y: 580 },
                    { x: "Fri", y: 490 },
                    { x: "Sat", y: 580 },
                    { x: "Sun", y: 620 },
                ],
            },
            {
                name: "Trailors",
                color: getSecondaryColor(),
                data: [
                    { x: "Mon", y: 460 },
                    { x: "Tue", y: 490 },
                    { x: "Wed", y: 390 },
                    { x: "Thu", y: 620 },
                    { x: "Fri", y: 410 },
                    { x: "Sat", y: 640 },
                    { x: "Sun", y: 360 },
                ],
            },
            {
                name: "Other",
                color: getTertiaryColor(),
                data: [
                    { x: "Mon", y: 232 },
                    { x: "Tue", y: 630 },
                    { x: "Wed", y: 341 },
                    { x: "Thu", y: 224 },
                    { x: "Fri", y: 522 },
                    { x: "Sat", y: 411 },
                    { x: "Sun", y: 243 },
                ],
            },
        ],
        chart: {
            type: "bar",
            height: "520px",
            fontFamily: "Inter, sans-serif",
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: "70%",
                borderRadiusApplication: "end",
                borderRadius: 8,
            },
        },
        tooltip: {
            shared: true,
            intersect: false,
            style: {
                fontFamily: "Inter, sans-serif",
            },
        },
        states: {
            hover: {
                filter: {
                    type: "darken",
                    value: 1,
                },
            },
        },
        stroke: {
            show: true,
            width: 0,
            colors: ["transparent"],
        },
        grid: {
            show: false,
            strokeDashArray: 4,
            padding: {
                left: 2,
                right: 2,
                top: -14,
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: true,
            position: "bottom",
            fontFamily: "Inter, sans-serif",
            offsetY: 20,
            height: 40,
            markers: {
                radius: 99,
            },
            labels: {
                colors: textColor,
            },
        },
        xaxis: {
            floating: false,
            labels: {
                show: true,
                style: {
                    fontFamily: "Inter, sans-serif",
                    colors: textColor,
                },
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        yaxis: {
            show: false,
        },
        fill: {
            opacity: 1,
        },
    };
};

let chart = null;

onMounted(() => {
    if (document.getElementById("total-sales-chart")) {
        chart = new ApexCharts(
            document.querySelector("#total-sales-chart"),
            getTotalSalesChartOptions()
        );
        chart.render();

        // Listen for dark mode changes
        const handleDarkMode = () => {
            if (chart) {
                chart.updateOptions(getTotalSalesChartOptions());
            }
        };

        document.addEventListener("rerender-charts", handleDarkMode);

        // Cleanup
        return () => {
            document.removeEventListener("rerender-charts", handleDarkMode);
            if (chart) {
                chart.destroy();
            }
        };
    }
});
</script>

<template>
    <Head title="Operations" />

    <TenantLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Operations
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage inventory, invoices, and financial transactions across your business
                    </p>
                </div>
            </div>
        </template>


        <div class="space-y-6">
            <!-- Account Sections Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="section in recordsSections"
                    :key="section.title"
                    :href="section.href"
                    :class="[
                        'group block rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-800',
                        getColorClasses(section.color).border
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div :class="[
                            'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg',
                            getColorClasses(section.color).bg
                        ]">
                            <svg
                                :class="['h-6 w-6', getColorClasses(section.color).icon]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    :d="section.icon"
                                />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ section.title }}
                                </h3>
                                <!-- Arrow icon -->
                                <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-transform group-hover:translate-x-1 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ section.description }}
                            </p>

                            <!-- Stats (if available) -->
                            <div v-if="section.stats" class="mt-3 flex items-center gap-1 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ section.stats.label }}:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ section.stats.value }}</span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
  <div class="my-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <path
          fill-rule="evenodd"
          d="M9 15a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm3.8-1.9c.3-.5.7-1 1.2-1.2a1 1 0 0 1 2 0c.5.1.8.4 1.1.7a1 1 0 1 1-1.4 1.4l-.4-.2h-.1a.4.4 0 0 0-.4 0l.4.3a3 3 0 0 1 1.5.9 2 2 0 0 1 .5 1.9c-.3.5-.7 1-1.2 1.2a1 1 0 0 1-2 0c-.4 0-.8-.3-1.2-.7a1 1 0 1 1 1.6-1.3l.3.2h.1a.4.4 0 0 0 .4 0 1 1 0 0 0-.4-.3 3 3 0 0 1-1.5-.9 2 2 0 0 1-.5-2Zm2 .6Zm.5 2.6ZM4 14c.6 0 1 .4 1 1v4a1 1 0 1 1-2 0v-4c0-.6.4-1 1-1Zm3-2c.6 0 1 .4 1 1v6a1 1 0 1 1-2 0v-6c0-.6.4-1 1-1Zm6.5-8c0-.6.4-1 1-1H18c.6 0 1 .4 1 1v3a1 1 0 1 1-2 0v-.8l-2.3 2a1 1 0 0 1-1.3.1l-2.9-2-3.9 3a1 1 0 1 1-1.2-1.6l4.5-3.5a1 1 0 0 1 1.2 0l2.8 2L15.3 5h-.8a1 1 0 0 1-1-1Z"
          clip-rule="evenodd"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Income</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$163.4k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 ">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400 sm:text-base">
          <svg class="h-5 w-5 text-green-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          7%
        </span>
        vs last month
      </p>
    </div>
     <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4.5V19c0 .6.4 1 1 1h15M7 10l4 4 4-4 5 5m0 0h-3.2m3.2 0v-3.2" />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Outcome</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$82.1k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400 sm:text-base">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          8.8%
        </span>
        vs last month
      </p>
    </div>
   <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <path
          stroke="currentColor"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M8 17.3a5 5 0 0 0 2.6 1.7c2.2.6 4.5-.5 5-2.3.4-2-1.3-4-3.6-4.5-2.3-.6-4-2.7-3.5-4.5.5-1.9 2.7-3 5-2.3 1 .2 1.8.8 2.5 1.6m-3.9 12v2m0-18v2.2"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">Total Profit</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">$54.3k</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-red-600 dark:text-red-500">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V5m0 14-4-4m4 4 4-4" />
          </svg>
          2.5%
        </span>
        vs last month
      </p>
    </div>
    <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800 md:p-6">
      <svg class="mb-2 h-6 w-6 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <path
          fill-rule="evenodd"
          d="M12 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm-1.5 8a4 4 0 0 0-4 4c0 1.1.9 2 2 2h7a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-3Zm6.8-3.1a5.5 5.5 0 0 0-2.8-6.3c.6-.4 1.3-.6 2-.6a3.5 3.5 0 0 1 .8 6.9Zm2.2 7.1h.5a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-1.1l-.5.8c1.9 1 3.1 3 3.1 5.2ZM4 7.5a3.5 3.5 0 0 1 5.5-2.9A5.5 5.5 0 0 0 6.7 11 3.5 3.5 0 0 1 4 7.5ZM7.1 12H6a4 4 0 0 0-4 4c0 1.1.9 2 2 2h.5a6 6 0 0 1 3-5.2l-.4-.8Z"
          clip-rule="evenodd"
        />
      </svg>
      <h3 class="text-gray-500 dark:text-gray-400">New Customers</h3>
      <span class="text-2xl font-bold text-gray-900 dark:text-white">68</span>
      <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:text-base">
        <span class="mr-1.5 flex items-center text-sm font-medium text-green-500 dark:text-green-400">
          <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v13m0-13 4 4m-4-4-4 4" />
          </svg>
          5.6%
        </span>
        vs last month
      </p>
    </div>
  </div>



  <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800 md:p-6">
    <div class="items-start justify-between sm:flex">
      <div class="mb-4 sm:mb-0">
        <h2 class="mb-1 text-2xl font-bold leading-none text-gray-900 dark:text-white">$401,857</h2>
        <p class="text-gray-500 dark:text-gray-400">Total revenue for Maritime</p>
      </div>
      <div>
        <div date-rangepicker datepicker-autohide class="flex items-center">
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
              <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M5 5c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1a2 2 0 0 1 2 2v1c0 .6-.4 1-1 1H4a1 1 0 0 1-1-1V7c0-1.1.9-2 2-2ZM3 19v-7c0-.6.4-1 1-1h16c.6 0 1 .4 1 1v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm6-6c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1ZM7 17a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <input
              name="start"
              type="text"
              class="block w-32 rounded-lg border border-gray-300 bg-gray-50 p-2 ps-9 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500  dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
              placeholder="Start date"
            />
          </div>
          <span class="mx-2 text-gray-500 dark:text-gray-400">to</span>
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
              <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path
                  fill-rule="evenodd"
                  d="M5 5c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1h1c.6 0 1-.4 1-1a1 1 0 1 1 2 0c0 .6.4 1 1 1a2 2 0 0 1 2 2v1c0 .6-.4 1-1 1H4a1 1 0 0 1-1-1V7c0-1.1.9-2 2-2ZM3 19v-7c0-.6.4-1 1-1h16c.6 0 1 .4 1 1v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm6-6c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1ZM7 17a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Zm6 0c0-.6-.4-1-1-1a1 1 0 1 0 0 2c.6 0 1-.4 1-1Zm2 0a1 1 0 1 1 2 0c0 .6-.4 1-1 1a1 1 0 0 1-1-1Z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <input
              name="end"
              type="text"
              class="block w-32 rounded-lg border border-gray-300 bg-gray-50 p-2 ps-9 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500  dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
              placeholder="End date"
            />
          </div>
        </div>
      </div>
    </div>
    <div id="total-sales-chart"></div>
  </div>

        </div>
    </TenantLayout>
</template>
