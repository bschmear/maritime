<script setup>
import AssetCatalogOptionsSection from '@/Components/Tenant/AssetCatalogOptionsSection.vue';
import AssetUnitOriginalMsoCard from '@/Components/Tenant/AssetUnitOriginalMsoCard.vue';
import ConsignmentAgreementSection from '@/Components/Tenant/ConsignmentAgreementSection.vue';
import AssetUnitForm from '@/Components/Tenant/AssetUnitForm.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'assetunits' },
    recordTitle: { type: String, default: 'Asset Unit' },
    pluralTitle: { type: String, default: 'Asset Units' },
    domainName: { type: String, default: 'AssetUnit' },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    catalogResolvedOptions: { type: Array, default: () => [] },
    catalogContext: { type: Object, default: null },
    consignmentAgreementContext: { type: Object, default: null },
    msoBuilderLinks: { type: Array, default: () => [] },
});

const originalMso = computed(() => {
    const docs = props.record?.documents ?? [];
    return docs.find((doc) => (doc.pivot?.role ?? doc.role) === 'mso') ?? null;
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const unitLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Unit #${props.record?.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle, href: route('assetunits.index') },
    { label: unitLabel.value },
]);

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

const handleDelete = () => {
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('assetunits.destroy', props.record.id), {
        onSuccess: () => {
            router.visit(route('assetunits.index'));
        },
        onError: () => {
            isDeleting.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const cancelDelete = () => {
    showDeleteModal.value = false;
};

const sublists = computed(() => props.formSchema?.sublists ?? []);
</script>

<template>
    <Head :title="`${recordTitle} — ${unitLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ unitLabel }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium"
                                :class="record?.inactive
                                    ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
                                    : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'"
                            >
                                {{ record?.inactive ? 'Inactive' : 'Active' }}
                            </span>
                            <span
                                v-if="record?.is_consignment"
                                class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-sm font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                            >
                                Consignment
                            </span>
                            <span
                                v-if="record?.is_customer_owned"
                                class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-sm font-medium text-blue-900 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                Customer owned
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('assetunits.index')">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-base">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link :href="route('assetunits.edit', record.id)">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            >
                                <span class="material-icons text-base">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            @click="handleDelete"
                        >
                            <span class="material-icons text-base">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="min-w-0 space-y-6 lg:col-span-8">
                    <ConsignmentAgreementSection
                        v-if="record?.is_consignment && consignmentAgreementContext"
                        :context="consignmentAgreementContext"
                        :record="record"
                    />

                    <AssetCatalogOptionsSection
                        v-if="showAssetCatalogOptions"
                        :resolved-options="catalogResolvedOptions"
                        :catalog-context="catalogContext"
                        intro="These options apply when this unit’s parent asset has no variants."
                    />

                    <AssetUnitForm
                        :schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :record="record"
                        :record-type="recordType"
                        :record-title="recordTitle"
                        :enum-options="enumOptions"
                        :timezones="timezones"
                        mode="view"
                    />
                </div>

                <aside class="min-w-0 lg:col-span-4">
                    <div class="sticky top-[140px]">
                        <AssetUnitOriginalMsoCard
                            :parent-id="record.id"
                            :parent-type="domainName"
                            :document="originalMso"
                        />
                    </div>
                </aside>
            </div>

            <Sublist
                v-if="sublists.length > 0"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="sublists"
                :mso-builder-links="msoBuilderLinks"
            />
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="cancelDelete">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete asset unit</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this unit? This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        :disabled="isDeleting"
                        @click="cancelDelete"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
