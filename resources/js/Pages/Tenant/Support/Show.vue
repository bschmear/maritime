<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SupportTicketStatusBadge from '@/Components/Tenant/SupportTicketStatusBadge.vue';
import SupportTicketAvatar from '@/Components/Tenant/SupportTicketAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    appName: { type: String, default: 'Support' },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Help', href: route('dashHelp') },
    { label: 'Support', href: route('dashSupport') },
    { label: props.ticket.ticket_number },
]);

const replyForm = useForm({
    uid: props.ticket.uid,
    response: '',
});

const reopenForm = useForm({ uid: props.ticket.uid });

const submitReply = () => {
    replyForm.post(route('ticketReply'), {
        onSuccess: () => replyForm.reset('response'),
    });
};

const reopen = () => reopenForm.put(route('reopenTicket'));

const formatDate = (iso, options = {}) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        ...options,
    });
};

const formatRelative = (iso) => {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
};

const escapeHtml = (text) => {
    if (!text) return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\n/g, '<br>');
};

const messageHtml = computed(() => escapeHtml(props.ticket.message));
const responseHtml = (text) => escapeHtml(text);

const scrollToBottom = () => {
    const el = document.getElementById('last-response');
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <Head :title="ticket.subject" />

    <TenantLayout>
        <template #header>
            <Breadcrumb :items="breadcrumbItems" />
        </template>

        <div class="grid grid-cols-12 gap-4 lg:gap-6">
            <!-- Main thread -->
            <div class="col-span-full space-y-4 lg:col-span-8">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div
                        class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-center gap-3 sm:space-x-4">
                            <h1 class="font-semibold text-gray-900 dark:text-white sm:text-xl">
                                {{ ticket.ticket_number }}
                            </h1>
                            <SupportTicketStatusBadge
                                :label="ticket.status_label"
                                :color="ticket.status_color"
                            />
                        </div>
                        <button type="button" class="text-sm font-medium text-primary-600 hover:underline" @click="scrollToBottom">
                            Scroll to bottom
                        </button>
                    </div>

                    <div class="p-4 dark:bg-gray-900 xl:p-8">
                        <div
                            class="mb-4 flex flex-col justify-between border-b border-gray-200 pb-4 dark:border-gray-800 sm:flex-row sm:items-center"
                        >
                            <div>
                                <h2 class="mb-1.5 text-xl font-medium leading-none text-gray-900 dark:text-white">
                                    {{ ticket.subject }}
                                </h2>
                                <span class="text-gray-500 dark:text-gray-400">{{ ticket.category_label }}</span>
                            </div>
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 sm:mt-0">
                                {{ formatDate(ticket.created_at) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <SupportTicketAvatar :name="ticket.user?.name" />
                            <div class="font-semibold dark:text-white">
                                <div>{{ ticket.user?.name }}</div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    to {{ appName }} Support
                                </div>
                            </div>
                        </div>
                        <div
                            class="py-8 text-gray-500 dark:text-gray-400"
                            v-html="messageHtml"
                        />
                    </div>

                    <div
                        v-for="(response, index) in ticket.public_responses"
                        :id="index === ticket.public_responses.length - 1 ? 'last-response' : undefined"
                        :key="response.id"
                        class="p-4 !pb-0 !pt-0 dark:bg-gray-900 xl:px-8"
                    >
                        <div class="border-t border-gray-200 pt-4 dark:border-gray-800 xl:pt-8">
                            <div class="flex items-center gap-4">
                                <SupportTicketAvatar :name="response.user?.name ?? appName" />
                                <div class="font-semibold dark:text-white">
                                    <div>{{ response.user?.name ?? appName }}</div>
                                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ formatDate(response.created_at) }}
                                        <span class="text-gray-400"> ({{ formatRelative(response.created_at) }})</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="py-8 text-gray-500 dark:text-gray-400"
                                v-html="responseHtml(response.response)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Reply -->
                <div
                    v-if="ticket.is_replyable"
                    class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h2 class="font-semibold text-gray-900 dark:text-white sm:text-xl">Reply</h2>
                    </div>
                    <form class="p-4 text-gray-500 dark:bg-gray-900 dark:text-gray-400" @submit.prevent="submitReply">
                        <textarea
                            v-model="replyForm.response"
                            rows="8"
                            class="input-style mb-4"
                            placeholder="Write your reply..."
                            required
                        />
                        <p v-if="replyForm.errors.response" class="mb-2 text-sm text-red-600">
                            {{ replyForm.errors.response }}
                        </p>
                        <button type="submit" class="btn-primary" :disabled="replyForm.processing">
                            Submit
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-span-full -order-1 lg:order-2 lg:col-span-4">
                <div
                    class="sticky top-[85px] overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="p-4">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Ticket Settings</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                    Date Created
                                </label>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ formatDate(ticket.created_at, { hour: 'numeric', minute: '2-digit' }) }}
                                    ({{ formatRelative(ticket.created_at) }})
                                </p>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Category</label>
                                <span
                                    class="me-2 inline-flex rounded-sm bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    {{ ticket.category_label }}
                                </span>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Status</label>
                                <SupportTicketStatusBadge
                                    :label="ticket.status_label"
                                    :color="ticket.status_color"
                                />
                            </div>
                            <form v-if="ticket.is_solved" @submit.prevent="reopen">
                                <button type="submit" class="btn-primary w-full justify-center" :disabled="reopenForm.processing">
                                    Re-open Ticket
                                </button>
                            </form>
                            <p
                                v-if="ticket.is_closed"
                                class="text-sm italic text-gray-600 dark:text-gray-400"
                            >
                                Closed tickets cannot be reopened. Only tickets marked as resolved can be reopened.
                                If the issue persists, please open a new ticket.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
