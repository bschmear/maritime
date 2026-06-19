<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import axios from 'axios';

const props = defineProps({
    leads: {
        type: Array,
        default: () => [],
    },
    priorityOptions: {
        type: Array,
        default: () => [],
    },
    recordType: {
        type: String,
        default: 'leads',
    },
    reloadOnly: {
        type: Array,
        default: () => ['openLeads', 'kanbanLeads', 'stats', 'charts', 'records'],
    },
});

const emit = defineEmits(['lead-updated']);

const columnRefs = ref({});
const sortableInstances = ref({});
const isDragging = ref(false);
const updatingId = ref(null);

const UNSET_PRIORITY_ID = 0;

const columns = computed(() => {
    const opts = [
        { id: UNSET_PRIORITY_ID, name: 'Unset', color: 'gray' },
        ...props.priorityOptions.map((o) => ({
            id: Number(o.id),
            name: o.name,
            color: o.color ?? 'gray',
        })),
    ];

    return opts.map((col) => ({
        ...col,
        leads: props.leads.filter((lead) => {
            const pid = lead.priority_id;
            if (col.id === UNSET_PRIORITY_ID) {
                return pid == null || pid === '' || Number(pid) === 0;
            }

            return Number(pid) === Number(col.id);
        }),
    }));
});

function leadShowHref(id) {
    return route(`${props.recordType}.show`, id);
}

function formatFollowUp(val) {
    if (!val) {
        return null;
    }
    const d = new Date(val.length === 10 ? `${val}T12:00:00` : val);
    if (Number.isNaN(d.getTime())) {
        return val;
    }

    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

const columnDotClass = (color) => {
    const map = {
        red: 'bg-red-500',
        orange: 'bg-orange-500',
        yellow: 'bg-yellow-500',
        green: 'bg-green-500',
        blue: 'bg-blue-500',
        gray: 'bg-gray-500',
    };

    return map[color] || map.gray;
};

async function updateLeadPriority(leadId, columnId) {
    const priorityId = Number(columnId) === UNSET_PRIORITY_ID ? null : Number(columnId);
    updatingId.value = leadId;

    try {
        await axios.put(
            route(`${props.recordType}.update`, leadId),
            { priority_id: priorityId },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            },
        );
        emit('lead-updated', leadId);
        router.reload({ only: props.reloadOnly, preserveScroll: true });
    } catch (error) {
        console.error('Error updating lead priority:', error);
        router.reload({ only: props.reloadOnly, preserveScroll: true });
    } finally {
        updatingId.value = null;
    }
}

const handleLeadMove = async (evt) => {
    const leadId = evt.item.dataset.leadId;
    const newColumnId = evt.to.dataset.columnId;

    if (!leadId || newColumnId === undefined || newColumnId === '') {
        return;
    }

    await updateLeadPriority(leadId, newColumnId);
};

const initializeSortable = () => {
    nextTick(() => {
        Object.keys(columnRefs.value).forEach((columnId) => {
            const element = columnRefs.value[columnId];
            if (element && !sortableInstances.value[columnId]) {
                sortableInstances.value[columnId] = new Sortable(element, {
                    group: 'lead-priority-kanban',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    chosenClass: 'sortable-chosen',
                    handle: '.lead-drag-handle',
                    onStart: () => {
                        isDragging.value = true;
                    },
                    onEnd: (evt) => {
                        handleLeadMove(evt);
                        setTimeout(() => {
                            isDragging.value = false;
                        }, 100);
                    },
                });
            }
        });
    });
};

watch(
    () => columns.value.map((c) => `${c.id}:${c.leads.length}`).join('|'),
    () => {
        nextTick(() => {
            Object.values(sortableInstances.value).forEach((instance) => {
                instance?.destroy();
            });
            sortableInstances.value = {};
            initializeSortable();
        });
    },
);

watch(
    () => props.leads.length,
    () => {
        nextTick(() => {
            Object.values(sortableInstances.value).forEach((instance) => {
                instance?.destroy();
            });
            sortableInstances.value = {};
            initializeSortable();
        });
    },
);

onMounted(() => {
    initializeSortable();
});
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-2">
        <div
            v-for="col in columns"
            :key="col.id"
            class="flex w-[18rem] shrink-0 flex-col rounded-lg bg-gray-100 dark:bg-gray-900/50"
        >
            <div class="flex items-center gap-2 p-3">
                <span :class="['h-3 w-3 rounded-full', columnDotClass(col.color)]" />
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-200">{{ col.name }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">({{ col.leads.length }})</span>
            </div>

            <div
                :ref="(el) => { columnRefs[col.id] = el; }"
                :data-column-id="col.id"
                class="min-h-[160px] flex-1 space-y-2 p-2"
            >
                <div
                    v-for="lead in col.leads"
                    :key="lead.id"
                    :data-lead-id="lead.id"
                    class="lead-drag-handle rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    :class="updatingId === lead.id ? 'opacity-60' : 'cursor-move'"
                >
                    <Link
                        :href="leadShowHref(lead.id)"
                        class="block text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                        @click.stop
                    >
                        {{ lead.display_name || `Lead #${lead.id}` }}
                    </Link>
                    <p v-if="lead.email" class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ lead.email }}
                    </p>
                    <p
                        v-if="formatFollowUp(lead.next_followup_at)"
                        class="mt-2 flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400"
                    >
                        <span class="material-icons text-[13px] text-gray-400">event</span>
                        {{ formatFollowUp(lead.next_followup_at) }}
                    </p>
                    <p v-if="lead.assigned_user?.display_name" class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ lead.assigned_user.display_name }}
                    </p>
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
    opacity: 0.95;
}
</style>
