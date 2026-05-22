/** Polymorphic catalog types on estimate / deal line rows (match Laravel `::class` strings). */
export const ASSET_LINE_ITEM_TYPE = 'App\\Domain\\Asset\\Models\\Asset';
export const INVENTORY_LINE_ITEM_TYPE = 'App\\Domain\\InventoryItem\\Models\\InventoryItem';

/**
 * Primary-version line rows from an estimate (Inertia may use snake_case or camelCase).
 */
export function getEstimatePrimaryLineItems(estimate) {
    if (!estimate) return [];
    const pv = estimate.primary_version ?? estimate.primaryVersion;
    if (!pv) return [];
    return pv.line_items ?? pv.lineItems ?? [];
}

/**
 * @returns {{ items: array, source: 'estimate' | 'deal' }}
 */
export function resolveLineItemsForTransaction(record) {
    const dealLines = record?.items ?? [];
    if (dealLines.length > 0) {
        return { items: dealLines, source: 'deal' };
    }
    const fromEst = getEstimatePrimaryLineItems(record?.estimate);
    if (record?.estimate_id && fromEst.length > 0) {
        return { items: fromEst, source: 'estimate' };
    }
    return { items: [], source: 'deal' };
}

/**
 * @returns {{ items: array, source: 'estimate' | 'deal' }}
 */
export function resolveLineItemsForContract(record) {
    const dealLines = record?.transaction?.items ?? [];
    if (dealLines.length > 0) {
        return { items: dealLines, source: 'deal' };
    }
    const fromEst = getEstimatePrimaryLineItems(record?.estimate);
    if (record?.estimate_id && fromEst.length > 0) {
        return { items: fromEst, source: 'estimate' };
    }
    return { items: [], source: 'deal' };
}

/** Pre-tax line total (estimate lines include discount). */
export function lineItemPreTaxTotal(item) {
    const qty = Number(item.quantity || 1);
    const price = Number(item.unit_price || 0);
    const discount = Number(item.discount || 0);
    return Math.max(0, qty * price - discount);
}

export function taxRateForResolvedLines(record, source, dealTaxRate) {
    if (source === 'estimate') {
        const est = record?.estimate;
        const pv = est?.primary_version ?? est?.primaryVersion;
        const r = Number(est?.tax_rate ?? pv?.tax_rate);
        if (!Number.isNaN(r)) {
            return r;
        }
    }
    return Number(dealTaxRate) || 0;
}

/** Variant on a resolved line (estimate row or deal row via estimate_line_item). Inertia may use snake_case or camelCase. */
export function lineVariantId(item) {
    const direct = item.asset_variant_id ?? item.assetVariantId ?? null;
    if (direct) return direct;
    const eli = item.estimate_line_item ?? item.estimateLineItem;
    return eli?.asset_variant_id ?? eli?.assetVariantId ?? null;
}

export function lineVariant(item) {
    const v = item.asset_variant ?? item.assetVariant;
    if (v) return v;
    const eli = item.estimate_line_item ?? item.estimateLineItem;
    return eli ? (eli.asset_variant ?? eli.assetVariant) : null;
}

export function lineVariantDisplay(item) {
    const v = lineVariant(item);
    if (v?.display_name || v?.name) {
        return v.display_name || v.name;
    }
    const vid = lineVariantId(item);
    if (vid) {
        return `Variant #${vid}`;
    }
    return '—';
}

/** Unit on a resolved line (estimate row or deal row via estimate_line_item). Inertia may use snake_case or camelCase. */
export function lineUnitId(item) {
    const direct = item.asset_unit_id ?? item.assetUnitId ?? null;
    if (direct) return direct;
    const eli = item.estimate_line_item ?? item.estimateLineItem;
    return eli?.asset_unit_id ?? eli?.assetUnitId ?? null;
}

export function lineUnit(item) {
    const u = item.asset_unit ?? item.assetUnit;
    if (u) return u;
    const eli = item.estimate_line_item ?? item.estimateLineItem;
    return eli ? (eli.asset_unit ?? eli.assetUnit) : null;
}

/**
 * AssetUnit.display_name is typically "Asset Name - SN: 12345"; strip the leading asset
 * name so table cells show just the identifier (e.g. "SN: 12345").
 */
export function lineUnitDisplay(item) {
    const u = lineUnit(item);
    const raw = u?.display_name;
    if (raw) {
        const parts = String(raw).split(' - ');
        return parts.length > 1 ? parts.slice(1).join(' - ') : parts[0];
    }
    const uid = lineUnitId(item);
    return uid ? `Unit #${uid}` : '—';
}

/** Catalog asset id for links (snake or camel). */
export function lineItemAssetCatalogId(item) {
    return item.itemable_id ?? item.itemable?.id ?? null;
}

/**
 * Boat options on a deal or estimate line: direct rows first, then rows keyed from the source estimate line.
 * Same behavior as `lineAssetSelectedOptions` in `Transaction/Show.vue`.
 */
export function lineAssetSelectedOptions(row) {
    const direct = row.selected_asset_options ?? row.selectedAssetOptions ?? [];
    if (direct.length) {
        return direct;
    }
    return row.selected_asset_options_from_source_line ?? row.selectedAssetOptionsFromSourceLine ?? [];
}

export function selectedOptionLabel(opt) {
    const name = String(opt?.option_name ?? '').trim();
    const val = String(opt?.value_label ?? '').trim();
    if (name && val) {
        return `${name}: ${val}`;
    }
    return name || val || 'Option';
}

/** Stable key for v-for (estimate id or deal line id). */
export function lineItemRowKey(item) {
    if (item == null) {
        return null;
    }
    const id = item.id ?? item.line_item_id;
    return id == null ? null : String(id);
}

/**
 * Pre-tax catalog total for an asset line (base + option premiums when `line_total` is populated).
 * Mirrors Estimate Show `assetLineCatalogTotal`.
 */
export function assetLineCatalogTotal(item) {
    const stored = item.line_total;
    if (stored != null && stored !== '' && !Number.isNaN(Number(stored))) {
        return Number(stored);
    }
    return lineItemPreTaxTotal(item);
}

/**
 * Full line total including add-ons (stored `line_total` + add-on extension, or computed fallback).
 * Mirrors Estimate Show `lineTotal`.
 */
export function lineTotalWithAddons(item) {
    const addonsTotal = (item.addons || []).reduce(
        (sum, addon) => sum + Number(addon.price || 0) * Number(addon.quantity || 1),
        0,
    );
    const stored = item.line_total;
    if (stored != null && stored !== '' && !Number.isNaN(Number(stored))) {
        return Number(stored) + addonsTotal;
    }
    return lineItemPreTaxTotal(item) + addonsTotal;
}

export function partitionLineItemsByCatalogType(items) {
    const list = items ?? [];
    const assetLines = [];
    const inventoryLines = [];
    const otherLines = [];
    for (const row of list) {
        const t = row.itemable_type ?? row.itemableType;
        if (t === ASSET_LINE_ITEM_TYPE) {
            assetLines.push(row);
        } else if (t === INVENTORY_LINE_ITEM_TYPE) {
            inventoryLines.push(row);
        } else {
            otherLines.push(row);
        }
    }
    return { assetLines, inventoryLines, otherLines };
}
