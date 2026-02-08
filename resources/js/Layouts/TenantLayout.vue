<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Navbar from '@/Components/Tenant/Navbar.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: false
    }
});

const showDropdown = ref(null);
const showNestedDropdown = ref(null);
const hideTimeout = ref(null);
const mobileMenuOpen = ref(false);
const mobileExpandedItems = ref([]);

// Helper functions for navigation state
const isCurrentRoute = (item) => {
    if (!item.href) return false;
    return route().current(item.href) || route().current(item.href + '.*');
};

const hasActiveChild = (item) => {
    if (!item.children) return false;
    return item.children.some(child => {
        if (child.href && route().current(child.href)) return true;
        if (child.children) {
            return child.children.some(grandChild => route().current(grandChild.href));
        }
        return false;
    });
};

const hasActiveGrandChild = (child) => {
    if (!child.children) return false;
    return child.children.some(grandChild => route().current(grandChild.href));
};

// Desktop dropdown management - simplified
const openDropdown = (itemName, isNested = false) => {
    if (hideTimeout.value) {
        clearTimeout(hideTimeout.value);
        hideTimeout.value = null;
    }
    
    if (isNested) {
        showNestedDropdown.value = itemName;
    } else {
        showDropdown.value = itemName;
        showNestedDropdown.value = null; // Clear nested when opening new parent
    }
};

const closeDropdown = (itemName, isNested = false) => {
    if (hideTimeout.value) {
        clearTimeout(hideTimeout.value);
    }
    
    hideTimeout.value = setTimeout(() => {
        if (isNested && showNestedDropdown.value === itemName) {
            showNestedDropdown.value = null;
        } else if (!isNested && showDropdown.value === itemName) {
            showDropdown.value = null;
            showNestedDropdown.value = null;
        }
        hideTimeout.value = null;
    }, 200);
};

// Mobile menu functions
const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
    if (!mobileMenuOpen.value) {
        mobileExpandedItems.value = [];
    }
};

const toggleMobileItem = (itemName) => {
    const index = mobileExpandedItems.value.indexOf(itemName);
    if (index > -1) {
        mobileExpandedItems.value.splice(index, 1);
    } else {
        mobileExpandedItems.value.push(itemName);
    }
};

const isMobileExpanded = (itemName) => {
    return mobileExpandedItems.value.includes(itemName);
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
    mobileExpandedItems.value = [];
};

// Close mobile menu on route change
onMounted(() => {
    // You might want to add a route change listener here
});

// Secondary navigation items
const secondaryNavItems = ref([
    { name: 'Overview', href: 'dashboard'},
    {
        name: 'Account',
        href: 'account.index',
        children: [
            { name: 'Overview', href: 'account.index' },
            { name: 'Locations', href: 'locations.index' },
            { name: 'Users', href: 'users.index' },
            { name: 'Roles', href: 'roles.index' },
            { name: 'Subsidiaries', href: 'subsidiaries.index' }
        ]
    },
    {
        name: 'Operations',
        href: 'operations.index',
        children: [
            { name: 'Overview', href: 'operations.index' },
            { name: 'Transactions', href: 'transactions.index' },
            { 
                name: 'Items',
                children: [
                    { name: 'Inventory Items', href: 'inventoryitems.index' },
                    { name: 'Service Items', href: 'serviceitems.index' }
                ]
            },
            { name: 'Invoices', href: 'invoices.index' },
            { name: 'Work Orders', href: 'workorders.index' }
        ]
    },
    { name: 'Leads', href: 'leads.index' },
    { name: 'Customers', href: 'customers.index' },
    { name: 'Vendors', href: 'vendors.index'},
    { name: 'Tasks', href: 'tasks.index' },
    { name: 'Documents', href: 'documents.index' }
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Desktop Secondary Navigation -->
        <nav class="hidden lg:block bg-gray-100 border-b border-gray-200 dark:bg-gray-700 dark:border-gray-800">
            <div class="px-4 py-2">
                <div class="flex items-center">
                    <ul class="flex items-center text-sm text-gray-900 font-medium space-x-1">
                        <li v-for="item in secondaryNavItems" :key="item.name || item.href" class="relative">
                            <!-- Item with children (dropdown) -->
                            <div 
                                v-if="item.children" 
                                class="relative" 
                                @mouseenter="openDropdown(item.name)" 
                                @mouseleave="closeDropdown(item.name)"
                            >
                                <button
                                    :class="[
                                        'inline-flex items-center px-3 py-2 rounded-lg transition-colors',
                                        isCurrentRoute(item) || hasActiveChild(item)
                                            ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white'
                                            : 'hover:text-gray-900 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-white'
                                    ]"
                                >
                                    {{ item.name }}
                                    <span class="material-icons text-sm ml-1 transition-transform" :class="showDropdown === item.name ? 'rotate-180' : ''">
                                        expand_more
                                    </span>
                                </button>

                                <!-- Dropdown Menu -->
                                <div
                                    v-show="showDropdown === item.name"
                                    class="absolute left-0 mt-1 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 z-50"
                                    @mouseenter="openDropdown(item.name)"
                                    @mouseleave="closeDropdown(item.name)"
                                >
                                    <div class="py-1">
                                        <template v-for="child in item.children" :key="child.name || child.href">
                                            <!-- Child without nested children -->
                                            <Link
                                                v-if="!child.children"
                                                :href="route(child.href)"
                                                :class="[
                                                    'block px-4 py-2 text-sm transition-colors',
                                                    route().current(child.href)
                                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                ]"
                                            >
                                                {{ child.name }}
                                            </Link>

                                            <!-- Child with nested children (flyout) -->
                                            <div
                                                v-else
                                                class="relative"
                                                @mouseenter="openDropdown(child.name, true)"
                                                @mouseleave="closeDropdown(child.name, true)"
                                            >
                                                <div
                                                    :class="[
                                                        'flex items-center justify-between px-4 py-2 text-sm cursor-pointer transition-colors',
                                                        hasActiveGrandChild(child)
                                                            ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                    ]"
                                                >
                                                    <span>{{ child.name }}</span>
                                                    <span class="material-icons text-sm">chevron_right</span>
                                                </div>

                                                <!-- Nested Submenu (flyout to the right) -->
                                                <div
                                                    v-show="showNestedDropdown === child.name"
                                                    class="absolute left-full top-0 ml-1 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 z-50"
                                                    @mouseenter="openDropdown(child.name, true)"
                                                    @mouseleave="closeDropdown(child.name, true)"
                                                >
                                                    <div class="py-1">
                                                        <Link
                                                            v-for="grandChild in child.children"
                                                            :key="grandChild.href"
                                                            :href="route(grandChild.href)"
                                                            :class="[
                                                                'block px-4 py-2 text-sm transition-colors',
                                                                route().current(grandChild.href)
                                                                    ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                            ]"
                                                        >
                                                            {{ grandChild.name }}
                                                        </Link>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Regular item without children -->
                            <Link
                                v-else
                                :href="route(item.href)"
                                :class="[
                                    'inline-block px-3 py-2 rounded-lg transition-colors',
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

        <!-- Mobile Navigation Button -->
        <div class="lg:hidden bg-gray-100 border-b border-gray-200 dark:bg-gray-700 dark:border-gray-800 px-4 py-2">
            <button
                @click="toggleMobileMenu"
                class="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-600 rounded-lg"
            >
                <span>Menu</span>
                <span class="material-icons text-sm transition-transform" :class="mobileMenuOpen ? 'rotate-180' : ''">
                    expand_more
                </span>
            </button>
        </div>

        <!-- Mobile Navigation Menu -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-show="mobileMenuOpen" class="lg:hidden bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-lg">
                <nav class="px-2 py-3 space-y-1 max-h-[70vh] overflow-y-auto">
                    <template v-for="item in secondaryNavItems" :key="item.name || item.href">
                        <!-- Item without children -->
                        <Link
                            v-if="!item.children"
                            :href="route(item.href)"
                            @click="closeMobileMenu"
                            :class="[
                                'block px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                route().current(item.href) || route().current(item.href + '.*')
                                    ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                            ]"
                        >
                            {{ item.name }}
                        </Link>

                        <!-- Item with children -->
                        <div v-else class="space-y-1">
                            <button
                                @click="toggleMobileItem(item.name)"
                                :class="[
                                    'w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                    hasActiveChild(item)
                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                ]"
                            >
                                <span>{{ item.name }}</span>
                                <span class="material-icons text-sm transition-transform" :class="isMobileExpanded(item.name) ? 'rotate-180' : ''">
                                    expand_more
                                </span>
                            </button>

                            <!-- Children (collapsible) -->
                            <Transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 max-h-0"
                                enter-to-class="opacity-100 max-h-96"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 max-h-96"
                                leave-to-class="opacity-0 max-h-0"
                            >
                                <div v-show="isMobileExpanded(item.name)" class="ml-4 mt-1 space-y-1 overflow-hidden">
                                    <template v-for="child in item.children" :key="child.name || child.href">
                                        <!-- Child without nested children -->
                                        <Link
                                            v-if="!child.children"
                                            :href="route(child.href)"
                                            @click="closeMobileMenu"
                                            :class="[
                                                'block px-3 py-2 rounded-lg text-sm transition-colors',
                                                route().current(child.href)
                                                    ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                            ]"
                                        >
                                            {{ child.name }}
                                        </Link>

                                        <!-- Child with nested children -->
                                        <div v-else class="space-y-1">
                                            <button
                                                @click="toggleMobileItem(child.name)"
                                                :class="[
                                                    'w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors',
                                                    hasActiveGrandChild(child)
                                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                ]"
                                            >
                                                <span>{{ child.name }}</span>
                                                <span class="material-icons text-sm transition-transform" :class="isMobileExpanded(child.name) ? 'rotate-180' : ''">
                                                    expand_more
                                                </span>
                                            </button>

                                            <!-- Grandchildren -->
                                            <Transition
                                                enter-active-class="transition-all duration-200 ease-out"
                                                enter-from-class="opacity-0 max-h-0"
                                                enter-to-class="opacity-100 max-h-96"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-from-class="opacity-100 max-h-96"
                                                leave-to-class="opacity-0 max-h-0"
                                            >
                                                <div v-show="isMobileExpanded(child.name)" class="ml-4 mt-1 space-y-1 overflow-hidden">
                                                    <Link
                                                        v-for="grandChild in child.children"
                                                        :key="grandChild.href"
                                                        :href="route(grandChild.href)"
                                                        @click="closeMobileMenu"
                                                        :class="[
                                                            'block px-3 py-2 rounded-lg text-sm transition-colors',
                                                            route().current(grandChild.href)
                                                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                                        ]"
                                                    >
                                                        {{ grandChild.name }}
                                                    </Link>
                                                </div>
                                            </Transition>
                                        </div>
                                    </template>
                                </div>
                            </Transition>
                        </div>
                    </template>
                </nav>
            </div>
        </Transition>

        <!-- Page Header (Optional) -->
        <header v-if="$slots.header" class="">
            <div class="w-full px-4 pt-6 sm:px-6">
                <slot name="header" />
            </div>
        </header>

        <!-- Sticky Header (Optional) -->
        <header v-if="$slots.stickyheader" class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
            <div class="w-full px-4 py-6 sm:px-6">
                <slot name="stickyheader" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="mx-auto flex w-full h-full relative p-4 grow flex-col space-y-4 md:space-y-6">
            <slot />
        </main>
    </div>
</template>