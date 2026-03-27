<script setup>
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue:     { type: Boolean, default: false },
    linkedAssetIds: { type: Array,   default: () => [] },
    storeUrl:       { type: String,  required: true },
    unitsBaseUrl:   { type: String,  required: true },
});

const emit = defineEmits(['update:modelValue', 'attached']);

// ── Asset list state ───────────────────────────────────────────────────────
const assets        = ref([]);
const searchQuery   = ref('');
const loadingAssets = ref(false);
const currentPage   = ref(1);
const totalPages    = ref(1);
const perPage       = 10;

// ── Selection state ────────────────────────────────────────────────────────
const selectedAssetId   = ref(null);
const selectedAssetName = ref('');

// ── Unit state ─────────────────────────────────────────────────────────────
const units          = ref([]);
const selectedUnitId = ref('');
const loadingUnits   = ref(false);

// ── Form state ─────────────────────────────────────────────────────────────
const submitting = ref(false);
const errorMsg   = ref(null);

// ── Helpers ────────────────────────────────────────────────────────────────
function getDisplayName(asset) {
    if (asset.display_name) return asset.display_name;
    if (asset.name)         return asset.name;
    return `Asset #${asset.id}`;
}

function close() {
    emit('update:modelValue', false);
}

function reset() {
    selectedAssetId.value   = null;
    selectedAssetName.value = '';
    units.value             = [];
    selectedUnitId.value    = '';
    errorMsg.value          = null;
    searchQuery.value       = '';
    currentPage.value       = 1;
    assets.value            = [];
}

// ── Fetch assets ───────────────────────────────────────────────────────────
async function fetchAssets() {
    loadingAssets.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'asset');
        url.searchParams.append('page', currentPage.value);
        url.searchParams.append('per_page', perPage);
        if (searchQuery.value.trim()) {
            url.searchParams.append('search', searchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (data.records) {
            const excluded = new Set(props.linkedAssetIds.map(Number));
            assets.value     = data.records.filter(r => !excluded.has(Number(r.id)));
            totalPages.value = data.meta?.last_page ?? 1;
        } else {
            assets.value = [];
        }
    } catch {
        assets.value = [];
    } finally {
        loadingAssets.value = false;
    }
}

function selectAsset(asset) {
    selectedAssetId.value   = asset.id;
    selectedAssetName.value = getDisplayName(asset);
}

function prevPage() {
    if (currentPage.value > 1) { currentPage.value--; fetchAssets(); }
}

function nextPage() {
    if (currentPage.value < totalPages.value) { currentPage.value++; fetchAssets(); }
}

// Debounced search — resets to page 1 then re-fetches
let searchTimer = null;
watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentPage.value = 1;
        fetchAssets();
    }, 250);
});

// Load units whenever a new asset is selected
watch(selectedAssetId, async (id) => {
    selectedUnitId.value = '';
    units.value          = [];
    if (!id) return;
    loadingUnits.value = true;
    try {
        const { data } = await axios.get(props.unitsBaseUrl, { params: { asset_id: id } });
        units.value = data.units ?? [];
    } catch {
        units.value = [];
    } finally {
        loadingUnits.value = false;
    }
});

// Reset state and fetch first page whenever the modal opens
watch(() => props.modelValue, (open) => {
    if (open) { reset(); fetchAssets(); }
});

// ── Submit ─────────────────────────────────────────────────────────────────
async function submit() {
    if (!selectedAssetId.value) return;
    submitting.value = true;
    errorMsg.value   = null;
    try {
        await axios.post(props.storeUrl, {
            asset_id:      Number(selectedAssetId.value),
            asset_unit_id: selectedUnitId.value ? Number(selectedUnitId.value) : null,
        });
        emit('attached');
        close();
    } catch (e) {
        errorMsg.value =
            e.response?.data?.message ??
            (e.response?.data?.errors
                ? Object.values(e.response.data.errors).flat().join(' ')
                : null) ??
            'Could not add asset.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50"
                @click.self="close"
            >
                <div
                    class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-lg shadow-xl dark:bg-gray-800 flex flex-col"
                    @click.stop
                >
                    <!-- ── Header ──────────────────────────────────────── -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Add asset to event
                        </h3>
                        <button
                            type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                            @click="close"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- ── Body ───────────────────────────────────────── -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">

                        <!-- Error banner -->
                        <div
                            v-if="errorMsg"
                            class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errorMsg }}
                        </div>

                        <!-- Search -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search assets..."
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                            />
                        </div>

                        <!-- Loading -->
                        <div v-if="loadingAssets" class="flex justify-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </div>

                        <!-- Asset list -->
                        <div v-else-if="assets.length > 0" class="space-y-2">
                            <div
                                v-for="asset in assets"
                                :key="asset.id"
                                class="p-3 border rounded-lg cursor-pointer transition-colors"
                                :class="selectedAssetId === asset.id
                                    ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
                                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                @click="selectAsset(asset)"
                            >
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ getDisplayName(asset) }}
                                    </p>
                                    <div v-if="selectedAssetId === asset.id" class="ml-2 shrink-0">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="mt-2">
                                {{ searchQuery ? 'No assets match your search.' : 'No assets available.' }}
                            </p>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="!loadingAssets && assets.length > 0 && totalPages > 1"
                            class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700"
                        >
                            <button
                                :disabled="currentPage === 1"
                                class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                @click="prevPage"
                            >
                                Previous
                            </button>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Page {{ currentPage }} of {{ totalPages }}
                            </span>
                            <button
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                @click="nextPage"
                            >
                                Next
                            </button>
                        </div>

                        <!-- ── Unit picker ─────────────────────────────── -->
                        <div
                            v-if="selectedAssetId"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 p-3 space-y-2"
                        >
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Unit <span class="normal-case font-normal">(optional)</span>
                            </p>

                            <div v-if="loadingUnits" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 py-1">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Loading units…
                            </div>

                            <select
                                v-else
                                v-model="selectedUnitId"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                            >
                                <option value="">No specific unit</option>
                                <option v-for="u in units" :key="u.id" :value="String(u.id)">
                                    {{ u.display_name }}
                                </option>
                            </select>

                            <p v-if="!loadingUnits && units.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                No units on file for this asset.
                            </p>
                        </div>

                    </div>

                    <!-- ── Footer ─────────────────────────────────────── -->
                    <div class="flex items-center justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                            @click="close"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="!selectedAssetId || submitting"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="submit"
                        >
                            {{ submitting ? 'Adding…' : 'Add to event' }}
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>