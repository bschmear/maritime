<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    customer: Object,
    recentEstimates: Array,
    recentInvoices: Array,
    recentServiceTickets: Array,
    counts: Object,
});

const statCards = [
    { label: 'Estimates', key: 'estimates', icon: 'request_quote', color: 'primary', route: 'portal.estimates' },
    { label: 'Invoices', key: 'invoices', icon: 'receipt_long', color: 'secondary', route: 'portal.invoices' },
    { label: 'Service Tickets', key: 'serviceTickets', icon: 'build_circle', color: 'primary', route: 'portal.servicetickets' },
    { label: 'Documents', key: 'documents', icon: 'folder_open', color: 'secondary', route: 'portal.documents' },
];
</script>

<template>
    <ClientPortalLayout title="Overview">
        <Head title="Customer Portal" />

        <!-- Welcome -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">
                Welcome{{ customer?.first_name ? `, ${customer.first_name}` : '' }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">Here's an overview of your account activity.</p>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <Link
                v-for="card in statCards"
                :key="card.key"
                :href="route(card.route)"
                class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow group no-underline"
            >
                <div class="flex items-center justify-between mb-3">
                    <div
                        class="w-10 h-10 rounded-lg flex items-center justify-center"
                        :class="card.color === 'primary' ? 'bg-primary-100 text-primary-600' : 'bg-secondary-100 text-secondary-600'"
                    >
                        <span class="material-icons text-xl">{{ card.icon }}</span>
                    </div>
                    <span class="material-icons text-gray-300 group-hover:text-gray-400 transition-colors text-sm">
                        arrow_forward
                    </span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ counts?.[card.key] ?? 0 }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ card.label }}</p>
            </Link>
        </div>

        <!-- Recent Activity Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Estimates -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900 text-sm">Recent Estimates</h3>
                    <Link :href="route('portal.estimates')" class="text-xs font-medium text-primary-600 hover:text-primary-700 no-underline">
                        View all
                    </Link>
                </div>
                <div v-if="recentEstimates?.length" class="divide-y divide-gray-50">
                    <div v-for="est in recentEstimates" :key="est.id" class="px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ est.title || `Estimate #${est.id}` }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ est.created_at }}</p>
                            </div>
                            <span class="text-xs font-medium px-2 py-1 rounded-full bg-primary-50 text-primary-700">
                                {{ est.status || 'Draft' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-gray-400">No estimates yet</div>
            </div>

            <!-- Recent Invoices -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900 text-sm">Recent Invoices</h3>
                    <Link :href="route('portal.invoices')" class="text-xs font-medium text-primary-600 hover:text-primary-700 no-underline">
                        View all
                    </Link>
                </div>
                <div v-if="recentInvoices?.length" class="divide-y divide-gray-50">
                    <div v-for="inv in recentInvoices" :key="inv.id" class="px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ inv.invoice_number || `Invoice #${inv.id}` }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ inv.created_at }}</p>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ inv.total ? `$${Number(inv.total).toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '-' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-gray-400">No invoices yet</div>
            </div>

            <!-- Recent Service Tickets -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900 text-sm">Recent Service Tickets</h3>
                    <Link :href="route('portal.servicetickets')" class="text-xs font-medium text-primary-600 hover:text-primary-700 no-underline">
                        View all
                    </Link>
                </div>
                <div v-if="recentServiceTickets?.length" class="divide-y divide-gray-50">
                    <div v-for="ticket in recentServiceTickets" :key="ticket.id" class="px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ ticket.title || `Ticket #${ticket.id}` }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ ticket.created_at }}</p>
                            </div>
                            <span
                                class="text-xs font-medium px-2 py-1 rounded-full"
                                :class="ticket.status === 'closed' ? 'bg-green-50 text-green-700' : 'bg-secondary-50 text-secondary-700'"
                            >
                                {{ ticket.status || 'Open' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-gray-400">No service tickets yet</div>
            </div>
        </div>
    </ClientPortalLayout>
</template>
