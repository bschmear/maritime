<script setup>
import { nextTick, watch } from 'vue';
import { useAssetLineVariant } from '@/composables/useAssetLineVariant.js';

const variantId = defineModel({ default: null });
const variantDisplayName = defineModel('variantDisplayName', { default: '' });
const catalogDescription = defineModel('catalogDescription', { default: '' });

const props = defineProps({
    /** Current asset id (loads variants when set with hasVariants). */
    assetId: { type: [Number, String], default: null },
    hasVariants: { type: Boolean, default: false },
    /** Base asset description; used when clearing variant or building catalog text. */
    assetDescription: { type: String, default: '' },
    /** When true, keeps catalogDescription in sync with selected variant (estimates). */
    syncCatalogDescription: { type: Boolean, default: false },
    /** When true, emit default_price from variant as unit price. */
    applyDefaultPrice: { type: Boolean, default: true },
    /** Show resolved catalog text under the select (estimates). */
    showCatalogPreview: { type: Boolean, default: false },
});

const emit = defineEmits(['update:unitPrice']);

const { variantOptions, variantsLoading, loadForAsset, clear } = useAssetLineVariant();

function applySelection(id) {
    if (id == null || id === '') {
        variantDisplayName.value = '';
        if (props.syncCatalogDescription) {
            catalogDescription.value = props.assetDescription || '';
        }
        return;
    }
    const numId = Number(id);
    const v = variantOptions.value.find((x) => Number(x.id) === numId);
    if (!v) {
        return;
    }
    variantDisplayName.value = v.display_name || v.name || `Variant #${v.id}`;
    if (props.syncCatalogDescription) {
        catalogDescription.value =
            (v.resolved_description || '').trim() ||
            (v.description || '').trim() ||
            props.assetDescription ||
            '';
    }
    if (props.applyDefaultPrice && v.default_price != null && v.default_price !== '') {
        emit('update:unitPrice', Number(v.default_price));
    }
}

async function onSelectChange(e) {
    const raw = e.target.value;
    variantId.value = raw === '' ? null : Number(raw);
    await nextTick();
    applySelection(variantId.value);
}

watch(
    () => [props.assetId, props.hasVariants],
    async ([aid, hv]) => {
        clear();
        if (hv && aid) {
            await loadForAsset(aid);
            if (variantId.value != null) {
                applySelection(variantId.value);
            }
        }
    },
    { immediate: true },
);

watch(variantOptions, (opts) => {
    if (opts.length && variantId.value != null) {
        applySelection(variantId.value);
    }
});
</script>

<template>
    <div v-if="hasVariants && assetId" class="space-y-1.5">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Variant <span class="text-red-500">*</span>
        </label>
        <select
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent disabled:opacity-60"
            :disabled="variantsLoading"
            :value="variantId ?? ''"
            @change="onSelectChange"
        >
            <option value="">Select a variant…</option>
            <option v-for="v in variantOptions" :key="v.id" :value="v.id">
                {{ v.display_name || v.name || `Variant #${v.id}` }}
            </option>
        </select>
        <p v-if="variantsLoading" class="text-xs text-gray-500 dark:text-gray-400">Loading variants…</p>
        <p
            v-if="showCatalogPreview && (catalogDescription || '').trim()"
            class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-3"
        >
            <span class="font-medium text-gray-600 dark:text-gray-300">Catalog description:</span>
            {{ catalogDescription }}
        </p>
    </div>
</template>
