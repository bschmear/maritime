<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const mobileMenuOpen = ref(false);
const expandedSections = ref([]);

const navigation = [
    {
        name: 'Dashboard',
        href: 'kiosk.dashboard',
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    },
    {
        name: 'Posts',
        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
        children: [
            { name: 'All Posts', href: 'kiosk.posts.index' },
            { name: 'Categories', href: 'kiosk.categories.index' },
            { name: 'Tags', href: 'kiosk.tags.index' },
        ],
    },
    {
        name: 'FAQs',
        href: 'kiosk.faqs.index',
        icon: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z',
    },
    {
        name: 'Help',
        icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
        children: [
            { name: 'Categories', href: 'kiosk.help-categories.index' },
            { name: 'Articles', href: 'kiosk.help-articles.index' },
            { name: 'Support Tickets', href: 'kiosk.support-tickets.index' },
        ],
    },
    {
        name: 'Plans',
        href: 'kiosk.plans.index',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        name: 'Accounts',
        href: 'kiosk.accounts.index',
        icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008z',
    },
    {
        name: 'Users',
        href: 'kiosk.users.index',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
];

const isItemActive = (href) => route().current(href) || route().current(href + '*');

const isGroupActive = (item) => {
    if (!item.children?.length) {
        return false;
    }

    return item.children.some((child) => isItemActive(child.href));
};

const isExpanded = (name) => expandedSections.value.includes(name);

const toggleSection = (name) => {
    const index = expandedSections.value.indexOf(name);
    if (index > -1) {
        expandedSections.value.splice(index, 1);
    } else {
        expandedSections.value.push(name);
    }
};

const linkClasses = (active) => [
    active
        ? 'bg-primary-600 text-white shadow-sm dark:bg-primary-600'
        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900',
    'group flex gap-x-3 rounded-xl p-3 text-sm font-semibold leading-6 transition-colors',
];

const childLinkClasses = (active) => [
    active
        ? 'bg-primary-600/90 text-white dark:bg-primary-600'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-white',
    'block rounded-lg py-2 pl-11 pr-3 text-sm font-medium transition-colors',
];

onMounted(() => {
    navigation.forEach((item) => {
        if (item.children?.length && isGroupActive(item)) {
            expandedSections.value.push(item.name);
        }
    });
});
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100 dark:bg-black">
        <!-- Mobile menu button -->
        <div class="fixed left-4 top-4 z-50 lg:hidden">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg p-2 text-gray-700 transition-colors hover:bg-gray-200 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="mobileMenuOpen = !mobileMenuOpen"
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
            class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
            @click="mobileMenuOpen = false"
        />

        <!-- Sidebar -->
        <div
            :class="[
                'fixed inset-y-0 z-40 flex w-72 flex-col transition-transform duration-300 lg:translate-x-0',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div
                class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4 dark:border-gray-800 dark:bg-gray-950"
            >
                <!-- Logo -->
                <div class="flex h-16 shrink-0 items-center">
                    <Link :href="route('kiosk.dashboard')" class="block" @click="mobileMenuOpen = false">
                        <ApplicationLogo class="h-8 w-auto fill-current text-gray-800 dark:text-white" />
                    </Link>
                </div>

                <!-- Navigation -->
                <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                        <li>
                            <ul role="list" class="-mx-2 space-y-1">
                                <li v-for="item in navigation" :key="item.name">
                                    <!-- Nested group -->
                                    <template v-if="item.children?.length">
                                        <button
                                            type="button"
                                            :class="[
                                                linkClasses(isGroupActive(item)),
                                                'w-full items-center justify-between',
                                            ]"
                                            @click="toggleSection(item.name)"
                                        >
                                            <span class="flex items-center gap-x-3">
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
                                            </span>
                                            <svg
                                                class="h-5 w-5 shrink-0 transition-transform"
                                                :class="isExpanded(item.name) ? 'rotate-180' : ''"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        <ul v-show="isExpanded(item.name)" class="mt-1 space-y-1">
                                            <li v-for="child in item.children" :key="child.href">
                                                <Link
                                                    :href="route(child.href)"
                                                    :class="childLinkClasses(isItemActive(child.href))"
                                                    @click="mobileMenuOpen = false"
                                                >
                                                    {{ child.name }}
                                                </Link>
                                            </li>
                                        </ul>
                                    </template>

                                    <!-- Single link -->
                                    <Link
                                        v-else
                                        :href="route(item.href)"
                                        :class="linkClasses(isItemActive(item.href))"
                                        @click="mobileMenuOpen = false"
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
                    <div
                        class="flex items-center gap-x-4 rounded-xl p-3 text-sm font-semibold leading-6 text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-medium text-white dark:bg-gray-700"
                        >
                            {{ $page.props.auth.user.name.charAt(0) }}
                        </div>
                        <span class="sr-only">Your profile</span>
                        <div class="min-w-0 flex-1">
                            <span aria-hidden="true" class="block truncate">{{ $page.props.auth.user.name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $page.props.auth.user.email }}</span>
                        </div>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="shrink-0 text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                            title="Logout"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"
                                />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-1 flex-col lg:pl-72">
            <slot />
        </div>
    </div>
</template>
