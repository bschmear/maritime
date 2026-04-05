<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import SpecPreview from '@/Components/Tenant/AssetSpec/SpecPreview.vue';
import AddSpecModal from '@/Components/Tenant/AssetSpec/AddSpecModal.vue';
import SpecGroupTable from '@/Components/Tenant/AssetSpec/SpecGroupTable.vue';

const props = defineProps({
    specs: Array,
    specGroups: Array,
    types: Array,
    filters: Object,
});

const showAddModal = ref(false);
const savingSpecId = ref(null);
const deletingSpecId = ref(null);

const searchQuery = ref(props.filters?.search || '');
const selectedGroupId = ref(
    props.filters?.group_id != null && props.filters?.group_id !== ''
        ? String(props.filters.group_id)
        : ''
);
const selectedFieldType = ref(props.filters?.type || '');
const selectedAssetTypeFilter = ref(
    props.filters?.asset_type != null && props.filters?.asset_type !== ''
        ? String(props.filters.asset_type)
        : ''
);

const assetTypeColumns = [
    { id: 1, label: 'Boat' },
    { id: 3, label: 'Trailer' },
    { id: 2, label: 'Engine' },
    { id: 4, label: 'Other' },
];

const specGroupsList = computed(() => props.specGroups || []);

const localSpecs = ref([]);
const initialised = ref(false);

watch(
    () => props.specs,
    (specs) => {
        if (!initialised.value) {
            localSpecs.value = (specs || []).map((s) => ({ ...s }));
            initialised.value = true;
            return;
        }
        localSpecs.value = (specs || []).map((s) => {
            if (savingSpecId.value === s.id) {
                return localSpecs.value.find((ls) => ls.id === s.id) ?? { ...s };
            }
            return { ...s };
        });
    },
    { immediate: true, deep: true }
);

const normalizeAssetTypes = (raw) => {
    if (!raw || !Array.isArray(raw)) return [];
    return [...new Set(raw.map((t) => Number(t)).filter((n) => !Number.isNaN(n)))].sort((a, b) => a - b);
};

const blockKeyForSpec = (spec) => (spec.group_id == null ? '__none__' : String(spec.group_id));

function groupKeysInOrder(allSpecs, groups) {
    const present = new Set();
    allSpecs.forEach((s) => present.add(blockKeyForSpec(s)));
    const ordered = [];
    for (const g of [...groups].sort((a, b) => (a.position ?? 0) - (b.position ?? 0))) {
        if (present.has(String(g.id))) ordered.push(String(g.id));
    }
    for (const k of present) {
        if (k !== '__none__' && !ordered.includes(k)) ordered.push(k);
    }
    if (present.has('__none__')) ordered.push('__none__');
    return ordered;
}

const filteredSpecs = computed(() => {
    let list = [...localSpecs.value];

    if (selectedAssetTypeFilter.value !== '') {
        const id = Number(selectedAssetTypeFilter.value);
        list = list.filter((s) => normalizeAssetTypes(s.asset_types).includes(id));
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(
            (s) =>
                (s.label && s.label.toLowerCase().includes(q)) ||
                (s.key && s.key.toLowerCase().includes(q)) ||
                (s.group?.name && s.group.name.toLowerCase().includes(q)) ||
                (s.group?.key && s.group.key.toLowerCase().includes(q))
        );
    }

    if (selectedGroupId.value === '__none__') {
        list = list.filter((s) => !s.group_id);
    } else if (selectedGroupId.value) {
        list = list.filter((s) => String(s.group_id) === selectedGroupId.value);
    }

    if (selectedFieldType.value) {
        list = list.filter((s) => s.type === selectedFieldType.value);
    }

    return list.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
});

const groupedFilteredMap = computed(() => {
    const m = new Map();
    filteredSpecs.value.forEach((spec) => {
        const k = blockKeyForSpec(spec);
        if (!m.has(k)) m.set(k, []);
        m.get(k).push(spec);
    });
    m.forEach((arr) => arr.sort((a, b) => (a.position ?? 0) - (b.position ?? 0)));
    return m;
});

const orderedGroupBlocks = computed(() => {
    const m = groupedFilteredMap.value;
    let keys;
    if (hasActiveFilters.value) {
        keys = groupKeysInOrder(localSpecs.value, specGroupsList.value).filter((k) => m.has(k));
    } else {
        keys = [...specGroupsList.value]
            .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
            .map((g) => String(g.id));
        keys.push('__none__');
    }
    return keys.map((k) => {
        const specs = m.get(k) ?? [];
        const label =
            k === '__none__'
                ? 'Ungrouped'
                : specGroupsList.value.find((g) => String(g.id) === k)?.name
                    ?? specs[0]?.group?.name
                    ?? 'Group';
        return { blockKey: k, label, specs };
    });
});

const hasActiveFilters = computed(
    () =>
        Boolean(
            searchQuery.value.trim() ||
            selectedGroupId.value ||
            selectedFieldType.value ||
            selectedAssetTypeFilter.value !== ''
        )
);

const clearFilters = () => {
    searchQuery.value = '';
    selectedGroupId.value = '';
    selectedFieldType.value = '';
    selectedAssetTypeFilter.value = '';
};

const buildUpdatePayload = (spec) => ({
    label: spec.label,
    group_id: spec.group_id ?? null,
    type: spec.type,
    unit: spec.unit ?? null,
    unit_imperial: spec.unit_imperial ?? null,
    unit_metric: spec.unit_metric ?? null,
    use_metric: Boolean(spec.use_metric),
    options: spec.options ?? null,
    is_filterable: Boolean(spec.is_filterable),
    is_visible: Boolean(spec.is_visible),
    is_required: Boolean(spec.is_required),
    show_on_table: Boolean(spec.show_on_table),
    position: spec.position ?? 0,
    asset_types: normalizeAssetTypes(spec.asset_types),
});

const deleteSpec = (spec) => {
    if (spec.is_required) return;
    if (
        !window.confirm(
            `Delete "${spec.label}"? This removes the spec and any values saved on assets.`
        )
    ) {
        return;
    }
    deletingSpecId.value = spec.id;
    router.delete(route('asset-specs.destroy', { assetSpec: spec.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingSpecId.value = null;
        },
    });
};

const toggleShowOnTable = ({ spec, checked, done }) => {
    const idx = localSpecs.value.findIndex((s) => s.id === spec.id);
    if (idx === -1) {
        done();
        return;
    }

    const updated = { ...localSpecs.value[idx], show_on_table: checked };
    localSpecs.value[idx] = updated;
    savingSpecId.value = spec.id;

    router.put(
        route('asset-specs.update', { assetSpec: spec.id }),
        buildUpdatePayload(updated),
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                savingSpecId.value = null;
                done();
            },
            onError: () => {
                localSpecs.value = (props.specs || []).map((s) => ({ ...s }));
                done();
            },
        },
    );
};

const toggleAssetType = ({ spec, typeId, checked, done }) => {
    const idx = localSpecs.value.findIndex((s) => s.id === spec.id);
    if (idx === -1) {
        done();
        return;
    }

    const types = normalizeAssetTypes(localSpecs.value[idx].asset_types);
    const numericTypeId = Number(typeId);

    const nextTypes = checked
        ? [...new Set([...types, numericTypeId])].sort((a, b) => a - b)
        : types.filter((t) => t !== numericTypeId);

    const updated = { ...localSpecs.value[idx], asset_types: nextTypes };

    // optimistic update (keeps UI in sync with child)
    localSpecs.value[idx] = updated;

    savingSpecId.value = spec.id;

    router.put(
        route('asset-specs.update', { assetSpec: spec.id }),
        buildUpdatePayload(updated),
        {
            preserveScroll: true,
            preserveState: true,

            onFinish: () => {
                savingSpecId.value = null;
                done(); // 🔥 REQUIRED
            },

            onError: () => {
                // rollback
                localSpecs.value = (props.specs || []).map((s) => ({ ...s }));
                done(); // 🔥 ALSO REQUIRED HERE
            },
        }
    );
};

const onGroupReorder = (draggedBlockKey, newOrderForVisible) => {
    if (!newOrderForVisible?.length) return;

    const dragKey = String(draggedBlockKey);
    const visibleIds = new Set(newOrderForVisible.map((s) => s.id));

    const byGroup = {};
    localSpecs.value.forEach((s) => {
        const k = blockKeyForSpec(s);
        if (!byGroup[k]) byGroup[k] = [];
        byGroup[k].push(s);
    });

    const groupKeysOrdered = groupKeysInOrder(localSpecs.value, specGroupsList.value);

    const mergedByGroup = {};
    for (const k of groupKeysOrdered) {
        const inGroup = [...(byGroup[k] || [])].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
        if (k === dragKey) {
            const hidden = inGroup
                .filter((s) => !visibleIds.has(s.id))
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
            mergedByGroup[k] = [...newOrderForVisible, ...hidden];
        } else {
            mergedByGroup[k] = inGroup;
        }
    }

    const flat = groupKeysOrdered.flatMap((k) => mergedByGroup[k] || []);
    const updates = flat.map((spec, i) => ({
        id: spec.id,
        position: i + 1,
    }));

    flat.forEach((spec, i) => {
        const idx = localSpecs.value.findIndex((s) => s.id === spec.id);
        if (idx !== -1) {
            localSpecs.value[idx] = { ...localSpecs.value[idx], position: i + 1 };
        }
    });

    router.post(
        route('asset-specs.reorder'),
        { specs: updates },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localSpecs.value = (props.specs || []).map((s) => ({ ...s }));
            },
        }
    );
};

const moveGroup = (blockKey, direction) => {
    const keys = orderedGroupBlocks.value.map((b) => b.blockKey);
    const idx = keys.indexOf(blockKey);
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= keys.length) return;

    // Find the two groups in specGroupsList and swap their positions
    const groupA = specGroupsList.value.find((g) => String(g.id) === keys[idx]);
    const groupB = specGroupsList.value.find((g) => String(g.id) === keys[swapIdx]);
    if (!groupA || !groupB) return;

    const posA = groupA.position ?? idx;
    const posB = groupB.position ?? swapIdx;

    router.post(
        route('spec-groups.reorder'),
        {
            groups: [
                { id: groupA.id, position: posB },
                { id: groupB.id, position: posA },
            ],
        },
        { preserveScroll: true, preserveState: true }
    );
};

const applyCrossGroupMove = (allSpecs, payload, groups) => {
    const { specId, toBlockKey, newIndex } = payload;
    const normalized = allSpecs.map((s) => ({ ...s }));
    const movedIdx = normalized.findIndex((s) => s.id === specId);
    if (movedIdx === -1) return allSpecs;

    const moved = { ...normalized[movedIdx] };
    const newGroupId = toBlockKey === '__none__' ? null : Number(toBlockKey);
    moved.group_id = newGroupId;
    const gMeta = newGroupId ? groups.find((x) => Number(x.id) === newGroupId) : null;
    moved.group = gMeta ? { id: gMeta.id, name: gMeta.name, key: gMeta.key } : null;

    const without = normalized.filter((s) => s.id !== specId);
    const byKey = {};
    without.forEach((s) => {
        const k = blockKeyForSpec(s);
        if (!byKey[k]) byKey[k] = [];
        byKey[k].push(s);
    });
    Object.keys(byKey).forEach((k) => byKey[k].sort((a, b) => (a.position ?? 0) - (b.position ?? 0)));

    const toList = [...(byKey[toBlockKey] || [])];
    const insertAt = Math.max(0, Math.min(newIndex ?? toList.length, toList.length));
    toList.splice(insertAt, 0, moved);
    byKey[toBlockKey] = toList;

    const allInByKey = Object.values(byKey).flat();
    const keys = groupKeysInOrder(allInByKey, groups);
    const flat = [];
    for (const k of keys) {
        (byKey[k] || []).forEach((s) => flat.push(s));
    }
    return flat.map((s, i) => ({ ...s, position: i + 1 }));
};

const onCrossGroupMove = (payload) => {
    if (hasActiveFilters.value) return;
    const next = applyCrossGroupMove(localSpecs.value, payload, specGroupsList.value);
    const movedSpec = next.find((s) => s.id === payload.specId);
    if (!movedSpec) return;

    localSpecs.value = next;

    router.put(
        route('asset-specs.update', { assetSpec: movedSpec.id }),
        buildUpdatePayload(movedSpec),
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localSpecs.value = (props.specs || []).map((s) => ({ ...s }));
            },
            onSuccess: () => {
                router.post(
                    route('asset-specs.reorder'),
                    { specs: next.map((s) => ({ id: s.id, position: s.position })) },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onError: () => {
                            localSpecs.value = (props.specs || []).map((s) => ({ ...s }));
                        },
                    },
                );
            },
        },
    );
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Asset Specs' },
]);

const previewSpecs = computed(() =>
    filteredSpecs.value
        .filter((s) => normalizeAssetTypes(s.asset_types).length > 0)
        .slice()
        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
);
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
                            Manage spec groups, filter by field type, assign asset types, drag to reorder within each group
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
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
                <!-- <div class="col-span-12">
                    <SpecGroupsManager :groups="specGroupsList" />
                </div> -->

                <!-- Filters -->
                <div class="col-span-12">
                    <div class="rounded-lg bg-white p-4 shadow-md dark:bg-gray-800">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Filters</h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Search
                                </label>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Label, key, group…"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Field type
                                </label>
                                <select
                                    v-model="selectedFieldType"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                >
                                    <option value="">All types</option>
                                    <option v-for="t in types || []" :key="t" :value="t">
                                        {{ t }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Group
                                </label>
                                <select
                                    v-model="selectedGroupId"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                >
                                    <option value="">All groups</option>
                                    <option value="__none__">Ungrouped</option>
                                    <option v-for="g in specGroupsList" :key="g.id" :value="String(g.id)">
                                        {{ g.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Asset type
                                </label>
                                <select
                                    v-model="selectedAssetTypeFilter"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                >
                                    <option value="">All</option>
                                    <option v-for="col in assetTypeColumns" :key="col.id" :value="String(col.id)">
                                        {{ col.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button
                                    v-if="hasActiveFilters"
                                    type="button"
                                    class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="hasActiveFilters"
                            class="mt-3 flex items-start gap-2 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <span class="material-icons shrink-0 text-[16px]">info</span>
                            <span>
                                Drag reorder is disabled while filters are active (only a subset of rows is shown).
                                Clear filters to reorder within each group.
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Grouped tables -->
                <div class="col-span-12">
                    <div class="overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700 sm:px-6">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ filteredSpecs.length }} spec{{ filteredSpecs.length === 1 ? '' : 's' }} match filters
                            </p>
                        </div>

                        <template v-for="block in orderedGroupBlocks" :key="block.blockKey">
                            <SpecGroupTable
                                :group-block-key="block.blockKey"
                                :group-label="block.label"
                                :specs="block.specs"
                                :asset-type-columns="assetTypeColumns"
                                :saving-spec-id="savingSpecId"
                                :disable-drag="hasActiveFilters"
                                :is-first="block.blockKey === orderedGroupBlocks[0].blockKey"
                                :is-last="block.blockKey === orderedGroupBlocks[orderedGroupBlocks.length - 1].blockKey"
                                @reorder="onGroupReorder"
                                @cross-group-move="onCrossGroupMove"
                                @toggle-asset-type="toggleAssetType"
                                @toggle-show-on-table="toggleShowOnTable"
                                @delete-spec="deleteSpec"
                                @move-group="({ blockKey, direction }) => moveGroup(blockKey, direction)"
                            />
                        </template>

                        <div
                            v-if="filteredSpecs.length === 0"
                            class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            <span class="material-icons mb-2 text-4xl text-gray-300 dark:text-gray-600">search_off</span>
                            <p>No specs match your filters.</p>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="mt-3 text-sm font-medium text-primary-600 dark:text-primary-400"
                                @click="clearFilters"
                            >
                                Clear filters
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-span-12">
                    <SpecPreview :specs="previewSpecs" />
                </div>
            </div>
        </div>

        <AddSpecModal v-if="showAddModal" :spec-groups="specGroupsList" @close="showAddModal = false" />
    </TenantLayout>
</template>
