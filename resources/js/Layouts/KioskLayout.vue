<script setup>
import Sidebar from '@/Components/Kiosk/Sidebar.vue';
</script>

<template>
    <Sidebar>
        <!-- Top Bar -->
        <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-800 dark:bg-black sm:gap-x-6 sm:px-6 lg:px-8">
            <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
                <div class="flex flex-1 items-center">
                    <slot name="header" />
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-black">
            <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
                <slot />
            </div>
        </main>
    </Sidebar>
</template>
