<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'addons',
    },
    recordTitle: {
        type: String,
        default: 'Add-On',
    },
    pluralTitle: {
        type: String,
        default: 'Add-Ons',
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    domainName: {
        type: String,
        required: true,
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    imageUrls: {
        type: Object,
        default: () => ({}),
    },
    availableSpecs: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <ShowRecord
        :record="record"
        :record-type="recordType"
        :record-title="recordTitle"
        :plural-title="pluralTitle"
        :form-schema="formSchema"
        :fields-schema="fieldsSchema"
        :enum-options="enumOptions"
        :domain-name="domainName"
        :account="account"
        :timezones="timezones"
        :image-urls="imageUrls"
        :available-specs="availableSpecs"
        :show-sublists="false"
        breadcrumb-parent-label="Add-Ons"
        :breadcrumb-parent-href="route('addons.index')"
    />
</template>
