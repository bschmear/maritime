<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicBrandingFooter from '../../../Components/Tenant/Public/PublicBrandingFooter.vue';

const props = defineProps({
    event:            { type: Object, required: true },
    assetsFlat:       { type: Array,  default: () => [] },
    logoUrl:          { type: String, default: null },
    companyName:      { type: String, required: true },
    leadUrl:          { type: String, required: true },
    leadQrDataUri:    { type: String, required: true },
    showcaseUrl:      { type: String, required: true },
    brandingAppName:  { type: String, required: true },
    brandingAppUrl:   { type: String, required: true },
    brandingTermsUrl: { type: String, default: null },
});

const includeAssetPages = ref(false);

const runPrint = () => setTimeout(() => window.print(), 120);

const formatDate = (d) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';

const venueLine = () => {
    const e = props.event;
    return [e.venue, [e.city, e.state].filter(Boolean).join(', ')].filter(Boolean).join(' · ');
};

const assetSubtitle = (row) => {
    const parts = [];
    if (row.make)  parts.push(row.make);
    if (row.model) parts.push(row.model);
    if (row.year)  parts.push(String(row.year));
    return parts.join(' · ');
};
</script>

<template>
    <Head :title="`Print — ${event.display_name ?? 'Boat show'}`" />

    <div
        id="boat-show-public-print-root"
        class="min-h-screen bg-gray-100 text-gray-900"
        :class="{ 'print-root--asset-pages': includeAssetPages }"
    >
        <!-- Screen controls -->
        <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <a
                    :href="showcaseUrl"
                    class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                    <span class="material-icons text-lg leading-none">arrow_back</span>
                    Back to event
                </a>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                    <input v-model="includeAssetPages" type="checkbox" class="rounded border-gray-300" />
                    Include a page for each unit (with QR to that unit's page)
                </label>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
                    @click="runPrint"
                >
                    <span class="material-icons text-base leading-none">print</span>
                    Print
                </button>
            </div>
            <p v-if="!includeAssetPages && assetsFlat.length" class="mx-auto max-w-3xl px-4 pb-3 text-sm text-gray-500">
                {{ assetsFlat.length }} unit{{ assetsFlat.length === 1 ? '' : 's' }} on display — enable the option above to add
                {{ assetsFlat.length === 1 ? 'its' : 'their' }} page{{ assetsFlat.length === 1 ? '' : 's' }} to this print job.
            </p>
        </div>

        <!-- Event flyer (always prints) -->
        <section class="print-event-sheet">
            <div class="print-event-inner">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="mx-auto mb-8 max-h-20 w-auto object-contain" />
                <p class="mb-2 text-center text-sm font-semibold uppercase tracking-widest text-gray-500">
                    {{ companyName }}
                </p>
                <h1 class="mb-2 text-center text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                    {{ event.display_name }}
                </h1>
                <p v-if="event.boat_show?.display_name" class="mb-6 text-center text-lg text-gray-600">
                    {{ event.boat_show.display_name }}
                </p>
                <p v-if="venueLine()" class="mb-1 text-center text-base text-gray-700">
                    {{ venueLine() }}
                </p>
                <p v-if="event.starts_at || event.ends_at" class="mb-10 text-center text-sm text-gray-500">
                    {{ formatDate(event.starts_at) }}
                    <template v-if="event.ends_at && event.ends_at !== event.starts_at">
                        – {{ formatDate(event.ends_at) }}
                    </template>
                </p>

                <div class="mx-auto flex max-w-md flex-col items-center gap-4">
                    <div class="rounded-2xl border-4 border-blue-600 bg-white p-3 shadow-sm">
                        <img
                            :src="leadQrDataUri"
                            alt="Scan for lead form"
                            class="block h-56 w-56 sm:h-64 sm:w-64"
                            width="256"
                            height="256"
                        />
                    </div>
                    <p class="text-center text-sm font-medium text-gray-600">
                        Scan to get in touch — we'll follow up about this event.
                    </p>
                </div>
            </div>
        </section>

        <!-- Per-unit pages -->
        <section
            v-for="row in assetsFlat"
            :key="row.id"
            class="asset-print-sheet"
            :class="{ 'asset-print-sheet--screen-hidden': !includeAssetPages }"
        >
            <div class="asset-print-inner">
                <!-- Event context -->
                <p class="mb-1 text-center text-sm font-semibold uppercase tracking-widest text-gray-500">
                    {{ companyName }}
                </p>
                <p class="mb-6 text-center text-sm text-gray-500">{{ event.display_name }}</p>

                <!-- Image full width -->
                <div class="mb-5 aspect-video w-full overflow-hidden rounded-xl bg-gray-200">
                    <img
                        v-if="row.primary_image_url"
                        :src="row.primary_image_url"
                        :alt="row.display_name"
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full min-h-48 items-center justify-center text-gray-400"
                    >
                        <span class="material-icons" style="font-size: 72px;">directions_boat</span>
                    </div>
                </div>

                <!-- Name + specs -->
                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-gray-900">
                        {{ row.display_name }}
                    </h2>
                    <p v-if="assetSubtitle(row)" class="mt-1 text-gray-600">
                        {{ assetSubtitle(row) }}
                    </p>
                    <div class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-gray-500">
                        <span v-if="row.length_display">Length: {{ row.length_display }}</span>
                        <span v-else-if="row.length_ft">Length: {{ row.length_ft }} ft</span>
                        <span v-if="row.horsepower">{{ row.horsepower }} HP</span>
                        <span v-if="row.trailer_type">{{ row.trailer_type }}</span>
                    </div>
                </div>

                <!-- QR -->
                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                    <div class="rounded-xl border-4 border-blue-600 bg-white p-2">
                        <img
                            v-if="row.qr_data_uri"
                            :src="row.qr_data_uri"
                            alt="Scan for this unit"
                            class="block h-44 w-44"
                            width="176"
                            height="176"
                        />
                    </div>
                    <p class="text-center text-sm font-medium text-gray-600">
                        Scan for full details on this unit
                    </p>
                </div>
            </div>
        </section>

        <div class="no-print">
            <PublicBrandingFooter
                :app-name="brandingAppName"
                :app-url="brandingAppUrl"
                :terms-url="brandingTermsUrl"
            />
        </div>
    </div>
</template>

<style scoped>
.print-event-sheet {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    background: #fff;
    box-sizing: border-box;
}

.print-event-inner {
    width: 100%;
    max-width: 42rem;
}

.asset-print-sheet {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    box-sizing: border-box;
    page-break-before: always;
    break-inside: avoid;
}

.asset-print-inner {
    width: 100%;
    max-width: 44rem;
    padding: 2rem 1.5rem;
}

@media screen {
    .asset-print-sheet--screen-hidden {
        display: none !important;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .print-event-sheet {
        min-height: auto;
    }

    .print-root--asset-pages .print-event-sheet {
        page-break-after: always;
    }

    .asset-print-sheet {
        display: none !important;
        page-break-before: always;
    }

    .print-root--asset-pages .asset-print-sheet {
        display: flex !important;
    }

    .print-root--asset-pages .asset-print-sheet--screen-hidden {
        display: flex !important;
    }

    .print-event-inner,
    .asset-print-inner {
        max-width: 100%;
    }

    body {
        background: white !important;
    }
}
</style>