<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    accounts: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const searchAccounts = () => {
    router.get(route('kiosk.accounts.index'), { search: search.value }, { preserveState: true, replace: true });
};

const statusLabel = (account) => {
    if (account.has_active_subscription) {
        return 'Active';
    }
    if (account.subscription_status === 'trialing') {
        return 'Trial';
    }
    if (account.subscription_status === 'canceled' || account.subscription_status === 'cancelled') {
        return 'Cancelled';
    }
    if (account.subscription_status && account.subscription_status !== 'none') {
        return account.subscription_status;
    }
    return 'No subscription';
};

const statusClasses = (account) => {
    if (account.has_active_subscription) {
        return 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300';
    }
    if (account.subscription_status === 'trialing') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Accounts" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Accounts</h1>
        </template>

        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Customer workspaces, subscription status, and team size.
            </p>

            <input
                v-model="search"
                type="search"
                placeholder="Search by account, owner, or domain..."
                class="max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                @keyup.enter="searchAccounts"
            />

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Account</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Owner</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Domain</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Plan</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Users</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="account in accounts.data" :key="account.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                    <Link :href="route('kiosk.accounts.show', account.id)" class="text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                        {{ account.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <template v-if="account.owner">
                                        {{ account.owner.name }}
                                        <div class="text-xs">{{ account.owner.email }}</div>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ account.domain || '—' }}
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClasses(account)">
                                        {{ statusLabel(account) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ account.plan_name || '—' }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ account.users_count }}
                                </td>
                                <td class="px-4 py-4 text-right text-sm">
                                    <Link :href="route('kiosk.accounts.show', account.id)" class="text-primary-600 dark:text-primary-400">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!accounts.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No accounts found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="accounts.data?.length && (accounts.prev_page_url || accounts.next_page_url)"
                    class="border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50 sm:px-6"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <span class="font-medium">{{ accounts.from }}</span> to
                            <span class="font-medium">{{ accounts.to }}</span> of
                            <span class="font-medium">{{ accounts.total }}</span>
                        </p>
                        <div class="flex gap-2">
                            <Link
                                v-if="accounts.prev_page_url"
                                :href="accounts.prev_page_url"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="accounts.next_page_url"
                                :href="accounts.next_page_url"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </KioskLayout>
</template>
