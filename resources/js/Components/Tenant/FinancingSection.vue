<script setup>
import { Link } from '@inertiajs/vue3';
import FinancingAlerts from '@/Components/Tenant/FinancingAlerts.vue';
import { computed } from 'vue';

const props = defineProps({
    context: { type: Object, required: true },
    record: { type: Object, required: true },
});

const financing = computed(() => props.context?.financing ?? null);
const metrics = computed(() => props.context?.metrics ?? {});

const fmt$ = (v) =>
    v != null ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const fmtDate = (v) => {
    if (!v) return '—';
    try { return new Date(v + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
    catch { return v; }
};

const principal = computed(() => parseFloat(financing.value?.principal_amount ?? 0));
const balance = computed(() => parseFloat(financing.value?.current_balance ?? 0));
const paidOff = computed(() => Math.max(0, principal.value - balance.value));
const paidOffPct = computed(() =>
    principal.value > 0 ? Math.min(100, Math.round((paidOff.value / principal.value) * 100)) : null,
);
</script>

<template>
    <div
        class="rounded-lg border p-4"
        :class="metrics.at_risk
            ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/30'
            : 'border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-950/30'"
    >
        <!-- Header row -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Financing</h3>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="context?.create_url"
                    :href="context.create_url"
                    class="inline-flex rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700"
                >
                    New financing
                </Link>
                <Link
                    v-if="financing?.id"
                    :href="route('financings.show', financing.id)"
                    class="inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    View record
                </Link>
            </div>
        </div>

        <!-- No financing flag set -->
        <p v-if="!record?.is_financed && !financing" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            This unit is not marked as financed.
        </p>

        <!-- Financed but no record -->
        <p v-else-if="!financing" class="mt-2 text-sm text-amber-800 dark:text-amber-300">
            Marked as financed but no financing record found. Create one or import from a lender CSV.
        </p>

        <!-- Financing record found -->
        <template v-else>
            <FinancingAlerts :financing="financing" :metrics="metrics" class="mt-3" />

            <!-- At-risk banner -->
            <div
                v-if="metrics.at_risk"
                class="mt-2 rounded bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
            >
                At risk — sell or pay off
            </div>

            <!-- Key figures -->
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Principal</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ fmt$(financing.principal_amount) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Balance</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ fmt$(financing.current_balance) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Paid off</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-green-700 dark:text-green-400">{{ fmt$(paidOff) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">% paid</p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                        {{ paidOffPct != null ? `${paidOffPct}%` : '—' }}
                    </p>
                </div>
            </div>

            <!-- Progress bar -->
            <div v-if="paidOffPct != null" class="mt-2">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-blue-200 dark:bg-blue-800">
                    <div class="h-full rounded-full bg-green-500 transition-all" :style="{ width: `${paidOffPct}%` }" />
                </div>
            </div>

            <!-- Detail row -->
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-3">
                <div v-if="financing.vendor?.display_name">
                    <dt class="font-medium">Lender</dt>
                    <dd class="text-gray-900 dark:text-white">{{ financing.vendor.display_name }}</dd>
                </div>
                <div v-if="financing.lender_status">
                    <dt class="font-medium">Lender status</dt>
                    <dd class="text-gray-900 dark:text-white">{{ financing.lender_status }}</dd>
                </div>
                <div v-if="financing.aging_days != null">
                    <dt class="font-medium">Aging</dt>
                    <dd class="text-gray-900 dark:text-white">{{ financing.aging_days }} days</dd>
                </div>
                <div v-if="financing.financed_at">
                    <dt class="font-medium">Financed date</dt>
                    <dd class="text-gray-900 dark:text-white">{{ fmtDate(financing.financed_at) }}</dd>
                </div>
                <div v-if="financing.next_payment_date">
                    <dt class="font-medium">Next payment</dt>
                    <dd class="text-gray-900 dark:text-white">{{ fmtDate(financing.next_payment_date) }}</dd>
                </div>
                <div v-if="metrics.days_financed != null">
                    <dt class="font-medium">Days financed</dt>
                    <dd class="text-gray-900 dark:text-white">{{ metrics.days_financed }}</dd>
                </div>
                <div v-if="metrics.estimated_monthly_interest != null">
                    <dt class="font-medium">Est. monthly interest</dt>
                    <dd class="text-gray-900 dark:text-white">{{ fmt$(metrics.estimated_monthly_interest) }}</dd>
                </div>
                <div v-if="metrics.estimated_total_interest != null">
                    <dt class="font-medium">Est. total interest</dt>
                    <dd class="text-gray-900 dark:text-white">{{ fmt$(metrics.estimated_total_interest) }}</dd>
                </div>
                <div v-if="financing.loan_term_months">
                    <dt class="font-medium">Loan term</dt>
                    <dd class="text-gray-900 dark:text-white">{{ financing.loan_term_months }} months</dd>
                </div>
                <div v-if="metrics.total_interest_cost != null">
                    <dt class="font-medium">Interest to date</dt>
                    <dd class="text-gray-900 dark:text-white">{{ fmt$(metrics.total_interest_cost) }}</dd>
                </div>
                <div v-if="financing.lender_invoice_number">
                    <dt class="font-medium">Invoice #</dt>
                    <dd class="font-mono text-gray-900 dark:text-white">{{ financing.lender_invoice_number }}</dd>
                </div>
            </dl>
        </template>
    </div>
</template>
