<!-- Form.vue Component with Accordion -->
<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useTimezone } from '@/Composables/useTimezone';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import DateTimeInput from '@/Components/Tenant/FormComponents/DateTime.vue';
import Rating from '@/Components/Tenant/FormComponents/Rating.vue';
import MorphSelect from '@/Components/Tenant/MorphSelect.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import NumberInput from '@/Components/Tenant/FormComponents/Number.vue';
import MeasurementImperialInput from '@/Components/Tenant/FormComponents/MeasurementImperialInput.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { formatLengthMmImperial } from '@/utils/measurementMm.js';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    recordType: { type: String, default: '' },
    recordTitle: { type: String, default: '' },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    mode: {
        type: String,
        default: 'view',
        validator: (value) => ['view', 'edit', 'create'].includes(value),
    },
    preventRedirect: { type: Boolean, default: false },
    formId: { type: String, default: null },
    imageUrls: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
    recordIdentifier: { type: [String, Number], default: null },
    extraRouteParams: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    /** When set (e.g. variant forms), asset-spec definitions are loaded for this asset type instead of using `form.type`. */
    specsContextAssetType: { type: Number, default: null },
    /** Variant modal: send enable_has_variants so the API can turn on has_variants before the main asset is saved. */
    enableHasVariantsOnStore: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel', 'created', 'updated']);

const { convertUTCToTimezone, convertTimezoneToUTC, accountTimezone, accountTimezoneLabel } = useTimezone();

const isEditMode = computed(() => props.mode === 'edit' || props.mode === 'create');
const isCreateMode = computed(() => props.mode === 'create');
const updateRecordId = computed(() => props.recordIdentifier ?? props.record?.id);

// ── Spec values stored directly in form ──────────────────────────
// form.specValues = { [spec_id]: { value_number, value_text, value_boolean, unit } }

const normalizedSchema = computed(() => {
    if (props.schema && props.schema.form) return props.schema.form;
    return props.schema;
});

const columnCount = computed(() => {
    return props.schema?.settings?.columns ?? 2;
});

const formUniqueId = computed(() =>
    `form-${props.recordType}-${props.record?.id || 'new'}-${Math.random().toString(36).substr(2, 9)}`
);

const getFieldId = (fieldKey) => `${formUniqueId.value}-field-${fieldKey}`;

const resolvedFieldsSchema = computed(() => {
    const fs = props.fieldsSchema;
    if (fs && fs.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
        return fs.fields;
    }
    return fs || {};
});

const getFieldDefinition = (fieldKey) => resolvedFieldsSchema.value[fieldKey] || {};

const getRecordSpecValues = () => {
    const r = props.record;
    if (!r) {
        return [];
    }
    if (Array.isArray(r.spec_values)) {
        return r.spec_values;
    }
    if (Array.isArray(r.specValues)) {
        return r.specValues;
    }
    return [];
};

const hasSpecsSection = computed(() => {
    const s = normalizedSchema.value;
    if (!s || typeof s !== 'object') return false;
    return Object.values(s).some((g) => g && typeof g === 'object' && g.type === 'specs');
});

const usesAssetTypeScopedSpecs = computed(() =>
    props.recordType === 'assets' || props.recordType === 'assets.variants',
);

/** When non-null, replaces props.availableSpecs (after fetching by asset type). */
const specsOverrideFromFetch = ref(null);

const resolvedAvailableSpecs = computed(() => {
    if (specsOverrideFromFetch.value !== null) {
        return specsOverrideFromFetch.value;
    }
    return props.availableSpecs || [];
});

// ── Build initial spec values from existing record spec_values ───
const buildInitialSpecValues = () => {
    const specValues = {};
    const existing = {};
    getRecordSpecValues().forEach((sv) => {
        existing[sv.asset_spec_definition_id] = sv;
    });
    resolvedAvailableSpecs.value.forEach(spec => {
        const sv = existing[spec.id];
        specValues[spec.id] = sv
            ? {
                value_number:  sv.value_number  ?? null,
                value_text:    sv.value_text    ?? null,
                value_boolean: sv.value_boolean ?? false,
                unit:          sv.unit          ?? spec.unit ?? null,
            }
            : {
                value_number:  null,
                value_text:    null,
                value_boolean: false,
                unit:          spec.unit ?? null,
            };
    });
    return specValues;
};

const initializeFormData = () => {
    const formData = {};

    if (props.initialData && Object.keys(props.initialData).length > 0) {
        Object.assign(formData, props.initialData);
    }

    if (props.record) {
        const allowedRecordKeys = new Set(Object.keys(resolvedFieldsSchema.value || {}));
        Object.values(resolvedFieldsSchema.value || {}).forEach((fieldDef) => {
            if (fieldDef && fieldDef.type === 'morph' && fieldDef.id_field) {
                allowedRecordKeys.add(fieldDef.id_field);
            }
        });

        Object.keys(props.record).forEach(key => {
            if (!allowedRecordKeys.has(key)) return;
            const fieldDef = getFieldDefinition(key);
            const value = props.record[key];

            if (fieldDef.type === 'datetime' || fieldDef.type === 'date') {
                if (value) {
                    let displayDate;
                    if (value instanceof Date) {
                        displayDate = value;
                    } else if (typeof value === 'string') {
                        const parsedDate = new Date(value);
                        if (!isNaN(parsedDate.getTime())) {
                            displayDate = parsedDate;
                        } else {
                            formData[key] = value;
                            return;
                        }
                    } else {
                        formData[key] = value;
                        return;
                    }
                    const timezoneDate = convertUTCToTimezone(displayDate.toISOString(), accountTimezone.value);
                    formData[key] = fieldDef.type === 'datetime'
                        ? timezoneDate.toISOString().slice(0, 16)
                        : timezoneDate.toISOString().split('T')[0];
                } else {
                    formData[key] = null;
                }
            } else if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                formData[key] = value === true || value === 1 ? 1 : 0;
            } else if (fieldDef.type === 'measurement') {
                if (value == null || value === '') {
                    formData[key] = null;
                } else {
                    const n = Number(value);
                    formData[key] = Number.isFinite(n) && n >= 0 ? n : null;
                }
            } else if (fieldDef.type === 'record' && value && typeof value === 'object' && value.id) {
                formData[key] = value.id;
            } else if (fieldDef.type === 'multi_enum') {
                if (Array.isArray(value) && value.length > 0) {
                    formData[key] = value.map((x) => Number(x));
                } else if (value == null && props.record?.id) {
                    // Legacy makes: NULL in DB = all asset types
                    formData[key] = [1, 2, 3, 4];
                } else {
                    formData[key] = [];
                }
            } else {
                formData[key] = value;
            }
        });
    }

    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    if (!(field.key in formData)) {
                        const fieldDef = getFieldDefinition(field.key);
                        const fieldType = fieldDef.type || 'text';

                        if (fieldDef.default !== undefined && fieldDef.default !== null) {
                            formData[field.key] = fieldDef.default;
                        } else if (fieldDef.default_value !== undefined && fieldDef.default_value !== null) {
                            formData[field.key] = fieldDef.default_value;
                        } else if (fieldType === 'date' && field.defaultDay !== undefined) {
                            const d = new Date();
                            d.setDate(d.getDate() + Number(field.defaultDay));
                            formData[field.key] = d.toISOString().split('T')[0];
                        } else if (fieldType === 'date' && fieldDef.default_today === true) {
                            const now = new Date();
                            const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTimezone.value }));
                            formData[field.key] = localNow.toISOString().split('T')[0];
                        } else if (fieldType === 'datetime' && fieldDef.default_now === true) {
                            const now = new Date();
                            const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTimezone.value }));
                            const year = localNow.getFullYear();
                            const month = String(localNow.getMonth() + 1).padStart(2, '0');
                            const day = String(localNow.getDate()).padStart(2, '0');
                            const hours = String(localNow.getHours()).padStart(2, '0');
                            const minutes = String(localNow.getMinutes()).padStart(2, '0');
                            formData[field.key] = `${year}-${month}-${day}T${hours}:${minutes}`;
                        } else if (fieldType === 'select') {
                            if (fieldDef.enum && props.enumOptions[fieldDef.enum]?.length > 0) {
                                const enumOptions = props.enumOptions[fieldDef.enum];
                                if (fieldDef.default !== undefined && fieldDef.default !== null) {
                                    const defaultOption = enumOptions.find(opt => opt.value === fieldDef.default);
                                    formData[field.key] = defaultOption ? defaultOption.id : enumOptions[0].id;
                                } else if (field.required) {
                                    formData[field.key] = enumOptions[0].id;
                                } else {
                                    formData[field.key] = null;
                                }
                            } else {
                                formData[field.key] = null;
                            }
                        } else if (fieldType === 'multi_enum') {
                            if (fieldDef.default !== undefined && Array.isArray(fieldDef.default)) {
                                formData[field.key] = fieldDef.default.map((x) => Number(x));
                            } else {
                                formData[field.key] = [];
                            }
                        } else if (fieldType === 'record') {
                            formData[field.key] = null;
                        } else if (fieldType === 'morph') {
                            formData[field.key] = null;
                            if (fieldDef.id_field && !(fieldDef.id_field in formData)) {
                                formData[fieldDef.id_field] = null;
                            }
                        } else if (fieldType === 'datetime' || fieldType === 'date' || fieldType === 'time') {
                            formData[field.key] = null;
                        } else if (fieldType === 'rating') {
                            formData[field.key] = 0;
                        } else if (fieldType === 'checkbox' || fieldDef.type === 'boolean') {
                            formData[field.key] = 0;
                        } else if (fieldType === 'measurement') {
                            formData[field.key] = null;
                        } else {
                            formData[field.key] = '';
                        }
                    }
                });
            }
        });
    }

    const fillDefaultForKey = (fieldKey) => {
        if (fieldKey in formData) {
            return;
        }
        const field = { key: fieldKey };
        const fieldDef = getFieldDefinition(fieldKey);
        const fieldType = fieldDef.type || 'text';

        if (fieldDef.default !== undefined && fieldDef.default !== null) {
            formData[fieldKey] = fieldDef.default;
        } else if (fieldDef.default_value !== undefined && fieldDef.default_value !== null) {
            formData[fieldKey] = fieldDef.default_value;
        } else if (fieldType === 'date' && fieldDef.defaultDay !== undefined) {
            const d = new Date();
            d.setDate(d.getDate() + Number(fieldDef.defaultDay));
            formData[fieldKey] = d.toISOString().split('T')[0];
        } else if (fieldType === 'date' && fieldDef.default_today === true) {
            const now = new Date();
            const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTimezone.value }));
            formData[fieldKey] = localNow.toISOString().split('T')[0];
        } else if (fieldType === 'datetime' && fieldDef.default_now === true) {
            const now = new Date();
            const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTimezone.value }));
            const year = localNow.getFullYear();
            const month = String(localNow.getMonth() + 1).padStart(2, '0');
            const day = String(localNow.getDate()).padStart(2, '0');
            const hours = String(localNow.getHours()).padStart(2, '0');
            const minutes = String(localNow.getMinutes()).padStart(2, '0');
            formData[fieldKey] = `${year}-${month}-${day}T${hours}:${minutes}`;
        } else if (fieldType === 'select') {
            if (fieldDef.enum && props.enumOptions[fieldDef.enum]?.length > 0) {
                const enumOptions = props.enumOptions[fieldDef.enum];
                if (fieldDef.default !== undefined && fieldDef.default !== null) {
                    const defaultOption = enumOptions.find((opt) => opt.value === fieldDef.default);
                    formData[fieldKey] = defaultOption ? defaultOption.id : enumOptions[0].id;
                } else if (fieldDef.required) {
                    formData[fieldKey] = enumOptions[0].id;
                } else {
                    formData[fieldKey] = null;
                }
            } else {
                formData[fieldKey] = null;
            }
        } else if (fieldType === 'multi_enum') {
            if (fieldDef.default !== undefined && Array.isArray(fieldDef.default)) {
                formData[fieldKey] = fieldDef.default.map((x) => Number(x));
            } else {
                formData[fieldKey] = [];
            }
        } else if (fieldType === 'record') {
            formData[fieldKey] = null;
        } else if (fieldType === 'morph') {
            formData[fieldKey] = null;
            if (fieldDef.id_field && !(fieldDef.id_field in formData)) {
                formData[fieldDef.id_field] = null;
            }
        } else if (fieldType === 'datetime' || fieldType === 'date' || fieldType === 'time') {
            formData[fieldKey] = null;
        } else if (fieldType === 'rating') {
            formData[fieldKey] = 0;
        } else if (fieldType === 'checkbox' || fieldDef.type === 'boolean') {
            formData[fieldKey] = 0;
        } else if (fieldType === 'measurement') {
            formData[fieldKey] = null;
        } else if (fieldType === 'json') {
            formData[fieldKey] = '';
        } else {
            formData[fieldKey] = '';
        }
    };

    Object.keys(resolvedFieldsSchema.value || {}).forEach((k) => {
        if (resolvedFieldsSchema.value[k]?.spec) {
            fillDefaultForKey(k);
        }
    });

    // Inline spec values
    formData.specValues = buildInitialSpecValues();

    return formData;
};

const form = useForm(initializeFormData());
const isProcessing = ref(false);

watch(
    [
        () => props.recordType,
        () => form.type,
        () => props.specsContextAssetType,
        () => props.availableSpecs?.length ?? 0,
    ],
    async ([recordType, type, ctxType, availableLen], oldVals) => {
        if (!usesAssetTypeScopedSpecs.value || !hasSpecsSection.value || props.mode === 'view') {
            return;
        }

        if (oldVals === undefined && availableLen > 0) {
            return;
        }

        let assetType = null;
        if (recordType === 'assets') {
            assetType = type === null || type === '' ? null : Number(type);
        } else if (recordType === 'assets.variants') {
            assetType = ctxType === null || ctxType === '' ? null : Number(ctxType);
        }

        if (assetType === null || Number.isNaN(assetType)) {
            specsOverrideFromFetch.value = [];
            return;
        }

        try {
            const { data } = await axios.get(route('asset-specs.index'), {
                params: { asset_type: assetType },
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            specsOverrideFromFetch.value = data?.specs ?? [];
        } catch {
            specsOverrideFromFetch.value = [];
        }
    },
    { immediate: true },
);

// Re-seed spec values when resolved specs or record changes
watch([resolvedAvailableSpecs, () => props.record], () => {
    form.specValues = buildInitialSpecValues();
}, { deep: true });

watch(() => props.record, (newRecord) => {
    if (newRecord) {
        form.clearErrors();
        Object.keys(newRecord).forEach(key => {
            const fieldDef = getFieldDefinition(key);
            if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                form[key] = newRecord[key] === true || newRecord[key] === 1 ? 1 : 0;
            } else if (fieldDef.type === 'datetime' || fieldDef.type === 'date') {
                const dateValue = newRecord[key];
                if (dateValue) {
                    let utcDate;
                    if (dateValue instanceof Date) {
                        utcDate = dateValue;
                    } else if (typeof dateValue === 'string') {
                        const parsedDate = new Date(dateValue);
                        if (!isNaN(parsedDate.getTime())) {
                            utcDate = parsedDate;
                        } else {
                            form[key] = dateValue;
                            return;
                        }
                    } else {
                        form[key] = dateValue;
                        return;
                    }
                    const timezoneDate = convertUTCToTimezone(utcDate.toISOString(), accountTimezone.value);
                    form[key] = fieldDef.type === 'datetime'
                        ? timezoneDate.toISOString().slice(0, 16)
                        : timezoneDate.toISOString().split('T')[0];
                } else {
                    form[key] = null;
                }
                } else if (fieldDef.type === 'measurement') {
                    const v = newRecord[key];
                    if (v == null || v === '') {
                        form[key] = null;
                    } else {
                        const n = Number(v);
                        form[key] = Number.isFinite(n) && n >= 0 ? n : null;
                    }
                } else {
                    form[key] = newRecord[key] ?? '';
                }
        });
    }
}, { deep: true, immediate: true });

watch(() => form.data(), (newData, oldData) => {
    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    if (field.conditional && !isFieldVisible(field)) {
                        const ft = getFieldType(field.key);
                        if (ft === 'checkbox' || ft === 'boolean') {
                            form[field.key] = 0;
                        } else if (ft === 'measurement') {
                            form[field.key] = null;
                        } else {
                            form[field.key] = '';
                        }
                    }
                    const fieldDef = getFieldDefinition(field.key);
                    if (fieldDef && fieldDef.filterby) {
                        const filterFieldKey = fieldDef.filterby;
                        if (oldData && newData[filterFieldKey] !== oldData[filterFieldKey]) {
                            form[field.key] = null;
                        }
                    }
                });
            }
        });
    }
}, { deep: true });

// ── Grouped specs (spec_groups.name via relation) ─────────────────
const groupedSpecSections = computed(() => {
    const buckets = new Map();
    (resolvedAvailableSpecs.value || []).forEach((spec) => {
        const gid = spec.group_id ?? '__none__';
        if (!buckets.has(gid)) {
            buckets.set(gid, {
                key: String(gid),
                label: spec.group?.name || 'General',
                sortPos: spec.group?.position ?? 9999,
                specs: [],
            });
        }
        buckets.get(gid).specs.push(spec);
    });
    for (const b of buckets.values()) {
        b.specs.sort((a, c) => (a.position ?? 0) - (c.position ?? 0));
    }
    return [...buckets.values()].sort((a, b) => {
        if (a.sortPos !== b.sortPos) return a.sortPos - b.sortPos;
        return a.label.localeCompare(b.label);
    });
});

// ── Build specs array for submission ────────────────────────────
const buildSpecsPayload = () => {
    return resolvedAvailableSpecs.value.map(spec => {
        const val = form.specValues?.[spec.id] || {};
        return {
            spec_id:       spec.id,
            value_number:  spec.type === 'number'
                ? (val.value_number !== '' && val.value_number !== null ? val.value_number : null)
                : null,
            value_text:    (spec.type === 'text' || spec.type === 'select')
                ? (val.value_text || null)
                : null,
            value_boolean: spec.type === 'boolean' ? (val.value_boolean ? 1 : 0) : null,
            unit:          val.unit || null,
        };
    });
};

// ── Display helpers for spec view mode ──────────────────────────
const getSpecDisplayValue = (spec) => {
    const sv = getRecordSpecValues().find((s) => s.asset_spec_definition_id === spec.id);
    if (!sv) return null;

    if (spec.type === 'number')  return sv.value_number  == null ? null : parseFloat((+sv.value_number).toFixed(2));
    if (spec.type === 'boolean') return sv.value_boolean ?? null;
    if (spec.type === 'select')  return sv.value_text    ?? null;
    if (spec.type === 'text')    return sv.value_text    ?? null;

    return null;
};
const formatNumber = (val) => {
    if (val === null || val === undefined || val === '') return '';
    const n = parseFloat(val);
    return isNaN(n) ? '' : n.toFixed(2);
};
const getSpecDisplayUnit = (spec) => {
    const sv = getRecordSpecValues().find((s) => s.asset_spec_definition_id === spec.id);
    return (sv?.unit) ? sv.unit : (spec.unit || null);
};

const formGroups = computed(() => {
    if (!normalizedSchema.value) return [];
    return Object.entries(normalizedSchema.value)
        .filter(([, group]) => group && typeof group === 'object')
        .map(([key, group], index) => ({
            key,
            index,
            label: group.label || key,
            type: group.type || null,
            is_address: group.is_address || false,
            conditional: group.conditional || null,
            filteredFields: (group.fields || [])
                .filter((f) => f && typeof f === 'object' && f.key)
                .filter((f) => !getFieldDefinition(f.key).spec),
        }));
});

const staticSpecFormFieldEntries = computed(() => {
    const fs = resolvedFieldsSchema.value;
    if (!fs) return [];
    return Object.keys(fs).filter((k) => fs[k] && fs[k].spec);
});

const openSections = ref({});
const imagePreviews = ref({});

const getStorageKey = () => `form-sections-${props.recordType}-${props.mode}`;

const handleImageInput = (fieldKey, event) => {
    const file = event.target.files[0];
    if (file) {
        form[fieldKey] = file;
        imagePreviews.value[fieldKey] = URL.createObjectURL(file);
    }
};

const getImageSource = (fieldKey) => {
    if (imagePreviews.value[fieldKey]) return imagePreviews.value[fieldKey];
    if (props.imageUrls?.[fieldKey]) return props.imageUrls[fieldKey];
    const val = form[fieldKey];
    if (val && typeof val === 'string') {
        if (val.startsWith('http')) return val;
        return `/storage/${val.replace(/^public\//, '')}`;
    }
    return null;
};

const getFieldValue = (fieldKey) => form[fieldKey] ?? '';

const toggleMultiEnumValue = (fieldKey, optionId) => {
    const id = Number(optionId);
    if (!Array.isArray(form[fieldKey])) {
        form[fieldKey] = [];
    }
    const arr = form[fieldKey].map((x) => Number(x));
    const i = arr.indexOf(id);
    if (i >= 0) {
        arr.splice(i, 1);
        } else {
        arr.push(id);
    }
    form[fieldKey] = arr;
};

const isMultiEnumSelected = (fieldKey, optionId) => {
    const id = Number(optionId);
    if (!Array.isArray(form[fieldKey])) return false;
    return form[fieldKey].map((x) => Number(x)).includes(id);
};

const getMultiEnumDisplay = (fieldKey) => {
    const def = getFieldDefinition(fieldKey);
    const ids = form[fieldKey];
    if (!Array.isArray(ids) || ids.length === 0) return '—';
    const opts = getEnumOptions(fieldKey);
    return ids
        .map((id) => opts.find((o) => Number(o.id) === Number(id) || Number(o.value) === Number(id))?.name ?? id)
        .join(', ');
};

/** Append a value to FormData; arrays of scalars use Laravel-friendly key[] entries. */
const appendFormDataValue = (formData, key, value) => {
    if (value === null || value === undefined) return;
    if (Array.isArray(value)) {
        if (value.length === 0) return;
        if (typeof value[0] !== 'object' || value[0] instanceof File) {
            value.forEach((v) => formData.append(`${key}[]`, v));
            return;
        }
    }
    if (typeof value === 'object' && !(value instanceof File) && !(value instanceof Blob)) {
        formData.append(key, JSON.stringify(value));
        return;
    }
    formData.append(key, value);
};

const applySourcedDefaults = (changedFieldKey, selectedRecord) => {
    if (!selectedRecord || !props.fieldsSchema) return;
    const fieldsSchema = props.fieldsSchema.fields || props.fieldsSchema;
    for (const [fieldKey, fieldDef] of Object.entries(fieldsSchema)) {
        if (!fieldDef?.sourced_default) continue;
        const [sourceFieldKey, sourceProperty] = fieldDef.sourced_default.split('.');
        if (sourceFieldKey !== changedFieldKey) continue;
        const value = selectedRecord[sourceProperty];
        if (value !== undefined && value !== null && value !== '') {
            form[fieldKey] = value;
        }
    }
};

const getEnumOptions = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    if (fieldDef.enum) return props.enumOptions[fieldDef.enum] || [];
    if (fieldDef.type === 'record' && fieldDef.typeDomain) {
        if (props.enumOptions[fieldKey]) return props.enumOptions[fieldKey];
        const domainKey = `Domain\\${fieldDef.typeDomain}\\Models\\${fieldDef.typeDomain}`;
        return props.enumOptions[domainKey] || [];
    }
    return [];
};

const getEnumLabel = (fieldKey, value) => {
    const options = getEnumOptions(fieldKey);
    const valueStr = value != null ? String(value) : '';
    const option = options.find(opt => 
        String(opt.id) === valueStr || String(opt.value) === valueStr ||
        opt.id === value || opt.value === value
    );
    return option ? option.name : value;
};

const relationshipKeyOnRecord = (fieldKey, fieldDef) => {
    if (fieldDef.relationship) {
        return fieldDef.relationship.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, '');
    }
    if (fieldKey.endsWith('_id')) return fieldKey.slice(0, -3);
    return fieldKey.replace('_id', '');
};

const getRecordDisplayName = (fieldKey, value) => {
    if (!value) return '—';
    const fieldDef = getFieldDefinition(fieldKey);
    if (fieldDef.type === 'record' && props.record) {
        const relationshipName = relationshipKeyOnRecord(fieldKey, fieldDef);
        const relatedRecord = props.record[relationshipName];
        if (relatedRecord?.display_name) return relatedRecord.display_name;
        }
    return getEnumLabel(fieldKey, value);
};

const getMorphRelatedDisplayName = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    if (!fieldDef || fieldDef.type !== 'morph' || !props.record) return '';
    const relationshipName = fieldKey.replace('_type', '');
    return props.record[relationshipName]?.display_name || '';
};

const getFieldType = (fieldKey) => {
    const d = getFieldDefinition(fieldKey);
    if (d.type === 'measurement') {
        return 'measurement';
    }
    if (d.measurement && d.type === 'text') {
        return 'measurement';
    }
    return d.type || 'text';
};
const getFieldLabel = (fieldKey) => getFieldDefinition(fieldKey).label || fieldKey;
const isFieldRequired = (field) => {
    if (!field || typeof field !== 'object') return false;
    if (field.required === true) return true;
    return getFieldDefinition(field.key).required === true;
};
const isFieldDisabled = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    return fieldDef.disabled === true || (!isEditMode.value && props.mode === 'view');
};
const isFieldDisabledByFilter = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    if (fieldDef?.filterby) {
        const filterFieldValue = form[fieldDef.filterby];
        return !filterFieldValue || filterFieldValue === '' || filterFieldValue === null;
    }
    return false;
};
const getFieldFilterValue = (fieldKey) => {
    const fieldDef = getFieldDefinition(fieldKey);
    return fieldDef?.filterby ? (form[fieldDef.filterby] || null) : null;
};

const getConditionalFieldValue = (fieldPath) => {
    if (fieldPath.includes('.')) {
        let [relationshipOrTypeDomain, fieldName] = fieldPath.split('.', 2);
        let relationshipName = relationshipOrTypeDomain;
        if (resolvedFieldsSchema.value) {
            const fieldWithTypeDomain = Object.values(resolvedFieldsSchema.value).find(
                (field) => field.typeDomain === relationshipOrTypeDomain
            );
            if (fieldWithTypeDomain?.relationship) relationshipName = fieldWithTypeDomain.relationship;
        }
        if (props.record?.[relationshipName]) return props.record[relationshipName][fieldName];
        const relationshipData = form[relationshipName];
        if (relationshipData && typeof relationshipData === 'object') return relationshipData[fieldName];
        if (props.initialData?.[relationshipName] && typeof props.initialData[relationshipName] === 'object') {
            return props.initialData[relationshipName][fieldName];
        }
        return undefined;
    }
    return form[fieldPath];
};

const isFieldVisible = (field) => {
    if (!field || typeof field !== 'object') return false;
    const def = getFieldDefinition(field.key);
    if (def && def.update_only === true && isCreateMode.value) return false;
    if (field.update_only === true && isCreateMode.value) return false;
    const cond =
        (field.conditional && typeof field.conditional === 'object' ? field.conditional : null) ||
        (def && def.conditional && typeof def.conditional === 'object' ? def.conditional : null);
    if (cond) {
        const { key, value, operator = 'equals' } = cond;
        const currentValue = getConditionalFieldValue(key);
        const boolCurrent = currentValue === 1 || currentValue === true;
        switch (operator) {
            case 'equals':
            case 'eq':
                if (typeof value === 'boolean') return boolCurrent === value;
                return currentValue == value;
            case 'not_equals':
            case 'neq':
                if (typeof value === 'boolean') return boolCurrent !== value;
                return currentValue != value;
            case 'greater_than': case 'gt': return currentValue > value;
            case 'less_than': case 'lt':    return currentValue < value;
            case 'contains':    return String(currentValue).includes(String(value));
            case 'is_empty':    return !currentValue || currentValue === '';
            case 'is_not_empty': return currentValue && currentValue !== '';
            default:
                if (typeof value === 'boolean') return boolCurrent === value;
                return currentValue == value;
        }
    }
    return true;
};

const isGroupVisible = (group) => {
    if (!group.conditional || typeof group.conditional !== 'object') return true;
    const { key, value, operator = 'equals' } = group.conditional;
    const currentValue = form[key];
    const boolCurrent = currentValue === 1 || currentValue === true;
    switch (operator) {
        case 'equals': case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : currentValue == value;
        case 'not_equals': case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : currentValue != value;
        case 'greater_than': case 'gt': return currentValue > value;
        case 'less_than': case 'lt':    return currentValue < value;
        case 'contains':    return String(currentValue).includes(String(value));
        case 'is_empty':    return !currentValue || currentValue === '';
        case 'is_not_empty': return currentValue && currentValue !== '';
        default:
            return typeof value === 'boolean' ? boolCurrent === value : currentValue == value;
    }
};

const visibleFormGroups = computed(() => formGroups.value.filter(isGroupVisible));
const useFormAccordion = computed(() => visibleFormGroups.value.length > 1);

watch(() => visibleFormGroups.value, (groups) => {
    if (groups.length > 0 && Object.keys(openSections.value).length === 0) {
        const storageKey = getStorageKey();
        const savedState = localStorage.getItem(storageKey);
        if (savedState) {
            try {
                const parsed = JSON.parse(savedState);
                groups.forEach(group => {
                    openSections.value[group.key] = parsed[group.key] !== undefined ? parsed[group.key] : true;
                });
            } catch {
                groups.forEach(group => { openSections.value[group.key] = true; });
            }
        } else {
            groups.forEach(group => { openSections.value[group.key] = true; });
        }
    }
}, { immediate: true });

watch(openSections, () => {
    if (!useFormAccordion.value) return;
    localStorage.setItem(getStorageKey(), JSON.stringify(openSections.value));
}, { deep: true });

const toggleSection = (key) => { openSections.value[key] = !openSections.value[key]; };

const getFieldColSpan = (field) => {
    if (field.col_span) return field.col_span;
    if (field.span) return `sm:col-span-${field.span}`;
    const fieldType = getFieldType(field.key);
    if (fieldType === 'textarea' || field.key === 'address_line_1' || field.key === 'address_line_2' ||
        fieldType === 'editor' || fieldType === 'wysiwyg') return 'sm:col-span-12';
    return `sm:col-span-${Math.floor(12 / columnCount.value)}`;
};

const formatPhoneNumber = (value) => {
    if (!value) return '';
    const numbers = value.replace(/\D/g, '');
    if (numbers.length <= 3) return numbers;
    if (numbers.length <= 6) return `(${numbers.slice(0, 3)}) ${numbers.slice(3)}`;
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3, 6)}-${numbers.slice(6, 10)}`;
};
const unformatPhoneNumber = (value) => value ? value.replace(/\D/g, '') : '';
const handlePhoneInput = (fieldKey, event) => {
    const input = event.target;
    const cursorPosition = input.selectionStart;
    const oldValue = input.value;
    const unformatted = unformatPhoneNumber(oldValue);
    const formatted = formatPhoneNumber(unformatted);
    form[fieldKey] = unformatted;
    input.value = formatted;
    const digitsBeforeCursor = unformatPhoneNumber(oldValue.slice(0, cursorPosition)).length;
    let newPosition = 0;
    let digitCount = 0;
    for (let i = 0; i < formatted.length && digitCount < digitsBeforeCursor; i++) {
        if (/\d/.test(formatted[i])) digitCount++;
        newPosition = i + 1;
    }
    setTimeout(() => input.setSelectionRange(newPosition, newPosition), 0);
};
const getFormattedPhoneValue = (fieldKey) => formatPhoneNumber(form[fieldKey] || '');

const hasAddressTags = (group) => group.filteredFields?.some(field => field.tag);
const getAddressFieldValue = (group, tag) => {
    const field = group.filteredFields?.find(f => f.tag === tag);
    return field ? (form[field.key] || '') : '';
};
const updateAddressFields = (group, data) => {
    Object.keys(data).forEach(emittedKey => {
        let tag = emittedKey;
        if (emittedKey === 'stateCode')   tag = 'state_code';
        if (emittedKey === 'postalCode')  tag = 'postal_code';
        if (emittedKey === 'countryCode') tag = 'country_code';
        const field = group.filteredFields?.find(f => f.tag === tag || f.tag === emittedKey);
        if (field) form[field.key] = data[emittedKey];
    });
};
const handleFileInput = (fieldKey, event) => {
    const file = event.target.files[0];
    if (file) form[fieldKey] = file;
};
const getFileName = (filePath) => {
    if (!filePath) return '';
    return filePath.split('/').pop().split('\\').pop();
};
const formatDate = (value) => {
    if (!value) return '';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'long', day: 'numeric' }).format(date);
    } catch { return value; }
};
const formatDateTime = (value) => {
    if (!value) return '';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true,
        }).format(date);
    } catch { return value; }
};

const prepareFormData = () => {
    const data = { ...form.data() };
    if (normalizedSchema.value) {
        Object.values(normalizedSchema.value).filter(g => g && typeof g === 'object').forEach(group => {
            if (group.fields && Array.isArray(group.fields)) {
                group.fields.filter(f => f && typeof f === 'object' && f.key).forEach(field => {
                    const fieldDef = getFieldDefinition(field.key);
                    if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                        data[field.key] = data[field.key] === true || data[field.key] === 1 ? 1 : 0;
                    } else if ((fieldDef.type === 'datetime' || fieldDef.type === 'date') && data[field.key]) {
                        const timezoneDate = new Date(data[field.key]);
                        const utcDate = convertTimezoneToUTC(timezoneDate.toISOString(), accountTimezone.value);
                        data[field.key] = fieldDef.type === 'datetime'
                            ? utcDate.toISOString().slice(0, 16)
                            : utcDate.toISOString().split('T')[0];
                    }
                });
            }
        });
    }
    // Replace specValues map with a flat specs array for the controller
    delete data.specValues;
    data.specs = buildSpecsPayload();
    if (props.enableHasVariantsOnStore && props.recordType === 'assets.variants') {
        data.enable_has_variants = true;
    }
    return data;
};

const snapshotSpecCurrentForAi = (spec) => {
    const sv = form.specValues?.[spec.id];
    if (!sv) {
        return null;
    }
    if (spec.type === 'number') {
        return sv.value_number ?? null;
    }
    if (spec.type === 'boolean') {
        return sv.value_boolean === true || sv.value_boolean === 1;
    }
    if (spec.type === 'text' || spec.type === 'select') {
        return sv.value_text ?? null;
    }
    return null;
};

/** Rich specs + scalar fields for variant AI endpoint (not used for submit). */
const getVariantAiRequestPayload = () => {
    const data = prepareFormData();
    data.specs = (resolvedAvailableSpecs.value || []).map((spec) => ({
        id: spec.id,
        label: spec.label,
        type: spec.type,
        unit: spec.unit ?? null,
        options: spec.type === 'select' && Array.isArray(spec.options) ? spec.options : null,
        current: snapshotSpecCurrentForAi(spec),
    }));
    return data;
};

const applyVariantAiResponse = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return;
    }
    if (typeof payload.name === 'string' && payload.name.trim()) {
        form.name = payload.name.trim().slice(0, 255);
    }
    if (typeof payload.description === 'string' && payload.description.trim()) {
        form.description = payload.description.trim();
    }
    if (payload.length != null && Number.isFinite(Number(payload.length))) {
        form.length = Math.round(Number(payload.length));
    }
    if (payload.width != null && Number.isFinite(Number(payload.width))) {
        form.width = Math.round(Number(payload.width));
    }
    if (payload.default_cost != null && payload.default_cost !== '' && !Number.isNaN(Number(payload.default_cost))) {
        form.default_cost = Number(payload.default_cost);
    }
    if (payload.default_price != null && payload.default_price !== '' && !Number.isNaN(Number(payload.default_price))) {
        form.default_price = Number(payload.default_price);
    }
    for (const u of payload.spec_updates || []) {
        const id = Number(u.spec_id);
        if (!Number.isFinite(id) || !form.specValues?.[id]) {
            continue;
        }
        const cell = form.specValues[id];
        if (u.value_number !== null && u.value_number !== undefined && !Number.isNaN(Number(u.value_number))) {
            cell.value_number = Number(u.value_number);
        }
        if (u.value_text !== undefined && u.value_text !== null) {
            cell.value_text = String(u.value_text);
        }
        if (u.value_boolean !== undefined && u.value_boolean !== null) {
            cell.value_boolean = Boolean(u.value_boolean);
        }
        if (u.unit != null && u.unit !== '') {
            cell.unit = u.unit;
        }
    }
};

/** Copy another variant’s scalar fields + spec values into this form (create or edit). */
const applyCopiedVariantRecord = (srcRaw) => {
    if (!srcRaw || typeof srcRaw !== 'object') {
        return;
    }
    const src = { ...srcRaw };
    delete src.id;
    delete src.asset_id;
    delete src.created_at;
    delete src.updated_at;
    delete src.key;
    const specRows = src.spec_values || src.specValues;
    delete src.spec_values;
    delete src.specValues;
    delete src.resolved_description;

    Object.keys(src).forEach((key) => {
        if (!Object.prototype.hasOwnProperty.call(resolvedFieldsSchema.value, key)) {
            return;
        }
        const def = getFieldDefinition(key);
        const value = src[key];
        if (def.type === 'checkbox' || def.type === 'boolean') {
            form[key] = value === true || value === 1 ? 1 : 0;
        } else if (def.type === 'measurement') {
            if (value == null || value === '') {
                form[key] = null;
            } else {
                const n = Number(value);
                form[key] = Number.isFinite(n) && n >= 0 ? n : null;
            }
        } else if (def.type === 'currency' || def.type === 'number') {
            const n = Number(value);
            form[key] = value != null && value !== '' && Number.isFinite(n) ? n : null;
        } else {
            form[key] = value ?? '';
        }
    });

    const list = Array.isArray(specRows) ? specRows : [];
    const byDefId = {};
    list.forEach((sv) => {
        const sid = sv.asset_spec_definition_id;
        if (sid != null) {
            byDefId[sid] = sv;
        }
    });
    (resolvedAvailableSpecs.value || []).forEach((spec) => {
        const sv = byDefId[spec.id];
        if (!sv || !form.specValues?.[spec.id]) {
            return;
        }
        const cell = form.specValues[spec.id];
        if (spec.type === 'number' && sv.value_number != null && sv.value_number !== '') {
            const n = parseFloat(sv.value_number);
            cell.value_number = Number.isFinite(n) ? n : null;
        } else if (spec.type === 'boolean') {
            cell.value_boolean = sv.value_boolean === true || sv.value_boolean === 1;
        } else if (spec.type === 'text' || spec.type === 'select') {
            cell.value_text = sv.value_text != null ? String(sv.value_text) : null;
        }
        if (sv.unit != null && sv.unit !== '') {
            cell.unit = sv.unit;
        }
    });
};

const handleSubmit = () => {
    const rawData = prepareFormData();
    const hasFiles = Object.values(rawData).some(val => val instanceof File || val instanceof Blob);
    
    if (isCreateMode.value) {
        if (props.preventRedirect) {
            isProcessing.value = true;
            let submissionData = rawData;
            if (hasFiles) {
                const formData = new FormData();
                Object.keys(rawData).forEach((key) => {
                    appendFormDataValue(formData, key, rawData[key]);
                });
                submissionData = formData;
            }
            axios.post(route(`${props.recordType}.store`, props.extraRouteParams), submissionData, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })
            .then((response) => {
                const recordId = response.data?.recordId || response.data?.data?.recordId;
                if (recordId) { form.reset(); emit('created', recordId); }
                else emit('submit');
            })
            .catch((error) => {
                if (error.response?.status === 422) form.errors = error.response.data.errors || {};
                else form.errors = { general: [error.response?.data?.message || 'An error occurred'] };
            })
            .finally(() => { isProcessing.value = false; });

        } else {
            form.transform(() => rawData).post(route(`${props.recordType}.store`, props.extraRouteParams), {
                preserveScroll: true,
                onSuccess: (page) => {
                    let recordId = page?.props?.flash?.recordId;
                    if (!recordId) {
                        const urlMatch = page?.url?.match(/\/(\d+)$/);
                        if (urlMatch) recordId = urlMatch[1];
                        }
                    if (recordId) emit('created', recordId);
                    emit('submit');
                },
            });
        }

    } else {
        if (props.preventRedirect) {
            isProcessing.value = true;
            let submissionData = rawData;
            let method = 'put';
            const url = route(
                `${props.recordType}.update`,
                buildResourceRouteParams(props.recordType, updateRecordId.value, props.extraRouteParams)
            );
            if (hasFiles) {
                const formData = new FormData();
                formData.append('_method', 'PUT');
                Object.keys(rawData).forEach((key) => {
                    appendFormDataValue(formData, key, rawData[key]);
                });
                submissionData = formData;
                method = 'post';
            }
            axios[method](url, submissionData, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })
            .then((response) => {
                const updatedRecord = response.data?.record || response.data?.data?.record;
                if (updatedRecord) emit('updated', updatedRecord);
                else emit('submit');
            })
            .catch((error) => {
                if (error.response?.status === 422) form.errors = error.response.data.errors || {};
                else form.errors = { general: [error.response?.data?.message || 'An error occurred'] };
            })
            .finally(() => { isProcessing.value = false; });

        } else {
            form.transform(() => rawData).put(
                route(
                    `${props.recordType}.update`,
                    buildResourceRouteParams(props.recordType, updateRecordId.value, props.extraRouteParams)
                ),
                {
                preserveScroll: true,
                    onSuccess: () => {
                    emit('submit');
                    router.reload({ only: ['record', 'imageUrls'] });
                },
                }
            );
        }
    }
};

const handleCancel = () => {
        form.reset();
        emit('cancel');
};

const submitForm = () => handleSubmit();
const cancelForm = () => handleCancel();
const isFormProcessing = computed(() => form.processing || isProcessing.value);

defineExpose({
    submitForm,
    cancelForm,
    isProcessing: isFormProcessing,
    getVariantAiRequestPayload,
    applyVariantAiResponse,
    applyCopiedVariantRecord,
});
</script>

<template>
    <form :id="formId || `form-${recordType}-${record?.id || 'new'}`" @submit.prevent="handleSubmit" v-if="normalizedSchema">
        <div id="accordion-collapse">
            <div v-for="(group, groupIndex) in visibleFormGroups" :key="group.key">
                <div>
                    <!-- Accordion header -->
                    <h2 v-if="useFormAccordion" :id="`accordion-heading-${group.index}`">
                        <button
                            type="button"
                            @click="toggleSection(group.key)"
                            class="flex justify-between items-center py-4 px-4 w-full font-medium leading-none text-left text-gray-900 bg-gray-100 sm:px-5 focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-800 dark:text-white hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                            :class="groupIndex > 0 ? 'border-t border-gray-200 dark:border-gray-700' : ''"
                            :aria-expanded="openSections[group.key]"
                        >
                            <span>{{ group.label }}</span>
                            <svg class="w-6 h-6 shrink-0 transition-transform duration-200"
                                :class="openSections[group.key] ? 'rotate-180' : ''"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </h2>
                    <h2 v-else
                        class="py-4 px-4 font-medium leading-none text-left text-gray-900 bg-gray-100 sm:px-5 dark:text-white dark:bg-gray-700"
                        :class="groupIndex > 0 ? 'border-t border-gray-200 dark:border-gray-700' : ''">
                        {{ group.label }}
                    </h2>

                    <!-- ── SPECS SECTION ── -->
                    <template v-if="group.type === 'specs'">
                        <div
                            v-show="!useFormAccordion || openSections[group.key]"
                            class="p-4 sm:p-5"
                        >
                        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Specifications for this asset type
            </p>
            <Link
                :href="route('asset-specs.index')"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 cursor-pointer"
            >
                <span class="material-icons text-[16px]">tune</span>
                Manage spec definitions
            </Link>
        </div>
                            <div
                                v-if="!staticSpecFormFieldEntries.length && !resolvedAvailableSpecs.length"
                                class="py-6 text-center text-sm text-gray-400 dark:text-gray-500"
                            >
                                No specifications available for this asset type.
                            </div>
                            <div v-else>
                            <div
                                v-if="staticSpecFormFieldEntries.length"
                                class="mb-8 border-b border-gray-200 pb-6 dark:border-gray-700"
                            >
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Standard dimensions &amp; classification
                                </p>
                                <div class="grid gap-4 sm:grid-cols-12">
                                <div
                                    v-for="sk in staticSpecFormFieldEntries"
                                    :key="'static-spec-'+sk"
                                    v-show="isFieldVisible({ key: sk })"
                                    :class="getFieldColSpan({ key: sk })"
                                >
                                    <label
                                        :for="getFieldId(sk)"
                                        class="mb-2 block text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ getFieldLabel(sk) }}
                                        <span v-if="isFieldRequired({ key: sk })" class="text-red-500">*</span>
                                    </label>
                                    <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                        <span v-if="getFieldType(sk) === 'select' && getFieldDefinition(sk).enum">
                                            {{ getEnumLabel(sk, getFieldValue(sk)) || '—' }}
                                        </span>
                                        <span v-else-if="getFieldType(sk) === 'measurement'">{{ formatLengthMmImperial(getFieldValue(sk)) }}</span>
                                        <span v-else>{{ getFieldValue(sk) || '—' }}</span>
                                    </div>
                                    <div v-else>
                                        <MeasurementImperialInput
                                            v-if="getFieldType(sk) === 'measurement'"
                                            :id="getFieldId(sk)"
                                            v-model="form[sk]"
                                            :required="isFieldRequired({ key: sk })"
                                            :disabled="isFieldDisabled(sk)"
                                        />
                                        <input
                                            v-else-if="['text', 'email', 'url'].includes(getFieldType(sk))"
                                            :id="getFieldId(sk)"
                                            v-model="form[sk]"
                                            :type="getFieldType(sk) === 'url' ? 'url' : 'text'"
                                            :required="isFieldRequired({ key: sk })"
                                            :disabled="isFieldDisabled(sk)"
                                            :placeholder="getFieldDefinition(sk).placeholder"
                                            class="input-style"
                                        >
                                        <select
                                            v-else-if="getFieldType(sk) === 'select'"
                                            :id="getFieldId(sk)"
                                            v-model="form[sk]"
                                            :required="isFieldRequired({ key: sk })"
                                            :disabled="isFieldDisabled(sk)"
                                            :class="['input-style w-full', !form[sk] ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900']"
                                        >
                                            <option v-if="!isFieldRequired({ key: sk })" value="" disabled>
                                                Select {{ getFieldLabel(sk) }}
                                            </option>
                                            <option v-for="opt in getEnumOptions(sk)" :key="opt.id" :value="opt.id">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div v-if="resolvedAvailableSpecs.length" class="space-y-6">
                                <div v-for="section in groupedSpecSections" :key="section.key">
                                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ section.label }}
                                    </h3>
                                    <div class="grid gap-4 sm:grid-cols-12">
                                        <div v-for="spec in section.specs" :key="spec.id" class="sm:col-span-6 xl:col-span-4">

                                            <!-- View mode -->
                                            <template v-if="!isEditMode">
                                                <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ spec.label }}
                                                    <span v-if="spec.is_required" class="ml-1 text-red-500">*</span>
                                                </p>
                                                <p class="text-sm text-gray-900 dark:text-white">
                                                    <template v-if="spec.type === 'boolean'">
                                                        <span :class="getSpecDisplayValue(spec) ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'">
                                                            {{ getSpecDisplayValue(spec) ? 'Yes' : 'No' }}
                                                        </span>
                                                    </template>
                                                    <template v-else-if="getSpecDisplayValue(spec) !== null && getSpecDisplayValue(spec) !== ''">
                                                        {{ getSpecDisplayValue(spec) }}
                                                        <span v-if="getSpecDisplayUnit(spec)" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                                            {{ getSpecDisplayUnit(spec) }}
                                                        </span>
                                                    </template>
                                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                                </p>
                                            </template>

                                            <!-- Edit mode -->
                                            <template v-else-if="form.specValues?.[spec.id]">
                                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ spec.label }}
                                                    <span v-if="spec.is_required" class="text-red-500">*</span>
                                                </label>

                                                <!-- Number -->
                                                <div v-if="spec.type === 'number'" class="flex items-center gap-2">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                                        :value="form.specValues[spec.id].value_number != null
                                                            ? form.specValues[spec.id].value_number
                                                            : ''"
                                                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                                        @change="e => {
                                                            const n = parseFloat(e.target.value);
                                                            form.specValues[spec.id].value_number = isNaN(n) ? null : n;
                                                        }"
                                                        @blur="e => {
                                                            const n = parseFloat(e.target.value);
                                                            e.target.value = isNaN(n) ? '' : n.toFixed(2);
                                                        }"
                                                    />
                                                    <span v-if="getSpecDisplayUnit(spec)" class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ getSpecDisplayUnit(spec) }}
                                                    </span>
                                                </div>

                                                <!-- Text -->
                                                <input
                                                    v-else-if="spec.type === 'text'"
                                                    v-model="form.specValues[spec.id].value_text"
                                                    type="text"
                                                    :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                                />

                                                <!-- Select -->
                                                <select
                                                    v-else-if="spec.type === 'select'"
                                                    v-model="form.specValues[spec.id].value_text"
                                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                >
                                                    <option value="">Select {{ spec.label.toLowerCase() }}</option>
                                                    <option v-for="option in (spec.options || [])" :key="option.value" :value="option.value">
                                                        {{ option.label }}
                                                    </option>
                                                </select>

                                                <!-- Boolean -->
                                                <label v-else-if="spec.type === 'boolean'" class="flex cursor-pointer items-center gap-2">
                                                    <input
                                                        v-model="form.specValues[spec.id].value_boolean"
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                                    />
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">Yes</span>
                                                </label>
                                            </template>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </template>
                    <!-- ── REGULAR FIELDS SECTION ── -->
                    <div
                        v-else-if="group.filteredFields && group.filteredFields.length > 0"
                        v-show="!useFormAccordion || openSections[group.key]"
                    >
                        <div class="p-4 border-gray-200 sm:p-5 dark:border-gray-700">
                            <!-- Address Group -->
                            <div v-if="group.is_address && hasAddressTags(group)" class="mb-4 grid sm:grid-cols-12 gap-4">
                                <div class="sm:col-span-6">
                                <AddressAutocomplete
                                    :street="getAddressFieldValue(group, 'street')"
                                    :unit="getAddressFieldValue(group, 'unit')"
                                    :city="getAddressFieldValue(group, 'city')"
                                    :state="getAddressFieldValue(group, 'state')"
                                    :state-code="getAddressFieldValue(group, 'state_code')"
                                    :postal-code="getAddressFieldValue(group, 'postal_code')"
                                    :country="getAddressFieldValue(group, 'country')"
                                    :country-code="getAddressFieldValue(group, 'country_code')"
                                    :latitude="getAddressFieldValue(group, 'latitude')"
                                    :longitude="getAddressFieldValue(group, 'longitude')"
                                        :disabled="!isEditMode"
                                    @update="(data) => updateAddressFields(group, data)"
                                />
                                </div>
                            </div>

                            <!-- Regular Fields -->
                            <div v-else class="grid gap-4 sm:grid-cols-12">
                                <template v-for="field in group.filteredFields" :key="field?.key">
                                    <div v-if="field && isFieldVisible(field)" :class="getFieldColSpan(field)">
                                    <label :for="getFieldId(field.key)" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                        {{ getFieldLabel(field.key) }}
                                            <span v-if="getFieldType(field.key) === 'datetime' || getFieldType(field.key) === 'date'"
                                                  class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">
                                            ({{ accountTimezoneLabel }})
                                        </span>
                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                    </label>

                                    <!-- View Mode -->
                                    <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                            <span v-if="getFieldType(field.key) === 'textarea'" class="whitespace-pre-wrap">{{ getFieldValue(field.key) || '—' }}</span>
                                            <div v-else-if="getFieldType(field.key) === 'boolean' || getFieldType(field.key) === 'checkbox'" class="flex items-center">
                                                <label class="select-none w-full text-sm font-medium">{{ getFieldValue(field.key) ? 'Yes' : 'No' }}</label>
                                        </div>
                                            <span v-else-if="getFieldType(field.key) === 'record'">{{ getRecordDisplayName(field.key, getFieldValue(field.key)) }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'select' && getFieldDefinition(field.key).enum">{{ getEnumLabel(field.key, getFieldValue(field.key)) || '—' }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'multi_enum'">{{ getMultiEnumDisplay(field.key) }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'tel'">{{ getFormattedPhoneValue(field.key) || '—' }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'datetime'">{{ formatDateTime(getFieldValue(field.key)) || '—' }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'date'">{{ formatDate(getFieldValue(field.key)) || '—' }}</span>
                                            <span v-else-if="getFieldType(field.key) === 'time'">{{ getFieldValue(field.key) || '—' }}</span>
                                        <span v-else-if="getFieldType(field.key) === 'rating'">
                                            <div class="flex items-center space-x-1">
                                                <template v-for="star in 5" :key="star">
                                                        <svg class="w-4 h-4" :class="star <= getFieldValue(field.key) ? 'text-yellow-400' : 'text-gray-300'" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                    </svg>
                                                </template>
                                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ getFieldValue(field.key) || 0 }}/5</span>
                                            </div>
                                        </span>
                                        <span v-else-if="getFieldType(field.key) === 'file'">
                                                <span v-if="getFieldValue(field.key)" class="text-sm text-blue-600 dark:text-blue-400 underline">{{ getFileName(getFieldValue(field.key)) }}</span>
                                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">No file uploaded</span>
                                        </span>
                                        <div v-else-if="getFieldType(field.key) === 'image'">
                                                <img v-if="getImageSource(field.key)" :src="getImageSource(field.key)" class="h-32 w-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Image" @error="$event.target.style.display='none'" />
                                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">No image</span>
                                        </div>
                                        <div v-else-if="getFieldType(field.key) === 'wysiwyg'" class="prose prose-sm dark:prose-invert max-w-none p-4 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 rounded-lg max-h-[400px] overflow-y-auto">
                                            <div v-html="getFieldValue(field.key) || '—'"></div>
                                        </div>
                                        <span v-else-if="getFieldType(field.key) === 'morph'">
                                            <span v-if="record && record[getFieldDefinition(field.key).id_field]" class="inline-flex items-center gap-2">
                                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">{{ getFieldValue(field.key)?.split('\\').pop() || 'Unknown' }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="text-sm">{{ record.relatable?.display_name || '—' }}</span>
                                            </span>
                                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">Not assigned</span>
                                        </span>
                                        <span v-else-if="getFieldType(field.key) === 'currency'">
                                            {{ getFieldValue(field.key) !== null && getFieldValue(field.key) !== undefined
                                                ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(getFieldValue(field.key))
                                                    : '—' }}
                                        </span>
                                        <span v-else-if="getFieldType(field.key) === 'measurement'">{{ formatLengthMmImperial(getFieldValue(field.key)) }}</span>
                                            <span v-else>{{ getFieldValue(field.key) || '—' }}</span>
                                    </div>

                                    <!-- Edit Mode -->
                                    <div v-else>
                                            <div v-if="getFieldType(field.key) === 'multi_enum'" class="flex flex-wrap gap-3 rounded-lg border border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800">
                                                <label
                                                v-for="option in getEnumOptions(field.key)"
                                                :key="option.id"
                                                    class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                                >
                                        <input
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                                        :checked="isMultiEnumSelected(field.key, option.id)"
                                                        @change="toggleMultiEnumValue(field.key, option.id)"
                                                    />
                                                    <span>{{ option.name }}</span>
                                                </label>
                                            </div>
                                            <div v-else-if="getFieldType(field.key) === 'tel'" class="relative">
                                                <input :id="getFieldId(field.key)" type="tel" :value="getFormattedPhoneValue(field.key)" @input="handlePhoneInput(field.key, $event)" @blur="handlePhoneInput(field.key, $event)" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" class="input-style" placeholder="(123) 456-7890" />
                                        </div>
                                            <NumberInput v-else-if="getFieldType(field.key) === 'number'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" :min="getFieldDefinition(field.key).min" :max="getFieldDefinition(field.key).max" :step="getFieldDefinition(field.key).step || 1" :allow-decimals="getFieldDefinition(field.key).allow_decimals !== false" :is-year="getFieldDefinition(field.key).isYear === true" />
                                            <CurrencyInput v-else-if="getFieldType(field.key) === 'currency'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" />
                                            <MeasurementImperialInput
                                                v-else-if="getFieldType(field.key) === 'measurement'"
                                                :id="getFieldId(field.key)"
                                                v-model="form[field.key]"
                                                :required="isFieldRequired(field)"
                                                :disabled="isFieldDisabled(field.key)"
                                            />
                                            <input v-else-if="['text', 'email'].includes(getFieldType(field.key))" :id="getFieldId(field.key)" v-model="form[field.key]" :type="getFieldType(field.key)" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" class="input-style" />
                                            <textarea v-else-if="getFieldType(field.key) === 'textarea'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" rows="4" class="block p-2.5 w-full input-style" />
                                            <RecordSelect v-else-if="getFieldType(field.key) === 'record'" :id="getFieldId(field.key)" :field="getFieldDefinition(field.key)" v-model="form[field.key]" :disabled="isFieldDisabled(field.key) || isFieldDisabledByFilter(field.key)" :enum-options="getEnumOptions(field.key)" :record="record || (Object.keys(props.initialData).length > 0 ? props.initialData : null)" :field-key="field.key" :filter-by="getFieldDefinition(field.key).record_filter_field || getFieldDefinition(field.key).filterby || null" :filter-value="getFieldFilterValue(field.key)" @record-selected="(selectedRecord) => applySourcedDefaults(field.key, selectedRecord)" />
                                            <select v-else-if="getFieldType(field.key) === 'select'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" :class="['input-style', !form[field.key] ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900']">
                                                <option v-if="!isFieldRequired(field)" value="" disabled>Select {{ getFieldLabel(field.key) }}</option>
                                                <option v-for="option in getEnumOptions(field.key)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                            </select>
                                            <DateTimeInput v-else-if="getFieldType(field.key) === 'datetime'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" />
                                            <DateInput v-else-if="getFieldType(field.key) === 'date'" :id="getFieldId(field.key)" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" />
                                            <input v-else-if="getFieldType(field.key) === 'time'" :id="getFieldId(field.key)" type="time" v-model="form[field.key]" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" class="input-style" />
                                            <Rating v-else-if="getFieldType(field.key) === 'rating'" v-model="form[field.key]" :disabled="isFieldDisabled(field.key)" :show-value="false" />
                                            <div v-else-if="getFieldType(field.key) === 'file'" class="space-y-2">
                                                <input :id="getFieldId(field.key)" type="file" @change="handleFileInput(field.key, $event)" :required="isFieldRequired(field)" :disabled="isFieldDisabled(field.key)" :accept="getFieldDefinition(field.key).accept || '*/*'" class="input-style" />
                                                <div v-if="form[field.key] && typeof form[field.key] === 'string'" class="text-sm text-gray-600 dark:text-gray-400">Current file: <span class="font-medium">{{ getFileName(form[field.key]) }}</span></div>
                                            </div>
                                        <div v-else-if="getFieldType(field.key) === 'image'" class="space-y-4">
                                            <div v-if="getImageSource(field.key)" class="relative w-32 h-32 group">
                                                    <img :src="getImageSource(field.key)" class="w-full h-full object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Preview" />
                                                    <button v-if="!isFieldDisabled(field.key)" type="button" @click="form[field.key] = null; delete imagePreviews[field.key]" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none shadow-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>
                                            <div v-if="!getImageSource(field.key) || !isFieldDisabled(field.key)">
                                                    <input :id="getFieldId(field.key)" type="file" @change="handleImageInput(field.key, $event)" :required="isFieldRequired(field) && !form[field.key]" :disabled="isFieldDisabled(field.key)" accept="image/*" class="input-style" />
                                            </div>
                                        </div>
                                            <MorphSelect v-else-if="getFieldType(field.key) === 'morph'" :id="getFieldId(field.key)" :field="getFieldDefinition(field.key)" v-model="form[getFieldDefinition(field.key).id_field]" v-model:selected-type="form[field.key]" :disabled="isFieldDisabled(field.key)" :initial-display-name="getMorphRelatedDisplayName(field.key)" />
                                            <TipTapEditor v-else-if="getFieldType(field.key) === 'wysiwyg'" :id="getFieldId(field.key)" v-model="form[field.key]" :error="form.errors[field.key]" :show-anchor="getFieldDefinition(field.key).show_anchor" />
                                            <label :for="getFieldId(field.key)" v-else-if="getFieldType(field.key) === 'checkbox' || getFieldType(field.key) === 'boolean'" class="flex items-center ps-4 bg-gray-50 border border-gray-300 text-gray-900 rounded-lg py-3.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <input type="hidden" :name="field.key" :value="0" />
                                                <input :id="getFieldId(field.key)" v-model="form[field.key]" type="checkbox" :name="field.key" :true-value="1" :false-value="0" :disabled="isFieldDisabled(field.key)" class="w-4 h-4 border border-default-medium rounded-sm bg-neutral-secondary-medium focus:ring-2 focus:ring-brand-soft" />
                                        </label>
                                            <p v-if="getFieldDefinition(field.key).help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ getFieldDefinition(field.key).help }}</p>
                                            <p v-if="form.errors[field.key]" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ form.errors[field.key] }}</p>
                                    </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div v-if="isEditMode && !formId" class="flex items-center py-4 px-4 space-x-4 sm:px-5">
            <button
                type="submit"
                :disabled="form.processing || isProcessing"
                class="w-full text-white inline-flex items-center justify-center bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg v-if="form.processing || isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="-ml-1 w-5 h-5 sm:mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                {{ (form.processing || isProcessing) ? 'Processing...' : (isCreateMode ? 'Create' : 'Update') }} {{ recordTitle }}
            </button>
            <button
                type="button"
                @click="handleCancel"
                :disabled="form.processing || isProcessing"
                class="w-full inline-flex justify-center text-gray-500 items-center bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-primary-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Cancel
            </button>
        </div>
    </form>
</template>