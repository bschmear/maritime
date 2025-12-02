<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    monthly_price: '',
    yearly_price: '',
    stripe_monthly_id: '',
    stripe_yearly_id: '',
    seat_limit: 1,
    description: '',
    included: [],
    popular: false,
    active: true,
});

const newIncludedItem = ref('');

const addIncludedItem = () => {
    if (newIncludedItem.value.trim()) {
        form.included.push(newIncludedItem.value.trim());
        newIncludedItem.value = '';
    }
};

const removeIncludedItem = (index) => {
    form.included.splice(index, 1);
};

const submit = () => {
    form.post(route('kiosk.plans.store'));
};
</script>

<template>
    <Head title="Create Plan" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.plans.index')"
                    class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Create Plan</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="grid grid-cols-1 gap-x-8 gap-y-8 md:grid-cols-3">
                <!-- Info Section -->
                <div class="px-4 sm:px-0">
                    <h2 class="text-base font-semibold leading-7 text-gray-900 dark:text-white">Plan Information</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Create a new subscription plan for your customers.
                    </p>
                </div>

                <!-- Form Section -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl md:col-span-2">
                    <div class="px-4 py-6 sm:p-8">
                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8">
                            <!-- Name Field -->
                            <div>
                                <InputLabel for="name" value="Plan Name" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    placeholder="e.g., Basic, Pro, Agency"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Pricing Section -->
                            <div class="space-y-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Pricing Options</h3>

                                <!-- Monthly Price and Stripe ID Row -->
                                <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                                    <!-- Monthly Price -->
                                    <div>
                                        <InputLabel for="monthly_price" value="Monthly Price (Optional)" class="text-gray-900 dark:text-white" />
                                        <div class="relative mt-2">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                                            </div>
                                            <TextInput
                                                id="monthly_price"
                                                v-model="form.monthly_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="mt-0 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 pl-7"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.monthly_price" />
                                    </div>

                                    <!-- Stripe Monthly ID -->
                                    <div>
                                        <InputLabel for="stripe_monthly_id" value="Stripe Monthly Price ID (Optional)" class="text-gray-900 dark:text-white" />
                                        <TextInput
                                            id="stripe_monthly_id"
                                            v-model="form.stripe_monthly_id"
                                            type="text"
                                            class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 font-mono text-sm"
                                            placeholder="price_xxxxxxxxxxxxx"
                                        />
                                        <InputError class="mt-2" :message="form.errors.stripe_monthly_id" />
                                    </div>
                                </div>

                                <!-- Yearly Price and Stripe ID Row -->
                                <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                                    <!-- Yearly Price -->
                                    <div>
                                        <InputLabel for="yearly_price" value="Yearly Price (Optional)" class="text-gray-900 dark:text-white" />
                                        <div class="relative mt-2">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                                            </div>
                                            <TextInput
                                                id="yearly_price"
                                                v-model="form.yearly_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="mt-0 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 pl-7"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.yearly_price" />
                                    </div>

                                    <!-- Stripe Yearly ID -->
                                    <div>
                                        <InputLabel for="stripe_yearly_id" value="Stripe Yearly Price ID (Optional)" class="text-gray-900 dark:text-white" />
                                        <TextInput
                                            id="stripe_yearly_id"
                                            v-model="form.stripe_yearly_id"
                                            type="text"
                                            class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 font-mono text-sm"
                                            placeholder="price_xxxxxxxxxxxxx"
                                        />
                                        <InputError class="mt-2" :message="form.errors.stripe_yearly_id" />
                                    </div>
                                </div>
                            </div>

                            <!-- Seat Limit Field -->
                            <div>
                                <InputLabel for="seat_limit" value="Seat Limit" class="text-gray-900 dark:text-white" />
                                <TextInput
                                    id="seat_limit"
                                    v-model="form.seat_limit"
                                    type="number"
                                    min="1"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    placeholder="1"
                                    required
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Maximum number of users/seats for this plan.
                                </p>
                                <InputError class="mt-2" :message="form.errors.seat_limit" />
                            </div>

                            <!-- Description Field -->
                            <div>
                                <InputLabel for="description" value="Description" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Optional description of plan features and benefits.
                                </p>
                                <textarea
                                    v-model="form.description"
                                    id="description"
                                    rows="4"
                                    class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 placeholder:text-gray-400 dark:placeholder:text-gray-500"
                                    placeholder="Describe what's included in this plan..."
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- Included Features Field -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <InputLabel value="Included Features" class="text-gray-900 dark:text-white" />
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Add features or benefits included in this plan.
                                </p>

                                <!-- Add New Item -->
                                <div class="mt-3 flex gap-2">
                                    <TextInput
                                        v-model="newIncludedItem"
                                        type="text"
                                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                        placeholder="e.g., Unlimited projects"
                                        @keydown.enter.prevent="addIncludedItem"
                                    />
                                    <button
                                        type="button"
                                        @click="addIncludedItem"
                                        class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- List of Items -->
                                <div v-if="form.included.length > 0" class="mt-4 space-y-2">
                                    <div
                                        v-for="(item, index) in form.included"
                                        :key="index"
                                        class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg group hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                                    >
                                        <svg class="h-5 w-5 text-primary-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="flex-1 text-sm text-gray-900 dark:text-white">{{ item }}</span>
                                        <button
                                            type="button"
                                            @click="removeIncludedItem(index)"
                                            class="flex-shrink-0 p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                                            title="Remove item"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="mt-4 text-center py-6 bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        No features added yet
                                    </p>
                                </div>

                                <InputError class="mt-2" :message="form.errors.included" />
                            </div>

                            <!-- Popular Field -->
                            <div>
                                <div class="flex items-start">
                                    <div class="flex h-6 items-center">
                                        <input
                                            v-model="form.popular"
                                            id="popular"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-600 dark:focus:ring-primary-400 bg-white dark:bg-gray-800"
                                        />
                                    </div>
                                    <div class="ml-3">
                                        <InputLabel for="popular" value="Popular" class="text-gray-900 dark:text-white" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Mark this plan as the popular option.
                                        </p>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.popular" />
                            </div>

                            <!-- Active Field -->
                            <div>
                                <div class="flex items-start">
                                    <div class="flex h-6 items-center">
                                        <input
                                            v-model="form.active"
                                            id="active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-600 dark:focus:ring-primary-400 bg-white dark:bg-gray-800"
                                        />
                                    </div>
                                    <div class="ml-3">
                                        <InputLabel for="active" value="Active" class="text-gray-900 dark:text-white" />
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Make this plan available for new subscriptions.
                                        </p>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.active" />
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-x-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-4 sm:px-8 rounded-b-xl">
                        <Link
                            :href="route('kiosk.plans.index')"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-x-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                            <svg
                                v-if="form.processing"
                                class="animate-spin -ml-1 h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg
                                v-else
                                class="-ml-0.5 h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ form.processing ? 'Creating...' : 'Create Plan' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>
