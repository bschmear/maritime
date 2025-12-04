<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
        validator: (items) => {
            return items.every(item => 
                typeof item === 'object' && 
                item !== null && 
                'label' in item
            );
        },
    },
});

const isLastItem = (index) => {
    return index === props.items.length - 1;
};
</script>

<template>
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
            <li 
                v-for="(item, index) in items" 
                :key="index"
                class="inline-flex items-center"
                :aria-current="isLastItem(index) ? 'page' : undefined"
            >
                <!-- First item with home icon -->
                <template v-if="index === 0">
                    <Link 
                        v-if="item.href"
                        :href="item.href"
                        class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white"
                    >
                        <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                        </svg>
                        {{ item.label }}
                    </Link>
                    <span 
                        v-else
                        class="inline-flex items-center text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
                        </svg>
                        {{ item.label }}
                    </span>
                </template>

                <!-- Middle and last items -->
                <template v-else>
                    <div class="flex items-center">
                        <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                        </svg>
                        <Link 
                            v-if="item.href && !isLastItem(index)"
                            :href="item.href"
                            class="ms-1 text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white md:ms-2"
                        >
                            {{ item.label }}
                        </Link>
                        <span 
                            v-else
                            class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2"
                        >
                            {{ item.label }}
                        </span>
                    </div>
                </template>
            </li>
        </ol>
    </nav>
</template>

