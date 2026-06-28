<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
    suggestUrl: { type: String, required: true },
    applyUrl: { type: String, required: true },
    modelName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'applied']);

const loading = ref(false);
const applying = ref(false);
const errorMessage = ref('');
const result = ref(null);

const confidenceLabel = computed(() => {
    const c = result.value?.confidence;
    if (c == null) return null;
    return `${Math.round(Number(c) * 100)}% confidence`;
});

const dataSourceLabel = computed(() => {
    const t = result.value?.data_source_type;
    if (!t) return null;
    return String(t).replace(/_/g, ' ');
});

const hasAnyValues = computed(() =>
    (result.value?.preview_rows ?? []).some((row) => row.value != null && row.value !== ''),
);

const reset = () => {
    errorMessage.value = '';
    result.value = null;
    loading.value = false;
    applying.value = false;
};

const fetchSuggestions = async (refresh = false) => {
    loading.value = true;
    errorMessage.value = '';
    if (refresh) {
        result.value = null;
    }
    try {
        const { data } = await axios.post(props.suggestUrl, refresh ? { refresh: 1 } : {});
        result.value = data;
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not autofill specifications.';
        result.value = null;
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.show,
    (open) => {
        if (open) {
            reset();
            fetchSuggestions(false);
        }
    },
);

const handleClose = () => {
    if (applying.value) return;
    emit('close');
};

const handleApply = async () => {
    if (!result.value || applying.value) return;

    applying.value = true;
    errorMessage.value = '';

    try {
        await axios.post(props.applyUrl, {
            spec_updates: result.value.spec_updates ?? [],
            static_updates: result.value.static_updates ?? [],
        });
        emit('applied');
        emit('close');
        router.reload({ preserveScroll: true });
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Could not save specifications.';
    } finally {
        applying.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="handleClose">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Autofill specs with AI
                    </h3>
                    <p v-if="modelName" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ modelName }}
                    </p>
                </div>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    :disabled="loading || applying"
                    @click="handleClose"
                >
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div v-if="loading" class="mt-8 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                <span class="material-icons mb-3 animate-spin text-3xl text-primary-600">sync</span>
                <p>Gathering specifications…</p>
            </div>

            <div v-else-if="errorMessage && !result" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                {{ errorMessage }}
            </div>

            <template v-else-if="result">
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span
                        v-if="confidenceLabel"
                        class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                    >
                        {{ confidenceLabel }}
                    </span>
                    <span
                        v-if="dataSourceLabel"
                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ dataSourceLabel }}
                    </span>
                    <span
                        v-if="result.cached"
                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                    >
                        Cached result
                    </span>
                    <span
                        v-else-if="result.source === 'catalog'"
                        class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                    >
                        Manufacturer catalog
                    </span>
                    <span
                        v-else-if="result.source === 'openai'"
                        class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/40 dark:text-violet-200"
                    >
                        Fresh AI response
                    </span>
                </div>

                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    Does this look correct? Review the values below before saving.
                </p>

                <div class="mt-4 max-h-[50vh] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-200">
                                    Specification
                                </th>
                                <th class="px-4 py-2.5 text-right font-semibold text-gray-700 dark:text-gray-200">
                                    Value
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="row in result.preview_rows"
                                :key="row.key"
                                :class="row.value == null || row.value === '' ? 'opacity-60' : ''"
                            >
                                <td class="px-4 py-2.5 text-gray-800 dark:text-gray-200">
                                    {{ row.label }}
                                    <span
                                        v-if="row.kind === 'static'"
                                        class="ml-1 text-xs text-gray-400"
                                    >(standard)</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">
                                    {{ row.value ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="!hasAnyValues"
                    class="mt-3 text-sm text-amber-700 dark:text-amber-300"
                >
                    No verified values were found for this model. Saving will not change any specifications.
                </p>

                <p
                    v-if="errorMessage"
                    class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
                >
                    {{ errorMessage }}
                </p>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="loading || applying"
                        @click="fetchSuggestions(true)"
                    >
                        <span class="material-icons text-base">refresh</span>
                        Refresh from AI
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="applying"
                        @click="handleClose"
                    >
                        No, cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="applying"
                        @click="handleApply"
                    >
                        <span v-if="applying" class="material-icons animate-spin text-base">sync</span>
                        <span v-else class="material-icons text-base">check</span>
                        {{ applying ? 'Saving…' : 'Yes, save specs' }}
                    </button>
                </div>
            </template>

            <div v-else class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @click="handleClose"
                >
                    Close
                </button>
            </div>
        </div>
    </Modal>
</template>
