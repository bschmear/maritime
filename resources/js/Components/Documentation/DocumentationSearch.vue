<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const emit = defineEmits(['navigate']);

const page = usePage();
const searchIndex = computed(() => page.props.docSearchIndex ?? []);

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const inputRef = ref(null);
const modifierKey = ref('Ctrl ');

const results = computed(() => {
    const q = query.value.trim();
    if (q.length < 1) {
        return [];
    }

    return searchIndex.value
        .map((item) => ({ item, score: scoreItem(item, q) }))
        .filter(({ score }) => score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 8)
        .map(({ item }) => item);
});

watch(query, () => {
    activeIndex.value = 0;
});

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        activeIndex.value = 0;
        requestAnimationFrame(() => inputRef.value?.focus());
    }
});

function fuzzySubsequence(haystack, pattern) {
    let pi = 0;
    for (let i = 0; i < haystack.length && pi < pattern.length; i++) {
        if (haystack[i] === pattern[pi]) {
            pi++;
        }
    }
    return pi === pattern.length;
}

function scoreItem(item, rawQuery) {
    const q = rawQuery.toLowerCase();
    const title = item.title.toLowerCase();
    const excerpt = (item.excerpt || '').toLowerCase();
    const category = (item.category || '').toLowerCase();
    let score = 0;

    if (title === q) {
        score += 200;
    }
    if (title.includes(q)) {
        score += 100;
    }
    if (title.startsWith(q)) {
        score += 50;
    }
    if (fuzzySubsequence(title, q)) {
        score += 35;
    }
    if (category.includes(q)) {
        score += 25;
    }
    if (excerpt.includes(q)) {
        score += 20;
    }
    if (fuzzySubsequence(excerpt, q)) {
        score += 10;
    }

    q.split(/\s+/).filter((word) => word.length > 1).forEach((word) => {
        if (title.includes(word)) {
            score += 15;
        }
        if (excerpt.includes(word)) {
            score += 8;
        }
        if (category.includes(word)) {
            score += 6;
        }
        if (fuzzySubsequence(title, word)) {
            score += 5;
        }
    });

    return score;
}

function titleHighlight(item) {
    const text = item.title;
    const q = query.value.trim();
    if (!q) {
        return { before: text, match: '', after: '' };
    }
    const idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx === -1) {
        return { before: text, match: '', after: '' };
    }
    return {
        before: text.slice(0, idx),
        match: text.slice(idx, idx + q.length),
        after: text.slice(idx + q.length),
    };
}

function close() {
    open.value = false;
    query.value = '';
}

function goTo(item) {
    if (!item?.url) {
        return;
    }
    router.visit(item.url);
    close();
    emit('navigate');
}

function onKeydown(event) {
    if (!open.value) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (results.value.length) {
            activeIndex.value = (activeIndex.value + 1) % results.value.length;
        }
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (results.value.length) {
            activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length;
        }
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const item = results.value[activeIndex.value];
        if (item) {
            goTo(item);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}

function onGlobalKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = true;
    }
}

onMounted(() => {
    modifierKey.value = /(Mac|iPhone|iPod|iPad)/i.test(navigator.platform) ? '⌘' : 'Ctrl ';
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<template>
    <!-- Desktop trigger -->
    <div class="hidden min-w-0 flex-1 lg:block lg:max-w-md">
        <button
            type="button"
            class="flex h-8 w-full items-center gap-2 rounded-full bg-white pr-3 pl-2 text-sm text-gray-500 ring-1 ring-gray-900/10 transition hover:ring-primary-500/30"
            @click="open = true"
        >
            <svg class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 20 20" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12.01 12a4.25 4.25 0 1 0-6.02-6 4.25 4.25 0 0 0 6.02 6Zm0 0 3.24 3.25" />
            </svg>
            <span class="truncate">Search documentation...</span>
            <kbd class="ml-auto hidden shrink-0 text-[10px] text-gray-400 sm:inline">
                <kbd class="font-sans">{{ modifierKey }}</kbd><kbd class="font-sans">K</kbd>
            </kbd>
        </button>
    </div>

    <!-- Mobile trigger -->
    <button
        type="button"
        class="inline-flex shrink-0 items-center justify-center rounded-lg p-2 text-gray-700 hover:bg-gray-100 lg:hidden"
        aria-label="Search documentation"
        @click="open = true"
    >
        <svg class="h-5 w-5 stroke-current" fill="none" viewBox="0 0 20 20" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12.01 12a4.25 4.25 0 1 0-6.02-6 4.25 4.25 0 0 0 6.02 6Zm0 0 3.24 3.25" />
        </svg>
    </button>

    <Teleport to="body">
        <div
            v-show="open"
            class="fixed inset-0 z-[60]"
            role="dialog"
            aria-modal="true"
            aria-label="Search documentation"
        >
            <div class="absolute inset-0 bg-gray-900/25 backdrop-blur-sm" @click="close" />

            <div class="fixed inset-0 overflow-y-auto px-4 py-4 sm:px-6 sm:py-16 md:py-24 lg:px-8 lg:py-[12vh]">
                <div
                    class="mx-auto max-w-xl overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-900/10"
                    @click.stop
                >
                    <div class="flex h-12 items-center gap-2 border-b border-gray-100 px-3">
                        <svg class="h-5 w-5 shrink-0 stroke-gray-400" fill="none" viewBox="0 0 20 20" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.01 12a4.25 4.25 0 1 0-6.02-6 4.25 4.25 0 0 0 6.02 6Zm0 0 3.24 3.25" />
                        </svg>
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="search"
                            placeholder="Search documentation..."
                            autocomplete="off"
                            class="flex-1 border-0 bg-transparent text-sm text-gray-900 placeholder:text-gray-500 focus:ring-0"
                            @keydown="onKeydown"
                        />
                        <kbd class="hidden shrink-0 text-[10px] text-gray-400 sm:inline">esc</kbd>
                    </div>

                    <ul v-if="results.length" class="max-h-80 overflow-y-auto py-1">
                        <li
                            v-for="(item, index) in results"
                            :key="item.slug"
                            class="cursor-pointer px-4 py-3 transition"
                            :class="index === activeIndex ? 'bg-primary-50' : 'hover:bg-gray-50'"
                            @mouseenter="activeIndex = index"
                            @click="goTo(item)"
                        >
                            <p
                                class="text-sm font-medium"
                                :class="index === activeIndex ? 'text-primary-700' : 'text-gray-900'"
                            >
                                <template v-if="titleHighlight(item).match">
                                    {{ titleHighlight(item).before }}<mark class="bg-transparent font-semibold text-primary-600 underline">{{ titleHighlight(item).match }}</mark>{{ titleHighlight(item).after }}
                                </template>
                                <template v-else>{{ item.title }}</template>
                            </p>
                            <p v-if="item.category" class="mt-0.5 truncate text-xs text-gray-500">
                                {{ item.category }}
                            </p>
                            <p v-if="item.excerpt" class="mt-1 line-clamp-1 text-xs text-gray-400">
                                {{ item.excerpt }}
                            </p>
                        </li>
                    </ul>

                    <div v-else-if="query.trim()" class="px-4 py-10 text-center">
                        <svg class="mx-auto h-5 w-5 stroke-gray-400" fill="none" viewBox="0 0 20 20" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.01 12a4.237 4.237 0 0 0 1.24-3c0-.62-.132-1.207-.37-1.738M12.01 12A4.237 4.237 0 0 1 9 13.25c-.635 0-1.237-.14-1.777-.388M12.01 12l3.24 3.25m-3.715-9.661a4.25 4.25 0 0 0-5.975 5.908M4.5 15.5l11-11" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">
                            Nothing found for
                            <strong class="font-semibold text-gray-900">&lsquo;{{ query.trim() }}&rsquo;</strong>
                        </p>
                    </div>

                    <div v-else class="px-4 py-6 text-center text-xs text-gray-500">
                        Type to search articles by title, category, or excerpt
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
