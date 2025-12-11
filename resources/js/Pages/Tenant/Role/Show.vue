<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Modal from '@/Components/Modal.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'roles',
    },
    recordTitle: {
        type: String,
        default: 'Roles',
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
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const formRef = ref(null);

const handleEdit = () => {
    isEditMode.value = true;
};

const handleCancel = () => {
    isEditMode.value = false;
};

const handleSubmit = () => {
    isEditMode.value = false;
    router.reload({ only: ['record'] });
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
    isEditMode.value = false;
    if (formRef.value) {
        formRef.value.cancelForm();
    }
};

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Account', href: route('account.index') },
        { label: props.recordTitle, href: route(`${props.recordType}.index`) },
        { label: props.record.display_name },
    ];
});

// Parse permissions for display
const parsedPermissions = computed(() => {
    if (!props.record.permissions) return [];
    try {
        return Array.isArray(props.record.permissions)
            ? props.record.permissions
            : Object.keys(props.record.permissions);
    } catch (e) {
        return [];
    }
});
</script>

<template>
    <Head :title="`${recordTitle} - ${record.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center space-x-4">
                        <!-- Role Icon -->
                        <div class="flex items-center justify-center w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-full">
                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                                {{ record.display_name }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Role • {{ record.slug }}
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div v-if="!isEditMode" class="flex items-center space-x-3">
                        <button
                            @click="handleEdit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit Role
                        </button>
                        <button
                            v-if="record.slug !== 'admin'"
                            @click="handleDelete"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete Role
                        </button>
                    </div>

                    <!-- Edit Mode Buttons -->
                    <div v-else class="flex items-center space-x-3">
                        <button
                            @click="handleSave"
                            :disabled="formRef?.isProcessing"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:bg-transparent dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full">
            <!-- Edit Mode - Form -->
            <div v-if="isEditMode" class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                <Form
                    ref="formRef"
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record="record"
                    :record-type="recordType"
                    :enum-options="enumOptions"
                    :prevent-redirect="true"
                    mode="edit"
                    :form-id="`form-${recordType}-${record.id}`"
                    @submit="handleSubmit"
                    @cancel="handleCancel"
                />
            </div>

            <!-- View Mode - Profile Cards -->
            <div v-else class="space-y-6">
                <!-- Role Overview Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                                    Basic Information
                                </h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Display Name
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ record.display_name }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Slug
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">
                                            {{ record.slug }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Created
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ new Date(record.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric'
                                            }) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Description -->
                            <div>
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                                    Description
                                </h3>
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <p v-if="record.description" class="whitespace-pre-wrap">
                                        {{ record.description }}
                                    </p>
                                    <p v-else class="text-gray-500 dark:text-gray-400 italic">
                                        No description provided
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                Permissions
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                                {{ parsedPermissions.length }} permission{{ parsedPermissions.length !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div v-if="parsedPermissions.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="permission in parsedPermissions"
                                :key="permission"
                                class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                            >
                                <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ permission.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                No permissions assigned
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This role has no specific permissions configured.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Users with this Role Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                            Users with this Role
                        </h3>

                        <div v-if="record.users && record.users.length > 0" class="space-y-3">
                            <div
                                v-for="user in record.users"
                                :key="user.id"
                                class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-full">
                                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ user.display_name }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                No users assigned
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                No users have been assigned this role yet.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
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
                    Delete Role
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete the role "{{ record.display_name }}"? This action cannot be undone and may affect users currently assigned to this role.
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
                        {{ isDeleting ? 'Deleting...' : 'Delete Role' }}
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
