<script setup>
import { useForm } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { computed, ref, watch, onUnmounted } from 'vue';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';
import { useFormValidationToast } from '@/composables/useFormValidationToast';
import { useSchemaFormGroups } from '@/composables/useSchemaFormGroups.js';

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    recordType: { type: String, default: 'leads' },
    recordTitle: { type: String, default: 'Lead' },
});

const emit = defineEmits(['saved', 'cancelled']);

const expandedTextareaKey = ref(null);

const expandedTextareaMeta = computed(() => {
    const k = expandedTextareaKey.value;
    if (!k) {
        return null;
    }
    const def = resolvedFieldsSchema.value[k] || {};

    return { key: k, label: def.label || k };
});

const openTextareaExpand = (key) => {
    expandedTextareaKey.value = key;
};

const closeTextareaExpand = () => {
    expandedTextareaKey.value = null;
};

const onExpandKeydown = (e) => {
    if (e.key === 'Escape') {
        closeTextareaExpand();
    }
};

watch(expandedTextareaKey, (k) => {
    if (typeof document === 'undefined') {
        return;
    }
    if (k) {
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onExpandKeydown);
    } else {
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', onExpandKeydown);
    }
});

onUnmounted(() => {
    if (typeof document === 'undefined') {
        return;
    }
    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', onExpandKeydown);
});

const { formGroups } = useSchemaFormGroups(() => props.formSchema);

const formMaxWidthClass = computed(() => {
    const w = props.formSchema?.settings?.max_width;
    if (w === '4xl') {
        return 'max-w-4xl';
    }
    if (w === '5xl') {
        return 'max-w-5xl';
    }
    if (w === '6xl') {
        return 'max-w-6xl';
    }
    if (w === '7xl') {
        return 'max-w-7xl';
    }
    return 'max-w-6xl';
});

const allFieldKeys = computed(() => {
    const set = new Set();
    formGroups.value.forEach((g) => {
        if (!g.is_address) {
            g.fields.forEach((f) => set.add(f.key));
        } else {
            g.fields.forEach((f) => set.add(f.key));
        }
    });
    return [...set];
});

const resolvedFieldsSchema = computed(() => {
    const fs = props.fieldsSchema;
    if (fs?.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
        return fs.fields;
    }
    return fs && typeof fs === 'object' ? fs : {};
});

const fieldDef = (key) => resolvedFieldsSchema.value[key] || {};

const enumOptionsFor = (key) => {
    const en = fieldDef(key).enum;
    return en && props.enumOptions[en] ? props.enumOptions[en] : [];
};

const pseudoRecord = computed(() => props.record ?? (Object.keys(props.initialData || {}).length ? props.initialData : null));

function extractDate(val) {
    if (!val) {
        return '';
    }
    if (typeof val === 'string') {
        return val.split('T')[0];
    }
    return '';
}

function defaultValueForField(key) {
    const def = fieldDef(key);
    const t = def.type || 'text';
    if (t === 'boolean') {
        return false;
    }
    if (t === 'select') {
        return '';
    }
    if (t === 'number' || t === 'rating') {
        return null;
    }
    if (t === 'date') {
        return '';
    }
    if (t === 'json') {
        return '';
    }
    return '';
}

function initialValuesFromProps() {
    const values = {};
    for (const key of allFieldKeys.value) {
        values[key] = defaultValueForField(key);
    }
    if (props.mode === 'create' && (values.status_id === '' || values.status_id == null)) {
        values.status_id = values.status_id ?? 1;
    }
    if (props.initialData && typeof props.initialData === 'object') {
        for (const key of allFieldKeys.value) {
            if (key in props.initialData && props.initialData[key] !== undefined) {
                values[key] = props.initialData[key];
            }
        }
    }
    const r = props.record;
    if (r) {
        for (const key of allFieldKeys.value) {
            const def = fieldDef(key);
            let v = r[key];

            if (key === 'assigned_user_id' && r.assigned_user && typeof r.assigned_user === 'object') {
                v = r.assigned_user.id ?? v;
            }
            if (def.type === 'boolean' || def.type === 'checkbox') {
                values[key] = v === true || v === 1 || v === '1';
                continue;
            }
            if (def.type === 'select' && def.enum) {
                const opts = enumOptionsFor(key);
                if (v !== null && v !== undefined && v !== '') {
                    const hit = opts.find(
                        (o) =>
                            o.id === v ||
                            o.value === v ||
                            String(o.id) === String(v) ||
                            String(o.value) === String(v),
                    );
                    if (hit) {
                        values[key] = hit.id;
                    } else {
                        values[key] = v;
                    }
                }
                continue;
            }
            if (def.type === 'date') {
                values[key] = extractDate(v);
                continue;
            }
            if (def.type === 'json') {
                if (v == null) {
                    values[key] = '';
                } else if (typeof v === 'string') {
                    values[key] = v;
                } else {
                    try {
                        values[key] = JSON.stringify(v, null, 2);
                    } catch {
                        values[key] = '';
                    }
                }
                continue;
            }
            if (v !== null && v !== undefined) {
                values[key] = v;
            }
        }
    }
    return values;
}

const form = useForm(initialValuesFromProps());
const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const fieldIsVisible = (field) => {
    if (!field?.conditional) {
        return true;
    }
    const { key, value } = field.conditional;
    const current = form[key];
    if (value === true) {
        return current === true || current === 1 || current === '1';
    }
    return current == value;
};

const fieldIsDisabled = (field) => !!(field?.disabled || fieldDef(field?.key)?.disabled);

const leadLabel = computed(() => {
    const r = props.record;
    if (!r) {
        return '';
    }
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || `Lead #${r.id}`
    );
});

const headerTitle = computed(() => (props.mode === 'edit' ? 'EDIT LEAD' : 'NEW LEAD'));

const headerSubtitle = computed(() =>
    props.mode === 'edit'
        ? 'Update contact, address, and lead profile details'
        : 'Add a lead with contact info and follow-up preferences',
);

const isFullWidthField = (key) => fieldDef(key).type === 'textarea' || fieldDef(key).type === 'json';

const selectOptionValue = (key, opt) => {
    if (fieldDef(key).type === 'select' && fieldDef(key).enum) {
        return opt.id;
    }
    return opt.value !== undefined && opt.value !== null ? opt.value : opt.id;
};

const handleAddressUpdate = (data) => {
    form.address_line_1 = data.street ?? '';
    form.address_line_2 = data.unit ?? '';
    form.city = data.city ?? '';
    form.state = data.stateCode || data.state || '';
    form.postal_code = data.postalCode ?? '';
    form.country = data.countryCode || data.country || '';
    form.latitude = data.latitude ?? null;
    form.longitude = data.longitude ?? null;
};

const preparePayload = () => {
    const raw = { ...form.data() };
    raw.inactive = !!(raw.inactive === true || raw.inactive === 1 || raw.inactive === '1');
    raw.has_trade_in = !!(raw.has_trade_in === true || raw.has_trade_in === 1 || raw.has_trade_in === '1');
    if (!raw.has_trade_in) {
        raw.trade_in_value = null;
    }
    for (const k of [
        'status_id',
        'source_id',
        'priority_id',
        'budget_range',
        'preferred_contact_method',
        'preferred_contact_time',
        'purchase_timeline',
        'assigned_user_id',
        'trade_in_value',
        'last_contacted_at',
        'next_followup_at',
    ]) {
        if (raw[k] === '' || raw[k] === undefined) {
            raw[k] = null;
        }
    }
    return raw;
};

const submit = () => {
    const url =
        props.mode === 'edit'
            ? route(
                  `${props.recordType}.update`,
                  buildResourceRouteParams(props.recordType, props.record.id),
              )
            : route(`${props.recordType}.store`);

    form.clearErrors();
    const payload = preparePayload();
    Object.keys(payload).forEach((k) => {
        form[k] = payload[k];
    });

    if (props.mode === 'edit') {
        form.put(url, validationSubmitOptions({
            onSuccess: () => emit('saved', {}),
        }));
    } else {
        form.post(url, validationSubmitOptions({
            onSuccess: (page) =>
                emit('saved', {
                    recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
                }),
        }));
    }
};

const handleCancel = () => {
    form.reset();
    emit('cancelled');
};

</script>

<template>
    <div class="mx-auto w-full flex flex-col space-y-6" >
        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8 space-y-6 min-w-0">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div
                            class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ headerTitle }}
                                    </h1>
                                    <p class="text-primary-100 text-sm mt-1">
                                        {{ headerSubtitle }}
                                    </p>
                                </div>
                                <div v-if="record?.id" class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Lead</div>
                                    <div class="text-white text-lg font-mono">
                                        {{ leadLabel || `Lead #${record.id}` }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-8 border-t border-primary-500/20 dark:border-primary-900/40">
                            <template v-for="group in formGroups" :key="group.key">
                                <!-- Address -->
                                <div v-if="group.is_address">
                                    <h3
                                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4"
                                    >
                                        {{ group.label }}
                                    </h3>
                                    <div class="max-w-2xl">
                                        <AddressAutocomplete
                                            :street="form.address_line_1"
                                            :unit="form.address_line_2"
                                            :city="form.city"
                                            :state="form.state"
                                            :state-code="form.state"
                                            :postal-code="form.postal_code"
                                            :country="form.country"
                                            :latitude="form.latitude"
                                            :longitude="form.longitude"
                                            @update="handleAddressUpdate"
                                        />
                                    </div>
                                </div>

                                <div v-else>
                                    <h3
                                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4"
                                    >
                                        {{ group.label }}
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                        <template
                                            v-for="field in group.fields.filter(fieldIsVisible)"
                                            :key="field.key"
                                        >
                                                <!-- text / email / tel / number -->
                                                <div
                                                    v-if="['text', 'email', 'tel', 'number'].includes(fieldDef(field.key).type || 'text')"
                                                    :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                                >
                                                    <label
                                                        :for="`lead-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="field.required" class="text-red-500">*</span>
                                                    </label>
                                                    <input
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        :type="fieldDef(field.key).type === 'email' ? 'email' : fieldDef(field.key).type === 'number' ? 'number' : fieldDef(field.key).type === 'tel' ? 'tel' : 'text'"
                                                        :disabled="fieldIsDisabled(field)"
                                                        class="input-style disabled:opacity-60 disabled:cursor-not-allowed"
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
                                                        :for="`lead-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <input
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        type="date"
                                                        :disabled="fieldIsDisabled(field)"
                                                        class="input-style disabled:opacity-60 disabled:cursor-not-allowed"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- json -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'json'"
                                                    class="md:col-span-2"
                                                >
                                                    <label
                                                        :for="`lead-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }} (JSON array)
                                                    </label>
                                                    <textarea
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        rows="4"
                                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-y"
                                                        placeholder='["tag-one","tag-two"]'
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
                                                    <div class="flex items-start justify-between gap-3 mb-1.5">
                                                        <label
                                                            :for="`lead-${field.key}`"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                        >
                                                            {{ fieldDef(field.key).label || field.key }}
                                                            <span v-if="field.required" class="text-red-500">*</span>
                                                        </label>
                                                        <button
                                                            type="button"
                                                            class="shrink-0 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors"
                                                            title="Expand to edit"
                                                            @click="openTextareaExpand(field.key)"
                                                        >
                                                            <span class="material-icons text-[16px] leading-none">open_in_full</span>
                                                            Expand
                                                        </button>
                                                    </div>
                                                    <textarea
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        rows="4"
                                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-y min-h-[6.5rem]"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- boolean -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'boolean'"
                                                    class="md:col-span-2 flex items-center gap-3"
                                                >
                                                    <input
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        type="checkbox"
                                                        :disabled="fieldIsDisabled(field)"
                                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 disabled:opacity-60"
                                                    />
                                                    <label
                                                        :for="`lead-${field.key}`"
                                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <!-- enum select -->
                                                <div v-else-if="fieldDef(field.key).type === 'select'">
                                                    <label
                                                        :for="`lead-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="field.required" class="text-red-500">*</span>
                                                    </label>
                                                    <select
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        :disabled="fieldIsDisabled(field)"
                                                        class="input-style disabled:opacity-60 disabled:cursor-not-allowed"
                                                    >
                                                        <option value="">—</option>
                                                        <option
                                                            v-for="opt in enumOptionsFor(field.key)"
                                                            :key="`${field.key}-${opt.id}-${opt.value}`"
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

                                                <!-- assigned user -->
                                                <div
                                                    v-else-if="fieldDef(field.key).type === 'record'"
                                                    class="md:col-span-2"
                                                >
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="field.required" class="text-red-500">*</span>
                                                    </label>
                                                    <RecordSelect
                                                        :id="`lead-${field.key}`"
                                                        :field="fieldDef(field.key)"
                                                        v-model="form[field.key]"
                                                        :record="pseudoRecord"
                                                        :field-key="field.key"
                                                    />
                                                    <p
                                                        v-if="form.errors[field.key]"
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                    >
                                                        {{ form.errors[field.key] }}
                                                    </p>
                                                </div>

                                                <div v-else :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''">
                                                    <label
                                                        :for="`lead-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                    </label>
                                                    <input
                                                        :id="`lead-${field.key}`"
                                                        v-model="form[field.key]"
                                                        type="text"
                                                        class="input-style"
                                                    />
                                                </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-[140px]">
                        <div
                            class="flex justify-between items-center px-5 py-4 bg-gray-700 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600"
                        >
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-4 w-4 animate-spin text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                <span v-else class="material-icons text-[18px]">save</span>
                                {{ form.processing ? 'Saving…' : mode === 'edit' ? 'Save changes' : 'Create lead' }}
                            </button>
                            <button
                                type="button"
                                :disabled="form.processing"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 rounded-lg transition-colors"
                                @click="handleCancel"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <Teleport to="body">
            <div
                v-if="expandedTextareaKey && expandedTextareaMeta"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`lead-textarea-expand-title-${expandedTextareaMeta.key}`"
                @click.self="closeTextareaExpand"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h3
                            :id="`lead-textarea-expand-title-${expandedTextareaMeta.key}`"
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ expandedTextareaMeta.label }}
                        </h3>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors"
                            @click="closeTextareaExpand"
                        >
                            <span class="material-icons text-[18px] leading-none">close</span>
                            Done
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto p-5">
                        <textarea
                            v-model="form[expandedTextareaMeta.key]"
                            rows="18"
                            class="min-h-[min(70vh,28rem)] w-full resize-y rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-inner focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
