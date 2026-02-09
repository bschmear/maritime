<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import WorkOrderForm from '@/Components/Tenant/WorkOrderForm.vue';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true
    },
    recordType: {
        type: String,
        required: true
    },
    formSchema: {
        type: Object,
        required: true
    },
    fieldsSchema: {
        type: Object,
        required: true
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    }
});

const pluralTitle = computed(() => {
    // Convert WorkOrder to Work Orders
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: pluralTitle.value, href: route('workorders.index') },
        { label: props.record.display_name || `WO #${props.record.work_order_number}`, href: route('workorders.show', props.record.id) },
        { label: 'Edit' },
    ];
});

const handleFormCancelled = () => {
    window.location.href = route('workorders.show', props.record.id);
};
</script>

<template>
    <Head :title="`Edit ${record.display_name || `WO #${record.work_order_number}`}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Edit {{ pluralTitle }}: {{ record.display_name || `WO #${record.work_order_number}` }}
                    </h2>
                </div>
            </div>
        </template>

        <WorkOrderForm
            :record="record"
            :record-type="recordType"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            mode="edit"
            @cancelled="handleFormCancelled"
        />
    </TenantLayout>
</template>