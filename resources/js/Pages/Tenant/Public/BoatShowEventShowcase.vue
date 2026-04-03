<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    event: { type: Object, required: true },
    assets: {
        type: Object,
        default: () => ({ boats: [], engines: [], trailers: [] }),
    },
    account: { type: Object, default: null },
    logoUrl: { type: String, default: null },
    leadUrl: { type: String, required: true },
    leadQrDataUri: { type: String, required: true },
});

const allRows = computed(() => {
    const { boats = [], engines = [], trailers = [] } = props.assets;
    return [...boats, ...engines, ...trailers];
});

const formatDate = (d) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
</script>

<template>
    <Head :title="event.display_name ?? 'Boat show'" />
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-4xl flex-col items-center gap-4 px-4 py-8 sm:flex-row sm:justify-between">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt=""
                    class="max-h-16 w-auto object-contain"
                />
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
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-10">
            <section class="mb-10 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Get in touch</h2>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    Scan the QR code or use the link to tell us what you are interested in.
                </p>
                <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                    <div class="rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600">
                        <img :src="leadQrDataUri" alt="QR code to lead form" class="h-48 w-48" width="192" height="192" />
                    </div>
                    <div class="flex flex-col gap-3">
                        <a
                            :href="leadUrl"
                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Open lead form
                        </a>
                        <p class="max-w-md break-all text-xs text-gray-500 dark:text-gray-400">{{ leadUrl }}</p>
                    </div>
                </div>
            </section>

            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">On display</h2>
            <ul v-if="allRows.length" class="space-y-6">
                <li
                    v-for="row in allRows"
                    :key="row.event_asset_id ?? row.id"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex flex-col sm:flex-row">
                        <div class="aspect-video w-full shrink-0 bg-gray-100 sm:max-w-xs dark:bg-gray-700">
                            <img
                                v-if="row.primary_image_url"
                                :src="row.primary_image_url"
                                :alt="row.display_name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full min-h-[160px] items-center justify-center text-gray-400 dark:text-gray-500"
                            >
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
                            <p v-if="row.horsepower" class="text-sm text-gray-500 dark:text-gray-400">
                                {{ row.horsepower }} HP
                            </p>
                            <p v-if="row.trailer_type" class="text-sm text-gray-500 dark:text-gray-400">
                                {{ row.trailer_type }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="text-gray-500 dark:text-gray-400">No assets listed for this event yet.</p>
        </main>
    </div>
</template>
