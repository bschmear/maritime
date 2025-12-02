<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="grow relative flex items-center justify-center bg-gradient-to-br from-secondary-50 via-white to-purple-50 dark:from-gray-900 dark:via-secondary-950 dark:to-purple-950 py-12 px-4 sm:px-6 lg:px-8">
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
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full mb-4">
                        <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        Confirm Password
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Verify your identity to continue
                    </p>
                </div>

                <!-- Form Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 backdrop-blur-sm bg-white/95 dark:bg-gray-800/95">
                    <!-- Security Notice -->
                    <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                This is a secure area of the application. Please confirm your password before continuing.
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
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
                                autocomplete="current-password"
                                autofocus
                                placeholder="Enter your password"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <PrimaryButton
                                class="w-full justify-center px-6 py-3 bg-primary-500 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="{ 'opacity-50': form.processing }"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Confirming...</span>
                                <span v-else>Confirm Password</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Help Text -->
                <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
                    Your password will not be stored and this confirmation will expire after a period of inactivity.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
