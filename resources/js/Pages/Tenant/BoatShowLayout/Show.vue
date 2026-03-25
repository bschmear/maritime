<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Boat Show Layout' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    domainName: { type: String, default: 'BoatShowLayout' },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const indexHref = computed(() =>
    Object.keys(props.extraRouteParams).length
        ? route('boat-shows.layouts.index', props.extraRouteParams)
        : route('boat-show-layouts.index')
);

const parentLabel = computed(() =>
    Object.keys(props.extraRouteParams).length ? 'Layouts (show)' : 'Boat Show Layouts'
);
</script>

<template>
    <ShowRecord
        :record="record"
        :record-type="recordType"
        :record-title="recordTitle"
        :form-schema="formSchema"
        :fields-schema="fieldsSchema"
        :enum-options="enumOptions"
        :domain-name="domainName"
        :image-urls="imageUrls"
        :account="account"
        :timezones="timezones"
        :extra-route-params="extraRouteParams"
        :show-sublists="false"
        display-name-field="name"
        :breadcrumb-parent-label="parentLabel"
        :breadcrumb-parent-href="indexHref"
    />
</template>
