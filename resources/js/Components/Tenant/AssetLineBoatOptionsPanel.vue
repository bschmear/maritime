<script setup>
import { computed, ref } from 'vue';
import AssetOptionRadioChoices from '@/Components/Tenant/AssetOptionRadioChoices.vue';
import GlobalAssetOptionsModal from '@/Components/Tenant/GlobalAssetOptionsModal.vue';
import {
    addGlobalOptionToLine,
    allAvailableLineOptionIds,
    ensureCustomerOfferedOptionIds,
    isCustomerOfferedOptionId,
    lineOptionsForDisplay,
    removeGlobalOptionFromLine,
    toggleCustomerOfferedOptionId,
} from '@/Utils/assetLineBoatOptions.js';
import {
    clearAssetOptionSingle,
    hasAssetOptionAnySelection,
    isAssetOptionSelected,
    isAssetOptionToggleOn,
    setAssetOptionSingle,
    toggleAssetOptionMulti,
    toggleAssetOptionToggle,
} from '@/Utils/assetOptionSelections.js';

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true },
    assignedOptions: { type: Array, default: () => [] },
    globalOptions: { type: Array, default: () => [] },
    formatPrice: { type: Function, required: true },
    editable: { type: Boolean, default: true },
    showFillModeToggle: { type: Boolean, default: true },
    inputNamePrefix: { type: String, default: 'line' },
    /** Key on item for selections: asset_option_selections or selected_asset_options */
    selectionsKey: { type: String, default: 'asset_option_selections' },
    /** When using selected_asset_options, build full row metadata (deal line child rows). */
    formatSelectionMeta: { type: Function, default: null },
    /** Show checklist for which options appear on customer forms (opportunity feature request). */
    showCustomerOfferCuration: { type: Boolean, default: false },
});

const showGlobalModal = ref(false);

const displayOptions = computed(() =>
    lineOptionsForDisplay(props.assignedOptions, props.globalOptions, props.item.added_global_option_ids),
);

const assignedIdSet = computed(() => new Set((props.assignedOptions ?? []).map((o) => Number(o.option_id))));

const hasGlobalOptionsAvailable = computed(() => (props.globalOptions ?? []).length > 0);

const showPanel = computed(
    () =>
        displayOptions.value.length > 0 ||
        hasGlobalOptionsAvailable.value ||
        (props.item[props.selectionsKey] ?? []).length > 0,
);

const isStaffMode = computed(() => (props.item.asset_options_fill_mode || 'staff') !== 'customer');

function isGlobalOnLine(optionId) {
    return !assignedIdSet.value.has(Number(optionId));
}

function setFillMode(mode) {
    props.item.asset_options_fill_mode = mode;
    if (mode === 'customer') {
        ensureCustomerOfferedOptionIds(
            props.item,
            allAvailableLineOptionIds(props.assignedOptions, props.globalOptions, props.item.added_global_option_ids),
        );
    }
}

function openGlobalModal() {
    showGlobalModal.value = true;
}

function onGlobalAdd(opt) {
    addGlobalOptionToLine(props.item, opt.option_id, !isStaffMode.value);
    showGlobalModal.value = false;
}

function removeGlobal(opt) {
    removeGlobalOptionFromLine(props.item, opt.option_id, props.selectionsKey, clearAssetOptionSingle);
}

function excludeOptionIdsForModal() {
    return displayOptions.value.map((o) => Number(o.option_id));
}

// Selection helpers — use asset_option_selections shape; TransactionForm maps via selectionsKey
function selections(item) {
    if (!item[props.selectionsKey]) {
        item[props.selectionsKey] = [];
    }
    return item[props.selectionsKey];
}

function isSelected(optionId, valueId) {
    if (props.selectionsKey === 'selected_asset_options') {
        return selections(props.item).some(
            (s) => Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId),
        );
    }
    return isAssetOptionSelected(props.item, optionId, valueId);
}

function hasAnySelection(optionId) {
    if (props.selectionsKey === 'selected_asset_options') {
        return selections(props.item).some((s) => Number(s.option_id) === Number(optionId));
    }
    return hasAssetOptionAnySelection(props.item, optionId);
}

function buildSelection(optionId, valueId, taxable = true) {
    if (typeof props.formatSelectionMeta === 'function') {
        return props.formatSelectionMeta(optionId, valueId, taxable);
    }
    return { option_id: optionId, option_value_id: valueId };
}

function onToggleMulti(optionId, valueId, checked) {
    if (props.selectionsKey === 'selected_asset_options') {
        const rest = selections(props.item).filter(
            (s) => !(Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId)),
        );
        props.item[props.selectionsKey] = checked
            ? [...rest, buildSelection(optionId, valueId)]
            : rest;
        return;
    }
    toggleAssetOptionMulti(props.item, optionId, valueId, checked);
}

function onSetSingle(optionId, valueId) {
    if (props.selectionsKey === 'selected_asset_options') {
        const rest = selections(props.item).filter((s) => Number(s.option_id) !== Number(optionId));
        props.item[props.selectionsKey] = [...rest, buildSelection(optionId, valueId, true)];
        return;
    }
    setAssetOptionSingle(props.item, optionId, valueId);
}

function onClearSingle(optionId) {
    if (props.selectionsKey === 'selected_asset_options') {
        props.item[props.selectionsKey] = selections(props.item).filter((s) => Number(s.option_id) !== Number(optionId));
        return;
    }
    clearAssetOptionSingle(props.item, optionId);
}

function onToggleToggle(opt, checked) {
    if (props.selectionsKey === 'selected_asset_options') {
        const valueId = opt.values?.[0]?.id;
        if (valueId == null) return;
        if (checked) {
            onSetSingle(opt.option_id, valueId);
        } else {
            onClearSingle(opt.option_id);
        }
        return;
    }
    toggleAssetOptionToggle(props.item, opt, checked);
}

function toggleOffered(optionId, event) {
    toggleCustomerOfferedOptionId(props.item, optionId, event.target.checked);
}

defineExpose({ showPanel, displayOptions });
</script>

<template>
    <template v-if="showPanel">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Boat options</div>
            <div v-if="showFillModeToggle && editable" class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 p-0.5 bg-white dark:bg-gray-800 shadow-sm"
                    role="group"
                    aria-label="Who selects boat options"
                >
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="
                            isStaffMode
                                ? 'bg-primary-600 text-white'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        "
                        @click="setFillMode('staff')"
                    >
                        Staff selects here
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="
                            !isStaffMode
                                ? 'bg-primary-600 text-white'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        "
                        @click="setFillMode('customer')"
                    >
                        Email customer
                    </button>
                </div>
                <button
                    v-if="isStaffMode && hasGlobalOptionsAvailable"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="openGlobalModal"
                >
                    <span class="material-icons text-base">add</span>
                    Add global option
                </button>
            </div>
            <button
                v-else-if="editable && isStaffMode && hasGlobalOptionsAvailable"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="openGlobalModal"
            >
                <span class="material-icons text-base">add</span>
                Add global option
            </button>
        </div>

        <template v-if="isStaffMode">
            <div v-for="opt in displayOptions" :key="opt.option_id" class="mb-4 last:mb-0">
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ opt.name }}
                        <span v-if="opt.is_global" class="ml-1 text-xs font-normal text-sky-600 dark:text-sky-400">(global)</span>
                        <span v-if="opt.is_required" class="text-red-500">*</span>
                    </div>
                    <button
                        v-if="editable && isGlobalOnLine(opt.option_id)"
                        type="button"
                        class="text-xs text-red-600 hover:underline dark:text-red-400"
                        @click="removeGlobal(opt)"
                    >
                        Remove
                    </button>
                </div>
                <div v-if="opt.input_type === 'multi_select'" class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                    <label
                        v-for="v in opt.values"
                        :key="v.id"
                        class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(opt.option_id, v.id)"
                            :disabled="!editable"
                            @change="onToggleMulti(opt.option_id, v.id, $event.target.checked)"
                        />
                        <span>{{ v.label }}</span>
                        <span class="text-gray-500 tabular-nums">{{ formatPrice(v.price) }}</span>
                    </label>
                </div>
                <div v-else-if="opt.input_type === 'toggle'" class="mt-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            :checked="selectionsKey === 'selected_asset_options' ? isSelected(opt.option_id, opt.values?.[0]?.id) : isAssetOptionToggleOn(item, opt)"
                            :disabled="!editable"
                            @change="onToggleToggle(opt, $event.target.checked)"
                        />
                        <span>Yes</span>
                        <span v-if="opt.values?.[0]?.price" class="text-gray-500 tabular-nums">
                            {{ formatPrice(opt.values[0].price) }}
                        </span>
                    </label>
                </div>
                <AssetOptionRadioChoices
                    v-else
                    :opt="opt"
                    :input-name="`${inputNamePrefix}-ao-${index}-${opt.option_id}`"
                    :format-price="formatPrice"
                    :is-selected="(valueId) => isSelected(opt.option_id, valueId)"
                    :has-any-selection="() => hasAnySelection(opt.option_id)"
                    @select="(valueId) => onSetSingle(opt.option_id, valueId)"
                    @clear="onClearSingle(opt.option_id)"
                />
            </div>
            <p v-if="!displayOptions.length && hasGlobalOptionsAvailable" class="text-sm text-gray-500 dark:text-gray-400">
                No options on this line yet. Use <strong>Add global option</strong> or assign catalog options to this model.
            </p>
            <fieldset
                v-if="showCustomerOfferCuration && displayOptions.length"
                class="mt-4 space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-600"
            >
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Offer on customer feature request
                </legend>
                <label
                    v-for="opt in displayOptions"
                    :key="`offer-fr-${opt.option_id}`"
                    class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-200"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300"
                        :checked="isCustomerOfferedOptionId(item, opt.option_id)"
                        :disabled="!editable"
                        @change="toggleOffered(opt.option_id, $event)"
                    />
                    <span>
                        {{ opt.name }}
                        <span v-if="opt.is_global" class="text-xs text-sky-600 dark:text-sky-400">(global)</span>
                    </span>
                </label>
            </fieldset>
        </template>

        <template v-else>
            <div v-if="editable && hasGlobalOptionsAvailable" class="mb-4 flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="openGlobalModal"
                >
                    <span class="material-icons text-base">add</span>
                    Add global option
                </button>
            </div>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Choose which options the customer will fill on the secure link. Save, then use <strong>Email boat options</strong> on the estimate.
            </p>
            <fieldset v-if="displayOptions.length" class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Offer to customer</legend>
                <label
                    v-for="opt in displayOptions"
                    :key="`offer-${opt.option_id}`"
                    class="flex cursor-pointer items-start gap-3 text-sm text-gray-800 dark:text-gray-200"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300"
                        :checked="isCustomerOfferedOptionId(item, opt.option_id)"
                        :disabled="!editable"
                        @change="toggleOffered(opt.option_id, $event)"
                    />
                    <span>
                        {{ opt.name }}
                        <span v-if="opt.is_global" class="text-xs text-sky-600 dark:text-sky-400">(global)</span>
                        <span v-if="opt.is_required" class="text-red-500">*</span>
                    </span>
                </label>
            </fieldset>
            <p v-else class="rounded-lg border border-dashed border-amber-200 dark:border-amber-700 bg-amber-50/60 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
                Add options to this line first (catalog-assigned options appear automatically; use Add global option for extras).
            </p>
        </template>

        <GlobalAssetOptionsModal
            :show="showGlobalModal"
            :global-options="globalOptions"
            :exclude-option-ids="excludeOptionIdsForModal()"
            @close="showGlobalModal = false"
            @add="onGlobalAdd"
        />
    </template>
</template>
