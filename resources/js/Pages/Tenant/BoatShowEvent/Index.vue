<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'boat-show-events' },
    recordTitle: { type: String, default: 'Boat Show Event' },
    pluralTitle: { type: String, default: 'Boat Show Events' },
    extraRouteParams: { type: Object, default: () => ({}) },
    initialCreateData: { type: Object, default: () => ({}) },
    parentBoatShow: { type: Object, default: null },
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];
    items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
    if (props.parentBoatShow) {
        items.push({
            label: props.parentBoatShow.name,
            href: route('boat-shows.show', props.parentBoatShow.routeKey),
        });
    }
    items.push({ label: props.pluralTitle });
    return items;
});
</script>

<template>
    <Head :title="pluralTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
            :extra-route-params="extraRouteParams"
            :initial-create-data="initialCreateData"
        />
    </TenantLayout>
</template>
