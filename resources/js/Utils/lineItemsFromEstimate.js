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
    const fromEst = getEstimatePrimaryLineItems(record?.estimate);
    if (record?.estimate_id && fromEst.length > 0) {
        return { items: fromEst, source: 'estimate' };
    }
    return { items: record?.items ?? [], source: 'deal' };
}

/**
 * @returns {{ items: array, source: 'estimate' | 'deal' }}
 */
export function resolveLineItemsForContract(record) {
    const fromEst = getEstimatePrimaryLineItems(record?.estimate);
    if (record?.estimate_id && fromEst.length > 0) {
        return { items: fromEst, source: 'estimate' };
    }
    return { items: record?.transaction?.items ?? [], source: 'deal' };
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
