<template>
    <Teleport to="body">
        <Transition name="loading-overlay">
            <div
                v-if="loadingOverlay.visible"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black/50 backdrop-blur-sm"
                aria-live="assertive"
                aria-busy="true"
            >
                <div class="flex flex-col items-center gap-4 rounded-2xl bg-white dark:bg-gray-800 px-10 py-8 shadow-2xl border border-gray-200 dark:border-gray-700 min-w-[220px]">
                    <!-- Spinner -->
                    <svg
                        class="w-10 h-10 animate-spin text-blue-600 dark:text-blue-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12" cy="12" r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>

                    <!-- Message -->
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 text-center">
                        {{ loadingOverlay.message }}
                    </p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
export default {
    name: 'LoadingOverlay',
    computed: {
        loadingOverlay() {
            return this.$root.loadingOverlay || { visible: false, message: '' };
        }
    }
};
</script>

<style scoped>
.loading-overlay-enter-active,
.loading-overlay-leave-active {
    transition: opacity 0.2s ease;
}

.loading-overlay-enter-from,
.loading-overlay-leave-to {
    opacity: 0;
}
</style>
