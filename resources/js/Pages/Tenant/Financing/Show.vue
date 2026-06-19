<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import FinancingForm from '@/Components/Tenant/FinancingForm.vue';
import FinancingAlerts from '@/Components/Tenant/FinancingAlerts.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const STATUS_ENUM = 'App\\Enums\\Financing\\Status';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'financings' },
    recordTitle: { type: String, default: 'Financing' },
    domainName: { type: String, default: 'Financing' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    financingMetrics: { type: Object, default: () => ({}) },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showUnitDataPanel = ref(false);

const label = computed(() => props.record.display_name || `Financing #${props.record.id}`);
const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));
const sublists = computed(() => props.formSchema?.sublists || []);

const statusInfo = computed(() => {
    const opts = props.enumOptions?.[STATUS_ENUM] ?? [];
    const status = props.record.status?.value ?? props.record.status;
    return opts.find((o) => o.value === status) ?? { name: status, bgClass: 'bg-gray-100 text-gray-700' };
});

const metrics = computed(() => props.financingMetrics ?? {});

const formatCurrency = (v) =>
    v != null ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Financings', href: indexHref.value },
    { label: label.value },
]);

// Balance / paid-off helpers
const principal = computed(() => parseFloat(props.record.principal_amount ?? 0));
const balance = computed(() => parseFloat(props.record.current_balance ?? 0));
const paidOff = computed(() => Math.max(0, principal.value - balance.value));
const paidOffPct = computed(() => {
    if (!principal.value) return 0;
    return Math.min(100, Math.round((paidOff.value / principal.value) * 100));
});

// Create asset unit href — prefill serial and link back
const createAssetUnitHref = computed(() => {
    const params = new URLSearchParams();
    if (props.record.serial_vin) params.set('serial_number', props.record.serial_vin);
    params.set('link_financing_id', String(props.record.id));
    params.set('return_url', route(`${props.recordType}.show`, props.record.id));
    return `${route('assetunits.create')}?${params.toString()}`;
});

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="label" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
                    <!-- Title + badges -->
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ label }}</h2>
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-semibold"
                                :class="statusInfo.bgClass"
                            >
                                {{ statusInfo.name }}
                            </span>
                            <span
                                v-if="metrics.at_risk"
                                class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-sm font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-200"
                            >
                                At risk
                            </span>
                        </div>
                        <!-- Linked asset unit or prompt -->
                        <div class="mt-1 flex items-center gap-2">
                            <template v-if="record.asset_unit">
                                <Link
                                    :href="route('assetunits.show', record.asset_unit_id)"
                                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    {{ record.asset_unit.display_name }}
                                </Link>
                            </template>
                            <span v-else class="text-sm text-amber-600 dark:text-amber-400">No asset unit linked</span>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex flex-wrap gap-2">
                        <!-- Create asset unit when not linked -->
                        <template v-if="!record.asset_unit_id">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-600 dark:bg-amber-900/30 dark:text-amber-200"
                                @click="showUnitDataPanel = !showUnitDataPanel"
                            >
                                {{ showUnitDataPanel ? 'Hide data' : 'View data to copy' }}
                            </button>
                            <Link
                                :href="createAssetUnitHref"
                                class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                            >
                                Create asset unit
                            </Link>
                        </template>

                        <Link
                            :href="editHref"
                            class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400"
                            @click="showDeleteModal = true"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Data-copy panel (unlinked only) -->
        <div
            v-if="showUnitDataPanel && !record.asset_unit_id"
            class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20"
        >
            <p class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-200">
                Financing data — select &amp; copy into asset unit form
            </p>
            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                <div v-if="record.serial_vin" class="flex gap-1.5">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Serial / VIN:</dt>
                    <dd class="select-all font-mono text-gray-900 dark:text-white">{{ record.serial_vin }}</dd>
                </div>
                <div v-if="record.model_year" class="flex gap-1.5">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Year:</dt>
                    <dd class="select-all text-gray-900 dark:text-white">{{ record.model_year }}</dd>
                </div>
                <div v-if="record.model_number" class="flex gap-1.5">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Model:</dt>
                    <dd class="select-all text-gray-900 dark:text-white">{{ record.model_number }}</dd>
                </div>
                <div v-if="record.supplier_name" class="flex gap-1.5">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Supplier:</dt>
                    <dd class="select-all text-gray-900 dark:text-white">{{ record.supplier_name }}</dd>
                </div>
                <div v-if="record.lender_invoice_number" class="flex gap-1.5">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Invoice #:</dt>
                    <dd class="select-all font-mono text-gray-900 dark:text-white">{{ record.lender_invoice_number }}</dd>
                </div>
            </dl>
            <div class="mt-3">
                <Link
                    :href="createAssetUnitHref"
                    class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
                >
                    Create asset unit →
                </Link>
            </div>
        </div>

        <div class="space-y-6">
            <FinancingAlerts
                v-if="record.status?.value === 'active' || record.status === 'active'"
                :financing="record"
                :metrics="metrics"
            />

            <!-- Balance summary bar -->
            <div class="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-950/30">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="grid grid-cols-2 gap-x-10 gap-y-3 sm:grid-cols-4">
                        <div>
                            <p class="text-xs font-medium text-primary-700 dark:text-primary-300">Principal</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-primary-950 dark:text-primary-100">
                                {{ formatCurrency(record.principal_amount) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-primary-700 dark:text-primary-300">Balance remaining</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-primary-950 dark:text-primary-100">
                                {{ formatCurrency(record.current_balance) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-primary-700 dark:text-primary-300">Paid off</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-green-700 dark:text-green-400">
                                {{ formatCurrency(paidOff) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-primary-700 dark:text-primary-300">% paid off</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums text-primary-950 dark:text-primary-100">
                                {{ paidOffPct }}%
                            </p>
                        </div>
                    </div>
                    <div v-if="record.aging_days != null" class="text-right">
                        <p class="text-xs font-medium text-primary-700 dark:text-primary-300">Aging</p>
                        <p class="mt-0.5 text-xl font-bold tabular-nums text-primary-950 dark:text-primary-100">
                            {{ record.aging_days }} days
                        </p>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="mt-4">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-primary-200 dark:bg-primary-800">
                        <div
                            class="h-full rounded-full bg-green-500 transition-all"
                            :style="{ width: `${paidOffPct}%` }"
                        />
                    </div>
                    <div class="mt-1 flex justify-between text-xs text-primary-600 dark:text-primary-400">
                        <span>{{ paidOffPct }}% paid off</span>
                        <span>{{ formatCurrency(balance) }} remaining</span>
                    </div>
                </div>
            </div>

            <!-- Metrics grid -->
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Financing metrics</h3>
                <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Aging (inventory)</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ metrics.days_financed ?? '—' }} days</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Interest months elapsed</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ metrics.interest_months_elapsed ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Loan term</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ record.loan_term_months ? `${record.loan_term_months} mo` : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Interest paid</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(metrics.total_interest_paid) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Accrued interest</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(metrics.accrued_interest) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Est. total interest</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(metrics.estimated_total_interest) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Est. monthly interest</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(metrics.estimated_monthly_interest) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Monthly payment</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCurrency(metrics.monthly_payment_due) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Record detail fields -->
            <FinancingForm
                :schema="formSchema"
                :fields-schema="fieldsSchema"
                :record="record"
                :enum-options="enumOptions"
                mode="view"
            />
        </div>

        <div v-if="sublists.length" class="mt-8 space-y-6">
            <Sublist
                v-for="(sub, idx) in sublists"
                :key="idx"
                :sublist="sub"
                :parent-record="record"
                :parent-domain="domainName"
            />
        </div>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete financing?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showDeleteModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
