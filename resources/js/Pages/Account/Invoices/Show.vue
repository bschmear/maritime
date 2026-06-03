<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account: Object,
    invoice: Object,
    can_manage_billing: Boolean,
});

const breadcrumbItems = computed(() => [
    { label: 'Dashboard', href: route('dashboard') },
    { label: props.account.name, href: route('accounts.show', props.account.id) },
    { label: 'Invoices', href: route('accounts.invoices.index', props.account.id) },
    { label: props.invoice.number || props.invoice.id },
]);

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head :title="`Invoice ${invoice.number || invoice.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    Invoice {{ invoice.number || invoice.id }}
                </h2>
                <p class="mt-1 text-sm capitalize text-gray-500 dark:text-gray-400">{{ invoice.status }}</p>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-3">
                <a
                    v-if="invoice.invoice_pdf"
                    :href="invoice.invoice_pdf"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-600"
                >
                    Download PDF
                </a>
                <a
                    v-if="invoice.hosted_invoice_url"
                    :href="invoice.hosted_invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    View on Stripe
                </a>
                <Link
                    :href="route('accounts.invoices.index', account.id)"
                    class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    All invoices
                </Link>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Invoice date</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(invoice.date) }}</dd>
                    </div>
                    <div v-if="invoice.due_date">
                        <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Due date</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(invoice.due_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ invoice.subtotal }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tax</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ invoice.tax }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ invoice.total }}</dd>
                    </div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Line items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Description</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Qty</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="(line, index) in invoice.lines" :key="index">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ line.description }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ line.quantity ?? 1 }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ line.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
