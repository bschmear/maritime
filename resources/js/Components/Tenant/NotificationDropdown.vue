<!--
    Requires @mdi/font installed and imported globally, e.g.:
    npm install @mdi/font
    Then in your main.js or app.css:
    import '@mdi/font/css/materialdesignicons.min.css'
-->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },
    notifications: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'markAsRead', 'markAllRead', 'dismiss', 'notificationClick']);

const localNotifications = ref([...props.notifications]);

watch(() => props.notifications, (newVal) => {
    localNotifications.value = [...newVal];
}, { deep: true });

const unreadCount = computed(() => localNotifications.value.filter(n => !n.read_at).length);

const markAsRead = (id) => {
    const n = localNotifications.value.find(n => n.id === id);
    if (n && !n.read_at) {
        emit('markAsRead', id);
        n.read_at = new Date().toISOString();
    }
};

const markAllRead = () => {
    const unreadIds = localNotifications.value.filter(n => !n.read_at).map(n => n.id);
    if (unreadIds.length > 0) {
        emit('markAllRead');
        localNotifications.value.forEach(n => {
            if (!n.read_at) n.read_at = new Date().toISOString();
        });
    }
};

const dismiss = (id) => {
    emit('dismiss', id);
    localNotifications.value = localNotifications.value.filter(n => n.id !== id);
};

const handleNotificationClick = (notification) => {
    // Mark as read first
    markAsRead(notification.id);
    // Then emit click event with notification data
    emit('notificationClick', notification);
};

// Close on outside click
const dropdownRef = ref(null);
const handleOutsideClick = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        emit('close');
    }
};

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
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));
</script>

<template>
    <!-- Badge wrapper — place this around your bell button -->
    <div class="relative" ref="dropdownRef">
        <slot name="trigger" :unread-count="unreadCount" />

        <!-- Dropdown Panel -->
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
                class="absolute right-0 mt-2 w-[22rem] origin-top-right z-50
                       bg-white dark:bg-gray-800
                       border border-gray-200 dark:border-gray-700
                       rounded-2xl shadow-2xl shadow-gray-200/60 dark:shadow-black/40
                       overflow-hidden"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight">Notifications</h3>
                        <span
                            v-if="unreadCount > 0"
                            class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5
                                   text-[11px] font-bold text-white bg-indigo-500 rounded-full leading-none"
                        >{{ unreadCount }}</span>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllRead"
                        class="text-xs text-indigo-500 hover:text-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300
                               font-medium transition-colors"
                    >
                        Mark all read
                    </button>
                </div>

                <!-- Notification List -->
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
                            @click="handleNotificationClick(n)"
                            class="group relative flex gap-3 px-4 py-3.5 cursor-pointer
                                   hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            :class="{ 'bg-indigo-50/60 dark:bg-indigo-900/10': !n.read_at }"
                        >
                            <!-- Unread dot -->
                            <span
                                v-if="!n.read_at"
                                class="absolute left-2 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-indigo-500"
                            />

                            <!-- Icon based on notification type -->
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                                    <span v-if="n.type === 'service_ticket_approved'" class="material-icons text-lg text-blue-500">assignment_turned_in</span>
                                    <span v-else class="material-icons text-lg text-blue-500">notifications</span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white font-medium leading-snug mb-0.5">
                                    {{ n.title }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ n.message }}</p>
                                <p class="text-[11px] text-indigo-400 dark:text-indigo-500 mt-1 font-medium">
                                    {{ formatTimeAgo(n.created_at) }}
                                </p>
                            </div>

                            <!-- Dismiss button -->
                            <button
                                @click.stop="dismiss(n.id)"
                                class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity
                                       mt-0.5 p-1 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-400 dark:text-gray-500"
                                title="Dismiss"
                            >
                                <span class="material-icons text-sm leading-none">close</span>
                            </button>
                        </div>
                    </TransitionGroup>

                    <!-- Empty state -->
                    <div
                        v-if="localNotifications.length === 0"
                        class="flex flex-col items-center justify-center py-12 text-center px-4"
                    >
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-3">
                            <span class="material-icons text-2xl text-gray-400">notifications_off</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">All caught up!</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">No new notifications right now.</p>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    v-if="localNotifications.length > 0"
                    class="border-t border-gray-100 dark:border-gray-700 px-4 py-2.5"
                >
                    <a
                        href="#"
                        class="flex items-center justify-center gap-1.5 text-xs font-medium text-indigo-500
                               hover:text-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                    >
                        View all notifications
                        <span class="material-icons text-sm leading-none">arrow_forward</span>
                    </a>
                </div>
            </div>
        </Transition>
    </div>
</template>