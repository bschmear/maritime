<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const showSlot = ref(props.show);

/** Cancels a pending close timeout from a previous `show === false` run (avoids stale close after rapid open). */
let closeTimer = null;

const clearCloseTimer = () => {
    if (closeTimer !== null) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            clearCloseTimer();
            document.body.style.overflow = 'hidden';
            showSlot.value = true;
        } else {
            document.body.style.overflow = '';
            clearCloseTimer();
            closeTimer = window.setTimeout(() => {
                closeTimer = null;
                showSlot.value = false;
            }, 200);
        }
    },
    { immediate: true },
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    clearCloseTimer();
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '4xl': 'sm:max-w-4xl',
        '6xl': 'sm:max-w-6xl',
    }[props.maxWidth];
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showSlot"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden p-4"
            role="dialog"
            aria-modal="true"
        >
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80"
                    @click="close"
                />
            </Transition>

            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    class="relative w-full max-h-[90vh] flex flex-col transform rounded-lg bg-white shadow-xl transition-all dark:bg-gray-800"
                    :class="maxWidthClass"
                    @click.stop
                >
                    <slot />
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
