<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { ref, computed, getCurrentInstance, watch, onMounted } from 'vue';
import axios from 'axios';

// Get access to global properties
const instance = getCurrentInstance();
const $formatDate = instance.appContext.config.globalProperties.$formatDate;
const $formatDateRelative = instance.appContext.config.globalProperties.$formatDateRelative;

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'inventoryitems',
    },
    recordTitle: {
        type: String,
        default: 'Inventory Items',
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

// Sublist State
const activeTab = ref(null);
const sublistData = ref([]);
const sublistSchema = ref({});
const sublistPagination = ref(null);
const isLoadingSublist = ref(false);

// Sublist Create Modal State
const showSublistCreateModal = ref(false);
const sublistCreateFormData = ref(null);
const isLoadingSublistForm = ref(false);

const sublists = computed(() => {
    return props.formSchema?.sublists || [];
});

const formatRouteName = (domain) => {
    // Convert "InventoryUnit" to "inventoryunits.index"
    // Convert camelCase or PascalCase to lowercase plural (no hyphens)
    const lowercase = domain.toLowerCase();

    // Simple pluralization (add 's') - might need more robust pluralizer if domain names get complex
    const plural = lowercase.endsWith('s') ? lowercase : lowercase + 's';

    return `${plural}.index`;
};

const fetchSublistData = async (sublist, page = 1) => {
    if (!sublist) return;
    
    isLoadingSublist.value = true;
    try {
        const routeName = formatRouteName(sublist.domain);
        
        // Construct filter for current record
        // Assuming standard FK convention: inventory_item_id
        // We need to know the foreign key name. For now, let's guess based on current record type.
        // Or we could add a 'foreign_key' property to the sublist definition in form.json?
        // Let's assume singular_record_type_id for now.
        const singularRecordType = props.recordType.endsWith('s') ? props.recordType.slice(0, -1) : props.recordType;
        const foreignKey = `${singularRecordType}_id`;
        
        const filters = {
            [foreignKey]: props.record.id
        };

        // Handle Polymorphic relationships if needed (basic check)
        // If domain is Document, it might be polymorphic
        if (sublist.domain === 'Document') {
            // Polymorphic structure often uses relatable_type and relatable_id
            // Or we might need a specific endpoint. 
            // For now, let's stick to the generic controller pattern.
            // If it's a standard relational setup for documents to the item.
        }

        const response = await axios.get(route(routeName), {
            params: {
                filters: JSON.stringify(filters),
                page: page,
                per_page: 10
            },
             headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        sublistData.value = response.data.records || [];
        sublistSchema.value = response.data.fieldsSchema || {};
        // If schema is returned, we might use that for headers
        // Otherwise use fieldsSchema
        
        sublistPagination.value = response.data.meta;

    } catch (error) {
        console.error('Error fetching sublist data:', error);
        sublistData.value = [];
    } finally {
        isLoadingSublist.value = false;
    }
};

const handleTabChange = (sublist) => {
    activeTab.value = sublist;
    fetchSublistData(sublist);
};

// Sublist Create Modal Functions
const openSublistCreateModal = async () => {
    if (!sublistCreateFormData.value && activeTab.value) {
        // Load form data if not already loaded
        isLoadingSublistForm.value = true;
        try {
            // Use the domain name directly (e.g., 'InventoryUnit')
            const type = activeTab.value.domain;
            const response = await axios.get(route('records.select-form', { type: type }), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            sublistCreateFormData.value = response.data;
        } catch (error) {
            console.error('Error loading sublist form data:', error);
            return; // Don't open modal if loading failed
        } finally {
            isLoadingSublistForm.value = false;
        }
    }

    showSublistCreateModal.value = true;
};

const closeSublistCreateModal = () => {
    showSublistCreateModal.value = false;
};

const handleSublistItemCreated = (recordId) => {
    // Refresh the sublist data to include the new record
    if (activeTab.value) {
        fetchSublistData(activeTab.value);
    }
    showSublistCreateModal.value = false;
    sublistCreateFormData.value = null; // Reset form data
};

// Get initial form data for sublist creation
const getSublistInitialData = () => {
    const initialData = {};

    // For InventoryUnit creation, automatically set the inventory_item_id
    if (activeTab.value?.domain === 'InventoryUnit') {
        initialData.inventory_item_id = props.record.id;
        // Also include relationship data so RecordSelect can display the name
        initialData.inventory_item = {
            id: props.record.id,
            display_name: props.record.display_name
        };
    }

    return initialData;
};

// Get modified fields schema for sublist creation (to disable auto-filled fields)
const getSublistFieldsSchema = () => {
    if (!sublistCreateFormData.value?.fieldsSchema) return {};

    const fieldsSchema = { ...sublistCreateFormData.value.fieldsSchema };

    // For InventoryUnit creation, disable the inventory_item_id field since it's auto-filled
    if (activeTab.value?.domain === 'InventoryUnit' && fieldsSchema.inventory_item_id) {
        fieldsSchema.inventory_item_id = {
            ...fieldsSchema.inventory_item_id,
            disabled: true
        };
    }

    return fieldsSchema;
};

// Get record context for sublist creation (provides relationship data for RecordSelect components)
const getSublistRecordContext = () => {
    // For create mode, we provide a mock record with relationship data
    // so RecordSelect components can display the correct names
    const contextRecord = {};

    // For InventoryUnit creation, provide the inventory_item relationship
    if (activeTab.value?.domain === 'InventoryUnit') {
        contextRecord.inventory_item = {
            id: props.record.id,
            display_name: props.record.display_name
        };
    }

    return contextRecord;
};

// Initialize first tab if available
onMounted(() => {
    if (sublists.value.length > 0) {
        handleTabChange(sublists.value[0]);
    }
});

const handleEdit = () => {
    isEditMode.value = true;
};

const handleCancel = () => {
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
    isEditMode.value = false;
    if (formRef.value) {
        formRef.value.cancelForm();
    }
};

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.recordTitle, href: route(`${props.recordType}.index`) },
        { label: props.record.display_name },
    ];
});
</script>

<template>
    <Head :title="`${recordTitle} - ${record.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full ">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ record.display_name }}
                    </h2>
                    <!-- View Mode Buttons -->
                    <div class="xl:hidden">
                        <div v-if="!isEditMode" class="flex items-center space-x-3 ">
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
           <div class="grid gap-4  xl:grid-cols-12">
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
                <div class="hidden xl:block sticky top-5 xl:col-span-3 w-full">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg w-full overflow-hidden sticky top-5">
                        <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700 ">
                            Actions
                        </div>
                        <div class="p-4 sm:p-5 space-y-4">
                            <!-- Timestamp Information -->
                            <div class="space-y-3 pb-4 border-b border-gray-200 dark:border-gray-700">
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

            <div class="w-full">
                <div class="sm:hidden">
                    <label for="tabs" class="sr-only">Select User Sublist</label>
                    <select id="tabs" class="input-style bg-primary-600" @change="handleTabChange(sublists[$event.target.selectedIndex])">
                        <option v-for="(sublist, index) in sublists" :key="index" :selected="activeTab === sublist">
                            {{ sublist.label }}
                        </option>
                    </select>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg w-full overflow-hidden sticky top-5">
                    <div class="flex justify-between items-center p-4 sm:px-5 w-full font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                        Related Records
                    </div>
                    <div class="p-4 sm:p-5 space-x-2 border-b border-gray-200 dark:border-gray-600">
                        <ul class="record-sublist">
                            <li v-for="(sublist, index) in sublists" :key="index" class="me-2">
                                <a 
                                    href="#" 
                                    @click.prevent="handleTabChange(sublist)"
                                    class="record-sublist-item"
                                    :class="{ 'active': activeTab === sublist }"
                                >
                                    {{ sublist.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="p-4 sm:p-5 relative min-h-[200px]">
                        <div v-if="isLoadingSublist" class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 z-10">
                            <svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        
                        <div v-if="sublistData.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <!-- Determine headers from schema or first record keys -->
                                        <th v-for="(field, key) in sublistSchema" :key="key" class="px-6 py-3" v-show="field.show_in_index !== false">
                                            {{ field.label || key }}
                                        </th>
                                        <!-- Fallback if no schema -->
                                        <template v-if="!sublistSchema || Object.keys(sublistSchema).length === 0">
                                             <th class="px-6 py-3">Display Name</th>
                                             <th class="px-6 py-3">Created At</th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in sublistData" :key="item.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <template v-if="Object.keys(sublistSchema).length > 0">
                                            <td v-for="(field, key) in sublistSchema" :key="key" class="px-6 py-4" v-show="field.show_in_index !== false">
                                                <template v-if="field.type === 'record'">
                                                     <!-- Handle record display if expanded, usually just generic name here -->
                                                     {{ item[key.replace('_id', '')]?.display_name || item[key] }}
                                                </template>
                                                <template v-else-if="field.type === 'boolean'">
                                                    {{ item[key] ? 'Yes' : 'No' }}
                                                </template>
                                                <template v-else-if="field.type === 'date'">
                                                    {{ $formatDate(item[key]) }}
                                                </template>
                                                 <template v-else-if="field.type === 'datetime'">
                                                    {{ $formatDate(item[key]) }}
                                                </template>
                                                <template v-else>
                                                    {{ item[key] }}
                                                </template>
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td class="px-6 py-4">{{ item.display_name }}</td>
                                            <td class="px-6 py-4">{{ $formatDate(item.created_at) }}</td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <!-- Pagination -->
                            <div v-if="sublistPagination && sublistPagination.last_page > 1" class="flex justify-between items-center mt-4">
                                <button 
                                    @click="fetchSublistData(activeTab, sublistPagination.current_page - 1)"
                                    :disabled="sublistPagination.current_page === 1"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    Page {{ sublistPagination.current_page }} of {{ sublistPagination.last_page }}
                                </span>
                                <button 
                                    @click="fetchSublistData(activeTab, sublistPagination.current_page + 1)"
                                    :disabled="sublistPagination.current_page === sublistPagination.last_page"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                        <div v-else-if="!isLoadingSublist" class="text-center py-8">
                            <div class="text-gray-500 dark:text-gray-400 mb-4">
                                No {{ activeTab?.label || 'records' }} found.
                            </div>
                            <button
                                @click="openSublistCreateModal"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add New {{ activeTab?.label || 'Record' }}
                            </button>
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

        <!-- Sublist Create Modal -->
        <Modal :show="showSublistCreateModal" @close="closeSublistCreateModal" :max-width="'4xl'">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Create New {{ activeTab?.label || 'Record' }}
                </h3>
                <button
                    @click="closeSublistCreateModal"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-4">
                <div v-if="isLoadingSublistForm" class="flex justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <Form
                    v-else-if="sublistCreateFormData"
                    :schema="sublistCreateFormData.formSchema"
                    :fields-schema="getSublistFieldsSchema()"
                    :record="getSublistRecordContext()"
                    :record-type="sublistCreateFormData.recordType"
                    :record-title="activeTab?.label || 'Record'"
                    :enum-options="sublistCreateFormData.enumOptions"
                    :initial-data="getSublistInitialData()"
                    mode="create"
                    :prevent-redirect="true"
                    @created="handleSublistItemCreated"
                    @cancel="closeSublistCreateModal"
                />
            </div>
        </Modal>
    </TenantLayout>
</template>
