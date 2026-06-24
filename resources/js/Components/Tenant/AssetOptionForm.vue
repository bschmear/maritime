<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    fieldError: { type: Function, required: true },
    /** Options from enum: `[{ id, name }]` */
    inputTypeOptions: { type: Array, required: true },
});

const showsChoiceValues = computed(() =>
    ['select', 'multi_select'].includes(props.form.input_type),
);

const showsColorValues = computed(() => props.form.input_type === 'color');

const showsValuesSection = computed(() => showsChoiceValues.value || showsColorValues.value);

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') {
        return '#000000';
    }
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) {
        return h.toLowerCase();
    }
    if (/^[0-9a-fA-F]{6}$/.test(h)) {
        return `#${h.toLowerCase()}`;
    }

    return '#000000';
}

function addChoiceRow() {
    const next = [...(props.form.values || [])];
    const i = next.length;
    next.push({
        id: null,
        label: '',
        value: '',
        color_hex: null,
        cost: '',
        price: '',
        sort_order: i * 10,
    });
    props.form.values = next;
}

function addColorRow() {
    const next = [...(props.form.values || [])];
    const i = next.length;
    next.push({
        id: null,
        label: '',
        value: '',
        color_hex: '#2563eb',
        cost: '',
        price: '',
        sort_order: i * 10,
    });
    props.form.values = next;
}

function removeRow(index) {
    const next = [...(props.form.values || [])];
    next.splice(index, 1);
    props.form.values = next;
}

function ensureToggleValueRow() {
    if (props.form.input_type !== 'toggle') {
        return;
    }

    if (!props.form.values?.length) {
        props.form.values = [{
            id: null,
            label: 'On',
            value: 'on',
            color_hex: null,
            cost: '',
            price: '',
            sort_order: 0,
        }];

        return;
    }

    const row = props.form.values[0];
    props.form.values = [{
        ...row,
        label: 'On',
        value: 'on',
    }];
}

watch(
    () => props.form.input_type,
    (t) => {
        if (t === 'toggle') {
            props.form.allow_multiple = false;
            ensureToggleValueRow();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
            <h3 class="mb-6 text-base font-semibold text-gray-900 dark:text-white">Option definition</h3>
            <div class="grid gap-6 sm:grid-cols-12">
                <div class="sm:col-span-12">
                    <label for="asset-opt-name" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="asset-opt-name"
                        v-model="form.name"
                        type="text"
                        required
                        maxlength="255"
                        class="input-style w-full"
                        autocomplete="off"
                    />
                    <p v-if="fieldError('name')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('name') }}
                    </p>
                </div>

                <div class="sm:col-span-12 md:col-span-6">
                    <label for="asset-opt-input-type" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Input type <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="asset-opt-input-type"
                        v-model="form.input_type"
                        required
                        class="input-style w-full"
                        :class="{ 'ring-2 ring-red-500': fieldError('input_type') }"
                    >
                        <option v-for="opt in inputTypeOptions" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                    <p v-if="fieldError('input_type')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('input_type') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-6 sm:col-span-12 md:col-span-6 md:pt-8">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                        <input v-model="form.is_required" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        Required
                    </label>
                    <label
                        v-if="form.input_type !== 'toggle'"
                        class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-200"
                    >
                        <input v-model="form.allow_multiple" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        Allow multiple values
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                        <input v-model="form.active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        Active
                    </label>
                </div>

                <div class="sm:col-span-6">
                    <label for="asset-opt-min-select" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Minimum selections
                    </label>
                    <input
                        id="asset-opt-min-select"
                        v-model.number="form.min_select"
                        type="number"
                        min="0"
                        class="input-style w-full"
                    />
                    <p v-if="fieldError('min_select')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('min_select') }}
                    </p>
                </div>

                <div class="sm:col-span-6">
                    <label for="asset-opt-max-select" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Maximum selections
                    </label>
                    <input
                        id="asset-opt-max-select"
                        v-model.number="form.max_select"
                        type="number"
                        min="0"
                        class="input-style w-full"
                    />
                    <p v-if="fieldError('max_select')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('max_select') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="showsValuesSection"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ showsColorValues ? 'Colors' : 'Choices' }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="showsColorValues">
                            Add each available color. The native picker sets the hex value.
                        </template>
                        <template v-else>
                            Add each choice shown to customers. Internal value is optional (e.g. for integrations).
                        </template>
                    </p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium text-gray-800 dark:text-gray-100">Cost</span>
                        is your internal cost (not shown to customers).
                        <span class="font-medium text-gray-800 dark:text-gray-100">Price</span>
                        is the additional amount the customer pays when they select this option.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                    @click="showsColorValues ? addColorRow() : addChoiceRow()"
                >
                    <span class="material-icons mr-1 text-[18px]">add</span>
                    {{ showsColorValues ? 'Add color' : 'Add choice' }}
                </button>
            </div>

            <p v-if="fieldError('values')" class="mb-4 text-sm text-red-600 dark:text-red-500">{{ fieldError('values') }}</p>

            <!-- Choice rows (single / multi select) -->
            <div v-if="showsChoiceValues" class="space-y-4">
                <div
                    v-for="(row, index) in form.values"
                    :key="row.id ?? `new-${index}`"
                    class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <div class="min-w-0 flex-1">
                            <label class="mb-1 block text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Label <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="row.label"
                                type="text"
                                class="input-style w-full"
                                placeholder="e.g. Navy blue"
                                maxlength="255"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="mb-1 block text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Internal value
                            </label>
                            <input
                                v-model="row.value"
                                type="text"
                                class="input-style w-full"
                                placeholder="Optional slug / SKU"
                                maxlength="255"
                            />
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 lg:shrink-0 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                            @click="removeRow(index)"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                                Cost <span class="font-normal text-gray-400">(internal)</span>
                            </label>
                            <input
                                v-model="row.cost"
                                type="text"
                                inputmode="decimal"
                                class="input-style w-full"
                                placeholder="0.00"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                                Price <span class="font-normal text-gray-400">(add-on for customer)</span>
                            </label>
                            <input
                                v-model="row.price"
                                type="text"
                                inputmode="decimal"
                                class="input-style w-full"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                </div>
                <p v-if="!form.values?.length" class="rounded-lg border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-600">
                    No choices yet. Click “Add choice”.
                </p>
            </div>

            <!-- Color rows -->
            <div v-else-if="showsColorValues" class="space-y-4">
                <div
                    v-for="(row, index) in form.values"
                    :key="row.id ?? `new-${index}`"
                    class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex items-center gap-3">
                            <label class="sr-only" :for="`asset-opt-color-${index}`">Color</label>
                            <input
                                :id="`asset-opt-color-${index}`"
                                :value="normalizeHex(row.color_hex)"
                                type="color"
                                class="h-11 w-14 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-1 dark:border-gray-600"
                                @input="(e) => { row.color_hex = e.target.value }"
                            />
                            <span class="font-mono text-sm text-gray-600 dark:text-gray-300">{{ normalizeHex(row.color_hex) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="mb-1 block text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Label <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="row.label"
                                type="text"
                                class="input-style w-full"
                                placeholder="e.g. Gelcoat white"
                                maxlength="255"
                            />
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 sm:shrink-0 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                            @click="removeRow(index)"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                                Cost <span class="font-normal text-gray-400">(internal)</span>
                            </label>
                            <input
                                v-model="row.cost"
                                type="text"
                                inputmode="decimal"
                                class="input-style w-full"
                                placeholder="0.00"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                                Price <span class="font-normal text-gray-400">(add-on for customer)</span>
                            </label>
                            <input
                                v-model="row.price"
                                type="text"
                                inputmode="decimal"
                                class="input-style w-full"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                </div>
                <p v-if="!form.values?.length" class="rounded-lg border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-600">
                    No colors yet. Click “Add color”.
                </p>
            </div>
        </div>

        <div
            v-else-if="form.input_type === 'toggle'"
            class="rounded-lg border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40"
        >
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Customers see a yes/no choice. Set the internal cost and customer add-on price when the option is included.
            </p>
            <div v-if="form.values?.[0]" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                        Cost <span class="font-normal text-gray-400">(internal)</span>
                    </label>
                    <input
                        v-model="form.values[0].cost"
                        type="text"
                        inputmode="decimal"
                        class="input-style w-full"
                        placeholder="0.00"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500 dark:text-gray-400">
                        Price <span class="font-normal text-gray-400">(add-on for customer)</span>
                    </label>
                    <input
                        v-model="form.values[0].price"
                        type="text"
                        inputmode="decimal"
                        class="input-style w-full"
                        placeholder="0.00"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
