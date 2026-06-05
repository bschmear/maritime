<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    faqs: {
        type: Array,
        default: () => [],
    },
});

const listEl = ref(null);
const localFaqs = ref([...props.faqs]);
const reordering = ref(false);
let sortable = null;

watch(
    () => props.faqs,
    (faqs) => {
        localFaqs.value = [...faqs];
        nextTick(() => initSortable());
    },
    { deep: true },
);

const stripHtml = (value) => {
    if (!value) {
        return '';
    }

    const tmp = document.createElement('div');
    tmp.innerHTML = value;

    return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
};

const truncate = (value, length = 80) => {
    const plain = stripHtml(value);
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
    if (localFaqs.value.length < 2) {
        return;
    }

    reordering.value = true;
    router.post(
        route('kiosk.faqs.reorder'),
        { order: localFaqs.value.map((faq) => faq.id) },
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

    if (!listEl.value || localFaqs.value.length < 2) {
        return;
    }

    sortable = Sortable.create(listEl.value, {
        handle: '.faq-drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd(evt) {
            if (evt.oldIndex == null || evt.newIndex == null || evt.oldIndex === evt.newIndex) {
                return;
            }

            const moved = localFaqs.value.splice(evt.oldIndex, 1)[0];
            localFaqs.value.splice(evt.newIndex, 0, moved);
            persistReorder();
        },
    });
};

const deleteFaq = (faq) => {
    if (confirm(`Delete "${faq.question}"?`)) {
        router.delete(route('kiosk.faqs.destroy', faq.id));
    }
};

onMounted(() => nextTick(() => initSortable()));
onBeforeUnmount(() => destroySortable());
</script>

<template>
    <Head title="FAQs" />

    <KioskLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">FAQs</h1>
        </template>

        <div class="min-w-0 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Manage your frequently asked questions
                    </p>
                    <p v-if="localFaqs.length > 1" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Drag rows to change display order on the public FAQ page.
                        <span v-if="reordering" class="text-primary-600 dark:text-primary-400">Saving…</span>
                    </p>
                </div>
                <Link
                    :href="route('kiosk.faqs.create')"
                    class="gradient-btn gap-x-2 whitespace-nowrap rounded-lg px-4 py-2.5 text-sm"
                >
                    <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New FAQ
                </Link>
            </div>

            <div class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-[48rem] w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="w-10 py-3.5 pl-4 pr-2 sm:pl-6">
                                    <span class="sr-only">Reorder</span>
                                </th>
                                <th scope="col" class="py-3.5 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Question
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Answer
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    Featured
                                </th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            ref="listEl"
                            class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                        >
                            <tr
                                v-for="faq in localFaqs"
                                :key="faq.id"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                <td class="whitespace-nowrap py-4 pl-4 pr-2 sm:pl-6">
                                    <button
                                        type="button"
                                        class="faq-drag-handle cursor-grab text-gray-400 hover:text-gray-600 active:cursor-grabbing dark:hover:text-gray-300"
                                        title="Drag to reorder"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="max-w-[14rem] py-4 pr-3 text-sm font-medium sm:max-w-xs">
                                    <span
                                        class="block truncate text-gray-900 dark:text-white"
                                        :title="faq.question"
                                    >
                                        {{ truncate(faq.question, 80) }}
                                    </span>
                                </td>
                                <td class="max-w-[16rem] px-3 py-4 text-sm text-gray-500 dark:text-gray-400 sm:max-w-sm">
                                    <span
                                        v-if="faq.answer"
                                        class="block line-clamp-2"
                                        :title="stripHtml(faq.answer)"
                                    >
                                        {{ truncate(faq.answer, 100) }}
                                    </span>
                                    <span v-else class="italic text-gray-400 dark:text-gray-500">
                                        No answer
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span
                                        v-if="faq.featured"
                                        class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/40 dark:text-primary-300"
                                    >
                                        Featured
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-500/20 dark:text-gray-400"
                                    >
                                        Not featured
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <div class="flex items-center justify-end gap-x-3">
                                        <Link
                                            :href="route('kiosk.faqs.edit', faq.id)"
                                            class="inline-flex items-center gap-x-1 text-primary-600 transition-colors hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-x-1 text-red-600 transition-colors hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            @click="deleteFaq(faq)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="localFaqs.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">No FAQs</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new FAQ.</p>
                                    <div class="mt-6">
                                        <Link
                                            :href="route('kiosk.faqs.create')"
                                            class="gradient-btn gap-x-2 rounded-lg px-4 py-2 text-sm font-medium"
                                        >
                                            New FAQ
                                        </Link>
                                    </div>
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
