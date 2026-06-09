<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    notifications: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'markAsRead', 'markAllRead', 'dismiss', 'notificationClick']);

const localNotifications = ref([...props.notifications]);

watch(() => props.notifications, (newVal) => {
    localNotifications.value = [...newVal];
}, { deep: true });

const unreadCount = computed(() => localNotifications.value.filter((n) => !n.read_at).length);

const markAsRead = (id) => {
    const n = localNotifications.value.find((item) => item.id === id);
    if (n && !n.read_at) {
        emit('markAsRead', id);
        n.read_at = new Date().toISOString();
    }
};

const markAllRead = () => {
    const unreadIds = localNotifications.value.filter((n) => !n.read_at).map((n) => n.id);
    if (unreadIds.length > 0) {
        emit('markAllRead');
        localNotifications.value.forEach((n) => {
            if (!n.read_at) {
                n.read_at = new Date().toISOString();
            }
        });
    }
};

const dismiss = (id) => {
    emit('dismiss', id);
    localNotifications.value = localNotifications.value.filter((n) => n.id !== id);
};

const handleNotificationClick = (notification) => {
    markAsRead(notification.id);
    emit('notificationClick', notification);
};

const dropdownRef = ref(null);

const isMobileViewport = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 767px)').matches;
};

const handleOutsideClick = (e) => {
    if (isMobileViewport()) {
        return;
    }

    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        emit('close');
    }
};

const syncBodyScrollLock = (isOpen) => {
    if (typeof document === 'undefined' || !isMobileViewport()) {
        return;
    }

    document.body.style.overflow = isOpen ? 'hidden' : '';
};

watch(() => props.open, (isOpen) => {
    syncBodyScrollLock(isOpen);
});

const formatTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;

    return date.toLocaleDateString();
};

onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onUnmounted(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
    document.body.style.overflow = '';
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <slot name="trigger" :unread-count="unreadCount" />

        <!-- Desktop dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-show="open"
                class="absolute right-0 top-full z-50 mt-2 hidden w-[22rem] origin-top-right overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl shadow-gray-200/60 dark:border-gray-700 dark:bg-gray-800 dark:shadow-black/40 md:block"
            >
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Notifications</h3>
                        <span
                            v-if="unreadCount > 0"
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] font-bold leading-none text-white"
                        >{{ unreadCount }}</span>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-primary-500 transition-colors hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                        @click="markAllRead"
                    >
                        Mark all read
                    </button>
                </div>

                <div class="max-h-[26rem] overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/60">
                    <TransitionGroup
                        enter-active-class="transition-all duration-300"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200"
                        leave-from-class="opacity-100 max-h-28"
                        leave-to-class="opacity-0 max-h-0 overflow-hidden"
                    >
                        <div
                            v-for="n in localNotifications"
                            :key="n.id"
                            class="group relative flex cursor-pointer gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="{ 'bg-primary-50/60 dark:bg-primary-900/10': !n.read_at }"
                            @click="handleNotificationClick(n)"
                        >
                            <span
                                v-if="!n.read_at"
                                class="absolute left-2 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-primary-500"
                            />
                            <div class="mt-0.5 flex-shrink-0">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                                    <span v-if="n.type === 'service_ticket_approved'" class="material-icons text-lg text-blue-500">assignment_turned_in</span>
                                    <span v-else class="material-icons text-lg text-blue-500">notifications</span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="mb-0.5 text-sm font-medium leading-snug text-gray-900 dark:text-white">{{ n.title }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ n.message }}</p>
                                <p class="mt-1 text-[11px] font-medium text-primary-400 dark:text-primary-500">{{ formatTimeAgo(n.created_at) }}</p>
                            </div>
                            <button
                                type="button"
                                class="mt-0.5 flex-shrink-0 rounded-md p-1 text-gray-400 opacity-0 transition-opacity hover:bg-gray-200 group-hover:opacity-100 dark:text-gray-500 dark:hover:bg-gray-600"
                                title="Dismiss"
                                @click.stop="dismiss(n.id)"
                            >
                                <span class="material-icons text-sm leading-none">close</span>
                            </button>
                        </div>
                    </TransitionGroup>
                    <div
                        v-if="localNotifications.length === 0"
                        class="flex flex-col items-center justify-center px-4 py-12 text-center"
                    >
                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                            <span class="material-icons text-2xl text-gray-400">notifications_off</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">All caught up!</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">No new notifications right now.</p>
                    </div>
                </div>

                <div
                    v-if="localNotifications.length > 0"
                    class="border-t border-gray-100 px-4 py-2.5 dark:border-gray-700"
                >
                    <a
                        href="#"
                        class="flex items-center justify-center gap-1.5 text-xs font-medium text-primary-500 transition-colors hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        View all notifications
                        <span class="material-icons text-sm leading-none">arrow_forward</span>
                    </a>
                </div>
            </div>
        </Transition>

        <!-- Mobile full-screen overlay -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-[110] flex h-[100dvh] w-full flex-col overflow-hidden bg-white dark:bg-gray-800 md:hidden"
                >
                    <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-4 py-3.5 dark:border-gray-700">
                        <div class="flex min-w-0 items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                aria-label="Close notifications"
                                @click="emit('close')"
                            >
                                <span class="material-icons text-[22px]">close</span>
                            </button>
                            <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">Notifications</h3>
                            <span
                                v-if="unreadCount > 0"
                                class="inline-flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[11px] font-bold leading-none text-white"
                            >{{ unreadCount }}</span>
                        </div>
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="shrink-0 text-xs font-medium text-primary-500 transition-colors hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                            @click="markAllRead"
                        >
                            Mark all read
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/60">
                        <div
                            v-for="n in localNotifications"
                            :key="'mobile-' + n.id"
                            class="group relative flex cursor-pointer gap-3 px-4 py-4 transition-colors active:bg-gray-50 dark:active:bg-gray-700/50"
                            :class="{ 'bg-primary-50/60 dark:bg-primary-900/10': !n.read_at }"
                            @click="handleNotificationClick(n)"
                        >
                            <span
                                v-if="!n.read_at"
                                class="absolute left-2 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-primary-500"
                            />
                            <div class="mt-0.5 flex-shrink-0">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                                    <span v-if="n.type === 'service_ticket_approved'" class="material-icons text-lg text-blue-500">assignment_turned_in</span>
                                    <span v-else class="material-icons text-lg text-blue-500">notifications</span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="mb-0.5 text-sm font-medium leading-snug text-gray-900 dark:text-white">{{ n.title }}</p>
                                <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ n.message }}</p>
                                <p class="mt-1 text-[11px] font-medium text-primary-400 dark:text-primary-500">{{ formatTimeAgo(n.created_at) }}</p>
                            </div>
                            <button
                                type="button"
                                class="mt-0.5 flex-shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-gray-200 dark:text-gray-500 dark:hover:bg-gray-600"
                                title="Dismiss"
                                @click.stop="dismiss(n.id)"
                            >
                                <span class="material-icons text-base leading-none">close</span>
                            </button>
                        </div>

                        <div
                            v-if="localNotifications.length === 0"
                            class="flex flex-col items-center justify-center px-4 py-16 text-center"
                        >
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                                <span class="material-icons text-2xl text-gray-400">notifications_off</span>
                            </div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">All caught up!</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">No new notifications right now.</p>
                        </div>
                    </div>

                    <div
                        v-if="localNotifications.length > 0"
                        class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700"
                    >
                        <a
                            href="#"
                            class="flex items-center justify-center gap-1.5 text-xs font-medium text-primary-500 transition-colors hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            View all notifications
                            <span class="material-icons text-sm leading-none">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
