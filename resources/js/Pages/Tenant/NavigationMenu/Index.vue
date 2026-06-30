<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    canManage: {
        type: Boolean,
        default: true,
    },
    menus: {
        type: Array,
        default: () => [],
    },
    availableRoles: {
        type: Array,
        default: () => [],
    },
});

const createForm = useForm({
    role_id: props.availableRoles[0]?.id ?? null,
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Account', href: route('account.index') },
    { label: 'Navigation menus' },
]);

const createRoleMenu = () => {
    if (!createForm.role_id) {
        return;
    }

    createForm.post(route('navigation-menus.store'));
};

const deleteMenu = (menu) => {
    if (!confirm(`Delete the menu for ${menu.role?.display_name ?? menu.name}?`)) {
        return;
    }

    router.delete(route('navigation-menus.destroy', menu.id));
};
</script>

<template>
    <Head title="Navigation menus" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div
                v-if="!canManage"
                class="flex min-h-[50vh] flex-col items-center justify-center rounded-lg bg-white px-6 py-16 text-center shadow-lg dark:bg-gray-800"
            >
                <span class="material-icons mb-4 text-5xl text-gray-400 dark:text-gray-500">admin_panel_settings</span>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Administrator access required</h1>
                <p class="mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400">
                    You must be an administrator to customize navigation menus.
                </p>
                <Link
                    :href="route('account.index')"
                    class="mt-6 inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Back to Account
                </Link>
            </div>

            <template v-else>
            <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Navigation menus</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Customize the top navigation for your workspace. The default menu applies to all roles unless a role has its own menu.
                    </p>
                </div>

                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li
                        v-for="menu in menus"
                        :key="menu.id"
                        class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6"
                    >
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ menu.name }}
                                <span
                                    v-if="menu.is_default"
                                    class="ml-2 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    Default
                                </span>
                            </p>
                            <p v-if="menu.role" class="text-sm text-gray-500 dark:text-gray-400">
                                Role: {{ menu.role.display_name }}
                            </p>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                Used when no role-specific menu exists
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link
                                :href="menu.edit_url"
                                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Edit
                            </Link>
                            <button
                                v-if="!menu.is_default"
                                type="button"
                                class="inline-flex items-center rounded-lg border border-transparent bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700"
                                @click="deleteMenu(menu)"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
            </div>

            <div
                v-if="availableRoles.length"
                class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800"
            >
                <div class="px-4 py-5 sm:px-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Create role menu</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Start from a copy of the default menu, then customize links and groupings for that role.
                    </p>

                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                            <select
                                v-model="createForm.role_id"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                            >
                                <option v-for="role in availableRoles" :key="role.id" :value="role.id">
                                    {{ role.display_name }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="createForm.processing || !createForm.role_id"
                            @click="createRoleMenu"
                        >
                            Create from default
                        </button>
                    </div>
                    <p v-if="createForm.errors.role_id" class="mt-2 text-sm text-red-600">{{ createForm.errors.role_id }}</p>
                </div>
            </div>
            </template>
        </div>
    </TenantLayout>
</template>
