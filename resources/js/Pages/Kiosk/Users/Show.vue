<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    user: Object,
    kiosk_roles: Array,
    accounts: Array,
    all_roles: {
        type: Array,
        default: () => [],
    },
    available_roles: {
        type: Array,
        default: () => [],
    },
});

const accessForm = useForm({
    admin_access: !!props.user?.admin_access,
    is_support: !!props.user?.is_support,
    role_id: '',
});

const roleForm = useForm({
    role_id: '',
});

const hasKioskAccess = computed(() => !!props.user?.admin_access);

const roleOptionsForSetup = computed(() => {
    if ((props.kiosk_roles?.length ?? 0) > 0) {
        return props.available_roles ?? [];
    }

    return props.all_roles ?? [];
});

const syncAccessFormFromUser = () => {
    accessForm.admin_access = !!props.user?.admin_access;
    accessForm.is_support = !!props.user?.is_support;
    if (!accessForm.admin_access) {
        accessForm.role_id = '';
    }
};

watch(() => props.user, syncAccessFormFromUser, { deep: true });

const saveKioskAccess = () => {
    if (!accessForm.admin_access) {
        accessForm.is_support = false;
        accessForm.role_id = '';
    }

    accessForm.patch(route('kiosk.users.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            accessForm.role_id = '';
        },
    });
};

const assignRole = () => {
    roleForm.post(route('kiosk.users.roles.store', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            roleForm.reset('role_id');
        },
    });
};

const removeKioskRole = (role) => {
    if (!confirm(`Remove "${role.name}" from ${props.user.name}?`)) {
        return;
    }

    router.delete(route('kiosk.users.destroy', props.user.id), {
        data: { role_id: role.id },
        preserveScroll: true,
    });
};

const removeFromKiosk = () => {
    if (!confirm(`Remove ${props.user.name} from kiosk entirely?`)) {
        return;
    }

    router.delete(route('kiosk.users.kiosk-access.destroy', props.user.id));
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
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Kiosk access</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Enable admin access, assign a kiosk role, and optionally mark as support staff.
                        </p>
                    </div>
                    <Link
                        :href="route('kiosk.users.create')"
                        class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Assign another user
                    </Link>
                </div>

                <form class="mt-6 space-y-6" @submit.prevent="saveKioskAccess">
                    <div class="rounded-lg border border-primary-200 bg-primary-50/90 p-4 dark:border-primary-800/60 dark:bg-primary-950/25">
                        <label class="flex cursor-pointer items-start gap-3 select-none">
                            <input
                                v-model="accessForm.admin_access"
                                type="checkbox"
                                class="mt-0.5 rounded border-primary-300 text-primary-600 focus:ring-primary-500 dark:border-primary-600 dark:bg-gray-800"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-primary-950 dark:text-primary-100">Admin access</span>
                                <span class="mt-1 block text-sm text-primary-900/90 dark:text-primary-200/90">
                                    Allows this user to sign in to the kiosk and manage platform content.
                                </span>
                            </span>
                        </label>
                        <InputError class="mt-2" :message="accessForm.errors.admin_access" />
                    </div>

                    <template v-if="accessForm.admin_access">
                        <div v-if="kiosk_roles?.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="role in kiosk_roles"
                                :key="role.id"
                                class="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300"
                            >
                                {{ role.name }}
                                <button
                                    type="button"
                                    class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-200"
                                    title="Remove role"
                                    @click="removeKioskRole(role)"
                                >
                                    ×
                                </button>
                            </span>
                        </div>

                        <div v-if="!kiosk_roles?.length || roleOptionsForSetup.length">
                            <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ kiosk_roles?.length ? 'Add kiosk role' : 'Kiosk role' }}
                            </label>
                            <select
                                id="role_id"
                                v-model="accessForm.role_id"
                                :required="!kiosk_roles?.length"
                                class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="" disabled>
                                    {{ kiosk_roles?.length ? 'Select another role' : 'Select a role' }}
                                </option>
                                <option v-for="role in roleOptionsForSetup" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="accessForm.errors.role_id" />
                        </div>

                        <div class="rounded-lg border border-sky-200 bg-sky-50/90 p-4 dark:border-sky-800/60 dark:bg-sky-950/25">
                            <label class="flex cursor-pointer items-start gap-3 select-none">
                                <input
                                    v-model="accessForm.is_support"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-sky-300 text-sky-600 focus:ring-sky-500 dark:border-sky-600 dark:bg-gray-800"
                                />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-sky-950 dark:text-sky-100">Is support</span>
                                    <span class="mt-1 block text-sm text-sky-900/90 dark:text-sky-200/90">
                                        Can sign in to customer workspaces when the account has enabled support access.
                                    </span>
                                </span>
                            </label>
                            <InputError class="mt-2" :message="accessForm.errors.is_support" />
                        </div>
                    </template>

                    <div class="flex items-center gap-3">
                        <PrimaryButton :disabled="accessForm.processing">
                            Save kiosk access
                        </PrimaryButton>
                        <p v-if="accessForm.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">
                            Saved.
                        </p>
                    </div>
                </form>

                <form
                    v-if="user.admin_access && available_roles?.length && kiosk_roles?.length"
                    class="mt-6 flex flex-col gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:items-end dark:border-gray-700"
                    @submit.prevent="assignRole"
                >
                    <div class="min-w-0 flex-1">
                        <label for="add_role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add another role</label>
                        <select
                            id="add_role_id"
                            v-model="roleForm.role_id"
                            required
                            class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="" disabled>Select a role</option>
                            <option v-for="role in available_roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="roleForm.errors.role_id" />
                    </div>
                    <PrimaryButton :disabled="roleForm.processing">Add role</PrimaryButton>
                </form>

                <div v-if="hasKioskAccess" class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button
                        type="button"
                        class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        @click="removeFromKiosk"
                    >
                        Remove from kiosk
                    </button>
                </div>
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
