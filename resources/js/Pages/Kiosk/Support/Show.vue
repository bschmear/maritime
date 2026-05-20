<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import SupportTicketStatusBadge from '@/Components/Tenant/SupportTicketStatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    ticket: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
});

const statusForm = useForm({
    status: props.ticket.status,
    agent: props.ticket.agent || '',
    escalated: props.ticket.escalated ?? false,
    priority: props.ticket.priority ?? 2,
});

const replyForm = useForm({
    response: '',
    internal: false,
});
</script>

<template>
    <Head :title="ticket.subject" />
    <KioskLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('kiosk.support-tickets.index')" class="text-sm text-gray-500">← Tickets</Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
                <SupportTicketStatusBadge
                    :label="ticket.status_label"
                    :color="ticket.status_color"
                />
            </div>
        </template>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 p-6 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-gray-500">{{ ticket.ticket_number }} · {{ ticket.user?.email }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ ticket.category_label }}</p>
                    <div class="mt-4 prose max-w-none text-sm dark:prose-invert" v-html="ticket.message" />
                </div>
                <div
                    v-for="r in ticket.responses"
                    :key="r.id"
                    class="rounded-lg border p-4 dark:border-gray-700"
                    :class="r.internal ? 'border-amber-200 bg-amber-50 dark:bg-amber-900/20' : ''"
                >
                    <p class="text-xs text-gray-500">
                        {{ r.user?.name }} · {{ r.internal ? 'Internal' : 'Public' }}
                    </p>
                    <div class="mt-2 prose max-w-none text-sm dark:prose-invert" v-html="r.response" />
                </div>
                <form class="space-y-3" @submit.prevent="replyForm.post(route('kiosk.support-tickets.responses.store', ticket.id))">
                    <textarea v-model="replyForm.response" rows="4" class="input-style" placeholder="Agent reply..." />
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="replyForm.internal" type="checkbox" />
                        Internal note
                    </label>
                    <button type="submit" class="gradient-btn rounded-lg px-4 py-2 text-sm">Add response</button>
                </form>
            </div>
            <form
                class="space-y-4 rounded-xl border border-gray-200 p-6 dark:border-gray-700 dark:bg-gray-900"
                @submit.prevent="statusForm.put(route('kiosk.support-tickets.update', ticket.id))"
            >
                <h2 class="font-semibold text-gray-900 dark:text-white">Ticket settings</h2>
                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-400">Status</label>
                    <select v-model="statusForm.status" class="input-style mt-1">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-400">Agent</label>
                    <input v-model="statusForm.agent" type="text" class="input-style mt-1" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="statusForm.escalated" type="checkbox" />
                    Escalated
                </label>
                <button type="submit" class="gradient-btn w-full rounded-lg px-4 py-2 text-sm">Update</button>
            </form>
        </div>
    </KioskLayout>
</template>
