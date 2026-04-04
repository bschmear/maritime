<script setup>
defineProps({
    appName: { type: String, required: true },
    appUrl: { type: String, required: true },
    termsUrl: { type: String, default: null },
});
</script>

<template>
    <footer
        class="no-print border-t border-gray-200 bg-gray-50 py-8 dark:border-gray-700 dark:bg-gray-900/50"
    >
        <div class="mx-auto max-w-5xl px-4 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>
                Powered by
                <a
                    :href="appUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                >{{ appName }}</a>
                <template v-if="termsUrl">
                    <span class="mx-1.5 text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                    <a
                        :href="termsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >Terms and conditions</a>
                </template>
            </p>
        </div>
    </footer>
</template>
