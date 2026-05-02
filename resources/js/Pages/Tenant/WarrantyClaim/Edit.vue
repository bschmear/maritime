<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WarrantyClaimForm from '@/Components/Tenant/WarrantyClaimForm.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'warrantyclaims' },
    recordTitle: { type: String, default: 'Warranty claim' },
    domainName: { type: String, default: 'WarrantyClaim' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
});

const claimLabel = computed(() => {
    const r = props.record;
    return r.display_name || (r.sequence != null ? `WCL-${r.sequence}` : null) || `Claim #${r.id}`;
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Warranty claims', href: route('warrantyclaims.index') },
    { label: claimLabel.value, href: route('warrantyclaims.show', props.record.id) },
    { label: 'Edit' },
]);

const handleCancel = () => {
    router.visit(route('warrantyclaims.show', props.record.id));
};

const isSublistVisible = (sub) => {
    if (!sub?.conditional || typeof sub.conditional !== 'object') {
        return true;
    }
    const { key, value, operator = 'equals' } = sub.conditional;
    const current = props.record[key];
    const boolCurrent = current === true || current === 1;
    switch (operator) {
        case 'equals':
        case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
        case 'not_equals':
        case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : current != value;
        default:
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
    }
};

const visibleSublists = computed(() => (props.formSchema?.sublists || []).filter(isSublistVisible));
</script>

<template>
    <Head :title="`Edit ${claimLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ claimLabel }}
                </h2>
            </div>
        </template>

        <WarrantyClaimForm
            :record="record"
            mode="edit"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            @cancel="handleCancel"
        />
        <div
            v-if="visibleSublists.length > 0 && formSchema"
            class="mx-auto mt-8 w-full "
        >
            <Sublist
                :key="`warranty-claim-edit-sublist-${record?.id || 'new'}`"
                :parent-record="record"
                parent-domain="WarrantyClaim"
                :sublists="visibleSublists"
            />
        </div>
    </TenantLayout>
</template>
