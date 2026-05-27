<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Modal from '@/Components/Modal.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RolePermissionsEditor from '@/Components/Tenant/RolePermissionsEditor.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

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
    permissionsByDomain: {
        type: Array,
        default: () => [],
    },
    assignedPermissionIds: {
        type: Array,
        default: () => [],
    },
    canManageRoles: {
        type: Boolean,
        default: false,
    },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const form = useForm({
    display_name: props.record.display_name,
    slug: props.record.slug,
    description: props.record.description ?? '',
    permission_ids: [...(props.assignedPermissionIds || [])],
});

watch(
    () => props.record.id,
    () => {
        form.display_name = props.record.display_name;
        form.slug = props.record.slug;
        form.description = props.record.description ?? '';
        form.permission_ids = [...(props.assignedPermissionIds || [])];
        form.clearErrors();
    }
);

watch(
    () => [...(props.assignedPermissionIds || [])],
    (ids) => {
        if (!isEditMode.value) {
            form.permission_ids = [...ids];
        }
    }
);

const assignedIdSet = computed(() => {
    const perms = props.record.permissions;
    if (!perms || !Array.isArray(perms)) {
        return new Set();
    }
    return new Set(perms.map((p) => p.id));
});

const permissionCount = computed(() => props.record.permissions?.length ?? 0);

const domainsForView = computed(() => {
    return (props.permissionsByDomain || [])
        .map((d) => ({
            ...d,
            permissions: (d.permissions || []).filter((p) => assignedIdSet.value.has(p.id)),
        }))
        .filter((d) => d.permissions.length > 0);
});

const handleEdit = () => {
    form.display_name = props.record.display_name;
    form.slug = props.record.slug;
    form.description = props.record.description ?? '';
    form.permission_ids = [...(props.assignedPermissionIds || [])];
    form.clearErrors();
    isEditMode.value = true;
};

const handleCancelEdit = () => {
    isEditMode.value = false;
    form.display_name = props.record.display_name;
    form.slug = props.record.slug;
    form.description = props.record.description ?? '';
    form.permission_ids = [...(props.assignedPermissionIds || [])];
    form.clearErrors();
};

const handleSave = () => {
    form.put(route(`${props.recordType}.update`, props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditMode.value = false;
        },
    });
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

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Account', href: route('account.index') },
        { label: props.recordTitle, href: route(`${props.recordType}.index`) },
        { label: props.record.display_name },
    ];
});

const slugDisabled = computed(() => props.record.slug === 'admin');
</script>

<template>
    <Head :title="`${recordTitle} - ${record.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                            <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                                {{ record.display_name }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Role • {{ record.slug }}</p>
                        </div>
                    </div>

                    <div v-if="canManageRoles && !isEditMode" class="flex items-center space-x-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                            @click="handleEdit"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit Role
                        </button>
                        <button
                            v-if="record.slug !== 'admin'"
                            type="button"
                            class="inline-flex items-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900"
                            @click="handleDelete"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete Role
                        </button>
                    </div>

                    <div v-else-if="canManageRoles && isEditMode" class="flex items-center space-x-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                            :disabled="form.processing"
                            @click="handleSave"
                        >
                            <svg v-if="form.processing" class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                            :disabled="form.processing"
                            @click="handleCancelEdit"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full">
            <div v-if="isEditMode && canManageRoles" class="space-y-6">
                <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Basic information</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Display name</label>
                                <input
                                    v-model="form.display_name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="form.errors.display_name" class="mt-1 text-sm text-red-600">{{ form.errors.display_name }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Slug</label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    :disabled="slugDisabled"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800"
                                />
                                <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Permissions</h3>
                        <RolePermissionsEditor v-model="form.permission_ids" :permissions-by-domain="permissionsByDomain" />
                        <p v-if="form.errors.permission_ids" class="mt-2 text-sm text-red-600">{{ form.errors.permission_ids }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Basic Information</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ record.display_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Slug</dt>
                                        <dd class="mt-1 rounded bg-gray-50 px-2 py-1 font-mono text-sm text-gray-900 dark:bg-gray-700 dark:text-white">{{ record.slug }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{
                                                new Date(record.created_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Description</h3>
                                <p v-if="record.description" class="whitespace-pre-wrap text-sm text-gray-900 dark:text-white">{{ record.description }}</p>
                                <p v-else class="text-sm italic text-gray-500 dark:text-gray-400">No description provided</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Permissions</h3>
                            <span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                                {{ permissionCount }} permission{{ permissionCount !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div v-if="domainsForView.length > 0" class="space-y-4">
                            <div
                                v-for="domain in domainsForView"
                                :key="domain.domain"
                                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-700/40"
                            >
                                <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{{ domain.domainLabel }}</h4>
                                <ul class="flex flex-wrap gap-2">
                                    <li
                                        v-for="perm in domain.permissions"
                                        :key="perm.id"
                                        class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-xs font-medium text-gray-800 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600"
                                    >
                                        {{ perm.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-else class="py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No permissions assigned</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This role has no permissions in the catalog yet.</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Users with this Role</h3>

                        <div v-if="record.users && record.users.length > 0" class="space-y-3">
                            <div
                                v-for="user in record.users"
                                :key="user.id"
                                class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                                        <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ user.display_name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users assigned</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No users have been assigned this role yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="cancelDelete">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Delete Role</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete the role "{{ record.display_name }}"? This action cannot be undone and may affect users currently assigned to this role.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete Role' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
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
