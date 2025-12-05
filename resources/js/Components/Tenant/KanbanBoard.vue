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

const emit = defineEmits(['task-updated', 'task-clicked']);

const columnRefs = ref({});
const sortableInstances = ref({});

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

// Initialize Sortable for each column
const initializeSortable = () => {
    nextTick(() => {
        Object.keys(columnRefs.value).forEach(columnId => {
            const element = columnRefs.value[columnId];
            if (element && !sortableInstances.value[columnId]) {
                sortableInstances.value[columnId] = new Sortable(element, {
                    group: 'tasks',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    chosenClass: 'sortable-chosen',
                    handle: '.task-drag-handle',
                    onEnd: (evt) => {
                        handleTaskMove(evt);
                    },
                });
            }
        });
    });
};

// Handle task movement between columns
const handleTaskMove = async (evt) => {
    const taskId = evt.item.dataset.taskId;
    const newColumnId = evt.to.dataset.columnId;

    if (!taskId || !newColumnId) return;

    try {
        // Update task via API
        await axios.put(route(`${props.recordType}.update`, taskId), {
            [props.groupByField]: parseInt(newColumnId),
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

const handleTaskClick = (task) => {
    emit('task-clicked', task);
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

// Watch for tasks changes and reinitialize Sortable
watch(() => props.tasks, () => {
    // Destroy existing instances
    Object.values(sortableInstances.value).forEach(instance => {
        if (instance) instance.destroy();
    });
    sortableInstances.value = {};
    
    // Reinitialize
    initializeSortable();
}, { deep: true });

onMounted(() => {
    initializeSortable();
});
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-4">
        <div
            v-for="group in groupedTasks"
            :key="group.id"
            class="min-w-[22rem] flex-shrink-0"
        >
            <!-- Column Header -->
            <div class="flex items-center justify-between mb-4 px-1">
                <div class="flex items-center space-x-2">
                    <div
                        :class="[
                            'w-3 h-3 rounded-full',
                            `bg-${group.color}-500`
                        ]"
                    ></div>
                    <span class="text-base font-semibold text-gray-900 dark:text-gray-300">
                        {{ group.name }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        ({{ group.tasks.length }})
                    </span>
                </div>
            </div>

            <!-- Tasks Container -->
            <div
                :ref="el => columnRefs[group.id] = el"
                :data-column-id="group.id"
                class="space-y-3 min-h-[200px] p-2 rounded-lg bg-gray-50 dark:bg-gray-900/50"
            >
                <!-- Task Card -->
                <div
                    v-for="task in group.tasks"
                    :key="task.id"
                    :data-task-id="task.id"
                    class="task-drag-handle flex flex-col rounded-lg bg-white p-4 shadow-sm hover:shadow-md transition-shadow cursor-move dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                    @click="handleTaskClick(task)"
                >
                    <!-- Task Header -->
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex-1">
                            {{ task.display_name }}
                        </h4>
                        <button
                            type="button"
                            class="ml-2 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-white"
                            @click.stop
                        >
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M11.3 6.2H5a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h11c1.1 0 2-1 2-2.1V11l-4 4.2c-.3.3-.7.6-1.2.7l-2.7.6c-1.7.3-3.3-1.3-3-3.1l.6-2.9c.1-.5.4-1 .7-1.3l3-3.1Z" clip-rule="evenodd"></path>
                                <path fill-rule="evenodd" d="M19.8 4.3a2.1 2.1 0 0 0-1-1.1 2 2 0 0 0-2.2.4l-.6.6 2.9 3 .5-.6a2.1 2.1 0 0 0 .6-1.5c0-.2 0-.5-.2-.8Zm-2.4 4.4-2.8-3-4.8 5-.1.3-.7 3c0 .3.3.7.6.6l2.7-.6.3-.1 4.7-5Z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Task Description -->
                    <p v-if="task.notes" class="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                        {{ task.notes }}
                    </p>

                    <!-- Task Footer -->
                    <div class="flex items-center justify-between mt-auto pt-2">
                        <!-- Assigned User -->
                        <div v-if="task.assigned" class="flex items-center">
                            <div class="flex items-center justify-center w-6 h-6 bg-primary-100 dark:bg-primary-900 rounded-full text-xs font-semibold text-primary-600 dark:text-primary-400">
                                {{ task.assigned.display_name?.charAt(0) || task.assigned.first_name?.charAt(0) || '?' }}
                            </div>
                        </div>
                        <div v-else class="w-6"></div>

                        <!-- Due Date Badge -->
                        <div v-if="task.due_date" :class="[
                            'flex items-center justify-center rounded-lg px-2 py-1 text-xs font-medium',
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
}

.task-drag-handle {
    cursor: move;
}

.task-drag-handle:active {
    cursor: grabbing;
}
</style>