<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    globalOptions: { type: Array, default: () => [] },
    /** Option IDs already on this line */
    excludeOptionIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'add']);

const search = ref('');

const excludedSet = computed(() => new Set((props.excludeOptionIds ?? []).map(Number)));

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.globalOptions ?? [])
        .filter((o) => !excludedSet.value.has(Number(o.option_id)))
        .filter((o) => !q || String(o.name ?? '').toLowerCase().includes(q));
});

function addOption(opt) {
    emit('add', opt);
}

function close() {
    search.value = '';
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex flex-col bg-gray-950/80 backdrop-blur-sm">
            <div class="flex shrink-0 items-center justify-between border-b border-white/10 bg-gray-900 px-4 py-4 sm:px-6">
                <div>
                    <h2 class="text-lg font-semibold text-white">Add global option</h2>
                    <p class="mt-1 text-sm text-gray-400">Choose an option to add to this line item.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-400 hover:bg-white/10 hover:text-white"
                    aria-label="Close"
                    @click="close"
                >
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="shrink-0 border-b border-white/10 px-4 py-3 sm:px-6">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search global options…"
                    class="input-style w-full max-w-xl bg-white dark:bg-gray-800"
                    autocomplete="off"
                />
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                <p v-if="!filteredOptions.length" class="py-12 text-center text-sm text-gray-400">
                    {{ globalOptions.length ? 'No matching global options.' : 'No global options configured yet.' }}
                </p>
                <ul v-else class="mx-auto max-w-3xl space-y-2">
                    <li
                        v-for="opt in filteredOptions"
                        :key="opt.option_id"
                        class="flex items-center justify-between gap-4 rounded-xl border border-white/10 bg-gray-900/90 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <div class="font-medium text-white">{{ opt.name }}</div>
                            <div class="mt-0.5 text-xs capitalize text-gray-400">{{ opt.input_type?.replace('_', ' ') }}</div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="addOption(opt)"
                        >
                            Add
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
