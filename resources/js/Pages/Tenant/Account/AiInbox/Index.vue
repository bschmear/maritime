<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

const props = defineProps({
    routes: { type: Array, default: () => [] },
    actionOptions: { type: Array, default: () => [] },
    inboundDomain: { type: String, default: 'inbound.helmful.com' },
    account: { type: Object, default: null },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const createForm = useForm({
    action: 'create_lead',
});

const createRoute = () => {
    createForm.post(route('account.ai-inbox.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const toggleRoute = (routeId) => {
    router.patch(route('account.ai-inbox.update', routeId), {}, { preserveScroll: true });
};

const deleteRoute = (routeId) => {
    if (!window.confirm('Delete this inbound email address? Future emails sent here will be ignored.')) {
        return;
    }
    router.delete(route('account.ai-inbox.destroy', routeId), { preserveScroll: true });
};

async function copyAddress(address) {
    try {
        await navigator.clipboard.writeText(address);
        showToast('success', 'Address copied to clipboard.');
    } catch {
        showToast('error', 'Could not copy address.');
    }
}
</script>

<template>
    <Head title="AI Inbox" />

    <TenantLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('account.index')"
                        class="mb-2 inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-lg leading-none">arrow_back</span>
                        Account
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">AI Inbox</h2>
                    <p class="mt-1 text-md text-gray-500 dark:text-gray-400">
                        Forward lead emails to a unique address and Helmful will extract contact details and create a lead automatically.
                    </p>
                </div>
            </div>
        </template>

        <div v-if="flashSuccess" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
            {{ flashSuccess }}
        </div>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-6 flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/50">
                        <span class="material-icons text-[22px] leading-none text-primary-600 dark:text-primary-400">mail</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Create inbound address</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Addresses use <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">lead-&#123;number&#125;@{{ inboundDomain }}</code>.
                            Forward dealer leads, web form notifications, or inquiry emails to the address.
                        </p>
                    </div>
                </div>

                <form class="flex flex-wrap items-end gap-4" @submit.prevent="createRoute">
                    <div class="min-w-[200px] flex-1">
                        <label for="action" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Action</label>
                        <select
                            id="action"
                            v-model="createForm.action"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                        >
                            <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="createForm.errors.action" class="mt-1 text-sm text-red-600">{{ createForm.errors.action }}</p>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="createForm.processing"
                    >
                        <span class="material-icons text-lg leading-none">add</span>
                        Create address
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Inbound addresses</h3>
                </div>

                <div v-if="routes.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    No inbound addresses yet. Create one to start receiving leads by email.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email address</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in routes" :key="row.id">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm text-gray-900 dark:text-white">{{ row.address }}</span>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400"
                                            title="Copy address"
                                            @click="copyAddress(row.address)"
                                        >
                                            <span class="material-icons text-lg leading-none">content_copy</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ row.action_label }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="row.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ row.is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                            @click="toggleRoute(row.id)"
                                        >
                                            {{ row.is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/40"
                                            @click="deleteRoute(row.id)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
