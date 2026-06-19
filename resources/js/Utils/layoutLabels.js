export function resolveUnitLabel(row) {
    return row?.unit_label ?? row?.asset_unit?.unit_label ?? null;
}

/** Remove repeated " · {unitLabel}" suffixes from a previously saved full label. */
export function stripUnitLabelSuffixes(base, unitLabel) {
    if (!base) {
        return '';
    }

    let result = String(base).trim();
    if (!unitLabel) {
        return result;
    }

    const suffix = ` · ${unitLabel}`;
    while (result.endsWith(suffix)) {
        result = result.slice(0, -suffix.length);
    }

    return result;
}

/** User-defined placement label only (never includes unit id suffix). */
export function layoutCustomLabel(row) {
    const unitLabel = resolveUnitLabel(row);
    if (row?.layout_label && String(row.layout_label).trim()) {
        return stripUnitLabelSuffixes(String(row.layout_label).trim(), unitLabel);
    }

    return null;
}

export function layoutDisplayName(row, index = 0) {
    const custom = layoutCustomLabel(row);
    if (custom) {
        return custom;
    }

    return row?.display_name ?? row?.name ?? `Asset ${index + 1}`;
}

export function layoutItemLabel(row, index = 0) {
    const base = layoutDisplayName(row, index);
    const unitLabel = resolveUnitLabel(row);

    return unitLabel ? `${base} · ${unitLabel}` : base;
}

/** Value to persist as layout_label — custom name only, not the computed display label. */
export function layoutCustomNameForSave(boat) {
    const displayName = boat.displayName?.trim();
    if (!displayName) {
        return null;
    }

    const assetDefault = boat.assetDisplayName?.trim();
    if (assetDefault && displayName === assetDefault) {
        return null;
    }

    return displayName;
}
