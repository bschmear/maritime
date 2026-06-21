<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import DateTimeInput from '@/Components/Tenant/FormComponents/DateTime.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import NumberInput from '@/Components/Tenant/FormComponents/Number.vue';
import EnumButtonGroup from '@/Components/Tenant/FormComponents/EnumButtonGroup.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { buildRecordShowUrl, buildResourceRouteParams } from '@/Utils/resourceRoutes.js';
import { buildFormErrorMessages, useFormValidationToast } from '@/composables/useFormValidationToast';
import { useSubsidiaryLocationAutofill } from '@/composables/useSubsidiaryLocationAutofill';
import { defaultRequiredSelectValue } from '@/Utils/selectDefaults';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    prefill: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'assetunits' },
    recordTitle: { type: String, default: 'Asset Unit' },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    mode: {
        type: String,
        default: 'view',
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
    redirectAfterUpdate: { type: String, default: null },
    /** When true, record pickers stack above a parent modal overlay. */
    nestedInModal: { type: Boolean, default: false },
});

const recordSelectOverlayZIndex = computed(() => (props.nestedInModal ? 100 : 50));

const emit = defineEmits(['saved', 'cancelled', 'created', 'cancel']);

const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const truthyFlag = (value) => value === true || value === 1 || value === '1';

const isView = computed(() => props.mode === 'view');
const isCreate = computed(() => props.mode === 'create');
const isEdit = computed(() => props.mode === 'edit');
const showActionSidebar = computed(() => !isView.value);

const assetHasVariants = ref(false);

const normalizedForm = computed(() => {
    const s = props.schema;
    if (s?.form && typeof s.form === 'object') {
        return s.form;
    }
    return s && typeof s === 'object' ? s : {};
});

const formGroups = computed(() => {
    const out = [];
    for (const [key, group] of Object.entries(normalizedForm.value)) {
        if (!group || typeof group !== 'object') {
            continue;
        }
        const fields = (group.fields ?? [])
            .map((f) => (f && typeof f === 'object' && f.key ? { ...f, key: f.key } : null))
            .filter(
                (f) =>
                    f
                    && !f.hidden
                    && !fieldDef(f.key).hidden
                    && fieldDef(f.key).type !== 'hidden',
            );
        if (fields.length === 0) {
            continue;
        }
        out.push({
            key,
            label: group.label || key,
            fields,
        });
    }
    return out;
});

const allFieldKeys = computed(() => {
    const keys = new Set();
    formGroups.value.forEach((g) => g.fields.forEach((f) => keys.add(f.key)));
    return [...keys];
});

const fieldDef = (key) => props.fieldsSchema[key] || {};

const enumOptionsFor = (key) => {
    const en = fieldDef(key).enum;
    if (en && props.enumOptions[en]) {
        return props.enumOptions[en];
    }
    return props.enumOptions[key] ?? [];
};

const isEnumSelectField = (key) => fieldDef(key).type === 'select' && !!fieldDef(key).enum;

const pseudoRecord = computed(() => {
    if (props.record) {
        return props.record;
    }
    if (Object.keys(props.prefill).length) {
        return props.prefill;
    }
    return null;
});

/** Only hydrate from props when the nested asset object is present; never clear here. */
const syncAssetHasVariantsFromProps = () => {
    const asset = props.record?.asset ?? props.prefill?.asset ?? null;
    const assetId = props.record?.asset_id ?? props.prefill?.asset_id ?? null;
    if (asset && assetId != null && Number(asset.id) === Number(assetId)) {
        assetHasVariants.value = truthyFlag(asset.has_variants);
    }
};

watch(() => [props.record, props.prefill], syncAssetHasVariantsFromProps, { immediate: true, deep: true });

const loadAssetHasVariants = async (assetId) => {
    if (!assetId) {
        assetHasVariants.value = false;
        return;
    }
    const prefillAsset = props.prefill?.asset;
    if (prefillAsset && Number(prefillAsset.id) === Number(assetId)) {
        assetHasVariants.value = truthyFlag(prefillAsset.has_variants);
        return;
    }
    const recordAsset = props.record?.asset;
    if (recordAsset && Number(recordAsset.id) === Number(assetId)) {
        assetHasVariants.value = truthyFlag(recordAsset.has_variants);
        return;
    }
    try {
        const { data } = await axios.get(route('assets.show', assetId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        assetHasVariants.value = truthyFlag(data.record?.has_variants);
    } catch {
        assetHasVariants.value = false;
    }
};

const isFieldVisible = (field) => {
    if (field.key === 'asset_variant_id') {
        return assetHasVariants.value;
    }
    return true;
};

const recordFieldGridClass = (fieldKey) => {
    if (fieldKey !== 'asset_id' && fieldKey !== 'asset_variant_id') {
        return '';
    }
    // Side-by-side on md+ when both asset and variant are shown; full width when variant is hidden.
    return assetHasVariants.value ? '' : 'md:col-span-2';
};

const isFieldRequired = (field) => {
    if (field.key === 'asset_variant_id') {
        return assetHasVariants.value;
    }
    if (field.required) {
        return true;
    }
    return fieldDef(field.key).required === true;
};

const isFieldDisabled = (field) => {
    if (isView.value) {
        return true;
    }
    if (field.readOnly || fieldDef(field.key).readOnly) {
        return true;
    }
    if (field.key === 'asset_id' && (!isCreate.value || props.prefill?.asset_id)) {
        return true;
    }
    if (field.key === 'asset_variant_id' && !form.asset_id) {
        return true;
    }
    const filterKey = fieldDef(field.key).record_filter_field || fieldDef(field.key).filterby;
    if (filterKey && !form[filterKey]) {
        return true;
    }
    return false;
};

const formFieldMeta = (key) => {
    for (const group of formGroups.value) {
        const hit = group.fields.find((f) => f.key === key);
        if (hit) {
            return hit;
        }
    }

    return null;
};

const defaultValueForField = (key) => {
    const def = fieldDef(key);
    const t = def.type || 'text';
    if (t === 'boolean') {
        return !!def.default;
    }
    if (t === 'select') {
        if (!isCreate.value) {
            return '';
        }
        const meta = formFieldMeta(key);
        const required = meta?.required === true || def.required === true;

        return defaultRequiredSelectValue(
            def,
            enumOptionsFor(key),
            (opt) => selectOptionValue(key, opt),
            { required },
        );
    }
    return '';
};

const extractDate = (value) => {
    if (!value) {
        return '';
    }
    const s = String(value);
    return s.length >= 10 ? s.slice(0, 10) : s;
};

const extractDateTime = (value) => {
    if (!value) {
        return '';
    }
    const s = String(value);
    if (s.length >= 16) {
        return s.slice(0, 16);
    }
    return s;
};

/** Condition/status are stored as integer IDs (see UnitCondition::id / UnitStatus::id). */
const selectUsesIntegerId = (key) => key === 'condition' || key === 'status';

const coerceEnumSelectToId = (key, value) => {
    if (value == null || value === '') {
        return null;
    }
    const opts = enumOptionsFor(key);
    if (!opts.length) {
        return value;
    }
    const asNum = Number(value);
    if (!Number.isNaN(asNum) && opts.some((o) => Number(o.id) === asNum)) {
        return asNum;
    }
    const hit = opts.find(
        (o) => o.value === value || String(o.value) === String(value) || o.name === value,
    );
    if (hit) {
        return hit.id;
    }
    return value;
};

const selectOptionValue = (key, opt) => {
    if (selectUsesIntegerId(key)) {
        return opt.id;
    }
    return opt.value !== undefined && opt.value !== null ? opt.value : opt.id;
};

function initialValuesFromProps() {
    const values = {};
    for (const key of allFieldKeys.value) {
        values[key] = defaultValueForField(key);
    }

    const mergeSource = { ...props.prefill, ...(props.record ?? {}) };
    for (const key of allFieldKeys.value) {
        if (!(key in mergeSource)) {
            continue;
        }
        const def = fieldDef(key);
        let v = mergeSource[key];
        const rel = def.relationship ? mergeSource[def.relationship] : null;
        if (def.type === 'record' && rel && typeof rel === 'object' && rel.id != null) {
            v = rel.id;
        }
        if (def.type === 'boolean') {
            values[key] = truthyFlag(v);
            continue;
        }
        if (def.type === 'date') {
            values[key] = extractDate(v);
            continue;
        }
        if (def.type === 'datetime') {
            values[key] = extractDateTime(v);
            continue;
        }
        if (def.type === 'currency' || def.type === 'number') {
            values[key] = v != null && v !== '' ? String(v) : '';
            continue;
        }
        if (def.type === 'select') {
            values[key] = selectUsesIntegerId(key) ? coerceEnumSelectToId(key, v) ?? '' : v ?? '';
            continue;
        }
        if (v !== null && v !== undefined) {
            values[key] = v;
        }
    }
    return values;
}

const form = useForm(initialValuesFromProps());

watch(
    () => form.asset_id,
    (id, oldId) => {
        if (id) {
            loadAssetHasVariants(id);
            return;
        }
        if (oldId !== undefined) {
            assetHasVariants.value = false;
            form.asset_variant_id = null;
        }
    },
    { immediate: true },
);

useSubsidiaryLocationAutofill(form, () => props.fieldsSchema, {
    enabled: () => !isView.value,
});

const handleAssetSelected = (selected) => {
    if (!selected) {
        return;
    }
    assetHasVariants.value = truthyFlag(selected.has_variants);
    if (!assetHasVariants.value) {
        form.asset_variant_id = null;
    }
};

const headerTitle = computed(() => {
    if (isCreate.value) {
        return 'NEW ASSET UNIT';
    }
    if (isEdit.value) {
        return 'EDIT ASSET UNIT';
    }
    return 'ASSET UNIT';
});

const headerSubtitle = computed(() => {
    if (isCreate.value) {
        return 'Register a physical unit for an asset';
    }
    if (isEdit.value) {
        return 'Update unit details, ownership, and pricing';
    }
    return 'Unit record and consignment context';
});

const unitLabel = computed(() => {
    const name = String(props.record?.display_name ?? '').trim();
    if (name) {
        return name;
    }
    if (isCreate.value) {
        return 'New unit';
    }
    return props.record?.id ? `#${props.record.id}` : '—';
});

const getEnumLabel = (key, value) => {
    if (value == null || value === '') {
        return '—';
    }
    const opts = enumOptionsFor(key);
    const hit = opts.find(
        (o) => o.id === value || o.value === value || String(o.id) === String(value) || String(o.value) === String(value),
    );
    return hit?.name ?? String(value);
};

const formatCurrency = (value) => {
    if (value == null || value === '') {
        return '—';
    }
    const n = parseFloat(value);
    if (Number.isNaN(n)) {
        return '—';
    }
    return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formErrorMessages = computed(() => buildFormErrorMessages(form.errors, props.fieldsSchema));

const normalizeFormBeforeSubmit = () => {
    for (const key of ['vendor_id', 'customer_id', 'location_id', 'subsidiary_id', 'asset_variant_id']) {
        if (form[key] === '' || form[key] === undefined) {
            form[key] = null;
        }
    }
    if (!assetHasVariants.value) {
        form.asset_variant_id = null;
    }
    for (const key of ['inactive', 'is_customer_owned', 'is_consignment']) {
        form[key] = !!(form[key] === true || form[key] === 1 || form[key] === '1');
    }
    for (const key of ['cost', 'asking_price', 'sold_price', 'engine_hours']) {
        if (form[key] === '') {
            form[key] = null;
        }
    }
    for (const key of allFieldKeys.value) {
        const t = fieldDef(key).type;
        if ((t === 'date' || t === 'datetime') && (form[key] === '' || form[key] === undefined)) {
            form[key] = null;
        }
        if (t === 'select') {
            if (form[key] === '' || form[key] === undefined) {
                form[key] = null;
            } else if (selectUsesIntegerId(key)) {
                form[key] = coerceEnumSelectToId(key, form[key]);
            }
        }
    }
};

const submit = () => {
    if (isView.value) {
        return;
    }

    form.clearErrors();
    normalizeFormBeforeSubmit();

    const url =
        isEdit.value
            ? route(
                  `${props.recordType}.update`,
                  buildResourceRouteParams(props.recordType, props.record.id),
              )
            : route(`${props.recordType}.store`);

    if (isEdit.value) {
        form.put(url, validationSubmitOptions({
            errorSelector: '[data-asset-unit-form-error]',
            onSuccess: () => {
                emit('saved', {});
                if (props.redirectAfterUpdate) {
                    router.visit(props.redirectAfterUpdate);
                }
            },
        }));
    } else {
        form.post(url, validationSubmitOptions({
            errorSelector: '[data-asset-unit-form-error]',
            onSuccess: (page) => {
                const recordId = page.props.flash?.recordId ?? page.props.flash?.record_id;
                emit('saved', { recordId });
                emit('created', recordId);
            },
        }));
    }
};

const handleCancel = () => {
    form.reset();
    emit('cancelled');
    emit('cancel');
};

const recordFilterValue = (fieldKey) => {
    const def = fieldDef(fieldKey);
    const filterKey = def.record_filter_field || def.filterby;
    if (!filterKey) {
        return null;
    }
    return form[filterKey] ?? null;
};

const camelToSnake = (value) => String(value).replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);

const relatedRecordForField = (fieldKey) => {
    const def = fieldDef(fieldKey);
    const relName = def.relationship || fieldKey.replace(/_id$/, '');
    const candidates = [
        relName,
        camelToSnake(relName),
        relName.replace(/_([a-z])/g, (_, c) => c.toUpperCase()),
    ];

    for (const key of candidates) {
        const rel = props.record?.[key];
        if (rel && typeof rel === 'object' && rel.id != null) {
            return rel;
        }
    }

    const rawId = props.record?.[fieldKey] ?? form[fieldKey];
    if (rawId != null && rawId !== '') {
        return { id: rawId };
    }

    return null;
};

const relatedRecordLabel = (fieldKey) => {
    const def = fieldDef(fieldKey);
    const rel = relatedRecordForField(fieldKey);
    if (!rel) {
        return '—';
    }
    const displayField = def.displayField || 'display_name';
    if (rel[displayField]) {
        return rel[displayField];
    }
    if (rel.display_name) {
        return rel.display_name;
    }
    if (rel.name) {
        return rel.name;
    }
    if (rel.contact?.display_name) {
        return rel.contact.display_name;
    }
    if (rel.id != null) {
        return `#${rel.id}`;
    }
    return '—';
};

const unitHeaderMeta = computed(() => {
    if (!props.record?.id) {
        return null;
    }

    const hin = String(props.record.hin ?? '').trim();
    if (hin) {
        return { label: 'Hull Number', value: hin };
    }

    const serial = String(props.record.serial_number ?? '').trim();
    if (serial) {
        return { label: 'Serial Number', value: serial };
    }

    return { label: 'Unit ID', value: `#${props.record.id}` };
});

const relatedRecordShowUrl = (fieldKey) => {
    const def = fieldDef(fieldKey);
    if (def.type !== 'record' || !def.typeDomain) {
        return null;
    }
    const rel = relatedRecordForField(fieldKey);
    if (!rel?.id) {
        return null;
    }
    try {
        return buildRecordShowUrl(def.typeDomain, rel.id, {
            assetId: props.record?.asset_id ?? form.asset_id,
        });
    } catch {
        return null;
    }
};
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form id="asset-unit-form" novalidate class="pb-24" @submit.prevent="submit">
            <div
                v-if="formErrorMessages.length"
                data-asset-unit-form-error
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
                role="alert"
            >
                <p class="font-medium">Please fix the following:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    <li v-for="(message, index) in formErrorMessages" :key="index">
                        {{ message }}
                    </li>
                </ul>
            </div>
            <div class="grid gap-6 lg:grid-cols-12">
                <div :class="showActionSidebar && record?.asset ? 'lg:col-span-8' : 'lg:col-span-12'" class="space-y-6">
                    <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div
                            class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ headerTitle }}
                                    </h1>
                                    <p class="mt-1 text-sm text-primary-100">
                                        {{ headerSubtitle }}
                                    </p>
                                </div>
                                <div v-if="unitHeaderMeta" class="text-right">
                                    <div class="text-xs font-medium text-primary-200">{{ unitHeaderMeta.label }}</div>
                                    <div class="font-mono text-lg text-white">{{ unitHeaderMeta.value }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8 border-t border-primary-500/20 p-6 dark:border-primary-900/40">
                            <p
                                v-if="!isView"
                                class="rounded-lg border border-blue-200 bg-blue-50/80 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100"
                            >
                                <span class="font-semibold">Variant:</span>
                                Only required when the selected asset has variants.
                            </p>

                            <template v-for="group in formGroups" :key="group.key">
                                <div>
                                    <h3
                                        class="mb-4 border-b border-gray-200 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                                    >
                                        {{ group.label }}
                                    </h3>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                        <template
                                            v-for="field in group.fields"
                                            :key="field.key"
                                        >
                                            <template v-if="isFieldVisible(field)">
                                                <!-- boolean -->
                                                <div
                                                    v-if="fieldDef(field.key).type === 'boolean'"
                                                    class="md:col-span-2"
                                                >
                                                    <template v-if="isView">
                                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                            {{ fieldDef(field.key).label || field.key }}
                                                        </dt>
                                                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                                            {{ form[field.key] ? 'Yes' : 'No' }}
                                                        </dd>
                                                    </template>
                                                    <div v-else class="flex items-center gap-3">
                                                        <input
                                                            :id="`au-${field.key}`"
                                                            v-model="form[field.key]"
                                                            type="checkbox"
                                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                            :disabled="isFieldDisabled(field)"
                                                        />
                                                        <label
                                                            :for="`au-${field.key}`"
                                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                                        >
                                                            {{ fieldDef(field.key).label || field.key }}
                                                        </label>
                                                    </div>
                                                </div>

                                                <!-- select -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'select'"
                                                    :class="isEnumSelectField(field.key) ? 'md:col-span-2' : ''"
                                                >
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                                    </label>
                                                    <template v-if="isView">
                                                        <p class="text-sm text-gray-900 dark:text-gray-100">
                                                            {{ getEnumLabel(field.key, form[field.key]) }}
                                                        </p>
                                                    </template>
                                                    <EnumButtonGroup
                                                        v-else-if="isEnumSelectField(field.key)"
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        :options="enumOptionsFor(field.key)"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <select
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        class="input-style"
                                                        :disabled="isFieldDisabled(field)"
                                                    >
                                                        <option v-if="!isFieldRequired(field)" value="">—</option>
                                                        <option
                                                            v-for="opt in enumOptionsFor(field.key)"
                                                            :key="`${field.key}-${opt.id}`"
                                                            :value="selectOptionValue(field.key, opt)"
                                                        >
                                                            {{ opt.name }}
                                                        </option>
                                                    </select>
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- record -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'record'"
                                                    :class="recordFieldGridClass(field.key)"
                                                >
                                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                                    </label>
                                                    <template v-if="isView">
                                                        <Link
                                                            v-if="relatedRecordShowUrl(field.key)"
                                                            :href="relatedRecordShowUrl(field.key)"
                                                            class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                                        >
                                                            {{ relatedRecordLabel(field.key) }}
                                                        </Link>
                                                        <p
                                                            v-else
                                                            class="text-sm text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{ relatedRecordLabel(field.key) }}
                                                        </p>
                                                    </template>
                                                    <RecordSelect
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        :field="fieldDef(field.key)"
                                                        v-model="form[field.key]"
                                                        :record="pseudoRecord"
                                                        :field-key="field.key"
                                                        :enum-options="enumOptionsFor(field.key)"
                                                        :filter-by="fieldDef(field.key).record_filter_field || fieldDef(field.key).filterby || null"
                                                        :filter-value="recordFilterValue(field.key)"
                                                        :disabled="isFieldDisabled(field)"
                                                        :overlay-z-index="recordSelectOverlayZIndex"
                                                        @record-selected="field.key === 'asset_id' ? handleAssetSelected($event) : undefined"
                                                    />
                                                    <p
                                                        v-if="fieldDef(field.key).description && !isView"
                                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                                    >
                                                        {{ fieldDef(field.key).description }}
                                                    </p>
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- currency -->
                                                <div v-else-if="fieldDef(field.key).type === 'currency'">
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p v-if="isView" class="text-sm text-gray-900 dark:text-gray-100">
                                                        {{ formatCurrency(form[field.key]) }}
                                                    </p>
                                                    <CurrencyInput
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        icon-position="none"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- number -->
                                                <div v-else-if="fieldDef(field.key).type === 'number'">
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p v-if="isView" class="text-sm text-gray-900 dark:text-gray-100">
                                                        {{ form[field.key] || '—' }}
                                                    </p>
                                                    <NumberInput
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        icon-position="none"
                                                        :min="fieldDef(field.key).min ?? 0"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- date -->
                                                <div v-else-if="fieldDef(field.key).type === 'date'">
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p v-if="isView" class="text-sm text-gray-900 dark:text-gray-100">
                                                        {{ form[field.key] || '—' }}
                                                    </p>
                                                    <DateInput
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- datetime -->
                                                <div v-else-if="fieldDef(field.key).type === 'datetime'">
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p v-if="isView" class="text-sm text-gray-900 dark:text-gray-100">
                                                        {{ form[field.key] || '—' }}
                                                    </p>
                                                    <DateTimeInput
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- textarea -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'textarea'"
                                                    class="md:col-span-2"
                                                >
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p
                                                        v-if="isView"
                                                        class="whitespace-pre-line text-sm text-gray-900 dark:text-gray-100"
                                                    >
                                                        {{ form[field.key] || '—' }}
                                                    </p>
                                                    <textarea
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        rows="3"
                                                        class="input-style min-h-[2.5rem] resize-y"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- text default -->
                                                <div v-else>
                                                    <label
                                                        :for="`au-${field.key}`"
                                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                                    </label>
                                                    <p v-if="isView" class="text-sm text-gray-900 dark:text-gray-100">
                                                        {{ form[field.key] || '—' }}
                                                    </p>
                                                    <input
                                                        v-else
                                                        :id="`au-${field.key}`"
                                                        v-model="form[field.key]"
                                                        type="text"
                                                        class="input-style"
                                                        :disabled="isFieldDisabled(field)"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <aside v-if="showActionSidebar && record?.asset" class="lg:col-span-4 space-y-6">
                    <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Parent asset</span>
                        </div>
                        <div class="space-y-2 p-5 text-sm">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Asset</span>
                                <Link
                                    :href="route('assets.show', record.asset.id)"
                                    class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    {{ record.asset.display_name || `#${record.asset.id}` }}
                                </Link>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Has variants</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ assetHasVariants ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <Teleport v-if="!isView" to="body">
                <div class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] backdrop-blur supports-[backdrop-filter]:bg-white/90 dark:border-gray-700 dark:bg-gray-900/95 dark:supports-[backdrop-filter]:bg-gray-900/90">
                    <div class="flex w-full items-center justify-end gap-3">
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 disabled:opacity-50"
                            @click="handleCancel"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="asset-unit-form"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span v-if="form.processing" class="material-icons animate-spin text-base">refresh</span>
                            <span v-else class="material-icons text-base">save</span>
                            {{ form.processing ? 'Saving…' : isCreate ? 'Create unit' : 'Save changes' }}
                        </button>
                    </div>
                </div>
            </Teleport>
        </form>
    </div>
</template>

<style scoped>
.input-style {
    @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100;
}
</style>
