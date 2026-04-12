<script setup>
import { ref, onMounted } from 'vue';

const emit = defineEmits(['toggle-sidebar']);
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import NotificationDropdown from '@/Components/Tenant/NotificationDropdown.vue';
import axios from 'axios';

const page = usePage();
const showingNavigationDropdown = ref(false);
const userDropdownOpen = ref(false);
const notificationDropdownOpen = ref(false);
const notifications = ref([]);

const { theme, setTheme, initTheme } = useTheme();

const cycleTheme = () => {
    const themes = ['light', 'dark', 'auto'];
    const currentIndex = themes.indexOf(theme.value);
    const nextIndex = (currentIndex + 1) % themes.length;
    setTheme(themes[nextIndex]);
};

// Cache key for notifications
const NOTIFICATIONS_CACHE_KEY = 'tenant_notifications';
const NOTIFICATIONS_CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

const fetchNotifications = async () => {
    try {
        // Check cache first
        const cached = getCachedNotifications();
        if (cached) {
            notifications.value = cached;
            return;
        }

        // Clear any potentially corrupted cache
        localStorage.removeItem(NOTIFICATIONS_CACHE_KEY);

        const response = await axios.get(route('notifications.index'));

        notifications.value = response.data.data;

        // Cache the results
        cacheNotifications(response.data.data);
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
        notifications.value = [];
    }
};

const getCachedNotifications = () => {
    try {
        const cached = localStorage.getItem(NOTIFICATIONS_CACHE_KEY);
        if (!cached) return null;

        const parsed = JSON.parse(cached);
        const now = Date.now();

        // Check if cache is still valid
        if (now - parsed.timestamp > NOTIFICATIONS_CACHE_DURATION) {
            localStorage.removeItem(NOTIFICATIONS_CACHE_KEY);
            return null;
        }

        // Handle both old format (paginated object) and new format (array)
        if (Array.isArray(parsed.data)) {
            return parsed.data; // New format - already an array
        } else if (parsed.data && typeof parsed.data === 'object' && parsed.data.data) {
            return parsed.data.data; // Old format - extract array from paginated response
        }

        return null;
    } catch (error) {
        localStorage.removeItem(NOTIFICATIONS_CACHE_KEY);
        return null;
    }
};

const cacheNotifications = (data) => {
    try {
        const cacheData = {
            data,
            timestamp: Date.now()
        };
        localStorage.setItem(NOTIFICATIONS_CACHE_KEY, JSON.stringify(cacheData));
    } catch (error) {
        // Ignore localStorage errors
    }
};

const handleMarkAsRead = async (id) => {
    try {
        await axios.post(route('notifications.read', id));

        // Update local state
        const notification = notifications.value.find(n => n.id === id);
        if (notification) {
            notification.read_at = new Date().toISOString();
        }

        // Update cache
        cacheNotifications(notifications.value);
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

const handleMarkAllRead = async () => {
    try {
        await axios.post(route('notifications.markAllRead'));

        // Update local state
        notifications.value.forEach(n => {
            n.read_at = new Date().toISOString();
        });

        // Update cache
        cacheNotifications(notifications.value);
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    }
};

const handleDismiss = async (id) => {
    try {
        await axios.delete(route('notifications.destroy', id));

        // Update local state
        notifications.value = notifications.value.filter(n => n.id !== id);

        // Update cache
        cacheNotifications(notifications.value);
    } catch (error) {
        console.error('Failed to dismiss notification:', error);
    }
};

const handleNotificationClick = (notification) => {
    // Close the dropdown
    notificationDropdownOpen.value = false;

    // Redirect to the notification's route
    if (notification.route && notification.route_params) {
        router.visit(route(notification.route, notification.route_params));
    } else if (notification.route) {
        router.visit(route(notification.route));
    }
};

// Method to refresh notifications (can be called externally if needed)
const refreshNotifications = () => {
    // Clear cache to force fresh fetch
    localStorage.removeItem(NOTIFICATIONS_CACHE_KEY);
    fetchNotifications();
};

// Watch for notification dropdown opening to refresh if cache is old
const openNotificationDropdown = () => {
    notificationDropdownOpen.value = true;
    // Refresh notifications when opening dropdown if cache is older than 1 minute
    const cached = getCachedNotifications();
    if (!cached || (Date.now() - JSON.parse(localStorage.getItem(NOTIFICATIONS_CACHE_KEY)).timestamp > 60 * 1000)) {
        refreshNotifications();
    }
};

onMounted(() => {
    // Clear old notification cache format
    localStorage.removeItem(NOTIFICATIONS_CACHE_KEY);

    initTheme();
    fetchNotifications();
});
</script>

<template>
    <header >
        <nav class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center ">
                    <!-- Logo -->
                    <Link :href="route('dashboard')" class="block mr-6">
                        <ApplicationLogo class="mr-3 h-8 fill-current text-gray-800 dark:text-white" />
                    </Link>

                    <!-- Desktop Search -->
                    <form action="#" method="get" class="hidden md:block">
                        <div class="flex">
                            <div class="relative w-full">
                                <input
                                    type="search"
                                    class="block p-2.5 w-full md:w-72 xl:w-96 text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:border-blue-500"
                                    placeholder="Search..."
                                />
                                <button
                                    type="submit"
                                    class="absolute top-0 right-0 p-2.5 text-sm font-medium text-white bg-blue-700 rounded-r-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    <span class="sr-only">Search</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="flex items-center justify-center">
                    <!-- Theme Toggle -->
                    <button
                        @click="cycleTheme"
                        type="button"
                        class="inline-flex items-center p-2 mr-1 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
                        :title="theme === 'auto' ? 'Theme: Auto' : theme === 'dark' ? 'Theme: Dark' : 'Theme: Light'"
                    >
                        <span class="material-icons text-[24px]">
                            {{ theme === 'dark' ? 'dark_mode' : theme === 'light' ? 'light_mode' : 'brightness_auto' }}
                        </span>
                    </button>
                    <!-- Mobile Search Button -->
                    <!-- <button
                        type="button"
                        class="md:hidden p-2 mr-1 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
                    >
                        <span class="sr-only">Search</span>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button> -->

                    <!-- Notifications -->
                    <NotificationDropdown
                        :open="notificationDropdownOpen"
                        :notifications="notifications"
                        @close="notificationDropdownOpen = false"
                        @mark-as-read="handleMarkAsRead"
                        @mark-all-read="handleMarkAllRead"
                        @dismiss="handleDismiss"
                        @notification-click="handleNotificationClick"
                    >
                        <template #trigger="{ unreadCount }">
                            <button
                                type="button"
                                @click="notificationDropdownOpen ? (notificationDropdownOpen = false) : openNotificationDropdown()"
                                class="relative p-2 mr-1 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-100
                                    dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700
                                    focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
                            >
                                <span class="sr-only">View notifications</span>
                                <span class="material-icons text-2xl text-gray-500 dark:text-gray-400">notifications</span>
                                <!-- Badge -->
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center
                                        rounded-full bg-primary-500 text-[10px] font-bold text-white"
                                >{{ unreadCount }}</span>
                            </button>
                        </template>
                    </NotificationDropdown>
                    <!-- User Dropdown Button -->
                    <button
                        type="button"
                        @click="userDropdownOpen = !userDropdownOpen"
                        class="flex text-sm bg-gray-800 rounded-full md:me-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600 ml-2"
                    >
                        <span class="sr-only">Open user menu</span>
                        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-medium">
                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </div>
                    </button>

                    <!-- User Dropdown Menu -->
                    <div
                        v-show="userDropdownOpen"
                        class="absolute right-0  top-16 z-50 my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow dark:bg-gray-700 dark:divide-gray-600"
                    >
                        <div class="px-4 py-3">
                            <span class="block text-sm text-gray-900 dark:text-white">{{ $page.props.auth.user.name }}</span>
                            <span class="block text-sm text-gray-500 truncate dark:text-gray-400">{{ $page.props.auth.user.email }}</span>
                        </div>
                        <ul class="py-2">
                            <li>
                                <Link
                                    :href="route('profile.edit')"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white"
                                >
                                    Profile
                                </Link>
                            </li>
                            <li>
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white"
                                >
                                    Log out
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Mobile nav slideout (handled in TenantLayout) -->
                    <button
                        type="button"
                        @click="emit('toggle-sidebar')"
                        class="inline-flex items-center justify-center p-2 text-gray-500 rounded-lg lg:hidden hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>
    </header>
</template>
