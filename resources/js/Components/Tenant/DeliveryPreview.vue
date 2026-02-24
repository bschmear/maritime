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
    checklistItems: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const sending = ref(false);
const printing = ref(false);

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

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};

const handleSendSignatureRequest = () => {
    if (confirm('Send signature request to the customer? This will allow them to review and sign the delivery document online.')) {
        sending.value = true;
        router.post(route('deliveries.send-signature-request', props.record.id), {}, {
            preserveState: true,
            onSuccess: () => {
                alert('Signature request sent successfully!');
                sending.value = false;
            },
            onError: (errors) => {
                let message = 'Failed to send signature request.';
                if (errors && errors.message) {
                    message += ' ' + errors.message;
                }
                alert(message + ' Please try again.');
                sending.value = false;
            }
        });
    }
};

// Group checklist items by category for display
const itemsByCategory = computed(() => {
    const grouped = {};
    (props.checklistItems || []).forEach(item => {
        const catId = item.category_id ?? item.category?.id ?? 'uncategorized';
        const catName = item.category?.name ?? 'Other';
        if (!grouped[catId]) {
            grouped[catId] = { id: catId, name: catName, items: [] };
        }
        grouped[catId].items.push(item);
    });
    return Object.values(grouped).sort((a, b) => a.name.localeCompare(b.name));
});
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
                            This is how the delivery document will appear to the customer
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
                            v-if="!record.signed_at"
                            @click="handleSendSignatureRequest"
                            :disabled="sending"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
                        >
                            <span v-if="sending" class="material-icons text-sm animate-spin">refresh</span>
                            <span v-else class="material-icons text-sm">send</span>
                            {{ sending ? 'Sending...' : 'Send Signature Request' }}
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
                                    {{ record.subsidiary?.display_name || account.name || 'Company Name' }}
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

                        <!-- Delivery Number -->
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 uppercase">Delivery</div>
                            <div class="text-3xl font-bold text-gray-900 font-mono">
                                {{ record.display_name }}
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

                <!-- Delivery Information -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Delivery Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-600">Scheduled:</span>
                            <div class="text-sm text-gray-900">{{ formatDateTime(record.scheduled_at) }}</div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Estimated Arrival:</span>
                            <div class="text-sm text-gray-900">{{ formatDateTime(record.estimated_arrival_at) }}</div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Delivered:</span>
                            <div class="text-sm text-gray-900">{{ record.delivered_at ? formatDateTime(record.delivered_at) : '—' }}</div>
                        </div>
                    </div>
                    <div v-if="record.address_line_1" class="mt-4">
                        <span class="text-sm font-medium text-gray-600">Delivery Address:</span>
                        <div class="text-sm text-gray-900 mt-1">
                            <div>{{ record.address_line_1 }}</div>
                            <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
                            <div>{{ record.city }}, {{ record.state }} {{ record.postal_code }}</div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Checklist -->
                <div class="px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Delivery Checklist</h2>

                    <div class="space-y-6">
                        <div
                            v-for="category in itemsByCategory"
                            :key="category.id"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <h3 class="font-semibold text-gray-900 mb-3">{{ category.name }}</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="item in category.items"
                                    :key="item.id"
                                    class="flex items-center gap-3"
                                >
                                    <div class="flex-shrink-0 w-4 h-4 rounded border-2 flex items-center justify-center" :class="item.completed ? 'bg-green-600 border-green-600' : 'bg-red-600 border-red-600'">
                                        <span v-if="item.completed" class="material-icons text-xs text-white">check</span>
                                        <span v-else class="material-icons text-xs text-white">close</span>
                                    </div>
                                    <span class="text-sm text-gray-900">{{ item.label }}</span>
                                    <span v-if="item.is_required" class="text-red-500 text-xs">*</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Acknowledgment & Signature -->
                <div class="px-8 py-6 border-t-2 border-gray-900">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Customer Authorization</h2>

                    <!-- Acknowledgment Text -->
                    <div v-if="account.delivery_ack_text" class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">
                            {{ account.delivery_ack_text.replace('[COMPANY NAME]', record.subsidiary?.display_name || account.name || 'Company Name') }}
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
                    <p v-else-if="account.phone" class="mt-1">
                        Questions? Call us at {{ account.phone }}
                    </p>
                </div>
            </div>
        </div>
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

    @page {
        margin: 0.5in;
    }
}
</style>