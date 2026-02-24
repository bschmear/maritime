<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, nextTick, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import Sortable from 'sortablejs';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Templates', href: route('delivery-checklist-templates.index') },
    { label: props.template.name },
]);

// Modal state
const showAddItemModal = ref(false);
const showEditItemModal = ref(false);
const editingItem = ref(null);
const isLoading = ref(false);
const formErrors = ref({});

// Form data
const itemForm = ref({
    label: '',
    category_id: null,
    new_category_name: '',
    is_required: false,
});

// Open add item modal
const openAddItemModal = () => {
    itemForm.value = {
        label: '',
        category_id: props.categories && props.categories.length > 0 ? props.categories[0].id : null,
        new_category_name: '',
        is_required: false,
    };
    formErrors.value = {};
    showAddItemModal.value = true;
};

// Open edit item modal
const openEditItemModal = (item) => {
    editingItem.value = item;
    itemForm.value = {
        label: item.label,
        category_id: item.category_id,
        new_category_name: '',
        is_required: item.is_required,
    };
    formErrors.value = {};
    showEditItemModal.value = true;
};

// Close modals
const closeAddItemModal = () => {
    showAddItemModal.value = false;
    formErrors.value = {};
};

const closeEditItemModal = () => {
    showEditItemModal.value = false;
    editingItem.value = null;
    formErrors.value = {};
};

// Save item
const saveItem = async () => {
    isLoading.value = true;
    formErrors.value = {};

    try {
        if (editingItem.value) {
            await axios.put(route('delivery-checklist-templates.update-item', editingItem.value.id), itemForm.value);
        } else {
            await axios.post(route('delivery-checklist-templates.add-item', props.template.id), itemForm.value);
        }
        router.reload();
        closeAddItemModal();
        closeEditItemModal();
    } catch (error) {
        console.error('Error saving item:', error);
        if (error.response && error.response.data && error.response.data.message) {
            formErrors.value.general = error.response.data.message;
        }
    } finally {
        isLoading.value = false;
    }
};

// Delete item
const deleteItem = async (item) => {
    if (confirm('Are you sure you want to delete this item?')) {
        try {
            await axios.delete(route('delivery-checklist-templates.delete-item', item.id));
            router.reload();
        } catch (error) {
            console.error('Error deleting item:', error);
        }
    }
};

// Initialize sortable for a category
const initSortable = (containerId, categoryId) => {
    nextTick(() => {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Destroy existing sortable if it exists
        if (container._sortable) {
            container._sortable.destroy();
        }

        container._sortable = Sortable.create(container, {
            handle: '.sortable-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: async (evt) => {
                const items = itemsByCategory.value[categoryId]?.items || [];
                if (items.length === 0) return;

                // Get the new order from the DOM after sorting
                const container = document.getElementById(`category-${categoryId}-items`);
                const itemElements = container.querySelectorAll('[data-item-id]');
                const newOrder = Array.from(itemElements).map((el, index) => ({
                    id: parseInt(el.getAttribute('data-item-id')),
                    sort_order: index,
                }));

                try {
                    // Update all items' sort order
                    await Promise.all(newOrder.map(({ id, sort_order }) => {
                        const item = props.template.items.find(i => i.id === id);
                        if (!item) return Promise.resolve();

                        return axios.put(route('delivery-checklist-templates.update-item', id), {
                            label: item.label,
                            category_id: categoryId,
                            is_required: item.is_required,
                            sort_order: sort_order,
                        });
                    }));

                    // Reload the page to reflect the new order
                    router.reload();
                } catch (error) {
                    console.error('Error reordering items:', error);
                    router.reload(); // Reload to revert changes on error
                }
            },
        });
    });
};

// Computed properties
const itemsByCategory = computed(() => {
    const grouped = {};
    if (props.categories && props.template && props.template.items && Array.isArray(props.categories) && Array.isArray(props.template.items)) {
        props.categories.forEach(category => {
            if (category && typeof category === 'object' && category.id && category.name) {
                const categoryItems = props.template.items
                    .filter(item => item && typeof item === 'object' && item.category_id === category.id)
                    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

                if (categoryItems.length > 0) {
                    grouped[category.id] = {
                        category: category,
                        items: categoryItems
                    };
                }
            }
        });
    }
    return grouped;
});

// Initialize sortable on mount and when items change
onMounted(() => {
    if (props.categories && props.template && Array.isArray(props.categories)) {
        props.categories.forEach(category => {
            if (category && category.id) {
                initSortable(`category-${category.id}-items`, category.id);
            }
        });
    }
});

// Re-initialize sortable when items change
watch(itemsByCategory, () => {
    nextTick(() => {
        if (props.categories && props.template && Array.isArray(props.categories)) {
            props.categories.forEach(category => {
                if (category && category.id) {
                    initSortable(`category-${category.id}-items`, category.id);
                }
            });
        }
    });
}, { deep: true });

// Cleanup sortables on unmount
onUnmounted(() => {
    if (props.categories && Array.isArray(props.categories)) {
        props.categories.forEach(category => {
            if (category && category.id) {
                const container = document.getElementById(`category-${category.id}-items`);
                if (container && container._sortable) {
                    container._sortable.destroy();
                }
            }
        });
    }
});
</script>

<template>
    <Head :title="`${template.name} - Checklist Template`" />

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
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ template.name }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Global Template • {{ template.items?.length || 0 }} items
                        <span v-if="template.is_default" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                            Default
                        </span>
                    </p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="openAddItemModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Item
                    </button>
                    <Link
                        :href="route('delivery-checklist-templates.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium"
                    >
                        Back to Templates
                    </Link>
                </div>
            </div>

            <!-- Template Items -->
            <div class="space-y-6">
                <!-- Loading state -->
                <div v-if="!props.categories || !props.template" class="text-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="mt-4 text-gray-500">Loading template...</p>
                </div>

                <!-- Categories with Items -->
                <template v-else>
                    <div
                        v-for="categoryGroup in Object.values(itemsByCategory)"
                        :key="categoryGroup.category.id"
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :class="`bg-${categoryGroup.category.color || 'blue'}-500`"></span>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ categoryGroup.category.name }}</h3>
                            <span class="text-sm text-gray-500 dark:text-gray-400">({{ categoryGroup.items.length }} items)</span>
                        </div>
                    </div>

                    <div :id="`category-${categoryGroup.category.id}-items`" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="(item, index) in categoryGroup.items"
                            :key="item.id"
                            :data-item-id="item.id"
                            class="px-6 py-4 flex items-center justify-between group hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="sortable-handle cursor-move text-gray-400 group-hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                    </svg>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.label }}</span>
                                </div>
                                <span v-if="item.is_required" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                    Required
                                </span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <!-- Edit/Delete -->
                                <button
                                    @click="openEditItemModal(item)"
                                    class="p-2 text-gray-400 hover:text-gray-600"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteItem(item)"
                                    class="p-2 text-red-400 hover:text-red-600"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="Object.keys(itemsByCategory).length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h6a2 2 0 002-2V7a2 2 0 00-2-2H9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9l6 6m-6 0l6-6" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No checklist items</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first checklist item.</p>
                        <div class="mt-6">
                            <button
                                @click="openAddItemModal"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                            >
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Item
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Add/Edit Item Modal -->
        <Modal :show="showAddItemModal || showEditItemModal" @close="showAddItemModal ? closeAddItemModal() : closeEditItemModal()" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ editingItem ? 'Edit Item' : 'Add Checklist Item' }}
                    </h3>
                    <button @click="showAddItemModal ? closeAddItemModal() : closeEditItemModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveItem" class="space-y-4">
                    <!-- Error Display -->
                    <div v-if="formErrors.general" class="bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-md p-4">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ formErrors.general }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Item Label
                        </label>
                        <input
                            v-model="itemForm.label"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            placeholder="e.g., Check vessel documentation"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Category *
                        </label>
                        <div class="space-y-2">
                            <!-- Category Selection -->
                            <select
                                v-model="itemForm.category_id"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>

                            <!-- Create New Category Option -->
                            <div class="flex items-center space-x-2">
                                <input
                                    v-model="itemForm.new_category_name"
                                    type="text"
                                    placeholder="Or create new category..."
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                                />
                                <button
                                    v-if="itemForm.new_category_name"
                                    type="button"
                                    @click="itemForm.category_id = null"
                                    class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    Use New
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input
                            v-model="itemForm.is_required"
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
                            @click="showAddItemModal ? closeAddItemModal() : closeEditItemModal()"
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
                            {{ editingItem ? 'Update Item' : 'Add Item' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </TenantLayout>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.4;
    background: rgb(156 163 175);
}
</style>