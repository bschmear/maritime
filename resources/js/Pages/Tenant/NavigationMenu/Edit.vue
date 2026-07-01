<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import NavigationMenuEditor from '@/Components/Tenant/NavigationMenuEditor.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    menu: {
        type: Object,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    routeCatalog: {
        type: Array,
        default: () => [],
    },
    rolePermissionKeys: {
        type: Array,
        default: () => [],
    },
    readOnly: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const form = useForm({
    name: props.menu.name,
    items: JSON.parse(JSON.stringify(props.items)),
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Account', href: route('account.index') },
    { label: 'Navigation menus', href: route('navigation-menus.index') },
    { label: props.menu.name },
]);

const subtitle = computed(() => {
    if (props.readOnly) {
        return 'Read-only preview of the application default menu shipped with Helmful.';
    }

    if (props.menu.is_workspace_default) {
        return 'Workspace default menu used for all roles without a role-specific menu.';
    }

    if (props.menu.is_default) {
        return 'Workspace default menu used for all roles without a custom menu.';
    }

    return `Custom menu for the ${props.menu.role?.display_name ?? 'role'} role.`;
});

const pageTitle = computed(() => (props.readOnly ? props.menu.name : `Edit ${props.menu.name}`));

const save = () => {
    form.put(route('navigation-menus.update', props.menu.id), {
        preserveScroll: true,
    });
};

const onItemsChange = (items) => {
    form.items = items;
};
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
                {{ flashSuccess }}
            </div>

            <div
                v-if="readOnly"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-100"
            >
                This menu is managed in the application configuration file (<code class="font-mono text-xs">tenant_navigation.json</code>) and cannot be edited here. Create a workspace default to customize navigation for your account.
            </div>

            <div
                v-else-if="menu.is_workspace_default"
                class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-100"
            >
                This menu applies to every role in your workspace. Role-specific menus override it. Remove it from the navigation menus page to fall back to the application default.
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ menu.name }}</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ subtitle }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('navigation-menus.index')"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Back
                        </Link>
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="save"
                        >
                            Save menu
                        </button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6">
                    <div v-if="!readOnly">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Menu name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <h2 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Menu structure</h2>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                            <template v-if="readOnly">
                                Preview of top-level groups and links. Use collapse controls to scan nested sections.
                            </template>
                            <template v-else>
                                Drag rows to reorder. Choose “Group (no link)” for parent items. Items flagged as missing permission are still shown here so you can see what the role cannot access.
                            </template>
                        </p>

                        <NavigationMenuEditor
                            :model-value="readOnly ? items : form.items"
                            :route-catalog="routeCatalog"
                            :role-permission-keys="rolePermissionKeys"
                            :read-only="readOnly"
                            @update:model-value="onItemsChange"
                        />

                        <p v-if="form.errors.items" class="mt-2 text-sm text-red-600">{{ form.errors.items }}</p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
