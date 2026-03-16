<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    invoices: Object,
});
</script>

<template>
    <ClientPortalLayout title="Invoices">
        <Head title="Invoices - Customer Portal" />

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Your Invoices</h2>
            </div>

            <!-- Table -->
            <div v-if="invoices?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Invoice #</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider text-right">Amount</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="inv in invoices.data" :key="inv.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ inv.invoice_number || `#${inv.id}` }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="text-xs font-medium px-2 py-1 rounded-full"
                                    :class="inv.status === 'paid' ? 'bg-green-50 text-green-700' : 'bg-secondary-50 text-secondary-700'"
                                >
                                    {{ inv.status || 'Pending' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-gray-900 font-medium">
                                {{ inv.total ? `$${Number(inv.total).toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '-' }}
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ inv.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">receipt_long</span>
                <p class="text-sm text-gray-500">No invoices found.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="invoices?.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in invoices.links" :key="link.label">
                <component
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url"
                    class="px-3 py-1.5 text-xs rounded-lg border transition-colors no-underline"
                    :class="link.active ? 'bg-primary-600 text-white border-primary-600' : link.url ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' : 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'"
                    v-html="link.label"
                />
            </template>
        </div>
    </ClientPortalLayout>
</template>
