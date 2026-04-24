<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const pwa = computed(() => Boolean(usePage().props.pwa));
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <!-- Global Navbar -->
        <Navbar v-if="!pwa" />

        <!-- Guest Content Area -->
        <div class="flex-grow flex flex-col">
            <slot />
        </div>

        <!-- Global Footer -->
        <Footer v-if="!pwa" />
    </div>
</template>
