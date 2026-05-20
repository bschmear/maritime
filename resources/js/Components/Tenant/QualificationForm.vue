<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

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
        default: 'create',
        validator: (v) => ['create', 'edit'].includes(v),
    },
});

const emit = defineEmits(['saved', 'cancelled']);

const resolvedFieldsSchema = computed(() => {
    const fs = props.fieldsSchema;
    if (fs?.fields && typeof fs.fields === 'object' && !Array.isArray(fs.fields)) {
        return fs.fields;
    }
    return fs || {};
});

const pseudoRecord = computed(() =>
    props.record ?? (Object.keys(props.initialData).length > 0 ? props.initialData : null),
);

const leadLocked = computed(() => !!(props.initialData?.lead_id && props.mode === 'create'));

const getEnumOptions = (fieldKey) => {
    const fieldDef = resolvedFieldsSchema.value[fieldKey];
    if (fieldDef?.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
};

const getEnumLabel = (fieldKey, value) => {
    const opts = getEnumOptions(fieldKey);
    const opt = opts.find((o) => o.id === value || o.value === value);
    return opt ? (opt.name ?? opt.label) : (value ?? '—');
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return String(value);
    }
};

const SKIP_ON_FORM = new Set(['createdby_id', 'qualified_at', 'converted_at', 'created_at', 'updated_at']);

const buildInitialFormData = () => {
    const data = {};

    Object.keys(resolvedFieldsSchema.value).forEach((key) => {
        if (SKIP_ON_FORM.has(key)) {
            return;
        }
        const field = resolvedFieldsSchema.value[key];
        if (field?.disabled && !props.record) {
            return;
        }
        if (props.record && props.record[key] !== undefined && props.record[key] !== null) {
            data[key] = props.record[key];
        } else if (props.initialData[key] !== undefined && props.initialData[key] !== null) {
            data[key] = props.initialData[key];
        } else if (field?.default !== undefined && field?.default !== null && field.default !== '') {
            data[key] = field.default;
        } else if (field?.type === 'boolean') {
            data[key] = false;
        } else if (field?.type === 'select') {
            const opts = getEnumOptions(key);
            data[key] = opts.length > 0 ? (opts[0].id ?? opts[0].value ?? null) : null;
        } else if (field?.type === 'record') {
            data[key] = null;
        } else if (field?.type === 'number') {
            data[key] = null;
        } else {
            data[key] = '';
        }
    });

    if (props.initialData.lead_id) {
        data.lead_id = props.initialData.lead_id;
    }
    if (props.initialData.user_id) {
        data.user_id = props.initialData.user_id;
    }

    return data;
};

const form = useForm(buildInitialFormData());

watch(
    () => props.initialData?.lead,
    (lead) => {
        if (!lead || props.mode !== 'create') return;
        if (props.initialData.budget_range != null) {
            form.budget_range = props.initialData.budget_range;
        }
        if (props.initialData.purchase_timeline != null) {
            form.purchase_timeline = props.initialData.purchase_timeline;
        }
    },
    { immediate: true },
);

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('qualifications.update', props.record.id), {
            onSuccess: () => emit('saved'),
            onError: (errors) => console.error('Update failed:', errors),
        });
    } else {
        form.post(route('qualifications.store'), {
            onSuccess: () => emit('saved'),
            onError: (errors) => console.error('Create failed:', errors),
        });
    }
};

const handleCancel = () => emit('cancelled');

const fieldLabel = (key) => resolvedFieldsSchema.value[key]?.label ?? key;
const fieldRequired = (key) => resolvedFieldsSchema.value[key]?.required === true;
const fieldHelp = (key) => resolvedFieldsSchema.value[key]?.help ?? null;
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Main column -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Header card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">
                                        {{ mode === 'edit' ? 'EDIT QUALIFICATION' : 'NEW QUALIFICATION' }}
                                    </h1>
                                    <p class="text-primary-100 text-md mt-1">
                                        {{
                                            mode === 'edit'
                                                ? 'Update qualification details'
                                                : 'Capture lead requirements and buying intent'
                                        }}
                                    </p>
                                </div>
                                <div v-if="record?.display_name || record?.id" class="text-right">
                                    <div class="text-primary-200 text-sm font-medium">Qualification</div>
                                    <div class="text-white text-xl font-mono">
                                        {{ record.display_name || `QLF-${record.id}` }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Lead & assignment | Status & timeline -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Lead &amp; assignment
                                    </h3>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('lead_id') }}
                                            <span v-if="fieldRequired('lead_id')" class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="qualification_lead_id"
                                            :field="resolvedFieldsSchema.lead_id"
                                            v-model="form.lead_id"
                                            :record="pseudoRecord"
                                            field-key="lead_id"
                                            :disabled="leadLocked"
                                        />
                                        <p v-if="leadLocked" class="mt-1 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                            <span class="material-icons text-base" aria-hidden="true">lock</span>
                                            Set from the selected lead
                                        </p>
                                        <p v-if="form.errors.lead_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.lead_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('user_id') }}
                                            <span v-if="fieldRequired('user_id')" class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="qualification_user_id"
                                            :field="resolvedFieldsSchema.user_id"
                                            v-model="form.user_id"
                                            :record="pseudoRecord"
                                            field-key="user_id"
                                        />
                                        <p v-if="form.errors.user_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.user_id }}
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Qualification details
                                    </h3>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('status') }}
                                            <span v-if="fieldRequired('status')" class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.status" class="input-style w-full">
                                            <option
                                                v-for="opt in getEnumOptions('status')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.status }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('intended_use') }}
                                            <span v-if="fieldRequired('intended_use')" class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.intended_use" class="input-style w-full">
                                            <option
                                                v-for="opt in getEnumOptions('intended_use')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.intended_use" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.intended_use }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('ownership_type') }}
                                        </label>
                                        <select v-model="form.ownership_type" class="input-style w-full">
                                            <option :value="null">—</option>
                                            <option
                                                v-for="opt in getEnumOptions('ownership_type')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.ownership_type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.ownership_type }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('purchase_timeline') }}
                                        </label>
                                        <select v-model="form.purchase_timeline" class="input-style w-full">
                                            <option :value="null">—</option>
                                            <option
                                                v-for="opt in getEnumOptions('purchase_timeline')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.purchase_timeline" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.purchase_timeline }}
                                        </p>
                                    </div>

                                    <div v-if="resolvedFieldsSchema.lead_source">
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('lead_source') }}
                                        </label>
                                        <select v-model="form.lead_source" class="input-style w-full">
                                            <option :value="null">—</option>
                                            <option
                                                v-for="opt in getEnumOptions('lead_source')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.lead_source" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.lead_source }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Product requirements -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Product requirements
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('desired_brand') }}
                                            <span v-if="fieldRequired('desired_brand')" class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            id="qualification_desired_brand"
                                            :field="resolvedFieldsSchema.desired_brand"
                                            v-model="form.desired_brand"
                                            :record="pseudoRecord"
                                            field-key="desired_brand"
                                        />
                                        <p v-if="form.errors.desired_brand" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.desired_brand }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('desired_model') }}
                                        </label>
                                        <input
                                            v-model="form.desired_model"
                                            type="text"
                                            class="input-style w-full"
                                            :placeholder="fieldLabel('desired_model')"
                                        />
                                        <p v-if="form.errors.desired_model" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.desired_model }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('preferred_length') }}
                                        </label>
                                        <input
                                            v-model="form.preferred_length"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="input-style w-full"
                                            placeholder="ft"
                                        />
                                        <p v-if="form.errors.preferred_length" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.preferred_length }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('max_weight') }}
                                        </label>
                                        <input
                                            v-model="form.max_weight"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="input-style w-full"
                                            placeholder="lbs"
                                        />
                                        <p v-if="form.errors.max_weight" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.max_weight }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('budget_range') }}
                                            <span v-if="fieldRequired('budget_range')" class="text-red-500">*</span>
                                        </label>
                                        <select v-model="form.budget_range" class="input-style w-full">
                                            <option
                                                v-for="opt in getEnumOptions('budget_range')"
                                                :key="String(opt.id ?? opt.value)"
                                                :value="opt.id ?? opt.value"
                                            >
                                                {{ opt.name ?? opt.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.budget_range" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.budget_range }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <input
                                            v-model="form.needs_engine"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                        />
                                        <span class="text-md text-gray-700 dark:text-gray-300">{{ fieldLabel('needs_engine') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <input
                                            v-model="form.needs_trailer"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                        />
                                        <span class="text-md text-gray-700 dark:text-gray-300">{{ fieldLabel('needs_trailer') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <input
                                            v-model="form.requires_delivery"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                        />
                                        <span class="text-md text-gray-700 dark:text-gray-300">{{ fieldLabel('requires_delivery') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Delivery -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Delivery
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-1">
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('delivery_location') }}
                                        </label>
                                        <input v-model="form.delivery_location" type="text" class="input-style w-full" />
                                        <p v-if="form.errors.delivery_location" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.delivery_location }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('delivery_state') }}
                                        </label>
                                        <input v-model="form.delivery_state" type="text" class="input-style w-full" />
                                        <p v-if="form.errors.delivery_state" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.delivery_state }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ fieldLabel('delivery_country') }}
                                        </label>
                                        <input v-model="form.delivery_country" type="text" class="input-style w-full" />
                                        <p v-if="form.errors.delivery_country" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.delivery_country }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ fieldLabel('customer_notes') }}
                                    </label>
                                    <p v-if="fieldHelp('customer_notes')" class="text-sm text-gray-500 dark:text-gray-400 mb-1.5">
                                        {{ fieldHelp('customer_notes') }}
                                    </p>
                                    <textarea
                                        v-model="form.customer_notes"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-md focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Notes visible to the customer..."
                                    />
                                    <p v-if="form.errors.customer_notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.customer_notes }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-md font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        {{ fieldLabel('internal_notes') }}
                                    </label>
                                    <p v-if="fieldHelp('internal_notes')" class="text-sm text-gray-500 dark:text-gray-400 mb-1.5">
                                        {{ fieldHelp('internal_notes') }}
                                    </p>
                                    <textarea
                                        v-model="form.internal_notes"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-md focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                        placeholder="Internal team notes..."
                                    />
                                    <p v-if="form.errors.internal_notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.internal_notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="sticky top-[145px] space-y-6">
                        <!-- Actions -->
                        <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                            <div class="flex justify-between items-center px-5 py-4 bg-gray-700 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-md font-semibold text-white">Actions</span>
                            </div>

                            <div class="p-5 space-y-3">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-md font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                                >
                                    <span v-if="form.processing" class="material-icons text-lg animate-spin" aria-hidden="true">sync</span>
                                    <span v-else class="material-icons text-lg" aria-hidden="true">check</span>
                                    {{ form.processing ? 'Saving…' : (mode === 'edit' ? 'Save Changes' : 'Create Qualification') }}
                                </button>

                                <button
                                    type="button"
                                    @click="handleCancel"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                                <span class="text-md font-semibold text-white">Summary</span>
                            </div>
                            <dl class="p-5 space-y-3 text-md">
                                <div v-if="record?.display_name" class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Qualification</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white text-right">
                                        {{ record.display_name }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white text-right">
                                        {{ getEnumLabel('status', form.status) }}
                                    </dd>
                                </div>
                                <div v-if="form.budget_range != null" class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Budget</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white text-right">
                                        {{ getEnumLabel('budget_range', form.budget_range) }}
                                    </dd>
                                </div>
                                <div v-if="mode === 'edit' && record?.qualified_at" class="flex justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                    <dt class="text-gray-500 dark:text-gray-400">Qualified at</dt>
                                    <dd class="text-gray-900 dark:text-white text-right text-sm">
                                        {{ formatDateTime(record.qualified_at) }}
                                    </dd>
                                </div>
                                <div v-if="mode === 'edit' && record?.converted_at" class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Converted at</dt>
                                    <dd class="text-gray-900 dark:text-white text-right text-sm">
                                        {{ formatDateTime(record.converted_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
