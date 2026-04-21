import { ref } from 'vue';

/**
 * Fetch units for an asset (tenant `assets.units.index` JSON). Pass `variantId`
 * to restrict the list to units of a given variant.
 */
export async function fetchAssetUnits(assetId, variantId = null, customerId = null) {
    if (!assetId) {
        return [];
    }
    const url = new URL(route('assets.units.index', { asset: assetId }), window.location.origin);
    url.searchParams.set('per_page', '100');
    url.searchParams.set('page', '1');
    if (variantId != null && variantId !== '') {
        url.searchParams.set('variant', String(variantId));
    }
    if (customerId != null && customerId !== '') {
        url.searchParams.set('customer_id', String(customerId));
    }
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
 * Reactive unit options + loading for asset line modals (estimates, opportunities, etc.).
 */
export function useAssetLineUnit() {
    const unitOptions = ref([]);
    const unitsLoading = ref(false);

    async function loadForAsset(assetId, variantId = null, customerId = null) {
        unitOptions.value = [];
        if (!assetId) {
            return;
        }
        unitsLoading.value = true;
        try {
            unitOptions.value = await fetchAssetUnits(assetId, variantId, customerId);
        } catch (e) {
            console.error(e);
            unitOptions.value = [];
        } finally {
            unitsLoading.value = false;
        }
    }

    function clear() {
        unitOptions.value = [];
    }

    return { unitOptions, unitsLoading, loadForAsset, clear };
}
