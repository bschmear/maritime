<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    open:   { type: Boolean, default: false },
    groups: { type: Array,   default: () => [] },
});

const emit = defineEmits(['close']);

// ── Local editable copy (editing refs before watch — watcher references editingId) ──
const localGroups = ref([]);
const editingId   = ref(null);
const editingName = ref('');

watch(
    () => props.groups,
    (g) => {
        localGroups.value = [...(g || [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        if (editingId.value != null && !localGroups.value.some((x) => x.id === editingId.value)) {
            editingId.value = null;
            editingName.value = '';
        }
    },
    { immediate: true, deep: true },
);

// ── Add ───────────────────────────────────────────────────────────────────────
const newKey  = ref('');
const newName = ref('');

const createGroup = () => {
    if (!newKey.value.trim() || !newName.value.trim()) return;
    router.post(
        route('spec-groups.store'),
        { key: newKey.value.trim(), name: newName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => { newKey.value = ''; newName.value = ''; },
        },
    );
};

// ── Edit ──────────────────────────────────────────────────────────────────────
const startEdit  = (g) => { editingId.value = g.id; editingName.value = g.name || ''; };
const cancelEdit = ()  => { editingId.value = null; editingName.value = ''; };

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
            onError:   () => cancelEdit(),
        },
    );
};

// ── Delete ────────────────────────────────────────────────────────────────────
const removeGroup = (g) => {
    if (!window.confirm(`Remove group "${g.name}"? Specs in this group will become ungrouped.`)) return;
    router.delete(route('spec-groups.destroy', { specGroup: g.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="emit('close')"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="emit('close')" />

                <!-- Panel -->
                <div
                    class="relative z-10 w-full max-w-md rounded-xl bg-white shadow-xl dark:bg-gray-800
                           flex flex-col max-h-[85vh]"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Manage spec groups</h2>
                        <button
                            type="button"
                            class="rounded p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-[20px]">close</span>
                        </button>
                    </div>

                    <!-- Group list -->
                    <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-if="localGroups.length === 0" class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            No groups yet. Add one below.
                        </div>

                        <div
                            v-for="g in localGroups"
                            :key="g.id"
                            class="flex items-center gap-2 px-5 py-3"
                        >
                            <!-- Editing state -->
                            <template v-if="editingId === g.id">
                                <input
                                    v-model="editingName"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm
                                           dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    @keyup.enter="saveEdit(g)"
                                    @keyup.escape="cancelEdit"
                                >
                                <button
                                    type="button"
                                    class="text-sm font-medium text-primary-600 dark:text-primary-400"
                                    @click="saveEdit(g)"
                                >Save</button>
                                <button
                                    type="button"
                                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="cancelEdit"
                                >Cancel</button>
                            </template>

                            <!-- Display state -->
                            <template v-else>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ g.name }}</p>
                                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ g.key }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700
                                           dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                    title="Rename"
                                    @click="startEdit(g)"
                                >
                                    <span class="material-icons text-[18px]">edit</span>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-red-500 hover:bg-red-50 hover:text-red-700
                                           dark:hover:bg-red-950/40"
                                    title="Remove group"
                                    @click="removeGroup(g)"
                                >
                                    <span class="material-icons text-[18px]">delete</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Add new group -->
                    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Add new group</p>
                        <div class="flex flex-wrap gap-2">
                            <input
                                v-model="newKey"
                                type="text"
                                placeholder="key  (e.g. rigging)"
                                class="w-36 rounded-lg border-gray-300 text-sm
                                       dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @keyup.enter="createGroup"
                            >
                            <input
                                v-model="newName"
                                type="text"
                                placeholder="Display name"
                                class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm
                                       dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @keyup.enter="createGroup"
                            >
                            <button
                                type="button"
                                :disabled="!newKey.trim() || !newName.trim()"
                                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white
                                       hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="createGroup"
                            >
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.15s ease; }
.modal-enter-from,  .modal-leave-to      { opacity: 0; }
</style>