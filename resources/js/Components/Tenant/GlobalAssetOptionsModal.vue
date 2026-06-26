<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    globalOptions: { type: Array, default: () => [] },
    /** Option IDs already on this line */
    excludeOptionIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'add']);

const search = ref('');
const selectedIds = ref(new Set());

const excludedSet = computed(() => new Set((props.excludeOptionIds ?? []).map(Number)));

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.globalOptions ?? [])
        .filter((o) => !excludedSet.value.has(Number(o.option_id)))
        .filter((o) => !q || String(o.name ?? '').toLowerCase().includes(q));
});

const selectedCount = computed(() => selectedIds.value.size);

const selectedOptions = computed(() =>
    filteredOptions.value.filter((o) => selectedIds.value.has(Number(o.option_id))),
);

function isSelected(opt) {
    return selectedIds.value.has(Number(opt.option_id));
}

function toggleSelection(opt) {
    const id = Number(opt.option_id);
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function addSelected() {
    if (!selectedOptions.value.length) {
        return;
    }

    emit('add', selectedOptions.value);
    resetState();
}

function resetState() {
    search.value = '';
    selectedIds.value = new Set();
}

function close() {
    resetState();
    emit('close');
}

watch(
    () => props.show,
    (show) => {
        if (show) {
            resetState();
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex flex-col bg-gray-950/80 backdrop-blur-sm">
            <div class="flex shrink-0 items-center justify-between border-b border-white/10 bg-gray-900 px-4 py-3 sm:px-6">
                <div>
                    <h2 class="text-base font-semibold text-white sm:text-lg">Add global options</h2>
                    <p class="mt-0.5 text-xs text-gray-400 sm:text-sm">Select one or more options, then click Add.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-white/10 hover:text-white"
                    aria-label="Close"
                    @click="close"
                >
                    <span class="material-icons text-xl">close</span>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                <div class="mx-auto w-full max-w-3xl">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search global options…"
                        class="input-style mb-3 w-full bg-white dark:bg-gray-800"
                        autocomplete="off"
                    />

                    <p v-if="!filteredOptions.length" class="py-10 text-center text-sm text-gray-400">
                        {{ globalOptions.length ? 'No matching global options.' : 'No global options configured yet.' }}
                    </p>

                    <ul
                        v-else
                        class="grid grid-cols-1 gap-1 "
                        role="listbox"
                        aria-multiselectable="true"
                        aria-label="Global options"
                    >
                        <li v-for="opt in filteredOptions" :key="opt.option_id">
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 transition-colors"
                                :class="
                                    isSelected(opt)
                                        ? 'border-primary-500 bg-primary-600/15'
                                        : 'border-white/10 bg-gray-900/90 hover:border-white/20 hover:bg-gray-900'
                                "
                            >
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 shrink-0 rounded border-gray-500 text-primary-600 focus:ring-primary-500"
                                    :checked="isSelected(opt)"
                                    @change="toggleSelection(opt)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium leading-tight text-white">
                                        {{ opt.name }}
                                    </span>
                                    <span class="block truncate text-[11px] capitalize leading-tight text-gray-400">
                                        {{ opt.input_type?.replace('_', ' ') }}
                                    </span>
                                </span>
                            </label>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="flex shrink-0 items-center justify-end gap-2 border-t border-white/10 bg-gray-900 px-4 py-3 sm:px-6"
            >
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="selectedCount === 0"
                    @click="addSelected"
                >
                    <span class="material-icons text-base">add</span>
                    Add{{ selectedCount ? ` (${selectedCount})` : '' }}
                </button>
            </div>
        </div>
    </Teleport>
</template>
