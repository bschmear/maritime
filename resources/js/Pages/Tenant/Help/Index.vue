<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    helpPortalUrl: String,
    hasTicketSupport: Boolean,
    upgradeUrl: String,
});
</script>

<template>
    <Head title="Help" />

    <TenantLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Help & Support</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Find answers in our documentation or contact support.</p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <a
                    :href="helpPortalUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900 transition"
                >
                    <span class="material-icons text-primary-600 text-3xl">menu_book</span>
                    <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Documentation</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Browse guides and how-tos in our documentation portal.
                    </p>
                </a>

                <Link
                    v-if="hasTicketSupport"
                    :href="route('dashSupport')"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900 transition"
                >
                    <span class="material-icons text-primary-600 text-3xl">support_agent</span>
                    <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Support Tickets</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        View and manage your support requests.
                    </p>
                </Link>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 dark:border-gray-600 dark:bg-gray-800/50"
                >
                    <span class="material-icons text-gray-400 text-3xl">lock</span>
                    <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Support Tickets</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Support tickets are available on select plans.
                    </p>
                    <a
                        :href="upgradeUrl"
                        class="mt-4 inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-500"
                    >
                        Upgrade your plan →
                    </a>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
