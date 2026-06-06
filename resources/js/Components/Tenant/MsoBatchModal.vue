<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    transactionId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'submitted']);

const loading = ref(false);
const submitting = ref(false);
const error = ref(null);
const transaction = ref(null);
const units = ref([]);
const selections = ref({});

const hasUnits = computed(() => units.value.length > 0);

watch(
    () => [props.show, props.transactionId],
    async ([show, transactionId]) => {
        if (!show || !transactionId) {
            return;
        }

        loading.value = true;
        error.value = null;
        selections.value = {};

        try {
            const { data } = await axios.get(route('transactions.mso.units', transactionId));
            transaction.value = data.transaction;
            units.value = data.units ?? [];

            const initial = {};
            for (const unit of units.value) {
                if (unit.mso_status === 'submitted') {
                    initial[unit.transaction_line_item_id] = 'submitted';
                } else if (unit.mso_status === 'not_required') {
                    initial[unit.transaction_line_item_id] = 'not_required';
                } else {
                    initial[unit.transaction_line_item_id] = '';
                }
            }
            selections.value = initial;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to load asset units for this deal.';
            units.value = [];
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

function setSelection(lineItemId, status) {
    selections.value = {
        ...selections.value,
        [lineItemId]: selections.value[lineItemId] === status ? '' : status,
    };
}

async function submit() {
    if (!props.transactionId) {
        return;
    }

    submitting.value = true;
    error.value = null;

    const items = units.value.map((unit) => ({
        transaction_line_item_id: unit.transaction_line_item_id,
        status: selections.value[unit.transaction_line_item_id] || null,
    }));

    router.post(route('transactions.mso.batch', props.transactionId), { items }, {
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ') || 'Failed to save MSO selections.';
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
    emit('submitted');
}

function close() {
    emit('close');
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="close"
    >
        <div class="w-full max-w-2xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Create MSO
                </h3>
                <p v-if="transaction" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ transaction.display_name }} — mark units as MSO created or not required.
                </p>
            </div>

            <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                <p v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading asset units…</p>

                <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

                <p v-else-if="!hasUnits" class="text-sm text-gray-500 dark:text-gray-400">
                    This deal has no asset unit line items.
                </p>

                <ul v-else class="space-y-4">
                    <li
                        v-for="unit in units"
                        :key="unit.transaction_line_item_id"
                        class="rounded-lg border border-gray-200 p-4 dark:border-gray-600"
                    >
                        <p class="font-medium text-gray-900 dark:text-white">{{ unit.display_name }}</p>
                        <p v-if="unit.line_name" class="text-sm text-gray-500 dark:text-gray-400">{{ unit.line_name }}</p>

                        <div class="mt-3 flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    :checked="selections[unit.transaction_line_item_id] === 'submitted'"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600"
                                    @change="setSelection(unit.transaction_line_item_id, 'submitted')"
                                />
                                MSO created
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    :checked="selections[unit.transaction_line_item_id] === 'not_required'"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600"
                                    @change="setSelection(unit.transaction_line_item_id, 'not_required')"
                                />
                                No MSO required
                            </label>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    :disabled="submitting"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="submitting || loading || !hasUnits"
                    @click="submit"
                >
                    {{ submitting ? 'Saving…' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
</template>
