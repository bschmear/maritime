<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'subsidiaries',
    },
    recordTitle: {
        type: String,
        default: 'Subsidiaries',
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
        default: 'Subsidiary',
    },
});
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
        :show-sublists="true"
        :breadcrumb-parent-label="'Subsidiaries'"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
    />
</template>

