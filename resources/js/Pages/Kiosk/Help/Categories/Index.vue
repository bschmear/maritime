<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    filters: Object,
    canReorder: {
        type: Boolean,
        default: true,
    },
});

const search = ref(props.filters?.search || '');
const listEl = ref(null);
const localCategories = ref([...props.categories]);
const reordering = ref(false);
let sortable = null;

const sortableEnabled = computed(() => props.canReorder && localCategories.value.length > 1);

watch(
    () => props.categories,
    (categories) => {
        localCategories.value = [...categories];
        nextTick(() => initSortable());
    },
    { deep: true },
);

const truncate = (value, length = 60) => {
    if (!value) {
        return '';
    }

    const plain = String(value).trim();
    if (plain.length <= length) {
        return plain;
    }

    return `${plain.slice(0, length)}…`;
};

const destroySortable = () => {
    sortable?.destroy();
    sortable = null;
};

const persistReorder = () => {
    if (!sortableEnabled.value) {
        return;
    }

    reordering.value = true;
    router.post(
        route('kiosk.help-categories.reorder'),
        { order: localCategories.value.map((category) => category.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                reordering.value = false;
            },
        },
    );
};

const initSortable = () => {
    destroySortable();

    if (!listEl.value || !sortableEnabled.value) {
        return;
    }

    sortable = Sortable.create(listEl.value, {
        handle: '.category-drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd(evt) {
            if (evt.oldIndex == null || evt.newIndex == null || evt.oldIndex === evt.newIndex) {
                return;
            }

            const moved = localCategories.value.splice(evt.oldIndex, 1)[0];
            localCategories.value.splice(evt.newIndex, 0, moved);
            persistReorder();
        },
    });
};

const searchCategories = () => {
    router.get(route('kiosk.help-categories.index'), { search: search.value || undefined }, {
        preserveState: true,
        replace: true,
    });
};

onMounted(() => nextTick(() => initSortable()));
onBeforeUnmount(() => destroySortable());
</script>

<template>
    <Head title="Help Categories" />
    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Help Categories</h1>
        </template>

        <div class="min-w-0 space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search..."
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:max-w-sm"
                    @keyup.enter="searchCategories"
                />
                <Link :href="route('kiosk.help-categories.create')" class="gradient-btn whitespace-nowrap rounded-lg px-4 py-2 text-sm">
                    New Category
                </Link>
            </div>

            <p v-if="sortableEnabled" class="text-xs text-gray-500 dark:text-gray-400">
                Drag rows to change category order in the help center navigation.
                <span v-if="reordering" class="text-primary-600 dark:text-primary-400">Saving…</span>
            </p>
            <p v-else-if="!canReorder" class="text-xs text-gray-500 dark:text-gray-400">
                Clear search to reorder categories.
            </p>

            <div class="min-w-0 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-[40rem] w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="w-10 px-4 py-3" />
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Parent</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Active</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody ref="listEl" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="cat in localCategories" :key="cat.id">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <button
                                        v-if="sortableEnabled"
                                        type="button"
                                        class="category-drag-handle cursor-grab text-gray-400 hover:text-gray-600 active:cursor-grabbing dark:hover:text-gray-300"
                                        title="Drag to reorder"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="max-w-xs px-4 py-3 text-sm text-gray-900 dark:text-white sm:max-w-sm">
                                    <span class="block truncate" :title="cat.name">{{ truncate(cat.name, 60) }}</span>
                                </td>
                                <td class="max-w-[10rem] truncate px-4 py-3 text-sm text-gray-500" :title="cat.parent?.name || ''">
                                    {{ cat.parent?.name ? truncate(cat.parent.name, 40) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">{{ cat.active ? 'Yes' : 'No' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <Link :href="route('kiosk.help-categories.edit', cat.id)" class="text-sm text-primary-600">Edit</Link>
                                </td>
                            </tr>
                            <tr v-if="localCategories.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No categories found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </KioskLayout>
</template>

<style scoped>
.sortable-ghost {
    opacity: 0.45;
}
.sortable-chosen {
    background-color: rgb(249 250 251);
}
:global(.dark) .sortable-chosen {
    background-color: rgb(31 41 55 / 0.5);
}
</style>
