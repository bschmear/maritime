<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';

const props = defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
});

const listEl = ref(null);
const localGroups = ref([]);
let sortable = null;

const newKey = ref('');
const newName = ref('');
const adding = ref(false);

const editingId = ref(null);
const editingName = ref('');

watch(
    () => props.groups,
    (g) => {
        localGroups.value = [...(g || [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        if (editingId.value != null && !localGroups.value.some((x) => x.id === editingId.value)) {
            editingId.value = null;
        }
    },
    { immediate: true, deep: true },
);

const destroySortable = () => {
    sortable?.destroy();
    sortable = null;
};

const initSortable = () => {
    destroySortable();
    if (!listEl.value || localGroups.value.length < 2) return;

    sortable = Sortable.create(listEl.value, {
        handle: '.group-drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd() {
            const rows = [...listEl.value.querySelectorAll('[data-group-id]')];
            const ordered = rows
                .map((el) => localGroups.value.find((g) => String(g.id) === el.dataset.groupId))
                .filter(Boolean);
            if (ordered.length !== localGroups.value.length) return;
            localGroups.value = ordered;
            const payload = ordered.map((g, i) => ({ id: g.id, position: i + 1 }));
            router.post(route('spec-groups.reorder'), { groups: payload }, { preserveScroll: true, preserveState: true });
        },
    });
};

watch(
    () => props.groups,
    () => nextTick(() => initSortable()),
    { deep: true },
);

onMounted(() => nextTick(() => initSortable()));
onBeforeUnmount(() => destroySortable());

const createGroup = () => {
    if (!newKey.value.trim() || !newName.value.trim()) return;
    router.post(
        route('spec-groups.store'),
        { key: newKey.value.trim(), name: newName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                newKey.value = '';
                newName.value = '';
                adding.value = false;
            },
        },
    );
};

const startEdit = (g) => {
    editingId.value = g.id;
    editingName.value = g.name || '';
};

const cancelEdit = () => {
    editingId.value = null;
    editingName.value = '';
};

const saveEdit = (g) => {
    const name = editingName.value.trim();
    if (!name) return;
    router.put(
        route('spec-groups.update', { specGroup: g.id }),
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => cancelEdit(),
            onError: () => cancelEdit(),
        },
    );
};

const removeGroup = (g) => {
    if (
        !window.confirm(
            `Remove group “${g.name}”? Specs in this group will become ungrouped. This cannot be undone from the UI (the group is deactivated).`,
        )
    ) {
        return;
    }
    router.delete(route('spec-groups.destroy', { specGroup: g.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <div id="asset-spec-groups" class="scroll-mt-24 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Spec groups</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Add, rename, or remove groups. Drag groups to change section order. Drag specs between sections in the tables below.
                </p>
            </div>
            <button
                type="button"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                @click="adding = !adding"
            >
                {{ adding ? 'Cancel' : 'Add group' }}
            </button>
        </div>

        <div v-if="adding" class="mb-4 flex flex-wrap items-end gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Key</label>
                <input
                    v-model="newKey"
                    type="text"
                    placeholder="e.g. rigging"
                    class="w-40 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Display name</label>
                <input
                    v-model="newName"
                    type="text"
                    placeholder="e.g. Rigging"
                    class="w-48 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
            </div>
            <button
                type="button"
                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                @click="createGroup"
            >
                Save
            </button>
        </div>

        <div v-if="localGroups.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No groups yet.</div>
        <div v-else ref="listEl" class="divide-y divide-gray-100 dark:divide-gray-700">
            <div
                v-for="g in localGroups"
                :key="g.id"
                :data-group-id="String(g.id)"
                class="flex flex-wrap items-center gap-2 py-2 first:pt-0 sm:gap-3"
            >
                <button
                    type="button"
                    class="group-drag-handle cursor-move rounded p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500"
                    title="Drag to reorder groups"
                >
                    <span class="material-icons text-[18px]">drag_indicator</span>
                </button>
                <div class="min-w-0 flex-1">
                    <template v-if="editingId === g.id">
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                v-model="editingName"
                                type="text"
                                class="min-w-[10rem] flex-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @keyup.enter="saveEdit(g)"
                            >
                            <button
                                type="button"
                                class="text-sm font-medium text-primary-600 dark:text-primary-400"
                                @click="saveEdit(g)"
                            >
                                Save
                            </button>
                            <button type="button" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400" @click="cancelEdit">
                                Cancel
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ g.name }}</p>
                        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ g.key }}</p>
                    </template>
                </div>
                <div v-if="editingId !== g.id" class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        title="Rename"
                        @click="startEdit(g)"
                    >
                        <span class="material-icons text-[18px]">edit</span>
                    </button>
                    <button
                        type="button"
                        class="rounded p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                        title="Remove group"
                        @click="removeGroup(g)"
                    >
                        <span class="material-icons text-[18px]">delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.45;
}
.sortable-chosen {
    @apply bg-gray-50 dark:bg-gray-700/50;
}
</style>
