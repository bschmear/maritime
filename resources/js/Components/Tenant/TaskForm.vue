<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import MorphSelect from '@/Components/Tenant/MorphSelect.vue';
import DateTimeInput from '@/Components/Tenant/FormComponents/DateTime.vue';
import { useTimezone } from '@/composables/useTimezone';
import { buildMorphShowUrl, buildResourceRouteParams } from '@/Utils/resourceRoutes.js';

const props = defineProps({
    schema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    recordType: { type: String, default: 'tasks' },
    recordTitle: { type: String, default: 'Task' },
    recordIdentifier: { type: [String, Number], default: null },
    enumOptions: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        default: 'create',
        validator: (v) => ['view', 'edit', 'create'].includes(v),
    },
    preventRedirect: { type: Boolean, default: false },
    hideActions: { type: Boolean, default: false },
    lockRelatable: { type: Boolean, default: false },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['submit', 'cancel', 'created', 'updated']);

const { convertUTCToTimezone, convertTimezoneToUTC, accountTimezone } = useTimezone();

const isProcessing = ref(false);
const isViewMode = computed(() => props.mode === 'view');
const isCreateMode = computed(() => props.mode === 'create');
const isEditable = computed(() => props.mode === 'edit' || props.mode === 'create');
const updateRecordId = computed(() => props.recordIdentifier ?? props.record?.id);

const TASK_KEYS = [
    'display_name',
    'notes',
    'start_date',
    'due_date',
    'has_due_time',
    'due_time',
    'status_id',
    'priority_id',
    'assigned_id',
    'relatable_type',
    'relatable_id',
    'completed',
    'reminder_at',
    'created_by',
    'updated_by',
    'created_at',
    'updated_at',
];

const inputClass =
    'block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500';

const labelClass = 'mb-2 block text-sm font-medium text-gray-900 dark:text-white';

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

const relatableLocked = computed(() => {
    if (props.lockRelatable) {
        return true;
    }
    if (isCreateMode.value && props.record?.relatable_type && props.record?.relatable_id) {
        return true;
    }
    return false;
});

const relatableDisplayName = computed(() => {
    const r = props.record;
    if (!r) {
        return '';
    }
    return r.relatable?.display_name || r.relatable_display_name || '';
});

const relatableSelectedName = ref('');

const morphFieldDef = computed(() => fieldDef('relatable_type'));

function extractDate(val) {
    if (!val) {
        return '';
    }
    if (typeof val === 'string') {
        return val.split('T')[0];
    }
    return '';
}

function formatDateTimeDisplay(value) {
    if (!value) {
        return '—';
    }
    try {
        const d = convertUTCToTimezone(
            typeof value === 'string' ? value : new Date(value).toISOString(),
            accountTimezone.value,
        );
        return d.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return String(value);
    }
}

function defaultForKey(key) {
    const def = fieldDef(key);
    const t = def.type || 'text';
    if (t === 'boolean' || t === 'checkbox') {
        return false;
    }
    if (t === 'select' || t === 'morph' || t === 'record') {
        return '';
    }
    if (t === 'date' || t === 'time' || t === 'datetime') {
        return '';
    }
    return '';
}

function buildInitialValues() {
    const values = {};
    for (const key of TASK_KEYS) {
        values[key] = defaultForKey(key);
    }

    if (isCreateMode.value) {
        if (values.status_id === '' || values.status_id == null) {
            values.status_id = 1;
        }
        if (values.priority_id === '' || values.priority_id == null) {
            values.priority_id = 2;
        }
        const startDef = fieldDef('start_date');
        if (startDef.default_today && !values.start_date) {
            const now = new Date();
            const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTimezone.value }));
            values.start_date = localNow.toISOString().split('T')[0];
        }
    }

    const mergeSource = { ...props.initialData };
    if (props.record && typeof props.record === 'object') {
        Object.assign(mergeSource, props.record);
    }

    for (const key of TASK_KEYS) {
        if (!(key in mergeSource) || mergeSource[key] === undefined) {
            continue;
        }
        let v = mergeSource[key];
        const def = fieldDef(key);

        if (key === 'assigned_id' && mergeSource.assigned && typeof mergeSource.assigned === 'object') {
            v = mergeSource.assigned.id ?? v;
        }

        if (def.type === 'boolean' || def.type === 'checkbox' || key === 'completed' || key === 'has_due_time') {
            values[key] = v === true || v === 1 || v === '1';
            continue;
        }

        if (def.type === 'date') {
            values[key] = extractDate(v);
            continue;
        }

        if (def.type === 'datetime' && v) {
            try {
                const parsed = new Date(v);
                if (!Number.isNaN(parsed.getTime())) {
                    const tz = convertUTCToTimezone(parsed.toISOString(), accountTimezone.value);
                    values[key] = tz.toISOString().slice(0, 16);
                } else {
                    values[key] = v;
                }
            } catch {
                values[key] = v;
            }
            continue;
        }

        if (def.type === 'record' && v && typeof v === 'object' && v.id) {
            values[key] = v.id;
            continue;
        }

        values[key] = v;
    }

    return values;
}

const form = useForm(buildInitialValues());

watch(
    () => [props.record, props.mode, props.initialData],
    () => {
        if (!isEditable.value) {
            return;
        }
        const next = buildInitialValues();
        Object.keys(next).forEach((k) => {
            form[k] = next[k];
        });
    },
    { deep: true },
);

const showDueTime = computed(
    () => form.has_due_time === true || form.has_due_time === 1 || form.has_due_time === '1',
);

const effectiveRelatableId = computed(() => form.relatable_id ?? props.record?.relatable_id);
const effectiveRelatableType = computed(() => form.relatable_type || props.record?.relatable_type);

const selectedMorphConfig = computed(() => {
    const val = effectiveRelatableType.value;
    return morphFieldDef.value.morphable_types?.find((t) => t.value === val) ?? null;
});

const relatableShowUrl = computed(() =>
    buildMorphShowUrl(
        effectiveRelatableType.value,
        effectiveRelatableId.value,
        selectedMorphConfig.value,
    ),
);

const effectiveRelatableDisplayName = computed(() => {
    if (relatableSelectedName.value) {
        return relatableSelectedName.value;
    }
    const r = props.record;
    const rel = r?.relatable;
    if (
        rel?.display_name &&
        Number(rel.id) === Number(effectiveRelatableId.value) &&
        (r.relatable_type === effectiveRelatableType.value ||
            form.relatable_type === effectiveRelatableType.value)
    ) {
        return rel.display_name;
    }
    return relatableDisplayName.value;
});

function onRelatableSelected(payload) {
    relatableSelectedName.value = payload?.displayName ?? '';
}

watch([() => form.relatable_id, () => form.relatable_type], ([id, type]) => {
    if (!id || !type) {
        relatableSelectedName.value = '';
    }
});

const statusLabel = computed(() => {
    const hit = enumOptionsFor('status_id').find((o) => Number(o.id) === Number(form.status_id));
    return hit?.name ?? '—';
});

const priorityLabel = computed(() => {
    const hit = enumOptionsFor('priority_id').find((o) => Number(o.id) === Number(form.priority_id));
    return hit?.name ?? '—';
});

const priorityBgClass = computed(() => {
    const hit = enumOptionsFor('priority_id').find((o) => Number(o.id) === Number(form.priority_id));
    return hit?.bgClass ?? 'bg-gray-100 dark:bg-gray-700';
});

const statusBgClass = computed(() => {
    const hit = enumOptionsFor('status_id').find((o) => Number(o.id) === Number(form.status_id));
    return hit?.bgClass ?? 'bg-gray-100 dark:bg-gray-700';
});

const assignedDisplayName = computed(() => {
    const r = props.record;
    if (r?.assigned?.display_name) {
        return r.assigned.display_name;
    }
    if (r?.assigned?.name) {
        return r.assigned.name;
    }
    return '—';
});

const relatableTypeLabel = computed(() => {
    const val = effectiveRelatableType.value;
    const hit = morphFieldDef.value.morphable_types?.find((t) => t.value === val);
    return hit?.label ?? '—';
});

const relatableLinkLabel = computed(() => {
    const name = effectiveRelatableDisplayName.value;
    const type = relatableTypeLabel.value;
    if (name && type && type !== '—') {
        return `${type}: ${name}`;
    }
    if (name) {
        return name;
    }
    if (effectiveRelatableId.value != null && effectiveRelatableId.value !== '') {
        return `${type !== '—' ? type : 'Record'} #${effectiveRelatableId.value}`;
    }
    return 'related record';
});

function fieldError(key) {
    const e = form.errors[key];
    if (!e) {
        return null;
    }
    return Array.isArray(e) ? e[0] : e;
}

function preparePayload() {
    const data = { ...form.data() };

    data.has_due_time = data.has_due_time === true || data.has_due_time === 1 ? 1 : 0;
    data.completed = data.completed === true || data.completed === 1 ? 1 : 0;

    if (!data.has_due_time) {
        data.due_time = null;
    }

    for (const key of ['start_date', 'due_date']) {
        if (data[key]) {
            const timezoneDate = new Date(data[key]);
            const utcDate = convertTimezoneToUTC(timezoneDate.toISOString(), accountTimezone.value);
            data[key] = utcDate.toISOString().split('T')[0];
        } else {
            data[key] = null;
        }
    }

    if (data.reminder_at) {
        const timezoneDate = new Date(data.reminder_at);
        const utcDate = convertTimezoneToUTC(timezoneDate.toISOString(), accountTimezone.value);
        data.reminder_at = utcDate.toISOString().slice(0, 16);
    } else {
        data.reminder_at = null;
    }

    for (const key of ['status_id', 'priority_id', 'assigned_id', 'relatable_id']) {
        if (data[key] === '' || data[key] === undefined) {
            data[key] = null;
        }
    }

    if (!data.relatable_type) {
        data.relatable_id = null;
    }

    return data;
}

const handleSubmit = () => {
    if (!isEditable.value) {
        return;
    }

    const rawData = preparePayload();

    if (isCreateMode.value) {
        if (props.preventRedirect) {
            isProcessing.value = true;
            axios
                .post(route(`${props.recordType}.store`, props.extraRouteParams), rawData, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                })
                .then((response) => {
                    const recordId = response.data?.recordId || response.data?.data?.recordId;
                    form.reset();
                    if (recordId) {
                        emit('created', recordId);
                    } else {
                        emit('submit');
                    }
                })
                .catch((error) => {
                    if (error.response?.status === 422) {
                        form.errors = error.response.data.errors || {};
                    } else {
                        form.errors = {
                            general: [error.response?.data?.message || 'An error occurred'],
                        };
                    }
                })
                .finally(() => {
                    isProcessing.value = false;
                });
        } else {
            Object.keys(rawData).forEach((k) => {
                form[k] = rawData[k];
            });
            form.post(route(`${props.recordType}.store`, props.extraRouteParams), {
                preserveScroll: true,
                onSuccess: (page) => {
                    let recordId = page?.props?.flash?.recordId;
                    if (!recordId) {
                        const urlMatch = page?.url?.match(/\/(\d+)$/);
                        if (urlMatch) {
                            recordId = urlMatch[1];
                        }
                    }
                    if (recordId) {
                        emit('created', recordId);
                    }
                    emit('submit');
                },
            });
        }
        return;
    }

    const url = route(
        `${props.recordType}.update`,
        buildResourceRouteParams(props.recordType, updateRecordId.value, props.extraRouteParams),
    );

    if (props.preventRedirect) {
        isProcessing.value = true;
        axios
            .put(url, rawData, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
            .then((response) => {
                const updatedRecord = response.data?.record || response.data?.data?.record;
                if (updatedRecord) {
                    emit('updated', updatedRecord);
                } else {
                    emit('submit');
                }
            })
            .catch((error) => {
                if (error.response?.status === 422) {
                    form.errors = error.response.data.errors || {};
                } else {
                    form.errors = {
                        general: [error.response?.data?.message || 'An error occurred'],
                    };
                }
            })
            .finally(() => {
                isProcessing.value = false;
            });
    } else {
        Object.keys(rawData).forEach((k) => {
            form[k] = rawData[k];
        });
        form.put(url, {
            preserveScroll: true,
            onSuccess: () => {
                emit('submit');
                router.reload({ only: ['record'] });
            },
        });
    }
};

const handleCancel = () => {
    form.reset();
    emit('cancel');
};

const submitForm = () => handleSubmit();
const cancelForm = () => handleCancel();
const isFormProcessing = computed(() => form.processing || isProcessing.value);

defineExpose({
    submitForm,
    cancelForm,
    isProcessing: isFormProcessing,
});
</script>

<template>
    <form class="task-form pt-5" @submit.prevent="handleSubmit">
        <div
            v-if="form.errors.general"
            class="mx-4 mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200 sm:mx-6"
        >
            {{ Array.isArray(form.errors.general) ? form.errors.general[0] : form.errors.general }}
        </div>

        <div class="mb-4 grid gap-5 px-4 sm:mb-5 sm:px-6 lg:grid-cols-2">
            <!-- Left: title + notes -->
            <div class="space-y-4">
                <div>
                    <label :class="labelClass" for="task-display-name">
                        {{ fieldDef('display_name').label || 'Task name' }}
                        <span v-if="isEditable" class="text-red-500">*</span>
                    </label>
                    <template v-if="isViewMode">
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ form.display_name || '—' }}
                        </p>
                    </template>
                    <input
                        v-else
                        id="task-display-name"
                        v-model="form.display_name"
                        type="text"
                        :class="inputClass"
                        :placeholder="fieldDef('display_name').label || 'Task name'"
                        required
                    />
                    <p v-if="fieldError('display_name')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('display_name') }}
                    </p>
                </div>

                <div>
                    <label :class="labelClass" for="task-notes">
                        {{ fieldDef('notes').label || 'Notes' }}
                    </label>
                    <template v-if="isViewMode">
                        <div
                            class="min-h-[12rem] whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-200"
                        >
                            {{ form.notes || 'No notes.' }}
                        </div>
                    </template>
                    <textarea
                        v-else
                        id="task-notes"
                        v-model="form.notes"
                        rows="14"
                        :class="[inputClass, 'resize-y min-h-[14rem]']"
                        placeholder="Add details, checklist items, or context for this task…"
                    />
                    <p v-if="fieldError('notes')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('notes') }}
                    </p>
                </div>
            </div>

            <!-- Right: assignment, status, dates -->
            <div class="space-y-5">
                <div>
                    <div :class="labelClass">Assigned to</div>
                    <template v-if="isViewMode">
                        <p class="text-sm text-gray-800 dark:text-gray-200">{{ assignedDisplayName }}</p>
                    </template>
                    <RecordSelect
                        v-else
                        id="task-assigned-id"
                        field-key="assigned_id"
                        :field="fieldDef('assigned_id')"
                        v-model="form.assigned_id"
                        :record="record"
                        modal-context
                    />
                    <p v-if="fieldError('assigned_id')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('assigned_id') }}
                    </p>
                </div>

                <div>
                    <div :class="labelClass">{{ fieldDef('status_id').label || 'Status' }}</div>
                    <template v-if="isViewMode">
                        <span
                            class="inline-flex rounded px-2.5 py-1 text-xs font-medium"
                            :class="statusBgClass"
                        >
                            {{ statusLabel }}
                        </span>
                    </template>
                    <div v-else class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in enumOptionsFor('status_id')"
                            :key="opt.id"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition"
                            :class="
                                Number(form.status_id) === Number(opt.id)
                                    ? [opt.bgClass, 'ring-primary-500 dark:ring-primary-400']
                                    : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'
                            "
                            @click="form.status_id = opt.id"
                        >
                            {{ opt.name }}
                        </button>
                    </div>
                    <p v-if="fieldError('status_id')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('status_id') }}
                    </p>
                </div>

                <div>
                    <div :class="labelClass">{{ fieldDef('priority_id').label || 'Priority' }}</div>
                    <template v-if="isViewMode">
                        <span
                            class="inline-flex rounded px-2.5 py-1 text-xs font-medium"
                            :class="priorityBgClass"
                        >
                            {{ priorityLabel }}
                        </span>
                    </template>
                    <div v-else class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in enumOptionsFor('priority_id')"
                            :key="opt.id"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition"
                            :class="
                                Number(form.priority_id) === Number(opt.id)
                                    ? [opt.bgClass, 'ring-primary-500 dark:ring-primary-400']
                                    : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'
                            "
                            @click="form.priority_id = opt.id"
                        >
                            {{ opt.name }}
                        </button>
                    </div>
                    <p v-if="fieldError('priority_id')" class="mt-1 text-xs text-red-600">
                        {{ fieldError('priority_id') }}
                    </p>
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="w-full">
                        <label :class="labelClass" for="task-start-date">
                            {{ fieldDef('start_date').label || 'Start date' }}
                        </label>
                        <template v-if="isViewMode">
                            <p class="text-sm text-gray-800 dark:text-gray-200">
                                {{ form.start_date || '—' }}
                            </p>
                        </template>
                        <input
                            v-else
                            id="task-start-date"
                            v-model="form.start_date"
                            type="date"
                            :class="inputClass"
                        />
                    </div>
                    <div class="w-full">
                        <label :class="labelClass" for="task-due-date">
                            {{ fieldDef('due_date').label || 'Due date' }}
                        </label>
                        <template v-if="isViewMode">
                            <p class="text-sm text-gray-800 dark:text-gray-200">
                                {{ form.due_date || '—' }}
                                <span v-if="showDueTime && form.due_time" class="text-gray-500">
                                    at {{ form.due_time }}
                                </span>
                            </p>
                        </template>
                        <input
                            v-else
                            id="task-due-date"
                            v-model="form.due_date"
                            type="date"
                            :class="inputClass"
                        />
                    </div>
                </div>

                <div v-if="isEditable" class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input
                            v-model="form.has_due_time"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ fieldDef('has_due_time').label || 'Set specific time' }}
                        </span>
                    </label>
                    <div v-if="showDueTime" class="min-w-[8rem] flex-1">
                        <label class="sr-only" for="task-due-time">{{ fieldDef('due_time').label }}</label>
                        <input
                            id="task-due-time"
                            v-model="form.due_time"
                            type="time"
                            :class="inputClass"
                        />
                    </div>
                </div>

                <div v-if="!relatableLocked">
                    <div :class="labelClass">{{ fieldDef('relatable_type').label || 'Related to' }}</div>
                    <template v-if="isViewMode">
                        <p class="text-sm text-gray-800 dark:text-gray-200">
                            {{ relatableTypeLabel }}
                            <span v-if="effectiveRelatableDisplayName">
                                — {{ effectiveRelatableDisplayName }}
                            </span>
                        </p>
                        <Link
                            v-if="relatableShowUrl"
                            :href="relatableShowUrl"
                            class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            <span class="material-icons text-[16px]" aria-hidden="true">open_in_new</span>
                            View {{ relatableLinkLabel }}
                        </Link>
                    </template>
                    <MorphSelect
                        v-else
                        id="task-relatable"
                        :field="morphFieldDef"
                        v-model="form.relatable_id"
                        v-model:selected-type="form.relatable_type"
                        :initial-display-name="effectiveRelatableDisplayName || relatableDisplayName"
                        @record-selected="onRelatableSelected"
                    />
                    <Link
                        v-if="relatableShowUrl"
                        :href="relatableShowUrl"
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        <span class="material-icons text-[16px]" aria-hidden="true">open_in_new</span>
                        View {{ relatableLinkLabel }}
                    </Link>
                </div>
                <div v-else-if="form.relatable_type">
                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900/40"
                    >
                        <span class="text-gray-500 dark:text-gray-400">Related to</span>
                        <span class="ml-1 font-medium text-gray-900 dark:text-white">
                            {{ relatableTypeLabel }}
                            <span v-if="effectiveRelatableDisplayName">
                                — {{ effectiveRelatableDisplayName }}
                            </span>
                        </span>
                    </div>
                    <Link
                        v-if="relatableShowUrl"
                        :href="relatableShowUrl"
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        <span class="material-icons text-[16px]" aria-hidden="true">open_in_new</span>
                        View {{ relatableLinkLabel }}
                    </Link>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <label v-if="isEditable" class="inline-flex cursor-pointer items-center gap-2">
                        <input
                            v-model="form.completed"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ fieldDef('completed').label || 'Mark completed' }}
                        </span>
                    </label>
                    <p v-else-if="form.completed" class="text-sm text-green-700 dark:text-green-400">
                        Completed
                    </p>
                </div>

                <div>
                    <label :class="labelClass" for="task-reminder">
                        {{ fieldDef('reminder_at').label || 'Reminder' }}
                    </label>
                    <template v-if="isViewMode">
                        <p class="text-sm text-gray-800 dark:text-gray-200">
                            {{ formatDateTimeDisplay(form.reminder_at) }}
                        </p>
                    </template>
                    <DateTimeInput v-else id="task-reminder" v-model="form.reminder_at" />
                </div>

                <div
                    v-if="isViewMode && (record?.created_at || record?.updated_at)"
                    class="border-t border-gray-200 pt-4 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
                >
                    <p v-if="record?.created_at">Created {{ formatDateTimeDisplay(record.created_at) }}</p>
                    <p v-if="record?.updated_at">Updated {{ formatDateTimeDisplay(record.updated_at) }}</p>
                </div>
            </div>
        </div>

        <div
            v-if="isEditable && !hideActions"
            class="flex items-center gap-3 border-t border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6"
        >
            <button
                type="submit"
                :disabled="isFormProcessing"
                class="inline-flex items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
            >
                {{ isCreateMode ? `Create ${recordTitle}` : `Save ${recordTitle}` }}
            </button>
            <button
                type="button"
                :disabled="isFormProcessing"
                class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="handleCancel"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
