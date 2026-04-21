<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    faqs: {
        type: Array,
        required: true,
    },
});

const featured = computed(() => props.faqs.filter((f) => f.featured));
const rest = computed(() => props.faqs.filter((f) => !f.featured));
</script>

<template>
    <Head title="FAQs" />

    <AppLayout>
        <div class="min-h-screen bg-white">

            <!-- Hero -->
            <section class="relative overflow-hidden bg-gray-950 px-6 pb-12 pt-24 sm:px-12 lg:px-24">

                <!-- Anchor watermark -->
                <div class="pointer-events-none absolute -right-8 -top-4 select-none opacity-[0.07]">
                    <span
                        class="material-icons text-primary-400"
                        style="font-size: 420px; line-height: 1;"
                    >anchor</span>
                </div>

                <!-- Hero text -->
                <div class="relative max-w-3xl pb-16">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-500/30 bg-primary-500/10 px-4 py-2">
                        <span class="material-icons text-base leading-none text-primary-300">quiz</span>
                        <span class="text-sm font-medium tracking-wide text-primary-300">FAQs</span>
                    </div>
                    <h1 class="mb-5 text-5xl font-bold leading-tight tracking-tight text-white sm:text-6xl">
                        Frequently asked questions
                    </h1>
                    <p class="text-xl leading-relaxed text-gray-400">
                        Everything you need to know about Helmful. Can't find an answer?
                        <a href="/contact" class="text-primary-400 underline underline-offset-4 transition-colors hover:text-primary-300">Get in touch.</a>
                    </p>
                </div>

                <!-- Wave divider -->
                <div class="absolute bottom-0 left-0 right-0 leading-none">
                    <svg viewBox="0 0 1440 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="h-16 w-full">
                        <path d="M0,32 C180,64 360,0 540,32 C720,64 900,0 1080,32 C1260,64 1350,16 1440,32 L1440,64 L0,64 Z" fill="white"/>
                    </svg>
                </div>
            </section>

            <!-- FAQ content -->
            <section class="px-6 py-16 sm:px-12 lg:px-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-16 lg:grid-cols-3 lg:gap-24">

                        <!-- Left rail: jump links -->
                        <div class="lg:col-span-1">
                            <div class="lg:sticky lg:top-24">
                                <p class="mb-2 text-sm font-semibold uppercase tracking-widest text-primary-600">Top questions</p>
                                <h2 class="mb-6 text-2xl font-bold tracking-tight text-gray-950">The essentials</h2>
                                <nav v-if="featured.length > 0" class="space-y-1">
                                    <a
                                        v-for="faq in featured"
                                        :key="faq.id"
                                        :href="'#faq-' + faq.id"
                                        class="group flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                    >
                                        <span
                                            class="material-icons text-xl leading-none text-primary-200 transition-colors group-hover:text-primary-400"
                                        >chevron_right</span>
                                        <span class="text-lg font-medium leading-snug">{{ faq.question }}</span>
                                    </a>
                                </nav>

                                <!-- CTA card -->
                                <div class="mt-10 rounded-2xl bg-gray-950 p-6">
                                    <span class="material-icons mb-3 text-2xl leading-none text-primary-400">support_agent</span>
                                    <p class="font-semibold text-white">Still have questions?</p>
                                    <p class="mt-1 text-lg text-gray-400">Our team is happy to walk you through anything.</p>
                                    <a
                                        href="/contact"
                                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-lg font-semibold text-white transition hover:bg-primary-700"
                                    >
                                        <span class="material-icons text-base leading-none">mail_outline</span>
                                        Get in touch
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Right: static Q&A -->
                        <div class="lg:col-span-2">
                            <div v-if="featured.length > 0" class="mb-12 space-y-6">
                                <div
                                    v-for="faq in featured"
                                    :id="'faq-' + faq.id"
                                    :key="faq.id"
                                    class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800/50"
                                >
                                    <div class="flex gap-4">
                                        <div
                                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/40"
                                        >
                                            <span class="material-icons text-base leading-none text-primary-600 dark:text-primary-400"
                                                >star</span
                                            >
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ faq.question }}
                                            </h3>
                                            <p class="mt-3 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                                {{ faq.answer }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="rest.length > 0">
                                <p class="mb-6 text-sm font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                                    More questions
                                </p>
                                <div class="space-y-6">
                                    <div
                                        v-for="faq in rest"
                                        :id="'faq-' + faq.id"
                                        :key="faq.id"
                                        class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800/50"
                                    >
                                        <div class="flex gap-4">
                                            <div
                                                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700/80"
                                            >
                                                <span class="material-icons text-base leading-none text-gray-600 dark:text-gray-300"
                                                    >help_outline</span
                                                >
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    {{ faq.question }}
                                                </h3>
                                                <p class="mt-3 text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                                                    {{ faq.answer }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
