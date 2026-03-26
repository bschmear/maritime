<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    record: { type: Object, default: null },
    availableSpecs: { type: Array, default: () => [] },
    mode: { type: String, default: 'view' },
    assetType: { type: [Number, String], default: null },
});

const isEditMode = computed(() => props.mode === 'edit' || props.mode === 'create');

const existingValues = computed(() => {
    const map = {};
    if (props.record && Array.isArray(props.record.spec_values)) {
        props.record.spec_values.forEach(sv => {
            map[sv.asset_spec_definition_id] = sv;
        });
    }
    return map;
});

const liveSpecs = ref([]);
const localValues = ref({});

const applySpecs = (specs) => {
    const newLocalValues = {};
    specs.forEach(spec => {
        const existing = existingValues.value[spec.id];
        newLocalValues[spec.id] = existing
            ? {
                value_number:  existing.value_number  ?? null,
                value_text:    existing.value_text    ?? null,
                value_boolean: existing.value_boolean ?? false,
                unit:          existing.unit          ?? spec.unit ?? null,
            }
            : {
                value_number:  null,
                value_text:    null,
                value_boolean: false,
                unit:          spec.unit ?? null,
            };
    });
    liveSpecs.value  = specs;
    localValues.value = newLocalValues;
};

watch(() => props.availableSpecs, (val) => applySpecs([...(val ?? [])]), { immediate: true });

const hasInitialisedType = ref(false);
watch(
    () => props.assetType,
    async (newType, oldType) => {
        if (!newType) return;
        if (!hasInitialisedType.value) { hasInitialisedType.value = true; return; }
        if (newType === oldType) return;
        try {
            const response = await axios.get(route('asset-specs.index'), {
                params: { asset_type: newType },
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            applySpecs(response.data?.specs ?? []);
        } catch { /* keep existing specs on error */ }
    }
);

watch(() => props.record, () => applySpecs([...liveSpecs.value]), { deep: true });

const getDisplayValue = (spec) => {
    const sv = existingValues.value[spec.id];
    if (!sv) return null;
    if (spec.type === 'number')  return sv.value_number ?? null;
    if (spec.type === 'boolean') return sv.value_boolean;
    if (spec.type === 'select' || spec.type === 'text') return sv.value_text ?? null;
    return null;
};

const getDisplayUnit = (spec) => {
    const sv = existingValues.value[spec.id];
    return (sv && sv.unit) ? sv.unit : (spec.unit || null);
};

const groupedSpecSections = computed(() => {
    const buckets = new Map();
    liveSpecs.value.forEach((spec) => {
        const gid = spec.group_id ?? '__none__';
        if (!buckets.has(gid)) {
            buckets.set(gid, {
                key: String(gid),
                label: spec.group?.name || 'Other',
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

// ── Public API ───────────────────────────────────────────────────
// Returns the specs array ready to be merged into the main form payload.
const buildSpecsPayload = () => liveSpecs.value.map(spec => {
    const val = localValues.value[spec.id] || {};
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

defineExpose({ buildSpecsPayload });
</script>

<template>
    <div class="p-4 sm:p-5">
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

        <div v-if="liveSpecs.length === 0" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            No specifications available for this asset type.
            <Link :href="route('asset-specs.index')" class="ml-1 font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                Add specifications
            </Link>
        </div>

        <div v-else class="space-y-6">
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
                                    <span :class="getDisplayValue(spec) ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'">
                                        {{ getDisplayValue(spec) ? 'Yes' : 'No' }}
                                    </span>
                                </template>
                                <template v-else-if="getDisplayValue(spec) !== null && getDisplayValue(spec) !== ''">
                                    {{ getDisplayValue(spec) }}
                                    <span v-if="getDisplayUnit(spec)" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ getDisplayUnit(spec) }}
                                    </span>
                                </template>
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </p>
                        </template>

                        <!-- Edit mode -->
                        <template v-else-if="localValues[spec.id]">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ spec.label }}
                                <span v-if="spec.is_required" class="text-red-500">*</span>
                                <span v-if="spec.is_required" class="ml-1 text-gray-400">
                                    <span class="material-icons text-[13px] align-middle">lock</span>
                                </span>
                            </label>

                            <div v-if="spec.type === 'number'" class="flex items-center gap-2">
                                <input
                                    v-model="localValues[spec.id].value_number"
                                    type="number"
                                    :step="spec.step || 'any'"
                                    :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                />
                                <span v-if="getDisplayUnit(spec)" class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ getDisplayUnit(spec) }}
                                </span>
                            </div>

                            <input
                                v-else-if="spec.type === 'text'"
                                v-model="localValues[spec.id].value_text"
                                type="text"
                                :placeholder="`Enter ${spec.label.toLowerCase()}`"
                                class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            />

                            <select
                                v-else-if="spec.type === 'select'"
                                v-model="localValues[spec.id].value_text"
                                class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">Select {{ spec.label.toLowerCase() }}</option>
                                <option v-for="option in (spec.options || [])" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>

                            <label v-else-if="spec.type === 'boolean'" class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="localValues[spec.id].value_boolean"
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
</template>