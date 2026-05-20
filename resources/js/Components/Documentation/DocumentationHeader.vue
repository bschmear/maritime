<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DocumentationNavigation from '@/Components/Documentation/DocumentationNavigation.vue';
import DocumentationSearch from '@/Components/Documentation/DocumentationSearch.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    mobileMenuOpen: Boolean,
});

const emit = defineEmits(['toggle-menu', 'close-menu']);
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-50 flex h-14 items-center gap-4 border-b border-primary-900/5 bg-white/80 px-4 backdrop-blur-md sm:px-6 lg:left-72 lg:z-30 lg:px-8 xl:left-80"
    >
        <div class="flex shrink-0 items-center gap-3 lg:hidden">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 hover:bg-gray-100"
                aria-label="Open navigation"
                @click="emit('toggle-menu')"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                </svg>
            </button>
            <Link :href="route('docs.home')" class="flex items-center gap-2.5" @click="emit('close-menu')">
                <ApplicationLogo class="h-6 w-auto fill-current text-gray-800" />
                <span
                    class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 ring-1 ring-inset ring-primary-600/15"
                >
                    Docs
                </span>
            </Link>
        </div>

        <DocumentationSearch @navigate="emit('close-menu')" />

        <div class="flex shrink-0 items-center gap-2 lg:gap-4">
            <Link
                :href="route('docs.home')"
                class="hidden text-sm text-gray-600 transition hover:text-gray-900 lg:inline"
                :class="{ 'font-medium text-gray-900': route().current('docs.home') }"
            >
                Home
            </Link>
        </div>
    </header>

    <!-- Mobile nav drawer -->
    <Teleport to="body">
        <div
            v-show="mobileMenuOpen"
            class="fixed inset-0 z-40 lg:hidden"
            @click="emit('close-menu')"
        >
            <div class="absolute inset-0 bg-gray-900/20 backdrop-blur-sm" />
            <div
                class="absolute inset-y-0 left-0 flex w-full max-w-sm flex-col overflow-y-auto bg-white px-6 pt-6 pb-8 shadow-xl ring-1 ring-gray-900/10"
                @click.stop
            >
                <div class="flex items-center justify-between">
                    <Link :href="route('docs.home')" class="flex items-center gap-2.5" @click="emit('close-menu')">
                        <ApplicationLogo class="h-7 w-auto fill-current text-gray-800" />
                        <span
                            class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 ring-1 ring-inset ring-primary-600/15"
                        >
                            Documentation
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
                        aria-label="Close navigation"
                        @click="emit('close-menu')"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="mt-8">
                    <DocumentationNavigation close-on-navigate @navigate="emit('close-menu')" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
