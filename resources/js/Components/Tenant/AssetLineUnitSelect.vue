<script setup>
import { computed, nextTick, watch } from 'vue';
import { useAssetLineUnit } from '@/composables/useAssetLineUnit.js';

const unitId = defineModel({ default: null });
const unitDisplayName = defineModel('unitDisplayName', { default: '' });

const props = defineProps({
    /** Current asset id; units load when set. */
    assetId: { type: [Number, String], default: null },
    /** Optional variant id; when set, only units for this variant are shown. */
    variantId: { type: [Number, String], default: null },
    /**
     * When set (e.g. service ticket), include units for this customer OR unassigned (stock) units.
     */
    customerId: { type: [Number, String], default: null },
    /** e.g. delivery or pick-asset-only — hide Cost / Asking under the unit selector. */
    hideFinancialDetails: { type: Boolean, default: false },
});

const { unitOptions, unitsLoading, loadForAsset, clear } = useAssetLineUnit();

const selectedUnit = computed(() => {
    if (unitId.value == null || unitId.value === '') {
        return null;
    }
    const numId = Number(unitId.value);
    return unitOptions.value.find((u) => Number(u.id) === numId) || null;
});

const formatCost = (value) => {
    if (value == null || value === '') {
        return '—';
    }
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

function applySelection(id) {
    if (id == null || id === '') {
        unitDisplayName.value = '';
        return;
    }
    const numId = Number(id);
    const u = unitOptions.value.find((x) => Number(x.id) === numId);
    if (!u) {
        return;
    }
    unitDisplayName.value = u.display_name || `Unit #${u.id}`;
}

async function onSelectChange(e) {
    const raw = e.target.value;
    unitId.value = raw === '' ? null : Number(raw);
    await nextTick();
    applySelection(unitId.value);
}

watch(
    () => [props.assetId, props.variantId, props.customerId],
    async ([aid, vid, cid]) => {
        clear();
        if (!aid) {
            return;
        }
        await loadForAsset(aid, vid, cid);
        if (unitId.value != null) {
            const stillValid = unitOptions.value.some((u) => Number(u.id) === Number(unitId.value));
            if (!stillValid) {
                unitId.value = null;
                unitDisplayName.value = '';
            } else {
                applySelection(unitId.value);
            }
        }
    },
    { immediate: true },
);

watch(unitOptions, (opts) => {
    if (opts.length && unitId.value != null) {
        applySelection(unitId.value);
    }
});
</script>

<template>
    <div v-if="assetId && (unitsLoading || unitOptions.length > 0)" class="space-y-1.5">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label>
        <select
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent disabled:opacity-60"
            :disabled="unitsLoading"
            :value="unitId ?? ''"
            @change="onSelectChange"
        >
            <option value="">No specific unit</option>
            <option v-for="u in unitOptions" :key="u.id" :value="u.id">
                {{ u.display_name || `Unit #${u.id}` }}
            </option>
        </select>
        <p v-if="unitsLoading" class="text-xs text-gray-500 dark:text-gray-400">Loading units…</p>
        <p
            v-else-if="selectedUnit && !props.hideFinancialDetails"
            class="text-xs text-gray-500 dark:text-gray-400"
        >
            <span class="font-medium text-gray-600 dark:text-gray-300">Cost:</span>
            {{ formatCost(selectedUnit.cost) }}
            <span v-if="selectedUnit.asking_price != null" class="ml-3">
                <span class="font-medium text-gray-600 dark:text-gray-300">Asking:</span>
                {{ formatCost(selectedUnit.asking_price) }}
            </span>
        </p>
    </div>
</template>
