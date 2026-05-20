<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const helpNav = page.props.helpNav || [];
const mobileMenuOpen = ref(false);
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50">
        <div class="fixed left-4 top-4 z-50 lg:hidden">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 text-gray-700 shadow-sm"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <div
            v-show="mobileMenuOpen"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            @click="mobileMenuOpen = false"
        />

        <aside
            :class="[
                'fixed inset-y-0 z-40 flex w-72 flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0',
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center border-b border-gray-100 px-6">
                <Link :href="route('docs.home')" class="flex items-center gap-3" @click="mobileMenuOpen = false">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white font-bold text-sm">
                        {{ page.props.app?.name?.charAt(0) || 'M' }}
                    </div>
                    <span class="text-lg font-semibold text-gray-900">{{ page.props.app?.name }} Docs</span>
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-6">
                <ul class="space-y-6">
                    <li v-for="category in helpNav" :key="category.id">
                        <Link
                            :href="route('docs.category', category.slug)"
                            class="block text-sm font-semibold text-gray-900 hover:text-primary-600"
                            @click="mobileMenuOpen = false"
                        >
                            {{ category.name }}
                        </Link>
                        <ul v-if="category.children?.length" class="mt-2 space-y-1 pl-3 border-l border-gray-200">
                            <li v-for="child in category.children" :key="child.id">
                                <Link
                                    :href="route('docs.category', child.slug)"
                                    class="block py-1 text-sm text-gray-600 hover:text-primary-600"
                                    @click="mobileMenuOpen = false"
                                >
                                    {{ child.name }}
                                </Link>
                            </li>
                        </ul>
                        <ul v-if="category.articles?.length" class="mt-2 space-y-1">
                            <li v-for="article in category.articles" :key="article.id">
                                <Link
                                    :href="route('docs.article', article.slug)"
                                    class="block py-1 text-sm text-gray-500 hover:text-primary-600"
                                    @click="mobileMenuOpen = false"
                                >
                                    {{ article.title }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col lg:pl-72">
            <slot />
        </div>
    </div>
</template>
