<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SupportTicketStatusBadge from '@/Components/Tenant/SupportTicketStatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    activeTicket: { type: Object, default: null },
    inactiveTickets: { type: Object, required: true },
    ticketCategories: { type: Array, default: () => [] },
    canCreateTicket: { type: Boolean, default: true },
});

const page = usePage();
const showCreateModal = ref(false);

const breadcrumbItems = [
    { label: 'Home', href: route('dashboard') },
    { label: 'Help', href: route('dashHelp') },
    { label: 'Support' },
];

const hasAnyTickets = computed(
    () => props.activeTicket || (props.inactiveTickets.data?.length ?? 0) > 0,
);

const form = useForm({
    subject: '',
    message: '',
    category: props.ticketCategories[0]?.id ?? 1,
});

const submit = () => {
    form.post(route('storeTicket'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const formatDate = (iso, long = false) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (long) {
        return d.toLocaleString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }
    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Support" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="flex grow flex-col space-y-4 md:space-y-6">
            <!-- Empty state -->
            <div
                v-if="!hasAnyTickets"
                class="relative flex grow flex-col overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800"
            >
                <div class="flex grow flex-col items-center justify-center overflow-x-auto p-8 text-gray-700 dark:text-gray-400">
                    <div class="relative flex max-w-sm flex-col items-center text-center">
                        <span class="material-icons text-4xl text-gray-300 dark:text-gray-500">confirmation_number</span>
                        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white md:text-3xl">
                            You haven't submitted any tickets yet.
                        </h2>
                        <button
                            type="button"
                            class="btn-primary flex items-center gap-2"
                            @click="showCreateModal = true"
                        >
                            <span class="material-icons text-base">add</span>
                            <span>Create Ticket</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active ticket -->
            <div
                v-if="activeTicket"
                class="mb-4 overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800 md:mb-6"
            >
                <div class="grid grid-cols-1 gap-4 border-b p-4 sm:grid-cols-2 md:p-6">
                    <div class="flex flex-1 items-center">
                        <h1 class="font-semibold dark:text-white">
                            <span class="text-lg font-semibold text-gray-900 dark:bg-gray-800 dark:text-white">
                                Active Ticket — {{ activeTicket.ticket_number }}
                            </span>
                        </h1>
                    </div>
                    <div class="flex items-center space-x-2 sm:justify-end">
                        <Link
                            :href="route('showTicket', { uid: activeTicket.uid })"
                            class="btn-primary rounded-md px-4 py-2 text-sm"
                        >
                            View Ticket
                        </Link>
                    </div>
                </div>
                <div class="bg-white p-4 text-gray-900 md:p-6 dark:bg-gray-800 dark:text-white">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
                        <div class="lg:col-span-3">
                            <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-white">Subject</label>
                            <div>{{ activeTicket.subject }}</div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-white">Created</label>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(activeTicket.created_at, true) }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-white">Category</label>
                            <span
                                class="inline-flex items-center rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {{ activeTicket.category_label }}
                            </span>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-white">Status</label>
                            <SupportTicketStatusBadge
                                :label="activeTicket.status_label"
                                :color="activeTicket.status_color"
                            />
                        </div>
                    </div>
                </div>
                <div class="border-t p-4 md:p-6">
                    <p class="text-sm italic text-gray-600 dark:text-gray-400">
                        You can only have <span class="font-bold">one</span> open ticket at a time.
                    </p>
                </div>
            </div>

            <!-- Inactive tickets table -->
            <div
                v-if="inactiveTickets.data?.length"
                class="overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800"
            >
                <div class="flex items-center justify-between px-4 pt-4 md:pb-4">
                    <h1 class="font-semibold dark:text-white">
                        <span class="bg-white text-lg font-semibold text-gray-900 dark:bg-gray-800 dark:text-white">
                            {{ activeTicket ? 'Other Tickets' : 'Your Tickets' }}
                        </span>
                    </h1>
                    <div v-if="canCreateTicket" class="flex items-center space-x-2">
                        <button
                            type="button"
                            class="btn-primary flex items-center gap-2 rounded-md px-4 py-2 text-sm"
                            @click="showCreateModal = true"
                        >
                            <span class="material-icons text-base">add</span>
                            <span>New ticket</span>
                        </button>
                    </div>
                    <div v-else-if="activeTicket" class="flex items-center space-x-2 sm:justify-end">
                        <Link
                            :href="route('showTicket', { uid: activeTicket.uid })"
                            class="btn-primary rounded-md px-4 py-2 text-sm"
                        >
                            View Open Ticket
                        </Link>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">ID</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">Subject</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">Status</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="ticket in inactiveTickets.data"
                                :key="ticket.id"
                                class="border-b border-gray-200 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    <Link
                                        :href="route('showTicket', { uid: ticket.uid })"
                                        class="underline-offset-2 hover:underline"
                                    >
                                        {{ ticket.ticket_number }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3">{{ ticket.subject }}</td>
                                <td class="px-4 py-3">
                                    <SupportTicketStatusBadge
                                        :label="ticket.status_label"
                                        :color="ticket.status_color"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{ formatDate(ticket.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="inactiveTickets.last_page > 1"
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Page {{ inactiveTickets.current_page }} of {{ inactiveTickets.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="inactiveTickets.prev_page_url"
                            :href="inactiveTickets.prev_page_url"
                            class="btn-outline rounded-md px-3 py-1.5 text-sm"
                            preserve-state
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="inactiveTickets.next_page_url"
                            :href="inactiveTickets.next_page_url"
                            class="btn-outline rounded-md px-3 py-1.5 text-sm"
                            preserve-state
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>

            <div
                v-if="page.props.flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300"
            >
                {{ page.props.flash.error }}
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="2xl" @close="showCreateModal = false">
            <div class="rounded-lg bg-white shadow dark:bg-gray-800">
                <div class="flex items-center justify-between rounded-t border-b p-4 md:p-5 dark:border-gray-600">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Support Ticket</h3>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        @click="showCreateModal = false"
                    >
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <form class="p-4 md:p-5" @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <label for="subject" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                Subject
                            </label>
                            <input
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                required
                                class="input-style"
                                autocomplete="off"
                            />
                            <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                        </div>
                        <div>
                            <label for="category" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                Category
                            </label>
                            <select id="category" v-model="form.category" required class="input-style">
                                <option v-for="c in ticketCategories" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p>
                        </div>
                        <div>
                            <label for="message" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                Message
                            </label>
                            <textarea id="message" v-model="form.message" rows="8" required class="input-style" />
                            <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-2 border-t pt-4 dark:border-gray-600">
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            Submit Ticket
                        </button>
                        <button type="button" class="btn-outline" @click="showCreateModal = false">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </TenantLayout>
</template>
