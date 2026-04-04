<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicBrandingFooter from '../../../Components/Tenant/Public/PublicBrandingFooter.vue';

const props = defineProps({
    event:            { type: Object, required: true },
    asset:            { type: Object, required: true },
    account:          { type: Object, default: null },
    logoUrl:          { type: String, default: null },
    showcaseUrl:      { type: String, required: true },
    leadUrlWithAsset: { type: String, required: true },
    brandingAppName: { type: String, required: true },
    brandingAppUrl: { type: String, required: true },
    brandingTermsUrl: { type: String, default: null },
});

const gallery        = computed(() => props.asset.image_gallery ?? []);
const activeImageUrl = ref(null);

const displayImage = computed(() => {
    if (activeImageUrl.value) return activeImageUrl.value;
    if (gallery.value.length) return gallery.value[0].url;
    return props.asset.primary_image_url ?? null;
});

const setActive = (url) => { activeImageUrl.value = url; };

const specs = computed(() => props.asset.specs ?? []);

const subtitleParts = computed(() => {
    const a     = props.asset;
    const parts = [];
    if (a.make)  parts.push(a.make);
    if (a.model) parts.push(a.model);
    if (a.year)  parts.push(String(a.year));
    return parts;
});
</script>

<template>
    <Head :title="`${asset.display_name ?? 'Unit'} — ${event.display_name ?? 'Event'}`" />

    <div id="boat-show-public-print-root" class="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">

        <!-- Header -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
                <div class="flex items-center gap-4">
                    <img v-if="logoUrl" :src="logoUrl" alt="" class="max-h-10 w-auto object-contain" />
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ event.boat_show?.display_name }}
                        </p>
                        <h1 class="text-base font-semibold text-gray-900 dark:text-white leading-tight">
                            {{ event.display_name }}
                        </h1>
                    </div>
                </div>
                <a
                    :href="showcaseUrl"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors whitespace-nowrap"
                >
                    <span class="material-icons text-lg leading-none">arrow_back</span>
                    Back to event
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 space-y-0">

            <!-- Card wraps everything -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">

                <!-- Hero image -->
                <div class="relative w-full bg-gray-100 dark:bg-gray-900">
                    <div class="aspect-[16/7] w-full">
                        <img
                            v-if="displayImage"
                            :src="displayImage"
                            :alt="asset.display_name"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full min-h-64 items-center justify-center text-gray-300 dark:text-gray-700"
                        >
                            <span class="material-icons" style="font-size: 96px;">directions_boat</span>
                        </div>
                    </div>

                    <!-- Thumbnail strip overlapping the bottom of the hero -->
                    <div v-if="gallery.length > 1" class="absolute bottom-0 left-0 right-0 flex gap-2 overflow-x-auto bg-gradient-to-t from-black/50 to-transparent px-4 pb-3 pt-8">
                        <button
                            v-for="(img, idx) in gallery"
                            :key="idx"
                            type="button"
                            class="h-14 w-20 shrink-0 overflow-hidden rounded-lg ring-2 transition-all focus:outline-none"
                            :class="displayImage === img.url
                                ? 'ring-white opacity-100'
                                : 'ring-transparent opacity-70 hover:opacity-100'"
                            @click="setActive(img.url)"
                        >
                            <img :src="img.url" :alt="img.alt ?? ''" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Content below image -->
                <div class="p-6 sm:p-8 space-y-6">

                    <!-- Title + CTA row -->
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ asset.display_name }}
                            </h2>
                            <p v-if="subtitleParts.length" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ subtitleParts.join(' · ') }}
                            </p>
                        </div>

                        <a
                            :href="leadUrlWithAsset"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors"
                        >
                            <span class="material-icons text-lg leading-none">mail</span>
                            Request more info
                        </a>
                    </div>

                    <!-- Quick stats strip -->
                    <!-- <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-if="asset.length_display || asset.length_ft"
                            class="flex flex-col rounded-xl bg-gray-50 dark:bg-gray-900/60 px-4 py-3">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Length</span>
                            <span class="mt-0.5 text-base font-semibold text-gray-900 dark:text-white">
                                {{ asset.length_display ?? `${asset.length_ft} ft` }}
                            </span>
                        </div>
                        <div v-if="asset.horsepower"
                            class="flex flex-col rounded-xl bg-gray-50 dark:bg-gray-900/60 px-4 py-3">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Power</span>
                            <span class="mt-0.5 text-base font-semibold text-gray-900 dark:text-white">{{ asset.horsepower }} HP</span>
                        </div>
                        <div v-if="asset.trailer_type"
                            class="flex flex-col rounded-xl bg-gray-50 dark:bg-gray-900/60 px-4 py-3">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</span>
                            <span class="mt-0.5 text-base font-semibold text-gray-900 dark:text-white">{{ asset.trailer_type }}</span>
                        </div>
                        <div v-if="asset.asset_unit?.display_name"
                            class="flex flex-col rounded-xl bg-gray-50 dark:bg-gray-900/60 px-4 py-3">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Unit</span>
                            <span class="mt-0.5 text-base font-semibold text-gray-900 dark:text-white">{{ asset.asset_unit.display_name }}</span>
                        </div>
                    </div> -->

                    <!-- Description -->
                    <div v-if="asset.description" class="border-t border-gray-100 dark:border-gray-700 pt-6">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                            Description
                        </h3>
                        <div class="prose prose-sm max-w-none text-gray-700 dark:prose-invert dark:text-gray-300 whitespace-pre-wrap">
                            {{ asset.description }}
                        </div>
                    </div>

                    <!-- Specs grid -->
                    <div v-if="specs.length" class="border-t border-gray-100 dark:border-gray-700 pt-6">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                            Specifications
                        </h3>
                        <dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="(row, idx) in specs"
                                :key="idx"
                                class="flex flex-col rounded-xl bg-gray-50 dark:bg-gray-900/60 px-4 py-3"
                            >
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ row.value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Bottom CTA -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
                        <a
                            :href="leadUrlWithAsset"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-4 text-base font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors sm:w-auto sm:min-w-64"
                        >
                            <span class="material-icons text-xl leading-none">mail</span>
                            Request more info about this unit
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <PublicBrandingFooter
            :app-name="brandingAppName"
            :app-url="brandingAppUrl"
            :terms-url="brandingTermsUrl"
        />
    </div>
</template>