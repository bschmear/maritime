<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col ">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Page Heading (Optional) -->
        <header v-if="$slots.header" class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-grow bg-gray-100  dark:bg-gray-900">
            <slot />
        </main>
        <Footer />
    </div>
</template>

