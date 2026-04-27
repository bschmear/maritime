<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, shallowRef } from 'vue';

const ASSET_TYPE_ENUM_KEY = 'App\\Enums\\Inventory\\AssetType';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
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
    recordType: {
        type: String,
        default: 'boatmakes',
    },
    recordTitle: {
        type: String,
        default: 'Brand',
    },
    pluralTitle: {
        type: String,
        default: 'Brands',
    },
    manufacturers: {
        type: Array,
        default: () => [],
    },
    existingBrandKeys: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const showManufacturersModal = ref(false);
const showManualModal = ref(false);
const manufacturerSearch = ref('');
const selectedSlugs = shallowRef(new Set());
const busy = ref(false);

const manualForm = reactive({
    display_name: '',
    asset_types: [1],
    confirm_tenant_duplicate: false,
    confirm_catalog_match: false,
});
const manualBusy = ref(false);
const manualFieldErrors = ref({});
const duplicatePrompt = ref(null);

const assetTypeOptions = computed(() => {
    const raw = props.enumOptions[ASSET_TYPE_ENUM_KEY];
    if (raw && raw.length) {
        return raw;
    }
    return [
        { id: 1, value: 1, name: 'Boat' },
        { id: 2, value: 2, name: 'Engine' },
        { id: 3, value: 3, name: 'Trailer' },
        { id: 4, value: 4, name: 'Other' },
    ];
});

const breadcrumbItems = computed(() => {
    return [
        { label: 'Home', href: route('dashboard') },
        { label: props.pluralTitle },
    ];
});

const filteredManufacturers = computed(() => {
    const q = manufacturerSearch.value.trim().toLowerCase();
    if (!q) {
        return props.manufacturers;
    }
    return props.manufacturers.filter((m) => {
        const name = (m.display_name ?? '').toLowerCase();
        const slug = (m.slug ?? '').toLowerCase();

        return name.includes(q) || slug.includes(q);
    });
});

function isAlreadyAdded(slug) {
    return props.existingBrandKeys.includes(slug);
}

function isSelected(slug) {
    return selectedSlugs.value.has(slug);
}

function onCheckboxChange(slug, event) {
    if (isAlreadyAdded(slug)) {
        event.target.checked = false;

        return;
    }
    const checked = event.target.checked;
    const next = new Set(selectedSlugs.value);
    if (checked) {
        next.add(slug);
    } else {
        next.delete(slug);
    }
    selectedSlugs.value = next;
}

function openManufacturersModal() {
    manufacturerSearch.value = '';
    selectedSlugs.value = new Set();
    showManufacturersModal.value = true;
}

function closeManufacturersModal() {
    showManufacturersModal.value = false;
}

function resetManualForm() {
    manualForm.display_name = '';
    manualForm.asset_types = [1];
    manualForm.confirm_tenant_duplicate = false;
    manualForm.confirm_catalog_match = false;
    manualFieldErrors.value = {};
    duplicatePrompt.value = null;
}

function openManualModal() {
    resetManualForm();
    showManualModal.value = true;
}

function closeManualModal() {
    showManualModal.value = false;
    resetManualForm();
}

function openManualFromCatalog() {
    closeManufacturersModal();
    openManualModal();
}

function toggleAssetType(id) {
    const n = Number(id);
    const arr = [...manualForm.asset_types];
    const i = arr.indexOf(n);
    if (i === -1) {
        arr.push(n);
    } else {
        arr.splice(i, 1);
    }
    if (arr.length === 0) {
        arr.push(1);
    }
    manualForm.asset_types = arr;
}

const selectedCount = computed(() => selectedSlugs.value.size);

function submitBulk() {
    if (selectedSlugs.value.size === 0) {
        return;
    }
    busy.value = true;
    router.post(
        route('boatmakes.bulk-from-catalog'),
        { brand_keys: [...selectedSlugs.value], asset_types: [1] },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                selectedSlugs.value = new Set();
                closeManufacturersModal();
            },
        }
    );
}

async function submitManual() {
    manualBusy.value = true;
    manualFieldErrors.value = {};
    duplicatePrompt.value = null;
    try {
        const { data } = await axios.post(route('boatmakes.manual'), {
            display_name: manualForm.display_name.trim(),
            asset_types: manualForm.asset_types,
            confirm_tenant_duplicate: manualForm.confirm_tenant_duplicate,
            confirm_catalog_match: manualForm.confirm_catalog_match,
        });
        if (data.success) {
            closeManualModal();
            router.reload({ preserveScroll: true });
        }
    } catch (e) {
        const status = e.response?.status;
        const body = e.response?.data;
        if (status === 422 && body?.code) {
            duplicatePrompt.value = body;
        } else if (body?.errors) {
            manualFieldErrors.value = body.errors;
        }
    } finally {
        manualBusy.value = false;
    }
}

function viewExistingBrand() {
    const id = duplicatePrompt.value?.existing?.id;
    if (!id) {
        return;
    }
    duplicatePrompt.value = null;
    closeManualModal();
    router.visit(route('boatmakes.show', id));
}

function confirmTenantNotMineCreate() {
    manualForm.confirm_tenant_duplicate = true;
    duplicatePrompt.value = null;
    submitManual();
}

function openCatalogFromCatalogPrompt() {
    duplicatePrompt.value = null;
    manualForm.confirm_catalog_match = false;
    closeManualModal();
    openManufacturersModal();
}

function confirmCatalogManualAnyway() {
    manualForm.confirm_catalog_match = true;
    duplicatePrompt.value = null;
    submitManual();
}
</script>

<template>
    <Head :title="recordTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <Breadcrumb :items="breadcrumbItems" />
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 mt-4">
                        {{ recordTitle }}
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="openManualModal"
                    >
                        Add manually
                    </button> -->
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                        @click="openManufacturersModal"
                    >
                        What brands do you work with?
                    </button>
                </div>
            </div>
        </template>

        <div
            v-if="flashSuccess"
            class="col-span-full mb-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        >
            {{ flashSuccess }}
        </div>

        <Modal :show="showManufacturersModal" max-width="2xl" @close="closeManufacturersModal">
            <div class="flex max-h-[90vh] flex-col">
                <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                            What brands do you work with?
                        </h3>
                        <p class="mt-1 text-md text-gray-500 dark:text-gray-400">
                            Select manufacturers to add. Each uses a stable catalog key (slug) shared with the inventory
                            database.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="closeManufacturersModal"
                    >
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close</span>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div class="mb-3 space-y-2">
                        <label class="sr-only" for="manufacturer-search">Filter manufacturers</label>
                        <input
                            id="manufacturer-search"
                            v-model="manufacturerSearch"
                            type="search"
                            placeholder="Search by name or slug…"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-md text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            class="text- font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                            @click="openManualFromCatalog"
                        >
                            I don't see my brand…
                        </button>
                    </div>
                    <div
                        class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700"
                    >
                        <p
                            v-if="filteredManufacturers.length === 0"
                            class="px-3 py-8 text-center text-md text-gray-500 dark:text-gray-400"
                        >
                            No manufacturers match your search. Try another term, or add your brand manually.
                        </p>
                        <label
                            v-for="m in filteredManufacturers"
                            :key="m.slug"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2 text-md hover:bg-gray-50 dark:hover:bg-gray-800/80"
                            :class="isAlreadyAdded(m.slug) ? 'cursor-not-allowed opacity-50' : ''"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                :checked="isSelected(m.slug)"
                                :disabled="isAlreadyAdded(m.slug)"
                                @change="onCheckboxChange(m.slug, $event)"
                            />
                            <span class="text-gray-800 dark:text-gray-200">{{ m.display_name }}</span>
                            <span class="ml-auto font-mono text-sm text-gray-400">{{ m.slug }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
                    <!-- <button
                        type="button"
                        class="mr-auto text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                        @click="openManualFromCatalog"
                    >
                        Add manually
                    </button> -->
                    <span v-if="selectedCount > 0" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ selectedCount }} selected
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeManufacturersModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        :disabled="busy || selectedCount === 0"
                        @click="submitBulk"
                    >
                        Add selected brands
                    </button>
                </div>
            </div>
        </Modal>

        <!-- <Modal :show="showManualModal" max-width="lg" @close="closeManualModal">
            <div class="flex max-h-[90vh] flex-col">
                <div class="flex shrink-0 items-start justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add brand manually</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Manual brands are stored only for your dealership and are not linked to the shared inventory
                            catalog.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="closeManualModal"
                    >
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close</span>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div
                        v-if="duplicatePrompt?.code === 'TENANT_DUPLICATE'"
                        class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-200"
                    >
                        <p class="font-medium text-gray-900 dark:text-white">{{ duplicatePrompt.message }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            Existing: <span class="font-semibold">{{ duplicatePrompt.existing?.display_name }}</span>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                                @click="viewExistingBrand"
                            >
                                Yes — open this brand
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="confirmTenantNotMineCreate"
                            >
                                No — add another manual entry
                            </button>
                        </div>
                    </div>

                    <div
                        v-else-if="duplicatePrompt?.code === 'CATALOG_MATCH'"
                        class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800/60 dark:text-gray-200"
                    >
                        <p class="font-medium text-gray-900 dark:text-white">{{ duplicatePrompt.message }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            Matched catalog: <span class="font-semibold">{{ duplicatePrompt.catalog?.display_name }}</span>
                            <span class="ml-1 font-mono text-gray-500">({{ duplicatePrompt.catalog?.slug }})</span>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                                @click="openCatalogFromCatalogPrompt"
                            >
                                Add from manufacturer list
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="confirmCatalogManualAnyway"
                            >
                                Create manual brand without catalog link
                            </button>
                        </div>
                    </div>

                    <template v-else>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Brand name</label>
                            <input
                                v-model="manualForm.display_name"
                                type="text"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                autocomplete="organization"
                            />
                            <p v-if="manualFieldErrors.display_name" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ Array.isArray(manualFieldErrors.display_name) ? manualFieldErrors.display_name[0] : manualFieldErrors.display_name }}
                            </p>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-600 dark:text-gray-400">Applies to asset types</span>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label
                                    v-for="opt in assetTypeOptions"
                                    :key="opt.value ?? opt.id"
                                    class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                        :checked="manualForm.asset_types.includes(Number(opt.value ?? opt.id))"
                                        @change="toggleAssetType(opt.value ?? opt.id)"
                                    />
                                    <span>{{ opt.name }}</span>
                                </label>
                            </div>
                            <p v-if="manualFieldErrors.asset_types" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ Array.isArray(manualFieldErrors.asset_types) ? manualFieldErrors.asset_types[0] : manualFieldErrors.asset_types }}
                            </p>
                        </div>
                    </template>
                </div>

                <div
                    v-if="!duplicatePrompt"
                    class="flex shrink-0 justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeManualModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="manualBusy || !manualForm.display_name.trim()"
                        @click="submitManual"
                    >
                        Create brand
                    </button>
                </div>
            </div>
        </Modal> -->

        <Table
            :records="records"
            :schema="schema"
            :form-schema="formSchema"
            :fields-schema="fieldsSchema"
            :enum-options="enumOptions"
            :record-type="recordType"
            :record-title="recordTitle"
            :plural-title="pluralTitle"
        />
    </TenantLayout>
</template>
