<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    serviceTickets: Object,
    serviceTicketStatusOptions: { type: Array, default: () => [] },
});

const statusOption = (raw) => {
    if (raw == null || raw === '') {
        return null;
    }
    return props.serviceTicketStatusOptions.find(
        (o) => o.id === raw || o.value === raw || String(o.id) === String(raw),
    );
};

const statusLabel = (raw) => statusOption(raw)?.name ?? '—';

const statusBadgeClass = (raw) => {
    const color = statusOption(raw)?.color || 'gray';
    const map = {
        green: 'bg-green-50 text-green-700',
        blue: 'bg-blue-50 text-blue-700',
        yellow: 'bg-yellow-50 text-yellow-700',
        slate: 'bg-slate-100 text-slate-700',
        red: 'bg-red-50 text-red-700',
        gray: 'bg-gray-100 text-gray-700',
    };
    return `text-xs font-medium px-2 py-1 rounded-full ${map[color] || map.gray}`;
};

const openPrintReview = (uuid) => {
    const u = new URL(route('service-tickets.review', uuid), window.location.origin);
    u.searchParams.set('autoprint', '1');
    window.open(u.toString(), '_blank', 'noopener,noreferrer');
};
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
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Date</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="ticket in serviceTickets.data" :key="ticket.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                <Link
                                    v-if="ticket.uuid"
                                    :href="route('portal.servicetickets.show', ticket.uuid)"
                                    class="text-primary-600 hover:text-primary-700 hover:underline"
                                >
                                    {{ ticket.title || `Ticket #${ticket.id}` }}
                                </Link>
                                <span v-else>{{ ticket.title || `Ticket #${ticket.id}` }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span :class="statusBadgeClass(ticket.status)">
                                    {{ statusLabel(ticket.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ ticket.created_at }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <template v-if="ticket.uuid">
                                    <Link
                                        :href="route('portal.servicetickets.show', ticket.uuid)"
                                        class="text-xs font-medium text-primary-600 hover:text-primary-700"
                                    >
                                        View
                                    </Link>
                                    <span class="mx-2 text-gray-300">|</span>
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-gray-600 hover:text-gray-900"
                                        @click="openPrintReview(ticket.uuid)"
                                    >
                                        Print
                                    </button>
                                </template>
                            </td>
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
