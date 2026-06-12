import { lineUnit, lineUnitDisplay, lineUnitId } from '@/Utils/lineItemsFromEstimate';

export const TRANSACTION_TERMINAL_STATUS_IDS = [3, 4, 5];

export function isTerminalTransactionStatus(statusId) {
    return TRANSACTION_TERMINAL_STATUS_IDS.includes(Number(statusId));
}

/** Default unit status when a deal moves to completed / failed / cancelled. */
export function defaultAssetUnitStatusForTransaction(transactionStatusId) {
    return Number(transactionStatusId) === 3 ? 3 : 1;
}

export function transactionStatusLabel(statusId, statusOptions = []) {
    const id = Number(statusId);
    const opt = statusOptions.find((o) => Number(o.id) === id);
    return opt?.name ?? 'this status';
}

export function collectAssetUnitsFromLineItems(items) {
    const map = new Map();

    for (const item of items ?? []) {
        const unitId = lineUnitId(item);
        if (!unitId) {
            continue;
        }
        const numericId = Number(unitId);
        if (map.has(numericId)) {
            continue;
        }
        const unit = lineUnit(item);
        map.set(numericId, {
            asset_unit_id: numericId,
            display_name: unit?.display_name || lineUnitDisplay(item),
            current_status: unit?.status ?? null,
            line_label: item.name || item.itemable?.display_name || 'Asset line',
        });
    }

    return [...map.values()];
}

export function collectAssetUnitsFromFormAssetItems(assetItems) {
    const map = new Map();

    for (const item of assetItems ?? []) {
        const unitId = item.asset_unit_id;
        if (!unitId) {
            continue;
        }
        const numericId = Number(unitId);
        if (map.has(numericId)) {
            continue;
        }
        map.set(numericId, {
            asset_unit_id: numericId,
            display_name: item.unit_display_name || `Unit #${numericId}`,
            current_status: item.asset_unit?.status ?? null,
            line_label: item.name || 'Asset line',
        });
    }

    return [...map.values()];
}

export function buildAssetUnitStatusDraft(units, transactionStatusId) {
    const defaultStatus = defaultAssetUnitStatusForTransaction(transactionStatusId);

    return units.map((unit) => ({
        asset_unit_id: unit.asset_unit_id,
        display_name: unit.display_name,
        line_label: unit.line_label,
        current_status: unit.current_status,
        status: defaultStatus,
    }));
}
