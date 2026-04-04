<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PublicBrandingFooter from '../../../Components/Tenant/Public/PublicBrandingFooter.vue';

const props = defineProps({
    event:         { type: Object, required: true },
    assets:        { type: Object, default: () => ({ boats: [], engines: [], trailers: [] }) },
    account:       { type: Object, default: null },
    logoUrl:       { type: String, default: null },
    leadUrl:       { type: String, required: true },
    leadQrDataUri: { type: String, required: true },
    brandingAppName: { type: String, required: true },
    brandingAppUrl: { type: String, required: true },
    brandingTermsUrl: { type: String, default: null },
});

const page      = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);

const allRows = computed(() => {
    const { boats = [], engines = [], trailers = [] } = props.assets;
    return [...boats, ...engines, ...trailers];
});

const formatDate = (d) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
</script>

<template>
    <Head :title="event.display_name ?? 'Boat show'" />

    <div id="boat-show-public-print-root" class="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">

        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-4xl flex-col items-center gap-4 px-4 py-8 sm:flex-row sm:justify-between">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="max-h-16 w-auto object-contain" />
                <div class="flex flex-col items-center gap-2 sm:items-end">
                    <div class="text-center sm:text-right">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ event.display_name }}
                        </h1>
                        <p v-if="event.boat_show?.display_name" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ event.boat_show.display_name }}
                        </p>
                        <p v-if="event.venue || event.city" class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ [event.venue, [event.city, event.state].filter(Boolean).join(', ')].filter(Boolean).join(' · ') }}
                        </p>
                        <p v-if="event.starts_at || event.ends_at" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ formatDate(event.starts_at) }}
                            <template v-if="event.ends_at && event.ends_at !== event.starts_at">
                                – {{ formatDate(event.ends_at) }}
                            </template>
                        </p>
                    </div>

                    <!-- Print flyer — staff only -->
                    <a
                        v-if="isLoggedIn"
                        :href="route('boat-show-events.public.print', { uuid: event.uuid })"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        <span class="material-icons text-base leading-none">print</span>
                        Print flyer
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-4 py-10 space-y-10">

            <!-- ── QR / Get in touch ───────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="flex flex-col items-center gap-6 p-8 sm:flex-row sm:items-center">

                    <!-- QR — main focus -->
                    <div class="flex-shrink-0">
                        <div class="rounded-2xl border-4 border-blue-600 bg-white p-2 shadow-lg">
                            <img
                                :src="leadQrDataUri"
                                alt="Scan to open the lead form"
                                class="h-44 w-44 block"
                                width="176"
                                height="176"
                            />
                        </div>
                        <p class="mt-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">
                            Scan to get started
                        </p>
                    </div>

                    <!-- Text + button -->
                    <div class="text-center sm:text-left space-y-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Interested in something?
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300 max-w-xs">
                            Scan the QR code with your phone camera or tap the button below to tell us what caught your eye — we'll follow up with more details.
                        </p>
                        <a
                            :href="leadUrl"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm"
                        >
                            <span class="material-icons text-base leading-none">mail</span>
                            Open lead form
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── On display ─────────────────────────────────────────────── -->
            <div>
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">On display</h2>
                <ul v-if="allRows.length" class="space-y-4">
                    <li
                        v-for="row in allRows"
                        :key="row.event_asset_id ?? row.id"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm transition-shadow hover:shadow-md"
                    >
                        <a
                            :href="route('boat-show-events.public.asset', { uuid: event.uuid, asset: row.id })"
                            class="flex flex-col sm:flex-row focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                        >
                            <div class="aspect-video w-full shrink-0 bg-gray-100 dark:bg-gray-700 sm:max-w-xs">
                                <img
                                    v-if="row.primary_image_url"
                                    :src="row.primary_image_url"
                                    :alt="row.display_name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full min-h-40 items-center justify-center text-gray-400 dark:text-gray-500">
                                    <span class="material-icons text-5xl">directions_boat</span>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col justify-center p-5">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ row.display_name }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <template v-if="row.make">{{ row.make }}</template>
                                    <template v-if="row.model"> {{ row.model }}</template>
                                    <template v-if="row.year"> · {{ row.year }}</template>
                                </p>
                                <p v-if="row.length_display || row.length_ft" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="row.length_display">Length: {{ row.length_display }}</span>
                                    <span v-else-if="row.length_ft">Length: {{ row.length_ft }} ft</span>
                                </p>
                                <p v-if="row.horsepower" class="text-sm text-gray-500 dark:text-gray-400">{{ row.horsepower }} HP</p>
                                <p v-if="row.trailer_type" class="text-sm text-gray-500 dark:text-gray-400">{{ row.trailer_type }}</p>
                                <p class="mt-3 text-sm font-medium text-blue-600 dark:text-blue-400">View details →</p>
                            </div>
                        </a>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">No assets listed for this event yet.</p>
            </div>
        </main>

        <PublicBrandingFooter
            :app-name="brandingAppName"
            :app-url="brandingAppUrl"
            :terms-url="brandingTermsUrl"
        />
    </div>
</template>

<style>
@media print {
    header,
    .no-print {
        display: none !important;
    }
    body {
        background: white !important;
    }
}
</style>