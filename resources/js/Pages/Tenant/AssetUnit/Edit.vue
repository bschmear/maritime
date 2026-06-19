<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetUnitForm from '@/Components/Tenant/AssetUnitForm.vue';
import FinancingSection from '@/Components/Tenant/FinancingSection.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'assetunits' },
    recordTitle: { type: String, default: 'Asset Unit' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    financingContext: { type: Object, default: null },
});

const unitLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Unit #${props.record?.id}`,
);

const unitShowUrl = computed(() => route('assetunits.show', props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Asset Units', href: route('assets.units.global-index') },
    { label: unitLabel.value, href: route('assetunits.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('assetunits.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${unitLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ unitLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <FinancingSection
                v-if="financingContext && (record?.is_financed || financingContext?.financing)"
                :context="financingContext"
                :record="record"
            />

            <AssetUnitForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record="record"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :timezones="timezones"
                mode="edit"
                :redirect-after-update="unitShowUrl"
                @cancelled="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
