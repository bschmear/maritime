<script setup>
// Layout patterns match Tenant/Asset/Show.vue (breadcrumb, title, badges, spacing).
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    asset: { type: Object, required: true },
    variant: { type: Object, required: true },
    specRows: { type: Array, default: () => [] },
});

const variantLabel = computed(
    () => props.variant?.display_name || props.variant?.name || `Variant #${props.variant?.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: props.asset?.display_name || 'Asset', href: route('assets.show', props.asset.id) },
    { label: variantLabel.value },
]);
</script>

<template>
    <Head :title="`${variantLabel} — ${asset.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ variantLabel }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ asset.display_name }}
                            <span v-if="asset.make_display_name || asset.year" class="text-gray-400">
                                ·
                                <template v-if="asset.make_display_name">{{ asset.make_display_name }}</template>
                                <template v-if="asset.year">
                                    {{ asset.make_display_name ? ' ' : '' }}{{ asset.year }}
                                </template>
                            </span>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium"
                                :class="variant?.inactive
                                    ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
                                    : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'"
                            >
                                {{ variant?.inactive ? 'Inactive' : 'Active' }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                Variant
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('assets.index')">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-base">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link :href="route('assets.show', asset.id)">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-base">inventory_2</span>
                                Parent asset
                            </button>
                        </Link>
                        <a
                            href="#specifications"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-base">table_chart</span>
                            Specifications
                        </a>
                        <Link :href="route('assets.edit', asset.id)">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            >
                                <span class="material-icons text-base">edit</span>
                                Edit asset
                            </button>
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <!-- Parent asset (compact summary — same card language as asset form sections) -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Parent asset</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        This variant belongs to the following asset record.
                    </p>
                </div>
                <div class="px-6 py-4">
                    <Link
                        :href="route('assets.show', asset.id)"
                        class="text-base font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        {{ asset.display_name }}
                    </Link>
                    <p v-if="asset.make_display_name || asset.year" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="asset.make_display_name">{{ asset.make_display_name }}</template>
                        <template v-if="asset.year">{{ asset.make_display_name ? ' · ' : '' }}{{ asset.year }}</template>
                    </p>
                </div>
            </div>

            <!-- Variant identity & pricing — mirrors read-only blocks on asset show -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Variant</h3>
                </div>
                <div class="px-6 py-6">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Display name
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ variant.display_name || variant.name || '—' }}
                            </dd>
                        </div>
                        <div v-if="variant.default_cost != null || variant.default_price != null">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Default pricing
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <span v-if="variant.default_cost != null">
                                    Cost {{ `$${Number(variant.default_cost).toLocaleString('en-US', { minimumFractionDigits: 2 })}` }}
                                </span>
                                <span v-if="variant.default_cost != null && variant.default_price != null"> · </span>
                                <span v-if="variant.default_price != null">
                                    Price {{ `$${Number(variant.default_price).toLocaleString('en-US', { minimumFractionDigits: 2 })}` }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="variant.length != null || variant.width != null" class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Dimensions
                            </dt>
                            <dd class="mt-1 flex flex-wrap gap-4 text-sm text-gray-900 dark:text-gray-100">
                                <span v-if="variant.length != null">
                                    Length:
                                    {{ specRows.find((r) => r.label === 'Length')?.value ?? '—' }}
                                </span>
                                <span v-if="variant.width != null">
                                    Width:
                                    {{ specRows.find((r) => r.label === 'Width')?.value ?? '—' }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <div v-if="variant.resolved_description" class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-700">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Description
                        </h4>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">
                            {{ variant.resolved_description }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                id="specifications"
                class="scroll-mt-24 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40"
            >
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Specifications</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        All specification values for this variant (same definitions as on the asset).
                    </p>
                </div>
                <div class="px-6 py-6">
                    <table class="w-full text-sm">
                            <thead class="border-b-2 border-gray-900 bg-white dark:border-gray-600 dark:bg-gray-900">
                                <tr>
                                    <th class="py-3 pr-4 text-left font-semibold text-gray-900 dark:text-white">
                                        Specification
                                    </th>
                                    <th class="py-3 pl-4 text-right font-semibold text-gray-900 dark:text-white">
                                        Value
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(row, idx) in specRows" :key="idx">
                                    <td class="py-2.5 pr-4 text-gray-800 dark:text-gray-200">{{ row.label }}</td>
                                    <td class="py-2.5 pl-4 text-right font-medium text-gray-900 dark:text-gray-100">
                                        {{ row.value ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            v-if="!specRows?.length"
                            class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No specification values recorded.
                        </p>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
