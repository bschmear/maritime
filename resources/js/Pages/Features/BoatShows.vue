<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FeaturePageCta from '@/Components/Features/FeaturePageCta.vue';
import { Head, Link } from '@inertiajs/vue3';

const hierarchy = [
    {
        icon: 'festival',
        title: 'Boat Show',
        description: 'The recurring brand: Miami International Boat Show, Fort Lauderdale, your regional expo. Logo, website, and description live here once and carry across every year.',
    },
    {
        icon: 'calendar_month',
        title: 'Event',
        description: 'A specific year and location with dates, venue, booth number, and everything tied to that appearance.',
    },
    {
        icon: 'directions_boat',
        title: 'Event Assets',
        description: 'Boats, engines, and trailers you are bringing, linked from inventory with dimensions used for layout and public showcase.',
    },
];

const capabilities = [
    {
        icon: 'grid_on',
        title: 'Floor Plan Layout Builder',
        description: 'Drag boats onto a canvas measured in real feet. Positions snap to the grid, rotation supports 90 degree increments, and dimensions are snapshotted so layout stays accurate even if inventory changes later.',
    },
    {
        icon: 'qr_code_2',
        title: 'Public Showcase and QR Lead Capture',
        description: 'Each event gets a public page listing your on-display inventory. Visitors scan a QR code to open a mobile lead form with no app install required.',
    },
    {
        icon: 'person_add',
        title: 'Leads Wired into Your System',
        description: 'Submissions create a real lead assigned to your team, tagged with the event as source, scored automatically, and linked back to the show via a dedicated boat show lead record.',
    },
    {
        icon: 'mail',
        title: 'Automated Follow-up Emails',
        description: 'Turn on auto follow-up per event with a configurable delay of minutes, hours, or days. Merge tokens pull in lead and asset details from the submission.',
    },
    {
        icon: 'checklist',
        title: 'Event Checklists and Tasks',
        description: 'Attach preparation checklists and tasks to an event so your team knows what is done before doors open.',
    },
    {
        icon: 'print',
        title: 'Print-Ready Flyers',
        description: 'Staff can print a branded flyer with event details and the same QR code used on the public showcase, ready for the booth wall.',
    },
];

const behindTheScenes = [
    {
        step: '01',
        title: 'Show and Event Structure',
        body: 'BoatShow holds the long-lived identity including name, slug, and logo. BoatShowEvent is the dated occurrence with venue, coordinates, booth, and settings like auto follow-up delay and email template.',
    },
    {
        step: '02',
        title: 'Inventory on the Floor',
        body: 'EventAssetsPayload groups linked inventory into boats, engines, and trailers. Length and width come from variant or asset specs with millimeters converted to feet, and can be overridden per event for layout accuracy.',
    },
    {
        step: '03',
        title: 'Layout Sync',
        body: 'The layout builder persists positions via a bulk sync endpoint. Each placed boat stores its own name, length, width, x/y, rotation, and color as a snapshot independent of live inventory records.',
    },
    {
        step: '04',
        title: 'Visitor Submits the Lead Form',
        body: 'SubmitBoatShowEventLead validates the payload, ensures selected asset IDs belong to the event, creates a Lead with Source::BoatShow and campaign metadata, records a BoatShowLead polymorphic to the lead, and applies an auto-generated score breakdown.',
    },
    {
        step: '05',
        title: 'Follow-up Queues in the Background',
        body: 'If auto_followup is enabled and the lead has an email, BoatShowFollowUpScheduler dispatches a delayed job. The job merges your email template with lead and asset context and sends from your dealership account.',
    },
];
</script>

<template>
    <Head title="Boat Shows and Events" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">

            <!-- Hero -->
            <section class="relative overflow-hidden bg-gray-950 px-6 pb-12 lg:pb-36 pt-24 sm:px-12 lg:px-24">

                <!-- Subtle grid background -->
                <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style="background-image: linear-gradient(to right, #60a5fa 1px, transparent 1px), linear-gradient(to bottom, #60a5fa 1px, transparent 1px); background-size: 48px 48px;">
                </div>

                <!-- Glow -->
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
                            <span class="material-icons text-sm leading-none text-primary-400">sailing</span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-primary-400">Boat Shows &amp; Events</span>
                        </div>
                    </div>

                    <h1 class="mb-6 max-w-4xl text-5xl font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Run boat shows like a system,<br>
                        <span class="text-primary-400">not a spreadsheet.</span>
                    </h1>
                    <p class="mb-16 max-w-2xl text-lg leading-relaxed text-gray-400">
                        Plan your booth layout in feet, publish inventory to visitors, capture leads from QR codes,
                        and follow up automatically. All connected to the same leads and inventory your team uses every day.
                    </p>

                    <!-- Stats strip -->
                    <div class="grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/5 bg-white/5 sm:grid-cols-3">
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">QR</div>
                            <div class="mt-1 text-sm text-gray-400">Instant lead capture</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">1:1</div>
                            <div class="mt-1 text-sm text-gray-400">Synced to your system</div>
                        </div>
                        <div class="bg-gray-950 px-8 py-6">
                            <div class="text-3xl font-bold text-white">ft²</div>
                            <div class="mt-1 text-sm text-gray-400">Real floor dimensions</div>
                        </div>
                    </div>
                </div>

                <!-- Wave divider -->
                <div class="absolute bottom-0 left-0 right-0 leading-none">
                    <svg viewBox="0 0 1440 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="h-16 w-full">
                        <path
                            class="fill-gray-50 dark:fill-gray-900"
                            d="M0,32 C180,64 360,0 540,32 C720,64 900,0 1080,32 C1260,64 1350,16 1440,32 L1440,64 L0,64 Z"
                        />
                    </svg>
                </div>
            </section>





            <!-- Data model -->
            <section class="px-6 py-24 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-14 max-w-xl">
                        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">How it's structured</p>
                        <h2 class="mb-4 text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Shows, events, and what you bring
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Helmful separates the recurring show brand from each year's appearance,
                            then links the inventory you're displaying to that specific event.
                        </p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-3">
                        <div
                            v-for="(item, i) in hierarchy"
                            :key="item.title"
                            class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-gray-700/60 dark:bg-gray-800/80"
                        >
                            <div class="absolute right-5 top-5 text-5xl font-black text-gray-100 dark:text-gray-700/60 select-none">
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
                            From planning to follow-up
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Every phase of a boat show, handled in one place.
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
                            What happens when a visitor scans your QR code
                        </h2>
                        <p class="text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            The public form is simple on purpose. Under the hood, Helmful ties every submission
                            back to your system with scoring, source tracking, and optional automated outreach.
                        </p>
                    </div>

                    <div class="relative">
                        <!-- Connecting line -->
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
                badge="Built for marine retail"
                title="Boat shows are a core part of marine retail"
                description="Helmful is built for the seasonality and event-driven sales that define the industry."
                primary-label="Request a demo"
                primary-route="contact"
                secondary-label="Explore all features"
                secondary-route="features"
            />
        </div>
    </AppLayout>
</template>