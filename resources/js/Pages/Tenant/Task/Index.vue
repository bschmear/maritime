<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import KanbanBoard from '@/Components/Tenant/KanbanBoard.vue';
import TaskListView from '@/Components/Tenant/TaskListView.vue';
import Modal from '@/Components/Modal.vue';
import Form from '@/Components/Tenant/Form.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
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
    recordType: {
        type: String,
        default: 'tasks',
    },
    recordTitle: {
        type: String,
        default: 'Task',
    },
    pluralTitle: {
        type: String,
        default: 'Tasks',
    },
});

const currentView = ref('kanban'); // 'kanban', 'list', or 'table'
const groupBy = ref('status_id'); // 'status_id' or 'priority_id'
const showGroupDropdown = ref(false);

// Modal state
const showViewModal = ref(false);
const showCreateModal = ref(false);
const selectedRecord = ref(null);
const isLoadingRecord = ref(false);
const prePopulatedData = ref({});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

// Get tasks as flat array from paginated records
const tasks = computed(() => {
    return props.records?.data || [];
});

// Get group options based on groupBy field
const groupOptions = computed(() => {
    if (groupBy.value === 'status_id') {
        return props.enumOptions['App\\Enums\\Tasks\\Status'] || [];
    } else if (groupBy.value === 'priority_id') {
        return props.enumOptions['App\\Enums\\Tasks\\Priority'] || [];
    }
    return [];
});


const handleTaskUpdated = () => {
    router.reload({ only: ['records'] });
    closeViewModal();
};

const handleTaskFormSubmit = () => {
    router.reload({ only: ['records'] });
    closeViewModal();
};

const setView = (view) => {
    currentView.value = view;
};

const setGroupBy = (field) => {
    groupBy.value = field;
};

// Handle view task event from Kanban/List components
const handleViewTask = async (task) => {
    isLoadingRecord.value = true;
    showViewModal.value = true;

    try {
        const response = await axios.get(route(`${props.recordType}.show`, task.id), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        // Extract the record from the JSON response
        if (response.data && response.data.record) {
            selectedRecord.value = response.data.record;
        } else {
            // Fallback to the partial record if full record not available
            selectedRecord.value = task;
        }
    } catch (error) {
        console.error('Error fetching record:', error);
        // Fallback to the partial record on error
        selectedRecord.value = task;
    } finally {
        isLoadingRecord.value = false;
    }
};

// Close the view modal
const closeViewModal = () => {
    showViewModal.value = false;
    selectedRecord.value = null;
};

// Open create modal (optionally with pre-populated data)
const openCreateModal = (groupId = null) => {
    prePopulatedData.value = {};
    
    // If a group ID is provided, pre-populate the groupBy field
    if (groupId !== null) {
        prePopulatedData.value[groupBy.value] = groupId;
    }
    
    showCreateModal.value = true;
};

// Close the create modal
const closeCreateModal = () => {
    showCreateModal.value = false;
    prePopulatedData.value = {};
};

// Handle task creation
const handleTaskCreated = () => {
    router.reload({ only: ['records'] });
    closeCreateModal();
};
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                
                <!-- Header with View Toggle -->
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ pluralTitle }}
                    </h2>

                    <div class="flex items-center space-x-3">
                        <!-- Add Task Button (only for kanban/list views) -->
                        <button
                            v-if="currentView !== 'table'"
                            @click="openCreateModal()"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-primary-700 px-3 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Task
                        </button>

                        <!-- Group By Dropdown (only for kanban/list views) -->
                        <div v-if="currentView !== 'table'" class="relative">
                            <button
                                @click="showGroupDropdown = !showGroupDropdown"
                                type="button"
                                class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700"
                            >
                                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M15 4H9v16h6V4Zm2 16h3a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3v16ZM4 4h3v16H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" clip-rule="evenodd"></path>
                                </svg>
                                Group by: {{ groupBy === 'status_id' ? 'Status' : 'Priority' }}
                                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div v-show="showGroupDropdown" class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 z-50">
                                <div class="py-1">
                                    <button
                                        @click="setGroupBy('status_id'); showGroupDropdown = false"
                                        :class="[
                                            'block w-full text-left px-4 py-2 text-sm transition-colors',
                                            groupBy === 'status_id'
                                                ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        Status
                                    </button>
                                    <button
                                        @click="setGroupBy('priority_id'); showGroupDropdown = false"
                                        :class="[
                                            'block w-full text-left px-4 py-2 text-sm transition-colors',
                                            groupBy === 'priority_id'
                                                ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        Priority
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- View Toggle Buttons -->
                        <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600" role="group">
                            <button
                                @click="setView('kanban')"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-l-lg transition-colors flex items-center space-x-2',
                                    currentView === 'kanban'
                                        ? 'bg-primary-600 text-white hover:bg-primary-700'
                                        : 'bg-white text-gray-900 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                                ]"
                                title="Kanban View"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M3 6a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2V6zM3 16a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4zm10 0a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2v-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Kanban</span>
                            </button>
                            <button
                                @click="setView('list')"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-r-lg border-l border-gray-200 dark:border-gray-600 transition-colors flex items-center space-x-2',
                                    currentView === 'list'
                                        ? 'bg-primary-600 text-white hover:bg-primary-700'
                                        : 'bg-white text-gray-900 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                                ]"
                                title="List View"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 20a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span>List</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col">
            <!-- Kanban View -->
            <KanbanBoard
                v-if="currentView === 'kanban'"
                :tasks="tasks"
                :group-by-field="groupBy"
                :group-options="groupOptions"
                :record-type="recordType"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                @task-updated="handleTaskUpdated"
                @view-task="handleViewTask"
                @create-task="openCreateModal"
            />

            <!-- List View -->
            <TaskListView
                v-else-if="currentView === 'list'"
                :tasks="tasks"
                :group-by-field="groupBy"
                :group-options="groupOptions"
                :record-type="recordType"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                @task-updated="handleTaskUpdated"
                @view-task="handleViewTask"
                @create-task="openCreateModal"
            />

        </div>

        <!-- View Modal for Kanban/List Views -->
        <Modal :show="showViewModal" @close="closeViewModal" max-width="4xl">
            <!-- Modal header (fixed) -->
            <div class="flex items-start justify-between p-4 border-b dark:border-gray-700 flex-shrink-0">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ selectedRecord ? `Edit ${recordTitle}` : '' }}
                </h3>
                <button
                    @click="closeViewModal"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body (scrollable) -->
            <div class="flex-1 overflow-y-auto max-h-[70vh]">
                <div v-if="isLoadingRecord" class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    <span class="ml-2 text-gray-600 dark:text-gray-400">Loading...</span>
                </div>
                <div v-else-if="selectedRecord">
                    <Form
                        :schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :record="selectedRecord"
                        :record-type="recordType"
                        :record-title="recordTitle"
                        :enum-options="enumOptions"
                        :mode="'edit'"
                        :prevent-redirect="true"
                        @updated="handleTaskUpdated"
                        @submit="handleTaskFormSubmit"
                        @cancel="closeViewModal"
                    />
                </div>
            </div>

        </Modal>

        <!-- Create Modal for Kanban/List Views -->
        <Modal :show="showCreateModal" @close="closeCreateModal" max-width="4xl">
            <!-- Modal header (fixed) -->
            <div class="flex items-start justify-between p-4 border-b dark:border-gray-700 flex-shrink-0">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Create {{ recordTitle }}
                </h3>
                <button
                    @click="closeCreateModal"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body (scrollable) -->
            <div class="flex-1 overflow-y-auto max-h-[70vh]">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record="prePopulatedData"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    :enum-options="enumOptions"
                    :mode="'create'"
                    :prevent-redirect="true"
                    @created="handleTaskCreated"
                    @submit="handleTaskCreated"
                    @cancel="closeCreateModal"
                />
            </div>
        </Modal>
    </TenantLayout>
</template>
