<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="emit('close')" />

            <!-- Panel -->
            <div class="relative z-10 w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <span class="material-icons text-primary-500 text-xl">library_books</span>
                        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Choose a Template</h3>
                    </div>
                    <button
                        @click="emit('close')"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>

                <!-- Search -->
                <div class="px-5 pt-4 pb-2">
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search templates…"
                            class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                        />
                    </div>
                </div>

                <!-- Template list -->
                <div class="px-3 pb-4 max-h-72 overflow-y-auto space-y-1 mt-1">
                    <button
                        v-for="template in filtered"
                        :key="template.id ?? template.name"
                        @click="emit('select', template)"
                        class="w-full text-left px-4 py-3 rounded-xl hover:bg-primary-50 dark:hover:bg-primary-900/20 group transition-colors"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100 group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors truncate">
                                    {{ template.name }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ template.items?.length ?? 0 }} items
                                </p>
                            </div>
                            <span class="material-icons text-gray-300 group-hover:text-primary-400 transition-colors text-base shrink-0">
                                chevron_right
                            </span>
                        </div>
                    </button>

                    <!-- Empty state -->
                    <div v-if="!filtered.length" class="py-10 text-center">
                        <span class="material-icons text-3xl text-gray-200 dark:text-gray-600 block mb-2">
                            {{ search ? 'search_off' : 'inventory_2' }}
                        </span>
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            {{ search ? 'No templates match your search.' : 'No saved templates yet.' }}
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'select'])

const search = ref('')

const filtered = computed(() =>
    props.templates.filter(t =>
        t.name.toLowerCase().includes(search.value.toLowerCase())
    )
)
</script>
