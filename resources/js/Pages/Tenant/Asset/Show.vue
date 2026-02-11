<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String
    },
    recordTitle: {
        type: String
    },
    pluralTitle: {
        type: String
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
        :show-sublists="true"
        :breadcrumb-parent-label="'Asset'"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
        :account="account"
        :timezones="timezones"
    />
</template>
