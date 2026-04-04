<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import AssetForm from '@/Components/Tenant/AssetForm.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'assets',
    },
    recordTitle: {
        type: String,
        default: 'Asset',
    },
    domainName: {
        type: String,
        default: 'Asset',
    },
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    imageUrls: {
        type: Object,
        default: () => ({}),
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    availableSpecs: {
        type: Array,
        default: () => [],
    },
    extraRouteParams: {
        type: Object,
        default: () => ({}),
    },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const assetLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Asset #${props.record?.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Assets', href: route('assets.index') },
    { label: assetLabel.value },
]);

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

const handleDelete = () => {
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(
        route(
            `${props.recordType}.destroy`,
            buildResourceRouteParams(props.recordType, props.record.id, props.extraRouteParams),
        ),
        {
            onSuccess: () => {
                router.visit(route(`${props.recordType}.index`, props.extraRouteParams));
            },
            onError: () => {
                isDeleting.value = false;
            },
            onFinish: () => {
                isDeleting.value = false;
                showDeleteModal.value = false;
            },
        },
    );
};

const cancelDelete = () => {
    showDeleteModal.value = false;
};
</script>

<template>
    <Head :title="`${recordTitle} — ${assetLabel}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ assetLabel }}
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
                                v-if="record?.has_variants"
                                class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                Has variants
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('assets.index')">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <span class="material-icons text-base">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link
                            :href="route(
                                `${recordType}.edit`,
                                buildResourceRouteParams(recordType, record.id, extraRouteParams),
                            )"
                        >
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

        <div class="mx-auto flex w-full flex-col space-y-6">
            <AssetForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record="record"
                :record-type="recordType"
                :record-title="recordTitle"
                :enum-options="enumOptions"
                :image-urls="imageUrls"
                :timezones="timezones"
                :available-specs="availableSpecs"
                :extra-route-params="extraRouteParams"
                mode="view"
                :prevent-redirect="true"
                :form-id="`form-${recordType}-${record.id}`"
            />

            <Sublist
                v-if="visibleSublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="visibleSublists"
            />
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="cancelDelete">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    Delete asset
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this asset? This cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="cancelDelete"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
