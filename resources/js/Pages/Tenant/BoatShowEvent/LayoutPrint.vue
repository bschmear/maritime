<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutPrintSheet from '@/Components/Tenant/LayoutPrintSheet.vue';

defineProps({
    record: { type: Object, required: true },
    assets: { type: Object, default: () => ({ boats: [], engines: [], trailers: [] }) },
    layoutSpace: { type: Object, default: () => ({}) },
    backUrl: { type: String, required: true },
    companyName: { type: String, default: '' },
});
</script>

<template>
    <Head :title="`Print layout — ${record.display_name ?? 'Event'}`" />

    <LayoutPrintSheet
        :event="record"
        :assets="assets"
        :layout-space="layoutSpace"
        :company-name="companyName"
    >
        <template #back>
            <Link
                :href="backUrl"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-800"
            >
                <span class="material-icons text-lg leading-none">arrow_back</span>
                Back to event
            </Link>
        </template>
    </LayoutPrintSheet>
</template>
