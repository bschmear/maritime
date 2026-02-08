<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Modal from '@/Components/Modal.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { Head, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { ref, computed, getCurrentInstance } from 'vue';

// Get access to global properties for formatting (optional)
const instance = getCurrentInstance();
const $formatDate = instance?.appContext?.config?.globalProperties?.$formatDate;
const $formatDateRelative = instance?.appContext?.config?.globalProperties?.$formatDateRelative;

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        required: true,
    },
    recordTitle: {
        type: String,
        required: true,
    },
    pluralTitle: {
        type: String,
        default: null,
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
        default: null,
    },
    showSublists: {
        type: Boolean,
        default: true,
    },
    breadcrumbParentLabel: {
        type: String,
        default: null,
    },
    breadcrumbParentHref: {
        type: String,
        default: null,
    },
    displayNameField: {
        type: String,
        default: 'display_name',
    },
    nameFields: {
        type: Array,
        default: () => ['first_name', 'last_name'],
    },
    useNameFields: {
        type: Boolean,
        default: false,
    },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const formRef = ref(null);

const computedPluralTitle = computed(() => {
    return props.pluralTitle || props.recordTitle;
});

const sublists = computed(() => {
    return props.formSchema?.sublists || [];
});

const handleEdit = () => {
    isEditMode.value = true;
};

const handleCancel = () => {
    // Form reset is handled by Form component's cancelForm()
    // Just exit edit mode
    isEditMode.value = false;
};

const handleSubmit = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};

const handleUpdated = (updatedRecord) => {
    isEditMode.value = false;
    location.reload();
};

const handleDelete = () => {
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => {
            router.visit(route(`${props.recordType}.index`));
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

const handleSave = () => {
    if (formRef.value) {
        formRef.value.submitForm();
    }
};

const handleCancelEdit = () => {
    // Just exit edit mode - form.reset() is already called in cancelForm()
    isEditMode.value = false;
    // Optionally reset form if needed
    if (formRef.value) {
        formRef.value.cancelForm();
    }
};

const displayName = computed(() => {
    if (props.useNameFields) {
        return props.nameFields.map(field => props.record[field]).filter(Boolean).join(' ');
    }
    return props.record[props.displayNameField] || '—';
});

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home', href: route('dashboard') },
    ];

    if (props.breadcrumbParentLabel && props.breadcrumbParentHref) {
        items.push({
            label: props.breadcrumbParentLabel,
            href: props.breadcrumbParentHref,
        });
    } else {
        items.push({
            label: computedPluralTitle.value,
            href: route(`${props.recordType}.index`),
        });
    }

    items.push({ label: displayName.value });

    return items;
});
</script>

<template>
    <Head :title="`${recordTitle} - ${displayName}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ displayName }}
                    </h2>

                    <!-- View Mode Buttons -->
                    <div class="xl:hidden">
                        <div v-if="!isEditMode" class="flex items-center space-x-3">
                            <button
                                @click="handleEdit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>
                            <button
                                @click="handleDelete"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </div>

                        <!-- Edit Mode Buttons -->
                        <div v-else class="flex items-center space-x-3">
                            <button
                                @click="handleSave"
                                :disabled="formRef?.isProcessing"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="formRef?.isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ formRef?.isProcessing ? 'Saving...' : 'Save Changes' }}
                            </button>
                            <button
                                @click="handleCancelEdit"
                                :disabled="formRef?.isProcessing"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-4 md:space-y-6">
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-9 bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg w-full">
                    <div class="show">
                        <Form
                            ref="formRef"
                            :schema="formSchema"
                            :fields-schema="fieldsSchema"
                            :record="record"
                            :record-type="recordType"
                            :enum-options="enumOptions"
                            :mode="isEditMode ? 'edit' : 'view'"
                            :prevent-redirect="true"
                            :form-id="`form-${recordType}-${record.id}`"
                            @submit="handleSubmit"
                            @updated="handleUpdated"
                            @cancel="handleCancel"
                        />
                    </div>
                </div>

                <div class="hidden xl:block xl:col-span-3 w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg w-full overflow-hidden sticky top-5">
                        <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                            Actions
                        </div>
                        <div class="p-4 sm:p-5 space-y-4">
                            <!-- Timestamp Information -->
                            <div class="space-y-3 pb-4 border-b border-gray-200 dark:border-gray-700" v-if="$formatDate && $formatDateRelative">
                                <div class="flex flex-col space-y-1">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Created</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $formatDate(record.created_at) }}</span>
                                </div>
                                <div class="flex flex-col space-y-1">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Last Updated</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $formatDateRelative(record.updated_at) }}</span>
                                </div>
                            </div>

                            <!-- View Mode Buttons -->
                            <div v-if="!isEditMode" class="flex space-x-4">
                                <button
                                    @click="handleEdit"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <button
                                    @click="handleDelete"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Delete
                                </button>
                            </div>

                            <!-- Edit Mode Buttons -->
                            <div v-else class="flex space-x-4">
                                <button
                                    @click="handleSave"
                                    :disabled="formRef?.isProcessing"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="formRef?.isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ formRef?.isProcessing ? 'Saving...' : 'Save Changes' }}
                                </button>
                                <button
                                    @click="handleCancelEdit"
                                    :disabled="formRef?.isProcessing"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sublist Component -->
            <Sublist
                v-if="showSublists && sublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="sublists"
            />
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    Delete Record
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this record? This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>