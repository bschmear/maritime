<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

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
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        Create your account
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Start your journey with AI-powered social media management
                    </p>
                </div>

                <!-- Form Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-sm bg-white/95 dark:bg-gray-800/95">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- First Name Field -->
                        <div>
                            <InputLabel
                                for="first_name"
                                value="First Name"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <TextInput
                                id="first_name"
                                type="text"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                v-model="form.first_name"
                                required
                                autofocus
                                autocomplete="given-name"
                                placeholder="John"
                            />
                            <InputError class="mt-2" :message="form.errors.first_name" />
                        </div>

                        <!-- Last Name Field -->
                        <div>
                            <InputLabel
                                for="last_name"
                                value="Last Name"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <TextInput
                                id="last_name"
                                type="text"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                v-model="form.last_name"
                                required
                                autocomplete="family-name"
                                placeholder="Doe"
                            />
                            <InputError class="mt-2" :message="form.errors.last_name" />
                        </div>

                        <!-- Email Field -->
                        <div>
                            <InputLabel
                                for="email"
                                value="Email"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                v-model="form.email"
                                required
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
                            <TextInput
                                id="password"
                                type="password"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                v-model="form.password"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- Confirm Password Field -->
                        <div>
                            <InputLabel
                                for="password_confirmation"
                                value="Confirm Password"
                                class="text-gray-700 dark:text-gray-300 font-medium"
                            />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                class="mt-2 block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-secondary-500 dark:focus:ring-secondary-400 focus:border-transparent transition-all"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.password_confirmation"
                            />
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <PrimaryButton
                                class="w-full justify-center px-6 py-3 bg-primary-500 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="{ 'opacity-50': form.processing }"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Creating account...</span>
                                <span v-else>Create Account</span>
                            </PrimaryButton>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Already have an account?
                                <Link
                                    :href="route('login')"
                                    class="font-semibold text-secondary-600 dark:text-secondary-400 hover:text-secondary-500 dark:hover:text-secondary-300 transition-colors"
                                >
                                    Sign in
                                </Link>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Terms Text -->
                <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
                    By creating an account, you agree to our
                    <a href="#" class="text-secondary-600 dark:text-secondary-400 hover:underline">Terms of Service</a>
                    and
                    <a href="#" class="text-secondary-600 dark:text-secondary-400 hover:underline">Privacy Policy</a>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
