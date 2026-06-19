<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    financing: { type: Object, default: null },
    metrics: { type: Object, default: () => ({}) },
});

const showInterestModal = ref(false);
const interestRateInput = ref('');
const loanTermMonthsInput = ref('');
const savingInterestRate = ref(false);
const interestRateError = ref('');

const fmt$ = (v) =>
    v != null ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const fmtRate = (v) => {
    const n = parseFloat(v);
    if (!Number.isFinite(n)) return '—';
    return `${parseFloat(n.toFixed(4))}%`;
};

const hasInterestRate = computed(() => {
    const rate = parseFloat(props.financing?.annual_interest_rate ?? 0);
    return Number.isFinite(rate) && rate > 0;
});

const daysExceeded = computed(() => {
    const threshold = props.metrics?.days_threshold;
    if (threshold == null) return false;
    return (props.metrics?.days_financed ?? 0) >= threshold;
});

const interestExceeded = computed(() => {
    const threshold = props.metrics?.interest_threshold;
    if (threshold == null) return false;
    return (props.metrics?.total_interest_cost ?? 0) >= threshold;
});

const interestBasisLabel = computed(() => {
    if (props.financing?.interest_start_date) return 'interest start date';
    if (props.financing?.financed_at) return 'invoice date';
    return 'financing date';
});

const loanTermLabel = computed(() => {
    const months = props.metrics?.loan_term_months ?? props.financing?.loan_term_months;
    if (!months) return null;
    const yrs = Math.floor(months / 12);
    const rem = months % 12;
    if (yrs > 0 && rem > 0) return `${months} months (${yrs} yr ${rem} mo)`;
    if (yrs > 0) return `${months} months (${yrs} yr)`;
    return `${months} months`;
});

const skipStorageKey = computed(() =>
    props.financing?.id ? `financing-interest-rate-skipped-${props.financing.id}` : null,
);

onMounted(() => {
    if (!props.financing?.id || hasInterestRate.value) return;
    if (skipStorageKey.value && sessionStorage.getItem(skipStorageKey.value)) return;
    loanTermMonthsInput.value = props.financing?.loan_term_months ?? '';
    showInterestModal.value = true;
});

function skipInterestModal() {
    if (skipStorageKey.value) {
        sessionStorage.setItem(skipStorageKey.value, '1');
    }
    showInterestModal.value = false;
}

async function saveInterestRate() {
    const rate = parseFloat(interestRateInput.value);
    if (!Number.isFinite(rate) || rate <= 0) {
        interestRateError.value = 'Enter a valid annual interest rate greater than 0.';
        return;
    }

    const payload = { annual_interest_rate: rate };
    const termMonths = parseInt(loanTermMonthsInput.value, 10);
    if (Number.isFinite(termMonths) && termMonths > 0) {
        payload.loan_term_months = termMonths;
    }

    savingInterestRate.value = true;
    interestRateError.value = '';

    try {
        await axios.post(route('financings.interest-rate', props.financing.id), payload);
        showInterestModal.value = false;
        if (skipStorageKey.value) {
            sessionStorage.removeItem(skipStorageKey.value);
        }
        router.reload();
    } catch (e) {
        interestRateError.value = e?.response?.data?.message ?? 'Could not save interest rate.';
    } finally {
        savingInterestRate.value = false;
    }
}
</script>

<template>
    <div v-if="financing" class="space-y-2">
        <div
            v-if="daysExceeded"
            class="rounded-lg border border-red-300 bg-red-100 px-3 py-2 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-200"
            role="alert"
        >
            Aging alert — {{ metrics.days_financed }} days in inventory (threshold: {{ metrics.days_threshold }} days)
        </div>

        <div
            v-if="interestExceeded"
            class="rounded-lg border border-red-300 bg-red-100 px-3 py-2 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-200"
            role="alert"
        >
            Interest alert — accrued interest {{ fmt$(metrics.total_interest_cost) }}
            (threshold: {{ fmt$(metrics.interest_threshold) }})
        </div>

        <div
            v-if="hasInterestRate"
            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
        >
            <p>
                <span class="font-medium">Accrued interest to date:</span>
                {{ fmt$(metrics.accrued_interest) }}
                <span class="text-blue-700 dark:text-blue-300">
                    ({{ fmtRate(financing.annual_interest_rate) }} on principal
                    × {{ metrics.interest_months_elapsed ?? 0 }} mo since {{ interestBasisLabel }})
                </span>
            </p>
            <p v-if="metrics.estimated_monthly_interest != null" class="mt-1">
                <span class="font-medium">Est. monthly interest:</span>
                {{ fmt$(metrics.estimated_monthly_interest) }}
            </p>
            <p v-if="metrics.estimated_total_interest != null" class="mt-1">
                <span class="font-medium">Est. total interest over term:</span>
                {{ fmt$(metrics.estimated_total_interest) }}
                <span v-if="loanTermLabel" class="text-blue-700 dark:text-blue-300">({{ loanTermLabel }})</span>
            </p>
            <p v-else-if="!financing.loan_term_months" class="mt-1 text-blue-700 dark:text-blue-300">
                Add a loan term (months) on the financing record to estimate total interest over the life of the loan.
            </p>
        </div>
    </div>

    <Modal :show="showInterestModal" max-width="md" @close="skipInterestModal">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add interest rate &amp; loan term</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Interest is calculated on the original principal amount. Accrual starts from the
                {{ interestBasisLabel }}.
            </p>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Annual interest rate (%)
                    </label>
                    <input
                        v-model="interestRateInput"
                        type="number"
                        min="0"
                        step="0.0001"
                        placeholder="e.g. 6.5"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Loan term (months)
                    </label>
                    <input
                        v-model="loanTermMonthsInput"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="e.g. 60 for 5 years"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        @keydown.enter.prevent="saveInterestRate"
                    />
                </div>
                <p v-if="interestRateError" class="text-xs text-red-600 dark:text-red-400">
                    {{ interestRateError }}
                </p>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                    :disabled="savingInterestRate"
                    @click="skipInterestModal"
                >
                    Skip
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                    :disabled="savingInterestRate"
                    @click="saveInterestRate"
                >
                    {{ savingInterestRate ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
