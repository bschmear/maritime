<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import SupportTicketStatusBadge from '@/Components/Tenant/SupportTicketStatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ tickets: Object, filters: Object, statusOptions: Array });
const search = ref(props.filters?.search || '');

const searchTickets = () => {
    router.get(route('kiosk.support-tickets.index'), { search: search.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Support Tickets" />
    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Support Tickets</h1>
        </template>
        <div class="space-y-6">
            <input v-model="search" type="search" placeholder="Search tickets..." class="max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" @keyup.enter="searchTickets" />
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Ticket</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Subject</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">User</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="ticket in tickets.data" :key="ticket.id">
                            <td class="px-4 py-3 text-sm">
                                <Link :href="route('kiosk.support-tickets.show', ticket.id)" class="text-primary-600 text-sm">{{ ticket.ticket_number }}</Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ ticket.subject }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ ticket.user?.email }}</td>
                            <td class="px-4 py-3">
                                <SupportTicketStatusBadge
                                    :label="ticket.status_label"
                                    :color="ticket.status_color"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('kiosk.support-tickets.show', ticket.id)" class="text-primary-600 text-sm">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </KioskLayout>
</template>
