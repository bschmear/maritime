<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { computed, ref, watch, onUnmounted } from 'vue';
import { buildResourceRouteParams } from '@/utils/resourceRoutes.js';

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    initialData: { type: Object, default: () => ({}) },
    availableSpecs: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit', 'view'].includes(v),
    },
    recordType: { type: String, default: 'contacts' },
    recordTitle: { type: String, default: 'Contact' },
});

const emit = defineEmits(['saved', 'cancelled', 'delete-requested']);

/** Textarea “expand” overlay (single field at a time). */
const expandedTextareaKey = ref(null);

const expandedTextareaMeta = computed(() => {
    const k = expandedTextareaKey.value;
    if (!k) {
        return null;
    }
    const def = props.fieldsSchema[k] || {};

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

const normalizedForm = computed(() => {
    const s = props.formSchema;
    if (s?.form && typeof s.form === 'object') {
        return s.form;
    }
    return s && typeof s === 'object' ? s : {};
});

const formGroups = computed(() => {
    const out = [];
    const form = normalizedForm.value;
    for (const [key, group] of Object.entries(form)) {
        if (!group || typeof group !== 'object' || group.type === 'specs') {
            continue;
        }
        const fields = Array.isArray(group.fields) ? group.fields : [];
        const items = fields
            .map((f) => (f && typeof f === 'object' && f.key ? { ...f, key: f.key } : null))
            .filter(Boolean);
        if (items.length === 0) {
            continue;
        }
        out.push({
            key,
            label: group.label || key,
            fields: items,
        });
    }
    return out;
});

const allFieldKeys = computed(() => {
    const set = new Set();
    formGroups.value.forEach((g) => g.fields.forEach((f) => set.add(f.key)));
    return [...set];
});

const fieldDef = (key) => props.fieldsSchema[key] || {};

const enumOptionsFor = (key) => {
    const en = fieldDef(key).enum;
    return en && props.enumOptions[en] ? props.enumOptions[en] : [];
};

const pseudoRecord = computed(() => props.record ?? (Object.keys(props.initialData || {}).length ? props.initialData : null));

function defaultValueForField(key) {
    const def = fieldDef(key);
    const t = def.type || 'text';
    if (t === 'boolean') {
        return false;
    }
    if (t === 'select') {
        return '';
    }
    return '';
}

function initialValuesFromProps() {
    const values = {};
    for (const key of allFieldKeys.value) {
        values[key] = defaultValueForField(key);
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
            if (key === 'vendor_id' && r.vendor && typeof r.vendor === 'object') {
                v = r.vendor.id ?? v;
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
                        values[key] =
                            key === 'stage_id'
                                ? hit.id
                                : hit.value !== undefined && hit.value !== null
                                  ? hit.value
                                  : hit.id;
                    } else {
                        values[key] = v;
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

const isView = computed(() => props.mode === 'view');

const contactLabel = computed(() => {
    const r = props.record;
    if (!r) {
        return '';
    }
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || (r.id ? `#${r.id}` : '')
    );
});

const headerTitle = computed(() => {
    if (props.mode === 'view') {
        return 'CONTACT';
    }
    if (props.mode === 'edit') {
        return 'EDIT CONTACT';
    }
    return 'NEW CONTACT';
});

const headerSubtitle = computed(() => {
    if (props.mode === 'view') {
        return 'Contact profile and related records';
    }
    if (props.mode === 'edit') {
        return 'Update contact details and preferences';
    }
    return 'Add a new person or company to your directory';
});

const selectOptionValue = (key, opt) => {
    if (key === 'stage_id') {
        return opt.id;
    }
    return opt.value !== undefined && opt.value !== null ? opt.value : opt.id;
};

const isFullWidthField = (key) => fieldDef(key).type === 'textarea';

const submit = () => {
    if (props.mode === 'view') {
        return;
    }
    const url =
        props.mode === 'edit'
            ? route(
                  `${props.recordType}.update`,
                  buildResourceRouteParams(props.recordType, props.record.id),
              )
            : route(`${props.recordType}.store`);

    form.clearErrors();
    form.inactive = !!(form.inactive === true || form.inactive === 1 || form.inactive === '1');
    for (const k of ['assigned_user_id', 'vendor_id', 'stage_id', 'status', 'preferred_contact_method', 'preferred_contact_time', 'type']) {
        if (form[k] === '' || form[k] === undefined) {
            form[k] = null;
        }
    }

    if (props.mode === 'edit') {
        form.put(url, {
            preserveScroll: true,
            onSuccess: () => emit('saved', {}),
        });
    } else {
        form.post(url, {
            preserveScroll: true,
            onSuccess: (page) =>
                emit('saved', {
                    recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
                }),
        });
    }
};

const handleCancel = () => {
    form.reset();
    emit('cancelled');
};
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8 space-y-6">
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
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">
                                        {{ contactLabel || `Contact #${record.id}` }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-8 border-t border-primary-500/20 dark:border-primary-900/40">
                            <template v-for="group in formGroups" :key="group.key">
                                <div>
                                    <h3
                                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4"
                                    >
                                        {{ group.label }}
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                        <template v-for="field in group.fields" :key="field.key">
                                            <!-- text / email / tel -->
                                            <div
                                                v-if="['text', 'email', 'tel'].includes(fieldDef(field.key).type || 'text')"
                                                :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                            >
                                                <label
                                                    :for="`contact-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    :id="`contact-${field.key}`"
                                                    v-model="form[field.key]"
                                                    :type="fieldDef(field.key).type === 'email' ? 'email' : fieldDef(field.key).type === 'tel' ? 'tel' : 'text'"
                                                    :disabled="isView"
                                                    class="input-style"
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
                                                        :for="`contact-${field.key}`"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ fieldDef(field.key).label || field.key }}
                                                        <span v-if="field.required" class="text-red-500">*</span>
                                                    </label>
                                                    <button
                                                        type="button"
                                                        class="shrink-0 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors"
                                                        :title="isView ? 'Expand to read' : 'Expand to edit'"
                                                        @click="openTextareaExpand(field.key)"
                                                    >
                                                        <span class="material-icons text-[16px] leading-none">open_in_full</span>
                                                        Expand
                                                    </button>
                                                </div>
                                                <textarea
                                                    :id="`contact-${field.key}`"
                                                    v-model="form[field.key]"
                                                    rows="4"
                                                    :disabled="isView"
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
                                                    :id="`contact-${field.key}`"
                                                    v-model="form[field.key]"
                                                    type="checkbox"
                                                    :disabled="isView"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                />
                                                <label
                                                    :for="`contact-${field.key}`"
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
                                                    :for="`contact-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <select
                                                    :id="`contact-${field.key}`"
                                                    v-model="form[field.key]"
                                                    :disabled="isView"
                                                    class="input-style"
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

                                            <!-- record relationship -->
                                            <div v-else-if="fieldDef(field.key).type === 'record'" class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <RecordSelect
                                                    :id="`contact-${field.key}`"
                                                    :field="fieldDef(field.key)"
                                                    v-model="form[field.key]"
                                                    :record="pseudoRecord"
                                                    :field-key="field.key"
                                                    :disabled="isView"
                                                />
                                                <p
                                                    v-if="form.errors[field.key]"
                                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                >
                                                    {{ form.errors[field.key] }}
                                                </p>
                                            </div>

                                            <!-- fallback -->
                                            <div v-else :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''">
                                                <label
                                                    :for="`contact-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                </label>
                                                <input
                                                    :id="`contact-${field.key}`"
                                                    v-model="form[field.key]"
                                                    type="text"
                                                    :disabled="isView"
                                                    class="input-style"
                                                />
                                                <p
                                                    v-if="form.errors[field.key]"
                                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                >
                                                    {{ form.errors[field.key] }}
                                                </p>
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
                            <template v-if="isView && record?.id">
                                <Link
                                    :href="route(`${recordType}.edit`, record.id)"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                        />
                                    </svg>
                                    Edit contact
                                </Link>
                                <Link
                                    :href="route(`${recordType}.index`)"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                >
                                    Back to contacts
                                </Link>
                                <button
                                    type="button"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                    @click="emit('delete-requested')"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                    Delete contact
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                                >
                                    <svg
                                        v-if="form.processing"
                                        class="w-4 h-4 animate-spin"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    {{
                                        form.processing
                                            ? 'Saving…'
                                            : mode === 'edit'
                                              ? 'Save changes'
                                              : 'Create contact'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 rounded-lg transition-colors"
                                    @click="handleCancel"
                                >
                                    Cancel
                                </button>
                            </template>
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
                :aria-labelledby="`contact-textarea-expand-title-${expandedTextareaMeta.key}`"
                @click.self="closeTextareaExpand"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                    >
                        <h3
                            :id="`contact-textarea-expand-title-${expandedTextareaMeta.key}`"
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
                            :disabled="isView"
                            class="min-h-[min(70vh,28rem)] w-full resize-y rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-inner focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            :placeholder="isView ? '' : 'Enter notes…'"
                        />
                        <p
                            v-if="form.errors[expandedTextareaMeta.key]"
                            class="mt-2 text-xs text-red-600 dark:text-red-400"
                        >
                            {{ form.errors[expandedTextareaMeta.key] }}
                        </p>
                        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                            Press <kbd class="rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-[10px] dark:border-gray-600 dark:bg-gray-800">Esc</kbd>
                            or click outside to close.
                        </p>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
