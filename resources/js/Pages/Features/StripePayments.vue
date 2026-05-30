<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'link',
        title: 'Stripe Connect',
        description: 'Each dealership connects its own Stripe Express account. Onboarding runs through Stripe; Helmful stores the connected account id and syncs charges_enabled and payouts_enabled before checkout is offered.',
    },
    {
        icon: 'tune',
        title: 'Payment Methods',
        description: 'Enable card, ACH, and wire options per account configuration. Invoices can restrict which methods customers see on the public pay page.',
    },
    {
        icon: 'receipt_long',
        title: 'Payments & Invoices',
        description: 'Recorded payments live in Helmful alongside Stripe Checkout sessions. When a customer pays an open invoice online, the webhook flow confirms the payment and updates invoice balance.',
    },
];

const capabilities = [
    {
        icon: 'credit_card',
        title: 'Pay Invoices Online',
        description: 'Customers open a secure Stripe Checkout session from the public invoice page. Card and US bank debit are supported when enabled and allowed on the invoice.',
    },
    {
        icon: 'percent',
        title: 'Optional Surcharge',
        description: 'Configure a surcharge percent on invoices so card fees can be passed through transparently. Principal and surcharge amounts are stored in Checkout metadata for verification.',
    },
    {
        icon: 'account_balance',
        title: 'ACH & Bank Debit',
        description: 'When ACH or wire is enabled for USD invoices, Checkout can offer us_bank_account with Financial Connections for verified bank payment.',
    },
    {
        icon: 'verified_user',
        title: 'Ready-to-Charge Guard',
        description: 'Helmful checks stripeReadyForCharges before creating a session — onboarding alone is not enough; charges must be enabled on the connected account.',
    },
    {
        icon: 'hub',
        title: 'Webhooks & Reconciliation',
        description: 'Stripe Connect webhooks handle checkout.session.completed and account.updated so payment status and Connect capability flags stay in sync.',
    },
    {
        icon: 'swap_horiz',
        title: 'Provider-Agnostic Design',
        description: 'PaymentConfiguration supports stripe and quickbooks processors so tenants can choose how customer payments are collected without rewriting invoice logic.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Connect from Account → Payments',
        body: 'StripeController creates or resumes an Express account, requests card_payments, transfers, and us_bank_account_ach_payments capabilities, then redirects through Stripe Account Link onboarding.',
    },
    {
        step: '02',
        title: 'Checkout Session on the Connected Account',
        body: 'StripeService::createInvoiceCheckoutSession builds a one-time Checkout on the tenant stripe_account_id with invoice metadata, optional us_bank_account options, and success/cancel URLs on your domain.',
    },
    {
        step: '03',
        title: 'InvoicePayOnline Gates the Button',
        body: 'InvoicePayOnline verifies the invoice is open, has balance due, is not QuickBooks-managed, and that at least one Stripe method is enabled on the account and allowed on the invoice.',
    },
    {
        step: '04',
        title: 'Customer Completes Payment',
        body: 'The customer pays on Stripe-hosted Checkout. Session metadata carries invoice id, uuid, principal, and surcharge for fulfillment when the webhook fires.',
    },
    {
        step: '05',
        title: 'Webhook Updates Helmful',
        body: 'StripeConnectWebhookHandler processes completed sessions and account updates so payments post to the tenant ledger and Connect status reflects what Stripe reports.',
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
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Connect Stripe Express, turn on the payment methods you accept,
                        and let customers pay open invoices online — funds flow to your dealership, not a shared platform wallet.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Connect</div>
                            <div class="mt-1 text-sm text-gray-400">Your Stripe Express</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Card</div>
                            <div class="mt-1 text-sm text-gray-400">+ ACH when enabled</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Webhook</div>
                            <div class="mt-1 text-sm text-gray-400">Confirmed payments</div>
                        </div>
                    </div>
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
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">How it's structured</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Connect, configure, collect
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Tenant customer payments are separate from Helmful subscription billing.
                            Your Connect account is the destination for invoice checkout.
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

            <section class="bg-secondary-50 px-6 py-24 dark:bg-secondary-950/25 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-secondary-700 dark:text-secondary-400">What it does</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            From open invoice to paid
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Give customers a pay-now link, support the methods your store accepts,
                            and keep payment records inside Helmful.
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

            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">Under the hood</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Checkout on the connected account
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Stripe handles PCI-sensitive card and bank flows.
                            Helmful ties each session back to the invoice and payment ledger.
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
