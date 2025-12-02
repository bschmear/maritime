<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useTheme } from '@/composables/useTheme';
const { theme, setTheme, initTheme } = useTheme();

const cycleTheme = () => {
    const themes = ['light', 'dark', 'auto'];
    const currentIndex = themes.indexOf(theme.value);
    const nextIndex = (currentIndex + 1) % themes.length;
    setTheme(themes[nextIndex]);
};

onMounted(() => {
    initTheme();
});

const mobileMenuOpen = ref(false);

const navigation = [
    {
        name: 'Dashboard',
        href: 'kiosk.dashboard',
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'
    },
    {
        name: 'Posts',
        href: 'kiosk.posts.index',
        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'
    },
    {
        name: 'Categories',
        href: 'kiosk.categories.index',
        icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z'
    },
    {
        name: 'Tags',
        href: 'kiosk.tags.index',
        icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z'
    },
    {
        name: 'FAQs',
        href: 'kiosk.faqs.index',
        icon: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z'
    },
    {
        name: 'Plans',
        href: 'kiosk.plans.index',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'
    },
    {
        name: 'Users',
        href: 'kiosk.users.index',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'
    },
];
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-primary-50 dark:bg-navy-950">
        <!-- Mobile menu button -->
        <div class="lg:hidden fixed top-4 left-4 z-50">
            <button
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 dark:text-white-300 hover:bg-primary-100 dark:hover:bg-navy-800 transition-colors"
            >
                <svg v-if="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile menu overlay -->
        <div
            v-show="mobileMenuOpen"
            @click="mobileMenuOpen = false"
            class="fixed inset-0 z-40 bg-navy-900/50 backdrop-blur-sm lg:hidden"
        ></div>

        <!-- Sidebar -->
        <div
            :class="[
                'fixed inset-y-0 z-40 flex w-72 flex-col transition-transform duration-300 lg:translate-x-0',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 dark:border-navy-700 bg-white dark:bg-navy-900 px-6 pb-4">
                <!-- Logo -->
                <div class="flex h-16 shrink-0 items-center">
                    <Link :href="route('kiosk.dashboard')" class="flex items-center gap-3">
                        <div class="gradient-btn flex h-10 w-10 items-center justify-center rounded-xl shadow-lg">
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold bg-gradient-to-r from-primary-600 to-secondary-600 bg-clip-text text-transparent">Boat CRM</span>
                    </Link>
                </div>

                <!-- Navigation -->
                <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                        <li>
                            <ul role="list" class="-mx-2 space-y-1">
                                <li v-for="item in navigation" :key="item.name">
                                    <Link
                                        :href="route(item.href)"
                                        :class="[
                                            route().current(item.href + '*')
                                                ? 'gradient-btn text-white shadow-md'
                                                : 'text-gray-700 dark:text-white-300 hover:bg-primary-50 dark:hover:bg-navy-800',
                                            'group flex gap-x-3 rounded-xl p-3 text-sm font-semibold leading-6 transition-all duration-200'
                                        ]"
                                    >
                                        <svg
                                            class="h-6 w-6 shrink-0"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                        </svg>
                                        {{ item.name }}
                                    </Link>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </nav>

                <!-- User Section -->
                <div class="-mx-2 mt-auto">
                    <div class="flex items-center gap-x-4 rounded-xl p-3 text-sm font-semibold leading-6 text-gray-700 dark:text-white-300 hover:bg-primary-50 dark:hover:bg-navy-800 transition-colors">
                        <div class="gradient-btn flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium text-white shadow-md">
                            {{ $page.props.auth.user.name.charAt(0) }}
                        </div>
                        <span class="sr-only">Your profile</span>
                        <div class="flex-1">
                            <span aria-hidden="true" class="block truncate">{{ $page.props.auth.user.name }}</span>
                            <span class="text-xs text-gray-600 dark:text-white-400">{{ $page.props.auth.user.email }}</span>
                        </div>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="text-gray-500 dark:text-white-500 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                            title="Logout"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:pl-72 flex-1 flex flex-col">
            <slot />
        </div>
    </div>
</template>
