import { ref } from 'vue';

/**
 * Fetch variants for an asset (tenant `assets.variants.index` JSON).
 * Use inside {@link AssetLineVariantSelect} or call directly when you only need the list.
 */
export async function fetchAssetVariants(assetId) {
    if (!assetId) {
        return [];
    }
    const url = new URL(route('assets.variants.index', { asset: assetId }), window.location.origin);
    url.searchParams.set('per_page', '100');
    url.searchParams.set('page', '1');
    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        credentials: 'same-origin',
    });
    if (!response.ok) {
        throw new Error(String(response.status));
    }
    const data = await response.json();
    return data.records || [];
}

/**
 * Reactive variant options + loading for asset line modals (estimates, opportunities, etc.).
 */
export function useAssetLineVariant() {
    const variantOptions = ref([]);
    const variantsLoading = ref(false);

    async function loadForAsset(assetId) {
        variantOptions.value = [];
        if (!assetId) {
            return;
        }
        variantsLoading.value = true;
        try {
            variantOptions.value = await fetchAssetVariants(assetId);
        } catch (e) {
            console.error(e);
            variantOptions.value = [];
        } finally {
            variantsLoading.value = false;
        }
    }

    function clear() {
        variantOptions.value = [];
    }

    return { variantOptions, variantsLoading, loadForAsset, clear };
}
