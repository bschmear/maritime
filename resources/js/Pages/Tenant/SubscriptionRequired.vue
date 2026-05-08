<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    workspace_name: {
        type: String,
        default: '',
    },
    central_dashboard_url: {
        type: String,
        required: true,
    },
    account_management_url: {
        type: String,
        required: true,
    },
    reactivate_pricing_url: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col items-center justify-center px-4 py-12">
        <Head title="Subscription required" />

        <div class="max-w-lg w-full bg-white dark:bg-gray-800 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40 mb-5">
                <svg class="h-8 w-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                No active subscription
            </h1>
            <p v-if="workspace_name" class="mt-2 text-md text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ workspace_name }}</span>
                does not have an active billing plan.
            </p>
            <p class="mt-3 text-md text-gray-600 dark:text-gray-300 leading-relaxed">
                Please update your subscription from your account page. After billing is active, you can return to this workspace.
            </p>

            <div class="mt-8 flex flex-col gap-3">
                <a
                    :href="reactivate_pricing_url"
                    class="inline-flex justify-center items-center rounded-lg bg-secondary-600 px-5 py-2.5 text-md font-medium text-white hover:bg-secondary-700 focus:ring-4 focus:ring-secondary-300 dark:bg-secondary-600 dark:hover:bg-secondary-700"
                >
                    Choose plan for this workspace
                </a>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a
                    :href="account_management_url"
                    class="inline-flex justify-center items-center rounded-lg bg-primary-600 px-5 py-2.5 text-md font-medium text-white hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                >
                    Open account and billing
                </a>
                <a
                    :href="central_dashboard_url"
                    class="inline-flex justify-center items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Main dashboard
                </a>
                </div>
            </div>

            <p class="mt-6 text-md text-gray-500 dark:text-gray-400">
                You can still use
                <Link :href="route('profile.edit')" class="text-primary-600 dark:text-primary-400 hover:underline">profile</Link>
                or
                <Link :href="route('logout')" method="post" as="button" class="text-primary-600 dark:text-primary-400 hover:underline">log out</Link>
                from this workspace.
            </p>
        </div>
    </div>
</template>
