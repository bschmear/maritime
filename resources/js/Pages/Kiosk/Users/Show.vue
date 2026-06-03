<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    user: Object,
    kiosk_roles: Array,
    accounts: Array,
});

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatDateOnly = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head :title="user.name" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.users.index')"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ user.name }}</h1>
            </div>
        </template>

        <div class="space-y-8">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Profile</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email verified</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ user.email_verified_at ? formatDate(user.email_verified_at) : 'Not verified' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">User ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.id }}</dd>
                    </div>
                    <div v-if="user.first_name || user.last_name">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">First name</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.first_name || '—' }}</dd>
                    </div>
                    <div v-if="user.first_name || user.last_name">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last name</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ user.last_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Current workspace</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ user.current_tenant_domain || (user.current_tenant_id ? `Tenant #${user.current_tenant_id}` : '—') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stripe customer</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ user.has_stripe_customer ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                    <div v-if="user.trial_ends_at">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Trial ends</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(user.trial_ends_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Registered</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateOnly(user.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last updated</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateOnly(user.updated_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Kiosk roles</h2>
                <div v-if="kiosk_roles?.length" class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="role in kiosk_roles"
                        :key="role.id"
                        class="inline-flex rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300"
                    >
                        {{ role.name }}
                    </span>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">No kiosk admin roles assigned.</p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Accounts</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Workspaces this user belongs to.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Account</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Role</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="account in accounts" :key="account.id">
                                <td class="px-4 py-3 text-sm">
                                    <Link
                                        :href="route('kiosk.accounts.show', account.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        {{ account.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ account.role_label }}
                                    <span v-if="account.is_owner" class="text-primary-600 dark:text-primary-400">(owner)</span>
                                </td>
                            </tr>
                            <tr v-if="!accounts?.length">
                                <td colspan="2" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No accounts.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </KioskLayout>
</template>
