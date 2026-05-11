<script setup>
import AssetCatalogOptionsSection from '@/Components/Tenant/AssetCatalogOptionsSection.vue';
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';
import { computed } from 'vue';

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
    catalogResolvedOptions: {
        type: Array,
        default: () => [],
    },
    catalogContext: {
        type: Object,
        default: null,
    },
});

/** Only show catalog options when the parent asset has no variants (options live on variant show otherwise). */
const showAssetCatalogOptions = computed(() => {
    if (!props.catalogContext) {
        return false;
    }
    const hv = props.record?.asset?.has_variants;
    if (hv === true || hv === 1 || hv === '1') {
        return false;
    }

    return true;
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
        :breadcrumb-parent-label="'Asset Unit'"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
    >
        <template v-if="showAssetCatalogOptions" #prepend>
            <AssetCatalogOptionsSection
                class="mb-4 xl:mb-6"
                :resolved-options="catalogResolvedOptions"
                :catalog-context="catalogContext"
            />
        </template>
    </ShowRecord>
</template>
