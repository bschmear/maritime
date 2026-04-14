<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';

const PAYMENT_METHOD_ENUM = 'App\\Enums\\Payments\\PaymentMethod';
const PROCESSOR_ENUM = 'App\\Enums\\Payments\\PaymentProcessor';

const props = defineProps({
    form: { type: Object, required: true },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    enumOptions: { type: Object, default: () => ({}) },
    fieldsSchema: { type: Object, default: () => ({}) },
    /** Loaded payment on edit (for display + RecordSelect context). */
    payment: { type: Object, default: null },
    /** Create flow: invoice chosen in parent stepper (no RecordSelect). */
    pickedInvoice: { type: Object, default: null },
    allowChangeInvoice: { type: Boolean, default: false },
});

const emit = defineEmits(['change-invoice']);

const methodOptions = () => props.enumOptions?.[PAYMENT_METHOD_ENUM] ?? [];
const processorOptions = () => props.enumOptions?.[PROCESSOR_ENUM] ?? [];

const invoiceField = () =>
    props.fieldsSchema?.invoice_id ?? {
        type: 'record',
        typeDomain: 'Invoice',
        label: 'Invoice',
        relationship: 'invoice',
        required: true,
    };

const pseudoRecord = () => {
    if (props.mode === 'edit' && props.payment) {
        return {
            ...props.payment,
            invoice_id: props.payment.invoice_id,
            invoice: props.payment.invoice ?? null,
        };
    }
    return {
        id: props.form.id ?? null,
        invoice_id: props.form.invoice_id,
        invoice: props.form.invoice ?? null,
    };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Invoice -->
        <div v-if="mode === 'create' && pickedInvoice" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-900/30 px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">
                        {{ invoiceField().label || 'Invoice' }}
                    </div>
                    <div class="font-semibold text-gray-900 dark:text-white truncate">
                        {{ pickedInvoice.display_name || `INV-${pickedInvoice.sequence}` }}
                    </div>
                    <div v-if="pickedInvoice.customer_name" class="text-sm text-gray-600 dark:text-gray-400 truncate">
                        {{ pickedInvoice.customer_name }}
                    </div>
                </div>
                <button
                    v-if="allowChangeInvoice"
                    type="button"
                    class="shrink-0 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                    @click="emit('change-invoice')"
                >
                    Change invoice
                </button>
            </div>
            <p v-if="form.errors.invoice_id" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ form.errors.invoice_id }}</p>
        </div>

        <div v-else-if="mode === 'create' && !pickedInvoice">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ invoiceField().label || 'Invoice' }}
                <span class="text-red-500">*</span>
            </label>
            <RecordSelect
                id="payment_invoice_id"
                :field="invoiceField()"
                v-model="form.invoice_id"
                :record="pseudoRecord()"
                field-key="invoice_id"
            />
            <p v-if="form.errors.invoice_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.invoice_id }}</p>
        </div>

        <div
            v-else-if="mode === 'edit'"
        >
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                {{ invoiceField().label || 'Invoice' }}
            </label>
            <div
                class="input-style bg-gray-50 dark:bg-gray-900/50 text-gray-800 dark:text-gray-200 cursor-not-allowed"
            >
                <span v-if="payment?.invoice">
                    {{ payment.invoice.display_name || `INV-${payment.invoice.sequence}` }}
                    <span v-if="payment.invoice.customer_name" class="text-gray-500 dark:text-gray-400">
                        — {{ payment.invoice.customer_name }}
                    </span>
                </span>
                <span v-else class="text-gray-400">—</span>
            </div>
            <p v-if="form.errors.invoice_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.invoice_id }}</p>
        </div>

        <!-- Amount (create only) -->
        <div v-if="mode === 'create'">
            <label for="payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Amount <span class="text-red-500">*</span>
            </label>
            <input
                id="payment_amount"
                v-model="form.amount"
                type="text"
                inputmode="decimal"
                class="input-style"
                placeholder="0.00"
                autocomplete="off"
            >
            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.amount }}</p>
        </div>
        <div v-else class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-4 py-3">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">Amount</div>
            <div class="text-base font-semibold text-gray-900 dark:text-white">
                {{ payment?.amount != null ? `$${Number(payment.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—' }}
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Amount and invoice cannot be changed here; edit only updates logging fields.
            </p>
        </div>

        <!-- Apply to invoice (create) -->
        <div v-if="mode === 'create'" class="rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/20 px-4 py-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input
                    v-model="form.apply_to_invoice"
                    type="checkbox"
                    class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                >
                <span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Apply to invoice balance</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                        When checked, the invoice’s amount paid and status update like “Record payment” on the invoice.
                        Uncheck only to log a payment without changing the invoice (e.g. duplicate record kept for notes).
                    </span>
                </span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="payment_method_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Payment method <span class="text-red-500">*</span>
                </label>
                <select
                    id="payment_method_code"
                    v-model="form.payment_method_code"
                    required
                    class="input-style"
                    :class="!form.payment_method_code ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white'"
                >
                    <option value="" disabled>Select method</option>
                    <option
                        v-for="opt in methodOptions()"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.name }}
                    </option>
                </select>
                <p v-if="form.errors.payment_method_code" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.payment_method_code }}</p>
            </div>

            <div>
                <label for="payment_processor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Processor <span class="text-red-500">*</span>
                </label>
                <select
                    id="payment_processor"
                    v-model="form.processor"
                    required
                    class="input-style"
                    :class="!form.processor ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white'"
                >
                    <option value="" disabled>Select processor</option>
                    <option
                        v-for="opt in processorOptions()"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.name }}
                    </option>
                </select>
                <p v-if="form.errors.processor" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.processor }}</p>
            </div>
        </div>

        <div>
            <label for="payment_paid_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Paid at
            </label>
            <input
                id="payment_paid_at"
                v-model="form.paid_at"
                type="datetime-local"
                class="input-style"
            >
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to use the current time when saving (create only).</p>
            <p v-if="form.errors.paid_at" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.paid_at }}</p>
        </div>

        <div>
            <label for="payment_reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Reference #
            </label>
            <input
                id="payment_reference"
                v-model="form.reference_number"
                type="text"
                class="input-style"
                placeholder="Check number, transaction ID, etc."
                maxlength="255"
            >
            <p v-if="form.errors.reference_number" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.reference_number }}</p>
        </div>

        <div>
            <label for="payment_memo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                Memo
            </label>
            <textarea
                id="payment_memo"
                v-model="form.memo"
                rows="3"
                class="input-style"
                placeholder="Internal notes…"
            />
            <p v-if="form.errors.memo" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.memo }}</p>
        </div>
    </div>
</template>
