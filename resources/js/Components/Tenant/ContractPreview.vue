<script setup>
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
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close']);

const printing = ref(false);

const statusEnumKey = 'App\\Enums\\Contract\\ContractStatus';
const paymentEnumKey = 'App\\Enums\\Contract\\ContractPaymentStatus';

const formatCurrency = (value) => {
    if (value == null) {
        return '$0.00';
    }
    return `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }
        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return '—';
    }
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);
    } catch {
        return '—';
    }
};

const statusOption = computed(() => {
    const opts = props.enumOptions[statusEnumKey] || [];
    return opts.find((o) => o.value === props.record.status || o.id === props.record.status);
});

const paymentOption = computed(() => {
    const opts = props.enumOptions[paymentEnumKey] || [];
    return opts.find((o) => o.value === props.record.payment_status || o.id === props.record.payment_status);
});

const accountDisplayName = computed(() => {
    return props.account?.settings?.business_name
        || props.account?.business_name
        || 'Company';
});

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Contract preview
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Read-only summary for sharing or printing
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            @click="emit('close')"
                        >
                            <span class="material-icons text-sm">close</span>
                            Close
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 rounded-lg transition-colors"
                            :disabled="printing"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons text-sm animate-spin">refresh</span>
                            <span v-else class="material-icons text-sm">print</span>
                            {{ printing ? 'Preparing…' : 'Print' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <div class="bg-white dark:bg-gray-800 shadow-lg print:shadow-none rounded-lg overflow-hidden">
                <div class="border-b-4 border-gray-900 dark:border-gray-100 px-8 py-6 print:border-b-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-6">
                            <div v-if="account.logo_url" class="flex-shrink-0">
                                <img :src="account.logo_url" alt="Logo" class="h-20 w-auto object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ accountDisplayName }}
                                </h1>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase">
                                Contract
                            </div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white font-mono">
                                {{ record.contract_number || `#${record.id}` }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ formatDate(record.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-4 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                    <span
                        v-if="statusOption"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                        :class="statusOption.bgClass || 'bg-gray-200 dark:bg-gray-700'"
                    >
                        {{ statusOption.name }}
                    </span>
                    <span
                        v-if="paymentOption"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                        :class="paymentOption.bgClass || 'bg-gray-200 dark:bg-gray-700'"
                    >
                        Payment: {{ paymentOption.name }}
                    </span>
                    <span
                        v-if="record.signature_required"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                    >
                        Signature required
                    </span>
                </div>

                <div class="px-8 py-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-900/40">
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            Contact
                        </h2>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 space-y-2">
                            <div class="font-semibold text-lg text-gray-900 dark:text-white">
                                {{ record.contact?.display_name || '—' }}
                            </div>
                            <div v-if="record.contact?.email" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ record.contact.email }}
                            </div>
                            <div v-if="record.contact?.phone" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ record.contact.phone }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            Amount
                        </h2>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(record.total_amount) }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ record.currency || 'USD' }}
                            </div>
                            <div v-if="record.estimate" class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                Estimate: {{ record.estimate.display_name || `EST-${record.estimate.sequence}` }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="record.signed_at" class="px-8 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Signature
                    </h2>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Signed {{ formatDateTime(record.signed_at) }}
                        <span v-if="record.signed_name"> by {{ record.signed_name }}</span>
                        <span v-if="record.signed_email"> ({{ record.signed_email }})</span>
                    </p>
                </div>

                <div class="px-8 py-6 border-t border-gray-200 dark:border-gray-700">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Billing address
                    </h2>
                    <div class="text-sm text-gray-800 dark:text-gray-200 space-y-1">
                        <div v-if="record.billing_address_line1">{{ record.billing_address_line1 }}</div>
                        <div v-if="record.billing_address_line2">{{ record.billing_address_line2 }}</div>
                        <div v-if="record.billing_city || record.billing_state || record.billing_postal">
                            {{ [record.billing_city, record.billing_state, record.billing_postal].filter(Boolean).join(', ') }}
                        </div>
                        <div v-if="record.billing_country">{{ record.billing_country }}</div>
                        <div v-if="!record.billing_address_line1 && !record.billing_city" class="text-gray-500">
                            —
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 border-t border-gray-200 dark:border-gray-700">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Terms &amp; notes
                    </h2>
                    <div class="space-y-4 text-sm text-gray-800 dark:text-gray-200">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Payment terms</div>
                            <p class="whitespace-pre-line">{{ record.payment_terms || '—' }}</p>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Delivery terms</div>
                            <p class="whitespace-pre-line">{{ record.delivery_terms || '—' }}</p>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white mb-1">Notes</div>
                            <p class="whitespace-pre-line">{{ record.notes || '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
