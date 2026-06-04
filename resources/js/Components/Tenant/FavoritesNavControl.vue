<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import { usePage } from '@inertiajs/vue3';
import { useTenantFavorites } from '@/composables/useTenantFavorites';

const props = defineProps({
    navItems: {
        type: Array,
        default: () => [],
    },
    dropdownAlign: {
        type: String,
        default: 'left',
    },
});

const page = usePage();
const dropdownOpen = ref(false);
const modalOpen = ref(false);
const titleInput = ref('');
const saving = ref(false);
const saveError = ref(null);
const dropdownRef = ref(null);

const {
    favorites,
    loading,
    fetchFavorites,
    addFavorite,
    removeFavorite,
    navigateToFavorite,
    suggestFavoriteTitle,
    currentPageUrl,
    canAddCurrentPage,
    isCurrentPageFavorited,
} = useTenantFavorites();

const pageUrl = computed(() => currentPageUrl());
const currentPageFavorited = computed(() => isCurrentPageFavorited());
const addDisabled = computed(() => !canAddCurrentPage() || currentPageFavorited.value);
const dropdownPositionClass = computed(() =>
    props.dropdownAlign === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'
);

const openDropdown = async () => {
    dropdownOpen.value = true;
    syncBodyScrollLock();
    await fetchFavorites();
};

const closeDropdown = () => {
    dropdownOpen.value = false;
    syncBodyScrollLock();
};

const toggleDropdown = () => {
    if (dropdownOpen.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
};

const syncBodyScrollLock = () => {
    if (typeof document === 'undefined') {
        return;
    }
    const isMobile = window.matchMedia('(max-width: 1023px)').matches;
    if (dropdownOpen.value && isMobile) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
};

const openAddModal = () => {
    if (addDisabled.value) {
        return;
    }
    titleInput.value = suggestFavoriteTitle(props.navItems);
    saveError.value = null;
    modalOpen.value = true;
    closeDropdown();
};

const closeAddModal = () => {
    modalOpen.value = false;
    saveError.value = null;
};

const handleFavoriteClick = (favorite) => {
    closeDropdown();
    navigateToFavorite(favorite);
};

const handleRemove = async (event, id) => {
    event.stopPropagation();
    try {
        await removeFavorite(id);
    } catch (error) {
        console.error('Failed to remove favorite:', error);
    }
};

const handleSave = async () => {
    const label = titleInput.value.trim();
    if (!label) {
        return;
    }

    const tenantRoute = page.props.tenant_route;
    if (!tenantRoute?.name) {
        return;
    }

    saving.value = true;
    saveError.value = null;

    try {
        await addFavorite({
            label,
            route: tenantRoute.name,
            route_params: Object.keys(tenantRoute.params ?? {}).length ? tenantRoute.params : null,
        });
        closeAddModal();
    } catch (error) {
        const message = error.response?.data?.errors?.route?.[0]
            ?? error.response?.data?.message
            ?? 'Could not save favorite.';
        saveError.value = message;
    } finally {
        saving.value = false;
    }
};

const handleOutsideClick = (e) => {
    if (!window.matchMedia('(min-width: 1024px)').matches) {
        return;
    }
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        closeDropdown();
    }
};

watch(
    () => page.url,
    () => {
        closeDropdown();
    }
);

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick);
    fetchFavorites();
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            @click="toggleDropdown"
            class="inline-flex items-center p-2 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
            title="Favorites"
            aria-haspopup="true"
            :aria-expanded="dropdownOpen"
        >
            <span class="sr-only">Favorites</span>
            <span
                class="material-icons text-[24px]"
                :class="currentPageFavorited ? 'text-amber-500' : ''"
            >star</span>
        </button>

        <!-- Desktop dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-show="dropdownOpen"
                :class="[
                    'hidden lg:flex lg:flex-col absolute mt-2 w-[20rem] z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl overflow-hidden',
                    dropdownPositionClass,
                ]"
            >
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight">Favorites</h3>
                </div>

                <div class="max-h-[20rem] overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/60 min-h-0 flex-1">
                    <div
                        v-if="loading && favorites.length === 0"
                        class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        Loading…
                    </div>

                    <div
                        v-for="favorite in favorites"
                        :key="favorite.id"
                        class="group flex items-center gap-2"
                    >
                        <button
                            type="button"
                            @click="handleFavoriteClick(favorite)"
                            class="flex-1 min-w-0 text-left px-4 py-3 text-sm text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <span class="block font-medium truncate">{{ favorite.label }}</span>
                        </button>
                        <button
                            type="button"
                            @click="handleRemove($event, favorite.id)"
                            class="flex-shrink-0 mr-2 p-1 rounded-md opacity-0 group-hover:opacity-100 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-400 dark:text-gray-500 transition-opacity"
                            title="Remove"
                        >
                            <span class="material-icons text-sm leading-none">close</span>
                        </button>
                    </div>

                    <div
                        v-if="!loading && favorites.length === 0"
                        class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        No favorites yet.
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-700 shrink-0">
                    <button
                        type="button"
                        @click="openAddModal"
                        :disabled="addDisabled"
                        class="w-full px-4 py-3 text-sm font-medium text-left transition-colors"
                        :class="addDisabled
                            ? 'text-gray-400 dark:text-gray-500 cursor-not-allowed'
                            : 'text-primary-600 hover:bg-gray-50 dark:text-primary-400 dark:hover:bg-gray-700/50'"
                    >
                        <span class="inline-flex items-center gap-2">
                            <span class="material-icons text-lg leading-none">add</span>
                            Add to favorites
                        </span>
                        <span
                            v-if="currentPageFavorited"
                            class="block text-xs font-normal text-gray-400 dark:text-gray-500 mt-0.5"
                        >
                            This page is already saved.
                        </span>
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Mobile full-screen overlay -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="dropdownOpen"
                    class="lg:hidden fixed inset-0 z-[60] flex flex-col bg-white dark:bg-gray-900"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="favorites-mobile-title"
                >
                    <header class="flex items-center justify-between shrink-0 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3
                            id="favorites-mobile-title"
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            Favorites
                        </h3>
                        <button
                            type="button"
                            @click="closeDropdown"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                            aria-label="Close favorites"
                        >
                            <span class="material-icons text-2xl leading-none">close</span>
                        </button>
                    </header>

                    <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        <div
                            v-if="loading && favorites.length === 0"
                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            Loading…
                        </div>

                        <div
                            v-for="favorite in favorites"
                            :key="`mobile-${favorite.id}`"
                            class="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                @click="handleFavoriteClick(favorite)"
                                class="flex-1 min-w-0 text-left px-4 py-4 text-sm text-gray-900 dark:text-white active:bg-gray-50 dark:active:bg-gray-800"
                            >
                                <span class="block font-medium break-words">{{ favorite.label }}</span>
                            </button>
                            <button
                                type="button"
                                @click="handleRemove($event, favorite.id)"
                                class="flex-shrink-0 mr-3 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                title="Remove"
                                aria-label="Remove favorite"
                            >
                                <span class="material-icons text-xl leading-none">close</span>
                            </button>
                        </div>

                        <div
                            v-if="!loading && favorites.length === 0"
                            class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No favorites yet.
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-gray-200 pb-[max(1rem,env(safe-area-inset-bottom))] dark:border-gray-700">
                        <button
                            type="button"
                            @click="openAddModal"
                            :disabled="addDisabled"
                            class="w-full px-4 py-4 text-sm font-medium text-left transition-colors"
                            :class="addDisabled
                                ? 'text-gray-400 dark:text-gray-500 cursor-not-allowed'
                                : 'text-primary-600 active:bg-gray-50 dark:text-primary-400 dark:active:bg-gray-800'"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="material-icons text-lg leading-none">add</span>
                                Add to favorites
                            </span>
                            <span
                                v-if="currentPageFavorited"
                                class="block text-xs font-normal text-gray-400 dark:text-gray-500 mt-0.5"
                            >
                                This page is already saved.
                            </span>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Modal :show="modalOpen" max-width="md" @close="closeAddModal">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add to favorites</h2>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Page link</label>
                    <p class="text-sm text-gray-700 dark:text-gray-300 break-all rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2 border border-gray-200 dark:border-gray-600">
                        {{ pageUrl }}
                    </p>
                </div>

                <div class="mb-4">
                    <label for="favorite-title" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                        Title
                    </label>
                    <input
                        id="favorite-title"
                        v-model="titleInput"
                        type="text"
                        maxlength="255"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Favorite name"
                        @keyup.enter="handleSave"
                    />
                </div>

                <p v-if="saveError" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ saveError }}</p>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="closeAddModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleSave"
                        :disabled="saving || !titleInput.trim()"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-600"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
