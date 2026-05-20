<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DocumentationHeader from '@/Components/Documentation/DocumentationHeader.vue';
import DocumentationNavigation from '@/Components/Documentation/DocumentationNavigation.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const mobileMenuOpen = ref(false);

const closeMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>
    <div class="min-h-full bg-white antialiased">
        <DocumentationHeader
            :mobile-menu-open="mobileMenuOpen"
            @toggle-menu="mobileMenuOpen = !mobileMenuOpen"
            @close-menu="closeMenu"
        />

        <div class="lg:ml-72 xl:ml-80 min-h-screen flex flex-col">
            <!-- Desktop sidebar -->
            <div
                class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-72 lg:flex-col lg:border-r lg:border-gray-900/10 lg:bg-white lg:px-6 lg:pt-6 lg:pb-10 xl:w-80"
            >
                <Link :href="route('docs.home')" class="flex items-center gap-3">
                    <ApplicationLogo class="h-7 w-auto fill-current text-gray-800" />
                    <span
                        class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 ring-1 ring-inset ring-primary-600/15"
                    >
                        Documentation
                    </span>
                </Link>

                <div class="mt-10 flex-1 overflow-y-auto">
                    <DocumentationNavigation />
                </div>
            </div>

            <!-- Main content -->
            <div class="relative flex min-h-full flex-col px-4 pt-14 sm:px-6 lg:px-8 grow">
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <div
                        class="absolute -top-20 -right-16 h-64 w-80 rounded-full bg-primary-200/15 blur-3xl"
                    />
                    <div
                        class="absolute -right-8 -top-6 h-[20rem] w-[28rem] text-primary-600/25 sm:h-[22rem] sm:w-[32rem]"
                    >
                        <svg
                            class="h-full w-full"
                            viewBox="0 0 480 360"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <defs>
                                <pattern
                                    id="doc-grid"
                                    width="28"
                                    height="28"
                                    patternUnits="userSpaceOnUse"
                                    patternTransform="rotate(-14 240 180)"
                                >
                                    <path
                                        d="M 28 0 L 0 0 0 28"
                                        stroke="currentColor"
                                        stroke-width="0.75"
                                        stroke-opacity="0.35"
                                    />
                                </pattern>
                                <radialGradient
                                    id="doc-fade"
                                    cx="88%"
                                    cy="12%"
                                    r="70%"
                                >
                                    <stop offset="0%" stop-color="white" stop-opacity="1" />
                                    <stop offset="55%" stop-color="white" stop-opacity="0.4" />
                                    <stop offset="100%" stop-color="white" stop-opacity="0" />
                                </radialGradient>
                                <mask id="doc-corner-mask">
                                    <rect width="480" height="360" fill="url(#doc-fade)" />
                                </mask>
                            </defs>
                            <g mask="url(#doc-corner-mask)">
                                <rect width="480" height="360" fill="url(#doc-grid)" />
                                <circle
                                    cx="360"
                                    cy="72"
                                    r="108"
                                    stroke="currentColor"
                                    stroke-width="1"
                                    stroke-opacity="0.2"
                                />
                                <circle
                                    cx="388"
                                    cy="48"
                                    r="64"
                                    stroke="currentColor"
                                    stroke-width="0.75"
                                    stroke-opacity="0.15"
                                />
                                <path
                                    d="M 420 24 L 468 96 L 352 96 Z"
                                    stroke="currentColor"
                                    stroke-width="0.75"
                                    stroke-opacity="0.12"
                                    fill="currentColor"
                                    fill-opacity="0.04"
                                />
                            </g>
                        </svg>
                    </div>
                    <div
                        class="absolute top-0 right-0 h-56 w-[min(100%,28rem)] bg-gradient-to-bl from-primary-50/15 via-transparent to-transparent"
                    />
                </div>

                <main class="relative z-10 mx-auto w-full max-w-3xl flex-auto py-10 lg:max-w-5xl lg:py-16 grow">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
