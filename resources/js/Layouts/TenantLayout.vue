<script setup>
import { ref, computed } from 'vue';
import Navbar from '@/Components/Tenant/Navbar.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: false
    }
});

const showDropdown = ref(null);
const hideTimeout = ref(null);

// Helper functions for navigation state
const isCurrentRoute = (item) => {
    if (!item.href) return false;
    return route().current(item.href) || route().current(item.href + '.*');
};

const hasActiveChild = (item) => {
    if (!item.children) return false;
    return item.children.some(child => route().current(child.href));
};

// Dropdown management functions
const handleMouseEnter = (itemName) => {
    // Clear any pending hide timeout
    if (hideTimeout.value) {
        clearTimeout(hideTimeout.value);
        hideTimeout.value = null;
    }
    showDropdown.value = itemName;
};

const handleMouseLeave = (itemName) => {
    // Clear any existing timeout
    if (hideTimeout.value) {
        clearTimeout(hideTimeout.value);
    }
    
    // Delay hiding dropdown to allow cursor to move to menu items
    hideTimeout.value = setTimeout(() => {
        if (showDropdown.value === itemName) {
            showDropdown.value = null;
        }
        hideTimeout.value = null;
    }, 200);
};

const handleButtonBlur = (itemName) => {
    // Delay hiding to allow focus to move to dropdown items
    setTimeout(() => {
        // Check if any dropdown item has focus
        const dropdownItems = document.querySelectorAll(`[data-dropdown="${itemName}"] a`);
        const hasFocus = Array.from(dropdownItems).some(item => item === document.activeElement);

        if (!hasFocus && showDropdown.value === itemName) {
            showDropdown.value = null;
        }
    }, 100);
};

const handleItemBlur = (itemName) => {
    // Delay hiding to allow focus to move between items or back to button
    setTimeout(() => {
        const button = document.activeElement?.closest('[data-dropdown]')?.previousElementSibling;
        const dropdownItems = document.querySelectorAll(`[data-dropdown="${itemName}"] a`);
        const hasFocus = Array.from(dropdownItems).some(item => item === document.activeElement) ||
                        (button && button.tagName === 'BUTTON');

        if (!hasFocus && showDropdown.value === itemName) {
            showDropdown.value = null;
        }
    }, 100);
};

const handleKeydown = (event, item) => {
    if (event.key === 'Escape') {
        showDropdown.value = null;
        event.target.blur();
    } else if (event.key === 'ArrowDown' && showDropdown.value !== item.name) {
        event.preventDefault();
        showDropdown.value = item.name;
        // Focus first item after dropdown opens
        setTimeout(() => {
            const firstItem = document.querySelector(`[data-dropdown="${item.name}"] a`);
            if (firstItem) firstItem.focus();
        }, 10);
    } else if (event.key === 'ArrowDown' && showDropdown.value === item.name) {
        // Already open, focus first item
        event.preventDefault();
        const firstItem = document.querySelector(`[data-dropdown="${item.name}"] a`);
        if (firstItem) firstItem.focus();
    }
};

const handleItemKeydown = (event, item) => {
    const dropdownItems = Array.from(document.querySelectorAll(`[data-dropdown="${item.name}"] a`));
    const currentIndex = dropdownItems.indexOf(event.target);

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        const nextIndex = (currentIndex + 1) % dropdownItems.length;
        dropdownItems[nextIndex].focus();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        const prevIndex = currentIndex === 0 ? dropdownItems.length - 1 : currentIndex - 1;
        dropdownItems[prevIndex].focus();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        showDropdown.value = null;
        // Focus back to button
        const button = document.querySelector(`[aria-expanded="true"]`);
        if (button) button.focus();
    }
};

// Secondary navigation items - customize based on your needs
const secondaryNavItems = ref([
    { name: 'Overview', href: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    {
        name: 'Account',
        href: 'account.index',
        children: [
            { name: 'Overview', href: 'account.index' },
            { name: 'Locations', href: 'locations.index' },
            { name: 'Users', href: 'users.index' },
            { name: 'Roles', href: 'roles.index' },
        ]
    },
    { name: 'Leads', href: 'leads.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Customers', href: 'customers.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Vendors', href: 'vendors.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Tasks', href: 'tasks.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Invoices', href: 'invoices.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Secondary Navigation -->
        <nav class="bg-gray-100 border-b border-gray-200 dark:bg-gray-700 dark:border-gray-800">
            <div class="px-4 py-2">
                <div class="flex items-center">
                    <ul class="flex items-center text-sm text-gray-900 font-medium  space-x-1">
                        <li v-for="item in secondaryNavItems" :key="item.name || item.href" class="relative block lg:inline">
                            <!-- Item with children (dropdown) -->
                            <div 
                                v-if="item.children" 
                                class="relative" 
                                @mouseenter="handleMouseEnter(item.name)" 
                                @mouseleave="handleMouseLeave(item.name)"
                            >
                                <button
                                    @focus="showDropdown = item.name"
                                    @blur="handleButtonBlur(item.name)"
                                    @keydown="handleKeydown($event, item)"
                                    :aria-expanded="showDropdown === item.name"
                                    :aria-haspopup="true"
                                    :class="[
                                        'inline-flex items-center px-3 py-2 rounded-lg transition-colors',
                                        isCurrentRoute(item) || hasActiveChild(item)
                                            ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white'
                                            : 'hover:text-gray-900 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-white'
                                    ]"
                                >
                                    {{ item.name }}
                                    <svg class="w-4 h-4 ml-1 transition-transform" :class="showDropdown === item.name ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div
                                    v-show="showDropdown === item.name"
                                    :data-dropdown="item.name"
                                    class="absolute left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 z-50"
                                    @mouseenter="handleMouseEnter(item.name)"
                                    @mouseleave="handleMouseLeave(item.name)"
                                >
                                    <div class="py-1">
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.href"
                                            :href="route(child.href)"
                                            :class="[
                                                'block px-4 py-2 text-sm transition-colors focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700',
                                                route().current(child.href)
                                                    ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                                            ]"
                                            @focus="showDropdown = item.name"
                                            @blur="handleItemBlur(item.name)"
                                            @keydown="handleItemKeydown($event, item)"
                                        >
                                            {{ child.name }}
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Regular item without children -->
                            <Link
                                v-else
                                :href="route(item.href)"
                                :class="[
                                    'inline-block px-3 py-2 rounded-lg',
                                    route().current(item.href) || route().current(item.href + '.*')
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white'
                                        : 'hover:text-gray-900 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-white'
                                ]"
                            >
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Header (Optional) -->
        <header v-if="$slots.header" class="">
            <div class="w-full px-4 pt-6 sm:px-6 ">
                <slot name="header" />
            </div>
        </header>
        <!-- Page Header (Optional) -->
        <header v-if="$slots.sitckyeheader" class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
            <div class="w-full px-4 py-6 sm:px-6 ">
                <slot name="sitckyeheader" />
            </div>
        </header>
     
        <!-- Page Header (Optional) -->
        <header v-if="$slots.itemheader" class="">
            <div class="w-full px-4 py-6 sm:px-6 ">
                <slot name="sitckyeheader" />
            </div>
        </header>

        <!-- Page Content -->
        <main class=" mx-auto flex w-full h-full relative p-4 grow">
            <slot />
        </main>
    </div>
</template>
