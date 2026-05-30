<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'vpn_key',
        title: 'OAuth Connection',
        description: 'Connect your Mailchimp account once from Integrations. Tokens and server prefix are stored on the tenant integration record so API calls run under your audience, not a shared list.',
    },
    {
        icon: 'groups',
        title: 'Audiences & Segments',
        description: 'Browse Mailchimp lists, create new audiences, and work with static segments. Target a whole list or a segment when pushing or pulling contacts.',
    },
    {
        icon: 'sync_alt',
        title: 'Contacts & Leads',
        description: 'Push Helmful contacts or leads into Mailchimp in bulk, or pull subscribers back into Helmful as contacts or leads. Jobs run in the background for large exports.',
    },
];

const capabilities = [
    {
        icon: 'upload',
        title: 'Push to Mailchimp',
        description: 'Export all, selected, or filtered contacts and leads to a list or segment. PushContactsToMailchimp batches members through the Marketing API.',
    },
    {
        icon: 'download',
        title: 'Pull from Mailchimp',
        description: 'Import list members into Helmful as contacts or leads. PullContactsFromMailchimp queues the job so large audiences do not block the UI.',
    },
    {
        icon: 'playlist_add',
        title: 'Create Lists & Segments',
        description: 'Create a new Mailchimp audience or static segment without leaving Helmful when you are setting up a campaign audience.',
    },
    {
        icon: 'filter_list',
        title: 'Scoped Exports',
        description: 'When pushing, choose all records, a manual selection, or filtered sets by status, source, priority, and type so marketing lists stay intentional.',
    },
    {
        icon: 'campaign',
        title: 'Marketing Campaigns',
        description: 'Keep your email platform in Mailchimp while Helmful remains the source of truth for who bought, who serviced, and who is still a lead.',
    },
    {
        icon: 'history',
        title: 'Sync Status',
        description: 'Integration screen shows connection state, last sync time, and sync status so admins know when a push or pull finished.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'OAuth via Central Callback',
        body: 'MailchimpController redirects through MailchimpOAuthService with a configured redirect URI. On success, the integration row stores access_token and metadata including the datacenter prefix for API hostnames.',
    },
    {
        step: '02',
        title: 'List & Segment API',
        body: 'Authenticated requests use the Mailchimp Marketing API client. Staff can list audiences, create lists, list segments, and create static segments from the integration UI.',
    },
    {
        step: '03',
        title: 'Push Job Builds Members',
        body: 'PushContactsToMailchimp resolves contacts or leads by scope, maps email and merge fields, and submits batch operations to the selected list or segment id.',
    },
    {
        step: '04',
        title: 'Pull Job Creates Records',
        body: 'PullContactsFromMailchimp reads list members (optionally filtered by segment) and creates Contact or Lead records in Helmful, logging warnings when a row cannot be imported.',
    },
    {
        step: '05',
        title: 'Token Refresh on Use',
        body: 'MailchimpOAuthService refreshes tokens when needed so long-running jobs continue to authenticate without forcing staff to reconnect for every export.',
    },
];
</script>

<template>
    <Head title="Mailchimp Integration" />

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
                            <span class="material-icons text-sm leading-none text-primary-400">campaign</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Mailchimp</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Email lists that stay<br>
                        <span class="text-primary-400">in sync with Helmful.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Connect Mailchimp, push contacts and leads to the right audience,
                        and pull subscribers back when you need them in your dealership system.
                    </p>

                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Push</div>
                            <div class="mt-1 text-sm text-gray-400">To lists &amp; segments</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">Pull</div>
                            <div class="mt-1 text-sm text-gray-400">Into contacts &amp; leads</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">OAuth</div>
                            <div class="mt-1 text-sm text-gray-400">Your Mailchimp account</div>
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
                            Connect once, sync both ways
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Helmful does not replace Mailchimp for sending campaigns.
                            It keeps audiences aligned with the contacts and leads you already manage.
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
                            Marketing lists without double entry
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Export the right people after a boat show or service season,
                            and import engaged subscribers when they should become leads in Helmful.
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
                            Background jobs for large audiences
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Push and pull operations queue as jobs so the UI stays responsive
                            while Mailchimp processes batch member updates.
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
                badge="Email integration"
                badge-icon="campaign"
                title="Keep Mailchimp and Helmful aligned"
                description="Connect from Integrations, choose your lists, and push or pull contacts without exporting CSVs every week."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>
