<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Sortable from 'sortablejs';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const features = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    otherPlans: { type: Array, default: () => [] },
    error: { type: String, default: null },
});

const listRef = ref(null);
const importPlanId = ref('');
const importMode = ref('append');
let sortableInstance = null;

const newTitle = ref('');
const newDescription = ref('');

const addFeature = () => {
    const title = newTitle.value.trim();
    if (!title) {
        return;
    }

    features.value = [
        ...features.value,
        {
            title,
            description: newDescription.value.trim(),
        },
    ];

    newTitle.value = '';
    newDescription.value = '';
};

const removeFeature = (index) => {
    features.value = features.value.filter((_, i) => i !== index);
};

const importFromPlan = () => {
    const plan = props.otherPlans.find((p) => String(p.id) === String(importPlanId.value));
    if (!plan?.included?.length) {
        return;
    }

    const incoming = plan.included.map((f) => ({
        title: f.title ?? '',
        description: f.description ?? '',
    })).filter((f) => f.title.trim() !== '');

    if (importMode.value === 'replace') {
        features.value = incoming;
    } else {
        const existingTitles = new Set(
            features.value.map((f) => (f.title || '').trim().toLowerCase()),
        );
        const merged = [...features.value];
        for (const feature of incoming) {
            const key = feature.title.trim().toLowerCase();
            if (!existingTitles.has(key)) {
                merged.push(feature);
                existingTitles.add(key);
            }
        }
        features.value = merged;
    }
};

const initSortable = () => {
    if (!listRef.value) {
        return;
    }

    sortableInstance?.destroy();
    sortableInstance = new Sortable(listRef.value, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'opacity-40',
        onEnd: (evt) => {
            if (evt.oldIndex == null || evt.newIndex == null || evt.oldIndex === evt.newIndex) {
                return;
            }
            const next = [...features.value];
            const [moved] = next.splice(evt.oldIndex, 1);
            next.splice(evt.newIndex, 0, moved);
            features.value = next;
        },
    });
};

const hasOtherPlans = computed(() => props.otherPlans.length > 0);

onMounted(() => {
    nextTick(() => initSortable());
});

watch(
    () => features.value.length,
    () => nextTick(() => initSortable()),
);

onBeforeUnmount(() => {
    sortableInstance?.destroy();
});
</script>

<template>
    <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <InputLabel value="Plan features" class="text-gray-900 dark:text-white" />
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Add a title and optional description for each feature. Drag to reorder how they appear on pricing.
        </p>

        <div v-if="hasOtherPlans" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-sm font-medium text-gray-900 dark:text-white">Import from another plan</p>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="import_plan" class="sr-only">Plan</label>
                    <select
                        id="import_plan"
                        v-model="importPlanId"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">Select a plan…</option>
                        <option v-for="plan in otherPlans" :key="plan.id" :value="plan.id">
                            {{ plan.name }}
                        </option>
                    </select>
                </div>
                <div class="flex-1">
                    <label for="import_mode" class="sr-only">Import mode</label>
                    <select
                        id="import_mode"
                        v-model="importMode"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="append">Add to current list</option>
                        <option value="replace">Replace current list</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!importPlanId"
                    @click="importFromPlan"
                >
                    Import features
                </button>
            </div>
        </div>

        <div class="mt-4 space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-900 dark:text-white">Add feature</p>
            <div>
                <InputLabel for="feature_title" value="Title" />
                <TextInput
                    id="feature_title"
                    v-model="newTitle"
                    type="text"
                    class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="e.g. Customer portal"
                    @keydown.enter.prevent="addFeature"
                />
            </div>
            <div>
                <InputLabel for="feature_description" value="Description (shown in expanded pricing view)" />
                <textarea
                    id="feature_description"
                    v-model="newDescription"
                    rows="2"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Optional detail for the pricing page…"
                />
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                @click="addFeature"
            >
                <span class="material-icons text-base">add</span>
                Add feature
            </button>
        </div>

        <div v-if="features.length > 0" ref="listRef" class="mt-4 space-y-3">
            <div
                v-for="(feature, index) in features"
                :key="`${feature.title}-${index}`"
                class="group flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <button
                    type="button"
                    class="drag-handle mt-1 flex shrink-0 cursor-grab touch-none text-gray-400 hover:text-gray-600 active:cursor-grabbing dark:hover:text-gray-300"
                    title="Drag to reorder"
                >
                    <span class="material-icons">drag_indicator</span>
                </button>
                <div class="min-w-0 flex-1 space-y-2">
                    <TextInput
                        v-model="feature.title"
                        type="text"
                        class="block w-full rounded-lg border-gray-300 font-medium dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        placeholder="Feature title"
                    />
                    <textarea
                        v-model="feature.description"
                        rows="2"
                        class="block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        placeholder="Description (optional)"
                    />
                </div>
                <button
                    type="button"
                    class="shrink-0 self-start p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                    title="Remove"
                    @click="removeFeature(index)"
                >
                    <span class="material-icons text-xl">close</span>
                </button>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border-2 border-dashed border-gray-300 py-8 text-center dark:border-gray-700"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">No features yet. Add items above or import from another plan.</p>
        </div>

        <InputError class="mt-2" :message="error" />
    </div>
</template>
