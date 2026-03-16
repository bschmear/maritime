<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    serviceTickets: Object,
});
</script>

<template>
    <ClientPortalLayout title="Service Tickets">
        <Head title="Service Tickets - Customer Portal" />

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Your Service Tickets</h2>
            </div>

            <!-- Table -->
            <div v-if="serviceTickets?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Title</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Priority</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="ticket in serviceTickets.data" :key="ticket.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ ticket.title || `Ticket #${ticket.id}` }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="text-xs font-medium px-2 py-1 rounded-full"
                                    :class="{
                                        'bg-green-50 text-green-700': ticket.status === 'closed' || ticket.status === 'completed',
                                        'bg-yellow-50 text-yellow-700': ticket.status === 'in_progress',
                                        'bg-secondary-50 text-secondary-700': !ticket.status || ticket.status === 'open',
                                    }"
                                >
                                    {{ ticket.status || 'Open' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 capitalize">{{ ticket.priority || '-' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ ticket.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">build_circle</span>
                <p class="text-sm text-gray-500">No service tickets found.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="serviceTickets?.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in serviceTickets.links" :key="link.label">
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
