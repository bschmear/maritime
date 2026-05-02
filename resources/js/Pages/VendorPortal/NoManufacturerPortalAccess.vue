<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="No manufacturer access - Vendor Portal" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-gray-50 to-secondary-50 px-4 py-12">
        <div class="relative max-w-md w-full">
            <div class="text-center mb-8">
                <div class="mx-auto w-14 h-14 bg-amber-500 rounded-xl flex items-center justify-center mb-4">
                    <span class="material-icons text-white text-2xl">lock</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">No manufacturer portal access</h1>
                <p class="text-gray-500 text-sm leading-relaxed">
                    Your account is signed in, but you are not enabled for any manufacturer in this portal yet. Ask your
                    service provider to turn on manufacturer portal access for your contact on the relevant vendor
                    record.
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8 space-y-4">
                <div class="text-center pt-2">
                    <Link
                        :href="route('vendor.portal.logout')"
                        method="post"
                        as="button"
                        class="text-sm font-semibold text-primary-600 hover:text-primary-700"
                    >
                        Sign out
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
