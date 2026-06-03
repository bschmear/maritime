/**
 * Select-type asset spec definitions: stored values are sequential numbers (1, 2, 3…).
 * Editors only expose option labels; values are assigned automatically.
 */

export function reindexSelectOptions(options) {
    if (!Array.isArray(options)) {
        return [];
    }

    return options
        .filter((opt) => opt && String(opt.label ?? '').trim() !== '')
        .map((opt, index) => ({
            label: String(opt.label).trim(),
            value: String(index + 1),
        }));
}

/** Load existing options into the editor (labels only; values renumbered by position). */
export function normalizeSelectOptionsForEditor(options) {
    if (!Array.isArray(options) || options.length === 0) {
        return [];
    }

    return options.map((opt) => ({
        label: opt?.label ?? '',
        value: '',
    }));
}

export function addSelectOptionRow(options) {
    const next = Array.isArray(options) ? [...options] : [];
    next.push({ label: '', value: '' });

    return next;
}

export function removeSelectOptionRow(options, index) {
    const next = Array.isArray(options) ? [...options] : [];
    next.splice(index, 1);

    return reindexSelectOptions(next);
}
