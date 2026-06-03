<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    account: Object,
    invoices: Array,
    can_manage_billing: Boolean,
});

const breadcrumbItems = computed(() => [
    { label: 'Dashboard', href: route('dashboard') },
    { label: props.account.name, href: route('accounts.show', props.account.id) },
    { label: 'Invoices' },
]);

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const statusClass = (status) => {
    if (status === 'paid') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
    }
    if (status === 'open') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
};
</script>

<template>
    <Head :title="`Invoices — ${account.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">Subscription invoices</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ account.name }}</p>
            </div>
        </template>

        <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex justify-end">
                <Link
                    :href="route('accounts.show', account.id)"
                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                >
                    Back to account
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Invoice</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(invoice.date) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ invoice.number || invoice.id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ invoice.total }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClass(invoice.status)">
                                    {{ invoice.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link
                                    :href="route('accounts.invoices.show', { account: account.id, invoice: invoice.id })"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!invoices?.length">
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No subscription invoices found yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
