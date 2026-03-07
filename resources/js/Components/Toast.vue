<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-50 space-y-2 max-w-sm">
            <TransitionGroup name="toast" tag="div">
                <div
                    v-for="(toast, index) in toasts"
                    :key="index"
                    :class="[
                        'flex items-center p-4 text-sm rounded-lg shadow-lg border transition-all duration-300',
                        toastTypeClasses(toast.type)
                    ]"
                >
                    <!-- Icon -->
                    <div class="flex-shrink-0 mr-3">
                        <span :class="toastIconClasses(toast.type)"></span>
                    </div>

                    <!-- Message -->
                    <div class="flex-1 font-medium">
                        {{ toast.message }}
                    </div>

                    <!-- Close button -->
                    <button
                        @click="dismissToast(index)"
                        class="ml-3 flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
                        aria-label="Close"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script>
export default {
    name: 'Toast',
    computed: {
        toasts() {
            return this.$root.toasts || [];
        }
    },
    methods: {
        dismissToast(index) {
            this.$root.dismissToast(index);
        },
        toastTypeClasses(type) {
            const classes = {
                success: 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300',
                error: 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
                warning: 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-yellow-300',
                info: 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300'
            };
            return classes[type] || classes.info;
        },
        toastIconClasses(type) {
            const classes = {
                success: 'material-icons text-green-600 dark:text-green-400',
                error: 'material-icons text-red-600 dark:text-red-400',
                warning: 'material-icons text-yellow-600 dark:text-yellow-400',
                info: 'material-icons text-blue-600 dark:text-blue-400'
            };
            const iconClass = classes[type] || classes.info;

            return `${iconClass} text-lg`;
        }
    }
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-move {
    transition: transform 0.3s ease;
}
</style>