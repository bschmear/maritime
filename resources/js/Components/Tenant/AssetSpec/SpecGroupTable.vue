<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Sortable from 'sortablejs';

const props = defineProps({
    groupBlockKey: {
        type: String,
        required: true,
    },
    groupLabel: {
        type: String,
        required: true,
    },
    specs: {
        type: Array,
        default: () => [],
    },
    assetTypeColumns: {
        type: Array,
        default: () => [],
    },
    savingSpecId: {
        default: null,
    },
    disableDrag: {
        type: Boolean,
        default: false,
    },
    isFirst: { type: Boolean, default: false },
    isLast:  { type: Boolean, default: false },
});

const emit = defineEmits([
    'reorder',
    'toggle-asset-type',
    'move-group'
]);

/**
 * -----------------------------
 * Helpers
 * -----------------------------
 */
const normalizeAssetTypes = (raw) => {
    if (!raw || !Array.isArray(raw)) return [];
    return [...new Set(raw.map((t) => Number(t)).filter((n) => !Number.isNaN(n)))];
};

const hasAssetType = (spec, typeId) => {
    return normalizeAssetTypes(spec.asset_types).includes(typeId);
};

/**
 * -----------------------------
 * State
 * -----------------------------
 */
const listEl = ref(null);
const localSpecs = ref([]);
const checkboxStates = ref({});
const pendingUpdates = ref({}); // 🔥 NEW
let sortable = null;

/**
 * -----------------------------
 * Checkbox State Init
 * -----------------------------
 */
const initCheckboxStates = (specs) => {
    const states = {};

    specs.forEach(spec => {
        states[spec.id] = {};

        props.assetTypeColumns.forEach(col => {
            states[spec.id][col.id] = hasAssetType(spec, col.id);
        });
    });

    return states;
};

/**
 * -----------------------------
 * Watch Specs (FIXED SYNC)
 * -----------------------------
 */
watch(
    () => props.specs,
    (specs) => {
        localSpecs.value = specs.map(spec => ({ ...spec }));

        // First load
        if (Object.keys(checkboxStates.value).length === 0) {
            checkboxStates.value = initCheckboxStates(specs);
            return;
        }

        specs.forEach(spec => {
            if (!checkboxStates.value[spec.id]) {
                checkboxStates.value[spec.id] = {};
            }

            props.assetTypeColumns.forEach(col => {
                const key = `${spec.id}_${col.id}`;
                const serverState = hasAssetType(spec, col.id);

                // 🔥 CRITICAL FIX: don't overwrite while user is interacting
                if (!pendingUpdates.value[key]) {
                    checkboxStates.value[spec.id][col.id] = serverState;
                }
            });
        });

        checkboxStates.value = { ...checkboxStates.value };
    },
    { immediate: true, deep: true }
);

/**
 * -----------------------------
 * Sortable
 * -----------------------------
 */
const destroySortable = () => {
    sortable?.destroy();
    sortable = null;
};

const initSortable = () => {
    destroySortable();

    if (!listEl.value || props.disableDrag || localSpecs.value.length === 0) return;

    sortable = Sortable.create(listEl.value, {
        draggable: 'tr',
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        filter: '.drag-disabled',

        onEnd(evt) {
            const moved = localSpecs.value.splice(evt.oldIndex, 1)[0];
            localSpecs.value.splice(evt.newIndex, 0, moved);

            emit('reorder', props.groupBlockKey, [...localSpecs.value]);
        },
    });
};

watch(
    () => [props.specs, props.disableDrag, props.groupBlockKey],
    () => nextTick(() => initSortable()),
    { deep: true }
);

onMounted(() => nextTick(() => initSortable()));
onBeforeUnmount(() => destroySortable());

/**
 * -----------------------------
 * Checkbox Helpers
 * -----------------------------
 */
const isChecked = (specId, typeId) => {
    return checkboxStates.value[specId]?.[typeId] || false;
};

const isPending = (specId, typeId) => {
    return !!pendingUpdates.value[`${specId}_${typeId}`];
};

const onToggleAssetType = async ({ spec, typeId, checked, done }) => {
    try {
        await api.updateSpec(spec.id, typeId, checked);
    } finally {
        done(); // 🔥 required
    }
};

const onToggleType = (spec, typeId) => {
    if (!checkboxStates.value[spec.id]) {
        checkboxStates.value[spec.id] = {};
    }

    const current = checkboxStates.value[spec.id][typeId] || false;
    const checked = !current; // 🔥 manual toggle

    const key = `${spec.id}_${typeId}`;

    // mark pending
    pendingUpdates.value[key] = true;

    // optimistic UI update
    checkboxStates.value[spec.id][typeId] = checked;
    checkboxStates.value = { ...checkboxStates.value };

    emit('toggle-asset-type', {
        spec,
        typeId,
        checked,
        done: () => {
            delete pendingUpdates.value[key];
        }
    });
};
</script>

<template>
    <div class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
        <div class="bg-gray-50 px-4 py-2 dark:bg-gray-900/40 sm:px-6 flex justify-between items-center">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ groupLabel }}
            </h3>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="isFirst"
                    class="rounded p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                    title="Move group up"
                    @click="emit('move-group', { blockKey: groupBlockKey, direction: 'up' })"
                >
                    <span class="material-icons text-[18px]">arrow_upward</span>
                </button>
                <button
                    type="button"
                    :disabled="isLast"
                    class="rounded p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                    title="Move group down"
                    @click="emit('move-group', { blockKey: groupBlockKey, direction: 'down' })"
                >
                    <span class="material-icons text-[18px]">arrow_downward</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                    <tr>
                        <th
                            scope="col"
                            class="w-10 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400"
                        >
                            <span class="sr-only">Reorder</span>
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400"
                        >
                            Specification
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400 md:table-cell"
                        >
                            Field type
                        </th>
                        <th
                            v-for="col in assetTypeColumns"
                            :key="col.id"
                            scope="col"
                            class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody ref="listEl" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                        v-for="spec in localSpecs"
                        :key="spec.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        :class="spec.is_required ? 'drag-disabled' : ''"
                    >
                        <td class="px-2 py-3 align-middle">
                            <button
                                type="button"
                                :class="[
                                    'drag-handle flex items-center justify-center rounded p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                                    disableDrag || spec.is_required
                                        ? 'cursor-not-allowed opacity-30 drag-disabled pointer-events-none'
                                        : 'cursor-move',
                                ]"
                                :tabindex="disableDrag || spec.is_required ? -1 : 0"
                            >
                                <span class="material-icons text-[18px]">drag_indicator</span>
                            </button>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ spec.label }}
                                <span
                                    v-if="spec.is_required"
                                    class="ml-1.5 align-middle text-xs font-normal text-red-500"
                                    title="Required"
                                >
                                    *
                                </span>
                            </p>
                            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ spec.key }}
                            </p>
                        </td>
                        <td class="hidden px-4 py-3 align-middle text-sm text-gray-700 dark:text-gray-300 md:table-cell">
                            {{ spec.type }}
                        </td>
                        <td
                            v-for="col in assetTypeColumns"
                            :key="col.id"
                            class="px-2 py-3 text-center align-middle"
                        >
                            <label class="inline-flex cursor-pointer items-center justify-center">
                                <button
    type="button"
    class="flex items-center justify-center w-6 h-6 rounded transition"
    :class="[
        isChecked(spec.id, col.id)
            ? 'text-primary-600'
            : 'text-gray-400 hover:text-gray-600',
        (savingSpecId === spec.id || isPending(spec.id, col.id))
            ? 'opacity-50 cursor-not-allowed'
            : 'cursor-pointer'
    ]"
    :disabled="savingSpecId === spec.id || isPending(spec.id, col.id)"
    @click="onToggleType(spec, col.id)"
>
    <span class="material-icons text-[20px]">
        {{ isChecked(spec.id, col.id) ? 'check_box' : 'check_box_outline_blank' }}
    </span>
</button>
                                <span class="sr-only">{{ col.label }} for {{ spec.label }}</span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.4;
}
.sortable-chosen {
    @apply shadow-md ring-2 ring-primary-300 dark:ring-primary-600;
}
</style>