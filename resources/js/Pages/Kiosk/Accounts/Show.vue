<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    account: Object,
    subscription: Object,
    seat_usage: Object,
    users: Array,
    payment_history: Array,
    subscription_history: Array,
    can_support_login: {
        type: Boolean,
        default: false,
    },
    tenant_dashboard_url: {
        type: String,
        default: null,
    },
});

const openWorkspace = () => {
    router.post(route('kiosk.accounts.support-access', props.account.id));
};

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

const subscriptionStatusLabel = (subscription) => {
    if (subscription.has_active_subscription) {
        return 'Active';
    }
    if (subscription.on_trial) {
        return 'Trial';
    }
    if (subscription.cancelled) {
        return 'Cancelled';
    }
    if (subscription.stripe_status) {
        return subscription.stripe_status;
    }
    return 'No subscription';
};

const subscriptionStatusClass = (subscription) => {
    if (subscription.has_active_subscription) {
        return 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300';
    }
    if (subscription.on_trial) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
};
</script>

<template>
    <Head :title="account.name" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.accounts.index')"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ account.name }}</h1>
            </div>
        </template>

        <div class="space-y-8">
            <!-- Overview -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Overview</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Owner</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            <template v-if="account.owner">
                                <Link
                                    :href="route('kiosk.users.show', account.owner.id)"
                                    class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    {{ account.owner.name }}
                                </Link>
                                <div class="text-gray-500 dark:text-gray-400">{{ account.owner.email }}</div>
                            </template>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Workspace domain</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ account.domain || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateOnly(account.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Support access</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="account.allow_support_access
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'"
                            >
                                {{ account.allow_support_access ? 'Enabled by customer' : 'Not enabled' }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="account.allow_support_access && can_support_login"
                    class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50/90 p-4 dark:border-emerald-800/60 dark:bg-emerald-950/25"
                >
                    <p class="text-sm text-emerald-950 dark:text-emerald-100">
                        This account has granted support access. You can open their workspace to help configure, set up, or debug.
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                        @click="openWorkspace"
                    >
                        <span class="material-icons text-base leading-none">open_in_new</span>
                        Open workspace
                    </button>
                    <p v-if="tenant_dashboard_url" class="mt-2 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                        {{ tenant_dashboard_url }}
                    </p>
                </div>
                <p
                    v-else-if="account.allow_support_access && !can_support_login"
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Support access is enabled, but your kiosk user is not marked as support.
                </p>
            </section>

            <!-- Subscription status -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Account status</h2>
                    <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="subscriptionStatusClass(subscription)"
                    >
                        {{ subscriptionStatusLabel(subscription) }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Plan</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ subscription.plan?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Billing cycle</dt>
                        <dd class="mt-1 text-sm capitalize text-gray-900 dark:text-white">{{ subscription.billing_cycle || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stripe status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ subscription.stripe_status || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Seats</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ seat_usage.current_users }} / {{ seat_usage.seat_limit }}
                            <span v-if="seat_usage.over_limit" class="text-amber-600 dark:text-amber-400">
                                (+{{ seat_usage.over_limit }} over)
                            </span>
                        </dd>
                    </div>
                    <div v-if="subscription.trial_ends_at">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Trial ends</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(subscription.trial_ends_at) }}</dd>
                    </div>
                    <div v-if="subscription.ends_at">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Access ends</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(subscription.ends_at) }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Payment history -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Payment history</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stripe invoices for this account's subscription.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Invoice</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Amount</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="invoice in payment_history" :key="invoice.id">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDateOnly(invoice.date) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ invoice.number || invoice.id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ invoice.total }}</td>
                                <td class="px-4 py-3 text-sm capitalize text-gray-500 dark:text-gray-400">{{ invoice.status }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a
                                        v-if="invoice.pdf_url"
                                        :href="invoice.pdf_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary-600 dark:text-primary-400"
                                    >
                                        PDF
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!payment_history?.length">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No Stripe invoices found for this account.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Subscription history (local records) -->
            <section
                v-if="subscription_history?.length"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Subscription records</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Created</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Plan</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Cycle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in subscription_history" :key="row.id">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDateOnly(row.created_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ row.plan_name || '—' }}</td>
                                <td class="px-4 py-3 text-sm capitalize text-gray-500">{{ row.stripe_status }}</td>
                                <td class="px-4 py-3 text-sm capitalize text-gray-500">{{ row.billing_cycle }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Users -->
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Users</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Members of this workspace account.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Role</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-4 py-3 text-sm font-medium">
                                    <Link
                                        :href="route('kiosk.users.show', user.id)"
                                        class="text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        {{ user.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ user.role_label }}
                                    <span v-if="user.is_owner" class="ml-1 text-xs text-primary-600 dark:text-primary-400">(owner)</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDateOnly(user.joined_at) }}</td>
                            </tr>
                            <tr v-if="!users?.length">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No users on this account.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </KioskLayout>
</template>
