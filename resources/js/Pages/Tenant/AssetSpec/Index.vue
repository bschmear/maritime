<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SpecLibrary from '@/Components/Tenant/AssetSpec/SpecLibrary.vue';
import SelectedSpecsPanel from '@/Components/Tenant/AssetSpec/SelectedSpecsPanel.vue';
import SpecPreview from '@/Components/Tenant/AssetSpec/SpecPreview.vue';
import AddSpecModal from '@/Components/Tenant/AssetSpec/AddSpecModal.vue';

const props = defineProps({
    specs: Array,
    groups: Array,
    types: Array,
    filters: Object,
});

const selectedSpecs = ref([]);
const showAddModal = ref(false);
const selectedAssetType = ref(props.filters.asset_type || 1);

// Seed selectedSpecs from specs that are already visible (or required) for the given asset type
const seedVisibleSpecs = (assetType) => {
    const visible = (props.specs || []).filter(
        s => Array.isArray(s.asset_types) && s.asset_types.includes(assetType) && (s.is_required || s.is_visible)
    );
    selectedSpecs.value = visible;
};

// Re-seed when asset type changes (also runs immediately on mount)
watch(selectedAssetType, (newType) => {
    seedVisibleSpecs(newType);
}, { immediate: true });

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset Specs' },
]);

const addSpecToSelected = (spec) => {
    if (selectedSpecs.value.find(s => s.id === spec.id)) return;

    // Optimistically update local state
    selectedSpecs.value.push({ ...spec, is_visible: true });

    // Persist to backend
    router.put(
        route('asset-specs.update', { assetSpec: spec.id }),
        { ...spec, is_visible: true },
        { preserveScroll: true, preserveState: true }
    );
};

const removeSpecFromSelected = (specId) => {
    const spec = selectedSpecs.value.find(s => s.id === specId);
    if (!spec) return;
    if (spec.is_required) {
        alert('Cannot remove required specs');
        return;
    }

    // Optimistically update local state
    selectedSpecs.value = selectedSpecs.value.filter(s => s.id !== specId);

    // Persist to backend
    router.put(
        route('asset-specs.update', { assetSpec: specId }),
        { ...spec, is_visible: false },
        { preserveScroll: true, preserveState: true }
    );
};

const reorderSpecs = (specs) => {
    selectedSpecs.value = specs;
    
    // Update positions in backend
    const updates = specs.map((spec, index) => ({
        id: spec.id,
        position: index + 1
    }));
    
    router.post(route('asset-specs.reorder'), {
        specs: updates
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const assetTypeOptions = [
    { value: 1, label: 'Boat' },
    { value: 2, label: 'Engine' },
    { value: 3, label: 'Trailer' },
    { value: 4, label: 'Other' },
];
</script>

<template>
    <Head title="Asset Spec Builder" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-col gap-3 mt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Asset Spec Builder
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Build and manage asset specifications for your inventory
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="showAddModal = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">add</span>
                            Create spec
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full">
            <div class="grid grid-cols-12 gap-4 p-4">
                <!-- Asset Type Selector -->
                <div class="col-span-12">
                    <div class="rounded-lg bg-white p-4 shadow-md dark:bg-gray-800">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Asset Type
                        </label>
                        <select
                            v-model="selectedAssetType"
                            class="block w-full max-w-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                        >
                            <option v-for="option in assetTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Spec Library (Left) -->
                <div class="col-span-12 lg:col-span-4">
                    <SpecLibrary
                        :specs="specs"
                        :groups="groups"
                        :types="types"
                        :selectedSpecs="selectedSpecs"
                        :assetType="selectedAssetType"
                        @add-spec="addSpecToSelected"
                    />
                </div>

                <!-- Selected Specs Panel (Middle) -->
                <div class="col-span-12 lg:col-span-4">
                    <SelectedSpecsPanel
                        :specs="selectedSpecs"
                        @remove-spec="removeSpecFromSelected"
                        @reorder="reorderSpecs"
                    />
                </div>

                <!-- Preview (Right) -->
                <div class="col-span-12 lg:col-span-4">
                    <SpecPreview :specs="selectedSpecs" />
                </div>
            </div>
        </div>

        <!-- Add Spec Modal -->
        <AddSpecModal
            v-if="showAddModal"
            :assetType="selectedAssetType"
            @close="showAddModal = false"
        />
    </TenantLayout>
</template>