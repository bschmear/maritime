<script setup>
defineProps({
    logoUrl: { type: String, default: null },
    /** Small uppercase label (e.g. Invoice, Estimate) */
    documentLabel: { type: String, required: true },
    /** Prominent document number or title */
    documentNumber: { type: String, required: true },
    /** Optional date line under the document number */
    documentDate: { type: String, default: '' },
    /** Enable dark-mode text/border classes (consignment review) */
    dark: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="border-b-4 border-gray-900 px-4 py-5 sm:px-8 sm:py-6 print:border-b-2 print:px-0"
        :class="dark ? 'dark:border-gray-100' : ''"
    >
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
                <div v-if="logoUrl" class="shrink-0">
                    <img
                        :src="logoUrl"
                        alt="Company logo"
                        class="h-14 w-auto max-w-[140px] object-contain sm:h-20 sm:max-w-[180px]"
                    >
                </div>
                <div
                    v-else
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-gray-200 sm:h-20 sm:w-20"
                    :class="dark ? 'dark:bg-gray-700' : ''"
                >
                    <span class="material-icons text-3xl text-gray-400 sm:text-4xl">business</span>
                </div>
                <div class="min-w-0 flex-1">
                    <slot name="company" />
                </div>
            </div>

            <div
                class="w-full shrink-0 border-t border-gray-200 pt-4 text-left sm:w-auto sm:border-t-0 sm:pt-0 sm:text-right"
                :class="dark ? 'dark:border-gray-700' : ''"
            >
                <div
                    class="text-sm font-medium uppercase text-gray-600"
                    :class="dark ? 'dark:text-gray-400' : ''"
                >
                    {{ documentLabel }}
                </div>
                <div
                    class="break-words font-mono text-2xl font-bold text-gray-900 sm:text-3xl"
                    :class="dark ? 'dark:text-white' : ''"
                >
                    {{ documentNumber }}
                </div>
                <div
                    v-if="documentDate"
                    class="mt-1 text-sm text-gray-600"
                    :class="dark ? 'dark:text-gray-400' : ''"
                >
                    {{ documentDate }}
                </div>
                <slot name="meta-extra" />
            </div>
        </div>
    </div>
</template>
