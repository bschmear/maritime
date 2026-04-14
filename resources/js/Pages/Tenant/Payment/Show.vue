<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    payment: { type: Object, required: true },
});

const invoice = computed(() => props.payment?.invoice ?? null);

const invoiceLabel = computed(() => {
    if (!invoice.value) return null;
    return invoice.value.display_name ?? (invoice.value.sequence != null ? `INV-${invoice.value.sequence}` : null);
});

const paymentLabel = computed(() => {
    const dn = props.payment?.display_name;
    if (dn) {
        return dn;
    }
    const seq = props.payment?.sequence;
    return seq != null ? `Payment #${seq}` : `Payment #${props.payment?.id ?? ''}`;
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';

const formatDateTime = (val) => {
    if (!val) return '—';
    try {
        return new Date(val).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return '—';
    }
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Payments', href: route('payments.index') },
    { label: paymentLabel.value },
]);

const recordedBy = computed(() => props.payment?.recorded_by ?? null);

const hasSurcharge = computed(() =>
    parseFloat(props.payment?.surcharge_amount || 0) !== 0
);

const STATUS_STYLES = {
    completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    pending:   'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    failed:    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    refunded:  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

const statusClass = computed(() =>
    STATUS_STYLES[props.payment?.status?.toLowerCase()] ?? STATUS_STYLES.pending
);
</script>

<template>
    <Head :title="paymentLabel" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                                {{ paymentLabel }}
                            </h2>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold capitalize"
                                :class="statusClass"
                            >
                                {{ payment.status }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-md text-gray-500 dark:text-gray-400">
                            {{ formatDateTime(payment.paid_at) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('payments.edit', payment.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </Link>
                        <Link
                            v-if="invoice?.id"
                            :href="route('invoices.show', invoice.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Open invoice
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full  grid grid-cols-1 lg:grid-cols-12 gap-6 p-4">

            <!-- ── Main column ── -->
            <div class="lg:col-span-8 space-y-5">

                <!-- Amount card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount</span>
                    </div>
                    <div class="p-5">
                        <div class="flex items-baseline gap-2 mb-4">
                            <span class="text-3xl font-semibold text-gray-900 dark:text-white">
                                {{ formatCurrency(payment.amount) }}
                            </span>
                            <span class="text-md text-gray-400 dark:text-gray-500">{{ payment.currency || 'USD' }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-0.5">Net received</div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(payment.net_amount) }}</div>
                            </div>
                            <div v-if="hasSurcharge">
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-0.5">Surcharge</div>
                                <div class="text-lg font-medium text-gray-700 dark:text-gray-300">{{ formatCurrency(payment.surcharge_amount) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-0.5">Currency</div>
                                <div class="text-lg font-medium text-gray-900 dark:text-white">{{ payment.currency || 'USD' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Memo card -->
                <div
                    v-if="payment.memo"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden"
                >
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Memo</span>
                    </div>
                    <div class="p-5">
                        <p class="text-md text-gray-800 dark:text-gray-200 whitespace-pre-line leading-relaxed">{{ payment.memo }}</p>
                    </div>
                </div>

                <!-- Invoice card -->
                <div
                    v-if="invoice"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden"
                >
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</span>
                        <Link
                            :href="route('invoices.show', invoice.id)"
                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                        >
                            View invoice →
                        </Link>
                    </div>
                    <div class="p-5">
                        <div class="text-md font-semibold text-gray-900 dark:text-white mb-1">{{ invoiceLabel }}</div>
                        <div v-if="invoice.customer_name" class="text-md text-gray-500 dark:text-gray-400 mb-3">
                            {{ invoice.customer_name }}
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                            <span>
                                Status:
                                <span class="font-medium text-gray-800 dark:text-gray-200 capitalize">{{ invoice.status }}</span>
                            </span>
                            <span>
                                Total:
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatCurrency(invoice.total) }}</span>
                            </span>
                            <span v-if="invoice.amount_due != null">
                                Due:
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatCurrency(invoice.amount_due) }}</span>
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Sidebar ── -->
            <div class="lg:col-span-4 space-y-5">

                <!-- Payment details -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment details</span>
                    </div>
                    <dl class="divide-y divide-gray-50 dark:divide-gray-700/60">
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Status</dt>
                            <dd>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-semibold capitalize"
                                    :class="statusClass"
                                >
                                    {{ payment.status }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Method</dt>
                            <dd class="text-md font-medium text-gray-900 dark:text-white text-right">{{ payment.payment_method_code || '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Processor</dt>
                            <dd class="text-md font-medium text-gray-900 dark:text-white capitalize text-right">{{ payment.processor || '—' }}</dd>
                        </div>
                        <div v-if="payment.reference_number" class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Reference #</dt>
                            <dd class="text-md font-medium text-gray-900 dark:text-white text-right">{{ payment.reference_number }}</dd>
                        </div>
                        <div v-if="payment.processor_transaction_id" class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Processor ref</dt>
                            <dd class="font-mono text-sm text-gray-700 dark:text-gray-300 text-right break-all">{{ payment.processor_transaction_id }}</dd>
                        </div>
                        <div v-if="recordedBy" class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Recorded by</dt>
                            <dd class="text-md font-medium text-gray-900 dark:text-white text-right">{{ recordedBy.display_name || '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-gray-500 dark:text-gray-400 pt-0.5 shrink-0">Date</dt>
                            <dd class="text-md font-medium text-gray-900 dark:text-white text-right">{{ formatDateTime(payment.paid_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Summary</span>
                    </div>
                    <div class="p-5 space-y-2">
                        <div class="flex justify-between items-center text-md">
                            <span class="text-gray-500 dark:text-gray-400">Principal</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(payment.amount) }}</span>
                        </div>
                        <div v-if="hasSurcharge" class="flex justify-between items-center text-md">
                            <span class="text-gray-500 dark:text-gray-400">Surcharge</span>
                            <span class="text-gray-700 dark:text-gray-300">+ {{ formatCurrency(payment.surcharge_amount) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-md font-semibold text-gray-700 dark:text-gray-300">Net received</span>
                            <span class="text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(payment.net_amount) }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </TenantLayout>
</template>
