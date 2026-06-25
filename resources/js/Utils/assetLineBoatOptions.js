/**
 * Helpers for boat options on transaction line items (assigned + global).
 */

export function hydrateAddedGlobalOptionIds(item, assignedOptions, globalOptions, selectionsKey = 'asset_option_selections') {
    const selections = item[selectionsKey] ?? [];
    const assignedIds = new Set((assignedOptions ?? []).map((o) => Number(o.option_id)));
    const globalIds = new Set((globalOptions ?? []).map((o) => Number(o.option_id)));

    const fromSelections = selections
        .map((s) => Number(s.option_id))
        .filter((id) => globalIds.has(id) && !assignedIds.has(id));

    const existing = (item.added_global_option_ids ?? []).map(Number).filter((id) => globalIds.has(id));

    const merged = [...new Set([...existing, ...fromSelections])];
    item.added_global_option_ids = merged;

    return merged;
}

export function lineOptionsForDisplay(assignedOptions, globalOptions, addedGlobalOptionIds) {
    const assigned = assignedOptions ?? [];
    const addedIds = new Set((addedGlobalOptionIds ?? []).map(Number));
    const globals = (globalOptions ?? []).filter((o) => addedIds.has(Number(o.option_id)));

    return [...assigned, ...globals];
}

export function allAvailableLineOptionIds(assignedOptions, globalOptions, addedGlobalOptionIds) {
    return lineOptionsForDisplay(assignedOptions, globalOptions, addedGlobalOptionIds).map((o) =>
        Number(o.option_id),
    );
}

export function ensureCustomerOfferedOptionIds(item, availableOptionIds) {
    if (!Array.isArray(item.customer_offered_option_ids)) {
        item.customer_offered_option_ids = [...availableOptionIds];
        return;
    }

    const allowed = new Set(availableOptionIds.map(Number));
    item.customer_offered_option_ids = item.customer_offered_option_ids
        .map(Number)
        .filter((id) => allowed.has(id));
}

export function toggleCustomerOfferedOptionId(item, optionId, checked) {
    const id = Number(optionId);
    const current = (item.customer_offered_option_ids ?? []).map(Number);
    if (checked) {
        if (!current.includes(id)) {
            item.customer_offered_option_ids = [...current, id];
        }
    } else {
        item.customer_offered_option_ids = current.filter((x) => x !== id);
    }
}

export function isCustomerOfferedOptionId(item, optionId) {
    return (item.customer_offered_option_ids ?? []).map(Number).includes(Number(optionId));
}

export function removeGlobalOptionFromLine(item, optionId, selectionsKey = 'asset_option_selections', clearSingle) {
    const id = Number(optionId);
    item.added_global_option_ids = (item.added_global_option_ids ?? []).map(Number).filter((x) => x !== id);
    item.customer_offered_option_ids = (item.customer_offered_option_ids ?? []).map(Number).filter((x) => x !== id);
    if (typeof clearSingle === 'function') {
        clearSingle(item, id);
    } else if (item[selectionsKey]) {
        item[selectionsKey] = item[selectionsKey].filter((s) => Number(s.option_id) !== id);
    }
}

export function addGlobalOptionToLine(item, optionId, offerToCustomer = false) {
    const id = Number(optionId);
    const current = (item.added_global_option_ids ?? []).map(Number);
    if (!current.includes(id)) {
        item.added_global_option_ids = [...current, id];
    }
    if (!offerToCustomer) {
        return;
    }
    if (!Array.isArray(item.customer_offered_option_ids)) {
        item.customer_offered_option_ids = [id];
    } else if (!item.customer_offered_option_ids.map(Number).includes(id)) {
        item.customer_offered_option_ids = [...item.customer_offered_option_ids.map(Number), id];
    }
}
