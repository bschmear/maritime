<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DeliveryLocationForm from '@/Components/Tenant/DeliveryLocationForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Delivery Locations', href: route('delivery-locations.index') },
    { label: props.record.display_name ?? props.record.name, href: route('delivery-locations.show', props.record.id) },
    { label: 'Edit' },
]);
</script>

<template>
    <Head :title="`Edit ${record.display_name ?? record.name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mt-4">
                    Edit {{ record.display_name ?? record.name }}
                </h2>
            </div>
        </template>

        <DeliveryLocationForm
            :record="record"
            mode="edit"
            @cancelled="router.visit(route('delivery-locations.show', record.id))"
        />
    </TenantLayout>
</template>
