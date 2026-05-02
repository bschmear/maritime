<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({});

const resend = () => {
    form.post(route('vendor.portal.verification.send'));
};
</script>

<template>
    <Head title="Verify email - Vendor Portal" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-gray-50 to-secondary-50 px-4 py-12">
        <div class="relative max-w-md w-full">
            <div class="text-center mb-8">
                <div class="mx-auto w-14 h-14 bg-primary-600 rounded-xl flex items-center justify-center mb-4">
                    <span class="material-icons text-white text-2xl">mark_email_unread</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">Verify your email</h1>
                <p class="text-gray-500 text-sm">
                    Before you can access warranty claims, please click the verification link we emailed you.
                </p>
            </div>

            <div
                v-if="status"
                class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm font-medium text-green-700"
            >
                {{ status }}
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">
                    If you did not receive the email, check your spam folder or request another link below.
                </p>

                <button
                    type="button"
                    :disabled="form.processing"
                    @click="resend"
                    class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                >
                    <span v-if="form.processing">Sending...</span>
                    <span v-else>Resend verification email</span>
                </button>

                <div class="text-center pt-4 border-t border-gray-100">
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
