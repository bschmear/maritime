import { formatPhoneDashed } from '@/Utils/formatPhoneNumber.js';

/**
 * @param {unknown} value
 * @param {string} type
 */
function formatPartValue(value, type) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (type === 'tel') {
        const dashed = formatPhoneDashed(value);

        return dashed || null;
    }

    const text = String(value).trim();

    return text !== '' ? text : null;
}

/**
 * @param {unknown} value
 */
function normalizeTelDigits(value) {
    return String(value ?? '').replace(/\D/g, '');
}

/**
 * @typedef {{ key: string, text: string, icon?: string, label?: string, title?: string }} JoinedColumnSegment
 */

/**
 * @param {{ key?: string, label?: string, icon?: string, title?: string, type?: string }} part
 * @param {string} fieldKey
 */
function defaultIconForPart(part, fieldKey) {
    if (part.icon) {
        return part.icon;
    }

    if (fieldKey === 'mobile') {
        return 'smartphone';
    }

    if (fieldKey === 'phone') {
        return 'phone';
    }

    return null;
}

/**
 * @param {Record<string, unknown>|null|undefined} record
 * @param {{ keys?: Array<{ key: string, label?: string, icon?: string, title?: string, type?: string }>, join?: Array<{ key: string, label?: string, icon?: string, title?: string, type?: string }>, format?: string }} column
 * @param {Record<string, { type?: string, label?: string }>} [fieldsSchema]
 * @returns {{ segments: JoinedColumnSegment[], isEmpty: boolean }}
 */
export function buildJoinedColumnSegments(record, column, fieldsSchema = {}) {
    let parts = column?.keys ?? column?.join ?? [];

    if ((!Array.isArray(parts) || parts.length === 0) && column?.format === 'mobile_home') {
        parts = [
            { key: 'mobile', icon: 'smartphone', title: 'Mobile', type: 'tel' },
            { key: 'phone', icon: 'phone', title: 'Other phone', type: 'tel' },
        ];
    }

    if (!Array.isArray(parts) || parts.length === 0) {
        return { segments: [], isEmpty: true };
    }

    /** @type {JoinedColumnSegment[]} */
    const segments = [];
    const seenTel = new Set();

    for (const part of parts) {
        const fieldKey = part?.key;
        if (!fieldKey) {
            continue;
        }

        const raw = record?.[fieldKey];
        const fieldType = part.type ?? fieldsSchema[fieldKey]?.type ?? 'text';
        const formatted = formatPartValue(raw, fieldType);

        if (!formatted) {
            continue;
        }

        if (fieldType === 'tel') {
            const digits = normalizeTelDigits(raw);
            if (digits !== '' && seenTel.has(digits)) {
                continue;
            }
            if (digits !== '') {
                seenTel.add(digits);
            }
        }

        segments.push({
            key: fieldKey,
            text: formatted,
            icon: defaultIconForPart(part, fieldKey),
            label: part.label,
            title: part.title ?? fieldsSchema[fieldKey]?.label,
        });
    }

    return { segments, isEmpty: segments.length === 0 };
}

/**
 * Render a table cell from multiple record keys (e.g. mobile + other phone with icons).
 *
 * Column schema:
 * {
 *   "key": "phone",
 *   "label": "Phone",
 *   "format": "joined",
 *   "keys": [
 *     { "key": "mobile", "icon": "smartphone", "title": "Mobile" },
 *     { "key": "phone", "icon": "phone", "title": "Other phone" }
 *   ]
 * }
 *
 * @param {Record<string, unknown>|null|undefined} record
 * @param {{ keys?: Array<{ key: string, label?: string, icon?: string, title?: string, type?: string }>, join?: Array<{ key: string, label?: string, icon?: string, title?: string, type?: string }>, format?: string }} column
 * @param {Record<string, { type?: string, label?: string }>} [fieldsSchema]
 */
export function formatJoinedColumnDisplay(record, column, fieldsSchema = {}) {
    const { segments, isEmpty } = buildJoinedColumnSegments(record, column, fieldsSchema);

    if (isEmpty) {
        return '—';
    }

    return segments
        .map((segment) => {
            const label = segment.label ? `${segment.label} ` : '';

            return `${label}${segment.text}`;
        })
        .join('/');
}

/**
 * @param {unknown} column
 */
export function isJoinedColumnFormat(column) {
    return (
        typeof column === 'object'
        && column !== null
        && (column.format === 'joined' || column.format === 'mobile_home' || column.keys || column.join)
    );
}
