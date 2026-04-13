<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
});

const STATUS_ENUM_KEY = 'App\\Enums\\Invoice\\Status';
const PAYMENT_TERM_ENUM_KEY = 'App\\Enums\\Payments\\Terms';

const statusOptions = computed(() => props.enumOptions?.[STATUS_ENUM_KEY] ?? []);
const paymentTermOptions = computed(() => props.enumOptions?.[PAYMENT_TERM_ENUM_KEY] ?? []);

const statusLabel = computed(() =>
    statusOptions.value.find(o => o.id == props.record.status || o.value === props.record.status)?.name
    ?? props.record.status
    ?? 'Draft'
);

const paymentTermLabel = computed(() =>
    paymentTermOptions.value.find(o => (o.value ?? o.id) == props.record.payment_term)?.name
    ?? props.record.payment_term
    ?? '—'
);

const statusBadgeClass = computed(() => {
    const val = statusOptions.value.find(o => o.id == props.record.status)?.value ?? props.record.status ?? '';
    const map = {
        draft:     'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        sent:      'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        viewed:    'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
        paid:      'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        overdue:   'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        cancelled: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        void:      'bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-400',
    };
    return map[val] ?? map.draft;
});

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

const formatDate = (val) => val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : null;

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Invoices', href: route('invoices.index') },
    { label: `Invoice #${props.record.sequence ?? props.record.id}` },
]);
</script>

<template>
    <Head :title="`Invoice #${record.sequence ?? record.id}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Invoice #{{ record.sequence ?? record.id }}
                    </h2>
                    <div class="flex items-center gap-2">
                        <a
                            :href="route('invoices.edit', record.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full">
            <div class="grid grid-cols-12 gap-4 p-4">

                <!-- ============================
                     Main: Invoice Document
                     ============================ -->
                <div class="col-span-12 2xl:col-span-9">
                    <div class="space-y-4 rounded-lg bg-white p-6 shadow-md dark:bg-gray-800 sm:space-y-8 md:p-8">

                        <!-- Logo + Header -->
                        <div class="flex items-start justify-between">
                            <a :href="route('dashboard')" class="flex items-center">
                                <ApplicationLogo
                                    class="block mr-3 h-8 w-auto fill-current text-gray-800 dark:text-white"
                                />
                            </a>
                            <div class="text-right">
                                <span :class="['inline-flex px-3 py-1 rounded-full text-sm font-semibold', statusBadgeClass]">
                                    {{ statusLabel }}
                                </span>
                            </div>
                        </div>

                        <!-- Invoice # + Date -->
                        <div class="flex items-center justify-between border-b border-t border-gray-100 py-4 dark:border-gray-700">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                Invoice #{{ record.sequence ?? record.id }}
                            </h1>
                            <time class="text-base text-gray-500 dark:text-gray-400">
                                Date: {{ formatDate(record.created_at) }}
                            </time>
                        </div>

                        <!-- Pay To / Invoice To -->
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-8">
                            <div class="sm:w-64">
                                <h2 class="mb-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">From:</h2>
                                <address class="not-italic text-sm text-gray-700 dark:text-gray-300 space-y-0.5">
                                    <span class="block font-semibold text-gray-900 dark:text-white">Your Company</span>
                                    <span class="block text-gray-500 dark:text-gray-400">your@company.com</span>
                                </address>
                            </div>

                            <div class="sm:w-64">
                                <h2 class="mb-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Bill To:</h2>
                                <address class="not-italic text-sm text-gray-700 dark:text-gray-300 space-y-0.5">
                                    <span v-if="record.customer_name" class="block font-semibold text-gray-900 dark:text-white">{{ record.customer_name }}</span>
                                    <span v-if="record.customer_email" class="block">{{ record.customer_email }}</span>
                                    <span v-if="record.customer_phone" class="block text-gray-500 dark:text-gray-400">{{ record.customer_phone }}</span>
                                    <template v-if="record.billing_address_line1">
                                        <span class="block mt-1">{{ record.billing_address_line1 }}</span>
                                        <span v-if="record.billing_address_line2" class="block">{{ record.billing_address_line2 }}</span>
                                        <span class="block">
                                            {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                                        </span>
                                        <span v-if="record.billing_country" class="block text-gray-500 dark:text-gray-400">{{ record.billing_country }}</span>
                                    </template>
                                </address>
                            </div>
                        </div>

                        <!-- Line Items Table -->
                        <div class="relative overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-700">
                            <table class="w-full text-left text-sm font-medium text-gray-900 dark:text-white rtl:text-right">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 font-semibold">Item</th>
                                        <th scope="col" class="px-6 py-3 font-semibold">Qty</th>
                                        <th scope="col" class="px-6 py-3 font-semibold">Unit Price</th>
                                        <th scope="col" class="px-6 py-3 font-semibold">Discount</th>
                                        <th scope="col" class="px-6 py-3 font-semibold text-nowrap">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="record.line_items && record.line_items.length">
                                        <tr
                                            v-for="item in record.line_items"
                                            :key="item.id"
                                            class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800"
                                        >
                                            <th scope="row" class="h-16 space-y-1 whitespace-nowrap px-6 font-medium text-gray-900 dark:text-white">
                                                <div class="text-base">{{ item.name ?? item.description }}</div>
                                                <div v-if="item.description && item.name" class="font-normal text-xs leading-none text-gray-500 dark:text-gray-400">{{ item.description }}</div>
                                            </th>
                                            <td class="h-16 px-6">{{ item.quantity ?? 1 }}</td>
                                            <td class="h-16 px-6">{{ formatCurrency(item.unit_price ?? item.price) }}</td>
                                            <td class="h-16 px-6">{{ item.discount_percent != null ? `${item.discount_percent}%` : (item.discount ? formatCurrency(item.discount) : '—') }}</td>
                                            <td class="h-16 px-6">{{ formatCurrency(item.total ?? item.line_total) }}</td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                            No line items
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals -->
                        <div class="ms-auto mt-4 max-w-xs">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Order Summary</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.subtotal) }}</span>
                                </li>
                                <li v-if="record.discount_total && parseFloat(record.discount_total) !== 0" class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Discount</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">-{{ formatCurrency(record.discount_total) }}</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.tax_total) }}</span>
                                </li>
                                <li v-if="record.fees_total && parseFloat(record.fees_total) !== 0" class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Fees</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(record.fees_total) }}</span>
                                </li>
                                <li class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700 font-bold text-base text-gray-900 dark:text-white">
                                    <span>Total</span>
                                    <span>{{ formatCurrency(record.total) }}</span>
                                </li>
                                <li v-if="record.amount_paid && parseFloat(record.amount_paid) !== 0" class="flex items-center justify-between text-green-600 dark:text-green-400">
                                    <span>Amount Paid</span>
                                    <span>-{{ formatCurrency(record.amount_paid) }}</span>
                                </li>
                                <li v-if="record.amount_due != null" class="flex items-center justify-between font-bold text-primary-600 dark:text-primary-400 pt-2 border-t border-gray-100 dark:border-gray-700 text-base">
                                    <span>Amount Due</span>
                                    <span>{{ formatCurrency(record.amount_due) }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Notes -->
                        <div v-if="record.notes" class="border-t border-gray-100 dark:border-gray-700 pt-6">
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ record.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- ============================
                     Sidebar: Status & Details
                     ============================ -->
                <div class="col-span-12 2xl:col-span-3">
                    <div class="relative h-full space-y-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:space-y-6 md:p-6">

                        <!-- Paid alert -->
                        <div
                            v-if="record.paid_at"
                            class="flex items-center rounded-lg bg-green-50 p-4 text-base font-medium text-green-800 dark:bg-gray-700 dark:text-green-300"
                            role="alert"
                        >
                            <span class="material-icons text-[18px] me-2 shrink-0">info</span>
                            <span class="sr-only">Info</span>
                            <div>Invoice paid</div>
                        </div>

                        <!-- Overdue alert -->
                        <div
                            v-else-if="record.due_at && new Date(record.due_at) < new Date() && record.amount_due > 0"
                            class="flex items-center rounded-lg bg-red-50 p-4 text-base font-medium text-red-800 dark:bg-red-900/20 dark:text-red-300"
                            role="alert"
                        >
                            <span class="material-icons text-[18px] me-2 shrink-0">warning</span>
                            <div>Invoice overdue</div>
                        </div>

                        <h2 class="text-lg text-gray-500 dark:text-gray-400">Details:</h2>

                        <!-- Amount + Status badge -->
                        <div class="flex items-center justify-between border-b border-t border-gray-100 py-4 dark:border-gray-700">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(record.total) }}</h3>
                            <span :class="['px-2.5 py-0.5 rounded-full text-xs font-semibold', statusBadgeClass]">
                                {{ statusLabel }}
                            </span>
                        </div>

                        <!-- Details list -->
                        <ul class="max-w-md list-inside space-y-4 border-b border-gray-100 pb-4 text-gray-500 dark:border-gray-700 dark:text-gray-400 sm:pb-6">
                            <li v-if="record.customer_name" class="flex items-center">
                                <span class="material-icons text-[20px] me-2 shrink-0">person</span>
                                <span class="me-2 font-medium text-gray-900 dark:text-white">Customer:</span>
                                {{ record.customer_name }}
                            </li>
                            <li v-if="record.due_at" class="flex items-center">
                                <span class="material-icons text-[20px] me-2 shrink-0">event</span>
                                <span class="me-2 font-medium text-gray-900 dark:text-white">Due date:</span>
                                {{ formatDate(record.due_at) }}
                            </li>
                            <li class="flex items-center">
                                <span class="material-icons text-[20px] me-2 shrink-0">credit_card</span>
                                <span class="me-2 font-medium text-gray-900 dark:text-white">Terms:</span>
                                {{ paymentTermLabel }}
                            </li>
                            <li class="flex items-center">
                                <span class="material-icons text-[20px] me-2 shrink-0">payments</span>
                                <span class="me-2 font-medium text-gray-900 dark:text-white">Currency:</span>
                                {{ record.currency || 'USD' }}
                            </li>
                            <li v-if="record.amount_due != null" class="flex items-center">
                                <span class="material-icons text-[20px] me-2 shrink-0">account_balance_wallet</span>
                                <span class="me-2 font-medium text-gray-900 dark:text-white">Amount Due:</span>
                                <span class="font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(record.amount_due) }}</span>
                            </li>
                        </ul>

                        <!-- Timeline -->
                        <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700">
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span class="material-icons text-[20px] text-green-500">check_circle</span>
                                </span>
                                <div class="flex items-center justify-between">
                                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white text-sm">Invoice created</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(record.created_at) }}</time>
                                </div>
                            </li>
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span :class="['material-icons text-[20px]', record.sent_at ? 'text-green-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                </span>
                                <div class="flex items-center justify-between">
                                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white text-sm">Invoice sent</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">{{ record.sent_at ? formatDate(record.sent_at) : 'Pending' }}</time>
                                </div>
                            </li>
                            <li class="mb-4 ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span :class="['material-icons text-[20px]', record.viewed_at ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                </span>
                                <div class="flex items-center justify-between">
                                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white text-sm">Invoice viewed</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">{{ record.viewed_at ? formatDate(record.viewed_at) : 'Not yet' }}</time>
                                </div>
                            </li>
                            <li class="ms-6">
                                <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                    <span :class="['material-icons text-[20px]', record.paid_at ? 'text-green-600' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                </span>
                                <div class="flex items-center justify-between">
                                    <h4 class="flex items-center font-medium text-gray-900 dark:text-white text-sm">Invoice paid</h4>
                                    <time class="text-xs text-gray-500 dark:text-gray-400">{{ record.paid_at ? formatDate(record.paid_at) : 'Pending' }}</time>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

            </div>

        </div>
    </TenantLayout>
</template>