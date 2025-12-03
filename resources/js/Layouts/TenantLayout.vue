<script setup>
import { ref } from 'vue';
import Navbar from '@/Components/Tenant/Navbar.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: false
    }
});

// Secondary navigation items - customize based on your needs
const secondaryNavItems = ref([
    { name: 'Overview', href: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Contacts', href: 'contacts.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Secondary Navigation -->
        <nav class="bg-gray-100 border-b border-gray-200 dark:bg-gray-700 dark:border-gray-800">
            <div class="px-4 py-2">
                <div class="flex items-center">
                    <ul class="flex items-center text-sm text-gray-900 font-medium overflow-x-auto">
                        <li v-for="item in secondaryNavItems" :key="item.href" class="block lg:inline">
                            <Link
                                :href="route(item.href)"
                                :class="[
                                    'inline-block px-3 py-2 rounded-lg',
                                    route().current(item.href) || route().current(item.href + '.*')
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white'
                                        : 'hover:text-gray-900 hover:bg-gray-200 dark:hover:bg-gray-600 dark:text-white'
                                ]"
                            >
                                {{ item.name }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Header (Optional) -->
<!--         <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-screen-xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header> -->

        <!-- Page Content -->
        <main class=" mx-auto flex w-full h-full relative p-4">
            <slot />
        </main>
    </div>
</template>
