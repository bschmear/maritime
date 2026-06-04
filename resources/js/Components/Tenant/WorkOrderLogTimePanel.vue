<script setup>
import Modal from '@/Components/Modal.vue';
import WorkOrderTimeSummary from '@/Components/Tenant/WorkOrderTimeSummary.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    workOrderId: { type: [Number, String], required: true },
    serviceItems: { type: Array, default: () => [] },
    billingTypeOptions: { type: Array, default: () => [] },
});

const showModal = ref(false);
const selectedLineItemId = ref(null);
const timeMode = ref('add');
const hoursToAddInput = ref('1');
const totalHoursInput = ref('0');
const submitting = ref(false);
const errorMessage = ref('');

const activeLineItems = computed(() =>
    (props.serviceItems ?? []).filter((item) => item?.id != null && !item.inactive),
);

const selectedLineItem = computed(() =>
    activeLineItems.value.find((item) => Number(item.id) === Number(selectedLineItemId.value)) ?? null,
);

const estimatedHours = computed(() =>
    activeLineItems.value.reduce((sum, item) => sum + (Number(item.estimated_hours) || 0), 0),
);

const actualHours = computed(() =>
    activeLineItems.value.reduce((sum, item) => sum + (Number(item.actual_hours) || 0), 0),
);

const selectedCurrentHours = computed(() => Number(selectedLineItem.value?.actual_hours) || 0);

const selectedEstimatedHours = computed(() => Number(selectedLineItem.value?.estimated_hours) || 0);

const billingTypeLabel = (billingType) => {
    const hit = props.billingTypeOptions.find(
        (opt) => Number(opt.id) === Number(billingType) || Number(opt.value) === Number(billingType),
    );
    return hit?.name ?? '—';
};

const lineItemLabel = (item) => {
    const name = item.display_name || item.description || `Line #${item.id}`;
    return `${name} · ${billingTypeLabel(item.billing_type)}`;
};

const formatHours = (value) => (Number(value) || 0).toFixed(2);

function syncTotalHoursInput() {
    totalHoursInput.value = formatHours(selectedCurrentHours.value);
}

watch(showModal, (open) => {
    if (!open) {
        return;
    }
    errorMessage.value = '';
    timeMode.value = 'add';
    hoursToAddInput.value = '1';
    if (!selectedLineItemId.value && activeLineItems.value.length) {
        selectedLineItemId.value = activeLineItems.value[0].id;
    }
    syncTotalHoursInput();
});

watch(selectedLineItemId, () => {
    syncTotalHoursInput();
});

watch(timeMode, (mode) => {
    errorMessage.value = '';
    if (mode === 'set') {
        syncTotalHoursInput();
    }
});

function openModal() {
    if (!activeLineItems.value.length) {
        errorMessage.value = 'Add at least one service line item before logging time.';
        return;
    }
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    errorMessage.value = '';
}

function applyQuickHours(value) {
    timeMode.value = 'add';
    hoursToAddInput.value = String(value);
}

async function submitLogTime() {
    if (!selectedLineItemId.value) {
        errorMessage.value = 'Select a line item.';
        return;
    }

    const payload = {
        line_item_id: selectedLineItemId.value,
        mode: timeMode.value,
    };

    if (timeMode.value === 'set') {
        const total = Number(totalHoursInput.value);
        if (!Number.isFinite(total) || total < 0) {
            errorMessage.value = 'Enter a valid total (0 or more hours).';
            return;
        }
        payload.actual_hours = total;
    } else {
        const hours = Number(hoursToAddInput.value);
        if (!Number.isFinite(hours) || hours < 0.01) {
            errorMessage.value = 'Enter at least 0.01 hours to add.';
            return;
        }
        payload.hours = hours;
    }

    submitting.value = true;
    errorMessage.value = '';

    try {
        await axios.post(route('workorders.log-time', props.workOrderId), payload, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        closeModal();
        router.reload({ only: ['record'], preserveScroll: true });
    } catch (e) {
        const errors = e.response?.data?.errors ?? {};
        errorMessage.value =
            e.response?.data?.message
            || errors.hours?.[0]
            || errors.actual_hours?.[0]
            || errors.line_item_id?.[0]
            || 'Could not save time. Try again.';
    } finally {
        submitting.value = false;
    }
}

const submitLabel = computed(() => {
    if (submitting.value) {
        return 'Saving…';
    }
    return timeMode.value === 'set' ? 'Save total' : 'Add time';
});

defineExpose({ openModal });
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Time on work order
                </h3>
                <div class="mt-3">
                    <WorkOrderTimeSummary
                        :estimated-hours="estimatedHours"
                        :actual-hours="actualHours"
                    />
                </div>
            </div>
            <button
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!activeLineItems.length"
                @click="openModal"
            >
                <span class="material-icons text-lg" aria-hidden="true">schedule</span>
                Log actual time
            </button>
        </div>
        <p v-if="!activeLineItems.length" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Add service line items to track estimated vs actual hours.
        </p>
        <p v-else-if="errorMessage && !showModal" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ errorMessage }}
        </p>
    </div>

    <Modal :show="showModal" max-width="md" @close="closeModal">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Log actual time</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Add more hours to a line item, or set the total if something was logged incorrectly.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="wo-log-time-line" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Line item
                    </label>
                    <select
                        id="wo-log-time-line"
                        v-model="selectedLineItemId"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option v-for="item in activeLineItems" :key="item.id" :value="item.id">
                            {{ lineItemLabel(item) }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="selectedLineItem"
                    class="rounded-lg border border-blue-100 bg-blue-50/80 px-4 py-3 dark:border-blue-900/50 dark:bg-blue-950/40"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-800/80 dark:text-blue-300/80">
                        Current on this line
                    </p>
                    <div class="mt-2 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                        <div>
                            <span class="text-2xl font-semibold tabular-nums text-blue-900 dark:text-blue-100">
                                {{ formatHours(selectedCurrentHours) }}
                            </span>
                            <span class="ml-1 text-sm text-blue-800/90 dark:text-blue-200/90">hrs logged</span>
                        </div>
                        <div class="text-sm text-blue-800/90 dark:text-blue-200/90">
                            <span class="text-blue-700/70 dark:text-blue-300/70">Estimated:</span>
                            <span class="ml-1 font-medium tabular-nums">{{ formatHours(selectedEstimatedHours) }} hrs</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">How to update</span>
                    <div
                        class="inline-flex w-full rounded-lg border border-gray-200 p-0.5 dark:border-gray-600 sm:w-auto"
                        role="tablist"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="timeMode === 'add'"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors sm:flex-none sm:px-4"
                            :class="timeMode === 'add'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'"
                            @click="timeMode = 'add'"
                        >
                            Add hours
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="timeMode === 'set'"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors sm:flex-none sm:px-4"
                            :class="timeMode === 'set'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'"
                            @click="timeMode = 'set'"
                        >
                            Edit total
                        </button>
                    </div>
                </div>

                <div v-if="timeMode === 'add'">
                    <label for="wo-log-time-hours-add" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Additional hours
                    </label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                        Added to {{ formatHours(selectedCurrentHours) }} hrs already logged.
                    </p>
                    <input
                        id="wo-log-time-hours-add"
                        v-model="hoursToAddInput"
                        type="number"
                        min="0.01"
                        max="999"
                        step="0.25"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="preset in [0.25, 0.5, 1, 2, 4]"
                            :key="preset"
                            type="button"
                            class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="applyQuickHours(preset)"
                        >
                            +{{ preset }}h
                        </button>
                    </div>
                </div>

                <div v-else>
                    <label for="wo-log-time-hours-total" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Total actual hours
                    </label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                        Replaces the current total for this line item (use to fix mistakes).
                    </p>
                    <input
                        id="wo-log-time-hours-total"
                        v-model="totalHoursInput"
                        type="number"
                        min="0"
                        max="999"
                        step="0.25"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>

                <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400">{{ errorMessage }}</p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    :disabled="submitting"
                    @click="closeModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="submitting"
                    @click="submitLogTime"
                >
                    <span v-if="submitting" class="material-icons animate-spin text-lg">refresh</span>
                    {{ submitLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
