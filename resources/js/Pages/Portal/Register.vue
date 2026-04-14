<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: null },
    logoUrl: { type: String, default: null },
    companyName: { type: String, default: 'Customer Portal' },
});

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('portal.register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Customer Portal - Create Account" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-gray-50 to-secondary-50 px-4 py-12">
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 right-20 w-96 h-96 bg-primary-200/30 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 left-20 w-80 h-80 bg-secondary-200/30 rounded-full blur-3xl"></div>
        </div>

        <div class="relative max-w-md w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto mb-4 flex h-16 max-w-[220px] items-center justify-center">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="companyName"
                        class="max-h-16 w-auto max-w-full object-contain rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-100"
                    />
                    <div
                        v-else
                        class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-secondary-400 to-secondary-700 shadow-md"
                    >
                        <span class="text-xl font-semibold leading-none text-white">
                            {{ companyName?.charAt(0) ?? 'C' }}
                        </span>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">Create Portal Account</h1>
                <p class="text-gray-500 text-sm">Use the email address your provider has on file for you</p>
            </div>

            <!-- Form Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@example.com"
                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm"
                        />
                    </div>

                    <!-- Info box -->
                    <div class="p-3 bg-primary-50 border border-primary-100 rounded-lg">
                        <p class="text-xs text-primary-700 leading-relaxed">
                            <span class="font-semibold">Note:</span> Your email must match an existing customer record. If you don't have access, please contact your service provider.
                        </p>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    >
                        <span v-if="form.processing">Creating account...</span>
                        <span v-else>Create Account</span>
                    </button>

                    <!-- Login link -->
                    <div class="text-center pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-500">
                            Already have an account?
                            <Link
                                :href="route('portal.login')"
                                class="font-semibold text-primary-600 hover:text-primary-700 transition-colors"
                            >
                                Sign in
                            </Link>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
