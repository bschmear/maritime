<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TurnstileWidget from '@/Components/TurnstileWidget.vue';
import { firstValidationError, validationError } from '@/Utils/validationError';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    googleLoginEnabled: {
        type: Boolean,
        default: false,
    },
});

const turnstileEnabled = computed(() => Boolean(props.turnstileSiteKey));
const turnstileRef = ref(null);
const page = usePage();

const form = useForm({
    email: props.invitation?.email || '',
    password: '',
    remember: false,
    invitation_token: props.invitation?.token || null,
    turnstile_token: '',
});

const emailError = computed(() =>
    validationError(form.errors, 'email') || validationError(page.props.errors, 'email'),
);
const passwordError = computed(() =>
    validationError(form.errors, 'password') || validationError(page.props.errors, 'password'),
);
const turnstileError = computed(() =>
    validationError(form.errors, 'turnstile_token') || validationError(page.props.errors, 'turnstile_token'),
);

const loginAlertMessage = computed(() =>
    firstValidationError(form.errors, ['email', 'password', 'turnstile_token'])
    || firstValidationError(page.props.errors, ['email', 'password', 'turnstile_token']),
);

const inputErrorClass = 'border-red-500 focus:ring-red-500 dark:border-red-500 dark:focus:ring-red-500';

function resetTurnstile() {
    if (!turnstileEnabled.value) {
        return;
    }
    form.turnstile_token = '';
    turnstileRef.value?.reset();
}

const submit = () => {
    form.post(route('login'), {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
        onFinish: () => {
            if (Object.keys(form.errors).length > 0) {
                resetTurnstile();
            }
        },
    });
};

const googleLoginUrl = computed(() => {
    const params = {};
    if (props.invitation?.token) {
        params.invitation = props.invitation.token;
    }

    return route('google.login.redirect', params);
});
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
                    <div v-if="googleLoginEnabled" class="space-y-6">
                        <a
                            :href="googleLoginUrl"
                            class="flex w-full items-center justify-center gap-3 rounded-xl border border-gray-300 bg-white px-6 py-3 font-semibold text-gray-700 shadow-sm transition-all hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            </svg>
                            Continue with Google
                        </a>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200 dark:border-gray-700" />
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="bg-white px-3 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    Or continue with email
                                </span>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-6" :class="{ 'mt-6': googleLoginEnabled }" @submit.prevent="submit">
                        <div
                            v-if="loginAlertMessage"
                            role="alert"
                            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
                        >
                            {{ loginAlertMessage }}
                        </div>

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
                                :class="[
                                    'mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:border-transparent transition-all',
                                    emailError
                                        ? inputErrorClass
                                        : 'border-gray-300 dark:border-gray-700 focus:ring-secondary-500 dark:focus:ring-secondary-400',
                                ]"
                                :aria-invalid="emailError ? 'true' : 'false'"
                                :aria-describedby="emailError ? 'login-email-error' : undefined"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                            />
                            <InputError id="login-email-error" class="mt-2" :message="emailError" />
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
                                :class="[
                                    'mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:border-transparent transition-all',
                                    passwordError
                                        ? inputErrorClass
                                        : 'border-gray-300 dark:border-gray-700 focus:ring-secondary-500 dark:focus:ring-secondary-400',
                                ]"
                                :aria-invalid="passwordError ? 'true' : 'false'"
                                :aria-describedby="passwordError ? 'login-password-error' : undefined"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                            <InputError id="login-password-error" class="mt-2" :message="passwordError" />
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
                            ref="turnstileRef"
                            v-model="form.turnstile_token"
                            :site-key="turnstileSiteKey"
                        />
                        <InputError class="mt-2" :message="turnstileError" />

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
