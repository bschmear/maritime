<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const highlights = [
    {
        icon: 'link',
        title: 'Connect your Stripe account',
        description: 'Each dealership connects its own Stripe account from Account → Payments. Funds go to you—not a shared platform wallet.',
    },
    {
        icon: 'credit_card',
        title: 'Pay invoices online',
        description: 'Customers use a secure pay link on open invoices. Card and bank debit are available when you enable them.',
    },
    {
        icon: 'tune',
        title: 'Methods you control',
        description: 'Turn on card, ACH, and other options your store accepts, and choose which methods appear on each invoice.',
    },
    {
        icon: 'receipt_long',
        title: 'Payments stay in Helmful',
        description: 'When a customer pays online, the invoice balance updates in Helmful so your team sees the same status as your customer.',
    },
];
</script>

<template>
    <Head title="Stripe Payments" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

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
                            <span class="material-icons text-sm leading-none text-primary-400">payments</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Stripe Payments</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Get paid on invoices,<br>
                        <span class="text-primary-400">on your Stripe account.</span>
                    </h1>
                    <p class="max-w-2xl text-lg leading-relaxed text-gray-400">
                        Connect Stripe, turn on the payment methods you accept,
                        and let customers pay open invoices online—funds flow to your dealership.
                    </p>
                </div>

                <div class="absolute bottom-0 left-0 right-0 leading-none">
                    <svg viewBox="0 0 1440 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="h-16 w-full">
                        <path class="fill-gray-50 dark:fill-gray-900" d="M0,32 C180,64 360,0 540,32 C720,64 900,0 1080,32 C1260,64 1350,16 1440,32 L1440,64 L0,64 Z" />
                    </svg>
                </div>
            </section>

            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">What you get</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            From open invoice to paid
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Customer invoice payments are separate from your Helmful subscription.
                            Your connected Stripe account is where online invoice payments land.
                        </p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="item in highlights"
                            :key="item.title"
                            class="rounded-2xl border border-gray-200 bg-white p-7 shadow-sm dark:border-gray-700/60 dark:bg-gray-800/80"
                        >
                            <div class="mb-5 inline-flex rounded-xl bg-primary-50 p-3 dark:bg-primary-900/30">
                                <span class="material-icons text-2xl leading-none text-primary-600 dark:text-primary-400">{{ item.icon }}</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ item.title }}</h3>
                            <p class="mt-2 text-md leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <FeaturePageCta
                badge="Payments integration"
                badge-icon="payments"
                title="Ready to accept invoice payments online?"
                description="Connect Stripe from Account → Payments and start sending customers a secure pay link on open invoices."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
