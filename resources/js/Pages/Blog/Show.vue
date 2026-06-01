<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    post: Object,
    relatedPosts: Array,
});

const page = usePage();

const shareUrl = computed(() => {
    if (page.props.meta?.url) {
        return page.props.meta.url;
    }

    if (props.post?.slug && typeof window !== 'undefined') {
        return `${window.location.origin}/blog/${props.post.slug}`;
    }

    return '';
});

const twitterShareHref = computed(() => {
    if (!shareUrl.value) {
        return '#';
    }

    const params = new URLSearchParams({
        text: props.post?.title ?? '',
        url: shareUrl.value,
    });

    return `https://twitter.com/intent/tweet?${params.toString()}`;
});

const facebookShareHref = computed(() => {
    if (!shareUrl.value) {
        return '#';
    }

    return `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl.value)}`;
});

const linkedInShareHref = computed(() => {
    if (!shareUrl.value) {
        return '#';
    }

    const params = new URLSearchParams({
        mini: 'true',
        url: shareUrl.value,
        title: props.post?.title ?? '',
    });

    return `https://www.linkedin.com/shareArticle?${params.toString()}`;
});

const linkCopied = ref(false);
let copyResetTimer = null;

const copyShareLink = async () => {
    const url = shareUrl.value;
    if (!url) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'absolute';
            input.style.left = '-9999px';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        linkCopied.value = true;
        if (copyResetTimer) {
            clearTimeout(copyResetTimer);
        }
        copyResetTimer = setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    } catch {
        linkCopied.value = false;
    }
};

const proseClass =
    'prose prose-lg prose-secondary dark:prose-invert max-w-none ' +
    'prose-headings:font-bold prose-headings:text-gray-900 dark:prose-headings:text-white ' +
    'prose-p:text-gray-700 dark:prose-p:text-gray-300 ' +
    'prose-a:text-secondary-600 dark:prose-a:text-secondary-400 prose-a:no-underline hover:prose-a:underline ' +
    'prose-strong:text-gray-900 dark:prose-strong:text-white ' +
    'prose-code:text-secondary-600 dark:prose-code:text-secondary-400 ' +
    'prose-blockquote:border-secondary-500 dark:prose-blockquote:border-secondary-400 ' +
    'prose-img:rounded-xl prose-img:shadow-lg';
</script>

<template>
    <Head :title="post.title" />

    <AppLayout>
        <!-- Post header -->
        <section class="relative overflow-hidden border-b border-gray-200/80 dark:border-gray-800">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div
                    class="absolute inset-0 bg-gradient-to-br from-primary-50 via-sky-50/90 to-secondary-100/60 dark:from-gray-900 dark:via-slate-900 dark:to-primary-950"
                />
                <!-- <div
                    v-if="post.cover_image"
                    class="absolute inset-0 bg-cover bg-center opacity-[0.12] mix-blend-multiply dark:opacity-[0.08] dark:mix-blend-lighten"
                    :style="{ backgroundImage: `url(${post.cover_image})` }"
                /> -->
                <svg class="absolute inset-0 h-full w-full text-primary-600/10 dark:text-sky-400/15" preserveAspectRatio="none" >
                    <defs>
                        <pattern
                            id="blog-post-wave-lines"
                            width="160"
                            height="48"
                            patternUnits="userSpaceOnUse"
                            patternTransform="rotate(-4)"
                        >
                            <path
                                d="M0 24 Q40 10 80 24 T160 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <path
                                d="M0 38 Q40 24 80 38 T160 38"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1"
                                opacity="0.7"
                            />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#blog-post-wave-lines)" />
                </svg>
                <div
                    class="absolute -right-24 top-8 h-72 w-72 rounded-full bg-secondary-300/25 blur-3xl dark:bg-secondary-600/10"
                />
                <div
                    class="absolute -left-16 bottom-0 h-64 w-64 rounded-full bg-primary-300/30 blur-3xl dark:bg-primary-700/15"
                />
            </div>

            <div class="relative z-10 mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
                <Link
                    :href="route('blog')"
                    class="mb-8 inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-secondary-600 dark:text-gray-400 dark:hover:text-secondary-400"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to blog
                </Link>

                <div v-if="post.category" class="mb-4">
                    <Link
                        :href="`/blog/category?slug=${post.category.slug}`"
                        class="inline-flex items-center rounded-full bg-secondary-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-secondary-800 transition-colors hover:bg-secondary-200 dark:bg-secondary-900/50 dark:text-secondary-300 dark:hover:bg-secondary-900"
                    >
                        {{ post.category.name }}
                    </Link>
                </div>

                <h1 class="max-w-4xl text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl lg:text-5xl">
                    {{ post.title }}
                </h1>

                <p
                    v-if="post.short_description"
                    class="mt-5 max-w-3xl text-lg leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    {{ post.short_description }}
                </p>
            </div>
        </section>

        <!-- Article + sidebar -->
        <section class="py-12 lg:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-10 xl:gap-14">
                    <!-- Main column -->
                    <article class="min-w-0 lg:col-span-8">
                        <div
                            v-if="post.cover_image"
                            class="mb-10 overflow-hidden rounded-2xl border border-gray-200 shadow-sm dark:border-gray-700"
                        >
                            <img
                                :src="post.cover_image"
                                :alt="post.title"
                                class="aspect-[2/1] w-full object-cover"
                            />
                        </div>

                        <div :class="proseClass" v-html="post.body"></div>

                        <div
                            v-if="post.tags?.length > 0"
                            class="mt-12 border-t border-gray-200 pt-8 dark:border-gray-700 lg:hidden"
                        >
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Tags
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="tag in post.tags"
                                    :key="tag.slug"
                                    :href="`/blog/tag?slug=${tag.slug}`"
                                    class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-secondary-100 hover:text-secondary-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-secondary-900/50 dark:hover:text-secondary-300"
                                >
                                    #{{ tag.name }}
                                </Link>
                            </div>
                        </div>
                    </article>

                    <!-- Sidebar -->
                    <aside class="lg:col-span-4">
                        <div class="space-y-6 lg:sticky lg:top-24">
                            <!-- Article metadata -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                            >
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Article details
                                </h2>

                                <dl class="mt-5 space-y-4">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Published
                                        </dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                            <time v-if="post.published_at_iso" :datetime="post.published_at_iso">
                                                {{ post.published_at }}
                                            </time>
                                            <span v-else>{{ post.published_at }}</span>
                                        </dd>
                                    </div>

                                    <div v-if="post.updated_at">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Updated
                                        </dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ post.updated_at }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Reading time
                                        </dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ post.read_time }}
                                        </dd>
                                    </div>

                                    <div v-if="post.category">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Category
                                        </dt>
                                        <dd class="mt-1">
                                            <Link
                                                :href="`/blog/category?slug=${post.category.slug}`"
                                                class="text-sm font-medium text-secondary-600 hover:text-secondary-700 dark:text-secondary-400 dark:hover:text-secondary-300"
                                            >
                                                {{ post.category.name }}
                                            </Link>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Share -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                            >
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Share
                                </h2>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a
                                        :href="twitterShareHref"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                        title="Share on X"
                                    >
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                                        </svg>
                                        <span>X</span>
                                    </a>
                                    <a
                                        :href="facebookShareHref"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                        title="Share on Facebook"
                                    >
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                        </svg>
                                        <span>Facebook</span>
                                    </a>
                                    <a
                                        :href="linkedInShareHref"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                        title="Share on LinkedIn"
                                    >
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                                        </svg>
                                        <span>LinkedIn</span>
                                    </a>
                                </div>

                                <div v-if="shareUrl" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        Page link
                                    </p>
                                    <p class="mb-3 truncate text-sm text-gray-600 dark:text-gray-400" :title="shareUrl">
                                        {{ shareUrl }}
                                    </p>
                                    <button
                                        type="button"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        :class="linkCopied ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300' : ''"
                                        @click="copyShareLink"
                                    >
                                        <svg
                                            v-if="!linkCopied"
                                            class="h-4 w-4 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ linkCopied ? 'Copied!' : 'Copy link' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tags (sidebar, desktop) -->
                            <div
                                v-if="post.tags?.length > 0"
                                class="hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:block"
                            >
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Tags
                                </h2>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <Link
                                        v-for="tag in post.tags"
                                        :key="tag.slug"
                                        :href="`/blog/tag?slug=${tag.slug}`"
                                        class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-secondary-100 hover:text-secondary-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-secondary-900/50 dark:hover:text-secondary-300"
                                    >
                                        #{{ tag.name }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <!-- Related posts -->
        <section v-if="relatedPosts.length > 0" class="border-t border-gray-200 bg-gray-50 py-14 dark:border-gray-800 dark:bg-gray-900/50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="mb-8 text-2xl font-bold text-gray-900 dark:text-white">Related posts</h2>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <article
                        v-for="relatedPost in relatedPosts"
                        :key="relatedPost.id"
                        class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                    >
                        <Link :href="`/blog/${relatedPost.slug}`" class="relative block h-44 overflow-hidden">
                            <img
                                :src="relatedPost.cover_image"
                                :alt="relatedPost.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                        </Link>

                        <div class="p-5">
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ relatedPost.published_at }}
                            </p>

                            <Link :href="`/blog/${relatedPost.slug}`">
                                <h3
                                    class="mb-2 line-clamp-2 text-lg font-bold text-gray-900 transition-colors group-hover:text-secondary-600 dark:text-white dark:group-hover:text-secondary-400"
                                >
                                    {{ relatedPost.title }}
                                </h3>
                            </Link>

                            <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ relatedPost.excerpt }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.blog-post-header-wave {
    animation: blog-header-wave-drift 14s ease-in-out infinite alternate;
}

.blog-post-header-wave-slow {
    animation: blog-header-wave-drift 20s ease-in-out infinite alternate-reverse;
}

@keyframes blog-header-wave-drift {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-2.5%);
    }
}
</style>
