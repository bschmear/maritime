<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetForm from '@/Components/Tenant/AssetForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'assets',
    },
    recordTitle: {
        type: String,
        default: 'Asset',
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    imageUrls: {
        type: Object,
        default: () => ({}),
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    availableSpecs: {
        type: Array,
        default: () => [],
    },
    account: {
        type: Object,
        default: null,
    },
});

const assetLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Asset #${props.record?.id}`,
);

const assetShowUrl = computed(() => route('assets.show', props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: assetLabel.value, href: route('assets.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('assets.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${assetLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ assetLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <AssetForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record="record"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :image-urls="imageUrls"
                :timezones="timezones"
                :available-specs="availableSpecs"
                :account="account"
                :redirect-after-update="assetShowUrl"
                mode="edit"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
