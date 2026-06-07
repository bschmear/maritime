<script setup>
import CustomerApprovalDeliveryModal from '@/Components/Tenant/CustomerApprovalDeliveryModal.vue';
import { useCustomerApprovalDelivery } from '@/composables/useCustomerApprovalDelivery';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    account: {
        type: Object,
        required: true,
    },
    /** Same pattern as InvoicePreview / public InvoiceView (falls back to account.logo_url). */
    logoUrl: {
        type: String,
        default: null,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    serviceTicketApprovalSms: {
        type: Object,
        default: () => ({ offered: false, hint: null }),
    },
});

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

const emit = defineEmits(['close', 'sent']);

const printing = ref(false);

const customerEmail = computed(() => props.record?.customer?.email ?? '');

const {
    showModal: showApprovalDeliveryModal,
    delivery: approvalDelivery,
    sendForm: sendApprovalForm,
    emailPreview: approvalEmailPreview,
    modalSubtitle: approvalModalSubtitle,
    deliveryError: approvalDeliveryError,
    openModal: openApprovalDeliveryModal,
    closeModal: closeApprovalDeliveryModal,
    confirmSend: confirmSendApproval,
} = useCustomerApprovalDelivery({
    postRoute: (id) => route('servicetickets.send-approval-request', id),
    recordId: () => props.record.id,
    customerEmail,
    smsOffer: () => props.serviceTicketApprovalSms,
});

const formatCurrency = (value) => {
    return value != null ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '$0.00';
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    } catch (e) {
        return '—';
    }
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch (e) {
        return '—';
    }
};

const isSigned = computed(() =>
    Boolean(
        props.record.approved
        || props.record.signed_at
        || props.record.customer_signature
        || props.record.signature_url,
    ),
);

const hasSignatureImage = computed(() =>
    Boolean(props.record.signature_url || (Number(props.record.signature_method) === 5 && props.record.customer_signature)),
);

const getBillingTypeLabel = (billingType) => {
    const options = props.enumOptions?.billing_type || [];
    const option = options.find(opt => opt.value === billingType);
    return option?.name || 'Unknown';
};

const calculateLineItemPrice = (item) => {
    const rate = Number(item.unit_price) || 0;
    const quantity = Number(item.quantity) || 1;
    const estimatedHours = Number(item.estimated_hours) || 0;

    let total = 0;

    switch (item.billing_type) {
        case 1: // Hourly
            total = estimatedHours * rate;
            break;
        case 2: // Flat
            total = rate;
            break;
        case 3: // Quantity
        default:
            total = quantity * rate;
            break;
    }

    if (item.warranty) {
        total = 0;
    }

    return total;
};

const billableLineItems = computed(() => {
    return props.record.service_items?.filter(item => item.billable !== false) || [];
});

const subtotal = computed(() => {
    return billableLineItems.value.reduce((sum, item) => sum + calculateLineItemPrice(item), 0);
});

const taxAmount = computed(() => {
    const rate = Number(props.record.tax_rate) || 0;
    return subtotal.value * (rate / 100);
});

const grandTotal = computed(() => {
    return subtotal.value + taxAmount.value;
});

const estimateVariance = computed(() => {
    const threshold = Number(props.account.estimate_threshold_percent) || 20;
    return (grandTotal.value * threshold) / 100;
});

const companyName = computed(
    () => props.record.subsidiary?.display_name || props.account?.name || 'Company Name',
);

const acknowledgementText = computed(() =>
    (props.account.service_ticket_ack_text || '').replace('[COMPANY NAME]', companyName.value),
);

const consentLabel = computed(
    () => 'I acknowledge that I have reviewed the service details, line items, and estimated costs above. By signing, I authorize the work described to proceed as outlined.',
);

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};

const handleSendApproval = () => {
    openApprovalDeliveryModal();
};

const handleConfirmSendApproval = () => {
    confirmSendApproval({
        onSuccess: (page) => {
            const flash = page.props.flash;
            if (flash?.success) {
                emit('sent', flash.success);
            }
            const flashErr = flash?.error;
            if (flashErr) {
                emit('sent', { error: Array.isArray(flashErr) ? flashErr[0] : flashErr });
            }
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Action Bar - Hidden when printing -->
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
    <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-2 lg:py-4">
        <div class="flex items-center justify-between gap-2 lg:gap-4">
            <!-- Title: shrinks on mobile, full on desktop -->
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white lg:text-lg truncate">
                    Customer Preview
                </h2>
                <p class="hidden text-sm text-gray-500 dark:text-gray-400 lg:block mt-0.5">
                    This is how the service ticket will appear to the customer
                </p>
            </div>

            <!-- Buttons: always in a row, icon-only on mobile -->
            <div class="flex shrink-0 items-center gap-1.5 lg:gap-3">
                <button
                    type="button"
                    aria-label="Close preview"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 lg:px-4"
                    @click="$emit('close')"
                >
                    <span class="material-icons text-[18px]">close</span>
                    <span class="hidden lg:inline">Close</span>
                </button>

                <button
                    type="button"
                    :aria-label="sendApprovalForm.processing ? 'Sending approval request' : 'Send approval request to customer'"
                    :aria-busy="sendApprovalForm.processing"
                    :disabled="sendApprovalForm.processing"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                    @click="handleSendApproval"
                >
                    <span v-if="sendApprovalForm.processing" class="material-icons animate-spin text-[18px]">refresh</span>
                    <span v-else class="material-icons text-[18px]">send</span>
                    <span class="hidden lg:inline">{{ sendApprovalForm.processing ? 'Sending...' : 'Send Approval Request' }}</span>
                </button>

                <button
                    type="button"
                    :aria-label="printing ? 'Preparing print' : 'Print preview'"
                    :aria-busy="printing"
                    :disabled="printing"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                    @click="handlePrint"
                >
                    <span v-if="printing" class="material-icons animate-spin text-[18px]">refresh</span>
                    <span v-else class="material-icons text-[18px]">print</span>
                    <span class="hidden lg:inline">{{ printing ? 'Preparing...' : 'Print' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
        <!-- Printable Document -->
        <div id="service-ticket-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div class="bg-white shadow-lg print:shadow-none">
                <!-- Company Header -->
                <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-6">
                            <!-- Company Logo -->
                            <div v-if="effectiveLogoUrl" class="flex-shrink-0">
                                <img :src="effectiveLogoUrl" alt="Company Logo" class="h-20 w-auto max-w-[150px] object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>

                            <!-- Company Info -->
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ record.subsidiary?.display_name || 'Company Name' }}
                                </h1>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
                                    <p v-if="record.location?.address_line1">
                                        {{ record.location.address_line1 }}
                                        <span v-if="record.location?.address_line2">, {{ record.location.address_line2 }}</span>
                                    </p>
                                    <p v-if="record.location?.city">
                                        {{ record.location.city }}<span v-if="record.location?.state">, {{ record.location.state }}</span> {{ record.location?.postal_code }}
                                    </p>
                                    <p v-if="record.location?.phone" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ record.location.phone }}
                                    </p>
                                    <p v-if="record.location?.email" class="flex items-center gap-1">
                                        <span class="material-icons text-sm">email</span>
                                        {{ record.location.email }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Service Ticket Number -->
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 uppercase">Service Ticket</div>
                            <div class="text-3xl font-bold text-gray-900 font-mono">
                                #{{ record.service_ticket_number }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ formatDate(record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="px-8 print:px-0 py-6 bg-gray-50 print:bg-white">
                    <div class="service-ticket-customer-asset-grid grid grid-cols-1 md:grid-cols-2 print:grid-cols-2 gap-6">
                        <!-- Customer Details -->
                        <div>
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="space-y-2">
                                    <div class="font-semibold text-gray-900 text-lg">
                                        {{ record.customer?.display_name || '—' }}
                                    </div>
                                    <div v-if="record.customer?.email" class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="material-icons text-sm">email</span>
                                        {{ record.customer.email }}
                                    </div>
                                    <div v-if="record.customer?.phone" class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="material-icons text-sm">phone</span>
                                        {{ record.customer.phone }}
                                    </div>
                                    <div v-if="record.customer?.address_line1" class="flex items-start gap-2 text-sm text-gray-600 mt-3">
                                        <span class="material-icons text-sm mt-0.5">location_on</span>
                                        <div>
                                            <div>{{ record.customer.address_line1 }}</div>
                                            <div v-if="record.customer?.address_line2">{{ record.customer.address_line2 }}</div>
                                            <div v-if="record.customer?.city">
                                                {{ record.customer.city }}<span v-if="record.customer?.state">, {{ record.customer.state }}</span> {{ record.customer?.postal_code }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Asset Information -->
                        <div v-if="record.asset_unit">
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset Information</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="space-y-2">
                                    <div class="font-semibold text-gray-900 text-lg">
                                        {{ record.asset_unit?.display_name || '—' }}
                                    </div>
                                    <div v-if="record.asset_unit?.asset?.make?.display_name" class="text-sm text-gray-600">
                                        <span class="font-medium">Make:</span> {{ record.asset_unit.asset.make.display_name }}
                                    </div>
                                    <div v-if="record.asset_unit?.asset?.year" class="text-sm text-gray-600">
                                        <span class="font-medium">Year:</span> {{ record.asset_unit.asset.year }}
                                    </div>
                                    <div v-if="record.asset_unit?.serial_number" class="text-sm text-gray-600">
                                        <span class="font-medium">Serial:</span> {{ record.asset_unit.serial_number }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repair Description -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Repair Description</h2>
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-900 whitespace-pre-line">{{ record.repair_description || '—' }}</p>
                    </div>
                </div>

                <!-- Service Items -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Service Items</h2>

                    <template v-if="billableLineItems.length > 0">
                        <!-- Mobile: stacked cards -->
                        <div class="md:hidden divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200">
                            <div
                                v-for="(item, index) in billableLineItems"
                                :key="`preview-item-m-${index}`"
                                class="space-y-3 bg-white p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-900">
                                            {{ item.display_name }}
                                        </div>
                                        <div v-if="item.description && item.description !== item.display_name" class="mt-1 text-sm text-gray-600">
                                            {{ item.description }}
                                        </div>
                                        <div v-if="item.warranty" class="mt-2 inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            <span class="material-icons text-xs">verified_user</span>
                                            Warranty
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Amount</div>
                                        <div class="text-base font-semibold tabular-nums text-gray-900">
                                            {{ formatCurrency(calculateLineItemPrice(item)) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Qty</div>
                                        <div class="text-gray-900">{{ item.quantity }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Est hrs</div>
                                        <div class="text-gray-900">{{ item.estimated_hours ?? 0 }}</div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Type</div>
                                        <span class="mt-0.5 inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                            {{ getBillingTypeLabel(item.billing_type) }}
                                        </span>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Rate</div>
                                        <div class="tabular-nums text-gray-900">{{ formatCurrency(item.unit_price) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- md+: table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full min-w-[32rem]">
                                <thead>
                                    <tr class="border-b-2 border-gray-900">
                                        <th class="py-3 text-left text-sm font-semibold text-gray-900">Description</th>
                                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Qty</th>
                                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Type</th>
                                        <th class="py-3 text-center text-sm font-semibold text-gray-900">Est Hrs</th>
                                        <th class="py-3 text-right text-sm font-semibold text-gray-900">Rate</th>
                                        <th class="py-3 text-right text-sm font-semibold text-gray-900">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="(item, index) in billableLineItems" :key="index" class="hover:bg-gray-50">
                                        <td class="py-3 pr-4">
                                            <div class="font-medium text-gray-900">{{ item.display_name }}</div>
                                            <div v-if="item.description && item.description !== item.display_name" class="mt-1 text-sm text-gray-600">
                                                {{ item.description }}
                                            </div>
                                            <div v-if="item.warranty" class="mt-1 inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                <span class="material-icons text-xs">verified_user</span>
                                                Warranty
                                            </div>
                                        </td>
                                        <td class="py-3 text-center text-gray-900">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ getBillingTypeLabel(item.billing_type) }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-center text-gray-900">
                                            {{ item.estimated_hours ?? 0 }}
                                        </td>
                                        <td class="py-3 text-right text-gray-900">
                                            {{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td class="py-3 text-right font-medium text-gray-900">
                                            {{ formatCurrency(calculateLineItemPrice(item)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="py-8 text-center text-gray-500">
                        No billable items
                    </div>
                </div>
                    <!-- Estimate Variance Notice -->
                    <div v-if="account.estimate_threshold_percent" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start gap-3">
                            <span class="material-icons text-blue-600 text-xl flex-shrink-0">info</span>
                            <div class="text-sm text-blue-900">
                                <p class="font-semibold mb-1">Estimate Variance Notice</p>
                                <p>
                                    Our estimate may vary by {{ account.estimate_threshold_percent }}% (up to {{ formatCurrency(estimateVariance) }}). 
                                    If the final cost exceeds this threshold, customer verification will be required before proceeding with additional work.
                                </p>
                            </div>
                        </div>
                    </div>
                <!-- Totals -->
                <div class="px-8 py-6 bg-gray-50 border-t border-gray-200">
                    
                    <div class="flex justify-end">
                        <div class="w-full md:w-1/2 lg:w-1/3 space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium text-gray-900">{{ formatCurrency(subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Tax ({{ record.tax_rate }}%):</span>
                                <span class="font-medium text-gray-900">{{ formatCurrency(taxAmount) }}</span>
                            </div>
                            <div class="flex justify-between text-xl font-bold border-t-2 border-gray-900 pt-3">
                                <span class="text-gray-900">Total:</span>
                                <span class="text-gray-900">{{ formatCurrency(grandTotal) }}</span>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Customer Acknowledgment & Signature -->
                <div class="px-8 print:px-0 py-8 border-t-2 border-gray-900 print:break-inside-avoid">
                    <h2 class="mb-6 text-sm font-semibold uppercase tracking-wide text-gray-900">Customer authorization</h2>

                    <div
                        v-if="isSigned"
                        class="mb-6 inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-800 print:hidden"
                    >
                        <span class="material-icons text-base">check_circle</span>
                        Signed {{ formatDateTime(record.signed_at) }}
                    </div>

                    <template v-if="isSigned && hasSignatureImage">
                        <div
                            v-if="acknowledgementText"
                            class="mb-6 whitespace-pre-line border border-gray-200 bg-white p-4 text-sm leading-relaxed text-gray-800 print:border-gray-400"
                        >
                            {{ acknowledgementText }}
                        </div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Customer signature</h3>
                        <div class="flex flex-wrap items-start gap-6">
                            <div class="signature-surface rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <img
                                    v-if="record.signature_url"
                                    :src="record.signature_url"
                                    alt="Customer signature"
                                    class="max-h-24 w-auto"
                                />
                                <p v-else class="signature-surface-text signature-cursive text-3xl text-gray-900">
                                    {{ record.customer_signature }}
                                </p>
                            </div>
                            <div class="space-y-1 pt-1 text-sm text-gray-600">
                                <div>
                                    <span class="text-gray-500">Signed by:</span>
                                    <span class="ml-1 font-medium text-gray-900">{{ record.signed_name || '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Date:</span>
                                    <span class="ml-1 font-medium text-gray-900">{{ formatDateTime(record.signed_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            v-if="acknowledgementText"
                            class="mb-6 whitespace-pre-line border border-gray-200 bg-white p-4 text-sm leading-relaxed text-gray-800 print:border-gray-400"
                        >
                            {{ acknowledgementText }}
                        </div>
                        <div class="mb-8 flex items-start gap-2 text-sm text-gray-800">
                            <span
                                class="service-ticket-signature-checkbox mt-0.5 inline-block h-4 w-4 shrink-0 border border-gray-700 bg-white"
                                aria-hidden="true"
                            />
                            <span>{{ consentLabel }}</span>
                        </div>
                        <div class="service-ticket-manual-signing-grid grid gap-8 sm:grid-cols-2 print:grid-cols-2">
                            <div>
                                <div class="service-ticket-signature-line h-12 border-b-2 border-gray-900" />
                                <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Customer signature</p>
                            </div>
                            <div>
                                <div class="service-ticket-signature-line min-h-[3rem] border-b-2 border-gray-900 pb-1 text-sm text-gray-900">
                                    {{ record.customer?.display_name || '' }}
                                </div>
                                <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Printed name</p>
                            </div>
                            <div>
                                <div class="service-ticket-signature-line min-h-[3rem] border-b-2 border-gray-900 pb-1 text-sm text-gray-900">
                                    {{ formatDate(record.created_at) }}
                                </div>
                                <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Date</p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-gray-900 text-white text-center text-xs">
                    <p>Thank you for your business!</p>
                    <p v-if="record.location?.phone" class="mt-1">
                        Questions? Call us at {{ record.location.phone }}
                    </p>
                </div>
            </div>
        </div>

        <CustomerApprovalDeliveryModal
            v-model:delivery="approvalDelivery"
            :show="showApprovalDeliveryModal"
            title="Send for approval"
            :subtitle="approvalModalSubtitle"
            :email-preview="approvalEmailPreview"
            :sms-offer="serviceTicketApprovalSms"
            :delivery-error="approvalDeliveryError"
            :processing="sendApprovalForm.processing"
            @close="closeApprovalDeliveryModal"
            @confirm="handleConfirmSendApproval"
        />
    </div>
</template>

<style>
@media print {
    /* Hide the action bar */
    .sticky {
        display: none !important;
    }

    .bg-white {
        background-color: white !important;
    }

    .shadow-lg {
        box-shadow: none !important;
    }

    .service-ticket-customer-asset-grid,
    .service-ticket-manual-signing-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .service-ticket-signature-line,
    .service-ticket-signature-checkbox {
        border-color: #111827 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    @page {
        margin: 0.5in;
    }
}
</style>