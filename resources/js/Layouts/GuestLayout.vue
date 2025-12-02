<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <!-- Global Navbar -->
        <Navbar />

        <!-- Guest Content Area -->
        <div class="flex-grow flex flex-col">
            <slot />
        </div>

        <!-- Global Footer -->
        <Footer />
    </div>
</template>
