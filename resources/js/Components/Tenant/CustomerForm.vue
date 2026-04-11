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
    recordType: { type: String, default: 'customers' },
    recordTitle: { type: String, default: 'Customer' },
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

function normalizedFormObject(formSchema) {
    const s = formSchema;
    if (s?.form && typeof s.form === 'object') {
        return s.form;
    }
    return s && typeof s === 'object' ? s : {};
}

function buildFormGroups(formSchema) {
    const out = [];
    const form = normalizedFormObject(formSchema);
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
}

const initialFormGroups = buildFormGroups(props.formSchema);
const isLinkedContactCreateStatic =
    props.mode === 'create' &&
    props.initialData?.contact_id != null &&
    props.initialData.contact_id !== '';

const keysForInitialForm = (() => {
    const groups = isLinkedContactCreateStatic
        ? initialFormGroups.filter((g) => g.key === 'customer_profile')
        : initialFormGroups;
    const set = new Set();
    for (const g of groups) {
        for (const f of g.fields) {
            set.add(f.key);
        }
    }
    if (isLinkedContactCreateStatic) {
        set.add('contact_id');
    }
    return [...set];
})();

const formGroups = computed(() => buildFormGroups(props.formSchema));

const isLinkedContactCreate = computed(
    () =>
        props.mode === 'create' &&
        props.initialData?.contact_id != null &&
        props.initialData.contact_id !== '',
);

const displayFormGroups = computed(() => {
    if (isLinkedContactCreate.value) {
        return formGroups.value.filter((g) => g.key === 'customer_profile');
    }
    return formGroups.value;
});

const linkedContactSummary = computed(() => {
    if (!isLinkedContactCreate.value) {
        return null;
    }
    const d = props.initialData || {};
    const name =
        (typeof d.display_name === 'string' && d.display_name.trim()) ||
        [d.first_name, d.last_name].filter(Boolean).join(' ').trim() ||
        'Contact';

    const line2 = [d.city, d.state, d.postal_code].filter(Boolean).join(', ');
    const addressLines = [
        [d.address_line_1, d.address_line_2].filter(Boolean).join(', ') || null,
        line2 || null,
        d.country || null,
    ].filter(Boolean);

    return {
        contactId: d.contact_id,
        name,
        email: d.email || null,
        secondaryEmail: d.secondary_email || null,
        phone: d.phone || null,
        mobile: d.mobile || null,
        company: d.company || null,
        title: d.title || null,
        position: d.position || null,
        addressLines,
        website: d.website || null,
        linkedin: d.linkedin || null,
        facebook: d.facebook || null,
        notes: d.notes || null,
    };
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
    for (const key of keysForInitialForm) {
        if (key === 'contact_id') {
            values[key] = props.initialData?.contact_id ?? '';
            continue;
        }
        values[key] = defaultValueForField(key);
    }
    if (props.initialData && typeof props.initialData === 'object') {
        for (const key of keysForInitialForm) {
            if (key === 'contact_id') {
                continue;
            }
            if (key in props.initialData && props.initialData[key] !== undefined) {
                values[key] = props.initialData[key];
            }
        }
    }
    const r = props.record;
    if (r) {
        for (const key of keysForInitialForm) {
            if (key === 'contact_id') {
                continue;
            }
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
                        values[key] = ['stage_id', 'status_id', 'source_id', 'priority_id'].includes(key)
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
    if (props.initialData?.contact_id != null && props.initialData.contact_id !== '') {
        values.contact_id = props.initialData.contact_id;
    }
    return values;
}

const form = useForm(initialValuesFromProps());

const isView = computed(() => props.mode === 'view');

const customerLabel = computed(() => {
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
        return 'CUSTOMER';
    }
    if (props.mode === 'edit') {
        return 'EDIT CUSTOMER';
    }
    return 'NEW CUSTOMER';
});

const headerSubtitle = computed(() => {
    if (props.mode === 'view') {
        return 'Customer profile and related records';
    }
    if (props.mode === 'edit') {
        return 'Update customer and contact details';
    }
    if (isLinkedContactCreate.value) {
        return 'Add a customer profile for this contact — CRM fields below.';
    }
    return 'Create a customer profile and linked contact';
});

const selectOptionValue = (key, opt) => {
    if (['stage_id', 'status_id', 'source_id', 'priority_id'].includes(key)) {
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
    for (const k of [
        'assigned_user_id',
        'status_id',
        'source_id',
        'priority_id',
        'lead_score',
        'budget_min',
        'budget_max',
        'trade_in_value',
        'preferred_contact_method',
        'preferred_contact_time',
        'credit_limit',
        'payment_terms',
        'loyalty_points',
        'last_contacted_at',
        'next_followup_at',
        'contract_start',
        'contract_end',
        'first_purchase_at',
        'last_purchase_at',
    ]) {
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
                                        {{ customerLabel || `Customer #${record.id}` }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-8 border-t border-primary-500/20 dark:border-primary-900/40">
                            <div
                                v-if="linkedContactSummary"
                                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-5 space-y-3"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                        Contact
                                    </h3>
                                    <Link
                                        v-if="linkedContactSummary.contactId"
                                        :href="route('contacts.show', linkedContactSummary.contactId)"
                                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                    >
                                        Open contact record
                                    </Link>
                                </div>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ linkedContactSummary.name }}
                                </p>
                                <dl class="grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <template v-if="linkedContactSummary.company">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Company</dt>
                                            <dd>{{ linkedContactSummary.company }}</dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.email">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Email</dt>
                                            <dd>
                                                <a
                                                    :href="`mailto:${linkedContactSummary.email}`"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    {{ linkedContactSummary.email }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.secondaryEmail">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">
                                                Secondary email
                                            </dt>
                                            <dd>
                                                <a
                                                    :href="`mailto:${linkedContactSummary.secondaryEmail}`"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    {{ linkedContactSummary.secondaryEmail }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.phone">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Phone</dt>
                                            <dd>
                                                <a
                                                    :href="`tel:${linkedContactSummary.phone}`"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    {{ linkedContactSummary.phone }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.mobile">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Mobile</dt>
                                            <dd>
                                                <a
                                                    :href="`tel:${linkedContactSummary.mobile}`"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline"
                                                >
                                                    {{ linkedContactSummary.mobile }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.title || linkedContactSummary.position">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Role</dt>
                                            <dd>
                                                {{
                                                    [linkedContactSummary.title, linkedContactSummary.position]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                }}
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-for="(line, idx) in linkedContactSummary.addressLines" :key="`addr-${idx}`">
                                        <div class="md:col-span-2 text-gray-700 dark:text-gray-200">
                                            {{ line }}
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.website">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Website</dt>
                                            <dd>
                                                <a
                                                    :href="
                                                        linkedContactSummary.website.startsWith('http')
                                                            ? linkedContactSummary.website
                                                            : `https://${linkedContactSummary.website}`
                                                    "
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline break-all"
                                                >
                                                    {{ linkedContactSummary.website }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.linkedin">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">LinkedIn</dt>
                                            <dd>
                                                <a
                                                    :href="
                                                        linkedContactSummary.linkedin.startsWith('http')
                                                            ? linkedContactSummary.linkedin
                                                            : `https://${linkedContactSummary.linkedin}`
                                                    "
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline break-all"
                                                >
                                                    {{ linkedContactSummary.linkedin }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.facebook">
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 shrink-0">Facebook</dt>
                                            <dd>
                                                <a
                                                    :href="
                                                        linkedContactSummary.facebook.startsWith('http')
                                                            ? linkedContactSummary.facebook
                                                            : `https://${linkedContactSummary.facebook}`
                                                    "
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-primary-600 dark:text-primary-400 hover:underline break-all"
                                                >
                                                    {{ linkedContactSummary.facebook }}
                                                </a>
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-if="linkedContactSummary.notes">
                                        <div class="pt-1 border-t border-gray-200 dark:border-gray-600 md:col-span-2">
                                            <dt class="font-medium text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">
                                                Notes
                                            </dt>
                                            <dd class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">
                                                {{ linkedContactSummary.notes }}
                                            </dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>

                            <template v-for="group in displayFormGroups" :key="group.key">
                                <div>
                                    <h3
                                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700 mb-4"
                                    >
                                        {{ group.label }}
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                        <template v-for="field in group.fields" :key="field.key">
                                            <!-- date -->
                                            <div
                                                v-if="fieldDef(field.key).type === 'date'"
                                                :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                            >
                                                <label
                                                    :for="`customer-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    :id="`customer-${field.key}`"
                                                    v-model="form[field.key]"
                                                    type="date"
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

                                            <!-- number -->
                                            <div
                                                v-else-if="fieldDef(field.key).type === 'number'"
                                                :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                            >
                                                <label
                                                    :for="`customer-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    :id="`customer-${field.key}`"
                                                    v-model="form[field.key]"
                                                    type="number"
                                                    step="any"
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

                                            <!-- text / email / tel -->
                                            <div
                                                v-else-if="['text', 'email', 'tel'].includes(fieldDef(field.key).type || 'text')"
                                                :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                            >
                                                <label
                                                    :for="`customer-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    :id="`customer-${field.key}`"
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
                                                        :for="`customer-${field.key}`"
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
                                                    :id="`customer-${field.key}`"
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
                                                    :id="`customer-${field.key}`"
                                                    v-model="form[field.key]"
                                                    type="checkbox"
                                                    :disabled="isView"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                                />
                                                <label
                                                    :for="`customer-${field.key}`"
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
                                                    :for="`customer-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                    <span v-if="field.required" class="text-red-500">*</span>
                                                </label>
                                                <select
                                                    :id="`customer-${field.key}`"
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
                                                    :id="`customer-${field.key}`"
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
                                                    :for="`customer-${field.key}`"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                                                >
                                                    {{ fieldDef(field.key).label || field.key }}
                                                </label>
                                                <input
                                                    :id="`customer-${field.key}`"
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
                                    Edit customer
                                </Link>
                                <Link
                                    :href="route(`${recordType}.index`)"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                                >
                                    Back to customers
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
                                    Delete customer
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
                                              : 'Create customer'
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
                :aria-labelledby="`customer-textarea-expand-title-${expandedTextareaMeta.key}`"
                @click.self="closeTextareaExpand"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                    >
                        <h3
                            :id="`customer-textarea-expand-title-${expandedTextareaMeta.key}`"
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
