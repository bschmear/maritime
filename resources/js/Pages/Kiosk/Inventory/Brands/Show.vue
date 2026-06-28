<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    brand: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const models = computed(() => props.brand.catalog_assets ?? []);

function formatMm(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString()} mm`;
}

function formatKg(value) {
    if (value == null || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString()} kg`;
}
</script>

<template>
    <Head :title="brand.display_name" />

    <KioskLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-x-3">
                    <Link
                        :href="route('kiosk.inventory-brands.index')"
                        class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        ←
                    </Link>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ brand.display_name }}</h1>
                        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ brand.slug }}</p>
                    </div>
                </div>
                <Link
                    :href="route('kiosk.inventory-brands.edit', brand.id)"
                    class="gradient-btn rounded-lg px-4 py-2 text-sm"
                >
                    Edit brand
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start gap-6">
                    <div class="shrink-0">
                        <img
                            v-if="brand.logo_url"
                            :src="brand.logo_url"
                            :alt="`${brand.display_name} logo`"
                            class="h-16 max-w-[12rem] object-contain"
                        />
                        <div
                            v-else
                            class="flex h-16 w-28 items-center justify-center rounded-lg border border-dashed border-gray-300 text-xs text-gray-400 dark:border-gray-600"
                        >
                            No logo
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="brand.active
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                            >
                                {{ brand.active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ brand.catalog_assets_count ?? models.length }} model(s)
                            </span>
                        </div>

                        <div v-if="brand.website_url" class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Website</span>
                            <a
                                :href="brand.website_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 block text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                {{ brand.website_url }}
                            </a>
                        </div>

                        <div v-if="brand.description" class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Description</span>
                            <p class="mt-1 whitespace-pre-line text-gray-600 dark:text-gray-400">{{ brand.description }}</p>
                        </div>

                        <div v-if="brand.boat_types?.length" class="text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Categories</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="type in brand.boat_types"
                                    :key="type.id"
                                    class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ type.display_name }}
                                    <span v-if="type.pivot?.is_primary" class="ml-1 text-primary-600 dark:text-primary-400">· primary</span>
                                </span>
                            </div>
                        </div>

                        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div v-if="brand.boat_type">
                                <dt class="font-medium text-gray-700 dark:text-gray-300">Primary boat type</dt>
                                <dd class="mt-0.5 text-gray-600 dark:text-gray-400">{{ brand.boat_type.display_name }}</dd>
                            </div>
                            <div v-if="brand.hull_type">
                                <dt class="font-medium text-gray-700 dark:text-gray-300">Hull type</dt>
                                <dd class="mt-0.5 text-gray-600 dark:text-gray-400">{{ brand.hull_type.display_name }}</dd>
                            </div>
                            <div v-if="brand.hull_material">
                                <dt class="font-medium text-gray-700 dark:text-gray-300">Hull material</dt>
                                <dd class="mt-0.5 text-gray-600 dark:text-gray-400">{{ brand.hull_material.display_name }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Models &amp; variants</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Catalog assets in the shared inventory database for this brand.
                    </p>
                </div>

                <div v-if="models.length === 0" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    No models linked to this brand yet.
                </div>

                <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div v-for="model in models" :key="model.id" class="px-6 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ model.display_name || model.model || model.slug }}
                                </h3>
                                <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ model.slug }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span
                                    v-if="model.inactive"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    Inactive
                                </span>
                                <span
                                    v-if="model.has_variants"
                                    class="rounded-full bg-primary-100 px-2 py-0.5 font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-200"
                                >
                                    {{ model.variants?.length || 0 }} variant(s)
                                </span>
                            </div>
                        </div>

                        <dl v-if="!model.variants?.length" class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Length</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ formatMm(model.length_mm) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Beam</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ formatMm(model.width_mm) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Weight</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ formatKg(model.weight_kg) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Max HP</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ model.max_hp ?? '—' }}</dd>
                            </div>
                        </dl>

                        <div v-if="model.variants?.length" class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr>
                                        <th class="py-2.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Variant
                                        </th>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Key
                                        </th>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Length
                                        </th>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Weight
                                        </th>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Max HP
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    <tr v-for="variant in model.variants" :key="variant.id">
                                        <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ variant.display_name || variant.name || variant.key }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {{ variant.key || variant.slug || '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ formatMm(variant.length_mm) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ formatKg(variant.weight_kg) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ variant.max_hp ?? '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </KioskLayout>
</template>
