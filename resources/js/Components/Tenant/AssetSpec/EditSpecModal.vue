<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    addSelectOptionRow,
    normalizeSelectOptionsForEditor,
    reindexSelectOptions,
    removeSelectOptionRow,
} from '@/Utils/assetSpecSelectOptions.js';

const props = defineProps({
    spec: {
        type: Object,
        required: true,
    },
    specGroups: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const normalizeAssetTypes = (raw) => {
    if (!raw || !Array.isArray(raw)) {
        return [];
    }

    return [...new Set(raw.map((t) => Number(t)).filter((n) => !Number.isNaN(n)))];
};

const buildFormState = (spec) => ({
    label: spec?.label ?? '',
    group_id: spec?.group_id != null && spec?.group_id !== '' ? spec.group_id : '',
    type: spec?.type ?? 'text',
    unit: spec?.unit ?? '',
    unit_imperial: spec?.unit_imperial ?? '',
    unit_metric: spec?.unit_metric ?? '',
    use_metric: Boolean(spec?.use_metric),
    options: normalizeSelectOptionsForEditor(spec?.options),
    is_filterable: Boolean(spec?.is_filterable),
    is_visible: spec?.is_visible !== false,
    is_required: Boolean(spec?.is_required),
    show_on_table: Boolean(spec?.show_on_table),
    position: spec?.position ?? 0,
    asset_types: normalizeAssetTypes(spec?.asset_types),
});

const form = useForm(buildFormState(props.spec));

const showUnitFields = ref(
    Boolean(props.spec?.unit_imperial || props.spec?.unit_metric),
);
const showOptionsFields = ref(props.spec?.type === 'select');

watch(
    () => props.spec,
    (spec) => {
        if (!spec) {
            return;
        }
        const state = buildFormState(spec);
        form.defaults(state);
        form.reset();
        showUnitFields.value = Boolean(spec.unit_imperial || spec.unit_metric);
        showOptionsFields.value = spec.type === 'select';
    },
    { deep: true },
);

const typeOptions = [
    { value: 'text', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'select', label: 'Select/Dropdown' },
    { value: 'boolean', label: 'Yes/No (Boolean)' },
];

const assetTypeOptions = [
    { value: 1, label: 'Boat' },
    { value: 3, label: 'Trailer' },
    { value: 2, label: 'Engine' },
    { value: 4, label: 'Other' },
];

const isRequiredSpec = computed(() => Boolean(props.spec?.is_required));

const addOption = () => {
    form.options = addSelectOptionRow(form.options);
};

const removeOption = (index) => {
    form.options = removeSelectOptionRow(form.options, index);
};

const handleTypeChange = () => {
    showOptionsFields.value = form.type === 'select';
    if (showOptionsFields.value && form.options.length === 0) {
        addOption();
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        group_id: data.group_id === '' || data.group_id == null ? null : data.group_id,
        options: data.type === 'select' ? reindexSelectOptions(data.options) : data.options,
    })).put(route('asset-specs.update', { assetSpec: props.spec.id }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
};
</script>

<template>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div
            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"
            @click="emit('close')"
        />

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Edit spec
                        </h3>
                        <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ spec.key }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click="emit('close')"
                    >
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <form class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.label"
                            type="text"
                            required
                            class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                        />
                        <p v-if="form.errors.label" class="mt-1 text-sm text-red-600">
                            {{ form.errors.label }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.type"
                            required
                            class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                            @change="handleTypeChange"
                        >
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Group
                        </label>
                        <select
                            v-model="form.group_id"
                            class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                        >
                            <option value="">None</option>
                            <option v-for="g in specGroups" :key="g.id" :value="g.id">
                                {{ g.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="form.type === 'number'">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Unit
                            </label>
                            <button
                                type="button"
                                class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                @click="showUnitFields = !showUnitFields"
                            >
                                {{ showUnitFields ? 'Hide' : 'Show' }} imperial/metric
                            </button>
                        </div>
                        <input
                            v-model="form.unit"
                            type="text"
                            placeholder="e.g., ft, lb, hp"
                            class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-400 dark:focus:ring-primary-400"
                        />

                        <div
                            v-if="showUnitFields"
                            class="mt-3 space-y-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50"
                        >
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Imperial unit
                                </label>
                                <input
                                    v-model="form.unit_imperial"
                                    type="text"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Metric unit
                                </label>
                                <input
                                    v-model="form.unit_metric"
                                    type="text"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <label class="flex items-center">
                                <input
                                    v-model="form.use_metric"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Prefer metric display
                                </span>
                            </label>
                        </div>
                    </div>

                    <div v-if="showOptionsFields">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Options <span class="text-red-500">*</span>
                        </label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            Labels only — stored values are reassigned as 1, 2, 3… in this order when you save.
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="(option, index) in form.options"
                                :key="index"
                                class="flex gap-2"
                            >
                                <input
                                    v-model="option.label"
                                    type="text"
                                    placeholder="Label (e.g., Short shaft)"
                                    required
                                    class="block flex-1 rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                                <button
                                    type="button"
                                    class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                    @click="removeOption(index)"
                                >
                                    <span class="material-icons text-[20px]">delete</span>
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-2 inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            @click="addOption"
                        >
                            <span class="material-icons text-[16px]">add</span>
                            Add option
                        </button>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Asset types
                        </label>
                        <div class="space-y-2">
                            <label
                                v-for="option in assetTypeOptions"
                                :key="option.value"
                                class="flex items-center"
                            >
                                <input
                                    v-model="form.asset_types"
                                    :value="option.value"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    {{ option.label }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <label class="flex items-center">
                            <input
                                v-model="form.is_required"
                                type="checkbox"
                                :disabled="isRequiredSpec"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Required field
                                <span v-if="isRequiredSpec" class="text-xs text-gray-500">(system default)</span>
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.is_filterable"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Marketplace filterable
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.is_visible"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Always visible in UI
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.show_on_table"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Show on asset Variants table
                            </span>
                        </label>
                    </div>
                </form>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-700">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
