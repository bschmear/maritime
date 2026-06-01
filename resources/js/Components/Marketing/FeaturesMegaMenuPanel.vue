<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { featuresMegaMenuGroups } from '@/data/marketingFeatures';

const props = defineProps({
    onNavigate: {
        type: Function,
        default: null,
    },
});

const groups = computed(() =>
    featuresMegaMenuGroups.map((group) => ({
        ...group,
        items: group.items.filter(
            (item) => !item.routeName || route().has(item.routeName),
        ),
    })).filter((group) => group.items.length > 0),
);

const featureHref = (item) => {
    if (item.routeName && route().has(item.routeName)) {
        return route(item.routeName);
    }
    if (route().has('features')) {
        return route('features');
    }

    return '#';
};

const handleClick = () => {
    props.onNavigate?.();
};
</script>

<template>
    <div
        class="w-full border-t border-b border-gray-200 bg-white shadow-md dark:border-gray-700 dark:bg-gray-900"
        role="region"
        aria-label="Features menu"
    >
        <div class="grid w-full gap-0 px-4 py-4 text-gray-900 dark:text-white md:grid-cols-2 md:px-6 lg:grid-cols-5 lg:px-8">
            <ul
                v-for="group in groups"
                :key="group.title"
                class="min-w-0"
            >
                <li class="px-1 pb-2 pt-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ group.title }}
                    </span>
                </li>
                <li v-for="item in group.items" :key="item.title">
                    <Link
                        :href="featureHref(item)"
                        class="flex rounded-lg p-3 transition hover:bg-gray-50 dark:hover:bg-gray-800"
                        @click="handleClick"
                    >
                        <span class="material-icons mr-2 w-6 shrink-0 text-xl leading-none text-primary-600 dark:text-primary-400">
                            chevron_right
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 font-semibold whitespace-normal">
                                <span class="material-icons text-lg leading-none text-gray-500 dark:text-gray-400">{{ item.icon }}</span>
                                <span>{{ item.title }}</span>
                            </div>
                            <span class="mt-0.5 block text-sm font-light whitespace-normal text-gray-500 dark:text-gray-400">
                                {{ item.description }}
                            </span>
                        </div>
                    </Link>
                </li>
            </ul>

            <div
                v-if="route().has('features')"
                class="col-span-2 min-w-0 border-t border-gray-100 p-4 md:col-span-1 md:border-t-0 md:border-l md:pl-6 lg:col-span-1 dark:border-gray-700"
            >
                <h2 class="mb-2 font-semibold whitespace-normal text-gray-900 dark:text-white">All features</h2>
                <p class="mb-3 text-sm font-light whitespace-normal text-gray-500 dark:text-gray-400">
                    Explore the full Helmful platform—from leads and inventory to boat shows, deliveries, and integrations.
                </p>
                <Link
                    :href="route('features')"
                    class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    @click="handleClick"
                >
                    View all features
                    <span class="material-icons ml-1 text-base leading-none">arrow_forward</span>
                </Link>
                <Link
                    v-if="route().has('checkout.plans')"
                    :href="route('checkout.plans')"
                    class="mt-3 inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    @click="handleClick"
                >
                    See pricing
                    <span class="material-icons ml-1 text-base leading-none">arrow_forward</span>
                </Link>
            </div>
        </div>
    </div>
</template>
