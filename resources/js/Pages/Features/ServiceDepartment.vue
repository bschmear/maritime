<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'confirmation_number',
        title: 'Service Ticket',
        description: 'The customer-facing job: complaint, estimates for labor and parts, approval status, and signatures. Auto-numbered (ST-1000+) and tied to the boat, customer, and location.',
    },
    {
        icon: 'assignment',
        title: 'Work Order',
        description: 'Shop-floor execution with scheduled start and end times, assigned technicians, billable flags, and warranty tracking. One ticket can spawn multiple work orders.',
    },
    {
        icon: 'handyman',
        title: 'Service Items',
        description: 'Your catalog of labor and parts applied to tickets and work orders. Line totals roll up into estimated labor hours, parts dollars, tax, and ticket total automatically.',
    },
];

const capabilities = [
    {
        icon: 'calculate',
        title: 'Live Estimate Rollups',
        description: 'Add hourly labor and flat-rate parts to a ticket and Helmful recalculates subtotal, tax, and total from the line items. Revisions capture changes when scope shifts mid-job.',
    },
    {
        icon: 'thumb_up',
        title: 'Customer Approval Links',
        description: 'Send a secure review URL so customers approve or decline estimates from any device. Supports digital or paper signatures with a full audit trail.',
    },
    {
        icon: 'calendar_month',
        title: 'Service Yard Scheduling',
        description: 'Drag work orders onto a visual schedule by technician and bay. See deliveries and service work together so the yard stays coordinated.',
    },
    {
        icon: 'link',
        title: 'Tickets and Work Orders Stay Linked',
        description: 'Work orders inherit context from their parent ticket including the asset unit on the lift. Completing a ticket can automatically close open work orders tied to it.',
    },
    {
        icon: 'verified_user',
        title: 'Warranty-Aware Line Items',
        description: 'Flag warranty coverage on service items and work order lines. Warranty claims pull from completed work so manufacturer submissions stay accurate.',
    },
    {
        icon: 'groups',
        title: 'Customer Portal Visibility',
        description: 'Customers see their service tickets in the portal with status, totals, and printable review links. No more phone tag for estimate approvals.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Ticket Created with Context',
        body: 'A service ticket links customer, asset unit, subsidiary, and location. On create, Helmful assigns the next ST- number and starts in draft or open status depending on your workflow.',
    },
    {
        step: '02',
        title: 'Line Items Drive the Estimate',
        body: 'ServiceTicketServiceItem rows distinguish hourly labor from parts. recalculateEstimates() sums labor hours, labor dollars, parts dollars, applies the ticket tax rate, and persists estimated totals on the ticket.',
    },
    {
        step: '03',
        title: 'Approval Sent to the Customer',
        body: 'Staff trigger an approval request with a UUID-based public review page. The customer approves or declines; signatures and timestamps are stored on the ticket with optional reauthorization when estimates change.',
    },
    {
        step: '04',
        title: 'Work Orders Scheduled in the Yard',
        body: 'Technicians get work orders with scheduled windows, priority, and billable or warranty flags. The scheduling grid updates assignments and times without leaving the operations view.',
    },
    {
        step: '05',
        title: 'Completion Syncs the Shop Floor',
        body: 'When a service ticket is marked complete, SyncServiceTicketCompletionToWorkOrders moves any open work orders to completed so the schedule and ticket status stay aligned.',
    },
];
</script>

<template>
    <Head title="Service Department" />

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
                            <span class="material-icons text-sm leading-none text-primary-400">build</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Service Department</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Run your service bay<br>
                        <span class="text-primary-400">with the same system as sales.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Service tickets, work orders, scheduling, and customer approvals, all tied to the boat
                        on the lift and the customer waiting for a call back.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">ST-</div>
                            <div class="mt-1 text-sm text-gray-400">Auto-numbered tickets</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">WO</div>
                            <div class="mt-1 text-sm text-gray-400">Shop work orders</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">✓</div>
                            <div class="mt-1 text-sm text-gray-400">Customer sign-off</div>
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
                            Tickets, work orders, and line items
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Helmful mirrors how service departments actually operate: estimate and approve first,
                            then schedule and complete the work, with everything tied to the asset on the lift.
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
                            From estimate to completed work
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Every phase of service, from quote through approval, scheduling, and completion, in one connected workflow.
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
                            What happens from ticket open to bay complete
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            The customer-facing flow stays simple. Behind the scenes, estimates, approvals,
                            scheduling, and completion stay in sync across tickets and work orders.
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
                badge="Built for marine service"
                badge-icon="build"
                title="Your service department deserves better than whiteboards"
                description="Helmful connects service to inventory, customers, and warranty, so nothing falls through the cracks between the front desk and the bay."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
