<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import Sortable from 'sortablejs';

const props = defineProps({
    specs: Array,
});

const emit = defineEmits(['remove-spec', 'reorder']);

const localSpecs = ref([...props.specs]);
const listEl = ref(null);
let sortable = null;

// Keep local in sync when parent changes
watch(() => props.specs, (newSpecs) => {
    localSpecs.value = [...newSpecs];
}, { deep: true });

onMounted(() => {
    sortable = Sortable.create(listEl.value, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        // Only allow dragging non-required specs
        filter: '.drag-disabled',
        onEnd(evt) {
            // Re-order localSpecs to match the new DOM order
            const moved = localSpecs.value.splice(evt.oldIndex, 1)[0];
            localSpecs.value.splice(evt.newIndex, 0, moved);
            emit('reorder', [...localSpecs.value]);
        },
    });
});

onBeforeUnmount(() => {
    sortable?.destroy();
});

const handleRemove = (specId) => {
    emit('remove-spec', specId);
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800">
        <!-- Header -->
        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Selected Specs
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ specs.length }} specs selected
            </p>
        </div>

        <!-- Spec List -->
        <div class="overflow-y-auto max-h-[600px]">
            <div ref="listEl" class="space-y-0">
                <div
                    v-for="spec in localSpecs"
                    :key="spec.id"
                    class="px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 last:border-b-0 bg-white dark:bg-gray-800"
                >
                    <div class="flex items-start gap-3">
                        <!-- Drag Handle -->
                        <button
                            :class="[
                                'drag-handle flex-shrink-0 mt-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                                spec.is_required ? 'cursor-not-allowed opacity-30 drag-disabled' : 'cursor-move',
                            ]"
                        >
                            <span class="material-icons text-[18px]">drag_indicator</span>
                        </button>

                        <!-- Spec Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ spec.label }}
                                </h4>
                                <span
                                    v-if="spec.is_required"
                                    class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300"
                                >
                                    Required
                                </span>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="capitalize">{{ spec.type }}</span>
                                <span v-if="spec.unit">• {{ spec.unit }}</span>
                                <span v-if="spec.group">• {{ spec.group }}</span>
                            </div>
                        </div>

                        <!-- Remove Button -->
                        <button
                            v-if="!spec.is_required"
                            @click="handleRemove(spec.id)"
                            class="flex-shrink-0 p-1 text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 transition-colors"
                        >
                            <span class="material-icons text-[18px]">close</span>
                        </button>
                        <div v-else class="w-6"></div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="specs.length === 0" class="px-6 py-12 text-center">
                <span class="material-icons text-gray-300 dark:text-gray-600 text-5xl">list_alt</span>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    No specs selected yet
                </p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Add specs from the library to get started
                </p>
            </div>
        </div>

        <!-- Footer Hint -->
        <div v-if="specs.length > 0" class="border-t border-gray-100 dark:border-gray-700 px-6 py-3 bg-gray-50 dark:bg-gray-700/50">
            <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <span class="material-icons text-[14px]">info</span>
                Drag to reorder • Required specs cannot be removed
            </p>
        </div>
    </div>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.4;
}
.sortable-chosen {
    @apply shadow-lg ring-2 ring-primary-300 dark:ring-primary-600;
}
</style>