<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="grow relative flex items-center justify-center relative bg-gradient-to-br from-primary-100 via-secondary-50 to-primary-50 dark:from-navy-900 dark:via-primary-950 dark:to-navy-800 overflow-hidden py-12 px-4 sm:px-6 lg:px-8">
            <!-- Background decoration -->
            <!-- Decorative Elements -->
            <div class="absolute inset-0 opacity-30 dark:opacity-20">
                <div class="absolute top-20 right-20 w-96 h-96 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-full blur-3xl animate-pulse"></div>
                <div class="absolute bottom-20 left-20 w-80 h-80 bg-gradient-to-tr from-secondary-500 to-primary-300 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-72 h-72 bg-gradient-to-r from-primary-300 to-secondary-300 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
            </div>

            <!-- Animated waves -->
            <div class="absolute bottom-0 left-0 right-0 opacity-20 dark:opacity-10">
                <svg viewBox="0 0 1440 320" class="w-full">
                    <path fill="currentColor" class="text-secondary-400 dark:text-secondary-700" fill-opacity="0.5" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,144C960,149,1056,139,1152,122.7C1248,107,1344,85,1392,74.7L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                </svg>
            </div>

            <div class="relative max-w-md w-full">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full mb-4">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        Verify your email
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        We've sent you a verification link
                    </p>
                </div>

                <!-- Success Message -->
                <div
                    v-if="verificationLinkSent"
                    class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl"
                >
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm font-medium text-green-600 dark:text-green-400">
                            A new verification link has been sent to your email address!
                        </p>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-sm bg-white/95 dark:bg-gray-800/95">
                    <!-- Info Box -->
                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl">
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Thanks for signing up! Before getting started, please verify your email address by clicking on the link we just emailed to you. If you didn't receive the email, we'll gladly send you another.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Resend Button -->
                        <div>
                            <PrimaryButton
                                class="w-full justify-center px-6 py-3 bg-primary-500 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="{ 'opacity-50': form.processing }"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Sending...</span>
                                <span v-else>Resend Verification Email</span>
                            </PrimaryButton>
                        </div>

                        <!-- Logout Link -->
                        <div class="text-center pt-4 border-t border-gray-200 dark:border-gray-700">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>Log Out</span>
                            </Link>
                        </div>
                    </form>
                </div>

                <!-- Help Text -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Check your spam folder if you don't see the email in your inbox
                    </p>
                    <div class="flex items-center justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>This verification link will expire in 60 minutes</span>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
