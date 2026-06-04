<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch, getCurrentInstance } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import axios from 'axios';

/** Open → Completed (closed/cancelled are table-only). */
const PRIMARY_STATUS_IDS = [2, 3, 4, 5, 6, 7];

const props = defineProps({
    workOrders: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'workorders' },
    reloadOnly: { type: Array, default: () => ['kanbanRecords', 'records'] },
});

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
        return;
    }
    const toast = inertiaApp?.appContext?.config?.globalProperties?.$toast;
    if (typeof toast === 'function') {
        toast(type, String(message));
    }
}

const localWorkOrders = ref([]);
const columnRefs = ref({});
const sortableInstances = ref({});
const isDragging = ref(false);
const showColumnsMenu = ref(false);

watch(
    () => props.workOrders,
    (list) => {
        localWorkOrders.value = list.map((wo) => ({ ...wo }));
    },
    { immediate: true, deep: true },
);

const statusOrderIndex = computed(() =>
    Object.fromEntries(props.statusOptions.map((o, i) => [o.id, i])),
);

const primaryStatusOptions = computed(() =>
    props.statusOptions.filter((o) => PRIMARY_STATUS_IDS.includes(Number(o.id))),
);

function initialVisiblePrimaryIds() {
    return [...PRIMARY_STATUS_IDS];
}

const visiblePrimaryStatusIds = ref(initialVisiblePrimaryIds());

watch(
    () => props.statusOptions.map((o) => o.id).join(','),
    () => {
        visiblePrimaryStatusIds.value = initialVisiblePrimaryIds();
    },
);

const visiblePrimaryColumns = computed(() =>
    primaryStatusOptions.value
        .filter((o) => visiblePrimaryStatusIds.value.includes(Number(o.id)))
        .sort((a, b) => statusOrderIndex.value[a.id] - statusOrderIndex.value[b.id]),
);

const hiddenWorkOrderCount = computed(() => {
    const allowed = new Set(visiblePrimaryStatusIds.value.map(Number));
    return localWorkOrders.value.filter(
        (wo) => PRIMARY_STATUS_IDS.includes(Number(wo.status)) && !allowed.has(Number(wo.status)),
    ).length;
});

function togglePrimaryColumn(statusId) {
    const id = Number(statusId);
    const idx = visiblePrimaryStatusIds.value.findIndex((x) => Number(x) === id);
    if (idx >= 0) {
        if (visiblePrimaryStatusIds.value.length <= 1) {
            return;
        }
        visiblePrimaryStatusIds.value = visiblePrimaryStatusIds.value.filter((x) => Number(x) !== id);
    } else {
        const next = [...visiblePrimaryStatusIds.value, id];
        next.sort((a, b) => statusOrderIndex.value[a] - statusOrderIndex.value[b]);
        visiblePrimaryStatusIds.value = next;
    }
}

function isColumnVisible(id) {
    return visiblePrimaryStatusIds.value.some((x) => Number(x) === Number(id));
}

const workOrdersByStatus = (statusId) =>
    localWorkOrders.value.filter((wo) => Number(wo.status) === Number(statusId));

const priorityOption = (wo) => {
    const list = props.enumOptions['App\\Enums\\WorkOrder\\Priority'];
    if (!list?.length || wo.priority == null) {
        return null;
    }
    return list.find((p) => Number(p.id) === Number(wo.priority)) ?? null;
};

const statusOption = (statusId) =>
    props.statusOptions.find((o) => Number(o.id) === Number(statusId)) ?? null;

const assignedLabel = (wo) => {
    const u = wo.assigned_user;
    if (!u) {
        return 'Unassigned';
    }
    return u.display_name || [u.first_name, u.last_name].filter(Boolean).join(' ') || u.email || 'Unassigned';
};

const columnDotClass = (color) => {
    const map = {
        red: 'bg-red-500',
        orange: 'bg-orange-500',
        yellow: 'bg-yellow-500',
        green: 'bg-green-500',
        blue: 'bg-blue-500',
        indigo: 'bg-indigo-500',
        gray: 'bg-gray-500',
        slate: 'bg-slate-500',
    };
    return map[color] || map.gray;
};

const priorityBadgeClass = (priority) => priority?.bgClass || 'bg-gray-200 dark:bg-gray-700 dark:text-gray-200';

const formatWhen = (iso) => {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return null;
    }
};

const handleCardMove = async (evt) => {
    const workOrderId = evt.item.dataset.workOrderId;
    const newStatusId = parseInt(evt.to.dataset.columnId, 10);

    if (!workOrderId || !newStatusId) {
        return;
    }

    const wo = localWorkOrders.value.find((w) => String(w.id) === String(workOrderId));
    const previousStatus = wo ? Number(wo.status) : null;

    if (wo) {
        wo.status = newStatusId;
    }

    const statusName = statusOption(newStatusId)?.name ?? 'updated';

    try {
        await axios.put(
            route(`${props.recordType}.update`, workOrderId),
            { status: newStatusId },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            },
        );
        showToast('success', `Work order moved to ${statusName}`);
    } catch (error) {
        console.error('Error updating work order status:', error);
        if (wo && previousStatus !== null) {
            wo.status = previousStatus;
        }
        showToast('error', 'Could not update work order status');
        router.reload({ only: props.reloadOnly, preserveScroll: true });
    }
};

const initializeSortable = () => {
    nextTick(() => {
        Object.keys(columnRefs.value).forEach((columnId) => {
            const element = columnRefs.value[columnId];
            if (element && !sortableInstances.value[columnId]) {
                sortableInstances.value[columnId] = new Sortable(element, {
                    group: 'work-orders-kanban',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    handle: '.wo-drag-handle',
                    swapThreshold: 0.65,
                    onStart: () => {
                        isDragging.value = true;
                    },
                    onEnd: (evt) => {
                        const fromStatus = evt.from?.dataset?.columnId;
                        const toStatus = evt.to?.dataset?.columnId;
                        if (fromStatus !== toStatus) {
                            handleCardMove(evt);
                        }
                        setTimeout(() => {
                            isDragging.value = false;
                        }, 100);
                    },
                });
            }
        });
    });
};

const destroySortable = () => {
    Object.values(sortableInstances.value).forEach((instance) => {
        if (instance) {
            instance.destroy();
        }
    });
    sortableInstances.value = {};
};

watch(
    () => visiblePrimaryColumns.value.map((c) => c.id).join(','),
    () => {
        destroySortable();
        initializeSortable();
    },
);

onMounted(() => {
    initializeSortable();
});

onBeforeUnmount(() => {
    destroySortable();
});
</script>

<template>
    <div class="flex flex-col min-h-[calc(100vh-14rem)]">
        <div
            v-show="showColumnsMenu"
            class="fixed inset-0 z-40"
            aria-hidden="true"
            @click="showColumnsMenu = false"
        />

        <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
            <p
                v-if="hiddenWorkOrderCount > 0"
                class="mr-auto text-xs text-amber-700 dark:text-amber-300"
            >
                {{ hiddenWorkOrderCount }} work order(s) in hidden columns.
            </p>
            <div class="relative z-50">
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
                        Board columns
                    </p>
                    <label
                        v-for="opt in primaryStatusOptions"
                        :key="opt.id"
                        class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/80"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            :checked="isColumnVisible(opt.id)"
                            @change="togglePrimaryColumn(opt.id)"
                        />
                        <span class="text-gray-800 dark:text-gray-200">{{ opt.name }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex flex-1 gap-4 overflow-x-auto pb-4">
            <div
                v-for="column in visiblePrimaryColumns"
                :key="column.id"
                class="flex w-[18rem] shrink-0 flex-col rounded-lg bg-gray-100 dark:bg-gray-800"
            >
                <div class="flex items-center gap-2 p-3">
                    <div :class="['h-3 w-3 rounded-full', columnDotClass(column.color)]" />
                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-200">{{ column.name }}</span>
                    <span class="text-xs text-gray-500">({{ workOrdersByStatus(column.id).length }})</span>
                </div>
                <div
                    :ref="(el) => { if (el) columnRefs[column.id] = el; }"
                    :data-column-id="column.id"
                    class="min-h-[160px] flex-1 space-y-2 overflow-y-auto p-2"
                >
                    <div
                        v-for="wo in workOrdersByStatus(column.id)"
                        :key="wo.id"
                        :data-work-order-id="wo.id"
                        class="wo-drag-handle cursor-move rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <Link
                            :href="route('workorders.show', wo.id)"
                            class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
                            @click.stop
                        >
                            {{ wo.display_name }}
                        </Link>
                        <p v-if="wo.customer?.display_name" class="mt-1 truncate text-xs text-gray-500">
                            {{ wo.customer.display_name }}
                        </p>
                        <p class="mt-1 flex items-center gap-1 truncate text-xs text-gray-600 dark:text-gray-400">
                            <span class="material-icons text-[14px] text-gray-400">person</span>
                            <span class="truncate">{{ assignedLabel(wo) }}</span>
                        </p>
                        <div v-if="priorityOption(wo)" class="mt-2">
                            <span
                                class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="priorityBadgeClass(priorityOption(wo))"
                            >
                                {{ priorityOption(wo).name }}
                            </span>
                        </div>
                        <p v-if="formatWhen(wo.scheduled_start_at)" class="mt-1 text-[10px] text-gray-400">
                            {{ formatWhen(wo.scheduled_start_at) }}
                        </p>
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
    opacity: 0.9;
}
</style>
