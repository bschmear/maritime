export function isAssetOptionSelected(item, optionId, valueId) {
    return (item.asset_option_selections || []).some(
        (s) => Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId),
    );
}

export function hasAssetOptionAnySelection(item, optionId) {
    return (item.asset_option_selections || []).some(
        (s) => Number(s.option_id) === Number(optionId),
    );
}

export function clearAssetOptionSingle(item, optionId) {
    if (!item.asset_option_selections) {
        item.asset_option_selections = [];

        return;
    }

    item.asset_option_selections = item.asset_option_selections.filter(
        (s) => Number(s.option_id) !== Number(optionId),
    );
}

export function setAssetOptionSingle(item, optionId, valueId) {
    if (!item.asset_option_selections) {
        item.asset_option_selections = [];
    }

    const rest = item.asset_option_selections.filter((s) => Number(s.option_id) !== Number(optionId));
    item.asset_option_selections = [...rest, { option_id: optionId, option_value_id: valueId }];
}

export function toggleAssetOptionMulti(item, optionId, valueId, checked) {
    if (!item.asset_option_selections) {
        item.asset_option_selections = [];
    }

    const rest = item.asset_option_selections.filter(
        (s) => !(Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId)),
    );

    if (checked) {
        item.asset_option_selections = [...rest, { option_id: optionId, option_value_id: valueId }];
    } else {
        item.asset_option_selections = rest;
    }
}

export function isAssetOptionToggleOn(item, opt) {
    const valueId = opt.values?.[0]?.id;

    return valueId != null && isAssetOptionSelected(item, opt.option_id, valueId);
}

export function toggleAssetOptionToggle(item, opt, checked) {
    const valueId = opt.values?.[0]?.id;
    if (valueId == null) {
        return;
    }

    if (checked) {
        setAssetOptionSingle(item, opt.option_id, valueId);
    } else {
        clearAssetOptionSingle(item, opt.option_id);
    }
}
