import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useTimezone } from '@/composables/useTimezone';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';

/**
 * Form state + submit pipeline mirrored from Form.vue, scoped for AssetForm (no Form component).
 */
export function useAssetSchemaForm(props, emit) {
    const { convertUTCToTimezone, convertTimezoneToUTC, accountTimezone, accountTimezoneLabel } = useTimezone();

    const isEditMode = computed(() => props.mode === 'edit' || props.mode === 'create');
    const isCreateMode = computed(() => props.mode === 'create');
    const updateRecordId = computed(() => props.recordIdentifier ?? props.record?.id);

    const resolvedFieldsSchema = computed(() => {
        const fs = props.fieldsSchema;
        if (fs && typeof fs === 'object' && fs.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
            return fs.fields;
        }
        return fs || {};
    });

    const normalizedSchema = computed(() => {
        if (props.schema && props.schema.form) return props.schema.form;
        return props.schema;
    });

    const columnCount = computed(() => props.schema?.settings?.columns ?? 2);

    const formUniqueId = computed(() =>
        `asset-form-${props.recordType}-${props.record?.id || 'new'}-${Math.random().toString(36).slice(2, 11)}`,
    );

    const getFieldId = (fieldKey) => `${formUniqueId.value}-field-${fieldKey}`;

    const getFieldDefinition = (fieldKey) => resolvedFieldsSchema.value[fieldKey] || {};

    const getRecordSpecValues = () => {
        const r = props.record;
        if (!r) return [];
        if (Array.isArray(r.spec_values)) return r.spec_values;
        if (Array.isArray(r.specValues)) return r.specValues;
        return [];
    };

    const hasSpecsSection = computed(() => {
        const s = normalizedSchema.value;
        if (!s || typeof s !== 'object') return false;
        return Object.values(s).some((g) => g && typeof g === 'object' && g.type === 'specs');
    });

    const specsOverrideFromFetch = ref(null);

    const resolvedAvailableSpecs = computed(() => {
        if (specsOverrideFromFetch.value !== null) {
            return specsOverrideFromFetch.value;
        }
        return props.availableSpecs || [];
    });

    const buildInitialSpecValues = () => {
        const specValues = {};
        const existing = {};
        getRecordSpecValues().forEach((sv) => {
            existing[sv.asset_spec_definition_id] = sv;
        });
        resolvedAvailableSpecs.value.forEach((spec) => {
            const sv = existing[spec.id];
            specValues[spec.id] = sv
                ? {
                      value_number: sv.value_number ?? null,
                      value_text: sv.value_text ?? null,
                      value_boolean: sv.value_boolean ?? false,
                      unit: sv.unit ?? spec.unit ?? null,
                  }
                : {
                      value_number: null,
                      value_text: null,
                      value_boolean: false,
                      unit: spec.unit ?? null,
                  };
        });
        return specValues;
    };

    const serializeJsonField = (value) => {
        if (value == null || value === '') return '';
        if (typeof value === 'string') return value;
        try {
            return JSON.stringify(value, null, 2);
        } catch {
            return '';
        }
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

            Object.keys(props.record).forEach((key) => {
                if (!allowedRecordKeys.has(key)) return;
                const fieldDef = getFieldDefinition(key);
                const value = props.record[key];

                if (fieldDef.type === 'json') {
                    formData[key] = serializeJsonField(value);
                    return;
                }

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
                        formData[key] =
                            fieldDef.type === 'datetime'
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
            Object.values(normalizedSchema.value)
                .filter((g) => g && typeof g === 'object')
                .forEach((group) => {
                    if (group.fields && Array.isArray(group.fields)) {
                        group.fields
                            .filter((f) => f && typeof f === 'object' && f.key)
                            .forEach((field) => {
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
                                                const defaultOption = enumOptions.find((opt) => opt.value === fieldDef.default);
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
                                    } else if (fieldType === 'json') {
                                        formData[field.key] = '';
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

        if (resolvedFieldsSchema.value) {
            Object.keys(resolvedFieldsSchema.value).forEach((k) => {
                if (resolvedFieldsSchema.value[k]?.spec) {
                    fillDefaultForKey(k);
                }
            });
        }

        formData.specValues = buildInitialSpecValues();
        return formData;
    };

    const form = useForm(initializeFormData());
    const isProcessing = ref(false);
    const imagePreviews = ref({});

    watch(
        [() => props.recordType, () => form.type, () => props.availableSpecs?.length ?? 0],
        async ([recordType, type, availableLen], oldVals) => {
            if (!hasSpecsSection.value || props.mode === 'view') {
                return;
            }

            if (oldVals === undefined && availableLen > 0) {
                return;
            }

            const assetType = type === null || type === '' ? null : Number(type);
            if (assetType === null || Number.isNaN(assetType)) {
                specsOverrideFromFetch.value = [];
                return;
            }

            if (recordType !== 'assets') {
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

    watch(
        [resolvedAvailableSpecs, () => props.record],
        () => {
            form.specValues = buildInitialSpecValues();
        },
        { deep: true },
    );

    watch(
        () => props.record,
        (newRecord) => {
            if (newRecord) {
                form.clearErrors();
                Object.keys(newRecord).forEach((key) => {
                    const fieldDef = getFieldDefinition(key);
                    if (fieldDef.type === 'json') {
                        form[key] = serializeJsonField(newRecord[key]);
                        return;
                    }
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
                            form[key] =
                                fieldDef.type === 'datetime'
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
        },
        { deep: true, immediate: true },
    );

    watch(
        () => form.data(),
        (newData, oldData) => {
            if (normalizedSchema.value) {
                Object.values(normalizedSchema.value)
                    .filter((g) => g && typeof g === 'object')
                    .forEach((group) => {
                        if (group.fields && Array.isArray(group.fields)) {
                            group.fields
                                .filter((f) => f && typeof f === 'object' && f.key)
                                .forEach((field) => {
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
        },
        { deep: true },
    );

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

    const buildSpecsPayload = () => {
        return resolvedAvailableSpecs.value.map((spec) => {
            const val = form.specValues?.[spec.id] || {};
            return {
                spec_id: spec.id,
                value_number:
                    spec.type === 'number' ? (val.value_number !== '' && val.value_number !== null ? val.value_number : null) : null,
                value_text: spec.type === 'text' || spec.type === 'select' ? val.value_text || null : null,
                value_boolean: spec.type === 'boolean' ? (val.value_boolean ? 1 : 0) : null,
                unit: val.unit || null,
            };
        });
    };

    const getSpecDisplayValue = (spec) => {
        const sv = getRecordSpecValues().find((s) => s.asset_spec_definition_id === spec.id);
        if (!sv) return null;

        if (spec.type === 'number') return sv.value_number == null ? null : parseFloat((+sv.value_number).toFixed(2));
        if (spec.type === 'boolean') return sv.value_boolean ?? null;
        if (spec.type === 'select') return sv.value_text ?? null;
        if (spec.type === 'text') return sv.value_text ?? null;

        return null;
    };

    const getSpecDisplayUnit = (spec) => {
        const sv = getRecordSpecValues().find((s) => s.asset_spec_definition_id === spec.id);
        return sv?.unit ? sv.unit : spec.unit || null;
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

    /** Field keys with `spec: true` in fields.json — rendered in the Specifications group before dynamic defs */
    const staticSpecFormFieldEntries = computed(() => {
        const fs = resolvedFieldsSchema.value;
        if (!fs) return [];
        return Object.keys(fs).filter((k) => fs[k] && fs[k].spec);
    });

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
        const ids = form[fieldKey];
        if (!Array.isArray(ids) || ids.length === 0) return '—';
        const opts = getEnumOptions(fieldKey);
        return ids
            .map((id) => opts.find((o) => Number(o.id) === Number(id) || Number(o.value) === Number(id))?.name ?? id)
            .join(', ');
    };

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
        if (!selectedRecord || !resolvedFieldsSchema.value) return;
        for (const [fieldKey, fieldDef] of Object.entries(resolvedFieldsSchema.value)) {
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
        const option = options.find(
            (opt) => String(opt.id) === valueStr || String(opt.value) === valueStr || opt.id === value || opt.value === value,
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
        return fieldDef?.filterby ? form[fieldDef.filterby] || null : null;
    };

    const getConditionalFieldValue = (fieldPath) => {
        if (fieldPath.includes('.')) {
            let [relationshipOrTypeDomain, fieldName] = fieldPath.split('.', 2);
            let relationshipName = relationshipOrTypeDomain;
            if (resolvedFieldsSchema.value) {
                const fieldWithTypeDomain = Object.values(resolvedFieldsSchema.value).find(
                    (field) => field.typeDomain === relationshipOrTypeDomain,
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
                case 'greater_than':
                case 'gt':
                    return currentValue > value;
                case 'less_than':
                case 'lt':
                    return currentValue < value;
                case 'contains':
                    return String(currentValue).includes(String(value));
                case 'is_empty':
                    return !currentValue || currentValue === '';
                case 'is_not_empty':
                    return currentValue && currentValue !== '';
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
            case 'equals':
            case 'eq':
                return typeof value === 'boolean' ? boolCurrent === value : currentValue == value;
            case 'not_equals':
            case 'neq':
                return typeof value === 'boolean' ? boolCurrent !== value : currentValue != value;
            case 'greater_than':
            case 'gt':
                return currentValue > value;
            case 'less_than':
            case 'lt':
                return currentValue < value;
            case 'contains':
                return String(currentValue).includes(String(value));
            case 'is_empty':
                return !currentValue || currentValue === '';
            case 'is_not_empty':
                return currentValue && currentValue !== '';
            default:
                return typeof value === 'boolean' ? boolCurrent === value : currentValue == value;
        }
    };

    const visibleFormGroups = computed(() => formGroups.value.filter(isGroupVisible));

    const getFieldColSpan = (field) => {
        if (field.col_span) return field.col_span;
        if (field.span) return `sm:col-span-${field.span}`;
        const fieldType = getFieldType(field.key);
        if (
            fieldType === 'textarea' ||
            field.key === 'address_line_1' ||
            field.key === 'address_line_2' ||
            fieldType === 'editor' ||
            fieldType === 'wysiwyg' ||
            fieldType === 'json'
        ) {
            return 'sm:col-span-12';
        }
        return `sm:col-span-${Math.floor(12 / columnCount.value)}`;
    };

    const formatPhoneNumber = (value) => {
        if (!value) return '';
        const numbers = value.replace(/\D/g, '');
        if (numbers.length <= 3) return numbers;
        if (numbers.length <= 6) return `(${numbers.slice(0, 3)}) ${numbers.slice(3)}`;
        return `(${numbers.slice(0, 3)}) ${numbers.slice(3, 6)}-${numbers.slice(6, 10)}`;
    };
    const unformatPhoneNumber = (value) => (value ? value.replace(/\D/g, '') : '');
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

    const hasAddressTags = (group) => group.filteredFields?.some((field) => field.tag);
    const getAddressFieldValue = (group, tag) => {
        const field = group.filteredFields?.find((f) => f.tag === tag);
        return field ? form[field.key] || '' : '';
    };
    const updateAddressFields = (group, data) => {
        Object.keys(data).forEach((emittedKey) => {
            let tag = emittedKey;
            if (emittedKey === 'stateCode') tag = 'state_code';
            if (emittedKey === 'postalCode') tag = 'postal_code';
            if (emittedKey === 'countryCode') tag = 'country_code';
            const field = group.filteredFields?.find((f) => f.tag === tag || f.tag === emittedKey);
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
        } catch {
            return value;
        }
    };
    const formatDateTime = (value) => {
        if (!value) return '';
        try {
            const date = new Date(value);
            if (isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }).format(date);
        } catch {
            return value;
        }
    };

    const parseJsonFieldForSubmit = (key) => {
        const raw = form[key];
        if (raw == null || raw === '') return null;
        if (typeof raw === 'object' && !Array.isArray(raw)) return raw;
        try {
            const parsed = JSON.parse(typeof raw === 'string' ? raw : String(raw));
            return parsed;
        } catch {
            return raw;
        }
    };

    const prepareFormData = () => {
        const data = { ...form.data() };
        if (normalizedSchema.value) {
            Object.values(normalizedSchema.value)
                .filter((g) => g && typeof g === 'object')
                .forEach((group) => {
                    if (group.fields && Array.isArray(group.fields)) {
                        group.fields
                            .filter((f) => f && typeof f === 'object' && f.key)
                            .forEach((field) => {
                                const fieldDef = getFieldDefinition(field.key);
                                if (fieldDef.type === 'checkbox' || fieldDef.type === 'boolean') {
                                    data[field.key] = data[field.key] === true || data[field.key] === 1 ? 1 : 0;
                                } else if ((fieldDef.type === 'datetime' || fieldDef.type === 'date') && data[field.key]) {
                                    const timezoneDate = new Date(data[field.key]);
                                    const utcDate = convertTimezoneToUTC(timezoneDate.toISOString(), accountTimezone.value);
                                    data[field.key] =
                                        fieldDef.type === 'datetime'
                                            ? utcDate.toISOString().slice(0, 16)
                                            : utcDate.toISOString().split('T')[0];
                                } else if (fieldDef.type === 'json') {
                                    data[field.key] = parseJsonFieldForSubmit(field.key);
                                }
                            });
                    }
                });
        }
        delete data.specValues;
        data.specs = buildSpecsPayload();
        return data;
    };

    const handleSubmit = () => {
        const rawData = prepareFormData();
        const hasFiles = Object.values(rawData).some((val) => val instanceof File || val instanceof Blob);

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
                axios
                    .post(route(`${props.recordType}.store`, props.extraRouteParams), submissionData, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    })
                    .then((response) => {
                        const recordId = response.data?.recordId || response.data?.data?.recordId;
                        if (recordId) {
                            form.reset();
                            emit('created', recordId);
                        } else emit('submit');
                    })
                    .catch((error) => {
                        if (error.response?.status === 422) form.errors = error.response.data.errors || {};
                        else form.errors = { general: [error.response?.data?.message || 'An error occurred'] };
                    })
                    .finally(() => {
                        isProcessing.value = false;
                    });
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
        } else if (isEditMode.value) {
            if (props.preventRedirect) {
                isProcessing.value = true;
                let submissionData = rawData;
                let method = 'put';
                const url = route(
                    `${props.recordType}.update`,
                    buildResourceRouteParams(props.recordType, updateRecordId.value, props.extraRouteParams),
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
                    .finally(() => {
                        isProcessing.value = false;
                    });
            } else {
                form.transform(() => rawData).put(
                    route(
                        `${props.recordType}.update`,
                        buildResourceRouteParams(props.recordType, updateRecordId.value, props.extraRouteParams),
                    ),
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            emit('submit');
                            if (props.redirectAfterUpdate) {
                                router.visit(props.redirectAfterUpdate);
                            } else {
                                router.reload({ only: ['record', 'imageUrls'] });
                            }
                        },
                    },
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

    return {
        form,
        isEditMode,
        isCreateMode,
        normalizedSchema,
        visibleFormGroups,
        accountTimezoneLabel,
        getFieldId,
        getFieldDefinition,
        getFieldType,
        getFieldLabel,
        getFieldValue,
        getFieldColSpan,
        isFieldRequired,
        isFieldDisabled,
        isFieldDisabledByFilter,
        getFieldFilterValue,
        isFieldVisible,
        staticSpecFormFieldEntries,
        getEnumOptions,
        getEnumLabel,
        getRecordDisplayName,
        getMorphRelatedDisplayName,
        getMultiEnumDisplay,
        isMultiEnumSelected,
        toggleMultiEnumValue,
        handlePhoneInput,
        getFormattedPhoneValue,
        handleImageInput,
        getImageSource,
        handleFileInput,
        getFileName,
        formatDate,
        formatDateTime,
        groupedSpecSections,
        resolvedAvailableSpecs,
        getSpecDisplayValue,
        getSpecDisplayUnit,
        hasAddressTags,
        getAddressFieldValue,
        updateAddressFields,
        applySourcedDefaults,
        handleSubmit,
        handleCancel,
        submitForm,
        cancelForm,
        isProcessing: isFormProcessing,
        imagePreviews,
    };
}

