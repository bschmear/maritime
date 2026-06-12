/**
 * Default value for a required enum/select field (first option, or schema default).
 *
 * @param {{ type?: string, required?: boolean, default?: unknown }} fieldDef
 * @param {{ id?: unknown, value?: unknown, name?: string }[]} options
 * @param {(opt: object) => unknown} resolveValue
 * @param {{ required?: boolean }} [overrides]
 * @returns {unknown}
 */
export function defaultRequiredSelectValue(fieldDef, options, resolveValue, overrides = {}) {
    if ((fieldDef?.type ?? '') !== 'select') {
        return '';
    }

    const opts = options ?? [];
    if (!opts.length) {
        return '';
    }

    const required = overrides.required ?? fieldDef.required === true;
    if (!required) {
        return '';
    }

    const pick = (opt) => resolveValue(opt);

    if (fieldDef.default !== undefined && fieldDef.default !== null) {
        const hit = opts.find(
            (o) =>
                o.value === fieldDef.default
                || o.id === fieldDef.default
                || String(o.value) === String(fieldDef.default),
        );

        return pick(hit ?? opts[0]);
    }

    return pick(opts[0]);
}
