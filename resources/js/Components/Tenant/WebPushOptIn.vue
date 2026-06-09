<script setup>
import { computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useWebPush } from '@/composables/useWebPush';

const page = usePage();

const {
    isSupported,
    isSubscribed,
    isLoading,
    errorMessage,
    serverEnabled,
    canPrompt,
    detectSupport,
    refreshStatus,
    subscribe,
    dismissPrompt,
} = useWebPush();

const MANAGER_ROLES = new Set(['manager', 'administrator']);

const shouldShow = computed(() => {
    const role = page.props.tenant_role_slug;
    return MANAGER_ROLES.has(role) && canPrompt.value;
});

onMounted(async () => {
    detectSupport();
    if (serverEnabled.value) {
        await refreshStatus();
    }
});

const handleEnable = async () => {
    await subscribe();
};

const handleDismiss = () => {
    dismissPrompt();
};
</script>

<template>
    <div
        v-if="shouldShow"
        class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-900/25 dark:text-blue-100"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-medium">Enable push notifications</p>
                <p class="mt-1 text-blue-800 dark:text-blue-200">
                    Get alerted on this device when a work order is submitted for your approval.
                    <span v-if="!isSupported" class="block mt-1 text-xs text-blue-700 dark:text-blue-300">
                        Push requires a supported browser and an installed PWA on iOS.
                    </span>
                </p>
                <p v-if="errorMessage" class="mt-2 text-xs text-red-700 dark:text-red-300">
                    {{ errorMessage }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-sm font-medium text-blue-800 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-100 dark:hover:bg-blue-900"
                    @click="handleDismiss"
                >
                    Not now
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="isLoading || !isSupported"
                    @click="handleEnable"
                >
                    <span v-if="isLoading" class="material-icons animate-spin text-[16px]">autorenew</span>
                    Enable
                </button>
            </div>
        </div>
    </div>

    <div
        v-else-if="MANAGER_ROLES.has(page.props.tenant_role_slug) && isSubscribed"
        class="hidden"
        aria-hidden="true"
    />
</template>
