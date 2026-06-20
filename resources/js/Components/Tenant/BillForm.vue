<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import EnumButtonGroup from '@/Components/Tenant/FormComponents/EnumButtonGroup.vue';
import FormFixedActionBar from '@/Components/Tenant/FormComponents/FormFixedActionBar.vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';
import { buildResourceRouteParams } from '@/Utils/resourceRoutes.js';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    initialData: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    recordType: { type: String, default: 'bills' },
    recordTitle: { type: String, default: 'Bill' },
    editRestrictions: {
        type: Object,
        default: () => ({
            restricted: false,
            allowedFields: ['vendor_id'],
            reason: null,
        }),
    },
});

const emit = defineEmits(['saved', 'cancelled']);

const READONLY_FIELDS = new Set([
    'quickbooks_bill_id',
    'ap_account_ref_name',
    'department_ref_name',
]);

const SIDEBAR_AMOUNT_FIELDS = new Set(['total_amt', 'balance']);

const normalizedForm = computed(() => {
    const s = props.formSchema;
    if (s?.form && typeof s.form === 'object') {
        return s.form;
    }
    return s && typeof s === 'object' ? s : {};
});

const formGroups = computed(() => {
    const out = [];
    for (const [key, group] of Object.entries(normalizedForm.value)) {
        if (!group || typeof group !== 'object') {
            continue;
        }
        const fields = (Array.isArray(group.fields) ? group.fields : [])
            .map((f) => (f && typeof f === 'object' && f.key ? { ...f, key: f.key } : null))
            .filter(Boolean);
        if (fields.length === 0) {
            continue;
        }
        out.push({
            key,
            label: group.label || key,
            fields,
        });
    }
    return out;
});

const visibleFieldsForGroup = (group) =>
    group.fields.filter((field) => !SIDEBAR_AMOUNT_FIELDS.has(field.key));

const fieldDef = (key) => props.fieldsSchema[key] || {};

const isQuickbooksManaged = computed(() => {
    const id = props.record?.quickbooks_bill_id;
    return id != null && String(id).trim() !== '';
});

const isPaid = computed(() => props.record?.status === 'paid');

const isEditRestricted = computed(() => {
    if (props.editRestrictions?.restricted) {
        return props.mode === 'edit';
    }

    return props.mode === 'edit' && (isQuickbooksManaged.value || isPaid.value);
});

const editableWhenRestricted = computed(() => {
    const allowed = props.editRestrictions?.allowedFields;
    return new Set(Array.isArray(allowed) && allowed.length ? allowed : ['vendor_id']);
});

const isFieldEditable = (key) => {
    if (READONLY_FIELDS.has(key) || !!fieldDef(key).disabled) {
        return false;
    }

    if (!isEditRestricted.value) {
        return true;
    }

    return editableWhenRestricted.value.has(key);
};

const isReadOnlyField = (key) => !isFieldEditable(key);

const restrictionBannerMessage = computed(() => {
    if (!isEditRestricted.value) {
        return '';
    }

    const reason = props.editRestrictions?.reason
        ?? (isPaid.value ? 'paid' : (isQuickbooksManaged.value ? 'quickbooks' : null));

    if (reason === 'paid') {
        return 'This bill is paid. Amounts, dates, and status cannot be changed here. You can link the vendor and line item accounts below.';
    }

    if (reason === 'quickbooks') {
        return 'This bill is synced with QuickBooks. Financial details are managed in QuickBooks. You can link the vendor and line item accounts below.';
    }

    return 'This bill has limited editing. You can link the vendor and line item accounts below.';
});

const isEnumSelectField = (key) => fieldDef(key).type === 'select' && !!fieldDef(key).enum;

const isFullWidthField = (key) => fieldDef(key).type === 'textarea' || isEnumSelectField(key);

function extractDate(val) {
    if (!val) {
        return '';
    }
    if (typeof val === 'string') {
        return val.split('T')[0];
    }
    return '';
}

function todayInputDate() {
    const d = new Date();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
}

function mapItemsFromRecord(record) {
    const rows = record?.items ?? [];
    if (!rows.length) {
        return [emptyLineItem()];
    }

    return rows.map((row) => ({
        id: row.id ?? null,
        description: row.description ?? '',
        chart_of_account_id: row.chart_of_account_id ?? null,
        chartOfAccount: row.chart_of_account ?? row.chartOfAccount ?? null,
        expense_account_ref_name: row.expense_account_ref_name ?? null,
        amount: row.amount ?? '',
        detail_type: row.detail_type ?? 'AccountBasedExpenseLineDetail',
    }));
}

function emptyLineItem() {
    return {
        id: null,
        description: '',
        chart_of_account_id: null,
        chartOfAccount: null,
        amount: '',
        detail_type: 'AccountBasedExpenseLineDetail',
    };
}

function lineItemPseudoRecord(line) {
    return {
        chart_of_account_id: line.chart_of_account_id,
        chartOfAccount: line.chartOfAccount ?? null,
    };
}

function buildInitialForm() {
    const r = props.record;
    const init = { ...props.initialData };

    const values = {
        vendor_id: init.vendor_id ?? r?.vendor_id ?? null,
        doc_number: init.doc_number ?? r?.doc_number ?? '',
        txn_date: extractDate(init.txn_date ?? r?.txn_date) || (props.mode === 'create' ? todayInputDate() : ''),
        due_date: extractDate(init.due_date ?? r?.due_date),
        status: normalizeEnumFormValue('status', init.status ?? r?.status ?? 'open'),
        total_amt: init.total_amt ?? r?.total_amt ?? '',
        balance: init.balance ?? r?.balance ?? '',
        currency_code: normalizeEnumFormValue('currency_code', init.currency_code ?? r?.currency_code ?? 'USD'),
        private_note: init.private_note ?? r?.private_note ?? '',
        quickbooks_bill_id: r?.quickbooks_bill_id ?? '',
        ap_account_ref_name: r?.ap_account_ref_name ?? '',
        department_ref_name: r?.department_ref_name ?? '',
        items: props.mode === 'edit' ? mapItemsFromRecord(r) : [emptyLineItem()],
    };

    if (values.vendor_id && r?.vendor) {
        values.vendor = r.vendor;
    }

    return values;
}

const form = useForm(buildInitialForm());
const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const pseudoRecord = computed(() => {
    if (props.record) {
        return props.record;
    }

    return Object.keys(props.initialData || {}).length ? props.initialData : null;
});

const billLabel = computed(() => {
    const r = props.record;
    if (!r) {
        return '';
    }
    return r.display_name || (r.sequence != null ? `BILL-${r.sequence}` : `Bill #${r.id}`);
});

const headerTitle = computed(() => (props.mode === 'edit' ? 'EDIT BILL' : 'NEW BILL'));

const submitLabel = computed(() => {
    if (props.mode === 'edit' && isEditRestricted.value) {
        return 'Save links';
    }

    return props.mode === 'edit' ? 'Save changes' : 'Create bill';
});

const headerSubtitle = computed(() => {
    if (props.mode === 'edit' && isEditRestricted.value) {
        return 'Link vendor and line item accounts in Helmful';
    }

    return props.mode === 'edit'
        ? 'Update bill details and expense line items'
        : 'Enter vendor, dates, and expense lines for this bill';
});

function selectOptionsForField(key) {
    const def = fieldDef(key);
    const enumKey = def.enum;
    if (!enumKey) {
        return [];
    }

    const opts = props.enumOptions?.[enumKey] ?? [];
    if (key === 'currency_code' && !opts.length) {
        return [{ id: 1, value: 'USD', name: 'US Dollar' }];
    }

    return opts;
}

function normalizeEnumFormValue(key, value) {
    const def = fieldDef(key);
    if (def.type !== 'select' || !def.enum) {
        return value;
    }

    const opts = selectOptionsForField(key);
    if (value == null || value === '') {
        if (def.default != null) {
            const defaultOpt = opts.find(
                (o) => o.value === def.default || o.id === def.default || String(o.value) === String(def.default),
            );
            return defaultOpt?.id ?? value;
        }

        return value;
    }

    const hit = opts.find(
        (o) =>
            o.id === value
            || o.value === value
            || String(o.id) === String(value)
            || String(o.value) === String(value),
    );

    return hit?.id ?? value;
}

const lineItemsTotal = computed(() =>
    form.items.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0),
);

const filledLineItemCount = computed(() =>
    form.items.filter((line) => {
        const amount = parseFloat(line.amount);
        return line.description?.trim() || line.chart_of_account_id || (amount && amount !== 0);
    }).length,
);

const currencyLabel = computed(() => {
    const opt = selectOptionsForField('currency_code').find(
        (o) =>
            o.id === form.currency_code
            || o.value === form.currency_code
            || String(o.id) === String(form.currency_code)
            || String(o.value) === String(form.currency_code),
    );

    return opt?.name ?? form.currency_code ?? 'USD';
});

watch(lineItemsTotal, (total) => {
    if (isEditRestricted.value) {
        return;
    }

    const rounded = Number(total).toFixed(2);
    form.total_amt = rounded;
    form.balance = rounded;
}, { immediate: true });

function addLineItem() {
    form.items.push(emptyLineItem());
}

function removeLineItem(index) {
    if (form.items.length <= 1) {
        form.items[0] = emptyLineItem();
        return;
    }
    form.items.splice(index, 1);
}

function relatedFromRecord(record, fieldKey) {
    if (!record || !fieldKey?.endsWith('_id')) {
        return null;
    }

    const snake = fieldKey.replace(/_id$/, '');
    const parts = snake.split('_');
    const camel = parts[0] + parts.slice(1).map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join('');

    return record[snake] ?? record[camel] ?? null;
}

function formatCurrencyDisplay(val) {
    if (val == null || val === '') {
        return '—';
    }

    const num = Number(val);
    if (Number.isNaN(num)) {
        return String(val);
    }

    return `$${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function readOnlyDisplayValue(key) {
    const def = fieldDef(key);
    const v = form[key];

    if (def.type === 'select') {
        const opt = selectOptionsForField(key).find(
            (o) =>
                o.value === v
                || o.id === v
                || String(o.id) === String(v)
                || String(o.value) === String(v),
        );
        return opt?.name ?? v ?? '—';
    }

    if (def.type === 'currency') {
        return formatCurrencyDisplay(v);
    }

    if (def.type === 'record') {
        const related = relatedFromRecord(pseudoRecord.value, key);
        if (related) {
            return related.display_name
                || related.fully_qualified_name
                || related.name
                || '—';
        }
    }

    if (v == null || v === '') {
        return '—';
    }

    return String(v);
}

function lineItemAccountLabel(line) {
    const account = line.chartOfAccount;
    if (!account) {
        return '—';
    }

    return account.fully_qualified_name || account.display_name || account.name || '—';
}

function preparePayload() {
    if (isEditRestricted.value) {
        const vendorId = form.vendor_id;

        return {
            vendor_id: vendorId === '' || vendorId === undefined ? null : vendorId,
            items: (form.items || [])
                .filter((line) => line?.id)
                .map((line) => ({
                    id: line.id,
                    chart_of_account_id: line.chart_of_account_id || null,
                })),
        };
    }

    const raw = { ...form.data() };
    delete raw.chart_of_account_id;

    for (const key of ['vendor_id']) {
        if (raw[key] === '' || raw[key] === undefined) {
            raw[key] = null;
        }
    }

    for (const key of ['doc_number', 'due_date', 'private_note']) {
        if (raw[key] === '') {
            raw[key] = null;
        }
    }

    if (raw.txn_date === '') {
        raw.txn_date = null;
    }

    raw.items = (raw.items || [])
        .filter((line) => {
            const amount = parseFloat(line.amount);
            return line.description?.trim() || line.chart_of_account_id || (amount && amount !== 0);
        })
        .map((line, index) => ({
            quickbooks_line_id: line.quickbooks_line_id ?? null,
            description: line.description?.trim() || null,
            chart_of_account_id: line.chart_of_account_id || null,
            amount: parseFloat(line.amount) || 0,
            detail_type: line.detail_type || 'AccountBasedExpenseLineDetail',
            position: index,
        }));

    raw.total_amt = lineItemsTotal.value;
    raw.balance = lineItemsTotal.value;

    for (const key of READONLY_FIELDS) {
        if (props.mode === 'create') {
            delete raw[key];
        }
    }

    return raw;
}

function submit() {
    const url =
        props.mode === 'edit'
            ? route(
                `${props.recordType}.update`,
                buildResourceRouteParams(props.recordType, props.record.id),
            )
            : route(`${props.recordType}.store`);

    form.clearErrors();
    const payload = preparePayload();

    const submitOptions = validationSubmitOptions({
        onSuccess: (page) => {
            if (props.mode === 'edit') {
                emit('saved', {});
                return;
            }

            emit('saved', {
                recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
            });
        },
    });

    if (props.mode === 'edit') {
        form.transform(() => payload).put(url, submitOptions);
    } else {
        form.transform(() => payload).post(url, submitOptions);
    }
}

function handleCancel() {
    form.reset();
    emit('cancelled');
}
</script>

<template>
    <div class="w-full flex flex-col space-y-6">
        <form id="bill-form" class="pb-28" @submit.prevent="submit">
            <div
                v-if="isEditRestricted"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-100"
            >
                {{ restrictionBannerMessage }}
            </div>
            <div
                v-else-if="isQuickbooksManaged"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-100"
            >
                This bill is linked to QuickBooks. AP account and department come from QuickBooks.
            </div>

            <div class="grid gap-6 lg:grid-cols-12">
                <div class="space-y-6 lg:col-span-8">
                    <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">{{ headerTitle }}</h1>
                                    <p class="mt-1 text-sm text-primary-100">{{ headerSubtitle }}</p>
                                </div>
                                <div v-if="record?.id" class="text-right">
                                    <div class="text-xs font-medium text-primary-200">Reference</div>
                                    <div class="font-mono text-lg text-white">{{ billLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8 border-t border-primary-500/20 p-6 dark:border-primary-900/40">
                            <template v-for="group in formGroups" :key="group.key">
                                <div v-if="visibleFieldsForGroup(group).length">
                                    <h3 class="mb-4 border-b border-gray-200 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        {{ group.label }}
                                    </h3>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                        <template v-for="field in visibleFieldsForGroup(group)" :key="field.key">
                                    <div
                                        v-if="isReadOnlyField(field.key)"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <div class="input-style cursor-not-allowed bg-gray-50 text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                                            {{ readOnlyDisplayValue(field.key) }}
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'record'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                            <span v-if="field.required" class="text-red-500">*</span>
                                        </label>
                                        <RecordSelect
                                            :id="`bill-${field.key}`"
                                            :field="fieldDef(field.key)"
                                            v-model="form[field.key]"
                                            :record="pseudoRecord"
                                            :field-key="field.key"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'date'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <input
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            type="date"
                                            class="input-style w-full"
                                        >
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'currency'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <CurrencyInput
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            icon-position="none"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="isEnumSelectField(field.key)"
                                        class="md:col-span-2"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <EnumButtonGroup
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            :options="selectOptionsForField(field.key)"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'select'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <select
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            class="input-style w-full"
                                        >
                                            <option v-for="opt in selectOptionsForField(field.key)" :key="opt.value ?? opt.id" :value="opt.value ?? opt.id">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'textarea'"
                                        class="md:col-span-2"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <textarea
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            rows="4"
                                            class="input-style w-full resize-y"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <input
                                            :id="`bill-${field.key}`"
                                            v-model="form[field.key]"
                                            type="text"
                                            class="input-style w-full"
                                        >
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Line items -->
                    <div>
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-2 dark:border-gray-700">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Line items
                            </h3>
                            <button
                                v-if="!isEditRestricted"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="addLineItem"
                            >
                                <span class="material-icons text-base leading-none">add</span>
                                Add line
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(line, index) in form.items"
                                :key="index"
                                class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Line {{ index + 1 }}
                                    </span>
                                    <button
                                        v-if="!isEditRestricted"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                                        @click="removeLineItem(index)"
                                    >
                                        <span class="material-icons text-sm leading-none">delete</span>
                                        Remove
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                    <template v-if="isEditRestricted">
                                        <div class="md:col-span-5">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                                            <div class="input-style cursor-not-allowed bg-gray-50 text-sm text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                                                {{ line.description || '—' }}
                                            </div>
                                        </div>
                                        <div class="md:col-span-4">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Account</label>
                                            <RecordSelect
                                                :id="`bill-line-${index}-chart-of-account`"
                                                :field="fieldDef('chart_of_account_id')"
                                                v-model="line.chart_of_account_id"
                                                :record="lineItemPseudoRecord(line)"
                                                field-key="chart_of_account_id"
                                            />
                                            <p
                                                v-if="!line.chart_of_account_id && line.expense_account_ref_name"
                                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                QuickBooks: {{ line.expense_account_ref_name }}
                                            </p>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Amount</label>
                                            <div class="input-style cursor-not-allowed bg-gray-50 text-sm text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                                                {{ formatCurrencyDisplay(line.amount) }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="md:col-span-5">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                                            <input
                                                v-model="line.description"
                                                type="text"
                                                class="input-style w-full text-sm"
                                                placeholder="Expense description"
                                            >
                                        </div>
                                        <div class="md:col-span-4">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Account</label>
                                            <RecordSelect
                                                :id="`bill-line-${index}-chart-of-account`"
                                                :field="fieldDef('chart_of_account_id')"
                                                v-model="line.chart_of_account_id"
                                                :record="lineItemPseudoRecord(line)"
                                                field-key="chart_of_account_id"
                                            />
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Amount</label>
                                            <CurrencyInput
                                                v-model="line.amount"
                                                icon-position="none"
                                                placeholder="$0.00"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.items" class="mt-2 text-xs text-red-600">{{ form.errors.items }}</p>
                    </div>
                        </div>
                    </div>
                </div>

                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky-below-nav">
                        <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                            <div class="border-b border-gray-200 bg-gray-700 px-5 py-4 dark:border-gray-600 dark:bg-gray-700">
                                <span class="text-sm font-semibold text-white">Bill summary</span>
                            </div>
                            <div class="space-y-4 p-5">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Line items</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ filledLineItemCount }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Currency</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ currencyLabel }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrencyDisplay(lineItemsTotal) }}</span>
                                </div>
                                <div class="space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef('total_amt').label || 'Total' }}
                                        </span>
                                        <span class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrencyDisplay(form.total_amt) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef('balance').label || 'Balance' }}
                                        </span>
                                        <span class="text-lg font-semibold text-primary-600 dark:text-primary-400">
                                            {{ formatCurrencyDisplay(form.balance) }}
                                        </span>
                                    </div>
                                </div>
                                <p
                                    v-if="!isEditRestricted"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Total and balance update automatically from line item amounts.
                                </p>
                                <p v-if="form.errors.total_amt" class="text-xs text-red-600">{{ form.errors.total_amt }}</p>
                                <p v-if="form.errors.balance" class="text-xs text-red-600">{{ form.errors.balance }}</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </form>

        <FormFixedActionBar
            form-id="bill-form"
            :processing="form.processing"
            :submit-label="submitLabel"
            @cancel="handleCancel"
        />
    </div>
</template>
