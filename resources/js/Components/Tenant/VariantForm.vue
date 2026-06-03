<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import MeasurementImperialInput from '@/Components/Tenant/FormComponents/MeasurementImperialInput.vue';
import { useAssetSchemaForm } from '@/composables/useAssetSchemaForm.js';
import { formatLengthMmImperial } from '@/Utils/measurementMm.js';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    /** Parent asset id for nested `assets.variants.*` routes */
    assetId: { type: [Number, String], default: null },
    extraRouteParams: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    specsContextAssetType: { type: Number, default: null },
    mode: {
        type: String,
        default: 'edit',
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
    preventRedirect: { type: Boolean, default: false },
    redirectAfterUpdate: { type: String, default: null },
    /** Modal layout: no page chrome, inline actions */
    compact: { type: Boolean, default: false },
    formId: { type: String, default: null },
    recordType: { type: String, default: 'assets.variants' },
    recordTitle: { type: String, default: 'Variant' },
    enableHasVariantsOnStore: { type: Boolean, default: true },
});

const emit = defineEmits(['submit', 'cancel', 'created', 'updated']);

const {
    form,
    isEditMode,
    isCreateMode,
    visibleFormGroups,
    getFieldId,
    getFieldDefinition,
    getFieldType,
    getFieldLabel,
    getFieldValue,
    getFieldColSpan,
    isFieldRequired,
    isFieldDisabled,
    isFieldVisible,
    staticSpecFormFieldEntries,
    groupedSpecSections,
    resolvedAvailableSpecs,
    getSpecDisplayValue,
    getSpecDisplayUnit,
    getEnumOptions,
    getEnumLabel,
    handleSubmit,
    handleCancel,
    submitForm,
    cancelForm,
    isProcessing,
    applyCopiedVariantRecord,
    normalizedSchema,
} = useAssetSchemaForm(props, emit);

const showActions = computed(() => isEditMode.value && !props.formId);

const headerLabel = computed(() => {
    if (isCreateMode.value) {
        return 'New variant';
    }
    if (props.record?.display_name || props.record?.name) {
        return props.record.display_name || props.record.name;
    }
    return props.record?.id ? `Variant #${props.record.id}` : 'Variant';
});

defineExpose({
    submitForm,
    cancelForm,
    isProcessing,
    applyCopiedVariantRecord,
});
</script>

<template>
    <div
        v-if="!normalizedSchema"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
    >
        Variant form configuration is missing. Try refreshing the page.
    </div>
    <form
        v-else
        :id="formId || `variant-form-${record?.id || 'new'}`"
        class="w-full"
        @submit.prevent="handleSubmit"
    >
        <p
            v-if="form.errors?.general?.length"
            class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200"
        >
            {{ form.errors.general[0] }}
        </p>

        <div
            :class="compact
                ? 'space-y-6'
                : 'overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800'"
        >
            <div
                v-if="!compact"
                class="border-b border-gray-200 bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:border-gray-700 dark:from-primary-700 dark:to-primary-800"
            >
                <h2 class="text-lg font-bold text-white">
                    {{ isCreateMode ? 'New variant' : 'Edit variant' }}
                </h2>
                <p class="mt-1 truncate text-sm text-primary-100">
                    {{ headerLabel }}
                </p>
            </div>

            <div :class="compact ? '' : 'space-y-8 p-6'">
                <template v-for="group in visibleFormGroups" :key="group.key">
                    <section :class="compact ? 'mb-6 last:mb-0' : 'mb-10 last:mb-0'">
                        <h3
                            class="mb-4 border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:border-gray-700 dark:text-white"
                        >
                            {{ group.label }}
                        </h3>

                        <!-- Specifications -->
                        <template v-if="group.type === 'specs'">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Specifications for this asset type
                                </p>
                                <Link
                                    :href="route('asset-specs.index')"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
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
                                        Standard dimensions
                                    </p>
                                    <div class="grid gap-4 sm:grid-cols-12">
                                        <div
                                            v-for="sk in staticSpecFormFieldEntries"
                                            :key="'static-spec-' + sk"
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
                                                <span v-if="getFieldType(sk) === 'measurement'">
                                                    {{ formatLengthMmImperial(getFieldValue(sk)) }}
                                                </span>
                                                <span v-else>{{ getFieldValue(sk) || '—' }}</span>
                                            </div>
                                            <MeasurementImperialInput
                                                v-else-if="getFieldType(sk) === 'measurement'"
                                                :id="getFieldId(sk)"
                                                v-model="form[sk]"
                                                :required="isFieldRequired({ key: sk })"
                                                :disabled="isFieldDisabled(sk)"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="resolvedAvailableSpecs.length" class="space-y-6">
                                    <div v-for="section in groupedSpecSections" :key="section.key">
                                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ section.label }}
                                        </h4>
                                        <div class="grid gap-4 sm:grid-cols-12">
                                            <div
                                                v-for="spec in section.specs"
                                                :key="spec.id"
                                                class="sm:col-span-6 xl:col-span-4"
                                            >
                                                <template v-if="!isEditMode">
                                                    <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ spec.label }}
                                                    </p>
                                                    <p class="text-sm text-gray-900 dark:text-white">
                                                        <template v-if="spec.type === 'boolean'">
                                                            {{ getSpecDisplayValue(spec) ? 'Yes' : 'No' }}
                                                        </template>
                                                        <template v-else-if="getSpecDisplayValue(spec) != null && getSpecDisplayValue(spec) !== ''">
                                                            {{ getSpecDisplayValue(spec) }}
                                                            <span v-if="getSpecDisplayUnit(spec)" class="text-xs text-gray-500">
                                                                {{ getSpecDisplayUnit(spec) }}
                                                            </span>
                                                        </template>
                                                        <span v-else class="text-gray-400">—</span>
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
                                                            :value="form.specValues[spec.id].value_number != null ? form.specValues[spec.id].value_number : ''"
                                                            class="input-style w-full"
                                                            @change="(e) => {
                                                                const n = parseFloat(e.target.value);
                                                                form.specValues[spec.id].value_number = Number.isNaN(n) ? null : n;
                                                            }"
                                                        >
                                                        <span v-if="getSpecDisplayUnit(spec)" class="text-sm text-gray-500">
                                                            {{ getSpecDisplayUnit(spec) }}
                                                        </span>
                                                    </div>
                                                    <input
                                                        v-else-if="spec.type === 'text'"
                                                        v-model="form.specValues[spec.id].value_text"
                                                        type="text"
                                                        class="input-style w-full"
                                                    >
                                                    <select
                                                        v-else-if="spec.type === 'select'"
                                                        v-model="form.specValues[spec.id].value_text"
                                                        class="input-style w-full"
                                                    >
                                                        <option value="">Select…</option>
                                                        <option
                                                            v-for="option in (spec.options || [])"
                                                            :key="option.value"
                                                            :value="option.value"
                                                        >
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                    <label
                                                        v-else-if="spec.type === 'boolean'"
                                                        class="flex cursor-pointer items-center gap-2"
                                                    >
                                                        <input
                                                            v-model="form.specValues[spec.id].value_boolean"
                                                            type="checkbox"
                                                            class="h-4 w-4 rounded border-gray-300 text-primary-600"
                                                        >
                                                        <span class="text-sm text-gray-600 dark:text-gray-400">Yes</span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Primary / other field groups -->
                        <div
                            v-else-if="group.filteredFields?.length"
                            class="grid gap-4 sm:grid-cols-12"
                        >
                            <template v-for="field in group.filteredFields" :key="field.key">
                                <div
                                    v-if="field && isFieldVisible(field)"
                                    :class="getFieldColSpan(field)"
                                >
                                    <label
                                        :for="getFieldId(field.key)"
                                        class="mb-2 block text-sm font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ getFieldLabel(field.key) }}
                                        <span v-if="isFieldRequired(field)" class="text-red-500">*</span>
                                    </label>

                                    <div v-if="!isEditMode" class="text-sm text-gray-900 dark:text-white">
                                        <span v-if="field.key === 'description'" class="whitespace-pre-wrap">
                                            {{ getFieldValue(field.key) || '—' }}
                                        </span>
                                        <span v-else-if="getFieldType(field.key) === 'boolean' || getFieldType(field.key) === 'checkbox'">
                                            {{ getFieldValue(field.key) == 1 || getFieldValue(field.key) === true ? 'Yes' : 'No' }}
                                        </span>
                                        <span v-else-if="getFieldType(field.key) === 'currency'">
                                            {{
                                                getFieldValue(field.key) != null && getFieldValue(field.key) !== ''
                                                    ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(getFieldValue(field.key))
                                                    : '—'
                                            }}
                                        </span>
                                        <span v-else>{{ getFieldValue(field.key) || '—' }}</span>
                                    </div>

                                    <div v-else>
                                        <textarea
                                            v-if="getFieldType(field.key) === 'textarea'"
                                            :id="getFieldId(field.key)"
                                            v-model="form[field.key]"
                                            rows="4"
                                            class="input-style w-full"
                                            :placeholder="getFieldDefinition(field.key).placeholder"
                                        />
                                        <label
                                            v-else-if="getFieldType(field.key) === 'boolean' || getFieldType(field.key) === 'checkbox'"
                                            :for="getFieldId(field.key)"
                                            class="flex cursor-pointer items-center gap-2"
                                        >
                                            <input type="hidden" :name="field.key" :value="0">
                                            <input
                                                :id="getFieldId(field.key)"
                                                v-model="form[field.key]"
                                                type="checkbox"
                                                :true-value="1"
                                                :false-value="0"
                                                :disabled="isFieldDisabled(field.key)"
                                                class="h-4 w-4 rounded border-gray-300 text-primary-600"
                                            >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ getFieldLabel(field.key) }}</span>
                                        </label>
                                        <CurrencyInput
                                            v-else-if="getFieldType(field.key) === 'currency'"
                                            :id="getFieldId(field.key)"
                                            v-model="form[field.key]"
                                            :required="isFieldRequired(field)"
                                            :disabled="isFieldDisabled(field.key)"
                                        />
                                        <input
                                            v-else
                                            :id="getFieldId(field.key)"
                                            v-model="form[field.key]"
                                            type="text"
                                            class="input-style w-full"
                                            :required="isFieldRequired(field)"
                                            :disabled="isFieldDisabled(field.key)"
                                            :placeholder="getFieldDefinition(field.key).placeholder"
                                        >
                                        <p
                                            v-if="getFieldDefinition(field.key).help"
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ getFieldDefinition(field.key).help }}
                                        </p>
                                        <p
                                            v-if="form.errors[field.key]"
                                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                                        >
                                            {{ form.errors[field.key] }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>
            </div>
        </div>

        <div
            v-if="showActions"
            :class="compact
                ? 'mt-6 flex flex-wrap items-center gap-3'
                : 'mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-6 dark:border-gray-700'"
        >
            <button
                type="submit"
                :disabled="isProcessing"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ isProcessing ? 'Saving…' : (isCreateMode ? 'Create variant' : 'Save variant') }}
            </button>
            <button
                type="button"
                :disabled="isProcessing"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                @click="handleCancel"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
