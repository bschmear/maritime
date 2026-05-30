<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'dashboard',
        title: 'Dealership Dashboard',
        description: 'Your morning briefing: cash collected this month, open receivables, pipeline value, tasks due today, and what needs attention across sales and service.',
    },
    {
        icon: 'assessment',
        title: 'Financial Reports',
        description: 'Profit & loss, cash flow, and sales tax reports built from real invoices and line items, with revenue split by boats, parts, and service.',
    },
    {
        icon: 'store',
        title: 'Subsidiary & Location Filters',
        description: 'Multi-store groups slice every report by subsidiary or location so you can compare stores without exporting spreadsheets.',
    },
];

const capabilities = [
    {
        icon: 'payments',
        title: 'Revenue at a Glance',
        description: 'Hero cards show month-to-date collections with month-over-month change, outstanding AR, past-due balances, and total open pipeline value from active opportunities.',
    },
    {
        icon: 'warning',
        title: 'Risk & Attention Panel',
        description: 'Surface overdue invoices, stalled deals, expiring estimates, stale service tickets, and overdue work orders before they become surprises.',
    },
    {
        icon: 'precision_manufacturing',
        title: 'Operations Snapshot',
        description: 'Open service ticket and work order counts plus deliveries scheduled this week, with links straight into each module.',
    },
    {
        icon: 'stacked_line_chart',
        title: 'Profit & Loss by Category',
        description: 'P&L breaks customer-billable revenue and cost into boat sales, parts, and service, including warranty and work-order context, not a single lump sum.',
    },
    {
        icon: 'receipt_long',
        title: 'Sales & Tax Reporting',
        description: 'Sales by customer and by item (summary or detail), plus sales tax liability and payable reports for reconciliation.',
    },
    {
        icon: 'forum',
        title: 'Communication Activity',
        description: 'Track calls, emails, and meetings with today and week summaries, overdue follow-ups, and team-wide views for managers.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Dashboard Aggregates Live Data',
        body: 'TenantDashboardDataService pulls tasks, follow-ups, deliveries, risk items, payment hero metrics, pipeline value, and recent activity in one request so owners start the day with current numbers.',
    },
    {
        step: '02',
        title: 'Payment Metrics with Period Context',
        body: 'PaymentDashboardMetricsService computes MTD collections, month-over-month percent change, outstanding and overdue AR, and period-scoped totals. The payments index supports MTD, last 30/90 days, quarters, and custom ranges.',
    },
    {
        step: '03',
        title: 'Risk Thresholds You Can Tune',
        body: 'Stalled opportunities, stale service tickets, and expiring estimates use configurable day thresholds (dashboard.stalled_opportunity_days, service_ticket_stale_days, expiring_estimate_days) so alerts match how your store actually works.',
    },
    {
        step: '04',
        title: 'Reports Filter by Store',
        body: 'ReportsController accepts subsidiary_id and location_id on P&L, cash flow, sales tax, and sales-by-customer or item queries. Invoice line items roll up with billable-to and warranty rules applied consistently.',
    },
    {
        step: '05',
        title: 'P&L from Invoice Line Items',
        body: 'Boat, parts, and service revenue and COGS come from posted invoice items with asset and inventory cost fallbacks. Service lines tied to work orders are tracked separately so shop performance is visible in financials.',
    },
];
</script>

<template>
    <Head title="Performance Tracking" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

            <!-- Hero -->
            <section class="relative overflow-hidden bg-gray-950 px-6 pb-12 pt-24 lg:pb-36 sm:px-12 lg:px-24">

                <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style="background-image: linear-gradient(to right, #60a5fa 1px, transparent 1px), linear-gradient(to bottom, #60a5fa 1px, transparent 1px); background-size: 48px 48px;">
                </div>

                <div class="pointer-events-none absolute -top-32 left-1/2 h-[500px] w-[700px] -translate-x-1/2 rounded-full bg-primary-500/10 blur-[120px]"></div>

                <div class="relative mx-auto max-w-7xl">
                    <Link
                        v-if="route().has('features')"
                        :href="route('features')"
                        class="mb-10 inline-flex items-center gap-1.5 text-sm font-medium text-gray-400 transition hover:text-white"
                    >
                        <span class="material-icons text-base leading-none">arrow_back</span>
                        All features
                    </Link>

                    <div class="mb-6 flex">
                        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-500/20 bg-primary-500/10 px-4 py-1.5">
                            <span class="material-icons text-sm leading-none text-primary-400">bar_chart</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Performance Tracking</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Know how you're doing<br>
                        <span class="text-primary-400">before the month ends.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Dashboards for daily pulse, reports for financial depth, and filters by location,
                        all fed from the same invoices, deals, and service data your team already enters.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">MTD</div>
                            <div class="mt-1 text-sm text-gray-400">Collections &amp; MoM</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">P&amp;L</div>
                            <div class="mt-1 text-sm text-gray-400">Boats, parts, service</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">×N</div>
                            <div class="mt-1 text-sm text-gray-400">Per location reports</div>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-0 left-0 right-0 leading-none">
                    <svg viewBox="0 0 1440 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="h-16 w-full">
                        <path
                            class="fill-gray-50 dark:fill-gray-900"
                            d="M0,32 C180,64 360,0 540,32 C720,64 900,0 1080,32 C1260,64 1350,16 1440,32 L1440,64 L0,64 Z"
                        />
                    </svg>
                </div>
            </section>

            <!-- Structure -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">How it's structured</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Pulse, depth, and store-level views
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Start with the dashboard for what needs attention today. Drill into reports
                            when you need P&amp;L, cash flow, or sales breakdowns, filtered to the right store.
                        </p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-3">
                        <div
                            v-for="(item, i) in hierarchy"
                            :key="item.title"
                            class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-gray-700/60 dark:bg-gray-800/80"
                        >
                            <div class="absolute right-5 top-5 select-none text-5xl font-black text-gray-100 dark:text-gray-700/60">
                                {{ String(i + 1).padStart(2, '0') }}
                            </div>
                            <div class="mb-5 inline-flex rounded-xl bg-primary-50 p-3 dark:bg-primary-900/30">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400">{{ item.icon }}</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                            <p class="mt-2 text-md leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Capabilities -->
            <section class="bg-secondary-50 px-6 py-24 dark:bg-secondary-950/25 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-secondary-700 dark:text-secondary-400">What it does</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            From daily ops to month-end numbers
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            See cash, pipeline, and risk on login. When leadership needs detail,
                            reports and communication stats are already in the same system.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="cap in capabilities"
                            :key="cap.title"
                            class="group rounded-2xl border border-secondary-200 bg-white p-6 shadow-sm transition hover:border-secondary-400 hover:shadow-md dark:border-secondary-800 dark:bg-gray-900 dark:hover:border-secondary-600"
                        >
                            <div class="mb-4 inline-flex rounded-lg bg-secondary-100 p-2.5 dark:bg-secondary-900/50">
                                <span class="material-icons text-xl leading-none text-secondary-600 dark:text-secondary-400">{{ cap.icon }}</span>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ cap.title }}</h3>
                            <p class="mt-2 text-md leading-relaxed text-gray-600 dark:text-gray-400">{{ cap.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Behind the scenes -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">Under the hood</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            One data model, multiple lenses
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Performance views are not a separate data warehouse. They read the same
                            payments, invoices, opportunities, and service records your team works in every day.
                        </p>
                    </div>

                    <div class="relative">
                        <div class="absolute left-[2.75rem] top-12 hidden h-[calc(100%-6rem)] w-px bg-gradient-to-b from-primary-500/40 via-primary-500/20 to-transparent lg:block"></div>

                        <ol class="space-y-4">
                            <li
                                v-for="item in behindTheScenes"
                                :key="item.step"
                                class="flex gap-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700/60 dark:bg-gray-800/80"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold tracking-wide text-white">
                                    {{ item.step }}
                                </div>
                                <div class="pt-1">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                                    <p class="mt-1.5 text-md leading-relaxed text-gray-500 dark:text-gray-400">{{ item.body }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <FeaturePageCta
                badge="Built for dealership leadership"
                badge-icon="bar_chart"
                title="Stop guessing. Start measuring."
                description="Helmful gives owners and GMs a clear picture of cash, pipeline, and store performance without exporting to another tool."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
