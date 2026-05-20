<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    closeOnNavigate: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate']);

const page = usePage();
const helpNav = computed(() => page.props.helpNav || []);

const onNavigate = () => {
    if (props.closeOnNavigate) {
        emit('navigate');
    }
};

const currentArticleSlug = computed(() => page.props.article?.slug ?? null);
const currentCategorySlug = computed(() => page.props.category?.slug ?? null);

const isHome = () => route().current('docs.home');

const isCategoryActive = (slug) =>
    route().current('docs.category', slug) || currentCategorySlug.value === slug;

const isArticleActive = (slug) =>
    route().current('docs.article', slug) || currentArticleSlug.value === slug;

const linkClass = (active) => [
    'flex justify-between gap-2 py-1 pr-3 text-sm transition',
    active
        ? 'font-medium text-gray-900'
        : 'text-gray-600 hover:text-gray-900',
];

const groupLinksByCategory = computed(() => {
    const map = {};

    helpNav.value.forEach((category) => {
        const links = [];

        category.articles?.forEach((article) => {
            links.push({
                key: `article-${article.id}`,
                href: route('docs.article', article.slug),
                label: article.title,
                active: isArticleActive(article.slug),
            });
        });

        category.children?.forEach((child) => {
            links.push({
                key: `category-${child.id}`,
                href: route('docs.category', child.slug),
                label: child.name,
                active: isCategoryActive(child.slug),
            });
        });

        map[category.id] = links;
    });

    return map;
});

const activeIndexInGroup = (links) => {
    const idx = links.findIndex((link) => link.active);
    return idx >= 0 ? idx : -1;
};
</script>

<template>
    <nav>
        <ul role="list">
            <li class="md:hidden">
                <Link
                    :href="route('docs.home')"
                    :class="linkClass(isHome())"
                    class="!pl-0"
                    @click="onNavigate"
                >
                    Overview
                </Link>
            </li>

            <li
                v-for="category in helpNav"
                :key="category.id"
                class="relative mt-6 first:mt-0"
            >
                <h2 class="text-xs font-semibold text-gray-900">
                    <Link
                        :href="route('docs.category', category.slug)"
                        class="transition hover:text-primary-600"
                        :class="{ 'text-primary-600': isCategoryActive(category.slug) }"
                        @click="onNavigate"
                    >
                        {{ category.name }}
                    </Link>
                </h2>

                <div
                    v-if="groupLinksByCategory[category.id]?.length"
                    class="relative mt-3 pl-2"
                >
                    <div
                        class="absolute inset-y-0 left-2 w-px bg-gray-900/10"
                        aria-hidden="true"
                    />
                    <div
                        v-if="activeIndexInGroup(groupLinksByCategory[category.id]) >= 0"
                        class="absolute left-2 w-px bg-primary-500 transition-all duration-200"
                        :style="{
                            top: `${activeIndexInGroup(groupLinksByCategory[category.id]) * 2}rem`,
                            height: '1.5rem',
                        }"
                        aria-hidden="true"
                    />
                    <ul role="list" class="border-l border-transparent">
                        <li
                            v-for="link in groupLinksByCategory[category.id]"
                            :key="link.key"
                            class="relative"
                        >
                            <Link
                                :href="link.href"
                                :class="[linkClass(link.active), 'pl-4']"
                                @click="onNavigate"
                            >
                                <span class="truncate">{{ link.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </nav>
</template>
