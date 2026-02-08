<script setup>
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'inventoryitems',
    },
    recordTitle: {
        type: String,
        default: 'Inventory Items',
    },
    pluralTitle: {
        type: String,
        default: 'Inventory',
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
        :breadcrumb-parent-label="'Inventory'"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
    />
</template>
