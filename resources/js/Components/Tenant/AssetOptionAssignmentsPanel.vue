<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue';

const appInstance = getCurrentInstance();
function toast(type, message) {
    appInstance?.appContext.config.globalProperties.$toast?.(type, message);
}

const props = defineProps({
    optionId: { type: Number, required: true },
    record: { type: Object, required: true },
});

const makers = ref([]);
const assetsByMakeId = ref({});
const loadingMakeIds = ref(new Set());
/** @type {import('vue').Ref<Array<{ make_id: string, apply_all: boolean, rows: Array<{ asset_id: number, variant_id: number|null }> }>>} */
const brandBlocks = ref([]);
const savingAssignments = ref(false);

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function deriveBrandBlocks(record) {
    const mas = record?.make_assignments ?? record?.makeAssignments ?? [];
    const asg = record?.assignments ?? [];
    const blocks = [];
    const makesWithMakeWide = new Set(mas.map((m) => m.make_id));

    mas.forEach((m) => {
        blocks.push({
            make_id: String(m.make_id),
            apply_all: true,
            rows: [],
        });
    });

    const byMake = {};
    asg.forEach((a) => {
        const mk = a.asset?.make_id;
        if (mk == null || makesWithMakeWide.has(mk)) {
            return;
        }
        if (!byMake[mk]) {
            byMake[mk] = [];
        }
        byMake[mk].push({
            asset_id: a.asset_id,
            variant_id: a.variant_id ?? null,
        });
    });
    Object.keys(byMake).forEach((mk) => {
        blocks.push({
            make_id: String(mk),
            apply_all: false,
            rows: byMake[mk],
        });
    });

    return blocks;
}

const savedAssignmentSummary = computed(() => {
    const mas = props.record?.make_assignments ?? props.record?.makeAssignments ?? [];
    const asg = props.record?.assignments ?? [];

    if (!mas.length && !asg.length) {
        return 'Not assigned to any catalog yet';
    }

    const parts = [];
    mas.forEach((m) => {
        const name = m.make?.display_name ?? makers.value.find((mk) => mk.id === m.make_id)?.display_name ?? `Brand #${m.make_id}`;
        parts.push(`${name} (all models)`);
    });

    asg.forEach((a) => {
        const assetLabel = a.asset?.display_name ?? `Model #${a.asset_id}`;
        const variantLabel = a.variant?.display_name || a.variant?.name;
        parts.push(variantLabel ? `${assetLabel} — ${variantLabel}` : assetLabel);
    });

    return parts.length ? parts.join(' · ') : 'Not assigned to any catalog yet';
});

const fetchMakersList = async () => {
    const { data } = await axios.get(route('asset-options.assignment-lookup'), {
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (data.makers) {
        makers.value = data.makers;
    }
};

const fetchAssetsForMake = async (makeId) => {
    const key = String(makeId);
    if (!makeId) {
        return;
    }
    loadingMakeIds.value = new Set([...loadingMakeIds.value, key]);
    try {
        const { data } = await axios.get(route('asset-options.assignment-lookup'), {
            params: { make_id: makeId },
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        assetsByMakeId.value[key] = data.assets || [];
    } finally {
        const next = new Set(loadingMakeIds.value);
        next.delete(key);
        loadingMakeIds.value = next;
    }
};

function lookupAssetsForMake(makeId) {
    return assetsByMakeId.value[String(makeId)] ?? [];
}

function isLoadingMake(makeId) {
    return loadingMakeIds.value.has(String(makeId));
}

function makersOptionsForBlock(blockIndex) {
    const current = brandBlocks.value[blockIndex]?.make_id;
    const selectedElsewhere = new Set(
        brandBlocks.value
            .map((b, i) => (i !== blockIndex && b.make_id ? String(b.make_id) : null))
            .filter(Boolean),
    );

    return makers.value.filter(
        (m) => !selectedElsewhere.has(String(m.id)) || String(m.id) === String(current),
    );
}

function addBrandBlock() {
    brandBlocks.value.push({
        make_id: '',
        apply_all: true,
        rows: [],
    });
}

function removeBrandBlock(index) {
    brandBlocks.value.splice(index, 1);
}

function toggleAssetRow(blockIndex, assetId, variantId = null) {
    const block = brandBlocks.value[blockIndex];
    if (!block) {
        return;
    }
    const rows = block.rows;
    const exists = rows.some(
        (r) =>
            Number(r.asset_id) === Number(assetId) &&
            (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId)),
    );
    if (exists) {
        block.rows = rows.filter(
            (r) =>
                !(
                    Number(r.asset_id) === Number(assetId) &&
                    (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId))
                ),
        );
    } else {
        block.rows = [...rows, { asset_id: assetId, variant_id: variantId }];
    }
}

function isRowSelected(blockIndex, assetId, variantId = null) {
    const rows = brandBlocks.value[blockIndex]?.rows ?? [];

    return rows.some(
        (r) =>
            Number(r.asset_id) === Number(assetId) &&
            (r.variant_id == null ? variantId == null : Number(r.variant_id) === Number(variantId)),
    );
}

function ensureDefaultBrandBlock() {
    if (brandBlocks.value.length === 0) {
        addBrandBlock();
    }
}

async function loadAssetsForBlocks(blocks) {
    for (const b of blocks) {
        if (!b.apply_all && b.make_id) {
            await fetchAssetsForMake(Number(b.make_id));
        }
    }
}

onMounted(async () => {
    await fetchMakersList();
    brandBlocks.value = deriveBrandBlocks(props.record);
    await loadAssetsForBlocks(brandBlocks.value);
    ensureDefaultBrandBlock();
});

async function ensureAssetsLoaded(blockIndex) {
    const b = brandBlocks.value[blockIndex];
    if (b?.make_id && !b.apply_all) {
        await fetchAssetsForMake(Number(b.make_id));
    }
}

async function onBlockMakeChange(blockIndex, event) {
    const makeId = event.target.value;
    const b = brandBlocks.value[blockIndex];
    if (!b) {
        return;
    }
    b.make_id = makeId ? String(makeId) : '';
    b.rows = [];
    await ensureAssetsLoaded(blockIndex);
}

async function onBlockApplyAllToggle(blockIndex, checked) {
    const b = brandBlocks.value[blockIndex];
    if (!b) {
        return;
    }
    b.apply_all = checked;
    if (checked) {
        b.rows = [];
    } else if (b.make_id) {
        await fetchAssetsForMake(Number(b.make_id));
    }
}

watch(
    () => props.record,
    async (record) => {
        brandBlocks.value = deriveBrandBlocks(record);
        await loadAssetsForBlocks(brandBlocks.value);
        ensureDefaultBrandBlock();
    },
);

const saveAssignments = async () => {
    const filled = brandBlocks.value.filter((b) => b.make_id);
    const incomplete = filled.some((b) => !b.apply_all && !(b.rows?.length > 0));
    if (incomplete) {
        toast('error', 'For each brand with “all models” off, pick at least one model or variant.');
        return;
    }
    const orphanEmptyRows = brandBlocks.value.some((b) => !b.make_id) && filled.length > 0;
    if (orphanEmptyRows) {
        toast('error', 'Remove empty brand rows or select a brand for each.');
        return;
    }
    if (!filled.length) {
        toast('error', 'Add at least one brand assignment.');
        return;
    }

    savingAssignments.value = true;
    try {
        const brands = brandBlocks.value
            .filter((b) => b.make_id)
            .map((b) => ({
                make_id: Number(b.make_id),
                apply_to_all_models: b.apply_all,
                rows: b.apply_all
                    ? []
                    : (b.rows ?? []).map((r) => ({
                          asset_id: Number(r.asset_id),
                          variant_id: r.variant_id == null ? null : Number(r.variant_id),
                      })),
            }));

        await axios.post(
            route('asset-options.sync-assignments', { assetOption: props.optionId }),
            { brands },
            { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
        toast('success', 'Assignments saved.');
        await router.reload({ only: ['record'] });
    } catch (err) {
        const data = err.response?.data;
        const errs = data?.errors;
        let msg =
            (typeof data?.message === 'string' && data.message) ||
            (typeof errs?.brands === 'string' ? errs.brands : Array.isArray(errs?.brands) ? errs.brands[0] : null) ||
            null;
        if (!msg && errs && typeof errs === 'object') {
            const first = Object.values(errs).find((v) => Array.isArray(v) && v[0]);
            msg = first?.[0] ?? null;
        }
        toast('error', msg || 'Could not save assignments.');
    } finally {
        savingAssignments.value = false;
    }
};
</script>

<template>
    <div
        id="catalog-assignments"
        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Catalog assignments</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Choose which brands, models, and variants show this option inline on transaction lines.
            </p>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                <span class="font-medium text-gray-500 dark:text-gray-400">Currently saved:</span>
                {{ savedAssignmentSummary }}
            </p>
        </div>
        <div class="space-y-6 p-6">
            <div
                v-for="(block, blockIndex) in brandBlocks"
                :key="blockIndex"
                class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Brand {{ blockIndex + 1 }}</span>
                    <button
                        type="button"
                        class="text-sm font-medium text-red-600 hover:underline dark:text-red-400"
                        @click="removeBrandBlock(blockIndex)"
                    >
                        Remove
                    </button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium uppercase text-gray-500">Brand</label>
                        <select
                            :value="block.make_id"
                            class="input-style"
                            @change="onBlockMakeChange(blockIndex, $event)"
                        >
                            <option value="">Select brand…</option>
                            <option v-for="m in makersOptionsForBlock(blockIndex)" :key="m.id" :value="String(m.id)">
                                {{ m.display_name }}
                            </option>
                        </select>
                        <p v-if="!makers.length" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            No active brands found. Activate a brand under Makes first.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            :id="`apply_all_${blockIndex}`"
                            type="checkbox"
                            class="rounded border-gray-300"
                            :checked="block.apply_all"
                            :disabled="!block.make_id"
                            @change="onBlockApplyAllToggle(blockIndex, $event.target.checked)"
                        />
                        <label :for="`apply_all_${blockIndex}`" class="text-sm text-gray-800 dark:text-gray-200">
                            Apply to all models under this brand
                        </label>
                    </div>
                </div>

                <div
                    v-if="block.make_id && !block.apply_all"
                    class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                >
                    <p v-if="isLoadingMake(block.make_id)" class="text-sm text-gray-500 dark:text-gray-400">Loading models…</p>
                    <template v-else>
                        <div v-for="a in lookupAssetsForMake(block.make_id)" :key="a.id" class="mb-3">
                            <label class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                <input
                                    type="checkbox"
                                    :checked="isRowSelected(blockIndex, a.id, null)"
                                    @change="toggleAssetRow(blockIndex, a.id, null)"
                                />
                                {{ a.display_name }}
                            </label>
                            <div v-if="a.has_variants && a.variants?.length" class="ml-6 mt-1 space-y-1">
                                <label
                                    v-for="vv in a.variants"
                                    :key="vv.id"
                                    class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isRowSelected(blockIndex, a.id, vv.id)"
                                        @change="toggleAssetRow(blockIndex, a.id, vv.id)"
                                    />
                                    {{ vv.display_name || vv.name }}
                                </label>
                            </div>
                        </div>
                        <p v-if="!lookupAssetsForMake(block.make_id).length" class="text-sm text-gray-500 dark:text-gray-400">
                            No active models found for this brand.
                        </p>
                    </template>
                </div>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                @click="addBrandBlock"
            >
                <span class="material-icons text-[18px]">add</span>
                Add brand
            </button>

            <div>
                <button
                    type="button"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:text-gray-100 disabled:hover:bg-gray-400"
                    :disabled="savingAssignments"
                    @click="saveAssignments"
                >
                    {{ savingAssignments ? 'Saving…' : 'Save assignments' }}
                </button>
            </div>
        </div>
    </div>
</template>
