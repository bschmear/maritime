<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import QualificationForm from '@/Components/Tenant/QualificationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'qualifications' },
    recordTitle: { type: String, default: 'Qualification' },
    domainName: { type: String, default: 'Qualification' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
});

const qualificationLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Qualification #${props.record?.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Qualifications', href: route('qualifications.index') },
    { label: qualificationLabel.value, href: route('qualifications.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancelled = () => {
    router.visit(route('qualifications.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${qualificationLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ qualificationLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6 mx-auto">
            <QualificationForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :initial-data="initialData"
                :image-urls="imageUrls"
                mode="edit"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
