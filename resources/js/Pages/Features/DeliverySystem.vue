<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'local_shipping',
        title: 'Delivery',
        description: 'Each run ties a customer, scheduled time, destination address, assigned technician, and status from scheduled through en route to delivered or rescheduled.',
    },
    {
        icon: 'inventory',
        title: 'Delivery Items',
        description: 'Load multiple boats, engines, and trailers on one trip. Items sync from the related work order or sale transaction, and staff can mark each line delivered individually.',
    },
    {
        icon: 'airport_shuttle',
        title: 'Fleet & Departure',
        description: 'Assign delivery truck and trailer from your fleet, depart from a store location, and block double-booking when the same rig is already scheduled.',
    },
];

const capabilities = [
    {
        icon: 'calendar_view_week',
        title: 'Schedule & Day Board',
        description: 'Drag deliveries on the visual scheduler filtered by location. The technician day board shows who is going where on any calendar date in your account timezone.',
    },
    {
        icon: 'route',
        title: 'Drive-Time Planning',
        description: 'Google Maps estimates outbound and return drive time, time-to-leave, and estimated arrival so dispatch knows when the truck should roll and when the customer should expect you.',
    },
    {
        icon: 'sms',
        title: 'Customer Text Alerts',
        description: 'Optional SMS when the driver is en route (with a tracking link), when they arrive on site, and when a signature is requested, all gated by account delivery SMS settings.',
    },
    {
        icon: 'draw',
        title: 'Review & Signature',
        description: 'Customers sign on a public delivery review page or via email link. Recipient name, signature image, timestamp, and audit fields are stored on the delivery record.',
    },
    {
        icon: 'notifications_active',
        title: 'Team Notifications',
        description: 'When a delivery is signed, Helmful notifies the assigned technician or account owner in-app so the front office knows the handoff is complete without refreshing the list.',
    },
    {
        icon: 'checklist',
        title: 'Delivery Checklists',
        description: 'Apply checklist templates before the truck leaves: pre-trip inspections, rigging steps, and customer walkthrough items your team can reuse on every delivery.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Delivery Created from Context',
        body: 'CreateDelivery links customer, subsidiary, location, optional work order or transaction, and delivery items. Address can come from a saved delivery location, contact address, or a custom snapshot so the route does not break if contact data changes later.',
    },
    {
        step: '02',
        title: 'Travel Windows Calculated',
        body: 'ComputeDeliveryTravelEstimates calls Google Maps for depot-to-destination and return durations, then sets time_to_leave_by from scheduled_at minus drive time. The form can preview estimates before save via the travel-estimate endpoint.',
    },
    {
        step: '03',
        title: 'Fleet Conflicts Checked',
        body: 'DeliveryFleetConflictGuard and check-fleet-schedule prevent overlapping truck or trailer bookings. Staff can swap fleet assignments between two deliveries when schedules collide instead of guessing in a spreadsheet.',
    },
    {
        step: '04',
        title: 'En Route Updates the Customer',
        body: 'Marking en route sets status, syncs technician in-progress flags, refreshes estimated arrival, and optionally sends sendDeliveryEnRouteSms with a public review URL on your tenant domain.',
    },
    {
        step: '05',
        title: 'Arrival, Signature, and Close-Out',
        body: 'notifyArrived records customer_arrived_notified_at and can text that the driver is on site. Signature capture on the public review page triggers NotificationService::notifyDeliverySigned; individual delivery items can be marked delivered as each asset is handed off.',
    },
];
</script>

<template>
    <Head title="Delivery System" />

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
                            <span class="material-icons text-sm leading-none text-primary-400">local_shipping</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Delivery System</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Plan every delivery,<br>
                        <span class="text-primary-400">from dock to driveway.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Schedule technicians and fleet, text customers when you are on the way,
                        and capture signatures on the same platform that holds the sale and the boat record.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">ETA</div>
                            <div class="mt-1 text-sm text-gray-400">Maps-backed drive times</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">SMS</div>
                            <div class="mt-1 text-sm text-gray-400">En route &amp; arrived</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">✍</div>
                            <div class="mt-1 text-sm text-gray-400">Digital sign-off</div>
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
                            One trip, many assets, full accountability
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Deliveries connect sales and service outcomes to the customer doorstep:
                            what is going, who is driving, and when it is officially handed off.
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
                            Track, plan, schedule, and notify
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Dispatch sees the week at a glance. Drivers get clear ETAs.
                            Customers stay informed, and your team gets proof of delivery.
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
                            From scheduled to signed
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Status changes, travel math, fleet guards, and customer messages
                            all run on the same delivery record your dashboard already surfaces.
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
                badge="Built for marine delivery ops"
                badge-icon="local_shipping"
                title="Your delivery day deserves more than phone calls"
                description="Helmful connects scheduling, fleet, customer alerts, and signatures to the same customer and inventory data from the deal."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
