<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'No photos have been shared for this service ticket yet.',
    },
});

const lightboxIndex = ref(null);

const sortedImages = computed(() =>
    [...props.images].sort((a, b) => {
        if (a.is_primary && !b.is_primary) return -1;
        if (!a.is_primary && b.is_primary) return 1;
        return 0;
    }),
);

const openLightbox = (index) => {
    lightboxIndex.value = index;
};

const closeLightbox = () => {
    lightboxIndex.value = null;
};

const lightboxImage = computed(() => {
    if (lightboxIndex.value === null) return null;
    return sortedImages.value[lightboxIndex.value] ?? null;
});

const showPrevious = () => {
    if (lightboxIndex.value === null || sortedImages.value.length < 2) return;
    lightboxIndex.value =
        (lightboxIndex.value - 1 + sortedImages.value.length) % sortedImages.value.length;
};

const showNext = () => {
    if (lightboxIndex.value === null || sortedImages.value.length < 2) return;
    lightboxIndex.value = (lightboxIndex.value + 1) % sortedImages.value.length;
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex items-center gap-2">
                <span class="material-icons text-gray-500">collections</span>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Photos</h2>
                <span
                    v-if="sortedImages.length"
                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                >
                    {{ sortedImages.length }}
                </span>
            </div>
        </div>

        <div v-if="sortedImages.length" class="p-5">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <button
                    v-for="(img, index) in sortedImages"
                    :key="img.id"
                    type="button"
                    class="group relative aspect-square overflow-hidden rounded-xl border border-gray-200 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    @click="openLightbox(index)"
                >
                    <img
                        :src="img.url"
                        :alt="img.display_name || 'Photo'"
                        class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                        loading="lazy"
                    />
                    <span
                        v-if="img.is_primary"
                        class="absolute left-2 top-2 rounded bg-primary-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white"
                    >
                        Primary
                    </span>
                    <span
                        class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/20 group-hover:opacity-100"
                    >
                        <span class="material-icons text-3xl text-white drop-shadow">zoom_in</span>
                    </span>
                </button>
            </div>
        </div>

        <div v-else class="px-5 py-14 text-center">
            <span class="material-icons mb-3 text-4xl text-gray-300">image_not_supported</span>
            <p class="text-sm text-gray-500">{{ emptyMessage }}</p>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="lightboxImage"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeLightbox"
        >
            <button
                type="button"
                class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Close"
                @click="closeLightbox"
            >
                <span class="material-icons">close</span>
            </button>

            <button
                v-if="sortedImages.length > 1"
                type="button"
                class="absolute left-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Previous"
                @click.stop="showPrevious"
            >
                <span class="material-icons">chevron_left</span>
            </button>

            <button
                v-if="sortedImages.length > 1"
                type="button"
                class="absolute right-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Next"
                @click.stop="showNext"
            >
                <span class="material-icons">chevron_right</span>
            </button>

            <div class="max-h-[85vh] max-w-5xl">
                <img
                    :src="lightboxImage.url"
                    :alt="lightboxImage.display_name || 'Photo'"
                    class="max-h-[85vh] w-auto max-w-full rounded-lg object-contain"
                />
                <p v-if="lightboxImage.display_name" class="mt-3 text-center text-sm text-white/80">
                    {{ lightboxImage.display_name }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
