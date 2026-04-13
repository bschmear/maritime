<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

defineProps({
    legalEmail: {
        type: String,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const showSuccessModal = ref(false);

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            showSuccessModal.value = true;
        }
    },
    { immediate: true },
);

function closeSuccessModal() {
    showSuccessModal.value = false;
}

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    dealership_name: '',
    locations: '',
    message: '',
    /** Honeypot — leave empty (bots often fill hidden “website” fields). */
    _company_website: '',
});

const locationOptions = [
    { value: '1', label: '1 location' },
    { value: '2-5', label: '2–5 locations' },
    { value: '6-10', label: '6–10 locations' },
    { value: '10+', label: '10+ locations' },
];

function submit() {
    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Contact" />

    <AppLayout>
        <Teleport to="body">
            <div
                v-if="showSuccessModal && flashSuccess"
                class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="contact-success-title"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm"
                    aria-label="Close dialog"
                    @click="closeSuccessModal"
                />
                <div
                    class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-navy-600 dark:bg-navy-800"
                >
                    <button
                        type="button"
                        class="absolute right-3 top-3 rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-navy-700 dark:hover:text-gray-200"
                        aria-label="Close"
                        @click="closeSuccessModal"
                    >
                        <span class="material-icons text-xl leading-none">close</span>
                    </button>
                    <div class="px-8 pb-8 pt-10 text-center sm:px-10 sm:pt-12">
                        <div
                            class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-secondary-100 dark:bg-secondary-900/40"
                        >
                            <span class="material-icons text-4xl leading-none text-secondary-600 dark:text-secondary-400">
                                check_circle
                            </span>
                        </div>
                        <h2
                            id="contact-success-title"
                            class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl"
                        >
                            We got your request
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            {{ flashSuccess }}
                        </p>
                        <button
                            type="button"
                            class="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-8 py-3.5 text-base font-semibold text-white shadow-lg transition hover:bg-primary-700 sm:w-auto sm:min-w-[200px]"
                            @click="closeSuccessModal"
                        >
                            Got it
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div class="min-h-screen bg-white">
            <div
                v-if="flashError"
                class="border-b border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800 sm:px-6 lg:px-8 dark:border-red-800 dark:bg-red-900/25 dark:text-red-200"
                role="alert"
            >
                {{ flashError }}
            </div>

<!-- Hero -->
<section class="relative border-b border-gray-200 dark:border-gray-800 bg-primary-50 dark:bg-gray-900 py-20">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">

            <!-- Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary-100 dark:bg-primary-900/50 rounded-full text-primary-700 dark:text-primary-300 text-sm font-medium mb-6 backdrop-blur-sm border border-primary-200/50 dark:border-primary-700/50">
                <span class="material-icons text-base leading-none">play_circle</span>
                <span>Get a demo</span>
            </div>

            <h1 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                See Helmful <span class="text-primary-600 dark:text-primary-400">in action</span>
            </h1>

            <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-10">
                Tell us about your dealership and we'll show you exactly how Helmful can work for your operation.
            </p>

            <div class="flex flex-wrap justify-center gap-x-6 gap-y-3">
                <span class="inline-flex items-center gap-1.5 text-lg text-gray-500 dark:text-gray-400">
                    <span class="material-icons text-lg leading-none text-secondary-500">check_circle</span>
                    No commitment required
                </span>
                <span class="inline-flex items-center gap-1.5 text-lg text-gray-500 dark:text-gray-400">
                    <span class="material-icons text-lg leading-none text-secondary-500">check_circle</span>
                    30-minute walkthrough
                </span>
                <span class="inline-flex items-center gap-1.5 text-lg text-gray-500 dark:text-gray-400">
                    <span class="material-icons text-lg leading-none text-secondary-500">check_circle</span>
                    Tailored to your dealership
                </span>
            </div>

        </div>
    </div>
</section>
            <!-- Main content -->
            <section class="px-6 py-16 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-16 lg:grid-cols-5 lg:gap-24">

                        <!-- Left: what to expect -->
                        <div class="lg:col-span-2">
                            <h2 class="mb-2 text-xl font-bold tracking-tight text-gray-950">
                                What to expect
                            </h2>
                            <p class="mb-10 text-md leading-relaxed text-gray-500">
                                A focused 30-minute walkthrough of Helmful, tailored to how your dealership operates.
                            </p>

                            <div class="space-y-8">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50">
                                        <span class="material-icons text-xl leading-none text-primary-600">search</span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">We learn about your operation</p>
                                        <p class="mt-1 text-md leading-relaxed text-gray-500">We take the time to understand your team, your tools, and where things are breaking down.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50">
                                        <span class="material-icons text-xl leading-none text-primary-600">play_circle</span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">A live walkthrough of Helmful</p>
                                        <p class="mt-1 text-md leading-relaxed text-gray-500">We'll show you the features most relevant to your dealership — not a generic product tour.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50">
                                        <span class="material-icons text-xl leading-none text-primary-600">question_answer</span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">Your questions answered</p>
                                        <p class="mt-1 text-md leading-relaxed text-gray-500">Pricing, migration, integrations — ask anything. We're straightforward about what Helmful can and can't do.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Email contact -->
                            <div class="mt-12 border-t border-gray-100 pt-10">
                                <p class="mb-4 text-md font-semibold text-gray-500 uppercase tracking-widest text-xs">Prefer email?</p>
                                <a
                                    :href="`mailto:${legalEmail}`"
                                    class="inline-flex items-center gap-2 text-md font-semibold text-primary-600 hover:text-primary-700 transition-colors"
                                >
                                    <span class="material-icons text-xl leading-none">mail_outline</span>
                                    {{ legalEmail }}
                                </a>
                                <p class="mt-2 text-md text-gray-400">We typically respond within one business day.</p>
                            </div>
                        </div>

                        <!-- Right: form -->
                        <div class="lg:col-span-3">
                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-8 sm:p-10">
                                <h2 class="mb-1 text-xl font-bold tracking-tight text-gray-950">Request a demo</h2>
                                <p class="mb-8 text-md text-gray-500">Fill in your details and we'll be in touch shortly.</p>

                                <form @submit.prevent="submit" class="relative space-y-5">
                                    <!-- Honeypot: hidden from users; must stay empty -->
                                    <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                                        <label for="contact_company_website">Company website</label>
                                        <input
                                            id="contact_company_website"
                                            v-model="form._company_website"
                                            type="text"
                                            name="_company_website"
                                            tabindex="-1"
                                            autocomplete="off"
                                        />
                                    </div>

                                    <!-- Name row -->
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="first_name" class="mb-1.5 block text-md font-medium text-gray-700">
                                                First name
                                            </label>
                                            <input
                                                id="first_name"
                                                v-model="form.first_name"
                                                type="text"
                                                autocomplete="given-name"
                                                placeholder="Jane"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                                :class="{ 'border-red-400': form.errors.first_name }"
                                            />
                                            <p v-if="form.errors.first_name" class="mt-1.5 text-sm text-red-500">{{ form.errors.first_name }}</p>
                                        </div>
                                        <div>
                                            <label for="last_name" class="mb-1.5 block text-md font-medium text-gray-700">
                                                Last name
                                            </label>
                                            <input
                                                id="last_name"
                                                v-model="form.last_name"
                                                type="text"
                                                autocomplete="family-name"
                                                placeholder="Smith"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                                :class="{ 'border-red-400': form.errors.last_name }"
                                            />
                                            <p v-if="form.errors.last_name" class="mt-1.5 text-sm text-red-500">{{ form.errors.last_name }}</p>
                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <label for="email" class="mb-1.5 block text-md font-medium text-gray-700">
                                            Work email
                                        </label>
                                        <input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            autocomplete="email"
                                            placeholder="jane@marinadealership.com"
                                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                            :class="{ 'border-red-400': form.errors.email }"
                                        />
                                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-500">{{ form.errors.email }}</p>
                                    </div>

                                    <!-- Dealership name -->
                                    <div>
                                        <label for="dealership_name" class="mb-1.5 block text-md font-medium text-gray-700">
                                            Dealership name
                                        </label>
                                        <input
                                            id="dealership_name"
                                            v-model="form.dealership_name"
                                            type="text"
                                            placeholder="Marina Bay Boats"
                                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                            :class="{ 'border-red-400': form.errors.dealership_name }"
                                        />
                                        <p v-if="form.errors.dealership_name" class="mt-1.5 text-sm text-red-500">{{ form.errors.dealership_name }}</p>
                                    </div>

                                    <!-- Number of locations -->
                                    <div>
                                        <label for="locations" class="mb-1.5 block text-md font-medium text-gray-700">
                                            Number of locations
                                        </label>
                                        <div class="relative">
                                            <select
                                                id="locations"
                                                v-model="form.locations"
                                                class="w-full appearance-none rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                                :class="{ 'border-red-400': form.errors.locations, 'text-gray-400': !form.locations }"
                                            >
                                                <option value="" disabled>Select...</option>
                                                <option
                                                    v-for="opt in locationOptions"
                                                    :key="opt.value"
                                                    :value="opt.value"
                                                >{{ opt.label }}</option>
                                            </select>
                                            <span class="material-icons pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xl leading-none text-gray-400">expand_more</span>
                                        </div>
                                        <p v-if="form.errors.locations" class="mt-1.5 text-sm text-red-500">{{ form.errors.locations }}</p>
                                    </div>

                                    <!-- Message -->
                                    <div>
                                        <label for="message" class="mb-1.5 block text-md font-medium text-gray-700">
                                            Anything you'd like us to know?
                                            <span class="font-normal text-gray-400">(optional)</span>
                                        </label>
                                        <textarea
                                            id="message"
                                            v-model="form.message"
                                            rows="4"
                                            placeholder="Tell us about your current setup, biggest pain points, or what you're hoping Helmful can solve..."
                                            class="w-full resize-none rounded-xl border border-gray-200 bg-white px-4 py-3 text-md text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                        ></textarea>
                                    </div>

                                    <!-- Submit -->
                                    <div class="pt-2">
                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-8 py-4 text-md font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60"
                                        >
                                            <span
                                                v-if="form.processing"
                                                class="material-icons animate-spin text-base leading-none"
                                            >autorenew</span>
                                            <span v-else class="material-icons text-base leading-none">rocket_launch</span>
                                            {{ form.processing ? 'Sending…' : 'Request a demo' }}
                                        </button>
                                        <p class="mt-4 text-center text-sm text-gray-400">
                                            No commitment required. We'll reach out within one business day.
                                        </p>
                                    </div>

                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
