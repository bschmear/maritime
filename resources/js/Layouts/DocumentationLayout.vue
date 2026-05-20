<script setup>
import DocumentationSidebar from '@/Components/Documentation/DocumentationSidebar.vue';
</script>

<template>
    <DocumentationSidebar>
        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-3xl px-6 py-10 lg:px-8">
                <slot />
            </div>
        </main>
    </DocumentationSidebar>
</template>
