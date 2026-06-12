<script setup>
import Modal from '@/Components/Modal.vue';
import { computed } from 'vue';
import { transactionStatusLabel } from '@/Utils/transactionAssetUnits';

const props = defineProps({
    show: { type: Boolean, default: false },
    units: { type: Array, default: () => [] },
    transactionStatusId: { type: Number, default: null },
    statusOptions: { type: Array, default: () => [] },
    unitStatusOptions: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const rows = defineModel('rows', { type: Array, default: () => [] });

const dealStatusLabel = computed(() =>
    transactionStatusLabel(props.transactionStatusId, props.statusOptions),
);

const unitStatusLabel = (statusId) => {
    const opt = props.unitStatusOptions.find((o) => Number(o.id) === Number(statusId));
    return opt?.name ?? '—';
};

const confirm = () => {
    emit('confirm', rows.value.map((row) => ({
        asset_unit_id: row.asset_unit_id,
        status: Number(row.status),
    })));
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Update asset unit status
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                This deal is moving to <span class="font-medium">{{ dealStatusLabel }}</span>.
                Choose a status for each asset unit on this transaction.
            </p>

            <div class="mt-5 space-y-3">
                <div
                    v-for="row in rows"
                    :key="row.asset_unit_id"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ row.display_name }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ row.line_label }}
                                <span v-if="row.current_status != null">
                                    · Current: {{ unitStatusLabel(row.current_status) }}
                                </span>
                            </p>
                        </div>
                        <select
                            v-model.number="row.status"
                            class="input-style sm:max-w-[12rem]"
                            :disabled="processing"
                        >
                            <option
                                v-for="opt in unitStatusOptions"
                                :key="opt.id"
                                :value="opt.id"
                            >
                                {{ opt.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="processing"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                    :disabled="processing"
                    @click="confirm"
                >
                    {{ processing ? 'Saving…' : 'Save deal & update units' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
