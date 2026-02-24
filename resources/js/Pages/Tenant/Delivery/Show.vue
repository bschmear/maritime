<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Modal from '@/Components/Modal.vue';
import DeliveryPreview from '@/Components/Tenant/DeliveryPreview.vue';
import { Head, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'deliveries',
    },
    recordTitle: {
        type: String,
        default: 'Delivery',
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
    imageUrls: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    checklistItems: {
        type: Array,
        default: () => [],
    },
    checklistTemplates: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const formRef = ref(null);

// Checklist state
const showChecklistModal = ref(false);
const checklistCreationMode = ref('template'); // 'template' or 'scratch'
const selectedTemplate = ref(null);
const newChecklistItems = ref([]);
const isLoadingChecklist = ref(false);

// Add Item Modal state
const showAddItemModal = ref(false);
const newItemLabel = ref('');
const newItemCategory = ref('');
const newItemRequired = ref(false);

// Preview state
const showPreview = ref(false);

// Mark delivered options modal
const showMarkDeliveredModal = ref(false);

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

const handleUpdated = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};

const handleDelete = () => {
    showDeleteModal.value = true;
};

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);

// Group checklist items by category for column layout
const itemsByCategory = computed(() => {
    const grouped = {};
    (props.checklistItems || []).forEach(item => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) {
            grouped[catId] = { id: catId, name: catName, items: [] };
        }
        grouped[catId].items.push(item);
    });
    return Object.values(grouped).sort((a, b) => a.name.localeCompare(b.name));
});

// Check if delivery is signed (locked)
const isSigned = computed(() => !!props.record?.signed_at);

// Show mark delivered options
const markAsDelivered = () => {
    showMarkDeliveredModal.value = true;
};

// Send signature request
const sendSignatureRequest = async () => {
    try {
        await axios.post(route('deliveries.send-signature-request', props.record.id));
        showMarkDeliveredModal.value = false;
        alert('Signature request sent to customer successfully!');
    } catch (error) {
        console.error('Error sending signature request:', error);
        alert('Failed to send signature request. Please try again.');
    }
};

// Mark as delivered without signature
const markDeliveredWithoutSignature = async () => {
    try {
        await axios.post(route('deliveries.mark-delivered', props.record.id));
        showMarkDeliveredModal.value = false;
        router.reload();
    } catch (error) {
        console.error('Error marking delivery as delivered:', error);
        alert('Failed to mark delivery as completed. Please try again.');
    }
};

// View signature request
const viewSignatureRequest = () => {
    const signatureUrl = route('deliveries.review', props.record.uuid);
    window.open(signatureUrl, '_blank');
};

// Add individual checklist item
const addChecklistItemToDelivery = () => {
    newItemLabel.value = '';
    newItemCategory.value = props.categories.length > 0 ? props.categories[0].name : '';
    newItemRequired.value = false;
    showAddItemModal.value = true;
};

// Save new checklist item
const saveNewChecklistItem = async () => {
    if (!newItemLabel.value.trim()) return;

    try {
        await axios.post(route('deliveries.checklist.add-item', { delivery: props.record.id }), {
            label: newItemLabel.value.trim(),
            category: newItemCategory.value,
            is_required: newItemRequired.value,
        });
        showAddItemModal.value = false;
        router.reload();
    } catch (error) {
        console.error('Error adding checklist item:', error);
        alert('Failed to add item. Please try again.');
    }
};

// Close add item modal
const closeAddItemModal = () => {
    showAddItemModal.value = false;
};

// Remove checklist item
const removeChecklistItemFromDelivery = async (item) => {
    if (!confirm(`Remove "${item.label}" from checklist?`)) return;

    try {
        await axios.delete(route('deliveries.checklist.remove-item', { delivery: props.record.id, item: item.id }));
        router.reload();
    } catch (error) {
        console.error('Error removing checklist item:', error);
        alert('Failed to remove item. Please try again.');
    }
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, recordIdentifier.value), {
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

// Checklist functions
const openChecklistModal = () => {
    showChecklistModal.value = true;
    checklistCreationMode.value = 'template';
    selectedTemplate.value = null;
    newChecklistItems.value = [];
};

const closeChecklistModal = () => {
    showChecklistModal.value = false;
};

const selectChecklistMode = (mode) => {
    checklistCreationMode.value = mode;
    if (mode === 'scratch') {
        newChecklistItems.value = [{
            label: '',
            category: 'Pre Delivery',
            is_required: false,
            sort_order: 0,
        }];
    }
};

const addChecklistItem = () => {
    newChecklistItems.value.push({
        label: '',
        category: 'Pre Delivery',
        is_required: false,
        sort_order: newChecklistItems.value.length,
    });
};

const removeChecklistItem = (index) => {
    newChecklistItems.value.splice(index, 1);
};

const saveChecklist = async () => {
    isLoadingChecklist.value = true;
    try {
        if (checklistCreationMode.value === 'template' && selectedTemplate.value) {
            await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), {
                template_id: selectedTemplate.value.id,
            });
        } else if (checklistCreationMode.value === 'scratch') {
            const validItems = newChecklistItems.value.filter(item => item.label.trim());
            if (validItems.length > 0) {
                await axios.post(route('deliveries.checklist.store', { delivery: props.record.id }), {
                    items: validItems,
                });
            }
        }

        // Reload the page to get updated checklist
        router.reload();
        closeChecklistModal();
    } catch (error) {
        console.error('Error saving checklist:', error);
    } finally {
        isLoadingChecklist.value = false;
    }
};

const setChecklistItemCompleted = async (item, completed) => {
    try {
        await axios.put(route('deliveries.checklist.update-item', { delivery: props.record.id, item: item.id }), {
            completed,
        });
        router.reload();
    } catch (error) {
        console.error('Error updating checklist item:', error);
    }
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

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: props.record?.display_name ?? 'Delivery' },
]);
</script>

<template>
    <Head :title="`${recordTitle} - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ record?.display_name }}
                    </h2>
                    <!-- View Mode Buttons -->
                    <div v-if="!isEditMode" class="flex items-center space-x-3">
                        <button
                            @click="openPreview"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview
                        </button>
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
        </template>

        <div class="flex gap-6">
            <!-- Main Content -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="">
                        <Form
                            ref="formRef"
                            :schema="formSchema"
                            :fields-schema="fieldsSchema"
                            :record="record"
                            :record-type="recordType"
                            :record-title="recordTitle"
                            :record-identifier="recordIdentifier"
                            :enum-options="enumOptions"
                            :image-urls="imageUrls"
                            :account="account"
                            :timezones="timezones"
                            :mode="isEditMode ? 'edit' : 'view'"
                            :prevent-redirect="true"
                            :form-id="`form-${recordType}-${record?.id ?? record?.uuid}`"
                            @submit="handleSubmit"
                            @updated="handleUpdated"
                            @cancel="handleCancel"
                        />
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-80 flex-shrink-0">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Delivery Actions</h3>
                        <div class="space-y-3">
                            <button
                                @click="markAsDelivered"
                                :disabled="record?.delivered_at"
                                class="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ record?.delivered_at ? 'Delivered' : 'Mark Delivered' }}
                            </button>

                            <button
                                @click="viewSignatureRequest"
                                class="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View Signature Request
                            </button>

                            <div v-if="record?.delivered_at" class="text-xs text-green-600 bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                <div class="font-medium">Delivered on:</div>
                                <div>{{ new Date(record.delivered_at).toLocaleDateString() }}</div>
                            </div>
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
                    Delete Delivery
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete {{ record?.display_name }}? This action cannot be undone.
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

        <!-- Checklist Section - Always visible -->
        <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delivery Checklist</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Items to complete before and during delivery
                            <span v-if="isSigned" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                <span class="material-icons text-sm mr-1">lock</span>
                                Signed
                            </span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            v-if="!isSigned"
                            @click="addChecklistItemToDelivery"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-base">add</span>
                            Add Item
                        </button>
                        <button
                            v-if="!isSigned && checklistItems.length === 0"
                            @click="openChecklistModal"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-base">description</span>
                            Use Template
                        </button>
                        <div v-if="isSigned" class="text-right">
                            <p class="text-xs text-gray-500">Signed by {{ record.recipient_name || 'Customer' }}</p>
                            <p class="text-xs text-gray-500">{{ new Date(record.signed_at).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="checklistItems.length > 0" class="overflow-x-auto">
                <div class="flex gap-6 p-6 min-w-max">
                    <div
                        v-for="category in itemsByCategory"
                        :key="category.id"
                        class="flex flex-col min-w-[220px] max-w-[280px]"
                    >
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center shrink-0">
                            <span class="w-2 h-2 rounded-full mr-2 bg-blue-500"></span>
                            {{ category.name }}
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="item in category.items"
                                :key="item.id"
                                class="border border-gray-200 dark:border-gray-600 rounded-lg p-3"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white flex-1">
                                        {{ item.label }}
                                        <span v-if="item.is_required" class="text-red-500">*</span>
                                    </p>
                                    <button
                                        v-if="!isSigned"
                                        @click="removeChecklistItemFromDelivery(item)"
                                        class="ml-2 flex-shrink-0 h-6 w-6 rounded flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    >
                                        <span class="material-icons text-sm">delete</span>
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="setChecklistItemCompleted(item, true)"
                                        :disabled="isSigned"
                                        :class="[
                                            'flex-1 px-2 py-1 text-xs font-medium rounded transition-colors',
                                            item.completed
                                                ? 'bg-green-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-green-100 dark:hover:bg-green-900/30',
                                            isSigned ? 'cursor-not-allowed opacity-60' : ''
                                        ]"
                                    >
                                        ✓ True
                                    </button>
                                    <button
                                        type="button"
                                        @click="setChecklistItemCompleted(item, false)"
                                        :disabled="isSigned"
                                        :class="[
                                            'flex-1 px-2 py-1 text-xs font-medium rounded transition-colors',
                                            !item.completed
                                                ? 'bg-red-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-900/30',
                                            isSigned ? 'cursor-not-allowed opacity-60' : ''
                                        ]"
                                    >
                                        ✗ False
                                    </button>
                                </div>
                                <p v-if="item.notes" class="text-xs text-gray-500 mt-2">{{ item.notes }}</p>
                                <p v-if="item.completed_by && item.completed" class="text-xs text-green-600 mt-1">
                                    {{ item.completed_by.display_name }} · {{ new Date(item.completed_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="px-6 py-12 text-center">
                <div class="mx-auto h-12 w-12 text-gray-400">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h6a2 2 0 002-2V7a2 2 0 00-2-2H9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9l6 6m-6 0l6-6" />
                    </svg>
                </div>
                <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No checklist items</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding items or using a template.</p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="addChecklistItemToDelivery"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                    >
                        <span class="material-icons text-base">add</span>
                        Add Item
                    </button>
                    <button
                        @click="openChecklistModal"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        <span class="material-icons text-base">description</span>
                        Use Template
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Checklist Modal -->
        <Modal :show="showChecklistModal" @close="closeChecklistModal" max-width="2xl">
            <div class="flex flex-col max-h-[80vh]">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="material-icons text-blue-600 dark:text-blue-400">checklist</span>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Delivery Checklist</h3>
                    </div>
                    <button @click="closeChecklistModal"
                        class="h-8 w-8 rounded-lg flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-icons text-lg">close</span>
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5 min-h-0">

                    <!-- Mode Selection -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- From Template -->
                        <button
                            type="button"
                            @click="selectChecklistMode('template')"
                            :class="[
                                'group relative flex flex-col items-start gap-2 p-4 rounded-xl border-2 text-left transition-all',
                                checklistCreationMode === 'template'
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                            ]"
                        >
                            <div :class="['h-9 w-9 rounded-lg flex items-center justify-center transition-colors',
                                checklistCreationMode === 'template'
                                    ? 'bg-blue-100 dark:bg-blue-900/50'
                                    : 'bg-gray-100 dark:bg-gray-700 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30']">
                                <span :class="['material-icons text-lg transition-colors',
                                    checklistCreationMode === 'template'
                                        ? 'text-blue-600 dark:text-blue-400'
                                        : 'text-gray-500 dark:text-gray-400 group-hover:text-blue-500 dark:group-hover:text-blue-400']">
                                    description
                                </span>
                            </div>
                            <div>
                                <p :class="['text-sm font-semibold',
                                    checklistCreationMode === 'template'
                                        ? 'text-blue-900 dark:text-blue-200'
                                        : 'text-gray-900 dark:text-white']">
                                    From Template
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Use an existing template</p>
                            </div>
                            <!-- Active check -->
                            <span v-if="checklistCreationMode === 'template'"
                                class="absolute top-3 right-3 material-icons text-base text-blue-600 dark:text-blue-400">
                                check_circle
                            </span>
                        </button>

                        <!-- From Scratch -->
                        <button
                            type="button"
                            @click="selectChecklistMode('scratch')"
                            :class="[
                                'group relative flex flex-col items-start gap-2 p-4 rounded-xl border-2 text-left transition-all',
                                checklistCreationMode === 'scratch'
                                    ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                            ]"
                        >
                            <div :class="['h-9 w-9 rounded-lg flex items-center justify-center transition-colors',
                                checklistCreationMode === 'scratch'
                                    ? 'bg-green-100 dark:bg-green-900/50'
                                    : 'bg-gray-100 dark:bg-gray-700 group-hover:bg-green-100 dark:group-hover:bg-green-900/30']">
                                <span :class="['material-icons text-lg transition-colors',
                                    checklistCreationMode === 'scratch'
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-gray-500 dark:text-gray-400 group-hover:text-green-500 dark:group-hover:text-green-400']">
                                    add_circle
                                </span>
                            </div>
                            <div>
                                <p :class="['text-sm font-semibold',
                                    checklistCreationMode === 'scratch'
                                        ? 'text-green-900 dark:text-green-200'
                                        : 'text-gray-900 dark:text-white']">
                                    Create from Scratch
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Build a custom checklist</p>
                            </div>
                            <span v-if="checklistCreationMode === 'scratch'"
                                class="absolute top-3 right-3 material-icons text-base text-green-600 dark:text-green-400">
                                check_circle
                            </span>
                        </button>
                    </div>

                    <!-- Template Selection -->
                    <div v-if="checklistCreationMode === 'template'"
                        class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                            <span class="material-icons text-gray-500 dark:text-gray-400 text-base">folder_open</span>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Select Template</h4>
                        </div>
                        <div class="p-4 space-y-3">
                            <select
                                v-model="selectedTemplate"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            >
                                <option :value="null">Choose a template…</option>
                                <option
                                    v-for="template in checklistTemplates"
                                    :key="template.id"
                                    :value="template"
                                >
                                    {{ template.name }}
                                </option>
                            </select>

                            <!-- Template meta -->
                            <div v-if="selectedTemplate"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                <span class="material-icons text-blue-500 dark:text-blue-400 text-base">info</span>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    This template contains
                                    <strong class="font-semibold">{{ selectedTemplate.items?.length || 0 }}</strong>
                                    {{ selectedTemplate.items?.length === 1 ? 'item' : 'items' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Scratch Creation -->
                    <div v-if="checklistCreationMode === 'scratch'"
                        class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                            <div class="flex items-center gap-2">
                                <span class="material-icons text-gray-500 dark:text-gray-400 text-base">list</span>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Checklist Items</h4>
                                <span class="px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-medium">
                                    {{ newChecklistItems.length }}
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="addChecklistItem"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-sm">add</span>
                                Add Item
                            </button>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700/70">
                            <div
                                v-for="(item, index) in newChecklistItems"
                                :key="index"
                                class="flex items-center gap-3 px-4 py-3"
                            >
                                <!-- Drag handle -->
                                <span class="material-icons text-base text-gray-300 dark:text-gray-600 cursor-grab flex-shrink-0">drag_indicator</span>

                                <!-- Label -->
                                <input
                                    v-model="item.label"
                                    placeholder="Enter checklist item…"
                                    class="flex-1 min-w-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 py-1.5 px-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                />

                                <!-- Category -->
                                <select
                                    v-model="item.category"
                                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 py-1.5 px-2 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors flex-shrink-0"
                                >
                                    <option value="Pre Delivery">Pre-Delivery</option>
                                    <option value="Upon Delivery">Upon Delivery</option>
                                </select>

                                <!-- Required toggle -->
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 cursor-pointer flex-shrink-0 select-none">
                                    <input
                                        v-model="item.is_required"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 bg-white dark:bg-gray-700 focus:ring-blue-500 focus:ring-offset-0 transition-colors"
                                    />
                                    <span class="hidden sm:inline">Required</span>
                                </label>

                                <!-- Remove -->
                                <button
                                    type="button"
                                    @click="removeChecklistItem(index)"
                                    :disabled="newChecklistItems.length === 1"
                                    class="flex-shrink-0 h-7 w-7 rounded-lg flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span class="material-icons text-base">delete</span>
                                </button>
                            </div>
                        </div>

                        <!-- Empty state (shouldn't show since we always start with 1, but just in case) -->
                        <div v-if="newChecklistItems.length === 0"
                            class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                            No items yet — click "Add Item" to get started.
                        </div>
                    </div>

                </div>
                <!-- end scrollable body -->

                <!-- Sticky Footer -->
                <div class="flex-shrink-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <button
                        type="button"
                        @click="closeChecklistModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveChecklist"
                        :disabled="
                            isLoadingChecklist
                            || (checklistCreationMode === 'template' && !selectedTemplate)
                            || (checklistCreationMode === 'scratch' && newChecklistItems.filter(i => i.label.trim()).length === 0)
                            || !checklistCreationMode
                        "
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm"
                    >
                        <span v-if="isLoadingChecklist" class="material-icons text-base animate-spin">autorenew</span>
                        <span v-else class="material-icons text-base">playlist_add_check</span>
                        Add Checklist
                    </button>
                </div>

            </div>
        </Modal>

        <!-- Add Item Modal -->
        <Modal :show="showAddItemModal" @close="closeAddItemModal" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Checklist Item</h3>
                    <button @click="closeAddItemModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveNewChecklistItem" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Item Label *
                        </label>
                        <input
                            v-model="newItemLabel"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Enter checklist item..."
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Category *
                        </label>
                        <select
                            v-model="newItemCategory"
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        >
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.name"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <input
                            v-model="newItemRequired"
                            type="checkbox"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            This item is required
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeAddItemModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!newItemLabel.trim()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Mark Delivered Options Modal -->
        <Modal :show="showMarkDeliveredModal" @close="showMarkDeliveredModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Complete Delivery</h3>
                    <button @click="showMarkDeliveredModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Choose how you want to complete this delivery:
                    </p>

                    <div class="grid grid-cols-1 gap-3">
                        <button
                            @click="sendSignatureRequest"
                            class="flex items-center gap-3 p-4 border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg transition-colors text-left"
                        >
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                                <span class="material-icons text-blue-600 dark:text-blue-400">send</span>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">Send Signature Request</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Request customer signature via email</p>
                            </div>
                        </button>

                        <button
                            @click="markDeliveredWithoutSignature"
                            class="flex items-center gap-3 p-4 border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 rounded-lg transition-colors text-left"
                        >
                            <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                                <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">Mark as Delivered</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Complete without customer signature</p>
                            </div>
                        </button>
                    </div>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="showMarkDeliveredModal = false"
                            class="w-full px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Delivery Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="fixed inset-0 z-[100] overflow-y-auto">
                <DeliveryPreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    :checklist-items="checklistItems"
                    @close="closePreview"
                />
            </div>
        </Teleport>

    </TenantLayout>
</template>
