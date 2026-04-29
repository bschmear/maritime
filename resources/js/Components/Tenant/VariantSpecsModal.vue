<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    assetId: { type: [Number, String], required: true },
    variantId: { type: [Number, String], required: true },
    /** When set (e.g. variant landing page), skips the network request */
    initialSpecRows: { type: Array, required: false },
    initialVariantLabel: { type: String, required: false },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const errorMessage = ref('');
const specRows = ref([]);
const variantLabel = ref('');

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            return;
        }
        errorMessage.value = '';

        if (Array.isArray(props.initialSpecRows)) {
            specRows.value = [...props.initialSpecRows];
            variantLabel.value =
                props.initialVariantLabel != null && String(props.initialVariantLabel).trim() !== ''
                    ? props.initialVariantLabel
                    : `Variant #${props.variantId}`;
            return;
        }

        loading.value = true;
        specRows.value = [];
        variantLabel.value = '';
        try {
            const { data } = await axios.get(
                route('assets.variants.show', { asset: props.assetId, variant: props.variantId }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const rec = data?.record;
            variantLabel.value =
                rec?.display_name || rec?.name || `Variant #${props.variantId}`;
            specRows.value = Array.isArray(data?.specRows) ? data.specRows : [];
        } catch (e) {
            errorMessage.value =
                e.response?.data?.message || e.message || 'Could not load specifications.';
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Specifications
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ variantLabel || 'Variant' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <span class="material-icons text-xl">close</span>
                </button>
            </div>

            <div v-if="loading" class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                Loading…
            </div>
            <p v-else-if="errorMessage" class="py-6 text-sm text-red-600 dark:text-red-400">
                {{ errorMessage }}
            </p>
            <div v-else class="mt-4 max-h-[min(70vh,560px)] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white dark:bg-gray-900">
                        <tr class="border-b-2 border-gray-900 dark:border-gray-600">
                            <th class="py-3 pr-4 text-left font-semibold text-gray-900 dark:text-white">
                                Specification
                            </th>
                            <th class="py-3 pl-4 text-right font-semibold text-gray-900 dark:text-white">
                                Value
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="(row, idx) in specRows" :key="idx">
                            <td class="py-2.5 pr-4 text-gray-800 dark:text-gray-200">{{ row.label }}</td>
                            <td class="py-2.5 pl-4 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ row.value ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!loading && !errorMessage && !specRows?.length" class="py-8 text-center text-sm text-gray-500">
                    No specification values recorded.
                </p>
            </div>
        </div>
    </Modal>
</template>
