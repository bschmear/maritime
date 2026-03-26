<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import KanbanBoard from '@/Components/Tenant/KanbanBoard.vue';
import TaskListView from '@/Components/Tenant/TaskListView.vue';
import Modal from '@/Components/Modal.vue';
import Form from '@/Components/Tenant/Form.vue';

/**
 * Reusable task board for a morph relatable (boat show event, lead, etc.).
 *
 * Usage:
 * <RelatableTasksBoard
 *   :tasks="tasks"
 *   :record="record"
 *   relatable-type="App\\Domain\\BoatShowEvent\\Models\\BoatShowEvent"
 *   :status-options="taskStatusOptions"
 *   :default-hidden-status-ids="[3, 4]"
 *   :task-form-schema="taskBoardFormSchema"
 *   :task-fields-schema="taskBoardFieldsSchema"
 *   :task-board-enum-options="taskBoardEnumOptions"
 *   :enum-options="enumOptions"
 *   :reload-only="['tasks']"
 * />
 */
const props = defineProps({
    tasks: { type: Array, required: true },
    /** Owning record (used for default relatable_id) */
    record: { type: Object, required: true },
    /** Laravel morph class for Task.relatable_type */
    relatableType: { type: String, required: true },
    relatableId: { type: [Number, String], default: null },
    /** Full status column list, e.g. App\Enums\Tasks\Status::options() */
    statusOptions: { type: Array, required: true },
    /** status_id values hidden until the user enables the column (e.g. Waiting=3, Blocked=4) */
    defaultHiddenStatusIds: { type: Array, default: () => [3, 4] },
    enumOptions: { type: Object, default: () => ({}) },
    /** Task Status / Priority options merged over enumOptions for Form + list badges */
    taskBoardEnumOptions: { type: Object, default: () => ({}) },
    /** Task form.json — if null, create/view modals are disabled */
    taskFormSchema: { type: Object, default: null },
    /** Task fields (unwrapped) — if null, create/view modals are disabled */
    taskFieldsSchema: { type: Object, default: null },
    /** Ziggy route prefix, e.g. 'tasks' */
    taskRecordType: { type: String, default: 'tasks' },
    taskRecordTitle: { type: String, default: 'Task' },
    /** Inertia partial reload keys */
    reloadOnly: { type: Array, default: () => ['tasks'] },
    /** Override Sortable group name (default: relatable-tasks-{id}) */
    sortableGroup: { type: String, default: null },
    showViewToggle: { type: Boolean, default: true },
});

const effectiveRelatableId = computed(() => props.relatableId ?? props.record.id);

const mergedEnumOptions = computed(() => ({
    ...props.enumOptions,
    ...props.taskBoardEnumOptions,
}));

const canUseTaskForms = computed(() => props.taskFormSchema && props.taskFieldsSchema);

const effectiveSortableGroup = computed(
    () => props.sortableGroup ?? `relatable-tasks-${effectiveRelatableId.value}`,
);

function initialVisibleIds() {
    const hidden = new Set(props.defaultHiddenStatusIds.map(Number));
    return props.statusOptions.map((o) => o.id).filter((id) => !hidden.has(Number(id)));
}

const visibleStatusIds = ref(initialVisibleIds());

watch(
    () =>
        `${props.statusOptions.map((o) => o.id).join(',')}|${props.defaultHiddenStatusIds.map(Number).join(',')}`,
    () => {
        visibleStatusIds.value = initialVisibleIds();
    },
);

const statusOrderIndex = computed(() =>
    Object.fromEntries(props.statusOptions.map((o, i) => [o.id, i])),
);

const visibleGroupOptions = computed(() => {
    const allowed = new Set(visibleStatusIds.value.map(Number));
    return props.statusOptions
        .filter((o) => allowed.has(Number(o.id)))
        .sort((a, b) => statusOrderIndex.value[a.id] - statusOrderIndex.value[b.id]);
});

const hiddenTasksCount = computed(() => {
    const allowed = new Set(visibleStatusIds.value.map(Number));
    return props.tasks.filter((t) => !allowed.has(Number(t.status_id))).length;
});

function toggleStatusColumn(statusId) {
    const id = Number(statusId);
    const idx = visibleStatusIds.value.findIndex((x) => Number(x) === id);
    if (idx >= 0) {
        if (visibleStatusIds.value.length <= 1) {
            return;
        }
        visibleStatusIds.value = visibleStatusIds.value.filter((x) => Number(x) !== id);
    } else {
        const next = [...visibleStatusIds.value, id];
        next.sort((a, b) => statusOrderIndex.value[a] - statusOrderIndex.value[b]);
        visibleStatusIds.value = next;
    }
}

function isColumnVisible(id) {
    return visibleStatusIds.value.some((x) => Number(x) === Number(id));
}

const currentView = ref('kanban');
const showColumnsMenu = ref(false);

const showViewModal = ref(false);
const showCreateModal = ref(false);
const selectedRecord = ref(null);
const isLoadingRecord = ref(false);
const prePopulatedData = ref({});

function openCreateModal(groupId = null) {
    if (!canUseTaskForms.value) {
        return;
    }
    prePopulatedData.value = {
        relatable_type: props.relatableType,
        relatable_id: effectiveRelatableId.value,
    };
    if (groupId !== null) {
        prePopulatedData.value.status_id = groupId;
    }
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
    prePopulatedData.value = {};
}

function handleTaskCreated() {
    router.reload({ only: props.reloadOnly });
    closeCreateModal();
}

async function handleViewTask(task) {
    if (!canUseTaskForms.value) {
        router.visit(route(`${props.taskRecordType}.show`, task.id));
        return;
    }
    isLoadingRecord.value = true;
    showViewModal.value = true;
    try {
        const response = await axios.get(route(`${props.taskRecordType}.show`, task.id), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        selectedRecord.value = response.data?.record ?? task;
    } catch {
        selectedRecord.value = task;
    } finally {
        isLoadingRecord.value = false;
    }
}

function closeViewModal() {
    showViewModal.value = false;
    selectedRecord.value = null;
}

function handleTaskUpdated() {
    router.reload({ only: props.reloadOnly });
    closeViewModal();
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-show="showColumnsMenu"
            class="fixed inset-0 z-40"
            aria-hidden="true"
            @click="showColumnsMenu = false"
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div
                v-if="hiddenTasksCount > 0"
                class="text-xs text-amber-700 dark:text-amber-300"
            >
                {{ hiddenTasksCount }} task(s) are in hidden columns — show those columns to view or move them.
            </div>
            <div v-else class="hidden sm:block" />

            <div class="relative z-50 flex flex-wrap items-center gap-2 justify-end">
                <div class="relative">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="showColumnsMenu = !showColumnsMenu"
                    >
                        <span class="material-icons text-[18px]">view_column</span>
                        Columns
                    </button>
                    <div
                        v-show="showColumnsMenu"
                        class="absolute right-0 z-50 mt-1 w-56 rounded-lg border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-600 dark:bg-gray-800"
                    >
                        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Status columns
                        </p>
                        <label
                            v-for="opt in statusOptions"
                            :key="opt.id"
                            class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/80"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                :checked="isColumnVisible(opt.id)"
                                @change="toggleStatusColumn(opt.id)"
                            />
                            <span class="text-gray-800 dark:text-gray-200">{{ opt.name }}</span>
                        </label>
                    </div>
                </div>

                <button
                    v-if="canUseTaskForms"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="openCreateModal()"
                >
                    <span class="material-icons text-[18px]">add</span>
                    Add task
                </button>

                <div
                    v-if="showViewToggle"
                    class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600"
                    role="group"
                >
                    <button
                        type="button"
                        :class="[
                            'px-3 py-2 text-sm font-medium rounded-l-lg',
                            currentView === 'kanban'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                        ]"
                        @click="currentView = 'kanban'"
                    >
                        Kanban
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-3 py-2 text-sm font-medium rounded-r-lg border-l border-gray-200 dark:border-gray-600',
                            currentView === 'list'
                                ? 'bg-primary-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                        ]"
                        @click="currentView = 'list'"
                    >
                        List
                    </button>
                </div>
            </div>
        </div>

        <KanbanBoard
            v-if="currentView === 'kanban'"
            :tasks="tasks"
            group-by-field="status_id"
            :group-options="visibleGroupOptions"
            :record-type="taskRecordType"
            :fields-schema="taskFieldsSchema || {}"
            :enum-options="mergedEnumOptions"
            :reload-only="reloadOnly"
            :sortable-group="effectiveSortableGroup"
            @task-updated="handleTaskUpdated"
            @view-task="handleViewTask"
            @create-task="openCreateModal"
        />

        <TaskListView
            v-else
            :tasks="tasks"
            group-by-field="status_id"
            :group-options="visibleGroupOptions"
            :record-type="taskRecordType"
            :fields-schema="taskFieldsSchema || {}"
            :enum-options="mergedEnumOptions"
            :reload-only="reloadOnly"
            :sortable-group="`${effectiveSortableGroup}-list`"
            @task-updated="handleTaskUpdated"
            @view-task="handleViewTask"
            @create-task="openCreateModal"
        />

        <Modal :show="showViewModal" max-width="4xl" @close="closeViewModal">
            <div class="flex flex-shrink-0 items-start justify-between border-b p-4 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ selectedRecord ? `Edit ${taskRecordTitle}` : '' }}
                </h3>
                <button
                    type="button"
                    class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                    @click="closeViewModal"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 14 14">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                </button>
            </div>
            <div class="max-h-[70vh] flex-1 overflow-y-auto">
                <div v-if="isLoadingRecord" class="flex items-center justify-center py-8">
                    <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-primary-600" />
                    <span class="ml-2 text-gray-600 dark:text-gray-400">Loading…</span>
                </div>
                <div v-else-if="selectedRecord && taskFormSchema && taskFieldsSchema">
                    <Form
                        :schema="taskFormSchema"
                        :fields-schema="taskFieldsSchema"
                        :record="selectedRecord"
                        :record-type="taskRecordType"
                        :record-title="taskRecordTitle"
                        :enum-options="mergedEnumOptions"
                        mode="edit"
                        :prevent-redirect="true"
                        @updated="handleTaskUpdated"
                        @submit="handleTaskUpdated"
                        @cancel="closeViewModal"
                    />
                </div>
            </div>
        </Modal>

        <Modal :show="showCreateModal" max-width="4xl" @close="closeCreateModal">
            <div class="flex flex-shrink-0 items-start justify-between border-b p-4 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Create {{ taskRecordTitle }}
                </h3>
                <button
                    type="button"
                    class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                    @click="closeCreateModal"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 14 14">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                </button>
            </div>
            <div class="max-h-[70vh] flex-1 overflow-y-auto">
                <Form
                    v-if="taskFormSchema && taskFieldsSchema"
                    :schema="taskFormSchema"
                    :fields-schema="taskFieldsSchema"
                    :record="prePopulatedData"
                    :record-type="taskRecordType"
                    :record-title="taskRecordTitle"
                    :enum-options="mergedEnumOptions"
                    mode="create"
                    :prevent-redirect="true"
                    @created="handleTaskCreated"
                    @submit="handleTaskCreated"
                    @cancel="closeCreateModal"
                />
            </div>
        </Modal>
    </div>
</template>
