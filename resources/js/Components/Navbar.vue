<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const page = usePage();
const showingNavigationDropdown = ref(false);
const { theme, setTheme, initTheme } = useTheme();

const isAuthenticated = () => {
    return page.props.auth && page.props.auth.user;
};

const cycleTheme = () => {
    const themes = ['light', 'dark', 'auto'];
    const currentIndex = themes.indexOf(theme.value);
    const nextIndex = (currentIndex + 1) % themes.length;
    setTheme(themes[nextIndex]);
};

onMounted(() => {
    initTheme();
});
</script>

<template>
    <nav class="border-b border-gray-200 dark:border-navy-700 bg-white dark:bg-navy-900 shadow-sm dark:shadow-navy-950/50 z-10 backdrop-blur-sm bg-white/95 dark:bg-navy-900/95">
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex">
                    <!-- Logo -->
                    <div class="flex shrink-0 items-center">
                        <Link :href="isAuthenticated() ? route('dashboard') : route('home')">
                            <ApplicationLogo
                                class="block h-9 w-auto fill-current text-gray-800 dark:text-white-100"
                            />
                        </Link>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                        <NavLink
                            v-if="isAuthenticated()"
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </NavLink>

                        <!-- Add more navigation links here as needed -->
                        <!-- Example:
                        <NavLink
                            :href="route('about')"
                            :active="route().current('about')"
                        >
                            About
                        </NavLink>
                        -->
                    </div>
                </div>

                <!-- Right Side Navigation -->
                <div class="hidden sm:ms-6 sm:flex sm:items-center space-x-3">
                    <!-- Trial Badge -->
                    <div
                        v-if="isAuthenticated() && $page.props.auth.onTrial"
                        class="flex items-center gap-2 px-3 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 rounded-lg text-sm font-medium border border-amber-200 dark:border-amber-800"
                        :title="'Trial ends ' + $page.props.auth.trialEndsAt"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Trial</span>
                    </div>

                    <!-- Theme Toggle -->
                    <button
                        @click="cycleTheme"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 dark:text-white-400 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-navy-800 hover:text-primary-700 dark:hover:text-white-300 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                        :title="theme === 'auto' ? 'Theme: Auto' : theme === 'dark' ? 'Theme: Dark' : 'Theme: Light'"
                    >
                        <!-- Sun Icon (Light Mode) -->
                        <svg v-if="theme === 'light'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Moon Icon (Dark Mode) -->
                        <svg v-else-if="theme === 'dark'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <!-- Auto Icon -->
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </button>

                    <!-- Authenticated User Dropdown -->
                    <div v-if="isAuthenticated()" class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <span class="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-white dark:bg-navy-900 px-3 py-2 text-sm font-medium leading-4 text-gray-600 dark:text-white-400 transition duration-150 ease-in-out hover:text-gray-900 dark:hover:text-white-300 focus:outline-none"
                                    >
                                        {{ $page.props.auth.user.name }}

                                        <svg
                                            class="-me-0.5 ms-2 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- Guest Links -->
                    <div v-else class="flex items-center space-x-4">
                        <Link
                            v-if="route().has('login')"
                            :href="route('login')"
                            class="text-sm font-medium text-gray-700 dark:text-white-300 hover:text-gray-900 dark:hover:text-white-100 transition-colors"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="route().has('register')"
                            :href="route('register')"
                            class="rounded-lg bg-primary-600 hover:bg-primary-700 px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all duration-200"
                        >
                            Register
                        </Link>
                    </div>
                </div>

                <!-- Mobile Theme Toggle & Hamburger -->
                <div class="-me-2 flex items-center space-x-2 sm:hidden">
                    <!-- Theme Toggle (Mobile) -->
                    <button
                        @click="cycleTheme"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 dark:text-white-500 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-navy-800 hover:text-gray-600 dark:hover:text-white-400 focus:bg-primary-50 dark:focus:bg-navy-800 focus:text-gray-600 dark:focus:text-white-400 focus:outline-none"
                    >
                        <!-- Sun Icon (Light Mode) -->
                        <svg v-if="theme === 'light'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Moon Icon (Dark Mode) -->
                        <svg v-else-if="theme === 'dark'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <!-- Auto Icon -->
                        <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </button>

                    <!-- Hamburger -->
                    <button
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 dark:text-white-500 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-navy-800 hover:text-gray-600 dark:hover:text-white-400 focus:bg-primary-50 dark:focus:bg-navy-800 focus:text-gray-600 dark:focus:text-white-400 focus:outline-none"
                    >
                        <svg
                            class="h-6 w-6"
                            stroke="currentColor"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                :class="{
                                    hidden: showingNavigationDropdown,
                                    'inline-flex': !showingNavigationDropdown,
                                }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                :class="{
                                    hidden: !showingNavigationDropdown,
                                    'inline-flex': showingNavigationDropdown,
                                }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div
            :class="{
                block: showingNavigationDropdown,
                hidden: !showingNavigationDropdown,
            }"
            class="sm:hidden bg-white dark:bg-navy-900"
        >
            <div class="space-y-1 pb-3 pt-2">
                <ResponsiveNavLink
                    v-if="isAuthenticated()"
                    :href="route('dashboard')"
                    :active="route().current('dashboard')"
                >
                    Dashboard
                </ResponsiveNavLink>

                <!-- Add more responsive navigation links here as needed -->
            </div>

            <!-- Responsive Settings Options -->
            <div v-if="isAuthenticated()" class="border-t border-gray-200 dark:border-navy-700 pb-1 pt-4">
                <div class="px-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-base font-medium text-gray-800 dark:text-white-200">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-600 dark:text-white-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>
                        <!-- Trial Badge (Mobile) -->
                        <div
                            v-if="$page.props.auth.onTrial"
                            class="flex items-center gap-1.5 px-2.5 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 rounded-lg text-xs font-medium border border-amber-200 dark:border-amber-800"
                            :title="'Trial ends ' + $page.props.auth.trialEndsAt"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Trial</span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <ResponsiveNavLink :href="route('profile.edit')">
                        Profile
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        :href="route('logout')"
                        method="post"
                        as="button"
                    >
                        Log Out
                    </ResponsiveNavLink>
                </div>
            </div>

            <!-- Responsive Guest Links -->
            <div v-else class="border-t border-gray-200 dark:border-navy-700 pb-1 pt-4">
                <div class="space-y-1">
                    <ResponsiveNavLink
                        v-if="route().has('login')"
                        :href="route('login')"
                    >
                        Log in
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        v-if="route().has('register')"
                        :href="route('register')"
                    >
                        Register
                    </ResponsiveNavLink>
                </div>
            </div>
        </div>
    </nav>
</template>
