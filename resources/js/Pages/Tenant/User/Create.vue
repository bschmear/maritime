<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserForm from '@/Components/Tenant/UserForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
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
    roles: {
        type: Array,
        default: () => [],
    },
    canAssignRole: {
        type: Boolean,
        default: false,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Users', href: route('users.index') },
    { label: 'Create User' },
]);

const handleCancel = () => {
    router.visit(route('users.index'));
};
</script>

<template>
    <Head title="Create User" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">New User</h2>
            </div>
        </template>

        <div class="flex w-full flex-col space-y-6">
            <UserForm
                :record="null"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :roles="roles"
                :can-assign-role="canAssignRole"
                mode="create"
                @cancelled="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
