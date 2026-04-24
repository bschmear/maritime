<script setup>
import { ref, onMounted, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const page = usePage();
const pwa = computed(() => Boolean(page.props.pwa));
const showingNavigationDropdown = ref(false);
const { theme, setTheme, initTheme } = useTheme();

const dashboardHref = computed(() =>
    pwa.value && route().has('dashboard') ? route('dashboard', { pwa: 1 }) : route('dashboard'),
);

const isAuthenticated = () => {
    return page.props.auth && page.props.auth.user;
};

/** Public + marketing routes (aligned with Footer / web.php). */
const primaryNavLinks = [
    { label: 'About', routeName: 'about', match: ['about'] },
    { label: 'Pricing', routeName: 'checkout.plans', match: ['checkout.plans'] },
    { label: 'Blog', routeName: 'blog', match: ['blog', 'blogCategory', 'blogTag', 'blogPostShow'] },
    { label: 'FAQ', routeName: 'faq', match: ['faq'] },
    { label: 'Contact', routeName: 'contact', match: ['contact'] },
];

const visiblePrimaryLinks = computed(() =>
    primaryNavLinks.filter((item) => route().has(item.routeName)),
);

/** Central app has `home`; tenant subdomains often only expose portal/dashboard routes. */
const guestLogoHref = computed(() => {
    if (route().has('home')) {
        return route('home');
    }
    if (route().has('portal.login')) {
        return route('portal.login');
    }

    return '/';
});

const logoHref = computed(() => {
    if (pwa.value) {
        if (isAuthenticated() && route().has('dashboard')) {
            return route('dashboard', { pwa: 1 });
        }
        if (route().has('home')) {
            return route('home', { pwa: 1 });
        }
    }

    return guestLogoHref.value;
});

const isNavActive = (matchNames) => matchNames.some((name) => route().current(name));

const closeMobileMenu = () => {
    showingNavigationDropdown.value = false;
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
    <nav
        class="sticky top-0 z-30 w-full border-b border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950"
    >
        <!-- Primary row: full width, centered links on lg+ (md is too cramped for many links) -->
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="relative flex h-16 items-center justify-between gap-3">
                <!-- Logo (left) -->
                <div class="flex shrink-0 items-center z-20">
                    <Link :href="logoHref">
                        <ApplicationLogo
                            class="block h-9 w-auto fill-current text-gray-800 dark:text-white-100"
                        />
                    </Link>
                </div>

                <!-- Centered nav — wide screens only -->
                <nav
                    class="absolute inset-x-0 top-0 bottom-0 z-10 hidden lg:flex items-center justify-center pointer-events-none px-28 xl:px-40"
                    aria-label="Main navigation"
                >
                    <div class="pointer-events-auto flex max-w-full flex-wrap items-center justify-center gap-x-1 gap-y-1 lg:gap-x-3">
                        <template v-if="!pwa">
                            <Link
                                v-for="item in visiblePrimaryLinks"
                                :key="item.routeName"
                                :href="route(item.routeName)"
                                class="rounded-lg px-2.5 py-2 text-md font-medium transition-colors lg:px-3"
                                :class="
                                    isNavActive(item.match)
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'
                                "
                            >
                                {{ item.label }}
                            </Link>
                        </template>
                        <Link
                            v-if="isAuthenticated() && route().has('dashboard')"
                            :href="dashboardHref"
                            class="rounded-lg px-2.5 py-2 text-md font-medium transition-colors lg:px-3"
                            :class="
                                route().current('dashboard')
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'
                            "
                        >
                            Dashboard
                        </Link>
                    </div>
                </nav>

                <!-- Right: theme + auth (same breakpoint as inline nav) -->
                <div class="hidden shrink-0 items-center gap-2 lg:flex lg:z-20">
                    <!-- Trial Badge -->
                    <div
                        v-if="isAuthenticated() && $page.props.auth.onTrial"
                        class="hidden lg:flex items-center gap-2 px-3 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 rounded-lg text-sm font-medium border border-amber-200 dark:border-amber-800"
                        :title="'Trial ends ' + $page.props.auth.trialEndsAt"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Trial</span>
                    </div>

                    <button
                        type="button"
                        @click="cycleTheme"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 dark:text-white-400 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-gray-800 hover:text-primary-700 dark:hover:text-white-300 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                        :title="theme === 'auto' ? 'Theme: Auto' : theme === 'dark' ? 'Theme: Dark' : 'Theme: Light'"
                    >
                        <svg v-if="theme === 'light'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg v-else-if="theme === 'dark'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </button>

                    <div v-if="isAuthenticated()" class="relative">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <span class="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-white dark:bg-gray-900 px-3 py-2 text-sm font-medium leading-4 text-gray-600 dark:text-white-400 transition duration-150 ease-in-out hover:text-gray-900 dark:hover:text-white-300 focus:outline-none"
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

                    <div v-else class="flex items-center gap-3">
                        <Link
                            v-if="route().has('login')"
                            :href="pwa ? route('login', { pwa: 1 }) : route('login')"
                            class="text-sm font-medium text-gray-700 dark:text-white-300 hover:text-gray-900 dark:hover:text-white-100 transition-colors"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="!pwa && route().has('register')"
                            :href="route('register')"
                            class="rounded-lg bg-primary-600 hover:bg-primary-700 px-4 py-2 text-sm font-medium text-white shadow-md hover:shadow-lg transition-all duration-200"
                        >
                            Register
                        </Link>
                    </div>
                </div>

                <!-- Compact: theme + hamburger (below lg) -->
                <div class="-me-2 flex items-center gap-2 lg:hidden z-20">
                    <button
                        type="button"
                        @click="cycleTheme"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 dark:text-white-500 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-white-400 focus:outline-none"
                    >
                        <svg v-if="theme === 'light'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg v-else-if="theme === 'dark'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 dark:text-white-500 transition duration-150 ease-in-out hover:bg-primary-50 dark:hover:bg-gray-800 focus:outline-none"
                        :aria-expanded="showingNavigationDropdown"
                        aria-controls="navbar-mobile-menu"
                    >
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path
                                :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
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

        <!-- Mobile menu -->
        <div
            id="navbar-mobile-menu"
            :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
            class="lg:hidden border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="max-h-[min(70vh,28rem)] overflow-y-auto">
                <div class="px-2 pb-2 pt-3 space-y-0.5">
                    <p
                        v-if="!pwa"
                        class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                    >
                        Menu
                    </p>
                    <template v-if="!pwa">
                        <ResponsiveNavLink
                            v-for="item in visiblePrimaryLinks"
                            :key="'m-' + item.routeName"
                            :href="route(item.routeName)"
                            :active="isNavActive(item.match)"
                            @click="closeMobileMenu"
                        >
                            {{ item.label }}
                        </ResponsiveNavLink>
                    </template>
                    <ResponsiveNavLink
                        v-if="isAuthenticated() && route().has('dashboard')"
                        :href="dashboardHref"
                        :active="route().current('dashboard')"
                        @click="closeMobileMenu"
                    >
                        Dashboard
                    </ResponsiveNavLink>
                </div>

                <div v-if="isAuthenticated()" class="border-t border-gray-200 px-2 py-3 dark:border-gray-800">
                    <div
                        v-if="$page.props.auth.onTrial"
                        class="mx-3 mb-3 flex w-fit items-center gap-2 rounded-lg border border-amber-200 bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Trial</span>
                    </div>
                    <div class="px-3 pb-2">
                        <div class="text-base font-medium text-gray-800 dark:text-white-200">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-white-400">
                            {{ $page.props.auth.user.email }}
                        </div>
                    </div>
                    <ResponsiveNavLink :href="route('profile.edit')" @click="closeMobileMenu">
                        Profile
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        :href="route('logout')"
                        method="post"
                        as="button"
                        @click="closeMobileMenu"
                    >
                        Log Out
                    </ResponsiveNavLink>
                </div>

                <div v-else class="space-y-1 border-t border-gray-200 px-2 py-3 dark:border-gray-800">
                    <ResponsiveNavLink
                        v-if="route().has('login')"
                        :href="pwa ? route('login', { pwa: 1 }) : route('login')"
                        @click="closeMobileMenu"
                    >
                        Log in
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        v-if="!pwa && route().has('register')"
                        :href="route('register')"
                        @click="closeMobileMenu"
                    >
                        Register
                    </ResponsiveNavLink>
                </div>
            </div>
        </div>
    </nav>
</template>
