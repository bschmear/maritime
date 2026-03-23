<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ContractForm from '@/Components/Tenant/ContractForm.vue';
import ContractPreview from '@/Components/Tenant/ContractPreview.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    formSchema: {
        type: Object,
        default: null,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Contracts', href: route('contracts.index') },
    { label: props.record.contract_number || `Contract #${props.record.id}` },
]);

const showPreview = ref(false);
const showActionsMenu = ref(false);

const isLocked = computed(() => {
    return Boolean(props.record.signed_at) || props.record.status === 'signed';
});

const deleteContract = () => {
    if (confirm('Are you sure you want to delete this contract?')) {
        router.delete(route('contracts.destroy', props.record.id));
    }
};

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};
</script>

<template>
    <Head :title="`Contract — ${record.contract_number || record.id}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Contract details
                    </h2>

                    <div class="hidden lg:flex items-center gap-2">
                        <Link :href="route('contracts.index')">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back to list
                            </button>
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors whitespace-nowrap"
                            @click="openPreview"
                        >
                            <span class="material-icons text-sm mr-1">visibility</span>
                            Preview
                        </button>
                        <Link v-if="!isLocked" :href="route('contracts.edit', record.id)">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-400 bg-gray-200 cursor-not-allowed rounded-lg"
                        >
                            <span class="material-icons text-sm mr-1">lock</span>
                            Locked
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-2 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                            @click="deleteContract"
                        >
                            <span class="material-icons">delete_forever</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 lg:hidden">
                        <Link :href="route('contracts.index')" class="flex-1">
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                            >
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        <Link v-if="!isLocked" :href="route('contracts.edit', record.id)" class="flex-1">
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg"
                            >
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center p-2.5 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                                @click="showActionsMenu = !showActionsMenu"
                            >
                                <span class="material-icons">more_vert</span>
                            </button>
                            <div
                                v-if="showActionsMenu"
                                class="fixed inset-0 z-40"
                                @click="showActionsMenu = false"
                            />
                            <div
                                v-if="showActionsMenu"
                                class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
                            >
                                <button
                                    type="button"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                    @click="openPreview(); showActionsMenu = false"
                                >
                                    <span class="material-icons text-base text-purple-600">visibility</span>
                                    Preview
                                </button>
                                <button
                                    type="button"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 border-t border-gray-100 dark:border-gray-700"
                                    @click="deleteContract(); showActionsMenu = false"
                                >
                                    <span class="material-icons text-base">delete_forever</span>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 md:space-y-6 !pt-0 !mt-0">
            <ContractForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                mode="show"
            />
        </div>

        <Teleport to="body">
            <div v-if="showPreview" class="fixed inset-0 z-[100] overflow-y-auto">
                <ContractPreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>
