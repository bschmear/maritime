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

const catalogRows = computed(() => props.catalogPreview.catalog_rows ?? []);

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

const importableCount = computed(() => catalogRows.value.filter((r) => !r.already_imported).length);

/** Nothing left to pull in, or no catalog rows at all. */
const canImportAny = computed(() => importableCount.value > 0);

const importBusy = ref(false);

function importAll() {
    if (!canImportAny.value) {
        return;
    }
    importBusy.value = true;
    router.post(
        route('boatmakes.catalog-import', props.record.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                importBusy.value = false;
            },
        }
    );
}

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
});

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
        <template v-if="record.brand_key" #prepend>
            <div
                id="boat-make-models-panel"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Models for this brand</h3>
                <p class="mt-1 mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Import lines from the inventory database (same catalog as below), pull everything onto your tenant
                    list<template v-if="boatMakeAiCatalogUiEnabled"
                        >, or use Add model to create a catalog line with AI</template
                    >.
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
                <div
                    v-if="libraryModels.length > 0"
                    class="mb-3 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/40"
                >
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Inventory catalog (library)</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        These rows live in your inventory database. Select lines to import; each runs as its own
                        background job, then syncs. Rows you already have are skipped.
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
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
                    <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="m in libraryModels" :key="m.slug" class="py-2">
                            <label
                                :for="`library-${m.slug}`"
                                class="flex cursor-pointer items-start gap-3 text-sm text-gray-900 dark:text-gray-100"
                            >
                                <input
                                    :id="`library-${m.slug}`"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    :checked="isLibrarySelected(m.slug)"
                                    @change="setLibrarySelected(m.slug, $event.target.checked)"
                                />
                                <span class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm sm:flex-row">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Import</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ m.label }}</span>
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ m.slug }}</span>
                                </span>
                            </label>
                        </li>
                    </ul>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                            :disabled="selectedLibraryCount === 0 || importDiscoveredForm.processing"
                            @click="importSelectedLibraryModels"
                        >
                            {{ importDiscoveredForm.processing ? 'Starting…' : 'Import selected' }}
                        </button>
                    </div>
                </div>
                <div
                    v-else
                    class="mb-3 rounded-md border border-dashed border-gray-300 bg-gray-50/80 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/40 dark:text-gray-400"
                >
                    <p class="font-medium text-gray-800 dark:text-gray-200">No catalog assets for this brand in inventory</p>
                    <p class="mt-1">
                        Add the make and assets in your inventory database (connection
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">inventory</code>
                        , env
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">INVENTORY_DATABASE</code>
                        ). Asset slug must be
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">{{ record.brand_key }}--your-model-slug</code>
                        to match brand key
                        <code class="rounded bg-gray-200 px-1 py-0.5 font-mono text-xs dark:bg-gray-700">{{ record.brand_key }}</code>
                        .
                    </p>
                </div>
                <div v-if="catalogRows.length === 0" class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                    <p class="font-medium text-gray-800 dark:text-gray-200">Nothing from the shared catalog on your list yet</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        <template v-if="boatMakeAiCatalogUiEnabled">
                            Use the library above or
                            <span class="font-medium text-gray-800 dark:text-gray-200">Add model</span>
                            to create a line in the inventory catalog with AI. When lines exist, you can use &quot;Add
                            all models&quot; to pull them onto your tenant list.
                        </template>
                        <template v-else>
                            When lines appear in the library above, import them or use &quot;Add all models&quot; to pull
                            them onto your tenant list.
                        </template>
                    </p>
                </div>
                <div
                    v-else-if="importableCount === 0"
                    class="mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    Every catalog model for this brand is already on your list.
                </div>
                <ul
                    v-if="catalogRows.length > 0"
                    class="mb-3 max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-gray-800 dark:border-gray-700"
                >
                    <li
                        v-for="row in catalogRows"
                        :key="row.catalog_asset_key"
                        class="flex items-center gap-2 px-3 py-2 text-sm"
                        :class="row.already_imported ? 'bg-gray-50 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100'"
                    >
                        <span class="font-medium">{{ row.display_name }}</span>
                        <span class="ml-auto font-mono text-sm text-gray-500 dark:text-gray-400">{{ row.catalog_asset_key }}</span>
                        <span v-if="row.already_imported" class="shrink-0 text-sm text-gray-500 dark:text-gray-400">On your list</span>
                    </li>
                </ul>
                <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        :disabled="importBusy || !canImportAny"
                        @click="importAll"
                    >
                        Add all models
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
</template>
