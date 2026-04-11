<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'contactaddresses' },
    recordTitle: { type: String, default: 'Contact address' },
    pluralTitle: { type: String, default: 'Contact addresses' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    domainName: { type: String, default: 'ContactAddress' },
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
        :show-sublists="false"
        :breadcrumb-parent-label="pluralTitle"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
    />
</template>
