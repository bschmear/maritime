/**
 * Normalize a single field error from Inertia useForm or shared page.props.errors.
 *
 * @param {Record<string, string|string[]>|undefined} bag
 * @param {string} key
 * @returns {string}
 */
export function validationError(bag, key) {
    const value = bag?.[key];
    if (!value) {
        return '';
    }
    if (Array.isArray(value)) {
        return value[0] ?? '';
    }

    return String(value);
}

/**
 * First non-empty message from the given keys (in order).
 *
 * @param {Record<string, string|string[]>|undefined} bag
 * @param {string[]} keys
 * @returns {string}
 */
export function firstValidationError(bag, keys) {
    for (const key of keys) {
        const message = validationError(bag, key);
        if (message) {
            return message;
        }
    }

    return '';
}
