<script setup>
import { computed, watch } from 'vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import EnumButtonGroup from '@/Components/Tenant/FormComponents/EnumButtonGroup.vue';

const categoryRecordField = Object.freeze({
    type: 'record',
    typeDomain: 'AssetOptionCategory',
    label: 'Category',
    create: true,
});

const props = defineProps({
    form: { type: Object, required: true },
    fieldError: { type: Function, required: true },
    /** Options from enum: `[{ id, name }]` */
    inputTypeOptions: { type: Array, required: true },
    /** Parent record on edit (for category relationship label). */
    categoryRecord: { type: Object, default: null },
});

const showsChoiceValues = computed(() =>
    ['select', 'multi_select'].includes(props.form.input_type),
);

const showsColorValues = computed(() => props.form.input_type === 'color');

const showsValuesSection = computed(() => showsChoiceValues.value || showsColorValues.value);

function normalizeHex(hex) {
    if (!hex || typeof hex !== 'string') return '#000000';
    const h = hex.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(h)) return h.toLowerCase();
    if (/^[0-9a-fA-F]{6}$/.test(h)) return `#${h.toLowerCase()}`;
    return '#000000';
}

function addChoiceRow() {
    const next = [...(props.form.values || [])];
    next.push({ id: null, label: '', value: '', color_hex: null, cost: '', price: '', sort_order: next.length * 10 });
    props.form.values = next;
}

function addColorRow() {
    const next = [...(props.form.values || [])];
    next.push({ id: null, label: '', value: '', color_hex: '#2563eb', cost: '', price: '', sort_order: next.length * 10 });
    props.form.values = next;
}

function removeRow(index) {
    const next = [...(props.form.values || [])];
    next.splice(index, 1);
    props.form.values = next;
}

function ensureToggleValueRow() {
    if (props.form.input_type !== 'toggle') return;
    if (!props.form.values?.length) {
        props.form.values = [{ id: null, label: 'On', value: 'on', color_hex: null, cost: '', price: '', sort_order: 0 }];
        return;
    }
    props.form.values = [{ ...props.form.values[0], label: 'On', value: 'on' }];
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
    <div class="space-y-0 divide-y divide-gray-100 dark:divide-gray-700/60">

        <!-- ─── Option Definition ─────────────────────────────────────── -->
        <section class="grid grid-cols-1 gap-8 py-8 first:pt-0 lg:grid-cols-[280px_1fr]">

            <!-- Left: section label -->
            <div class="lg:pt-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Option definition</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    Set the name, type, and basic behaviour of this option. The input type controls how customers interact with it.
                </p>
            </div>

            <!-- Right: fields -->
            <div class="space-y-5">

                <!-- Name -->
                <div>
                    <label for="asset-opt-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
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
                        placeholder="e.g. Paint colour"
                    />
                    <p v-if="fieldError('name')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('name') }}</p>
                </div>

                <!-- Category + Input type -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="asset-opt-category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <RecordSelect
                            id="asset-opt-category"
                            v-model="form.category_id"
                            field-key="category_id"
                            :field="categoryRecordField"
                            :record="categoryRecord"
                        />
                        <p v-if="fieldError('category_id')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('category_id') }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="asset-opt-input-type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Input type <span class="text-red-500">*</span>
                        </label>
                        <EnumButtonGroup
                            id="asset-opt-input-type"
                            v-model="form.input_type"
                            :options="inputTypeOptions"
                        />
                        <p v-if="fieldError('input_type')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('input_type') }}</p>
                    </div>
                </div>

                <!-- Min / Max selections -->
                <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="asset-opt-min-select" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Min selections</label>
                        <input id="asset-opt-min-select" v-model.number="form.min_select" type="number" min="0" class="input-style w-full" />
                        <p v-if="fieldError('min_select')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('min_select') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="asset-opt-max-select" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Max selections</label>
                        <input id="asset-opt-max-select" v-model.number="form.max_select" type="number" min="0" class="input-style w-full" />
                        <p v-if="fieldError('max_select')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('max_select') }}</p>
                    </div>
                </div>

                <!-- Toggles -->
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 divide-y divide-gray-100 dark:divide-gray-700/60">
                    <div class="flex flex-wrap gap-x-6 gap-y-3 px-4 py-3.5">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input v-model="form.is_required" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                            Required
                        </label>
                        <label v-if="form.input_type !== 'toggle'" class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input v-model="form.allow_multiple" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                            Allow multiple values
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input v-model="form.active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                            Active
                        </label>
                    </div>
                    <label class="flex cursor-pointer items-center gap-3 px-4 py-3.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input v-model="form.is_global" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        <span>
                            Global option
                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">Available on any line via Add Global Option; no catalog assignment needed.</span>
                        </span>
                    </label>
                </div>

            </div>
        </section>

        <!-- ─── Values / Colors / Toggle ────────────────────────────────── -->
        <section
            v-if="showsValuesSection || form.input_type === 'toggle'"
            class="grid grid-cols-1 gap-8 py-8 lg:grid-cols-[280px_1fr]"
        >
            <!-- Left -->
            <div class="lg:pt-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ showsColorValues ? 'Colors' : form.input_type === 'toggle' ? 'Pricing' : 'Choices' }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    <template v-if="showsColorValues">Add each available color. The native picker sets the hex value.</template>
                    <template v-else-if="form.input_type === 'toggle'">Set the internal cost and customer add-on price when this option is selected.</template>
                    <template v-else>Add each choice shown to customers. Internal value is optional (e.g. for integrations).</template>
                </p>
                <p v-if="showsValuesSection" class="mt-3 text-xs text-gray-500 dark:text-gray-400 leading-relaxed space-y-1">
                    <span class="block"><span class="font-semibold text-gray-700 dark:text-gray-300">Cost</span> — your internal cost, not shown to customers.</span>
                    <span class="block"><span class="font-semibold text-gray-700 dark:text-gray-300">Price</span> — additional amount the customer pays when selecting this option.</span>
                </p>
            </div>

            <!-- Right -->
            <div>

                <!-- Toggle pricing -->
                <div v-if="form.input_type === 'toggle' && form.values?.[0]" class="flex flex-wrap gap-4">
                    <div class="w-36">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Cost <span class="text-xs font-normal text-gray-400">(internal)</span>
                        </label>
                        <input v-model="form.values[0].cost" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                    </div>
                    <div class="w-36">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Price <span class="text-xs font-normal text-gray-400">(add-on)</span>
                        </label>
                        <input v-model="form.values[0].price" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                    </div>
                </div>

                <!-- Choice / Color rows header -->
                <div v-if="showsValuesSection" class="mb-4 flex items-center justify-between gap-3">
                    <p v-if="fieldError('values')" class="text-xs text-red-600 dark:text-red-400">{{ fieldError('values') }}</p>
                    <span v-else />
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                        @click="showsColorValues ? addColorRow() : addChoiceRow()"
                    >
                        <span class="material-icons text-[16px]">add</span>
                        {{ showsColorValues ? 'Add color' : 'Add choice' }}
                    </button>
                </div>

                <!-- Choice rows -->
                <div v-if="showsChoiceValues" class="space-y-3">
                    <div
                        v-for="(row, index) in form.values"
                        :key="row.id ?? `new-${index}`"
                        class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <!-- Row number badge -->
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Choice {{ index + 1 }}</span>
                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                @click="removeRow(index)"
                            >Remove</button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Label <span class="text-red-500">*</span>
                                </label>
                                <input v-model="row.label" type="text" class="input-style w-full" placeholder="e.g. Navy blue" maxlength="255" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Internal value</label>
                                <input v-model="row.value" type="text" class="input-style w-full" placeholder="Optional slug / SKU" maxlength="255" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Cost <span class="font-normal normal-case text-gray-400">(internal)</span>
                                </label>
                                <input v-model="row.cost" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Price <span class="font-normal normal-case text-gray-400">(add-on)</span>
                                </label>
                                <input v-model="row.price" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.values?.length" class="rounded-lg border border-dashed border-gray-200 py-10 text-center text-sm text-gray-400 dark:border-gray-600 dark:text-gray-500">
                        No choices yet — click "Add choice" to get started.
                    </p>
                </div>

                <!-- Color rows -->
                <div v-else-if="showsColorValues" class="space-y-3">
                    <div
                        v-for="(row, index) in form.values"
                        :key="row.id ?? `new-${index}`"
                        class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Color {{ index + 1 }}</span>
                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                @click="removeRow(index)"
                            >Remove</button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex items-end gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sr-only" :for="`asset-opt-color-${index}`">Color picker</label>
                                    <input
                                        :id="`asset-opt-color-${index}`"
                                        :value="normalizeHex(row.color_hex)"
                                        type="color"
                                        class="h-10 w-12 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-gray-600"
                                        @input="(e) => { row.color_hex = e.target.value }"
                                    />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Label <span class="text-red-500">*</span>
                                    </label>
                                    <input v-model="row.label" type="text" class="input-style w-full" placeholder="e.g. Gelcoat white" maxlength="255" />
                                </div>
                            </div>
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Cost <span class="font-normal normal-case text-gray-400">(internal)</span>
                                    </label>
                                    <input v-model="row.cost" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Price <span class="font-normal normal-case text-gray-400">(add-on)</span>
                                    </label>
                                    <input v-model="row.price" type="text" inputmode="decimal" class="input-style w-full text-right" placeholder="0.00" />
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 font-mono text-xs text-gray-400 dark:text-gray-500">{{ normalizeHex(row.color_hex) }}</p>
                    </div>
                    <p v-if="!form.values?.length" class="rounded-lg border border-dashed border-gray-200 py-10 text-center text-sm text-gray-400 dark:border-gray-600 dark:text-gray-500">
                        No colors yet — click "Add color" to get started.
                    </p>
                </div>

            </div>
        </section>

    </div>
</template>