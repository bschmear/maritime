<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserForm from '@/Components/Tenant/UserForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
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
    roles: {
        type: Array,
        default: () => [],
    },
    canAssignRole: {
        type: Boolean,
        default: false,
    },
    avatarPreviewUrl: {
        type: String,
        default: null,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Users', href: route('users.index') },
    { label: props.record.display_name || props.record.email || 'Edit User' },
]);

const handleCancel = () => {
    router.visit(route('users.show', props.record.id));
};
</script>

<template>
    <Head :title="`Edit ${record.display_name || record.email || 'User'}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Edit {{ record.display_name || record.email }}
                </h2>
            </div>
        </template>

        <div class="flex w-full flex-col space-y-6">
            <UserForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :roles="roles"
                :can-assign-role="canAssignRole"
                :avatar-preview-url="avatarPreviewUrl"
                mode="edit"
                @cancelled="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
