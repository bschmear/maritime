<script setup>
import { computed } from 'vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

const props = defineProps({
    authenticated: {
        type: Boolean,
        default: false,
    },
    buttonClass: {
        type: String,
        default: '',
    },
});

const { showManualInstall, promptInstall } = usePwaInstall();

const visible = computed(() => props.authenticated && showManualInstall.value);
</script>

<template>
    <button
        v-if="visible"
        type="button"
        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 transition duration-150 ease-in-out hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white dark:focus:ring-primary-400"
        :class="buttonClass"
        title="Install app"
        @click="promptInstall"
    >
        <span class="material-icons text-[22px] leading-none">install_mobile</span>
    </button>
</template>
