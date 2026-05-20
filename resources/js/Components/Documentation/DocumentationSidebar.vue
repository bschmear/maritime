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
                        class="absolute -top-20 -right-16 h-64 w-80 rounded-full bg-primary-200/20 blur-3xl"
                    />
                    <div
                        class="absolute top-0 right-0 h-48 w-[min(100%,32rem)] bg-gradient-to-bl from-primary-100/30 via-primary-50/10 to-transparent"
                    />
                </div>

                <main class="relative z-10 mx-auto w-full max-w-3xl flex-auto py-10 lg:max-w-5xl lg:py-16 grow">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
