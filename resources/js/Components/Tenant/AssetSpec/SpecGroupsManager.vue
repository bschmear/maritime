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

watch(
    () => props.groups,
    (g) => {
        localGroups.value = [...(g || [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    },
    { immediate: true, deep: true }
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
    { deep: true }
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
        }
    );
};
</script>

<template>
    <div class="rounded-lg bg-white p-4 shadow-md dark:bg-gray-800">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Spec groups</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Drag to change section order on the spec builder. Key is a stable id (e.g. dimensions).
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
                    class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Display name</label>
                <input
                    v-model="newName"
                    type="text"
                    placeholder="e.g. Rigging"
                    class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
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
                class="flex items-center gap-3 py-2 first:pt-0"
            >
                <button
                    type="button"
                    class="group-drag-handle cursor-move rounded p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500"
                    title="Drag to reorder"
                >
                    <span class="material-icons text-[18px]">drag_indicator</span>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ g.name }}</p>
                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ g.key }}</p>
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
