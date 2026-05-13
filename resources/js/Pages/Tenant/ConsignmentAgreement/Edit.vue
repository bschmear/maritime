<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ConsignmentAgreementForm from '@/Components/Tenant/ConsignmentAgreementForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    reviewUrl: { type: String, default: null },
});

const label = computed(() => {
    const ref = props.record.display_name;
    if (ref != null && ref !== '') {
        return `#${ref}`;
    }
    return `Agreement #${props.record.id}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Consignment agreements', href: route('consignmentagreements.index') },
    { label: label.value, href: route('consignmentagreements.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('consignmentagreements.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${label}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ label }}
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-5xl flex-col space-y-6">
            <div
                v-if="reviewUrl"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <a
                    :href="reviewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-amber-950 underline hover:no-underline dark:text-amber-100"
                >
                    Open public signing page
                </a>
                <span class="text-amber-800 dark:text-amber-300"> — share this with the customer.</span>
            </div>

            <ConsignmentAgreementForm
                mode="edit"
                :record="record"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
