<script setup>
import Sidebar from '@/Components/Kiosk/Sidebar.vue';
</script>

<template>
    <Sidebar>
        <!-- Top Bar -->
        <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 shadow-sm dark:shadow-gray-900/50 sm:gap-x-6 sm:px-6 lg:px-8">
            <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
                <div class="flex flex-1 items-center">
                    <slot name="header" />
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-950">
            <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
                <slot />
            </div>
        </main>
    </Sidebar>
</template>
