<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import axios from 'axios';

const props = defineProps({
    categoryId: {
        type: [String, Number],
        default: '',
    },
    articleId: {
        type: Number,
        default: null,
    },
    articleTitle: {
        type: String,
        default: '',
    },
    sortOrder: {
        type: Number,
        default: 0,
    },
    initialArticles: {
        type: Array,
        default: () => [],
    },
    /** Category the article is saved under (edit only). Reorder POST only when it matches categoryId. */
    savedCategoryId: {
        type: [String, Number],
        default: null,
    },
});

const emit = defineEmits(['update:sortOrder']);

const listEl = ref(null);
const localArticles = ref([]);
const loading = ref(false);
const reordering = ref(false);
let sortable = null;

const hasCategory = computed(() => props.categoryId !== '' && props.categoryId != null);

const canPersistOrder = computed(() => {
    if (props.articleId == null) {
        return false;
    }
    if (props.savedCategoryId == null || props.savedCategoryId === '') {
        return hasCategory.value;
    }

    return String(props.categoryId) === String(props.savedCategoryId);
});

const draftTitle = computed(() => (props.articleTitle || '').trim() || 'New article');

const syncSortOrderFromList = () => {
    const index = localArticles.value.findIndex((a) => a.is_current || a.is_draft);
    if (index >= 0) {
        emit('update:sortOrder', index);
    }
};

const buildLocalList = (articles) => {
    const rows = (articles || []).map((a) => ({
        id: a.id,
        title: a.title,
        sort_order: a.sort_order ?? 0,
        is_current: props.articleId != null && a.id === props.articleId,
        is_draft: false,
    }));

    if (props.articleId == null) {
        const draft = {
            id: null,
            title: draftTitle.value,
            sort_order: props.sortOrder,
            is_current: true,
            is_draft: true,
        };
        const index = Math.min(Math.max(0, props.sortOrder), rows.length);
        rows.splice(index, 0, draft);
    } else if (!rows.some((a) => a.id === props.articleId)) {
        const index = Math.min(Math.max(0, props.sortOrder), rows.length);
        rows.splice(index, 0, {
            id: props.articleId,
            title: draftTitle.value,
            sort_order: props.sortOrder,
            is_current: true,
            is_draft: false,
        });
    }

    return rows;
};

const fetchSiblings = async () => {
    if (!hasCategory.value) {
        localArticles.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.get(route('kiosk.help-articles.siblings'), {
            params: { category_id: props.categoryId },
        });
        localArticles.value = buildLocalList(data.articles);
        syncSortOrderFromList();
    } finally {
        loading.value = false;
        await nextTick();
        initSortable();
    }
};

const destroySortable = () => {
    sortable?.destroy();
    sortable = null;
};

const persistReorder = () => {
    const persisted = localArticles.value.filter((a) => a.id != null);
    if (persisted.length < 2) {
        syncSortOrderFromList();
        return;
    }

    reordering.value = true;
    router.post(
        route('kiosk.help-articles.reorder'),
        {
            category_id: props.categoryId,
            order: persisted.map((a) => a.id),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                reordering.value = false;
            },
            onSuccess: () => {
                syncSortOrderFromList();
            },
        },
    );
};

const initSortable = () => {
    destroySortable();
    if (!listEl.value || localArticles.value.length < 2) {
        return;
    }

    sortable = Sortable.create(listEl.value, {
        handle: '.article-drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd(evt) {
            const moved = localArticles.value.splice(evt.oldIndex, 1)[0];
            localArticles.value.splice(evt.newIndex, 0, moved);
            syncSortOrderFromList();

            if (canPersistOrder.value) {
                persistReorder();
            }
        },
    });
};

watch(
    () => props.categoryId,
    (id, prev) => {
        if (id === prev) {
            return;
        }
        if (!hasCategory.value) {
            localArticles.value = [];
            destroySortable();
            return;
        }
        fetchSiblings();
    },
);

watch(
    () => props.initialArticles,
    (articles) => {
        if (hasCategory.value && articles?.length && props.articleId != null) {
            localArticles.value = buildLocalList(articles);
            nextTick(() => initSortable());
        }
    },
    { deep: true },
);

watch(draftTitle, (title) => {
    const draft = localArticles.value.find((a) => a.is_draft);
    if (draft) {
        draft.title = title;
    }
});

onMounted(() => {
    if (hasCategory.value) {
        if (props.initialArticles?.length && props.articleId != null) {
            localArticles.value = buildLocalList(props.initialArticles);
            nextTick(() => initSortable());
        } else {
            fetchSiblings();
        }
    }
});

onBeforeUnmount(() => destroySortable());
</script>

<template>
    <div>
        <p v-if="!hasCategory" class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
            Select a category to set where this article appears in the list.
        </p>

        <div v-else-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading articles…</div>

        <div v-else-if="localArticles.length === 0" class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
            No other articles in this category yet. This will be the first.
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-600">
            <div ref="listEl" class="divide-y divide-gray-100 dark:divide-gray-700">
                <div
                    v-for="article in localArticles"
                    :key="article.is_draft ? 'draft' : article.id"
                    :data-article-id="article.id ?? 'draft'"
                    class="flex items-center gap-3 bg-white px-3 py-2.5 dark:bg-gray-900"
                    :class="{
                        'bg-primary-50/60 dark:bg-primary-900/20': article.is_current || article.is_draft,
                    }"
                >
                    <button
                        type="button"
                        class="article-drag-handle flex-shrink-0 cursor-move text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        :class="{ 'cursor-not-allowed opacity-30': localArticles.length < 2 }"
                        :disabled="localArticles.length < 2"
                    >
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 5h2v2H9V5zm0 6h2v2H9v-2zm0 6h2v2H9v-2zm4-12h2v2h-2V5zm0 6h2v2h-2v-2zm0 6h2v2h-2v-2z" />
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ article.title }}
                        </p>
                        <p v-if="article.is_draft" class="text-xs text-primary-600 dark:text-primary-400">This article (unsaved)</p>
                        <p v-else-if="article.is_current" class="text-xs text-primary-600 dark:text-primary-400">This article</p>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="reordering" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Saving order…</p>
        <p v-else-if="hasCategory && localArticles.length >= 2 && articleId == null" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Order is applied when you save the article.
        </p>
        <p v-else-if="hasCategory && articleId != null && !canPersistOrder" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Save the article to apply order in the new category.
        </p>
    </div>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.4;
}
.sortable-chosen {
    background-color: rgb(249 250 251);
}
:global(.dark) .sortable-chosen {
    background-color: rgb(31 41 55);
}
</style>
