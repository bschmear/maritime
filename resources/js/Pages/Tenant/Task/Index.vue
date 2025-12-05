<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import KanbanBoard from '@/Components/Tenant/KanbanBoard.vue';
import TaskListView from '@/Components/Tenant/TaskListView.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const handleTaskClicked = (task) => {
    router.visit(route(`${props.recordType}.show`, task.id));
};

const handleTaskUpdated = () => {
    router.reload({ only: ['records'] });
};

const setView = (view) => {
    currentView.value = view;
};

const setGroupBy = (field) => {
    groupBy.value = field;
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
                                    'px-4 py-2 text-sm font-medium border-l border-gray-200 dark:border-gray-600 transition-colors flex items-center space-x-2',
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
                            <button
                                @click="setView('table')"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-r-lg border-l border-gray-200 dark:border-gray-600 transition-colors flex items-center space-x-2',
                                    currentView === 'table'
                                        ? 'bg-primary-600 text-white hover:bg-primary-700'
                                        : 'bg-white text-gray-900 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                                ]"
                                title="Table View"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h16a1 1 0 011 1v18a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm2 2v3h3V5H5zm5 0v3h4V5h-4zm9 0h-3v3h3V5zM5 10v4h3v-4H5zm5 0v4h4v-4h-4zm9 0h-3v4h3v-4zM5 16v3h3v-3H5zm5 0v3h4v-3h-4zm9 0h-3v3h3v-3z" clip-rule="evenodd" />
                                </svg>
                                <span>Table</span>
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
                @task-clicked="handleTaskClicked"
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
                @task-clicked="handleTaskClicked"
            />

            <!-- Table View -->
            <Table
                v-else-if="currentView === 'table'"
                :records="records"
                :schema="schema"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :record-type="recordType"
                :record-title="recordTitle"
                :plural-title="pluralTitle"
            />
        </div>
    </TenantLayout>
</template>
