<script setup>
import { computed, useSlots } from 'vue';
import { useMobileActionBar } from '@/composables/useMobileActionBar';

const props = defineProps({
    enabled: {
        type: Boolean,
        default: true,
    },
});

const slots = useSlots();
const { isPwa, barVisibilityClass } = useMobileActionBar();

const hasActions = computed(() => Boolean(slots.actions));
const showBar = computed(() => props.enabled && hasActions.value);
</script>

<template>
    <div
        :class="{
            'mobile-action-bar-content': showBar,
            'mobile-action-bar-content--pwa': showBar && isPwa,
        }"
    >
        <slot />
    </div>

    <Teleport v-if="showBar" to="body">
        <nav
            :class="[
                'fixed inset-x-0 bottom-0 z-50 flex border-t border-black/[0.08] bg-[#f9f9f9]/80 backdrop-blur-xl dark:border-white/[0.08] dark:bg-[#1c1c1e]/80',
                barVisibilityClass,
                'pb-[env(safe-area-inset-bottom,0px)]',
            ]"
            aria-label="Page actions"
        >
            <slot name="actions" />
        </nav>
    </Teleport>
</template>

<style scoped>
.mobile-action-bar-content {
    padding-bottom: calc(49px + env(safe-area-inset-bottom, 0px));
}

@media (min-width: 704px) {
    .mobile-action-bar-content:not(.mobile-action-bar-content--pwa) {
        padding-bottom: 0;
    }
}
</style>
