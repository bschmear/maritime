<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { computed } from 'vue';

const props = defineProps({
    recordType: {
        type: String,
        required: true
    },
    formSchema: {
        type: Object,
        required: true
    },
    fieldsSchema: {
        type: Object,
        required: true
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    }
});

const pluralTitle = computed(() => {
    // Convert ServiceItem to Service Items
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: pluralTitle.value, href: route('serviceitems.index') },
        { label: 'Create' },
    ];
});

// Handle form submission
const handleSubmit = () => {
    // Form component handles the actual submission
};

const handleCreated = (recordId) => {
    // Navigate to the created record or back to index
    window.location.href = route('serviceitems.index');
};

const handleCancel = () => {
    // Navigate back to index
    window.location.href = route('serviceitems.index');
};
</script>

<template>
    <Head title="Create Service Item" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        New {{ pluralTitle }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-4 md:space-y-6">
            <div class="grid gap-4 xl:grid-cols-12">
                <!-- Main Service Item Form -->
                <div class="xl:col-span-9 space-y-6">
                    <!-- Service Item Header -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">SERVICE ITEM</h1>
                                    <p class="text-blue-100 text-sm mt-1">Create a new service offering</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <Form
                                :schema="props.formSchema"
                                :fields-schema="props.fieldsSchema"
                                :enum-options="props.enumOptions"
                                record-type="ServiceItem"
                                record-title="Service Item"
                                mode="create"
                                @created="handleCreated"
                                @cancel="handleCancel"
                            />
                        </div>
                    </div>
                </div>

                <!-- Actions Sidebar -->
                <div class="xl:col-span-3 w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                        <div class="flex justify-between items-center p-4 sm:px-5 font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                            Actions
                        </div>

                        <div class="p-4 sm:p-5 space-y-6">
                            <!-- Classification -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Quick Actions
                                    </label>
                                    <div class="space-y-2">
                                        <button
                                            type="button"
                                            @click="document.querySelector('form')?.dispatchEvent(new Event('submit'))"
                                            class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                        >
                                            <span class="material-icons text-sm mr-2">check_circle</span>
                                            Create Service Item
                                        </button>

                                        <button
                                            type="button"
                                            @click="handleCancel"
                                            class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                                        >
                                            <span class="material-icons text-sm mr-2">close</span>
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>