<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TurnstileWidget from '@/Components/TurnstileWidget.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    invitation: {
        type: Object,
        default: null,
    },
    turnstileSiteKey: {
        type: String,
        default: null,
    },
});

const turnstileEnabled = computed(() => Boolean(props.turnstileSiteKey));

const form = useForm({
    email: props.invitation?.email || '',
    password: '',
    remember: false,
    invitation_token: props.invitation?.token || null,
    turnstile_token: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="relative grow flex items-center justify-center relative bg-gradient-to-br from-primary-100 via-secondary-50 to-primary-50 dark:from-navy-900 dark:via-primary-950 dark:to-navy-800 overflow-hidden py-12 px-4 sm:px-6 lg:px-8">
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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        Welcome back
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Sign in to your account to continue
                    </p>
                </div>

                <!-- Status Message -->
                <div
                    v-if="status"
                    class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm font-medium text-green-600 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <!-- Invitation Message -->
                <div
                    v-if="invitation"
                    class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl"
                >
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                Account Invitation
                            </h3>
                            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                <p>You've been invited to join <strong>{{ invitation.account.name }}</strong> as a <strong>{{ invitation.role }}</strong>.</p>
                                <p class="mt-1">Please sign in with <strong>{{ invitation.email }}</strong> to accept the invitation.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-sm bg-white/95 dark:bg-gray-800/95">
                    <form class="space-y-6" @submit.prevent="submit">
                        <!-- Email Field -->
                        <div>
                            <InputLabel
                                for="email"
                                value="Email"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <!-- Password Field -->
                        <div>
                            <InputLabel
                                for="password"
                                value="Password"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-700 text-secondary-600 focus:ring-secondary-500 dark:focus:ring-secondary-400"
                                />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                                    Remember me
                                </span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-semibold text-secondary-600 dark:text-secondary-400 hover:text-secondary-500 dark:hover:text-secondary-300 transition-colors"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <TurnstileWidget
                            v-if="turnstileEnabled"
                            v-model="form.turnstile_token"
                            :site-key="turnstileSiteKey"
                        />
                        <InputError class="mt-2" :message="form.errors.turnstile_token" />

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full justify-center px-6 py-3 bg-primary-500 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="form.processing || (turnstileEnabled && !form.turnstile_token)"
                            >
                                Sign In
                            </button>
                        </div>

                        <!-- Register Link -->
                        <div class="text-center pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Don't have an account?
                                <Link
                                    :href="route('register')"
                                    class="font-semibold text-secondary-600 dark:text-secondary-400 hover:text-secondary-500 dark:hover:text-secondary-300 transition-colors"
                                >
                                    Create account
                                </Link>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
