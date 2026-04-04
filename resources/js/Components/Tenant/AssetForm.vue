<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import DateTimeInput from '@/Components/Tenant/FormComponents/DateTime.vue';
import Rating from '@/Components/Tenant/FormComponents/Rating.vue';
import MorphSelect from '@/Components/Tenant/MorphSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import NumberInput from '@/Components/Tenant/FormComponents/Number.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { useAssetSchemaForm } from '@/composables/useAssetSchemaForm.js';

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
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
    preventRedirect: { type: Boolean, default: false },
    formId: { type: String, default: null },
    imageUrls: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
    recordIdentifier: { type: [String, Number], default: null },
    extraRouteParams: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    specsContextAssetType: { type: Number, default: null },
});

const emit = defineEmits(['submit', 'cancel', 'created', 'updated']);

const {
    form,
    isEditMode,
    isCreateMode,
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
    isProcessing,
    imagePreviews,
} = useAssetSchemaForm(props, emit);

const hasVariants = computed(() => {
    const fromRecord = !!props.record?.has_variants;
    if (isEditMode.value) {
        const fv = form.has_variants;
        const fromForm = fv === 1 || fv === true || fv === '1';
        return fromForm || fromRecord;
    }
    return fromRecord;
});

const showVariantModal = ref(false);
const editingVariantIdx = ref(null);
const variantModalError = ref('');
const savingVariant = ref(false);
const loadingVariantDetail = ref(false);
const localVariants = ref([...(props.record?.variants ?? [])]);

watch(
    () => props.record?.variants,
    (v) => {
        if (Array.isArray(v)) {
            localVariants.value = [...v];
        }
    },
    { deep: true, immediate: true },
);

const getVariantSpecValuesArray = (variant) => {
    if (!variant) return [];
    if (Array.isArray(variant.spec_values)) return variant.spec_values;
    if (Array.isArray(variant.specValues)) return variant.specValues;
    return [];
};

const buildVariantFormSpecValues = (variant) => {
    const existing = {};
    getVariantSpecValuesArray(variant).forEach((sv) => {
        existing[sv.asset_spec_definition_id] = sv;
    });
    const out = {};
    (resolvedAvailableSpecs.value || []).forEach((spec) => {
        const sv = existing[spec.id];
        out[spec.id] = sv
            ? {
                  value_number: sv.value_number ?? null,
                  value_text: sv.value_text ?? null,
                  value_boolean: !!(sv.value_boolean === true || sv.value_boolean === 1),
                  unit: sv.unit ?? spec.unit ?? null,
              }
            : {
                  value_number: null,
                  value_text: null,
                  value_boolean: false,
                  unit: spec.unit ?? null,
              };
    });
    return out;
};

const variantForm = ref({
    id: null,
    display_name: '',
    specValues: {},
});

const variantModalUnit = (spec) =>
    variantForm.value.specValues?.[spec.id]?.unit || spec.unit || null;

const buildVariantSpecsPayload = () =>
    (resolvedAvailableSpecs.value || []).map((spec) => {
        const val = variantForm.value.specValues?.[spec.id] || {};
        return {
            spec_id: spec.id,
            value_number:
                spec.type === 'number'
                    ? val.value_number !== '' && val.value_number !== null && val.value_number !== undefined
                        ? val.value_number
                        : null
                    : null,
            value_text: spec.type === 'text' || spec.type === 'select' ? val.value_text || null : null,
            value_boolean: spec.type === 'boolean' ? (val.value_boolean ? true : false) : null,
            unit: val.unit || null,
        };
    });

const openAddVariantModal = () => {
    editingVariantIdx.value = null;
    variantModalError.value = '';
    variantForm.value = {
        id: null,
        display_name: '',
        specValues: buildVariantFormSpecValues(null),
    };
    showVariantModal.value = true;
};

const openEditVariantModal = async (idx) => {
    editingVariantIdx.value = idx;
    variantModalError.value = '';
    const v = localVariants.value[idx];
    if (v?.id && props.record?.id) {
        loadingVariantDetail.value = true;
        try {
            const { data } = await axios.get(
                route('assets.variants.show', { asset: props.record.id, variant: v.id }),
                { headers: { Accept: 'application/json' } },
            );
            const r = data?.record;
            variantForm.value = {
                id: r.id,
                display_name: r.display_name || r.name || '',
                specValues: buildVariantFormSpecValues(r),
            };
        } catch {
            variantForm.value = {
                id: v.id,
                display_name: v.display_name || v.name || '',
                specValues: buildVariantFormSpecValues(v),
            };
        } finally {
            loadingVariantDetail.value = false;
        }
    } else {
        variantForm.value = {
            id: v?.id ?? null,
            display_name: v?.display_name ?? v?.name ?? '',
            specValues: buildVariantFormSpecValues(v),
        };
    }
    showVariantModal.value = true;
};

const saveVariant = async () => {
    variantModalError.value = '';
    const name = variantForm.value.display_name?.trim();
    if (!name) return;
    if (!props.record?.id) {
        variantModalError.value = 'Save the asset first, then add variants.';
        return;
    }
    if (!hasVariants.value) {
        variantModalError.value = 'Turn on “This asset has variants” on the asset before adding variants.';
        return;
    }
    savingVariant.value = true;
    try {
        const payload = { name, specs: buildVariantSpecsPayload() };
        if (variantForm.value.id) {
            await axios.put(
                route('assets.variants.update', { asset: props.record.id, variant: variantForm.value.id }),
                payload,
            );
        } else {
            await axios.post(route('assets.variants.store', { asset: props.record.id }), payload);
        }
        showVariantModal.value = false;
        router.reload({ only: ['record'] });
    } catch (e) {
        const d = e.response?.data;
        if (d?.errors && typeof d.errors === 'object') {
            variantModalError.value = Object.values(d.errors)
                .flat()
                .filter(Boolean)
                .join(' ');
        } else {
            variantModalError.value = d?.message || 'Could not save variant.';
        }
    } finally {
        savingVariant.value = false;
    }
};

const removeVariant = async (idx) => {
    const v = localVariants.value[idx];
    if (!props.record?.id || !v?.id) {
        localVariants.value.splice(idx, 1);
        return;
    }
    if (!window.confirm('Delete this variant? Units must be reassigned first if any are assigned.')) {
        return;
    }
    try {
        await axios.delete(route('assets.variants.destroy', { asset: props.record.id, variant: v.id }));
        router.reload({ only: ['record'] });
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not delete variant.');
    }
};

const formatMoney = (v) => {
    if (v == null || v === '') return '—';
    const n = parseFloat(v);
    return isNaN(n) ? '—' : `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const recordBase = computed(() =>
    props.record || (Object.keys(props.initialData || {}).length > 0 ? props.initialData : null),
);

defineExpose({ submitForm, cancelForm, isProcessing });
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-9">
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-white">
                                    <template v-if="isCreateMode">NEW ASSET</template>
                                    <template v-else-if="isEditMode">EDIT ASSET</template>
                                    <template v-else>ASSET</template>
                                </h1>
                                <p class="text-primary-100 text-sm mt-1">Asset Record</p>
                            </div>
                            <div v-if="record?.id" class="text-right">
                                <div class="text-primary-200 text-sm font-medium">Asset ID</div>
                                <div class="text-white text-lg font-mono">#{{ record.id }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-8">
                        <div
                            v-if="isEditMode"
                            class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/80 dark:bg-blue-950/40 px-4 py-3 text-sm text-blue-900 dark:text-blue-100"
                            role="note"
                        >
                            <p class="font-semibold">How assets, variants &amp; specs work</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-blue-800/90 dark:text-blue-200/90">
                                <li>Enable <strong>Has Variants</strong> when each configuration needs its own specs — specs live on the variant, not this asset.</li>
                                <li>Leave variants off for single-spec products — specs are stored here on the asset aligned to its type.</li>
                                <li>With variants on, manage variant records from the Variants sublist; the table below is a quick preview only.</li>
                            </ul>
                        </div>

                        <form :id="formId || `form-${recordType}-${record?.id || 'new'}`" @submit.prevent="handleSubmit">
                            <p v-if="form.errors?.general?.length" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">
                                {{ form.errors.general[0] }}
                            </p>

                            <template v-for="group in visibleFormGroups" :key="group.key">
                                <section class="mb-10 last:mb-0">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4">
                                        {{ group.label }}
                                    </h3>

                                    <!-- Specs -->
                                    <template v-if="group.type === 'specs'">
                                        <div class="mb-4 flex items-center justify-between">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Specifications for this asset type
                                            </p>
                                            <Link
                                                :href="route('asset-specs.index')"
                                                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                            >
                                                <span class="material-icons text-[16px]">tune</span>
                                                Manage spec definitions
                                            </Link>
                                        </div>
                                        <div v-if="resolvedAvailableSpecs.length === 0" class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                            No specifications available for this asset type.
                                        </div>
                                        <div v-else class="space-y-6">
                                            <div v-for="section in groupedSpecSections" :key="section.key">
                                                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                    {{ section.label }}
                                                </h4>
                                                <div class="grid gap-4 sm:grid-cols-12">
                                                    <div v-for="spec in section.specs" :key="spec.id" class="sm:col-span-6 xl:col-span-4">
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
                                                        <template v-else-if="form.specValues?.[spec.id]">
                                                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                {{ spec.label }}
                                                                <span v-if="spec.is_required" class="text-red-500">*</span>
                                                            </label>
                                                            <div v-if="spec.type === 'number'" class="flex items-center gap-2">
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                                                    :value="form.specValues[spec.id].value_number != null ? form.specValues[spec.id].value_number : ''"
                                                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                                                    @change="(e) => {
                                                                        const n = parseFloat(e.target.value);
                                                                        form.specValues[spec.id].value_number = isNaN(n) ? null : n;
                                                                    }"
                                                                    @blur="(e) => {
                                                                        const n = parseFloat(e.target.value);
                                                                        e.target.value = isNaN(n) ? '' : n.toFixed(2);
                                                                    }"
                                                                >
                                                                <span v-if="getSpecDisplayUnit(spec)" class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                                    {{ getSpecDisplayUnit(spec) }}
                                                                </span>
                                                            </div>
                                                            <input
                                                                v-else-if="spec.type === 'text'"
                                                                v-model="form.specValues[spec.id].value_text"
                                                                type="text"
                                                                :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                                                class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                                            >
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
                                                            <label v-else-if="spec.type === 'boolean'" class="flex cursor-pointer items-center gap-2">
                                                                <input
                                                                    v-model="form.specValues[spec.id].value_boolean"
                                                                    type="checkbox"
                                                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                                                >
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">Yes</span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Regular fields -->
                                    <div v-else-if="group.filteredFields && group.filteredFields.length > 0">
                                        <div v-if="group.is_address && hasAddressTags(group)" class="mb-4 grid gap-4 sm:grid-cols-12">
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
                                        <div v-else class="grid gap-4 sm:grid-cols-12">
                                            <template v-for="field in group.filteredFields" :key="field?.key">
                                                <div v-if="field && isFieldVisible(field)" :class="getFieldColSpan(field)">
                                                    <label :for="getFieldId(field.key)" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                                        {{ getFieldLabel(field.key) }}
                                                        <span
                                                            v-if="getFieldType(field.key) === 'datetime' || getFieldType(field.key) === 'date'"
                                                            class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1"
                                                        >
                                                            ({{ accountTimezoneLabel }})
                                                        </span>
                                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                                    </label>

                                                    <!-- View -->
                                                    <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                                        <span v-if="getFieldType(field.key) === 'textarea'" class="whitespace-pre-wrap">{{ getFieldValue(field.key) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'json'" class="whitespace-pre-wrap font-mono text-xs">{{ getFieldValue(field.key) || '—' }}</span>
                                                        <div v-else-if="getFieldType(field.key) === 'boolean' || getFieldType(field.key) === 'checkbox'" class="flex items-center">
                                                            <span class="select-none text-sm font-medium">{{ getFieldValue(field.key) == 1 || getFieldValue(field.key) === true ? 'Yes' : 'No' }}</span>
                                                        </div>
                                                        <span v-else-if="getFieldType(field.key) === 'record'">{{ getRecordDisplayName(field.key, getFieldValue(field.key)) }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'select' && getFieldDefinition(field.key).enum">{{ getEnumLabel(field.key, getFieldValue(field.key)) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'multi_enum'">{{ getMultiEnumDisplay(field.key) }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'tel'">{{ getFormattedPhoneValue(field.key) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'datetime'">{{ formatDateTime(getFieldValue(field.key)) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'date'">{{ formatDate(getFieldValue(field.key)) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'time'">{{ getFieldValue(field.key) || '—' }}</span>
                                                        <span v-else-if="getFieldType(field.key) === 'rating'">
                                                            <span v-for="star in 5" :key="star" class="inline text-yellow-400">{{ star <= getFieldValue(field.key) ? '★' : '☆' }}</span>
                                                            <span class="ml-2 text-gray-600 dark:text-gray-400">{{ getFieldValue(field.key) || 0 }}/5</span>
                                                        </span>
                                                        <span v-else-if="getFieldType(field.key) === 'file'">
                                                            <span v-if="getFieldValue(field.key)" class="text-blue-600 dark:text-blue-400 underline">{{ getFileName(getFieldValue(field.key)) }}</span>
                                                            <span v-else class="text-gray-500 dark:text-gray-400">No file uploaded</span>
                                                        </span>
                                                        <div v-else-if="getFieldType(field.key) === 'image'">
                                                            <img
                                                                v-if="getImageSource(field.key)"
                                                                :src="getImageSource(field.key)"
                                                                class="h-32 w-32 rounded-lg border border-gray-200 object-cover dark:border-gray-700"
                                                                alt=""
                                                                @error="$event.target.style.display = 'none'"
                                                            >
                                                            <span v-else class="text-gray-500 dark:text-gray-400">No image</span>
                                                        </div>
                                                        <div v-else-if="getFieldType(field.key) === 'wysiwyg'" class="prose prose-sm dark:prose-invert max-w-none rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800" v-html="getFieldValue(field.key) || '—'" />
                                                        <span v-else-if="getFieldType(field.key) === 'morph'">
                                                            <span v-if="record && record[getFieldDefinition(field.key).id_field]" class="inline-flex items-center gap-2">
                                                                <span class="rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ getFieldValue(field.key)?.split('\\').pop() || 'Unknown' }}</span>
                                                                <span class="text-gray-400">→</span>
                                                                <span class="text-sm">{{ record.relatable?.display_name || '—' }}</span>
                                                            </span>
                                                            <span v-else class="text-gray-500 dark:text-gray-400">Not assigned</span>
                                                        </span>
                                                        <span v-else-if="getFieldType(field.key) === 'currency'">
                                                            {{
                                                                getFieldValue(field.key) !== null && getFieldValue(field.key) !== undefined && getFieldValue(field.key) !== ''
                                                                    ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(getFieldValue(field.key))
                                                                    : '—'
                                                            }}
                                                        </span>
                                                        <span v-else>{{ getFieldValue(field.key) || '—' }}</span>
                                                    </div>

                                                    <!-- Edit -->
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
                                                                >
                                                                <span>{{ option.name }}</span>
                                                            </label>
                                                        </div>
                                                        <div v-else-if="getFieldType(field.key) === 'tel'" class="relative">
                                                            <input
                                                                :id="getFieldId(field.key)"
                                                                type="tel"
                                                                :value="getFormattedPhoneValue(field.key)"
                                                                class="input-style"
                                                                placeholder="(123) 456-7890"
                                                                :required="isFieldRequired(field)"
                                                                :disabled="isFieldDisabled(field.key)"
                                                                @input="handlePhoneInput(field.key, $event)"
                                                                @blur="handlePhoneInput(field.key, $event)"
                                                            >
                                                        </div>
                                                        <NumberInput
                                                            v-else-if="getFieldType(field.key) === 'number'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            :min="getFieldDefinition(field.key).min"
                                                            :max="getFieldDefinition(field.key).max"
                                                            :step="getFieldDefinition(field.key).step || 1"
                                                            :allow-decimals="getFieldDefinition(field.key).allow_decimals !== false"
                                                            :is-year="getFieldDefinition(field.key).isYear === true"
                                                        />
                                                        <CurrencyInput
                                                            v-else-if="getFieldType(field.key) === 'currency'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                        />
                                                        <textarea
                                                            v-else-if="getFieldType(field.key) === 'json'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            rows="6"
                                                            class="block w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            placeholder="{ }"
                                                        />
                                                        <input
                                                            v-else-if="['text', 'email'].includes(getFieldType(field.key))"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :type="getFieldType(field.key)"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            class="input-style"
                                                        >
                                                        <textarea
                                                            v-else-if="getFieldType(field.key) === 'textarea'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            rows="4"
                                                            class="block w-full p-2.5 input-style"
                                                        />
                                                        <RecordSelect
                                                            v-else-if="getFieldType(field.key) === 'record'"
                                                            :id="getFieldId(field.key)"
                                                            :field="getFieldDefinition(field.key)"
                                                            v-model="form[field.key]"
                                                            :disabled="isFieldDisabled(field.key) || isFieldDisabledByFilter(field.key)"
                                                            :enum-options="getEnumOptions(field.key)"
                                                            :record="recordBase"
                                                            :field-key="field.key"
                                                            :filter-by="getFieldDefinition(field.key).record_filter_field || getFieldDefinition(field.key).filterby || null"
                                                            :filter-value="getFieldFilterValue(field.key)"
                                                            @record-selected="(selectedRecord) => applySourcedDefaults(field.key, selectedRecord)"
                                                        />
                                                        <select
                                                            v-else-if="getFieldType(field.key) === 'select'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            :class="['input-style', !form[field.key] ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900']"
                                                        >
                                                            <option v-if="!isFieldRequired(field)" value="" disabled>Select {{ getFieldLabel(field.key) }}</option>
                                                            <option v-for="option in getEnumOptions(field.key)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                                        </select>
                                                        <DateTimeInput
                                                            v-else-if="getFieldType(field.key) === 'datetime'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                        />
                                                        <DateInput
                                                            v-else-if="getFieldType(field.key) === 'date'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                        />
                                                        <input
                                                            v-else-if="getFieldType(field.key) === 'time'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            type="time"
                                                            :required="isFieldRequired(field)"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            class="input-style"
                                                        >
                                                        <Rating
                                                            v-else-if="getFieldType(field.key) === 'rating'"
                                                            v-model="form[field.key]"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            :show-value="false"
                                                        />
                                                        <div v-else-if="getFieldType(field.key) === 'file'" class="space-y-2">
                                                            <input
                                                                :id="getFieldId(field.key)"
                                                                type="file"
                                                                :required="isFieldRequired(field)"
                                                                :disabled="isFieldDisabled(field.key)"
                                                                :accept="getFieldDefinition(field.key).accept || '*/*'"
                                                                class="input-style"
                                                                @change="handleFileInput(field.key, $event)"
                                                            >
                                                            <div v-if="form[field.key] && typeof form[field.key] === 'string'" class="text-sm text-gray-600 dark:text-gray-400">
                                                                Current file: <span class="font-medium">{{ getFileName(form[field.key]) }}</span>
                                                            </div>
                                                        </div>
                                                        <div v-else-if="getFieldType(field.key) === 'image'" class="space-y-4">
                                                            <div v-if="getImageSource(field.key)" class="group relative h-32 w-32">
                                                                <img :src="getImageSource(field.key)" class="h-full w-full rounded-lg border border-gray-200 object-cover dark:border-gray-700" alt="">
                                                                <button
                                                                    v-if="!isFieldDisabled(field.key)"
                                                                    type="button"
                                                                    class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white opacity-0 shadow-sm transition-opacity hover:bg-red-600 group-hover:opacity-100"
                                                                    @click="form[field.key] = null; delete imagePreviews[field.key]"
                                                                >
                                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                </button>
                                                            </div>
                                                            <div v-if="!getImageSource(field.key) || !isFieldDisabled(field.key)">
                                                                <input
                                                                    :id="getFieldId(field.key)"
                                                                    type="file"
                                                                    accept="image/*"
                                                                    class="input-style"
                                                                    :required="isFieldRequired(field) && !form[field.key]"
                                                                    :disabled="isFieldDisabled(field.key)"
                                                                    @change="handleImageInput(field.key, $event)"
                                                                >
                                                            </div>
                                                        </div>
                                                        <MorphSelect
                                                            v-else-if="getFieldType(field.key) === 'morph'"
                                                            :id="getFieldId(field.key)"
                                                            :field="getFieldDefinition(field.key)"
                                                            v-model="form[getFieldDefinition(field.key).id_field]"
                                                            v-model:selected-type="form[field.key]"
                                                            :disabled="isFieldDisabled(field.key)"
                                                            :initial-display-name="getMorphRelatedDisplayName(field.key)"
                                                        />
                                                        <TipTapEditor
                                                            v-else-if="getFieldType(field.key) === 'wysiwyg'"
                                                            :id="getFieldId(field.key)"
                                                            v-model="form[field.key]"
                                                            :error="form.errors[field.key]"
                                                            :show-anchor="getFieldDefinition(field.key).show_anchor"
                                                        />
                                                        <label
                                                            v-else-if="getFieldType(field.key) === 'checkbox' || getFieldType(field.key) === 'boolean'"
                                                            :for="getFieldId(field.key)"
                                                            class="flex items-center rounded-lg border border-gray-300 bg-gray-50 py-3.5 ps-4 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        >
                                                            <input type="hidden" :name="field.key" :value="0">
                                                            <input
                                                                :id="getFieldId(field.key)"
                                                                v-model="form[field.key]"
                                                                type="checkbox"
                                                                :name="field.key"
                                                                :true-value="1"
                                                                :false-value="0"
                                                                :disabled="isFieldDisabled(field.key)"
                                                                class="h-4 w-4 rounded border border-default-medium bg-neutral-secondary-medium focus:ring-2 focus:ring-brand-soft"
                                                            >
                                                        </label>
                                                        <p v-if="getFieldDefinition(field.key).help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                            {{ getFieldDefinition(field.key).help }}
                                                        </p>
                                                        <p v-if="form.errors[field.key]" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                                            {{ form.errors[field.key] }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </section>
                            </template>

                            <!-- Variants preview -->
                            <section v-if="record || isEditMode" class="mb-10 border-t border-gray-200 pt-8 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-2">
                                    Variants
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    Each variant has its own specification values (same definitions as this asset’s type). Edit here or use the Variants sublist on the asset show page.
                                </p>
                                <div v-if="hasVariants">
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Variants ({{ localVariants.length }})</span>
                                        <button
                                            v-if="isEditMode"
                                            type="button"
                                            :disabled="!record?.id"
                                            class="inline-flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/30"
                                            :title="!record?.id ? 'Save the asset first' : undefined"
                                            @click="openAddVariantModal"
                                        >
                                            <span class="material-icons text-base leading-none">add_circle</span>
                                            Add Variant
                                        </button>
                                    </div>
                                    <div v-if="localVariants.length === 0" class="rounded-lg border-2 border-dashed border-gray-300 py-10 text-center dark:border-gray-700">
                                        <span class="material-icons mb-2 block text-4xl text-gray-400 dark:text-gray-600">account_tree</span>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No variants yet</p>
                                    </div>
                                    <div v-else class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-sm font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                                                    <th v-if="isEditMode" class="w-36 px-4 py-3 text-right text-sm font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                                <tr v-for="(variant, idx) in localVariants" :key="variant.id ?? idx" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ variant.display_name || variant.name || '—' }}</td>
                                                    <td v-if="isEditMode" class="px-4 py-3">
                                                        <div class="flex justify-end gap-2">
                                                            <button type="button" class="text-primary-600 dark:text-primary-400" @click="openEditVariantModal(idx)">
                                                                <span class="material-icons text-base">edit</span>
                                                            </button>
                                                            <button type="button" class="text-red-600 dark:text-red-400" @click="removeVariant(idx)">
                                                                <span class="material-icons text-base">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div
                                        class="mt-4 rounded-xl border border-blue-100 bg-blue-50/80 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                                    >
                                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Specifications are per variant</p>
                                        <p class="mt-1 text-sm text-blue-800/90 dark:text-blue-200/90">
                                            Use <strong>Add Variant</strong> or <strong>Edit</strong> to set spec values. The Variants sublist on the asset show page lists all variants for quick access.
                                        </p>
                                    </div>
                                </div>
                            </section>

                        </form>
                    </div>
                </div>
            </div>

            <aside class="lg:col-span-3 space-y-6">
                <div class="sticky top-5 space-y-6">
                    <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-600 bg-gray-700 px-5 py-4">
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <template v-if="isEditMode && !formId">
                                <button
                                    type="button"
                                    :disabled="form.processing || isProcessing"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                                    @click="submitForm"
                                >
                                    <span v-if="form.processing || isProcessing" class="material-icons animate-spin text-base">refresh</span>
                                    <span v-else class="material-icons text-base">save</span>
                                    {{ (form.processing || isProcessing) ? 'Saving…' : (isCreateMode ? 'Create' : 'Save') }} {{ recordTitle }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="form.processing || isProcessing"
                                    class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-200 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-gray-700"
                                    @click="handleCancel"
                                >
                                    Cancel
                                </button>
                            </template>
                            <p v-else class="py-2 text-center text-sm text-gray-400 dark:text-gray-500">
                                Use the page header or sublists for actions
                            </p>
                        </div>
                    </div>

                    <div v-if="record" class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Asset Info</span>
                        </div>
                        <div class="space-y-3 p-5 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Type</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ record.type_label || record.type || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Brand</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ record.make?.display_name || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Model</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ record.model || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Year</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ record.year || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Default Price</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatMoney(record.default_price) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Default Cost</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ formatMoney(record.default_cost) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Variants</span>
                                <span class="inline-flex items-center gap-1 text-sm font-medium" :class="hasVariants ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500'">
                                    <span class="material-icons text-base">{{ hasVariants ? 'check_circle' : 'remove' }}</span>
                                    {{ hasVariants ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Specs on</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ hasVariants ? 'Variants' : 'Asset' }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ record.created_at ? new Date(record.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Updated</span>
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ record.updated_at ? new Date(record.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(imageUrls).length > 0" class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Images</span>
                        </div>
                        <div class="p-5">
                            <div class="grid grid-cols-2 gap-2">
                                <div v-for="(url, key) in imageUrls" :key="key" class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                                    <img :src="url" :alt="key" class="h-full w-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showVariantModal" class="fixed inset-0 z-50 flex items-start justify-center px-4 pb-8 pt-10">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="!savingVariant && (showVariantModal = false)" />
                    <div class="relative flex max-h-[85vh] w-full max-w-3xl flex-col rounded-xl bg-white shadow-2xl dark:bg-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ editingVariantIdx !== null ? 'Edit Variant' : 'Add Variant' }}
                            </h3>
                            <button
                                type="button"
                                :disabled="savingVariant"
                                class="rounded p-1 text-gray-400 hover:text-gray-600 disabled:opacity-40 dark:hover:text-gray-300"
                                @click="showVariantModal = false"
                            >
                                <span class="material-icons">close</span>
                            </button>
                        </div>
                        <div class="relative flex-1 space-y-5 overflow-y-auto p-6">
                            <div v-if="loadingVariantDetail" class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70 dark:bg-gray-800/70">
                                <span class="material-icons animate-spin text-3xl text-primary-600">refresh</span>
                            </div>
                            <p v-if="variantModalError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">
                                {{ variantModalError }}
                            </p>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Variant name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="variantForm.display_name"
                                    type="text"
                                    class="input-style"
                                    placeholder="e.g. Standard, Sport package"
                                    :disabled="loadingVariantDetail"
                                >
                            </div>

                            <div class="border-t border-gray-200 pt-5 dark:border-gray-700">
                                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white">Specifications</h4>
                                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                                    Values are stored on this variant (same definitions as asset type {{ record?.type_label || record?.type || '' }}).
                                </p>
                                <div v-if="resolvedAvailableSpecs.length === 0" class="rounded-lg border border-dashed border-gray-300 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                    No spec definitions for this asset type.
                                    <Link :href="route('asset-specs.index')" class="mt-2 inline-block font-medium text-primary-600 dark:text-primary-400">Manage spec definitions</Link>
                                </div>
                                <div v-else class="space-y-6">
                                    <div v-for="section in groupedSpecSections" :key="section.key">
                                        <h5 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ section.label }}
                                        </h5>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div v-for="spec in section.specs" :key="spec.id">
                                                <template v-if="variantForm.specValues[spec.id]">
                                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ spec.label }}
                                                        <span v-if="spec.is_required" class="text-red-500">*</span>
                                                    </label>
                                                    <div v-if="spec.type === 'number'" class="flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            :value="variantForm.specValues[spec.id].value_number != null ? variantForm.specValues[spec.id].value_number : ''"
                                                            class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            :disabled="loadingVariantDetail"
                                                            @change="(e) => {
                                                                const n = parseFloat(e.target.value);
                                                                variantForm.specValues[spec.id].value_number = isNaN(n) ? null : n;
                                                            }"
                                                            @blur="(e) => {
                                                                const n = parseFloat(e.target.value);
                                                                e.target.value = isNaN(n) ? '' : n.toFixed(2);
                                                            }"
                                                        >
                                                        <span v-if="variantModalUnit(spec)" class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                            {{ variantModalUnit(spec) }}
                                                        </span>
                                                    </div>
                                                    <input
                                                        v-else-if="spec.type === 'text'"
                                                        v-model="variantForm.specValues[spec.id].value_text"
                                                        type="text"
                                                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        :disabled="loadingVariantDetail"
                                                        :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                                    >
                                                    <select
                                                        v-else-if="spec.type === 'select'"
                                                        v-model="variantForm.specValues[spec.id].value_text"
                                                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        :disabled="loadingVariantDetail"
                                                    >
                                                        <option value="">Select {{ spec.label.toLowerCase() }}</option>
                                                        <option v-for="opt in (spec.options || [])" :key="opt.value" :value="opt.value">
                                                            {{ opt.label }}
                                                        </option>
                                                    </select>
                                                    <label v-else-if="spec.type === 'boolean'" class="flex cursor-pointer items-center gap-2 pt-1">
                                                        <input
                                                            v-model="variantForm.specValues[spec.id].value_boolean"
                                                            type="checkbox"
                                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                                            :disabled="loadingVariantDetail"
                                                        >
                                                        <span class="text-sm text-gray-600 dark:text-gray-400">Yes</span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-700/30">
                            <button
                                type="button"
                                :disabled="savingVariant"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                @click="showVariantModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                :disabled="savingVariant || loadingVariantDetail || !variantForm.display_name?.trim()"
                                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:opacity-40"
                                @click="saveVariant"
                            >
                                <span v-if="savingVariant" class="material-icons mr-1 inline-block animate-spin align-middle text-base">refresh</span>
                                {{ savingVariant ? 'Saving…' : (editingVariantIdx !== null ? 'Save variant' : 'Create variant') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
