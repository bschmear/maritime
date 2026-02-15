<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    account: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close']);

const sending = ref(false);
const printing = ref(false);

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

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};

const handleSendEmail = () => {
    if (confirm('Send this service ticket to the customer via email?')) {
        sending.value = true;
        router.post(route('servicetickets.send-email', props.record.id), {}, {
            preserveState: true,
            onSuccess: () => {
                alert('Service ticket sent successfully!');
                sending.value = false;
            },
            onError: () => {
                alert('Failed to send service ticket. Please try again.');
                sending.value = false;
            }
        });
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Action Bar - Hidden when printing -->
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Customer Preview
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            This is how the service ticket will appear to the customer
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            @click="$emit('close')"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-sm">close</span>
                            Close
                        </button>
                        <button
                            @click="handleSendEmail"
                            :disabled="sending"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                        >
                            <span v-if="sending" class="material-icons text-sm animate-spin">refresh</span>
                            <span v-else class="material-icons text-sm">email</span>
                            {{ sending ? 'Sending...' : 'Send Email' }}
                        </button>
                        <button
                            @click="handlePrint"
                            :disabled="printing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                        >
                            <span v-if="printing" class="material-icons text-sm animate-spin">refresh</span>
                            <span v-else class="material-icons text-sm">print</span>
                            {{ printing ? 'Preparing...' : 'Print' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Printable Document -->
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div class="bg-white shadow-lg print:shadow-none">
                <!-- Company Header -->
                <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-6">
                            <!-- Company Logo -->
                            <div v-if="account.logo_url" class="flex-shrink-0">
                                <img :src="account.logo_url" alt="Company Logo" class="h-20 w-auto object-contain" />
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
                <div class="px-8 py-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                                    <div v-if="record.asset_unit?.model" class="text-sm text-gray-600">
                                        <span class="font-medium">Model:</span> {{ record.asset_unit.model }}
                                    </div>
                                    <div v-if="record.asset_unit?.year" class="text-sm text-gray-600">
                                        <span class="font-medium">Year:</span> {{ record.asset_unit.year }}
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
                    
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-900">
                                <th class="text-left py-3 text-sm font-semibold text-gray-900">Description</th>
                                <th class="text-center py-3 text-sm font-semibold text-gray-900">Qty</th>
                                <th class="text-center py-3 text-sm font-semibold text-gray-900">Type</th>
                                <th class="text-center py-3 text-sm font-semibold text-gray-900">Est Hrs</th>
                                <th class="text-right py-3 text-sm font-semibold text-gray-900">Rate</th>
                                <th class="text-right py-3 text-sm font-semibold text-gray-900">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(item, index) in billableLineItems" :key="index" class="hover:bg-gray-50">
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-gray-900">{{ item.display_name }}</div>
                                    <div v-if="item.description && item.description !== item.display_name" class="text-sm text-gray-600 mt-1">
                                        {{ item.description }}
                                    </div>
                                    <div v-if="item.warranty" class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium mt-1">
                                        <span class="material-icons text-xs">verified_user</span>
                                        Warranty
                                    </div>
                                </td>
                                <td class="py-3 text-center text-gray-900">
                                    {{ item.quantity }}
                                </td>
                                <td class="py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
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

                    <!-- Empty State -->
                    <div v-if="billableLineItems.length === 0" class="text-center py-8 text-gray-500">
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
                <div class="px-8 py-6 border-t-2 border-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Customer Authorization</h2>
                    
                    <!-- Acknowledgment Text -->
                    <div v-if="account.service_ticket_ack_text" class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">
                            {{ account.service_ticket_ack_text.replace('[COMPANY NAME]', record.subsidiary?.display_name || 'Company Name') }}
                        </p>
                    </div>

                    <!-- Signature Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                        <div>
                            <div class="border-b-2 border-gray-900 pb-1 mb-2 h-24"></div>
                            <div class="text-sm text-gray-600">Customer Signature</div>
                        </div>
                        <div>
                            <div class="border-b-2 border-gray-900 pb-1 mb-2 h-24"></div>
                            <div class="text-sm text-gray-600">Date</div>
                        </div>
                    </div>

                    <!-- Print Name -->
                    <div class="mt-6">
                        <div class="border-b border-gray-900 pb-1 mb-2"></div>
                        <div class="text-sm text-gray-600">Print Name</div>
                    </div>
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
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    
    .bg-white {
        background-color: white !important;
    }
    
    .shadow-lg {
        box-shadow: none !important;
    }
    
    @page {
        margin: 0.5in;
    }
}
</style>