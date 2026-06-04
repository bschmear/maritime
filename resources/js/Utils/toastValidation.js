/**
 * Build a user-facing toast message from an Inertia/Laravel validation error bag.
 *
 * @param {Record<string, string|string[]>|undefined} errors
 * @param {Record<string, { label?: string }>|null} [fieldsSchema]
 * @returns {string}
 */
export function validationErrorsToMessage(errors, fieldsSchema = null) {
    const entries = Object.entries(errors ?? {}).filter(([, value]) => {
        if (value == null || value === '') {
            return false;
        }
        if (Array.isArray(value)) {
            return value.length > 0;
        }

        return true;
    });

    if (!entries.length) {
        return 'Please fix the highlighted fields and try again.';
    }

    const [key, raw] = entries[0];
    const firstMessage = Array.isArray(raw) ? (raw[0] ?? '') : String(raw);
    const label = fieldsSchema?.[key]?.label ?? humanizeFieldKey(key);
    const primary = firstMessage || `The ${label} field is required.`;

    if (entries.length === 1) {
        return primary;
    }

    const extra = entries.length - 1;

    return `${primary} (${extra} more field${extra === 1 ? '' : 's'} need attention)`;
}

const FIELD_LABEL_OVERRIDES = {
    contact_id: 'Contact / Lead / Customer',
};

function humanizeFieldKey(key) {
    if (FIELD_LABEL_OVERRIDES[key]) {
        return FIELD_LABEL_OVERRIDES[key];
    }

    return key
        .replace(/_id$/, '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * @param {Record<string, string|string[]>|undefined} errors
 * @param {Record<string, { label?: string }>|null} [fieldsSchema]
 * @param {(type: string, message: string) => void} toast
 */
export function toastValidationErrors(errors, fieldsSchema, toast) {
    if (typeof toast !== 'function') {
        return;
    }

    toast('error', validationErrorsToMessage(errors, fieldsSchema));
}
