<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const features = [
    {
        icon: 'person',
        title: 'Leads & Contacts',
        description: 'Capture, assign, and nurture leads with full contact history in one place.',
        href: null,
        category: 'Sales',
    },
    {
        icon: 'handshake',
        title: 'Deals & Estimates',
        description: 'Build estimates, move deals through your pipeline, and close with confidence.',
        href: null,
        category: 'Sales',
    },
    {
        icon: 'inventory_2',
        title: 'Inventory Control',
        description: 'Track boats, engines, and trailers across locations with rich specifications.',
        href: null,
        category: 'Operations',
    },
    {
        icon: 'build',
        title: 'Service Department',
        description: 'Run service tickets and work orders tied to customers and inventory.',
        href: 'features.service-department',
        category: 'Operations',
        featured: true,
    },
    {
        icon: 'event',
        title: 'Boat Shows & Events',
        description: 'Plan show layouts, showcase inventory publicly, and capture leads on the floor.',
        href: 'features.boat-shows',
        category: 'Marketing',
    },
    {
        icon: 'bar_chart',
        title: 'Performance Tracking',
        description: 'See how your team and locations are performing across sales and operations.',
        href: 'features.performance-tracking',
        category: 'Analytics',
    },
    {
        icon: 'local_shipping',
        title: 'Delivery System',
        description: 'Plan routes, schedule deliveries, alert customers, and capture signatures on delivery day.',
        href: 'features.delivery-system',
        category: 'Operations',
    },
    {
        icon: 'poll',
        title: 'Smart Surveys',
        description: 'Build feedback and lead surveys with templates, conditional logic, and responses tied to leads and contacts.',
        href: 'features.smart-surveys',
        category: 'Marketing',
    },
    {
        icon: 'payments',
        title: 'Stripe Payments',
        description: 'Connect Stripe Express and let customers pay open invoices online by card or bank debit.',
        href: 'features.stripe-payments',
        category: 'Integrations',
    },
    {
        icon: 'campaign',
        title: 'Mailchimp',
        description: 'Sync contacts and leads with Mailchimp audiences — push, pull, lists, and segments.',
        href: 'features.mailchimp',
        category: 'Integrations',
    },
    {
        icon: 'account_balance',
        title: 'QuickBooks Online',
        description: 'Connect QuickBooks to sync customers, push invoices, and pull payments into Helmful.',
        href: 'features.quickbooks',
        category: 'Integrations',
    },
];
</script>

<template>
    <Head title="Features" />

    <AppLayout>
        <div class="min-h-screen overflow-hidden bg-white dark:bg-gray-950">

            <!-- ─── HERO ─── -->
            <section class="relative border-b border-gray-200 dark:border-gray-800 px-6 py-24 sm:px-12 lg:px-24" >
                <div class="relative  max-w-7xl mx-auto">

                    <!-- Badge -->
                    <div class="mb-8 inline-flex items-center gap-2.5 rounded-full border border-primary-200 bg-primary-50 px-4 py-2 dark:border-primary-900 dark:bg-primary-950/40">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-primary-600"></span>
                        </span>
                        <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">Everything Connected</span>
                    </div>

                    <!-- Main headline -->
                    <h1 class="mb-6 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.1] tracking-tight text-gray-950 dark:text-white max-w-4xl">
                        <span class="block">Power your dealership</span>
                        <span class="block text-primary-600 dark:text-primary-400">from showroom to service</span>
                    </h1>

                    <!-- Subheading -->
                    <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300 max-w-2xl leading-relaxed">
                        Helmful unifies every part of your business on one platform. Shared data means your entire team has context, always from the lot to the customer record.
                    </p>

                    <!-- CTA Button -->
                <!--     <div class="mt-10">
                        <button class="inline-flex items-center gap-2 px-6 py-3.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-semibold shadow-lg shadow-primary-600/30 transition-all duration-200 hover:shadow-xl hover:shadow-primary-600/40">
                            <span>Explore the platform</span>
                            <span class="material-icons text-lg">arrow_forward</span>
                        </button>
                    </div> -->
                </div>
            </section>

            <!-- ─── FEATURES BY CATEGORY ─── -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="max-w-7xl mx-auto">

                    <!-- Sales & Operations section -->
                    <div class="mb-20">
                        <div class="mb-12">
                            <div class="inline-flex items-center gap-2 mb-4">
                                <span class="material-icons text-primary-600 dark:text-primary-400">storefront</span>
                                <span class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Core Platform</span>
                            </div>
                            <h2 class="text-4xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-4">
                                Built for how you work
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl">
                                Each module works independently, but shares the same customer and inventory data so context flows naturally across your entire operation.
                            </p>
                        </div>

                        <!-- Main features grid (3 columns) -->
                        <div class="grid gap-6 lg:grid-cols-3">
                            <component
                                v-for="(feature, idx) in [features[0], features[1], features[2]]"
                                :key="feature.title"
                                :is="feature.href && route().has(feature.href) ? Link : 'div'"
                                :href="feature.href && route().has(feature.href) ? route(feature.href) : undefined"
                                class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-8 transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
                                :class="feature.href && route().has(feature.href) ? 'hover:border-primary-300 hover:shadow-lg dark:hover:border-primary-700' : 'hover:border-gray-300 dark:hover:border-gray-700'"
                                :style="{ animationDelay: `${idx * 100}ms` }"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-primary-500/5"></div>

                                <div class="relative z-10">
                                    <!-- Icon -->
                                    <div class="mb-6 inline-flex items-center justify-center h-14 w-14 rounded-xl bg-primary-100 dark:bg-primary-950">
                                        <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">{{ feature.icon }}</span>
                                    </div>

                                    <!-- Content -->
                                    <h3 class="text-xl font-bold text-gray-950 dark:text-white mb-3">{{ feature.title }}</h3>
                                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4">{{ feature.description }}</p>

                                    <!-- Link -->
                                    <div
                                        v-if="feature.href && route().has(feature.href)"
                                        class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold text-sm group-hover:gap-3 transition-all"
                                    >
                                        Learn more
                                        <span class="material-icons text-base">arrow_forward</span>
                                    </div>
                                </div>
                            </component>
                        </div>
                    </div>

                    <!-- Advanced features section -->
                    <div>
                        <div class="mb-12">
                            <div class="inline-flex items-center gap-2 mb-4">
                                <span class="material-icons text-secondary-600 dark:text-secondary-400">spark</span>
                                <span class="text-sm font-bold uppercase tracking-widest text-secondary-600 dark:text-secondary-400">Advanced</span>
                            </div>
                            <h2 class="text-4xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-4">
                                Specialized modules
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl">
                                Deep features for specific workflows. From deliveries and boat shows to service performance.
                            </p>
                        </div>

                        <!-- Featured + others grid -->
                        <div class="grid gap-6 lg:grid-cols-3">
                            <!-- Featured card (spans 2 columns) -->
                            <component
                                :is="features[3].href && route().has(features[3].href) ? Link : 'div'"
                                :href="features[3].href && route().has(features[3].href) ? route(features[3].href) : undefined"
                                class="group lg:col-span-2 relative overflow-hidden rounded-2xl border border-secondary-200 bg-secondary-50 dark:border-secondary-900 dark:bg-secondary-950/30 p-8 lg:p-10 transition-all duration-300 ring-1 ring-secondary-100 dark:ring-secondary-900"
                                :class="features[3].href && route().has(features[3].href) ? 'hover:border-secondary-400 hover:shadow-lg dark:hover:border-secondary-700 dark:hover:ring-secondary-800' : ''"
                                style="animation-delay: 100ms"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-secondary-500/5"></div>

                                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:gap-8">
                                    <div class="flex-1">
                                        <div class="mb-6 inline-flex items-center justify-center h-16 w-16 rounded-xl bg-secondary-100 dark:bg-secondary-900">
                                            <span class="material-icons text-3xl text-secondary-600 dark:text-secondary-400">{{ features[3].icon }}</span>
                                        </div>

                                        <h3 class="text-2xl lg:text-3xl font-bold text-gray-950 dark:text-white mb-3">{{ features[3].title }}</h3>
                                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed text-lg mb-6 whitespace-normal">{{ features[3].description }}</p>

                                        <div
                                            v-if="features[3].href && route().has(features[3].href)"
                                            class="inline-flex items-center gap-2 text-secondary-600 dark:text-secondary-400 font-semibold group-hover:gap-3 transition-all"
                                        >
                                            Explore feature
                                            <span class="material-icons">arrow_forward</span>
                                        </div>
                                    </div>

                                    <!-- Decorative element -->
                                    <div class="hidden lg:block flex-shrink-0">
                                        <div class="relative h-48 w-48">
                                            <div class="absolute inset-0 bg-secondary-200/30 dark:bg-secondary-900/30 rounded-2xl"></div>
                                            <div class="relative h-full flex items-center justify-center">
                                                <span class="material-icons text-8xl text-secondary-200 dark:text-secondary-900">build_circle</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </component>

                            <!-- Other features -->
                            <component
                                v-for="(feature, idx) in [features[4], features[5], features[6], features[7]]"
                                :key="feature.title"
                                :is="feature.href && route().has(feature.href) ? Link : 'div'"
                                :href="feature.href && route().has(feature.href) ? route(feature.href) : undefined"
                                class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-8 transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
                                :class="feature.href && route().has(feature.href) ? 'hover:border-primary-300 hover:shadow-lg dark:hover:border-primary-700' : 'hover:border-gray-300 dark:hover:border-gray-700'"
                                :style="{ animationDelay: `${200 + idx * 100}ms` }"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-primary-500/5"></div>

                                <div class="relative z-10">
                                    <div class="mb-6 inline-flex items-center justify-center h-14 w-14 rounded-xl bg-primary-100 dark:bg-primary-950">
                                        <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">{{ feature.icon }}</span>
                                    </div>

                                    <h3 class="text-xl font-bold text-gray-950 dark:text-white mb-3">{{ feature.title }}</h3>
                                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4 whitespace-normal">{{ feature.description }}</p>

                                    <div
                                        v-if="feature.href && route().has(feature.href)"
                                        class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold text-sm group-hover:gap-3 transition-all"
                                    >
                                        Learn more
                                        <span class="material-icons text-base">arrow_forward</span>
                                    </div>
                                </div>
                            </component>
                            
                        </div>

                    </div>

                    <!-- Integrations -->
                    <div class="mt-24">
                        <div class="mb-12">
                            <div class="inline-flex items-center gap-2 mb-4">
                                <span class="material-icons text-primary-600 dark:text-primary-400">extension</span>
                                <span class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Integrations</span>
                            </div>
                            <h2 class="text-4xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-4">
                                Connect the tools you already use
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl">
                                Payments, email audiences, and accounting — wired into the same customers, invoices, and contacts your team works with every day.
                            </p>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <component
                                v-for="(feature, idx) in [features[8], features[9], features[10]]"
                                :key="feature.title"
                                :is="feature.href && route().has(feature.href) ? Link : 'div'"
                                :href="feature.href && route().has(feature.href) ? route(feature.href) : undefined"
                                class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-8 transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
                                :class="feature.href && route().has(feature.href) ? 'hover:border-primary-300 hover:shadow-lg dark:hover:border-primary-700' : 'hover:border-gray-300 dark:hover:border-gray-700'"
                                :style="{ animationDelay: `${500 + idx * 100}ms` }"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-primary-500/5"></div>

                                <div class="relative z-10">
                                    <div class="mb-6 inline-flex items-center justify-center h-14 w-14 rounded-xl bg-primary-100 dark:bg-primary-950">
                                        <span class="material-icons text-2xl text-primary-600 dark:text-primary-400">{{ feature.icon }}</span>
                                    </div>

                                    <h3 class="text-xl font-bold text-gray-950 dark:text-white mb-3">{{ feature.title }}</h3>
                                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4 whitespace-normal">{{ feature.description }}</p>

                                    <div
                                        v-if="feature.href && route().has(feature.href)"
                                        class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold text-sm group-hover:gap-3 transition-all"
                                    >
                                        Learn more
                                        <span class="material-icons text-base">arrow_forward</span>
                                    </div>
                                </div>
                            </component>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ─── DATA CONNECTIVITY SECTION ─── -->
            <section class="relative px-6 py-20 sm:px-12 lg:px-24 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">

                <div class="max-w-7xl mx-auto">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <h2 class="text-4xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-6 leading-tight">
                                One platform,
                                <span class="text-primary-600 dark:text-primary-400">unified data</span>
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                                Unlike fragmented systems, Helmful connects all your data. When a lead is captured at a boat show, your sales team sees their complete history on Monday. When a customer enters the service bay, you have their entire ownership record.
                            </p>
                            <ul class="space-y-4">
                                <li class="flex gap-4 items-start">
                                    <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 mt-1">✓</span>
                                    <span class="text-gray-700 dark:text-gray-300">Shared customer and inventory data across all modules</span>
                                </li>
                                <li class="flex gap-4 items-start">
                                    <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 mt-1">✓</span>
                                    <span class="text-gray-700 dark:text-gray-300">Real-time updates so everyone has current information</span>
                                </li>
                                <li class="flex gap-4 items-start">
                                    <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 mt-1">✓</span>
                                    <span class="text-gray-700 dark:text-gray-300">No double data entry, fewer mistakes, happier teams</span>
                                </li>
                            </ul>
                        </div>

                        <div class="relative">
                            <div class="relative bg-white dark:bg-gray-800 rounded-3xl p-8 border border-gray-200 dark:border-gray-700">
                                <div class="space-y-4">
                                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-gray-100 dark:bg-gray-700">
                                        <span class="material-icons text-primary-600 dark:text-primary-400 flex-shrink-0">person</span>
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-950 dark:text-white">Customer Record</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Shared across all modules</p>
                                        </div>
                                    </div>
                                    <div class="text-center text-gray-400 dark:text-gray-500">↓</div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="p-4 rounded-2xl bg-primary-100 dark:bg-primary-950 border border-primary-200 dark:border-primary-900">
                                            <span class="material-icons text-primary-600 dark:text-primary-400 text-2xl mb-2">handshake</span>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Sales</p>
                                        </div>
                                        <div class="p-4 rounded-2xl bg-secondary-100 dark:bg-secondary-950 border border-secondary-200 dark:border-secondary-900">
                                            <span class="material-icons text-secondary-600 dark:text-secondary-400 text-2xl mb-2">build</span>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Service</p>
                                        </div>
                                        <div class="p-4 rounded-2xl bg-primary-100 dark:bg-primary-950 border border-primary-200 dark:border-primary-900">
                                            <span class="material-icons text-primary-600 dark:text-primary-400 text-2xl mb-2">inventory_2</span>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Inventory</p>
                                        </div>
                                        <div class="p-4 rounded-2xl bg-secondary-100 dark:bg-secondary-950 border border-secondary-200 dark:border-secondary-900">
                                            <span class="material-icons text-secondary-600 dark:text-secondary-400 text-2xl mb-2">event</span>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Events</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── CTA ─── -->
            <FeaturePageCta
                badge="Get started"
                badge-icon="rocket_launch"
                title="Ready to see it in action?"
                description="Talk with our team about how Helmful fits your dealership or start with pricing."
                primary-label="Contact us"
                primary-route="contact"
                secondary-label="View pricing"
                secondary-route="checkout.plans"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group {
    animation: fade-in-up 0.6s ease-out forwards;
    opacity: 0;
}

.group:nth-child(1) { animation-delay: 100ms; }
.group:nth-child(2) { animation-delay: 200ms; }
.group:nth-child(3) { animation-delay: 300ms; }
.group:nth-child(4) { animation-delay: 400ms; }
.group:nth-child(5) { animation-delay: 500ms; }
.group:nth-child(6) { animation-delay: 600ms; }
</style>
