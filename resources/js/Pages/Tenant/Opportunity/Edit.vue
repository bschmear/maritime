<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import OpportunityForm from '@/Components/Tenant/OpportunityForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'opportunities' },
    recordTitle: { type: String, default: 'Opportunity' },
    domainName: { type: String, default: 'Opportunity' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
});

const opportunityLabel = computed(() =>
    props.record?.sequence ? `OPP-${props.record.sequence}` : `Opportunity #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Opportunities', href: route('opportunities.index') },
    { label: opportunityLabel.value, href: route('opportunities.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancelled = () => {
    router.visit(route('opportunities.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${opportunityLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ opportunityLabel }}
                    </h2>
                </div>
            </div>
        </template>

        <OpportunityForm
            :record="record"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :initial-data="initialData"
            :account="account"
            :timezones="timezones"
            mode="edit"
            :from-qualification="false"
            @cancelled="handleCancelled"
        />
    </TenantLayout>
</template>
