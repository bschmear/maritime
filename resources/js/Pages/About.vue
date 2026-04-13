<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    legalEmail: {
        type: String,
        required: true,
    },
});

const features = [
    { icon: 'person', label: 'Leads & Contacts', description: 'Manage leads, contacts, and customers in one place.' },
    { icon: 'handshake', label: 'Deals & Estimates', description: 'Create and track estimates and deals seamlessly.' },
    { icon: 'inventory_2', label: 'Inventory Control', description: 'Control inventory across multiple locations.' },
    { icon: 'build', label: 'Service Department', description: 'Run service with tickets and work orders.' },
    { icon: 'event', label: 'Events & Boat Shows', description: 'Manage boat shows, events, and promotions.' },
    { icon: 'bar_chart', label: 'Performance Tracking', description: 'Track performance across your entire operation.' },
];

const audiences = [
    { icon: 'anchor', label: 'Boat Dealerships' },
    { icon: 'sailing', label: 'Marine Brokers' },
    { icon: 'location_on', label: 'Multi-location Groups' },
    { icon: 'construction', label: 'Service Departments' },
    { icon: 'sell', label: 'Sales Teams' },
    { icon: 'visibility', label: 'Operations Teams' },
];

const philosophyItems = [
    { icon: 'hub', label: 'One source of truth for your entire business' },
    { icon: 'bolt', label: 'Automation wherever possible' },
    { icon: 'speed', label: 'Clean, fast, and intuitive workflows' },
    { icon: 'tune', label: 'Flexibility without complexity' },
    { icon: 'device_hub', label: 'Systems that match real-world dealership operations' },
];

const marineItems = [
    { icon: 'payments', label: 'High-value, low-frequency transactions' },
    { icon: 'list_alt', label: 'Complex inventory with detailed specifications' },
    { icon: 'wb_sunny', label: 'Seasonal buying patterns' },
    { icon: 'home_repair_service', label: 'Service and maintenance workflows' },
    { icon: 'store', label: 'Multi-location dealership operations' },
    { icon: 'celebration', label: 'Event-driven sales like boat shows' },
];
</script>

<template>
    <Head title="About" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

            <!-- Hero (blog-style) -->
            <section class="relative border-b border-gray-200 bg-primary-50 py-20 dark:border-gray-800 dark:bg-gray-900 px-6 sm:px-12  lg:px-24">
                <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-16 lg:grid-cols-2 lg:items-start ">
                    <!-- Left: Headline -->
                    <div>
                        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200/50 bg-primary-100 px-4 py-2 text-md font-medium text-primary-700 backdrop-blur-sm dark:border-primary-700/50 dark:bg-primary-900/50 dark:text-primary-300">
                            <span class="material-icons text-base leading-none">anchor</span>
                            <span>About Helmful</span>
                        </div>

                        <h1 class="mb-6 text-4xl font-extrabold leading-[1.1] tracking-tight text-gray-900 dark:text-white sm:text-5xl lg:text-[3.25rem]">
                            The operating system<br>for modern
                            <span class="text-primary-600 dark:text-primary-400">boat dealerships</span>
                        </h1>

                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            Helmful brings your entire operation into one place — from leads and inventory to deals, service,
                            customers, and events — so your team can move faster and actually sell more boats.
                        </p>
                    </div>

                    <!-- Right: Feature blocks -->
                    <div class="flex flex-col gap-7 lg:pt-1">
                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span class="material-icons text-[18px] leading-none text-primary-600 dark:text-primary-400">hub</span>
                            </div>
                            <div>
                                <h2 class="mb-1.5 text-base font-bold text-gray-900 dark:text-white">Everything connected</h2>
                                <p class="text-md leading-relaxed text-gray-600 dark:text-gray-400">No more jumping between spreadsheets, inboxes, and legacy tools. One platform, one source of truth.</p>
                            </div>
                        </div>

                        <div class="h-px bg-gray-200 dark:bg-gray-700"></div>

                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span class="material-icons text-[18px] leading-none text-primary-600 dark:text-primary-400">speed</span>
                            </div>
                            <div>
                                <h2 class="mb-1.5 text-base font-bold text-gray-900 dark:text-white">Built for your pace</h2>
                                <p class="text-md leading-relaxed text-gray-600 dark:text-gray-400">Designed around how dealerships actually work — fast-moving, seasonal, relationship-driven.</p>
                            </div>
                        </div>

                        <div class="h-px bg-gray-200 dark:bg-gray-700"></div>

                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span class="material-icons text-[18px] leading-none text-primary-600 dark:text-primary-400">groups</span>
                            </div>
                            <div>
                                <h2 class="mb-1.5 text-base font-bold text-gray-900 dark:text-white">Made for your whole team</h2>
                                <p class="text-md leading-relaxed text-gray-600 dark:text-gray-400">Sales, service, and management all in sync — no more silos between departments.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- The Problem -->
            <section class="border-b border-gray-200 bg-white px-6 py-20 dark:border-gray-800 dark:bg-gray-900 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-16 lg:grid-cols-2 lg:gap-24">
                        <div>
                            <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">The Problem</p>
                            <h2 class="mb-6 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                                Tools that were never built for you
                            </h2>
                            <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                                Boat dealerships have been forced to operate with tools that were never built for them.
                                The industry needed something built specifically for it.
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                                <p class="mb-4 text-md font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">What teams juggle</p>
                                <ul class="space-y-3 text-lg text-gray-700 dark:text-gray-200">
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-gray-400 dark:text-gray-500">table_chart</span>
                                        Spreadsheets for inventory
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-gray-400 dark:text-gray-500">person_off</span>
                                        Generic CRMs that don't understand marine
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-gray-400 dark:text-gray-500">link_off</span>
                                        Disconnected service systems
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-gray-400 dark:text-gray-500">edit_off</span>
                                        Manual processes for deals &amp; follow-ups
                                    </li>
                                </ul>
                            </div>
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900/60 dark:bg-red-950/40">
                                <p class="mb-4 text-md font-semibold uppercase tracking-wider text-red-500 dark:text-red-400">The result</p>
                                <ul class="space-y-3 text-lg text-red-800 dark:text-red-200">
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-red-500 dark:text-red-400">warning</span>
                                        Missed leads &amp; slow response times
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-red-500 dark:text-red-400">warning</span>
                                        Inaccurate or outdated inventory
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-red-500 dark:text-red-400">warning</span>
                                        Poor visibility across locations
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-icons mt-0.5 text-base leading-none text-red-500 dark:text-red-400">warning</span>
                                        Lost revenue hiding in the gaps
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- The Solution -->
            <section class="border-b border-gray-200 bg-gray-50 px-6 py-20 dark:border-gray-800 dark:bg-gray-950 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-2xl">
                        <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">The Solution</p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Helmful connects everything
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            Instead of forcing your team to switch between tools, we unify your entire dealership into
                            one system. Everything is connected. Everything updates in real time.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="feature in features"
                            :key="feature.label"
                            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:shadow-none"
                        >
                            <div class="mb-4 inline-flex items-center justify-center rounded-xl bg-primary-50 p-3 dark:bg-primary-900/40">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400">{{ feature.icon }}</span>
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ feature.label }}</p>
                            <p class="mt-1 text-lg leading-relaxed text-gray-600 dark:text-gray-400">{{ feature.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Why We Built It + Who It's For -->
            <section class="border-b border-gray-200 bg-white px-6 py-20 dark:border-gray-800 dark:bg-gray-900 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-24">
                        <div>
                            <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Why We Built It</p>
                            <h2 class="mb-6 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                                Dealership software that hasn't kept up
                            </h2>
                            <p class="mb-8 text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                                We saw teams struggling with tools that were too rigid, too slow, too disconnected,
                                and not built for high-value, relationship-driven sales. So we built something different.
                            </p>
                            <blockquote class="border-l-4 border-primary-500 pl-5 dark:border-primary-400">
                                <p class="text-xl font-semibold italic leading-snug text-gray-800 dark:text-gray-200">
                                    "Not just a CRM. A true operating system for boat dealerships."
                                </p>
                            </blockquote>
                        </div>
                        <div>
                            <p class="mb-6 text-md font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">Built for</p>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <div
                                    v-for="audience in audiences"
                                    :key="audience.label"
                                    class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-5 text-center dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <span class="material-icons text-3xl leading-none text-primary-500 dark:text-primary-400">{{ audience.icon }}</span>
                                    <p class="text-lg font-medium text-gray-700 dark:text-gray-200">{{ audience.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Our Philosophy -->
            <section class="border-b border-gray-200 bg-gray-50 px-6 py-20 dark:border-gray-800 dark:bg-gray-950 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-2xl">
                        <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Our Philosophy</p>
                        <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                            Software should feel like leverage, not overhead
                        </h2>
                        <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                            We don't force your business into rigid software structures. We build software that adapts
                            to how you already work — and makes it better.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="item in philosophyItems"
                            :key="item.label"
                            class="flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <span class="material-icons text-xl leading-none text-primary-500 dark:text-primary-400">{{ item.icon }}</span>
                            <p class="text-lg font-medium leading-relaxed text-gray-700 dark:text-gray-200">{{ item.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Built for Marine -->
            <section class="border-b border-gray-200 bg-white px-6 py-20 dark:border-gray-800 dark:bg-gray-900 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid items-start gap-16 lg:grid-cols-2 lg:gap-24">
                        <div class="lg:sticky lg:top-24">
                            <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Built for Marine</p>
                            <h2 class="mb-6 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white">
                                Not a generic CRM with boat labels on it
                            </h2>
                            <p class="text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                                Helmful is built specifically for the marine world. Every feature is designed around
                                real dealership behavior.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div
                                v-for="item in marineItems"
                                :key="item.label"
                                class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-900/40">
                                    <span class="material-icons text-xl leading-none text-primary-600 dark:text-primary-400">{{ item.icon }}</span>
                                </div>
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ item.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Mission -->
            <section class="border-b border-gray-800 bg-gray-950 px-6 py-20 dark:border-gray-900 dark:bg-black sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-12 lg:grid-cols-2 lg:gap-24">
                        <div>
                            <p class="mb-3 text-md font-semibold uppercase tracking-widest text-primary-400">Our Mission</p>
                            <h2 class="mb-6 text-4xl font-bold leading-tight tracking-tight text-white">
                                Become the operating system for the marine industry
                            </h2>
                            <p class="text-xl leading-relaxed text-gray-400">
                                We're building Helmful to help dealerships sell more boats, eliminate operational chaos,
                                and scale without adding complexity.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                                <span class="material-icons mb-3 text-3xl leading-none text-primary-400">directions_boat</span>
                                <p class="font-semibold text-white">Sell more boats</p>
                                <p class="mt-1 text-lg text-gray-400">Streamlined workflows from first contact to closed deal.</p>
                            </div>
                            <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                                <span class="material-icons mb-3 text-3xl leading-none text-primary-400">layers_clear</span>
                                <p class="font-semibold text-white">Eliminate chaos</p>
                                <p class="mt-1 text-lg text-gray-400">One system replacing a dozen disconnected tools.</p>
                            </div>
                            <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                                <span class="material-icons mb-3 text-3xl leading-none text-primary-400">favorite</span>
                                <p class="font-semibold text-white">Better relationships</p>
                                <p class="mt-1 text-lg text-gray-400">Full customer history, always at your team's fingertips.</p>
                            </div>
                            <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                                <span class="material-icons mb-3 text-3xl leading-none text-primary-400">trending_up</span>
                                <p class="font-semibold text-white">Scale without friction</p>
                                <p class="mt-1 text-lg text-gray-400">From single-location to enterprise — Helmful grows with you.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Closing CTA -->
            <section class="border-b border-gray-200 bg-gray-50 px-6 py-24 text-center dark:border-gray-800 dark:bg-gray-950 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-3xl">
                    <span class="material-icons mb-6 text-5xl leading-none text-secondary-600 dark:text-secondary-400">sailing</span>
                    <h2 class="mb-4 text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white sm:text-5xl">
                        Helmful is just getting started
                    </h2>
                    <p class="mb-10 text-xl leading-relaxed text-gray-600 dark:text-gray-300">
                        Built for boat dealerships that are ready to run tighter, faster, and smarter.
                    </p>
                    <a
                        href="#"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-8 py-4 text-base font-semibold text-white shadow-md transition hover:bg-primary-700"
                    >
                        <span class="material-icons text-base leading-none">rocket_launch</span>
                        Modernize your dealership
                    </a>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
