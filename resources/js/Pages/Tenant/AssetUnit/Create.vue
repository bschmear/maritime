<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetUnitForm from '@/Components/Tenant/AssetUnitForm.vue';
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, default: 'assetunits' },
    recordTitle: { type: String, default: 'Asset Unit' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    prefill: { type: Object, default: () => ({}) },
    linkFinancingId: { type: Number, default: null },
    linkFinancing: { type: Object, default: null },
    returnUrl: { type: String, default: null },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: 'Asset Units', href: route('assets.units.global-index') },
    { label: 'New' },
]);

const handleCancel = () => {
    router.visit(route('assetunits.index'));
};

const handleCreated = async (recordId) => {
    if (props.linkFinancingId && recordId) {
        try {
            await axios.post(route('financings.link-asset-unit', props.linkFinancingId), {
                asset_unit_id: recordId,
            });
        } catch {
            router.visit(route('financings.edit', props.linkFinancingId));
            return;
        }

        if (props.returnUrl) {
            router.visit(props.returnUrl);
            return;
        }

        router.visit(route('financings.show', props.linkFinancingId));
        return;
    }

    if (recordId) {
        router.visit(route('assetunits.show', recordId));
    }
};
</script>

<template>
    <Head title="New asset unit" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New asset unit
                    </h2>
                </div>
            </div>
        </template>

        <!-- Financing data copy panel -->
        <div
            v-if="linkFinancing"
            class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                        Linking to {{ linkFinancing.display_name }} — copy data below into the form
                    </p>
                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                        The unit will be automatically linked to this financing record after saving.
                    </p>
                </div>
                <a
                    :href="route('financings.show', linkFinancing.id)"
                    class="text-xs font-medium text-amber-700 underline hover:text-amber-900 dark:text-amber-300"
                >
                    View financing →
                </a>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3 lg:grid-cols-4">
                <div v-if="linkFinancing.serial_vin">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Serial / VIN</dt>
                    <dd class="select-all font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.serial_vin }}</dd>
                </div>
                <div v-if="linkFinancing.model_year">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Year</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.model_year }}</dd>
                </div>
                <div v-if="linkFinancing.model_number">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Model</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.model_number }}</dd>
                </div>
                <div v-if="linkFinancing.supplier_name">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Supplier / Make</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.supplier_name }}</dd>
                </div>
                <div v-if="linkFinancing.dealer_name">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Dealer</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.dealer_name }}</dd>
                </div>
                <div v-if="linkFinancing.lender_invoice_number">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Invoice #</dt>
                    <dd class="select-all font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.lender_invoice_number }}</dd>
                </div>
                <div v-if="linkFinancing.vendor_name">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Lender</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.vendor_name }}</dd>
                </div>
                <div v-if="linkFinancing.financed_at">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Financed date</dt>
                    <dd class="select-all text-sm font-semibold text-gray-900 dark:text-white">{{ linkFinancing.financed_at }}</dd>
                </div>
            </dl>
        </div>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <AssetUnitForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :timezones="timezones"
                :prefill="prefill"
                mode="create"
                @cancelled="handleCancel"
                @saved="({ recordId }) => handleCreated(recordId)"
            />
        </div>
    </TenantLayout>
</template>
