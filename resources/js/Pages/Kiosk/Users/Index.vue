<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

const searchUsers = () => {
    router.get(route('kiosk.users.index'), { search: search.value }, { preserveState: true, replace: true });
};

const removeKioskRole = (user, role) => {
    if (!confirm(`Remove "${role.name}" from ${user.name}?`)) {
        return;
    }

    router.delete(route('kiosk.users.destroy', user.id), {
        data: { role_id: role.id },
        preserveScroll: true,
    });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Users" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Users</h1>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Platform users, workspace memberships, and kiosk admin roles.
                </p>
                <Link
                    :href="route('kiosk.users.create')"
                    class="gradient-btn inline-flex items-center gap-x-2 rounded-lg px-4 py-2.5 text-sm"
                >
                    Assign kiosk role
                </Link>
            </div>

            <input
                v-model="search"
                type="search"
                placeholder="Search by name or email..."
                class="max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                @keyup.enter="searchUsers"
            />

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">User</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Accounts</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Kiosk roles</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Joined</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-4 text-sm">
                                    <Link
                                        :href="route('kiosk.users.show', user.id)"
                                        class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        {{ user.name }}
                                    </Link>
                                    <div class="text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div v-if="user.accounts?.length" class="flex flex-wrap gap-2">
                                        <Link
                                            v-for="account in user.accounts"
                                            :key="account.id"
                                            :href="route('kiosk.accounts.show', account.id)"
                                            class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-gray-500/10 hover:bg-gray-200 dark:bg-gray-800 dark:text-primary-300 dark:hover:bg-gray-700"
                                        >
                                            {{ account.name }}
                                            <span class="ml-1 text-gray-500 dark:text-gray-400">({{ account.role_label }})</span>
                                        </Link>
                                    </div>
                                    <span v-else class="text-gray-400 dark:text-gray-500">No accounts</span>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div v-if="user.kiosk_roles?.length" class="flex flex-wrap gap-2">
                                        <span
                                            v-for="role in user.kiosk_roles"
                                            :key="role.id"
                                            class="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300"
                                        >
                                            {{ role.name }}
                                            <button
                                                type="button"
                                                class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-200"
                                                title="Remove role"
                                                @click="removeKioskRole(user, role)"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="px-4 py-4 text-right text-sm">
                                    <Link :href="route('kiosk.users.show', user.id)" class="text-primary-600 dark:text-primary-400">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!users.data?.length">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No users found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="users.data?.length && (users.prev_page_url || users.next_page_url)"
                    class="border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50 sm:px-6"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <span class="font-medium">{{ users.from }}</span> to
                            <span class="font-medium">{{ users.to }}</span> of
                            <span class="font-medium">{{ users.total }}</span>
                        </p>
                        <div class="flex gap-2">
                            <Link
                                v-if="users.prev_page_url"
                                :href="users.prev_page_url"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="users.next_page_url"
                                :href="users.next_page_url"
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
