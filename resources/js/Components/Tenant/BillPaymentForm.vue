<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import CurrencyInput from '@/Components/Tenant/FormComponents/Currency.vue';
import FormFixedActionBar from '@/Components/Tenant/FormComponents/FormFixedActionBar.vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';
import { useQuickBooksApSyncOverlay } from '@/composables/useQuickBooksApSyncOverlay';
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
    recordType: { type: String, default: 'bill-payments' },
    recordTitle: { type: String, default: 'Bill payment' },
    editRestrictions: {
        type: Object,
        default: () => ({
            restricted: false,
            allowedFields: ['vendor_id'],
            reason: null,
        }),
    },
    quickbooksApSync: { type: Object, default: null },
});

const syncEnabled = computed(() => !!props.quickbooksApSync?.enabled);

const { beginCreateSync, endCreateSync } = useQuickBooksApSyncOverlay({
    enabled: syncEnabled,
    entityLabel: computed(() => props.quickbooksApSync?.entityLabel || 'bill payment'),
});

const emit = defineEmits(['saved', 'cancelled']);

const READONLY_FIELDS = new Set([
    'quickbooks_bill_payment_id',
    'bank_account_ref_name',
    'cc_account_ref_name',
]);

const billFieldDef = {
    label: 'Bill',
    type: 'record',
    typeDomain: 'Bill',
    relationship: 'bill',
};

const bankAccountFieldDef = {
    label: 'Pay from account',
    type: 'record',
    typeDomain: 'ChartOfAccount',
    relationship: 'bankChartOfAccount',
};

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

const fieldDef = (key) => props.fieldsSchema[key] || {};

const isQuickbooksManaged = computed(() => {
    const id = props.record?.quickbooks_bill_payment_id;

    return id != null && String(id).trim() !== '';
});

const isEditRestricted = computed(() => {
    if (props.editRestrictions?.restricted) {
        return props.mode === 'edit';
    }

    return props.mode === 'edit' && isQuickbooksManaged.value;
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
        ?? (isQuickbooksManaged.value ? 'quickbooks' : null);

    if (reason === 'quickbooks') {
        return 'This payment is synced with QuickBooks. Amounts, dates, and applied bills are managed in QuickBooks. You can link the vendor below.';
    }

    return 'This payment has limited editing. You can link the vendor below.';
});

const isFullWidthField = (key) => fieldDef(key).type === 'textarea';

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

function emptyLine() {
    return {
        id: null,
        bill_id: null,
        bill: null,
        quickbooks_bill_id: null,
        amount: '',
    };
}

function mapLinesFromRecord(record) {
    const rows = record?.lines ?? [];
    if (!rows.length) {
        return [emptyLine()];
    }

    return rows.map((row) => ({
        id: row.id ?? null,
        bill_id: row.bill_id ?? null,
        bill: row.bill ?? null,
        quickbooks_bill_id: row.quickbooks_bill_id ?? row.bill?.quickbooks_bill_id ?? null,
        amount: row.amount ?? '',
    }));
}

function mapLinesFromInitialData(data) {
    const rows = data?.lines ?? [];
    if (!rows.length) {
        return [emptyLine()];
    }

    return rows.map((row) => ({
        id: row.id ?? null,
        bill_id: row.bill_id ?? null,
        bill: row.bill ?? null,
        quickbooks_bill_id: row.quickbooks_bill_id ?? row.bill?.quickbooks_bill_id ?? null,
        amount: row.amount ?? '',
    }));
}

function linePseudoRecord(line) {
    return {
        bill_id: line.bill_id,
        bill: line.bill ?? null,
    };
}

function buildInitialForm() {
    const r = props.record;
    const init = { ...props.initialData };

    const values = {
        vendor_id: init.vendor_id ?? r?.vendor_id ?? null,
        doc_number: init.doc_number ?? r?.doc_number ?? '',
        txn_date: extractDate(init.txn_date ?? r?.txn_date) || (props.mode === 'create' ? todayInputDate() : ''),
        total_amt: init.total_amt ?? r?.total_amt ?? '',
        pay_type: init.pay_type ?? r?.pay_type ?? 'Check',
        currency_code: init.currency_code ?? r?.currency_code ?? 'USD',
        private_note: init.private_note ?? r?.private_note ?? '',
        bank_chart_of_account_id: init.bank_chart_of_account_id ?? r?.bank_chart_of_account_id ?? null,
        quickbooks_bill_payment_id: r?.quickbooks_bill_payment_id ?? '',
        bank_account_ref_name: r?.bank_account_ref_name ?? '',
        cc_account_ref_name: r?.cc_account_ref_name ?? '',
        lines: props.mode === 'edit'
            ? mapLinesFromRecord(r)
            : mapLinesFromInitialData(init).length
                ? mapLinesFromInitialData(init)
                : [emptyLine()],
        vendor: init.vendor ?? r?.vendor ?? null,
        bankChartOfAccount: init.bankChartOfAccount ?? r?.bankChartOfAccount ?? null,
    };

    if (values.vendor_id && values.vendor) {
        // already set
    } else if (values.vendor_id && r?.vendor) {
        values.vendor = r.vendor;
    }

    if (values.bank_chart_of_account_id && (r?.bankChartOfAccount || init.bankChartOfAccount)) {
        values.bankChartOfAccount = r?.bankChartOfAccount ?? init.bankChartOfAccount;
    }

    return values;
}

const form = useForm(buildInitialForm());
const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const pseudoRecord = computed(() => {
    if (props.record) {
        return {
            ...props.record,
            vendor_id: form.vendor_id,
            vendor: form.vendor ?? props.record.vendor,
            bank_chart_of_account_id: form.bank_chart_of_account_id,
            bankChartOfAccount: form.bankChartOfAccount ?? null,
        };
    }

    return {
        vendor_id: form.vendor_id,
        vendor: form.vendor ?? null,
        bank_chart_of_account_id: form.bank_chart_of_account_id,
        bankChartOfAccount: form.bankChartOfAccount ?? null,
        ...(Object.keys(props.initialData || {}).length ? props.initialData : {}),
    };
});

const paymentLabel = computed(() => {
    const r = props.record;
    if (!r) {
        return '';
    }

    return r.display_name || (r.sequence != null ? `BPAY-${r.sequence}` : `Payment #${r.id}`);
});

const headerTitle = computed(() => (props.mode === 'edit' ? 'EDIT PAYMENT' : 'NEW PAYMENT'));

const submitLabel = computed(() => {
    if (props.mode === 'edit' && isEditRestricted.value) {
        return 'Save links';
    }

    return props.mode === 'edit' ? 'Save changes' : 'Create payment';
});

const headerSubtitle = computed(() => {
    if (props.mode === 'edit' && isEditRestricted.value) {
        return 'Link vendor record in Helmful';
    }

    return props.mode === 'edit'
        ? 'Update payment details and bills paid'
        : 'Record a payment and apply it to one or more bills';
});

function selectOptionsForField(key) {
    const def = fieldDef(key);
    if (Array.isArray(def.options) && def.options.length) {
        return def.options;
    }

    const enumKey = def.enum;
    if (!enumKey) {
        return [];
    }

    return props.enumOptions?.[enumKey] ?? [];
}

const linesTotal = computed(() =>
    form.lines.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0),
);

watch(linesTotal, (total) => {
    if (isEditRestricted.value || total <= 0) {
        return;
    }

    form.total_amt = total.toFixed(2);
});

const payFromAccountFilterValue = computed(() =>
    form.pay_type === 'CreditCard' ? 'Credit Card' : 'Bank',
);

const payFromAccountLabel = computed(() =>
    form.pay_type === 'CreditCard' ? 'Credit card account' : 'Bank account',
);

watch(
    () => form.pay_type,
    () => {
        if (isEditRestricted.value) {
            return;
        }
        form.bank_chart_of_account_id = null;
        form.bankChartOfAccount = null;
    },
);

watch(
    () => form.vendor_id,
    () => {
        if (props.mode !== 'create' || isEditRestricted.value) {
            return;
        }

        form.lines.forEach((line) => {
            line.bill_id = null;
            line.bill = null;
            line.quickbooks_bill_id = null;
        });
    },
);

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length <= 1) {
        form.lines[0] = emptyLine();

        return;
    }

    form.lines.splice(index, 1);
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
        const opt = selectOptionsForField(key).find((o) => o.value === v);

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

function billLabel(line) {
    const bill = line.bill;
    if (!bill) {
        return '—';
    }

    return bill.display_name || (bill.sequence != null ? `BILL-${bill.sequence}` : `Bill #${bill.id}`);
}

function onRecordFieldSelected(fieldKey, record) {
    if (fieldKey === 'vendor_id') {
        onVendorSelected(record);
    }
}

function onVendorSelected(record) {
    form.vendor = record ?? null;
}

function onBankAccountSelected(record) {
    form.bankChartOfAccount = record ?? null;
}

function onBillSelected(line, record) {
    line.bill = record ?? null;
    line.quickbooks_bill_id = record?.quickbooks_bill_id ?? null;
}

function applyAccountRefs(raw) {
    const account = form.bankChartOfAccount;
    if (!account?.quickbooks_account_id) {
        return;
    }

    const name = account.fully_qualified_name || account.name || null;
    if (String(raw.pay_type || 'Check').toLowerCase() === 'creditcard') {
        raw.cc_account_ref_id = account.quickbooks_account_id;
        raw.cc_account_ref_name = name;
        raw.bank_account_ref_id = null;
        raw.bank_account_ref_name = null;
    } else {
        raw.bank_account_ref_id = account.quickbooks_account_id;
        raw.bank_account_ref_name = name;
        raw.cc_account_ref_id = null;
        raw.cc_account_ref_name = null;
    }
}

function preparePayload() {
    if (isEditRestricted.value) {
        const vendorId = form.vendor_id;

        return {
            vendor_id: vendorId === '' || vendorId === undefined ? null : vendorId,
        };
    }

    const raw = { ...form.data() };

    for (const key of ['vendor_id', 'bank_chart_of_account_id']) {
        if (raw[key] === '' || raw[key] === undefined) {
            raw[key] = null;
        }
    }

    for (const key of ['doc_number', 'private_note']) {
        if (raw[key] === '') {
            raw[key] = null;
        }
    }

    if (raw.txn_date === '') {
        raw.txn_date = null;
    }

    raw.lines = (raw.lines || [])
        .filter((line) => {
            const amount = parseFloat(line.amount);

            return line.bill_id && amount > 0;
        })
        .map((line, index) => ({
            bill_id: line.bill_id,
            quickbooks_bill_id: line.quickbooks_bill_id ?? line.bill?.quickbooks_bill_id ?? null,
            amount: parseFloat(line.amount) || 0,
            position: index,
        }));

    if (raw.total_amt === '' || raw.total_amt == null) {
        raw.total_amt = linesTotal.value > 0 ? linesTotal.value : 0;
    }

    applyAccountRefs(raw);

    if (props.mode === 'create') {
        raw.apply_to_bills = true;
    }

    delete raw.bank_chart_of_account_id;
    delete raw.bankChartOfAccount;
    delete raw.vendor;

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
            endCreateSync();
            if (props.mode === 'edit') {
                emit('saved', {});
                return;
            }

            emit('saved', {
                recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
            });
        },
        onError: () => {
            endCreateSync();
        },
        onFinish: () => {
            endCreateSync();
        },
    });

    if (props.mode === 'create' && syncEnabled.value) {
        beginCreateSync();
    }

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
        <form id="bill-payment-form" class="pb-28" @submit.prevent="submit">
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
                This payment is linked to QuickBooks. Bank and credit card accounts come from QuickBooks.
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 dark:from-primary-700 dark:to-primary-800">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-white">{{ headerTitle }}</h1>
                            <p class="mt-1 text-sm text-primary-100">{{ headerSubtitle }}</p>
                        </div>
                        <div v-if="record?.id" class="text-right">
                            <div class="text-xs font-medium text-primary-200">Reference</div>
                            <div class="font-mono text-lg text-white">{{ paymentLabel }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8 border-t border-primary-500/20 p-6 dark:border-primary-900/40">
                    <template v-for="group in formGroups" :key="group.key">
                        <div>
                            <h3 class="mb-4 border-b border-gray-200 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {{ group.label }}
                            </h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                                <template v-for="field in group.fields" :key="field.key">
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
                                            :id="`bill-payment-${field.key}`"
                                            :field="fieldDef(field.key)"
                                            v-model="form[field.key]"
                                            :record="pseudoRecord"
                                            :field-key="field.key"
                                            @record-selected="onRecordFieldSelected(field.key, $event)"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'date'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-payment-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <input
                                            :id="`bill-payment-${field.key}`"
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
                                        <label :for="`bill-payment-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <CurrencyInput
                                            :id="`bill-payment-${field.key}`"
                                            v-model="form[field.key]"
                                            icon-position="none"
                                        />
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'select'"
                                        :class="isFullWidthField(field.key) ? 'md:col-span-2' : ''"
                                    >
                                        <label :for="`bill-payment-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <select
                                            :id="`bill-payment-${field.key}`"
                                            v-model="form[field.key]"
                                            class="input-style w-full"
                                        >
                                            <option v-for="opt in selectOptionsForField(field.key)" :key="opt.value" :value="opt.value">
                                                {{ opt.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors[field.key]" class="mt-1 text-xs text-red-600">{{ form.errors[field.key] }}</p>
                                    </div>

                                    <div
                                        v-else-if="fieldDef(field.key).type === 'textarea'"
                                        class="md:col-span-2"
                                    >
                                        <label :for="`bill-payment-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <textarea
                                            :id="`bill-payment-${field.key}`"
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
                                        <label :for="`bill-payment-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ fieldDef(field.key).label || field.key }}
                                        </label>
                                        <input
                                            :id="`bill-payment-${field.key}`"
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

                    <div v-if="!isEditRestricted" class="border-t border-gray-200 pt-6 dark:border-gray-700">
                        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Pay from account
                        </h3>
                        <div class="max-w-md">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ payFromAccountLabel }}
                            </label>
                            <RecordSelect
                                id="bill-payment-bank-chart-of-account"
                                :field="bankAccountFieldDef"
                                v-model="form.bank_chart_of_account_id"
                                :record="pseudoRecord"
                                field-key="bank_chart_of_account_id"
                                filter-by="account_type"
                                :filter-value="payFromAccountFilterValue"
                                @record-selected="onBankAccountSelected"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Must be a QuickBooks {{ payFromAccountFilterValue }} account. Import chart of accounts first if none appear.
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-2 dark:border-gray-700">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Bills paid
                                </h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Select open bills for this vendor and enter the amount applied to each.
                                </p>
                            </div>
                            <button
                                v-if="!isEditRestricted"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="addLine"
                            >
                                <span class="material-icons text-base leading-none">add</span>
                                Add bill
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(line, index) in form.lines"
                                :key="index"
                                class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Bill {{ index + 1 }}
                                    </span>
                                    <button
                                        v-if="!isEditRestricted"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                                        @click="removeLine(index)"
                                    >
                                        <span class="material-icons text-sm leading-none">delete</span>
                                        Remove
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                    <template v-if="isEditRestricted">
                                        <div class="md:col-span-7">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Bill</label>
                                            <div class="input-style cursor-not-allowed bg-gray-50 text-sm text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                                                {{ billLabel(line) }}
                                            </div>
                                        </div>
                                        <div class="md:col-span-5">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Amount applied</label>
                                            <div class="input-style cursor-not-allowed bg-gray-50 text-sm text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                                                {{ formatCurrencyDisplay(line.amount) }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="md:col-span-7">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Bill</label>
                                            <RecordSelect
                                                :id="`bill-payment-line-${index}-bill`"
                                                :field="billFieldDef"
                                                v-model="line.bill_id"
                                                :record="linePseudoRecord(line)"
                                                field-key="bill_id"
                                                filter-by="vendor_id"
                                                :filter-value="form.vendor_id"
                                                :disabled="!form.vendor_id"
                                                @record-selected="onBillSelected(line, $event)"
                                            />
                                            <p v-if="!form.vendor_id" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Select a vendor first.
                                            </p>
                                        </div>
                                        <div class="md:col-span-5">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Amount applied</label>
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

                        <div class="mt-4 flex justify-end border-t border-gray-200 pt-3 dark:border-gray-700">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                Applied total:
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">
                                    ${{ linesTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                        <p v-if="form.errors.lines" class="mt-2 text-xs text-red-600">{{ form.errors.lines }}</p>
                    </div>
                </div>
            </div>
        </form>

        <FormFixedActionBar
            form-id="bill-payment-form"
            :processing="form.processing"
            :submit-label="submitLabel"
            @cancel="handleCancel"
        />
    </div>
</template>
