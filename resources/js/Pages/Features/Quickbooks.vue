<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'vpn_key',
        title: 'QuickBooks Online Connection',
        description: 'OAuth through Intuit links your company (realm). Tokens, company name, and sync toggles live on the tenant integration record under Integrations → QuickBooks.',
    },
    {
        icon: 'people',
        title: 'Customers & Contacts',
        description: 'Optionally sync QuickBooks customers into Helmful contacts and leads. Contacts can store quickbooks_customer_id for matching on later invoice pushes.',
    },
    {
        icon: 'receipt',
        title: 'Invoices & Payments',
        description: 'Push Helmful invoices to QuickBooks when sync is enabled, and pull payments back onto open invoices so accounting and operations share the same balances.',
    },
];

const capabilities = [
    {
        icon: 'toggle_on',
        title: 'Configurable Sync Toggles',
        description: 'Turn on sync for contacts, invoices, and payments independently. QuickBooksSettings reads these flags so each tenant controls how much flows into QBO.',
    },
    {
        icon: 'upload_file',
        title: 'Push Invoice to QuickBooks',
        description: 'From an invoice, staff can push to QuickBooks Online. PushInvoiceToQuickBooks creates or updates the QBO invoice and stores quickbooks_invoice_id and a link back.',
    },
    {
        icon: 'download',
        title: 'Pull Payments from QuickBooks',
        description: 'Pull QuickBooks payments onto a Helmful invoice so amount due reflects what was collected in accounting without manual double entry.',
    },
    {
        icon: 'import_contacts',
        title: 'Import Customers',
        description: 'PullContactsFromQuickBooks imports QBO customers as contacts or leads in the background when you need to align Helmful with your chart of customers.',
    },
    {
        icon: 'block',
        title: 'Stripe vs QuickBooks Invoices',
        description: 'Invoices managed in QuickBooks skip Helmful online card checkout — InvoicePayOnline respects isQuickbooksManaged so payment rails stay consistent.',
    },
    {
        icon: 'business',
        title: 'Company Context',
        description: 'The integration screen shows realm id, environment, legal name, and token expiry so admins know which QBO company is connected.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'OAuth Callback Stores Realm',
        body: 'QuickbooksController completes OAuth via QuickBooksOAuthService. The integration external_id holds the realm id; metadata stores company name, environment, and token expiry timestamps.',
    },
    {
        step: '02',
        title: 'Settings Saved per Tenant',
        body: 'updateSettings persists sync_contacts, sync_invoices, and sync_payments on the integration settings JSON. QuickBooksSettings::forCurrentTenant exposes these flags to jobs and UI.',
    },
    {
        step: '03',
        title: 'Push Invoice Job',
        body: 'PushInvoiceToQuickBooks maps Helmful line items and customer to a QuickBooks invoice. On success, the invoice row stores quickbooks_invoice_id and quickbooks_invoice_url for staff reference.',
    },
    {
        step: '04',
        title: 'Pull Payments on Demand',
        body: 'InvoiceController::pullQuickbooksPayments fetches QBO payments applied to the linked invoice and records them against the Helmful invoice balance.',
    },
    {
        step: '05',
        title: 'Customer Import Job',
        body: 'PullContactsFromQuickBooks walks QBO customers and creates Contact or Lead records, logging per-row failures without stopping the entire import.',
    },
];
</script>

<template>
    <Head title="QuickBooks Integration" />

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
                            <span class="material-icons text-sm leading-none text-primary-400">account_balance</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">QuickBooks Online</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Accounting sync<br>
                        <span class="text-primary-400">without leaving Helmful.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Connect QuickBooks Online, choose what syncs,
                        push invoices to your books, and pull payments back when finance records them in QBO.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">QBO</div>
                            <div class="mt-1 text-sm text-gray-400">OAuth company link</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Push</div>
                            <div class="mt-1 text-sm text-gray-400">Invoices to books</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Pull</div>
                            <div class="mt-1 text-sm text-gray-400">Payments &amp; customers</div>
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
                            Books, customers, and invoice flow
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            QuickBooks remains your system of record for accounting.
                            Helmful pushes operational invoices and pulls status you need on the floor.
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
                            You control what syncs
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Not every dealership wants full two-way sync on day one.
                            Toggle contacts, invoices, and payments to match how your office works with QBO.
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
                            Jobs and invoice actions
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Heavy lifting runs in queued jobs; day-to-day staff use push and pull
                            buttons on the invoice when accounting and operations need to align.
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
                badge="Accounting integration"
                badge-icon="account_balance"
                title="Bridge operations and QuickBooks"
                description="Connect QuickBooks Online from Integrations, choose your sync options, and keep invoices and payments aligned with your books."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
