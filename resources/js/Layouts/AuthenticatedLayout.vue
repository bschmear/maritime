<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Page Heading -->
        <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow dark:shadow-gray-900/50 border-b border-gray-100 dark:border-gray-700">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-grow  flex flex-col">
            <slot />
        </div>

        <!-- Global Footer -->
        <Footer />
    </div>
</template>
