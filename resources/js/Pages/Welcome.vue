<script setup>
    import { ref } from 'vue';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { Head, Link } from '@inertiajs/vue3';

    defineProps({
        canLogin: {
            type: Boolean,
        },
        canRegister: {
            type: Boolean,
        },
        blogPosts: {
            type: Array,
            default: () => [],
        },
        pricingPlans: {
            type: Array,
            default: () => [],
        },
        faqs: {
            type: Array,
            default: () => [],
        }
    });

    function handleImageError() {
        document.getElementById('screenshot-container')?.classList.add('!hidden');
        document.getElementById('docs-card')?.classList.add('!row-span-1');
        document.getElementById('docs-card-content')?.classList.add('!flex-row');
        document.getElementById('background')?.classList.add('!hidden');
    }

    const billingCycle = ref('monthly');
    const openFaqIndex = ref(null);

    const toggleFaq = (index) => {
        openFaqIndex.value = openFaqIndex.value === index ? null : index;
    };

</script>

<template>
    <Head title="Welcome" />

    <AppLayout>
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-br from-primary-100 via-secondary-50 to-primary-50 dark:from-navy-900 dark:via-primary-950 dark:to-navy-800 overflow-hidden">

            <!-- Decorative Elements -->
            <div class="absolute inset-0 opacity-30 dark:opacity-20">
                <div class="absolute top-20 right-20 w-96 h-96 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-full blur-3xl animate-pulse"></div>
                <div class="absolute bottom-20 left-20 w-80 h-80 bg-gradient-to-tr from-secondary-500 to-primary-300 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-72 h-72 bg-gradient-to-r from-primary-300 to-secondary-300 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
            </div>

            <!-- Animated waves -->
            <div class="absolute bottom-0 left-0 right-0 opacity-20 dark:opacity-10">
                <svg viewBox="0 0 1440 320" class="w-full">
                    <path fill="currentColor" class="text-secondary-400 dark:text-secondary-700" fill-opacity="0.5" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                </svg>
            </div>

            <!-- Content -->
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 text-center">

                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary-200/60 dark:bg-primary-800/40 backdrop-blur-sm rounded-full text-primary-800 dark:text-primary-200 text-sm font-medium mb-8 border border-primary-300 dark:border-primary-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span>Smart CRM for Boat Sales</span>
                </div>

                <!-- Headline -->
                <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold text-gray-900 dark:text-white-100 mb-6 tracking-tight">
                    Sell More Boats,<br />
                    <span class="bg-gradient-to-r from-primary-600 to-secondary-500 dark:from-primary-400 dark:to-secondary-400 bg-clip-text text-transparent">
                        Manage Less Chaos
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="text-xl sm:text-2xl text-gray-700 dark:text-white-300 mb-10 max-w-3xl mx-auto leading-relaxed">
                    The all-in-one CRM built specifically for boat dealerships. Track inventory, manage leads, and close deals faster.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="#" class="group inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-500 dark:to-primary-600 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-2xl hover:shadow-primary-500/50 dark:hover:shadow-primary-400/30 hover:scale-105">
                        <span>Start Free Trial</span>
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                    <a href="#demo" class="inline-flex items-center gap-2 px-8 py-4 bg-white-500/80 dark:bg-navy-700/60 backdrop-blur-sm text-gray-800 dark:text-white-200 font-semibold rounded-xl border-2 border-gray-300 dark:border-primary-700 hover:border-primary-500 dark:hover:border-secondary-500 transition-all duration-200 hover:shadow-lg hover:bg-white dark:hover:bg-navy-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Watch Demo</span>
                    </a>
                </div>

                <!-- Social Proof -->
                <div class="mt-16 flex flex-col items-center">
                    <div class="flex -space-x-2 mb-4">
                        <img class="w-10 h-10 rounded-full border-2 border-white-500 dark:border-navy-700 shadow-lg" src="https://i.pravatar.cc/150?img=1" alt="User" />
                        <img class="w-10 h-10 rounded-full border-2 border-white-500 dark:border-navy-700 shadow-lg" src="https://i.pravatar.cc/150?img=2" alt="User" />
                        <img class="w-10 h-10 rounded-full border-2 border-white-500 dark:border-navy-700 shadow-lg" src="https://i.pravatar.cc/150?img=3" alt="User" />
                        <img class="w-10 h-10 rounded-full border-2 border-white-500 dark:border-navy-700 shadow-lg" src="https://i.pravatar.cc/150?img=4" alt="User" />
                        <img class="w-10 h-10 rounded-full border-2 border-white-500 dark:border-navy-700 shadow-lg" src="https://i.pravatar.cc/150?img=5" alt="User" />
                    </div>
                    <p class="text-sm text-gray-700 dark:text-white-400">
                        Trusted by <span class="font-semibold text-gray-900 dark:text-white-100">500+</span> boat dealerships nationwide
                    </p>
                </div>

            </div>
        </section>


        <!-- How It Works Section -->
        <section id="how-it-works" class="py-24 bg-white-500 dark:bg-navy-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center mb-20">
                    <h2 class="text-base font-semibold text-primary-600 dark:text-primary-400 tracking-wide uppercase mb-3">How It Works</h2>
                    <p class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white-100 mb-4">
                        Your Complete Boat Sales Solution
                    </p>
                    <p class="text-xl text-gray-700 dark:text-white-400 max-w-2xl mx-auto">
                        From lead capture to closing deals, streamline every aspect of your boat dealership in one powerful platform
                    </p>
                </div>

                <!-- Steps Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <!-- Steps List -->
                    <div class="space-y-8">
                        <!-- Step 1 -->
                        <div class="flex gap-6 group">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    01
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100">Capture Leads</h3>
                                </div>
                                <p class="text-gray-700 dark:text-white-400 leading-relaxed">
                                    Automatically capture leads from your website, boat shows, walk-ins, and phone inquiries. Every potential buyer is instantly added to your CRM with full contact details and preferences.
                                </p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex gap-6 group">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 bg-gradient-to-br from-secondary-500 to-secondary-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    02
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-6 h-6 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                    </svg>
                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100">Manage Inventory</h3>
                                </div>
                                <p class="text-gray-700 dark:text-white-400 leading-relaxed">
                                    Track every boat in your inventory with photos, specs, pricing, and availability. Sync with popular listing sites automatically and update all platforms with one click.
                                </p>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex gap-6 group">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-400 to-secondary-500 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    03
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100">Follow Up & Engage</h3>
                                </div>
                                <p class="text-gray-700 dark:text-white-400 leading-relaxed">
                                    Never miss a follow-up with automated reminders and email sequences. Track every customer interaction, schedule test drives, and keep prospects warm with personalized communications.
                                </p>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="flex gap-6 group">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 bg-gradient-to-br from-secondary-600 to-primary-700 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    04
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <svg class="w-6 h-6 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100">Close & Analyze</h3>
                                </div>
                                <p class="text-gray-700 dark:text-white-400 leading-relaxed">
                                    Manage deals through your sales pipeline with clear visibility. Track commission, generate contracts, and analyze performance metrics to optimize your dealership's success.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Visual/Screenshot -->
                    <div class="relative lg:order-last">
                        <!-- Main Screenshot Container -->
                        <div class="relative bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 rounded-3xl p-8 shadow-2xl">
                            <!-- Browser-like Header -->
                            <div class="bg-white dark:bg-navy-800 rounded-2xl overflow-hidden shadow-xl">
                                <div class="bg-gray-100 dark:bg-navy-700 px-4 py-3 flex items-center gap-2">
                                    <div class="flex gap-2">
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 bg-white dark:bg-navy-600 rounded px-3 py-1 text-xs text-gray-500 dark:text-gray-300 ml-2">
                                        app.boatcrm.com/dashboard
                                    </div>
                                </div>

                                <!-- Dashboard Preview -->
                                <div class="p-6 space-y-4">
                                    <!-- Active Leads -->
                                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/30 dark:to-secondary-900/30 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center text-white font-bold text-lg">
                                                24
                                            </div>
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Active Leads</span>
                                        </div>
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>

                                    <!-- Featured Boat -->
                                    <div class="p-4 bg-white dark:bg-navy-700 rounded-xl border-2 border-primary-200 dark:border-primary-700">
                                        <div class="flex items-center gap-2 mb-3">
                                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                            </svg>
                                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase">Hot Deal</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="w-16 h-16 bg-gradient-to-br from-primary-200 to-secondary-200 dark:from-primary-800 dark:to-secondary-800 rounded-lg flex items-center justify-center">
                                                <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-bold text-gray-900 dark:text-white-100">Sea Ray Sundancer 320</p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">2022 • 32ft • $289,000</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Follow-up Schedule -->
                                    <div class="grid grid-cols-5 gap-2">
                                        <div class="aspect-square bg-gray-100 dark:bg-navy-700 rounded flex items-center justify-center">
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-semibold">M</span>
                                        </div>
                                        <div class="aspect-square bg-gray-100 dark:bg-navy-700 rounded flex items-center justify-center">
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-semibold">T</span>
                                        </div>
                                        <div class="aspect-square bg-primary-500 rounded flex flex-col items-center justify-center">
                                            <span class="text-xs text-white font-bold">W</span>
                                            <div class="w-1 h-1 bg-white rounded-full mt-1"></div>
                                        </div>
                                        <div class="aspect-square bg-gray-100 dark:bg-navy-700 rounded flex items-center justify-center">
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-semibold">T</span>
                                        </div>
                                        <div class="aspect-square bg-secondary-500 rounded flex flex-col items-center justify-center">
                                            <span class="text-xs text-white font-bold">F</span>
                                            <div class="flex gap-0.5 mt-1">
                                                <div class="w-1 h-1 bg-white rounded-full"></div>
                                                <div class="w-1 h-1 bg-white rounded-full"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sales Pipeline Chart -->
                                    <div class="p-4 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Sales This Month</span>
                                            <span class="text-lg font-bold text-green-600 dark:text-green-400">$1.2M</span>
                                        </div>
                                        <div class="flex items-end gap-1 h-16">
                                            <div class="flex-1 bg-primary-300 dark:bg-primary-600 rounded-t" style="height: 50%"></div>
                                            <div class="flex-1 bg-primary-400 dark:bg-primary-500 rounded-t" style="height: 65%"></div>
                                            <div class="flex-1 bg-secondary-500 dark:bg-secondary-400 rounded-t" style="height: 85%"></div>
                                            <div class="flex-1 bg-secondary-600 dark:bg-secondary-300 rounded-t" style="height: 100%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Elements -->
                        <div class="absolute -top-6 -right-6 bg-white dark:bg-navy-800 rounded-2xl shadow-2xl p-4 animate-float">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Deal Closed</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white-100">$125,000</p>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -bottom-6 -left-6 bg-white dark:bg-navy-800 rounded-2xl shadow-2xl p-4 animate-float" style="animation-delay: 1s">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-primary-400 to-secondary-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">New Leads</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white-100">+12 Today</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-navy-900 dark:to-navy-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center mb-16">
                    <h2 class="text-base font-semibold text-primary-600 dark:text-primary-400 tracking-wide uppercase mb-3">Pricing</h2>
                    <p class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white-100 mb-4">
                        Choose Your Plan
                    </p>
                    <p class="text-xl text-gray-700 dark:text-white-400 max-w-2xl mx-auto mb-8">
                        Flexible pricing for boat dealerships of all sizes. Start free and scale as you grow.
                    </p>

                    <!-- Billing Toggle -->
                    <div class="inline-flex items-center bg-white dark:bg-navy-800 rounded-full p-1 shadow-md">
                        <button
                            @click="billingCycle = 'monthly'"
                            :class="[
                                'px-6 py-2 rounded-full font-semibold transition-all duration-200',
                                billingCycle === 'monthly'
                                    ? 'bg-primary-600 text-white shadow-lg'
                                    : 'text-gray-700 dark:text-white-400 hover:text-gray-900 dark:hover:text-white-100'
                            ]"
                        >
                            Monthly
                        </button>
                        <button
                            @click="billingCycle = 'annual'"
                            :class="[
                                'px-6 py-2 rounded-full font-semibold transition-all duration-200 flex items-center gap-2',
                                billingCycle === 'annual'
                                    ? 'bg-primary-600 text-white shadow-lg'
                                    : 'text-gray-700 dark:text-white-400 hover:text-gray-900 dark:hover:text-white-100'
                            ]"
                        >
                            Annual
                            <span class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full">Save 20%</span>
                        </button>
                    </div>
                </div>

                <!-- Pricing Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-6">
                    <div
                        v-for="(plan, index) in pricingPlans"
                        :key="index"
                        :class="[
                            'relative bg-white dark:bg-navy-800 rounded-3xl shadow-xl overflow-hidden transition-all duration-300 hover:shadow-2xl',
                            plan.popular ? 'ring-4 ring-primary-500 dark:ring-primary-500 scale-105 md:scale-110' : 'hover:scale-105'
                        ]"
                    >
                        <!-- Popular Badge -->
                        <div v-if="plan.popular" class="absolute top-0 right-0 bg-primary-500 text-white text-xs font-bold px-4 py-1 rounded-bl-2xl">
                            MOST POPULAR
                        </div>

                        <div class="p-8">
                            <!-- Plan Header -->
                            <div class="mb-6">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100 mb-2">{{ plan.name }}</h3>
                                <p class="text-gray-700 dark:text-white-400 text-sm">{{ plan.description }}</p>
                            </div>

                            <!-- Price -->
                            <div class="mb-8">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-5xl font-bold text-gray-900 dark:text-white-100">
                                        ${{ billingCycle === 'monthly' ? plan.price.monthly : plan.price.annual }}
                                    </span>
                                    <span class="text-gray-700 dark:text-white-400">
                                        {{ billingCycle === 'monthly' ? '/month' : '/year' }}
                                    </span>
                                </div>
                                <p v-if="billingCycle === 'annual' && plan.price.annual > 0" class="text-sm text-green-600 dark:text-green-400 mt-2">
                                    Save ${{ (plan.price.monthly * 12) - plan.price.annual }}/year
                                </p>
                            </div>

                            <!-- CTA Button -->
                            <Link
                                :href="route('checkout.plans', { plan: plan.id, billing: billingCycle })"
                                :class="[
                                    'block w-full text-center px-6 py-4 rounded-xl font-semibold transition-all duration-200 mb-8',
                                    plan.popular
                                        ? 'bg-primary-500 text-white hover:shadow-lg hover:scale-105'
                                        : 'bg-gray-900 dark:bg-white-500 text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-white-400'
                                ]"
                            >
                                {{ plan.cta }}
                            </Link>

                            <!-- Features List -->
                            <div class="space-y-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white-100 uppercase tracking-wide">What's included:</p>
                                <ul class="space-y-3">
                                    <li v-for="(feature, fIndex) in plan.features" :key="fIndex" class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-gray-700 dark:text-white-400">{{ feature }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="mt-16 text-center">
                    <p class="text-gray-700 dark:text-white-400 mb-4">
                        All plans include 14-day free trial. No credit card required.
                    </p>
                    <a href="#" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">
                        View detailed feature comparison →
                    </a>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="py-24 bg-white-500 dark:bg-navy-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center mb-16">
                    <h2 class="text-base font-semibold text-primary-600 dark:text-primary-400 tracking-wide uppercase mb-3">FAQ</h2>
                    <p class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white-100 mb-4">
                        Frequently Asked Questions
                    </p>
                    <p class="text-xl text-gray-700 dark:text-white-400">
                        Everything you need to know about our boat CRM platform
                    </p>
                </div>

                <!-- FAQ Accordion -->
                <div v-if="faqs && faqs.length > 0" class="space-y-4">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="faq.id || index"
                        class="bg-primary-50 dark:bg-navy-800 rounded-2xl overflow-hidden transition-all duration-200 hover:shadow-lg"
                    >
                        <button
                            @click="toggleFaq(index)"
                            class="w-full flex items-center justify-between p-6 text-left transition-colors duration-200"
                        >
                            <span class="text-lg font-semibold text-gray-900 dark:text-white-100 pr-8">
                                {{ faq.question }}
                            </span>
                            <svg
                                :class="[
                                    'w-6 h-6 text-primary-600 dark:text-primary-400 flex-shrink-0 transition-transform duration-200',
                                    openFaqIndex === index ? 'rotate-180' : ''
                                ]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            :class="[
                                'overflow-hidden transition-all duration-300',
                                openFaqIndex === index ? 'max-h-96' : 'max-h-0'
                            ]"
                        >
                            <div class="px-6 pb-6 text-gray-700 dark:text-white-400 leading-relaxed">
                                {{ faq.answer }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No FAQs Available -->
                <div v-else class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 dark:bg-navy-800 rounded-full mb-4">
                        <svg class="w-8 h-8 text-primary-400 dark:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white-100 mb-2">No FAQs Available</h3>
                    <p class="text-gray-700 dark:text-white-400">Frequently asked questions will be available soon.</p>
                </div>

                <!-- Additional Help -->
                <div v-if="faqs && faqs.length > 0" class="mt-16 text-center p-8 bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 rounded-3xl">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white-100 mb-3">
                        Still have questions?
                    </h3>
                    <p class="text-gray-700 dark:text-white-400 mb-6">
                        Can't find the answer you're looking for? Our support team is here to help.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Contact Support</span>
                        </a>
                        <a href="#" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white dark:bg-navy-800 text-gray-900 dark:text-white-100 font-semibold rounded-xl border-2 border-gray-300 dark:border-navy-700 hover:border-primary-500 dark:hover:border-primary-600 transition-colors duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>View Full FAQ</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Blog Teaser Section -->
        <section v-if="blogPosts.length > 0" id="blog" class="py-24 bg-white-500 dark:bg-navy-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center mb-16">
                    <h2 class="text-base font-semibold text-primary-600 dark:text-primary-400 tracking-wide uppercase mb-3">From Our Blog</h2>
                    <p class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white-100 mb-4">
                        Latest Insights & Tips
                    </p>
                    <p class="text-xl text-gray-700 dark:text-white-400 max-w-2xl mx-auto">
                        Stay updated with boat sales strategies, CRM tips, and marine industry insights
                    </p>
                </div>

                <!-- Blog Posts Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                    <article
                        v-for="(post, index) in blogPosts"
                        :key="index"
                        class="group bg-white dark:bg-navy-800 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2"
                    >
                        <!-- Featured Image -->
                        <div class="relative overflow-hidden h-48">
                            <img
                                :src="post.image"
                                :alt="post.title"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                            />
                            <div class="absolute top-4 left-4">
                                <span class="inline-block px-3 py-1 bg-primary-600 text-white text-xs font-semibold rounded-full">
                                    {{ post.category }}
                                </span>
                            </div>
                        </div>

                        <!-- Post Content -->
                        <div class="p-6">
                            <!-- Meta Info -->
                            <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-white-400 mb-3">
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>{{ post.date }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ post.readTime }}</span>
                                </div>
                            </div>

                            <!-- Title -->
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white-100 mb-3 line-clamp-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ post.title }}
                            </h3>

                            <!-- Excerpt -->
                            <p class="text-gray-700 dark:text-white-400 mb-4 line-clamp-3">
                                {{ post.excerpt }}
                            </p>

                            <!-- Author & Read More -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-navy-700">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-secondary-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                        {{ post.author.charAt(0) }}
                                    </div>
                                    <span class="text-sm text-gray-700 dark:text-white-300 font-medium">
                                        {{ post.author }}
                                    </span>
                                </div>
                                <a
                                    :href="post.link"
                                    class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 font-semibold text-sm group-hover:gap-2 transition-all"
                                >
                                    <span>Read More</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- View All CTA -->
                <div class="text-center">
                    <a
                        href="/blog"
                        class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary-500 to-secondary-500 hover:from-primary-700 hover:to-secondary-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105"
                    >
                        <span>View All Articles</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>


    </AppLayout>
</template>
