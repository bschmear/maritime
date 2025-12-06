<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import axios from 'axios';

const props = defineProps({
    tasks: {
        type: Array,
        required: true,
    },
    groupByField: {
        type: String,
        default: 'status_id', // or 'priority_id'
    },
    groupOptions: {
        type: Array,
        required: true,
    },
    recordType: {
        type: String,
        default: 'tasks',
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

const emit = defineEmits(['task-clicked', 'view-task', 'create-task', 'task-updated']);

const groupRefs = ref({});
const sortableInstances = ref({});
const isDragging = ref(false);

// Group tasks by the specified field
const groupedTasks = computed(() => {
    const groups = {};
    
    props.groupOptions.forEach(option => {
        groups[option.id] = {
            ...option,
            tasks: props.tasks.filter(task => task[props.groupByField] === option.id),
        };
    });

    return groups;
});

const handleTaskClick = (task) => {
    // Don't open modal if we just finished dragging
    if (isDragging.value) return;
    emit('view-task', task);
};

const handleCreateTask = (groupId) => {
    emit('create-task', groupId);
};

const formatDate = (date) => {
    if (!date) return null;
    const d = new Date(date);
    const now = new Date();
    const diffTime = d - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return { text: `${Math.abs(diffDays)} days overdue`, color: 'red' };
    } else if (diffDays === 0) {
        return { text: 'Due today', color: 'orange' };
    } else if (diffDays === 1) {
        return { text: '1 day left', color: 'yellow' };
    } else if (diffDays <= 3) {
        return { text: `${diffDays} days left`, color: 'yellow' };
    } else {
        return { text: `${diffDays} days left`, color: 'green' };
    }
};

const getColorClasses = (color) => {
    const colors = {
        red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };
    return colors[color] || colors.gray;
};

const getPriorityIcon = (priorityId) => {
    // You can customize icons based on priority
    return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
};

// Initialize Sortable for each group
const initializeSortable = () => {
    nextTick(() => {
        Object.keys(groupRefs.value).forEach(groupId => {
            const element = groupRefs.value[groupId];
            if (element && !sortableInstances.value[groupId]) {
                sortableInstances.value[groupId] = new Sortable(element, {
                    group: 'list-tasks',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    chosenClass: 'sortable-chosen',
                    onStart: () => {
                        isDragging.value = true;
                    },
                    onEnd: (evt) => {
                        handleTaskMove(evt);
                        // Small delay to prevent click events from firing
                        setTimeout(() => {
                            isDragging.value = false;
                        }, 100);
                    },
                });
            }
        });
    });
};

// Handle task movement between groups
const handleTaskMove = async (evt) => {
    const taskId = evt.item.dataset.taskId;
    const newGroupId = evt.to.dataset.groupId;

    if (!taskId || !newGroupId) {
        console.error('Missing taskId or newGroupId:', { taskId, newGroupId });
        return;
    }

    try {
        // Update task via API
        await axios.put(route(`${props.recordType}.update`, taskId), {
            [props.groupByField]: parseInt(newGroupId),
        }, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        emit('task-updated', taskId);

        // Reload the page to refresh data
        router.reload({ only: ['tasks'] });
    } catch (error) {
        console.error('Error updating task:', error);
        // Revert the move on error
        router.reload({ only: ['tasks'] });
    }
};

// Watch for changes in group structure (not individual tasks)
watch(() => props.groupOptions.length, () => {
    // Only reinitialize if group structure changes
    nextTick(() => {
        // Destroy existing instances
        Object.values(sortableInstances.value).forEach(instance => {
            if (instance) instance.destroy();
        });
        sortableInstances.value = {};
        
        // Reinitialize
        initializeSortable();
    });
});

onMounted(() => {
    initializeSortable();
});
</script>

<template>
    <div class="space-y-6">
        <div
            v-for="group in groupedTasks"
            :key="group.id"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
        >
            <!-- Group Header -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div
                            :class="[
                                'w-3 h-3 rounded-full',
                                `bg-${group.color}-500`
                            ]"
                        ></div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ group.name }}
                        </h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            {{ group.tasks.length }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tasks List -->
            <div
                :ref="el => groupRefs[group.id] = el"
                :data-group-id="group.id"
                class="min-h-[100px] divide-y divide-gray-200 dark:divide-gray-700"
            >
                <div
                    v-for="task in group.tasks"
                    :key="task.id"
                    :data-task-id="task.id"
                    class="px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                    @click="handleTaskClick(task)"
                >
                        <div class="flex items-start justify-between">
                            <!-- Task Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2 mb-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ task.display_name }}
                                    </h4>
                                    <!-- Priority Badge (if grouping by status) -->
                                    <span
                                        v-if="groupByField === 'status_id' && task.priority_id"
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                            getColorClasses(enumOptions['App\\Enums\\Tasks\\Priority']?.find(p => p.id === task.priority_id)?.color || 'gray')
                                        ]"
                                    >
                                        {{ enumOptions['App\\Enums\\Tasks\\Priority']?.find(p => p.id === task.priority_id)?.name || 'Unknown' }}
                                    </span>
                                </div>

                                <p v-if="task.notes" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-1 mb-2">
                                    {{ task.notes }}
                                </p>

                                <!-- Task Meta -->
                                <div class="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    <!-- Assigned User -->
                                    <div v-if="task.assigned" class="flex items-center space-x-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>{{ task.assigned.display_name }}</span>
                                    </div>

                                    <!-- Created Date -->
                                    <div class="flex items-center space-x-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ new Date(task.created_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Due Date Badge -->
                            <div v-if="task.due_date" class="ml-4 flex-shrink-0">
                                <div :class="[
                                    'flex items-center justify-center rounded-lg px-2 py-1 text-xs font-medium whitespace-nowrap',
                                    getColorClasses(formatDate(task.due_date)?.color)
                                ]">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ formatDate(task.due_date)?.text }}
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Empty State -->
                <div v-if="group.tasks.length === 0" class="px-4 py-8 text-center">
                    <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No tasks in this group
                    </p>
                </div>
            </div>

            <!-- Add Task Button -->
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <button
                    @click="handleCreateTask(group.id)"
                    type="button"
                    class="flex w-full items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white py-2 font-medium text-gray-500 hover:border-primary-700 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-white transition-colors"
                >
                    <svg class="-ms-0.5 me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7 7V5"></path>
                    </svg>
                    Add new task
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.4;
}

.sortable-drag {
    opacity: 0.8;
}

.sortable-chosen {
    opacity: 1;
    background-color: rgb(249 250 251);
}

.dark .sortable-chosen {
    background-color: rgb(55 65 81 / 0.5);
}

.task-drag-handle {
    cursor: move;
}

.task-drag-handle:active {
    cursor: grabbing;
}
</style>