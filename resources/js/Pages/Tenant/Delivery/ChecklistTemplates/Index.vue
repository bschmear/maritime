<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Checklist Templates' },
];

// Modal state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingTemplate = ref(null);
const isLoading = ref(false);

// Form data
const templateForm = ref({
    name: '',
    is_default: false,
});

// Open create modal
const openCreateModal = () => {
    templateForm.value = {
        name: '',
        is_default: false,
    };
    showCreateModal.value = true;
};

// Open edit modal
const openEditModal = (template) => {
    editingTemplate.value = template;
    templateForm.value = {
        name: template.name,
        is_default: template.is_default,
    };
    showEditModal.value = true;
};

// Close modals
const closeCreateModal = () => {
    showCreateModal.value = false;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingTemplate.value = null;
};

// Save template
const saveTemplate = async () => {
    isLoading.value = true;
    try {
        if (editingTemplate.value) {
            await axios.put(route('delivery-checklist-templates.update', editingTemplate.value.id), templateForm.value);
            router.reload();
            closeEditModal();
        } else {
            await axios.post(route('delivery-checklist-templates.store'), templateForm.value);
            // No need to reload since we're redirecting to the show page
            closeCreateModal();
        }
    } catch (error) {
        console.error('Error saving template:', error);
    } finally {
        isLoading.value = false;
    }
};

// Delete template
const deleteTemplate = async (template) => {
    if (confirm('Are you sure you want to delete this template?')) {
        try {
            await axios.delete(route('delivery-checklist-templates.destroy', template.id));
            router.reload();
        } catch (error) {
            console.error('Error deleting template:', error);
        }
    }
};
</script>

<template>
    <Head title="Checklist Templates" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Checklist Templates</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage reusable delivery checklist templates</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Template
                </button>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ template.name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Global Template
                            </p>
                        </div>
                        <div v-if="template.is_default" class="ml-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                Default
                            </span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ template.items?.length || 0 }} items
                        </p>
                    </div>

                    <div class="flex space-x-2">
                        <Link
                            :href="route('delivery-checklist-templates.show', template.id)"
                            class="flex-1 inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40"
                        >
                            View
                        </Link>
                        <button
                            @click="openEditModal(template)"
                            class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteTemplate(template)"
                            class="px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="templates.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No templates</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first checklist template.</p>
                <div class="mt-6">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                    >
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Template
                    </button>
                </div>
            </div>
        </div>

        <!-- Create/Edit Template Modal -->
        <Modal :show="showCreateModal || showEditModal" @close="showCreateModal ? closeCreateModal() : closeEditModal()" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ editingTemplate ? 'Edit Template' : 'Create Template' }}
                    </h3>
                    <button @click="showCreateModal ? closeCreateModal() : closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveTemplate" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Template Name
                        </label>
                        <input
                            v-model="templateForm.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            placeholder="e.g., Standard Boat Delivery"
                        />
                    </div>


                    <div class="flex items-center">
                        <input
                            v-model="templateForm.is_default"
                            type="checkbox"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Set as default template
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="showCreateModal ? closeCreateModal() : closeEditModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isLoading"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ editingTemplate ? 'Update Template' : 'Create Template' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </TenantLayout>
</template>