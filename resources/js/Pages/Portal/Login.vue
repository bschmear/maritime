<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('portal.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Customer Portal - Sign In" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-gray-50 to-secondary-50 px-4 py-12">
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 right-20 w-96 h-96 bg-primary-200/30 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 left-20 w-80 h-80 bg-secondary-200/30 rounded-full blur-3xl"></div>
        </div>

        <div class="relative max-w-md w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto w-14 h-14 bg-primary-600 rounded-xl flex items-center justify-center mb-4">
                    <span class="material-icons text-white text-2xl">storefront</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">Customer Portal</h1>
                <p class="text-gray-500 text-sm">Sign in to view your estimates, invoices, and more</p>
            </div>

            <!-- Status -->
            <div
                v-if="status"
                class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm font-medium text-green-700"
            >
                {{ status }}
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
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center">
                        <input
                            id="remember"
                            type="checkbox"
                            v-model="form.remember"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    >
                        <span v-if="form.processing">Signing in...</span>
                        <span v-else>Sign In</span>
                    </button>

                    <!-- Register link -->
                    <div class="text-center pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-500">
                            First time here?
                            <Link
                                :href="route('portal.register')"
                                class="font-semibold text-primary-600 hover:text-primary-700 transition-colors"
                            >
                                Create your portal account
                            </Link>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
