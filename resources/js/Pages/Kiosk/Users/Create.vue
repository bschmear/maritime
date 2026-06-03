<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    roles: Array,
    users: Array,
});

const form = useForm({
    user_id: '',
    role_id: '',
});

const submit = () => {
    form.post(route('kiosk.users.store'));
};
</script>

<template>
    <Head title="Assign Kiosk Role" />

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
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Assign kiosk role</h1>
            </div>
        </template>

        <form class="mx-auto max-w-xl space-y-6" @submit.prevent="submit">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="space-y-6">
                    <div>
                        <InputLabel for="user_id" value="User" />
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            required
                            class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="" disabled>Select a user</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.email }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <div>
                        <InputLabel for="role_id" value="Kiosk role" />
                        <select
                            id="role_id"
                            v-model="form.role_id"
                            required
                            class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="" disabled>Select a role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role_id" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link
                        :href="route('kiosk.users.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300"
                    >
                        Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">Assign role</PrimaryButton>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>
