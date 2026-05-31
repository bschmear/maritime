<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PlanFeatureEditor from '@/Components/Kiosk/PlanFeatureEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    allTiers: Object,
});

const form = useForm({
    title: props.allTiers.title || 'All tiers include',
    subtitle: props.allTiers.subtitle || '',
    features: props.allTiers.features || [],
});

const submit = () => {
    form.put(route('kiosk.pricing-settings.update'));
};
</script>

<template>
    <Head title="All tiers features" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.plans.index')"
                    class="text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">All tiers features</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-8">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                These features appear on the public pricing page under “All tiers include”. Plan cards still show
                tier-specific features configured on each plan.
            </p>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="space-y-6">
                    <div>
                        <InputLabel for="title" value="Section heading" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-2 block w-full dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="subtitle" value="Section subtitle" />
                        <textarea
                            id="subtitle"
                            v-model="form.subtitle"
                            rows="2"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                        <InputError class="mt-2" :message="form.errors.subtitle" />
                    </div>

                    <PlanFeatureEditor v-model="form.features" :error="form.errors.features" />
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link
                    :href="route('kiosk.plans.index')"
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="gradient-btn rounded-lg px-5 py-2.5 text-sm disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </KioskLayout>
</template>
