<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    faqs: {
        type: Array,
        required: true,
    },
});

const openId = ref(null);

const featured = computed(() => props.faqs.filter((f) => f.featured));
const rest = computed(() => props.faqs.filter((f) => !f.featured));

function toggle(id) {
    openId.value = openId.value === id ? null : id;
}
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
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-500/30 bg-primary-500/10 px-4 py-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                        <span class="text-lg font-medium tracking-wide text-primary-300">FAQs</span>
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
                <div class="mx-auto ">
                    <div class="grid gap-16 lg:grid-cols-3 lg:gap-24">

                        <!-- Left rail -->
                        <div class="lg:col-span-1">
                            <div class="lg:sticky lg:top-24">
                                <p class="mb-2 text-sm font-semibold uppercase tracking-widest text-primary-600">Top questions</p>
                                <h2 class="mb-6 text-2xl font-bold tracking-tight text-gray-950">The essentials</h2>
                                <nav class="space-y-1">
                                    <button
                                        v-for="faq in featured"
                                        :key="faq.id"
                                        class="group flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left transition"
                                        :class="openId === faq.id
                                            ? 'bg-primary-50 text-primary-700'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                                        @click="toggle(faq.id)"
                                    >
                                        <span
                                            class="material-icons text-xl leading-none transition-colors"
                                            :class="openId === faq.id ? 'text-primary-500' : 'text-primary-200 group-hover:text-primary-300'"
                                        >chevron_right</span>
                                        <span class="text-lg font-medium leading-snug">{{ faq.question }}</span>
                                    </button>
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

                        <!-- Right: accordion -->
                        <div class="lg:col-span-2">

                            <!-- Featured FAQs -->
                            <div v-if="featured.length > 0" class="mb-10">
                                <div class="divide-y divide-gray-100">
                                    <div v-for="faq in featured" :key="faq.id">
                                        <button
                                            class="flex w-full items-start gap-4 py-6 text-left transition"
                                            @click="toggle(faq.id)"
                                        >
                                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50">
                                                <span class="material-icons text-base leading-none text-primary-600">star</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="pr-8 text-lg font-semibold text-gray-900">{{ faq.question }}</p>
                                                <Transition
                                                    enter-active-class="transition-all duration-200 ease-out"
                                                    enter-from-class="opacity-0 -translate-y-1"
                                                    enter-to-class="opacity-100 translate-y-0"
                                                    leave-active-class="transition-all duration-150 ease-in"
                                                    leave-from-class="opacity-100 translate-y-0"
                                                    leave-to-class="opacity-0 -translate-y-1"
                                                >
                                                    <p v-if="openId === faq.id" class="mt-3 text-lg leading-relaxed text-gray-500">
                                                        {{ faq.answer }}
                                                    </p>
                                                </Transition>
                                            </div>
                                            <span
                                                class="material-icons mt-0.5 shrink-0 text-xl leading-none text-primary-300 transition-transform duration-200"
                                                :class="{ 'rotate-180': openId === faq.id }"
                                            >expand_more</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Non-featured FAQs -->
                            <div v-if="rest.length > 0">
                                <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-primary-400">More questions</p>
                                <div class="divide-y divide-gray-100">
                                    <div v-for="faq in rest" :key="faq.id">
                                        <button
                                            class="flex w-full items-start gap-4 py-6 text-left transition"
                                            @click="toggle(faq.id)"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <p class="pr-8 text-lg font-semibold text-gray-900">{{ faq.question }}</p>
                                                <Transition
                                                    enter-active-class="transition-all duration-200 ease-out"
                                                    enter-from-class="opacity-0 -translate-y-1"
                                                    enter-to-class="opacity-100 translate-y-0"
                                                    leave-active-class="transition-all duration-150 ease-in"
                                                    leave-from-class="opacity-100 translate-y-0"
                                                    leave-to-class="opacity-0 -translate-y-1"
                                                >
                                                    <p v-if="openId === faq.id" class="mt-3 text-lg leading-relaxed text-gray-500">
                                                        {{ faq.answer }}
                                                    </p>
                                                </Transition>
                                            </div>
                                            <span
                                                class="material-icons mt-0.5 shrink-0 text-xl leading-none text-primary-300 transition-transform duration-200"
                                                :class="{ 'rotate-180': openId === faq.id }"
                                            >expand_more</span>
                                        </button>
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
