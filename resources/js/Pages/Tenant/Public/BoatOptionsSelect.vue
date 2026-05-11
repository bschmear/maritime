<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    /** 'estimate' | 'opportunity' */
    context: { type: String, default: 'estimate' },
    formTitle: { type: String, default: 'Boat options' },
    /** Estimate / opportunity heading */
    recordLabel: { type: String, default: '' },
    estimate: { type: Object, default: null },
    lineItem: { type: Object, required: true },
    /** Asset + variant summary for the hero card */
    assetSummary: { type: Object, default: null },
    account: { type: Object, default: null },
    logoUrl: { type: String, default: null },
    options: { type: Array, default: () => [] },
    /** Opportunity-only: add-on rows from staff selections */
    addonsOffered: { type: Array, default: () => [] },
    includeAddonsInForm: { type: Boolean, default: false },
    submitUrl: { type: String, default: null },
    alreadyCompleted: { type: Boolean, default: false },
});

const displayLabel = computed(() => props.recordLabel || props.estimate?.display_name || '');

const headline = computed(() => {
    if (props.context === 'opportunity') {
        return props.includeAddonsInForm
            ? 'Please select asset options and add-ons'
            : 'Please select asset options';
    }
    return props.formTitle;
});

const formatCurrency = (value) =>
    value != null
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '$0.00';

/** Single-select (radio) choices */
const singleSelections = ref([]);

/** Multi-select (checkbox) choices */
const multiSelections = ref([]);

watch(
    () => props.options,
    () => {
        singleSelections.value = [];
        multiSelections.value = [];
    },
    { immediate: true },
);

/** addon offered rows — checkbox + qty */
const addonIncluded = reactive({});
const addonQty = reactive({});

/** Stable key for offered add-on row (catalog id, or legacy pivot row id). */
function addonOfferedKey(a) {
    if (a?.catalog_addon_id != null) return String(a.catalog_addon_id);
    if (a?.opportunity_asset_addon_id != null) return `oaa:${a.opportunity_asset_addon_id}`;
    return '';
}

watch(
    () => props.addonsOffered,
    (rows) => {
        Object.keys(addonIncluded).forEach((k) => delete addonIncluded[k]);
        Object.keys(addonQty).forEach((k) => delete addonQty[k]);
        (rows || []).forEach((a) => {
            const key = addonOfferedKey(a);
            if (!key) return;
            addonIncluded[key] = true;
            addonQty[key] = a.quantity_default ?? 1;
        });
    },
    { immediate: true },
);

const isMultiSelected = (optionId, valueId) =>
    multiSelections.value.some(
        (s) => Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId),
    );

const toggleMulti = (optionId, valueId, checked) => {
    const rest = multiSelections.value.filter(
        (s) => !(Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId)),
    );
    if (checked) {
        multiSelections.value = [...rest, { option_id: optionId, option_value_id: valueId }];
    } else {
        multiSelections.value = rest;
    }
};

const setSingle = (optionId, valueId) => {
    const rest = singleSelections.value.filter((s) => Number(s.option_id) !== Number(optionId));
    singleSelections.value = [...rest, { option_id: optionId, option_value_id: valueId }];
};

const form = useForm({
    selections: [],
    addon_selections: [],
    signer_name: '',
    confirm: false,
});

const submitError = ref('');

const buildPayloadSelections = () => {
    const singles = singleSelections.value.filter((s) => s.option_value_id != null);
    return [...singles, ...multiSelections.value];
};

const buildAddonSelections = () => {
    if (!props.includeAddonsInForm || props.context !== 'opportunity') {
        return [];
    }
    const out = [];
    for (const a of props.addonsOffered || []) {
        const key = addonOfferedKey(a);
        if (!key || !addonIncluded[key]) continue;
        const row = {
            quantity: Math.max(1, parseInt(String(addonQty[key] ?? 1), 10) || 1),
        };
        if (a.catalog_addon_id != null) {
            row.catalog_addon_id = a.catalog_addon_id;
        } else if (a.opportunity_asset_addon_id != null) {
            row.opportunity_asset_addon_id = a.opportunity_asset_addon_id;
        }
        out.push(row);
    }
    return out;
};

const submit = () => {
    submitError.value = '';
    form.selections = buildPayloadSelections();
    form.addon_selections = buildAddonSelections();
    form.post(props.submitUrl, {
        preserveScroll: true,
        onError: (errors) => {
            const first = Object.values(errors || {})[0];
            submitError.value = Array.isArray(first) ? first[0] : String(first || 'Could not save selections.');
        },
    });
};

const displayOptions = computed(() => props.options || []);

const assetHeroLines = computed(() => {
    const s = props.assetSummary;
    if (!s) return [];
    const lines = [];
    if (s.make_name || s.year) {
        lines.push([s.year, s.make_name].filter(Boolean).join(' · ') || null);
    }
    if (s.variant_label) {
        lines.push(s.variant_label);
    }
    return lines.filter(Boolean);
});
</script>

<template>
    <Head :title="`${formTitle} — ${displayLabel}`" />

    <div class="min-h-screen bg-gray-100 py-10 px-4">
        <div class="max-w-2xl mx-auto">
            <div v-if="logoUrl" class="flex justify-center mb-6">
                <img :src="logoUrl" alt="" class="max-h-14 object-contain" />
            </div>

            <div v-if="alreadyCompleted" class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-emerald-600 text-white px-6 py-8 text-center">
                    <h1 class="text-2xl font-bold">Submission received</h1>
                    <p class="text-emerald-100 mt-2">{{ displayLabel }} · {{ lineItem.name }}</p>
                </div>
                <div class="px-6 py-8 space-y-2 text-gray-700">
                    <p>Thank you. Your responses were submitted and recorded.</p>
                    <p v-if="lineItem.signer_name" class="text-sm text-gray-500">
                        Signed by <span class="font-medium text-gray-800">{{ lineItem.signer_name }}</span>
                        <span v-if="lineItem.completed_at"> on {{ new Date(lineItem.completed_at).toLocaleString() }}</span>.
                    </p>
                </div>
            </div>

            <div v-else class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gray-900 text-white px-6 py-8">
                    <p class="text-primary-200 text-xs font-semibold uppercase tracking-wide">{{ formTitle }}</p>
                    <h1 class="text-2xl font-bold mt-1">{{ headline }}</h1>
                    <!-- <p class="text-gray-200 mt-2 text-sm">{{ displayLabel }}</p> -->

                    <div v-if="assetSummary" class="mt-4 rounded-lg bg-white/10 px-4 py-3 text-left">
                        <div class="text-white font-semibold">{{ assetSummary.display_name || lineItem.name }}</div>
                        <div v-for="(ln, i) in assetHeroLines" :key="i" class="text-sm text-gray-300 mt-0.5">{{ ln }}</div>
                    </div>
                    <p v-else-if="lineItem.name" class="text-white/90 font-medium mt-3">{{ lineItem.name }}</p>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <p class="text-gray-600 text-sm">
                        <template v-if="context === 'opportunity'">
                            Review your boat configuration below. Prices shown are for planning — when finished, enter your name and confirm to submit your feature request.
                        </template>
                        <template v-else>
                            Select the options below. Prices shown will be added to your estimate. When finished, enter your name and confirm — this records your choices and signature for our files.
                        </template>
                    </p>

                    <div v-for="opt in displayOptions" :key="opt.option_id" class="border-b border-gray-100 pb-5 last:border-0">
                        <div class="text-sm font-semibold text-gray-900">
                            {{ opt.name }}
                            <span v-if="opt.is_required" class="text-red-500">*</span>
                        </div>

                        <div v-if="opt.input_type === 'multi_select'" class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                            <label
                                v-for="v in opt.values"
                                :key="v.id"
                                class="inline-flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isMultiSelected(opt.option_id, v.id)"
                                    @change="toggleMulti(opt.option_id, v.id, $event.target.checked)"
                                />
                                <span>{{ v.label }}</span>
                                <span class="text-gray-500 tabular-nums">{{ formatCurrency(v.price) }}</span>
                            </label>
                        </div>
                        <div v-else class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                            <label
                                v-for="v in opt.values"
                                :key="v.id"
                                class="inline-flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="radio"
                                    :name="`opt-${opt.option_id}`"
                                    @change="setSingle(opt.option_id, v.id)"
                                />
                                <span
                                    v-if="v.color_hex"
                                    class="inline-block h-4 w-4 rounded border border-gray-300"
                                    :style="{ backgroundColor: v.color_hex }"
                                />
                                <span>{{ v.label }}</span>
                                <span class="text-gray-500 tabular-nums">{{ formatCurrency(v.price) }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="includeAddonsInForm && addonsOffered.length > 0" class="border-t border-gray-100 pt-5 space-y-3">
                        <h2 class="text-sm font-semibold text-gray-900">Additional Options (Add-ons)</h2>
                        <p class="text-xs text-gray-500">
                            Select the add-ons you want included with this request. Quantities can be adjusted below.
                        </p>
                        <div
                            v-for="a in addonsOffered"
                            :key="addonOfferedKey(a)"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm"
                        >
                            <label class="inline-flex items-center gap-2 font-medium text-gray-800">
                                <input
                                    type="checkbox"
                                    :checked="addonIncluded[addonOfferedKey(a)]"
                                    @change="addonIncluded[addonOfferedKey(a)] = $event.target.checked"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                {{ a.name }}
                            </label>
                            <span class="text-gray-500 tabular-nums">{{ formatCurrency(a.price) }}</span>
                            <label v-if="addonIncluded[addonOfferedKey(a)]" class="ml-auto flex items-center gap-2 text-xs text-gray-600">
                                Qty
                                <input
                                    v-model="addonQty[addonOfferedKey(a)]"
                                    type="number"
                                    min="1"
                                    class="w-16 rounded border border-gray-300 px-2 py-1 text-gray-900"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Full name (sign-off)
                            <input
                                v-model="form.signer_name"
                                type="text"
                                autocomplete="name"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Type your name as authorization"
                            />
                        </label>

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input v-model="form.confirm" type="checkbox" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span>I confirm these selections are correct and authorize {{ account?.name || 'the dealer' }} to record this feature request.</span>
                        </label>
                    </div>

                    <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>
                    <p v-if="form.errors.selections" class="text-sm text-red-600">{{ form.errors.selections }}</p>
                    <p v-if="form.errors.addon_selections" class="text-sm text-red-600">{{ form.errors.addon_selections }}</p>
                    <p v-if="form.errors.signer_name" class="text-sm text-red-600">{{ form.errors.signer_name }}</p>
                    <p v-if="form.errors.confirm" class="text-sm text-red-600">{{ form.errors.confirm }}</p>

                    <button
                        type="button"
                        class="w-full rounded-lg bg-primary-600 px-4 py-3 text-center text-sm font-semibold text-white shadow hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing || !submitUrl"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : context === 'opportunity' ? 'Submit feature request' : 'Submit selections' }}
                    </button>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-8">{{ account?.name }}</p>
        </div>
    </div>
</template>
