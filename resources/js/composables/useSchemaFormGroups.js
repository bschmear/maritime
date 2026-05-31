import { computed, unref } from 'vue';

/**
 * Build form section groups from domain form.json (contact, address, lead_profile, etc.).
 */
export function buildFormGroups(formSchema) {
    const s = formSchema;
    if (!s || typeof s !== 'object' || typeof s === 'function') {
        return [];
    }

    let form = s.form;
    if (form && typeof form === 'object' && !Array.isArray(form)) {
        // Standard { form: { contact: … }, sublists: … }
    } else if (s.contact || s.address || s.lead_profile || s.notes || s.marketing) {
        form = s;
    } else {
        form = {};
    }

    const reserved = new Set(['settings', 'sublists']);
    const out = [];

    for (const [key, group] of Object.entries(form)) {
        if (reserved.has(key)) {
            continue;
        }
        if (!group || typeof group !== 'object' || group.type === 'specs') {
            continue;
        }
        const fields = Array.isArray(group.fields) ? group.fields : [];
        const items = fields
            .map((f) => (f && typeof f === 'object' && f.key ? { ...f, key: f.key } : null))
            .filter(Boolean)
            .filter((f) => !f.hidden);
        if (!group.is_address && items.length === 0) {
            continue;
        }
        out.push({
            key,
            label: group.label || key,
            is_address: !!group.is_address,
            fields: items,
        });
    }

    return out;
}

function resolveFormSchemaSource(formSchemaSource) {
    if (typeof formSchemaSource === 'function') {
        return formSchemaSource();
    }

    return unref(formSchemaSource);
}

export function useSchemaFormGroups(formSchemaSource) {
    const formGroups = computed(() => buildFormGroups(resolveFormSchemaSource(formSchemaSource)));

    return { formGroups, buildFormGroups };
}

const NUMERIC_ENUM_KEYS = new Set([
    'status_id',
    'source_id',
    'priority_id',
    'budget_range',
    'preferred_contact_method',
    'preferred_contact_time',
]);

/** Contact-backed fields on Lead (mirrors Lead::contactAttributeKeys()). */
const LEAD_CONTACT_FIELD_KEYS = new Set([
    'display_name',
    'first_name',
    'last_name',
    'email',
    'secondary_email',
    'phone',
    'mobile',
    'company',
    'title',
    'position',
    'website',
    'linkedin',
    'facebook',
    'notes',
    'inactive',
    'preferred_contact_method',
    'preferred_contact_time',
]);

/** Primary-address fields on Lead (mirrors Lead::addressAttributeKeys()). */
const LEAD_ADDRESS_FIELD_KEYS = new Set([
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country',
    'latitude',
    'longitude',
]);

function hasFieldValue(value) {
    return value !== null && value !== undefined && value !== '';
}

/**
 * Resolve a flat form field from a Lead record, including nested contact / address.
 */
export function resolveRecordFieldRaw(record, fieldKey) {
    if (!record || !fieldKey) {
        return null;
    }

    if (hasFieldValue(record[fieldKey])) {
        return record[fieldKey];
    }

    const contact = record.contact;
    if (contact && LEAD_CONTACT_FIELD_KEYS.has(fieldKey) && hasFieldValue(contact[fieldKey])) {
        return contact[fieldKey];
    }

    const primaryAddress = contact?.primary_address ?? contact?.primaryAddress;
    if (primaryAddress && LEAD_ADDRESS_FIELD_KEYS.has(fieldKey) && hasFieldValue(primaryAddress[fieldKey])) {
        return primaryAddress[fieldKey];
    }

    const addresses = contact?.addresses;
    if (Array.isArray(addresses) && addresses.length && LEAD_ADDRESS_FIELD_KEYS.has(fieldKey)) {
        const row = addresses.find((a) => a?.is_primary) ?? addresses[0];
        if (row && hasFieldValue(row[fieldKey])) {
            return row[fieldKey];
        }
    }

    return record[fieldKey] ?? null;
}

/**
 * Read-only display value for a record field (show pages).
 */
export function getRecordFieldDisplayValue(record, fieldKey, fieldsSchema, enumOptions) {
    if (!record || !fieldKey) {
        return { text: '—', empty: true };
    }

    const def = fieldsSchema?.[fieldKey] || {};
    const type = def.type || 'text';
    let raw = resolveRecordFieldRaw(record, fieldKey);

    if (fieldKey === 'assigned_user_id') {
        const u = record.assigned_user;
        if (u && typeof u === 'object') {
            raw = u.display_name ?? u.name ?? raw;
        }
    }

    if (type === 'boolean' || type === 'checkbox') {
        if (raw === true || raw === 1 || raw === '1') {
            return { text: 'Yes', empty: false };
        }
        if (raw === false || raw === 0 || raw === '0') {
            return { text: 'No', empty: false };
        }
        return { text: '—', empty: true };
    }

    if (type === 'select' && def.enum) {
        const opts = enumOptions?.[def.enum] || [];
        if (raw != null && raw !== '') {
            const hit = opts.find(
                (o) =>
                    o.id === raw
                    || o.value === raw
                    || String(o.id) === String(raw)
                    || String(o.value) === String(raw),
            );
            if (hit?.name) {
                return { text: hit.name, empty: false };
            }
        }
        return { text: raw != null && raw !== '' ? String(raw) : '—', empty: raw == null || raw === '' };
    }

    if (type === 'date' || type === 'datetime') {
        if (!raw) {
            return { text: '—', empty: true };
        }
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) {
            return { text: String(raw), empty: false };
        }
        const text =
            type === 'datetime'
                ? d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
                : d.toLocaleDateString('en-US', { dateStyle: 'medium' });
        return { text, empty: false };
    }

    if (type === 'number' && fieldKey === 'trade_in_value') {
        if (raw == null || raw === '') {
            return { text: '—', empty: true };
        }
        const num = Number(raw);
        if (Number.isNaN(num)) {
            return { text: String(raw), empty: false };
        }
        return {
            text: num.toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }),
            empty: false,
        };
    }

    if (raw == null || raw === '') {
        return { text: '—', empty: true };
    }

    return { text: String(raw), empty: false, type, raw };
}

function resolveFieldsSchemaSource(fieldsSchemaSource) {
    if (typeof fieldsSchemaSource === 'function') {
        return fieldsSchemaSource();
    }

    const fs = unref(fieldsSchemaSource);
    if (fs?.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
        return fs.fields;
    }

    return fs && typeof fs === 'object' ? fs : {};
}

export function useRecordFieldDisplay(recordSource, fieldsSchemaSource, enumOptionsSource) {
    const fieldDef = (key) => resolveFieldsSchemaSource(fieldsSchemaSource)?.[key] || {};

    const displayFor = (fieldKey) =>
        getRecordFieldDisplayValue(
            typeof recordSource === 'function' ? recordSource() : unref(recordSource),
            fieldKey,
            resolveFieldsSchemaSource(fieldsSchemaSource),
            typeof enumOptionsSource === 'function' ? enumOptionsSource() : unref(enumOptionsSource),
        );

    return { fieldDef, displayFor, NUMERIC_ENUM_KEYS, resolveRecordFieldRaw };
}
