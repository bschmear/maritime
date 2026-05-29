<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    siteKey: {
        type: String,
        required: true,
    },
});

const model = defineModel({
    type: String,
    default: '',
});

const containerRef = ref(null);
let widgetId = null;
let scriptEl = null;

function renderWidget() {
    if (!containerRef.value || !window.turnstile) {
        return;
    }

    if (widgetId !== null) {
        window.turnstile.remove(widgetId);
        widgetId = null;
    }

    widgetId = window.turnstile.render(containerRef.value, {
        sitekey: props.siteKey,
        theme: 'auto',
        callback: (token) => {
            model.value = token;
        },
        'expired-callback': () => {
            model.value = '';
        },
        'error-callback': () => {
            model.value = '';
        },
    });
}

onMounted(() => {
    if (window.turnstile) {
        renderWidget();

        return;
    }

    scriptEl = document.createElement('script');
    scriptEl.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    scriptEl.async = true;
    scriptEl.defer = true;
    scriptEl.onload = () => renderWidget();
    document.head.appendChild(scriptEl);
});

onUnmounted(() => {
    if (widgetId !== null && window.turnstile?.remove) {
        window.turnstile.remove(widgetId);
    }
});
</script>

<template>
    <div ref="containerRef" class="flex min-h-[65px] justify-center" />
</template>
