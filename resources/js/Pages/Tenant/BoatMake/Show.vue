<script setup>
import Modal from '@/Components/Modal.vue';
import ShowRecord from '@/Components/Tenant/ShowRecord.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'boatmakes',
    },
    recordTitle: {
        type: String,
        default: 'Brand',
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    domainName: {
        type: String,
        default: 'Brand',
    },
    catalogPreview: {
        type: Object,
        default: () => ({ catalog_rows: [], imported_keys: [] }),
    },
    pendingModelImports: {
        type: Array,
        default: () => [],
    },
    recentFailedModelImports: {
        type: Array,
        default: () => [],
    },
    libraryModels: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

/** Set true to show AI catalog generate (modal, overlay, AI-oriented copy). */
const boatMakeAiCatalogUiEnabled = false;

const showRefreshCatalogModal = ref(false);
const refreshCatalogBusy = ref(false);

const openRefreshCatalogModal = () => {
    showRefreshCatalogModal.value = true;
};

const closeRefreshCatalogModal = () => {
    if (refreshCatalogBusy.value) {
        return;
    }
    showRefreshCatalogModal.value = false;
};

const confirmRefreshFromCatalog = () => {
    refreshCatalogBusy.value = true;
    router.post(
        route(`${props.recordType}.refresh-catalog-data`, props.record.id),
        { confirm: true },
        {
            preserveScroll: true,
            only: ['record', 'imageUrls', 'flash', 'errors'],
            onFinish: () => {
                refreshCatalogBusy.value = false;
                showRefreshCatalogModal.value = false;
            },
        }
    );
};

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const aiModelLabelError = computed(() => {
    const e = page.props.errors?.model_label ?? aiForm.errors.model_label;
    if (e == null || e === '') {
        return null;
    }

    return Array.isArray(e) ? e[0] : e;
});

const aiBrandError = computed(() => {
    const e = page.props.errors?.brand ?? aiForm.errors.brand;
    if (e == null || e === '') {
        return null;
    }

    return Array.isArray(e) ? e[0] : e;
});

const importDiscoveredError = computed(() => {
    const e = page.props.errors?.import_discovered;
    if (e == null || e === '') {
        return null;
    }

    return Array.isArray(e) ? e[0] : e;
});

const hasPendingModelImports = computed(() => (props.pendingModelImports ?? []).length > 0);

let pendingImportsPollTimer = null;

function clearPendingImportsPoll() {
    if (pendingImportsPollTimer != null) {
        clearInterval(pendingImportsPollTimer);
        pendingImportsPollTimer = null;
    }
}

function startPendingImportsPollIfNeeded() {
    clearPendingImportsPoll();
    if (!hasPendingModelImports.value) {
        return;
    }
    pendingImportsPollTimer = setInterval(() => {
        router.reload({
            only: ['pendingModelImports', 'recentFailedModelImports', 'catalogPreview', 'libraryModels', 'record'],
        });
    }, 3500);
}

watch(hasPendingModelImports, () => {
    startPendingImportsPollIfNeeded();
});

onMounted(() => {
    startPendingImportsPollIfNeeded();
});

onUnmounted(() => {
    clearPendingImportsPoll();
});

const aiForm = useForm({
    model_label: '',
});

/** Full-screen wait while the server runs single-model catalog generate (OpenAI). */
const showAiFetchOverlay = computed(() => boatMakeAiCatalogUiEnabled && aiForm.processing);

const libraryModels = computed(() => props.libraryModels ?? []);

/** Slugs from the tenant model library selected for import. */
const selectedLibrarySlugs = ref(new Set());

watch(
    () => props.record.id,
    () => {
        selectedLibrarySlugs.value = new Set();
    }
);

function isLibrarySelected(slug) {
    return selectedLibrarySlugs.value.has(slug);
}

function setLibrarySelected(slug, checked) {
    const next = new Set(selectedLibrarySlugs.value);
    if (checked) {
        next.add(slug);
    } else {
        next.delete(slug);
    }
    selectedLibrarySlugs.value = next;
}

function selectAllLibrary() {
    const list = libraryModels.value;
    if (!list?.length) {
        return;
    }
    selectedLibrarySlugs.value = new Set(list.map((m) => m.slug));
}

function clearLibrarySelection() {
    selectedLibrarySlugs.value = new Set();
}

const selectedLibraryCount = computed(() => selectedLibrarySlugs.value.size);

const importDiscoveredForm = useForm({
    models: [],
    duplicate_strategy: 'skip',
});

const showImportModelsModal = ref(false);

function openImportModelsModal() {
    selectedLibrarySlugs.value = new Set();
    importDiscoveredForm.duplicate_strategy = 'skip';
    showImportModelsModal.value = true;
}

const selectedAlreadyImportedCount = computed(() => {
    const selected = selectedLibrarySlugs.value;
    return libraryModels.value.filter((m) => selected.has(m.slug) && m.already_imported).length;
});

function closeImportModelsModal() {
    showImportModelsModal.value = false;
}

const showAddModelModal = ref(false);

function openAddModelModal() {
    aiForm.clearErrors();
    aiForm.reset();
    showAddModelModal.value = true;
}

function closeAddModelModal() {
    showAddModelModal.value = false;
    aiForm.clearErrors();
    aiForm.reset();
}

function submitAddModelFromModal() {
    aiForm.post(route('boatmakes.catalog-generate-model', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddModelModal();
            nextTick(() => {
                document.getElementById('boat-make-models-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },
    });
}

function importSelectedLibraryModels() {
    const list = libraryModels.value.filter((m) => selectedLibrarySlugs.value.has(m.slug));
    if (!list.length) {
        return;
    }
    importDiscoveredForm.models = list.map((m) => ({
        model_slug: m.slug,
        model_label: m.label,
    }));
    importDiscoveredForm.post(route('boatmakes.import-discovered-models', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedLibrarySlugs.value = new Set();
            closeImportModelsModal();
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showAiFetchOverlay"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-gray-950/75 px-4 py-8 backdrop-blur-sm"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="ai-fetch-overlay-title"
            aria-describedby="ai-fetch-overlay-desc"
        >
            <div
                class="max-w-md rounded-xl border border-gray-200 bg-white p-8 text-center shadow-2xl dark:border-gray-600 dark:bg-gray-900"
            >
                <div
                    class="mx-auto mb-5 size-11 animate-spin rounded-full border-2 border-primary-600 border-t-transparent dark:border-primary-400 dark:border-t-transparent"
                    aria-hidden="true"
                />
                <h2
                    id="ai-fetch-overlay-title"
                    class="text-base font-semibold text-gray-900 dark:text-gray-100"
                >
                    Please don't refresh
                </h2>
                <p id="ai-fetch-overlay-desc" class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    Fetching model information from AI. This may take a minute.
                </p>
            </div>
        </div>
    </Teleport>
    <ShowRecord
        :record="record"
        :record-type="recordType"
        :record-title="recordTitle"
        :form-schema="formSchema"
        :fields-schema="fieldsSchema"
        :enum-options="enumOptions"
        :domain-name="domainName"
        :show-sublists="true"
        :breadcrumb-parent-label="'Brands'"
        :breadcrumb-parent-href="route(`${recordType}.index`)"
    >
        <template #prepend>
            <div
                v-if="record.logo_url || record.default_brand_image || record.brand_key"
                class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Brand logo</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <template v-if="record.use_default_logo && record.default_brand_image">
                                Using the shared catalog logo. Turn off “Use catalog logo” in the form below to upload your own.
                            </template>
                            <template v-else-if="record.logo_url">
                                Using a custom uploaded logo.
                            </template>
                            <template v-else>
                                No logo yet. Upload one in the Brand logo section below, or refresh from the catalog if available.
                            </template>
                        </p>
                    </div>
                    <button
                        v-if="record.brand_key"
                        type="button"
                        class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="openRefreshCatalogModal"
                    >
                        Refresh from catalog
                    </button>
                </div>
                <div v-if="record.logo_url" class="mt-4">
                    <img
                        :src="record.logo_url"
                        :alt="`${record.display_name} logo`"
                        class="max-h-24 max-w-xs object-contain"
                    />
                </div>
            </div>
            <div
                v-if="record.brand_key"
                id="boat-make-models-panel"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Models for this brand</h3>
                <p class="mt-1 mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Import models from your shared inventory catalog when this brand has assets there. Not every brand
                    has catalog inventory — use Import model to check and pull lines onto your tenant list.
                </p>
                <div
                    v-if="flashSuccess"
                    class="mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    {{ flashSuccess }}
                </div>
                <p v-if="importDiscoveredError" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ importDiscoveredError }}</p>
                <div
                    v-if="pendingModelImports && pendingModelImports.length > 0"
                    class="mb-3 rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/30"
                >
                    <p class="text-sm font-medium text-amber-950 dark:text-amber-100">Import in progress</p>
                    <p class="mt-1 text-sm text-amber-900/90 dark:text-amber-200/90">
                        Each import runs as its own background job. This page updates automatically.
                    </p>
                    <ul class="mt-2 divide-y divide-amber-200/80 dark:divide-amber-900/50">
                        <li
                            v-for="row in pendingModelImports"
                            :key="row.id"
                            class="flex items-center gap-2 py-2 text-sm text-amber-950 dark:text-amber-100"
                        >
                            <span
                                class="inline-block size-2 shrink-0 animate-pulse rounded-full bg-amber-600 dark:bg-amber-400"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 font-medium">{{ row.model_label }}</span>
                            <span class="shrink-0 font-mono text-xs text-amber-800/80 dark:text-amber-300/80">{{
                                row.status === 'processing' ? 'Working…' : 'Queued'
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    v-if="recentFailedModelImports && recentFailedModelImports.length > 0"
                    class="mb-3 rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-900/50 dark:bg-red-950/25"
                >
                    <p class="text-sm font-medium text-red-900 dark:text-red-100">Recent import issues</p>
                    <ul class="mt-2 divide-y divide-red-200 dark:divide-red-900/40">
                        <li
                            v-for="row in recentFailedModelImports"
                            :key="row.id"
                            class="py-2 text-sm text-red-900 dark:text-red-100"
                        >
                            <span class="font-medium">{{ row.model_label }}</span>
                            <p v-if="row.error_message" class="mt-0.5 text-xs text-red-800/90 dark:text-red-200/80">
                                {{ row.error_message }}
                            </p>
                        </li>
                    </ul>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                        @click="openImportModelsModal"
                    >
                        Import model
                    </button>
                    <button
                        v-if="boatMakeAiCatalogUiEnabled"
                        type="button"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="openAddModelModal"
                    >
                        Add model
                    </button>
                </div>
            </div>
        </template>
    </ShowRecord>

    <Modal :show="showImportModelsModal" max-width="lg" @close="closeImportModelsModal">
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import models</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Select one or more lines from the inventory catalog for
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ record.display_name }}</span>.
                        Each import runs as a background job.
                    </p>
                </div>
                <button
                    type="button"
                    class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                    @click="closeImportModelsModal"
                >
                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Close</span>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                <div v-if="libraryModels.length > 0">
                    <fieldset class="mb-4 rounded-md border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-600 dark:bg-gray-800/40">
                        <legend class="px-1 text-sm font-medium text-gray-900 dark:text-gray-100">If a model is already on your list</legend>
                        <div class="mt-2 space-y-2">
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input
                                    v-model="importDiscoveredForm.duplicate_strategy"
                                    type="radio"
                                    class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    value="skip"
                                />
                                <span>
                                    <span class="font-medium">Skip duplicates</span>
                                    <span class="mt-0.5 block text-xs text-gray-600 dark:text-gray-400">
                                        Leave existing models unchanged.
                                    </span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input
                                    v-model="importDiscoveredForm.duplicate_strategy"
                                    type="radio"
                                    class="mt-0.5 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    value="overwrite"
                                />
                                <span>
                                    <span class="font-medium">Overwrite with catalog data</span>
                                    <span class="mt-0.5 block text-xs text-gray-600 dark:text-gray-400">
                                        Update weight, capacity, horsepower, dimensions, and variants from the catalog.
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p
                            v-if="selectedAlreadyImportedCount > 0 && importDiscoveredForm.duplicate_strategy === 'skip'"
                            class="mt-3 text-xs text-amber-800 dark:text-amber-200"
                        >
                            {{ selectedAlreadyImportedCount }} selected
                            {{ selectedAlreadyImportedCount === 1 ? 'model is' : 'models are' }}
                            already on your list and will be skipped.
                        </p>
                        <p
                            v-else-if="selectedAlreadyImportedCount > 0 && importDiscoveredForm.duplicate_strategy === 'overwrite'"
                            class="mt-3 text-xs text-primary-800 dark:text-primary-200"
                        >
                            {{ selectedAlreadyImportedCount }} selected
                            {{ selectedAlreadyImportedCount === 1 ? 'model' : 'models' }}
                            will be refreshed from the catalog.
                        </p>
                    </fieldset>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <button
                            type="button"
                            class="font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                            @click="selectAllLibrary"
                        >
                            Select all
                        </button>
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <button
                            type="button"
                            class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                            @click="clearLibrarySelection"
                        >
                            Clear
                        </button>
                        <span v-if="selectedLibraryCount > 0" class="text-gray-600 dark:text-gray-400">
                            {{ selectedLibraryCount }} selected
                        </span>
                    </div>
                    <ul class="mt-3 max-h-72 divide-y divide-gray-200 overflow-y-auto rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-600">
                        <li v-for="m in libraryModels" :key="m.slug" class="px-3 py-2">
                            <label
                                :for="`library-modal-${m.slug}`"
                                class="flex cursor-pointer items-start gap-3 text-sm text-gray-900 dark:text-gray-100"
                            >
                                <input
                                    :id="`library-modal-${m.slug}`"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    :checked="isLibrarySelected(m.slug)"
                                    @change="setLibrarySelected(m.slug, $event.target.checked)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ m.label }}</span>
                                        <span
                                            v-if="m.already_imported"
                                            class="rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            On your list
                                        </span>
                                    </span>
                                    <span class="mt-0.5 block font-mono text-xs text-gray-500 dark:text-gray-400">{{ m.slug }}</span>
                                </span>
                            </label>
                        </li>
                    </ul>
                </div>
                <div
                    v-else
                    class="rounded-md border border-dashed border-gray-300 bg-gray-50/80 px-3 py-4 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/40 dark:text-gray-400"
                >
                    <p class="font-medium text-gray-800 dark:text-gray-200">No catalog assets for this brand in inventory</p>
                    <p class="mt-2">
                        Add assets in your inventory database with slug
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">{{ record.brand_key }}--your-model-slug</code>
                        (brand key
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">{{ record.brand_key }}</code>
                        ).
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="importDiscoveredForm.processing"
                    @click="closeImportModelsModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                    :disabled="selectedLibraryCount === 0 || importDiscoveredForm.processing || libraryModels.length === 0"
                    @click="importSelectedLibraryModels"
                >
                    {{ importDiscoveredForm.processing ? 'Starting…' : 'Import selected' }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal
        v-if="boatMakeAiCatalogUiEnabled"
        :show="showAddModelModal"
        max-width="lg"
        @close="closeAddModelModal"
    >
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add catalog model</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        AI uses this brand and the model name you enter to build metadata in the shared inventory
                        database. You can then import it like any other catalog line.
                    </p>
                </div>
                <button
                    type="button"
                    class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                    @click="closeAddModelModal"
                >
                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Close</span>
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Brand</p>
                    <p class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ record.display_name }}</p>
                    <p class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">{{ record.brand_key }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="add-model-label">Model name</label>
                    <input
                        id="add-model-label"
                        v-model="aiForm.model_label"
                        type="text"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        placeholder="e.g. Oceanus, G23 Paragon, Lammina AL"
                        autocomplete="off"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Enter the series or model line the way you would search for it. A catalog slug is derived
                        automatically.
                    </p>
                    <p v-if="aiModelLabelError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ aiModelLabelError }}</p>
                    <p v-if="aiBrandError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ aiBrandError }}</p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="aiForm.processing"
                    @click="closeAddModelModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                    :disabled="aiForm.processing || !aiForm.model_label?.trim()"
                    @click="submitAddModelFromModal"
                >
                    {{ aiForm.processing ? 'Working…' : 'Generate with AI & add to catalog' }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showRefreshCatalogModal" max-width="md" @close="closeRefreshCatalogModal">
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Refresh from catalog?</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        This will overwrite this brand's catalog-linked fields with the latest shared inventory data:
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                        <li>Brand name</li>
                        <li>Website</li>
                        <li>Description</li>
                        <li>Catalog logo settings</li>
                    </ul>
                    <p class="mt-3 text-sm font-medium text-amber-800 dark:text-amber-200">
                        Any custom edits to those fields on this brand will be replaced.
                    </p>
                </div>
                <button
                    type="button"
                    class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                    :disabled="refreshCatalogBusy"
                    @click="closeRefreshCatalogModal"
                >
                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Close</span>
                </button>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    :disabled="refreshCatalogBusy"
                    @click="closeRefreshCatalogModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                    :disabled="refreshCatalogBusy"
                    @click="confirmRefreshFromCatalog"
                >
                    {{ refreshCatalogBusy ? 'Refreshing…' : 'Refresh from catalog' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
