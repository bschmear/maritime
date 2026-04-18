<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Navbar from '@/Components/Tenant/Navbar.vue';
import Toast from '@/Components/Toast.vue';
import LoadingOverlay from '@/Components/LoadingOverlay.vue';
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

const secondaryNavItems = ref([
    { name: 'Overview', href: 'dashboard' },

    {
        name: 'Sales',
        children: [
            { name: 'Opportunities', href: 'opportunities.index' },
            { name: 'Estimates', href: 'estimates.index' },
            { name: 'Contracts', href: 'contracts.index' },
            { name: 'Transactions', href: 'transactions.index' },
            { name: 'Invoices', href: 'invoices.index' },
            { name: 'Payments', href: 'payments.index' }
        ]
    },
    {
        name: 'Reports',
        children: [
            {
                name: 'Financial',
                children: [
                    { name: 'Profit & Loss', href: 'reports.pnl' },
                    { name: 'Balance Sheet', href: 'reports.balance-sheet' },
                    { name: 'Cash Flow', href: 'reports.cash-flow' },
                    { name: 'Sales Tax Liability', href: 'reports.sales-tax-liability' },
                    { name: 'Sales Tax Payable', href: 'reports.sales-tax-payable' }
                ]
            },
            {
                name: 'Sales',
                children: [
                    { name: 'Sales by Customer', href: 'reports.sales-by-customer' },
                    { name: 'Sales by Item (Summary)', href: 'reports.sales-by-item-summary' },
                    { name: 'Sales by Item (Detail)', href: 'reports.sales-by-item-detail' }
                ]
            }
        ]
    },
    {
        name: 'Operations',
        children: [
            { name: 'Service Tickets', href: 'servicetickets.index' },
            { name: 'Work Orders', href: 'workorders.index' },
            {
                name: 'Deliveries',
                children: [
                    { name: 'All Deliveries', href: 'deliveries.index' },
                    { name: 'Common Locations', href: 'delivery-locations.index' },
                    { name: 'Templates', href: 'delivery-checklist-templates.index' }
                ]
            },
            { name: 'Qualifications', href: 'qualifications.index' }
        ]
    },

    {
        name: 'Inventory',
        children: [
            { name: 'Assets', href: 'assets.index',
                children: [
                    { name: 'All Assets', href: 'assets.index' },
                    { name: 'Asset Brands', href: 'boatmakes.index' },
                    { name: 'Asset Specifications', href: 'asset-specs.index' },
                ] 
            },
            { name: 'Parts & Accessories', href: 'inventoryitems.index' },
            { name: 'Service Items', href: 'serviceitems.index' }
        ]
    },

    {
        name: 'Relationships',
        children: [
            { name: 'Contacts', href: 'contacts.index' },
            { name: 'Leads', href: 'leads.index' },
            { name: 'Customers', href: 'customers.index' },
            { name: 'Vendors', href: 'vendors.index' },
            {
                name: 'Surveys',
                children: [
                    { name: 'All Surveys', href: 'surveysIndex' },
                    { name: 'Create', href: 'surveysCreate' },
                    { name: 'Responses', href: 'surveyResponses' }
                ]
            }
        ]
    },

    {
        name: 'Boat Shows',
        children: [
            { name: 'All Shows', href: 'boat-shows.index' },
            { name: 'Events', href: 'boat-show-events.index' },
            { name: 'Follow-up emails', href: 'boat-show-email-templates.index' }
        ]
    },

    {
        name: 'Productivity',
        children: [
            { name: 'Tasks', href: 'tasks.index' },
            { name: 'Documents', href: 'documents.index' }
        ]
    },

    {
        name: 'Account',
        href: 'account.index',
        children: [
            { name: 'Overview', href: 'account.index' },
            { name: 'Integrations', href: 'integrations' },
            { name: 'Locations', href: 'locations.index' },
            { name: 'Users', href: 'users.index' },
            { name: 'Roles', href: 'roles.index' },
            { name: 'Subsidiaries', href: 'subsidiaries.index' }
        ]
    }
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col ">
        <div class="sticky top-0 z-40">
        <!-- Global Navbar -->
        <Navbar @toggle-sidebar="toggleMobileMenu" />
        
        <!-- Desktop Secondary Navigation -->
        <nav class="hidden lg:block bg-gray-100 border-b border-gray-200 dark:bg-gray-700 dark:border-gray-800 ">
            <div class="px-4 py-2">
                <div class="flex items-center">
                    <ul class="flex items-center text-md text-gray-900 font-medium space-x-1">
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
                                    <span class="material-icons text-md ml-1 transition-transform" :class="showDropdown === item.name ? 'rotate-180' : ''">
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
    </div>

        <!-- Mobile nav slideout (hamburger in Navbar toggles this) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mobileMenuOpen"
                    class="fixed inset-0 z-[100] bg-black/50 backdrop-blur-[1px] lg:hidden"
                    aria-hidden="true"
                    @click="closeMobileMenu"
                />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <aside
                    v-if="mobileMenuOpen"
                    class="fixed left-0 top-0 z-[101] flex h-full w-[min(20rem,calc(100vw-2.5rem))] max-w-[90vw] flex-col border-r border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800 lg:hidden"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Main navigation"
                >
                        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <span class="text-lg font-semibold text-gray-900 dark:text-white">Menu</span>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="closeMobileMenu"
                            >
                                <span class="sr-only">Close menu</span>
                                <span class="material-icons text-xl leading-none">close</span>
                            </button>
                        </div>
                        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-2 py-3">
                            <template v-for="item in secondaryNavItems" :key="item.name || item.href">
                                <Link
                                    v-if="!item.children"
                                    :href="route(item.href)"
                                    class="block rounded-lg px-3 py-2 text-md font-medium transition-colors"
                                    :class="[
                                        route().current(item.href) || route().current(item.href + '.*')
                                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700',
                                    ]"
                                    @click="closeMobileMenu"
                                >
                                    {{ item.name }}
                                </Link>

                                <div v-else class="space-y-1">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-md font-medium transition-colors"
                                        :class="[
                                            hasActiveChild(item)
                                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700',
                                        ]"
                                        @click="toggleMobileItem(item.name)"
                                    >
                                        <span>{{ item.name }}</span>
                                        <span
                                            class="material-icons text-md transition-transform"
                                            :class="isMobileExpanded(item.name) ? 'rotate-180' : ''"
                                        >
                                            expand_more
                                        </span>
                                    </button>

                                    <Transition
                                        enter-active-class="transition-all duration-200 ease-out"
                                        enter-from-class="max-h-0 opacity-0"
                                        enter-to-class="max-h-[28rem] opacity-100"
                                        leave-active-class="transition-all duration-150 ease-in"
                                        leave-from-class="max-h-[28rem] opacity-100"
                                        leave-to-class="max-h-0 opacity-0"
                                    >
                                        <div
                                            v-show="isMobileExpanded(item.name)"
                                            class="ml-3 space-y-1 overflow-hidden border-l border-gray-200 pl-2 dark:border-gray-600"
                                        >
                                            <template v-for="child in item.children" :key="child.name || child.href">
                                                <Link
                                                    v-if="!child.children"
                                                    :href="route(child.href)"
                                                    class="block rounded-lg px-3 py-2 text-md transition-colors"
                                                    :class="[
                                                        route().current(child.href)
                                                            ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700',
                                                    ]"
                                                    @click="closeMobileMenu"
                                                >
                                                    {{ child.name }}
                                                </Link>

                                                <div v-else class="space-y-1">
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-md transition-colors"
                                                        :class="[
                                                            hasActiveGrandChild(child)
                                                                ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700',
                                                        ]"
                                                        @click="toggleMobileItem(child.name)"
                                                    >
                                                        <span>{{ child.name }}</span>
                                                        <span
                                                            class="material-icons text-md transition-transform"
                                                            :class="isMobileExpanded(child.name) ? 'rotate-180' : ''"
                                                        >
                                                            expand_more
                                                        </span>
                                                    </button>

                                                    <Transition
                                                        enter-active-class="transition-all duration-200 ease-out"
                                                        enter-from-class="max-h-0 opacity-0"
                                                        enter-to-class="max-h-96 opacity-100"
                                                        leave-active-class="transition-all duration-150 ease-in"
                                                        leave-from-class="max-h-96 opacity-100"
                                                        leave-to-class="max-h-0 opacity-0"
                                                    >
                                                        <div
                                                            v-show="isMobileExpanded(child.name)"
                                                            class="ml-2 space-y-1 overflow-hidden border-l border-gray-200 pl-2 dark:border-gray-600"
                                                        >
                                                            <Link
                                                                v-for="grandChild in child.children"
                                                                :key="grandChild.href"
                                                                :href="route(grandChild.href)"
                                                                class="block rounded-lg px-3 py-2 text-sm transition-colors"
                                                                :class="[
                                                                    route().current(grandChild.href)
                                                                        ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                                                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700',
                                                                ]"
                                                                @click="closeMobileMenu"
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
                </aside>
            </Transition>
        </Teleport>

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

        <!-- Global Toast Notifications -->
        <Toast />

        <!-- Global Loading Overlay -->
        <LoadingOverlay />
    </div>
</template>
