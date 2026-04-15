<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { reportRouteGroups } from '@/Constants/reportRoutes';

const props = defineProps({
    currentRouteName: {
        type: String,
        required: true,
    },
});

const selectedRoute = computed(() => props.currentRouteName);

const switchReport = (routeName) => {
    if (!routeName || routeName === props.currentRouteName) {
        return;
    }

    router.visit(route(routeName));
};
</script>

<template>
    <select
        id="report-selector"
        :value="selectedRoute"
        class="block w-full min-w-[200px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
        @change="switchReport($event.target.value)"
    >
        <optgroup v-for="group in reportRouteGroups" :key="group.label" :label="group.label">
            <option v-for="option in group.options" :key="option.route" :value="option.route">
                {{ option.label }}
            </option>
        </optgroup>
    </select>
</template>
