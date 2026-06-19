<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import EnumButtonGroup from '@/Components/Tenant/FormComponents/EnumButtonGroup.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import NumberInput from '@/Components/Tenant/FormComponents/Number.vue';
import { computed, ref } from 'vue';
import { buildRecordShowUrl } from '@/Utils/resourceRoutes.js';
import { useFormValidationToast } from '@/composables/useFormValidationToast';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    prefill: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'financings' },
    recordTitle: { type: String, default: 'Financing' },
    enumOptions: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        default: 'view',
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
});

const emit = defineEmits(['saved', 'cancelled', 'created']);

const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);
const isProcessing = ref(false);

const isView = computed(() => props.mode === 'view');
const isCreate = computed(() => props.mode === 'create');

const formGroups = computed(() => {
    const form = props.schema?.form ?? {};
    const out = [];
    for (const [key, group] of Object.entries(form)) {
        if (!group || typeof group !== 'object') continue;
        const fields = (group.fields ?? [])
            .map((f) => (f?.key ? { ...f } : null))
            .filter((f) => f && !f.hidden);
        if (fields.length) {
            out.push({ key, label: group.label || key, fields });
        }
    }
    return out;
});

const fieldDef = (key) => props.fieldsSchema[key] || {};

const enumOptionsFor = (key) => {
    const en = fieldDef(key).enum;
    return en && props.enumOptions[en] ? props.enumOptions[en] : [];
};

const isEnumSelectField = (key) => fieldDef(key).type === 'select' && !!fieldDef(key).enum;

const relatedRecord = (key) => {
    const def = fieldDef(key);
    const rel = def.relationship;
    if (!rel || !props.record) {
        return null;
    }

    const snake = rel.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, '');

    return props.record[rel] ?? props.record[snake] ?? null;
};

const recordFilterBy = (key) => {
    const def = fieldDef(key);
    return def.record_filter_field ?? def.filterby ?? (def.filter?.vendor_type != null ? 'vendor_type' : null);
};

const recordFilterValue = (key) => {
    const def = fieldDef(key);
    if (def.filter?.vendor_type != null) {
        return def.filter.vendor_type;
    }
    return def.filterby ? null : null;
};

const initial = computed(() => {
    const src = props.record ?? props.prefill ?? {};
    const keys = Object.keys(props.fieldsSchema);
    const data = {};
    keys.forEach((k) => {
        data[k] = src[k] ?? fieldDef(k).default ?? null;
    });
    return data;
});

const form = useForm({ ...initial.value });

const inputClass =
    'block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';

const labelClass = 'mb-2 block text-sm font-medium text-gray-900 dark:text-white';

const formatCurrency = (v) =>
    v != null && v !== ''
        ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';

const displayValue = (key, value) => {
    const def = fieldDef(key);
    if (def.type === 'currency') return formatCurrency(value);
    if (def.type === 'record') {
        const related = relatedRecord(key);
        if (related) {
            return related.display_name ?? value;
        }
    }
    if (def.type === 'select') {
        const hit = enumOptionsFor(key).find((o) => o.value === value || String(o.id) === String(value));
        return hit?.name ?? value ?? '—';
    }
    return value ?? '—';
};

const recordShowUrl = (key) => {
    const def = fieldDef(key);
    const related = relatedRecord(key);
    if (!related?.id || !def.typeDomain) return null;
    return buildRecordShowUrl(def.typeDomain, related.id);
};

const submit = () => {
    isProcessing.value = true;
    const opts = {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false; },
        onSuccess: (page) => {
            const id = page.props?.record?.id ?? props.record?.id;
            emit('saved', { recordId: id });
            if (isCreate.value) emit('created', { recordId: id });
        },
        ...validationSubmitOptions(),
    };
    if (isCreate.value) {
        form.post(route(`${props.recordType}.store`), opts);
    } else {
        form.put(route(`${props.recordType}.update`, props.record.id), opts);
    }
};

const cancel = () => emit('cancelled');
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section
            v-for="group in formGroups"
            :key="group.key"
            class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ group.label }}
            </h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="field in group.fields"
                    :key="field.key"
                    :class="[
                        fieldDef(field.key).type === 'textarea' || isEnumSelectField(field.key) ? 'sm:col-span-2' : '',
                    ]"
                >
                    <label :class="labelClass">{{ fieldDef(field.key).label || field.key }}</label>

                    <template v-if="isView">
                        <p class="text-sm text-gray-900 dark:text-gray-100">
                            <Link
                                v-if="recordShowUrl(field.key)"
                                :href="recordShowUrl(field.key)"
                                class="text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ displayValue(field.key, record?.[field.key]) }}
                            </Link>
                            <span v-else>{{ displayValue(field.key, record?.[field.key]) }}</span>
                        </p>
                    </template>

                    <template v-else-if="fieldDef(field.key).type === 'record'">
                        <RecordSelect
                            :id="`fin-${field.key}`"
                            :field="fieldDef(field.key)"
                            v-model="form[field.key]"
                            :record="record"
                            :field-key="field.key"
                            :filter-by="recordFilterBy(field.key)"
                            :filter-value="recordFilterValue(field.key)"
                            :disabled="field.readOnly"
                        />
                    </template>

                    <template v-else-if="isEnumSelectField(field.key)">
                        <EnumButtonGroup
                            :id="`fin-${field.key}`"
                            v-model="form[field.key]"
                            :options="enumOptionsFor(field.key)"
                            :disabled="field.readOnly"
                        />
                    </template>

                    <template v-else-if="fieldDef(field.key).type === 'currency'">
                        <CurrencyInput v-model="form[field.key]" :disabled="field.readOnly" />
                    </template>

                    <template v-else-if="fieldDef(field.key).type === 'date'">
                        <DateInput v-model="form[field.key]" :disabled="field.readOnly" />
                    </template>

                    <template v-else-if="fieldDef(field.key).type === 'number'">
                        <NumberInput v-model="form[field.key]" :disabled="field.readOnly" />
                    </template>

                    <template v-else-if="fieldDef(field.key).type === 'textarea'">
                        <textarea v-model="form[field.key]" rows="3" :class="inputClass" :readonly="field.readOnly" />
                    </template>

                    <template v-else>
                        <input
                            v-model="form[field.key]"
                            type="text"
                            :class="inputClass"
                            :readonly="field.readOnly"
                        />
                    </template>

                    <p v-if="form.errors[field.key]" class="mt-1 text-sm text-red-600">{{ form.errors[field.key] }}</p>
                </div>
            </div>
        </section>

        <div v-if="!isView" class="flex justify-end gap-2">
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200"
                @click="cancel"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                :disabled="isProcessing || form.processing"
            >
                {{ isCreate ? 'Create' : 'Save' }}
            </button>
        </div>
    </form>
</template>
