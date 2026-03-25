<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const showKey = computed(() => props.record.id ?? props.record.slug);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Boat Shows', href: route('boat-shows.index') },
    { label: props.record.display_name ?? 'Boat show', href: route('boat-shows.show', showKey.value) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('boat-shows.show', showKey.value));
};
</script>

<template>
    <Head :title="`Edit ${record.display_name ?? 'boat show'}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                    Edit boat show
                </h2>
                <p
                    v-if="record.display_name"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ record.display_name }}
                </p>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record="record"
                    :enum-options="enumOptions"
                    :image-urls="imageUrls"
                    :account="account"
                    :timezones="timezones"
                    :extra-route-params="extraRouteParams"
                    :record-type="recordType"
                    record-title="Boat Show"
                    mode="edit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </TenantLayout>
</template>
