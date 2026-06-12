<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
        validator: (items) => {
            return items.every(item =>
                typeof item === 'object' &&
                item !== null &&
                'label' in item,
            );
        },
    },
});

const isLastItem = (index) => index === props.items.length - 1;

const collapseMiddleOnMobile = computed(() => props.items.length > 2);

const hideMiddleOnMobile = (index) => {
    if (!collapseMiddleOnMobile.value) {
        return false;
    }

    return index > 0 && index < props.items.length - 1;
};

const linkLabelClass = (index) => [
    'ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2',
    isLastItem(index) ? 'max-w-[10rem] truncate sm:max-w-[14rem] md:max-w-none' : 'max-w-[7rem] truncate md:max-w-none',
];

const currentLabelClass = (index) => [
    'ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2',
    isLastItem(index) ? 'max-w-[10rem] truncate sm:max-w-[14rem] md:max-w-none' : 'max-w-[7rem] truncate md:max-w-none',
];

const homeLinkClass =
    'inline-flex min-w-0 max-w-full items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white';

const homeTextClass = 'hidden min-w-0 truncate sm:inline sm:max-w-[6rem] md:max-w-none';
</script>

<template>
    <nav class="flex min-w-0 max-w-full" aria-label="Breadcrumb">
        <ol class="inline-flex min-w-0 max-w-full items-center space-x-1 overflow-hidden md:space-x-2 rtl:space-x-reverse">
            <template v-for="(item, index) in items" :key="index">
            <li
                class="inline-flex min-w-0 items-center"
                :class="{ 'max-md:hidden': hideMiddleOnMobile(index) }"
                :aria-current="isLastItem(index) ? 'page' : undefined"
            >
                <!-- First item with home icon -->
                <template v-if="index === 0">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        :class="homeLinkClass"
                        :title="item.label"
                    >
                        <svg class="me-0 h-4 w-4 shrink-0 sm:me-2.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                        </svg>
                        <span :class="homeTextClass">{{ item.label }}</span>
                    </Link>
                    <span
                        v-else
                        :class="[homeLinkClass, 'text-gray-700 dark:text-gray-400']"
                        :title="item.label"
                    >
                        <svg class="me-0 h-4 w-4 shrink-0 sm:me-2.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                        </svg>
                        <span :class="homeTextClass">{{ item.label }}</span>
                    </span>
                </template>

                <!-- Middle and last items -->
                <template v-else>
                    <div class="flex min-w-0 items-center">
                        <svg class="mx-1 h-4 w-4 shrink-0 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                        </svg>
                        <Link
                            v-if="item.href && !isLastItem(index)"
                            :href="item.href"
                            :class="linkLabelClass(index)"
                            :title="item.label"
                        >
                            {{ item.label }}
                        </Link>
                        <span
                            v-else
                            :class="currentLabelClass(index)"
                            :title="item.label"
                        >
                            {{ item.label }}
                        </span>
                    </div>
                </template>
            </li>

            <!-- Collapsed middle segments on small screens (after Home) -->
            <li
                v-if="index === 0 && collapseMiddleOnMobile"
                class="inline-flex items-center md:hidden"
                aria-hidden="true"
            >
                <div class="flex items-center">
                    <svg class="mx-1 h-4 w-4 shrink-0 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                    <span class="ms-1 text-sm font-medium text-gray-400 dark:text-gray-500">…</span>
                </div>
            </li>
        </template>
        </ol>
    </nav>
</template>
