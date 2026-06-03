/**
 * Mirror App\Http\Controllers\Concerns\HasSchemaSupport::defaultFiltersFromTableSchema.
 * Quick-filter rows (field + label only) are not applied until the user adds them.
 */
export function defaultFiltersFromTableSchema(tableSchema) {
    const defs = tableSchema?.filters;
    if (!Array.isArray(defs)) {
        return [];
    }

    const out = [];
    const baseId = Date.now();

    defs.forEach((def, i) => {
        if (!def || typeof def !== 'object') {
            return;
        }

        const field = def.field ?? def.key;
        if (!field) {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(def, 'default_value') && def.default_value === false) {
            return;
        }

        const explicitDefault =
            def.apply_as_default === true || Object.prototype.hasOwnProperty.call(def, 'default_value');

        if (!explicitDefault) {
            return;
        }

        const row = {
            id: def.id ?? `default-${i}-${baseId}`,
            field,
            operator: def.operator ?? 'equals',
        };

        if (Object.prototype.hasOwnProperty.call(def, 'value')) {
            row.value = def.value;
        } else if (
            Object.prototype.hasOwnProperty.call(def, 'default_value')
            && def.default_value !== true
            && def.default_value !== false
        ) {
            row.value = def.default_value;
        }

        out.push(row);
    });

    return out;
}
