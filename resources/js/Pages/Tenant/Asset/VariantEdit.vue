<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import VariantForm from '@/Components/Tenant/VariantForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    asset: { type: Object, required: true },
    record: { type: Object, required: true },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    specsContextAssetType: { type: Number, default: null },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const variantLabel = computed(
    () => props.record?.display_name || props.record?.name || `Variant #${props.record?.id}`,
);

const variantShowUrl = computed(() =>
    route('assets.variants.show', {
        asset: props.asset.id,
        variant: props.record.id,
    }),
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: props.asset?.display_name || 'Asset', href: route('assets.show', props.asset.id) },
    { label: variantLabel.value, href: variantShowUrl.value },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(variantShowUrl.value);
};

const handleUpdated = () => {
    router.visit(variantShowUrl.value);
};
</script>

<template>
    <Head :title="`Edit ${variantLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ variantLabel }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ asset.display_name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <VariantForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record="record"
                :asset-id="asset.id"
                :enum-options="enumOptions"
                :available-specs="availableSpecs"
                :specs-context-asset-type="specsContextAssetType"
                mode="edit"
                prevent-redirect
                @updated="handleUpdated"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
