<script setup>
import { computed } from 'vue';
import PublicSignatureForm from '@/Components/Tenant/Public/PublicSignatureForm.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    /** `public` — customer review page; `preview` — staff customer preview (no live form). */
    mode: {
        type: String,
        default: 'public',
        validator: (v) => v === 'public' || v === 'preview',
    },
});

const isPreview = computed(() => props.mode === 'preview');
const isSigned = computed(() => !!props.record.signed_at);
const canSign = computed(() => props.mode === 'public' && !isSigned.value);

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

const companyName = computed(
    () => props.record.subsidiary?.display_name || props.account?.name || 'Company Name',
);

const deliveryAckBody = computed(() => {
    const raw = props.account?.delivery_ack_text;
    const tag = '[COMPANY NAME]';
    if (raw && String(raw).trim()) {
        return String(raw).split(tag).join(companyName.value);
    }
    return [
        `By signing below, you confirm that you (or your authorized representative) received the delivery described on this page, including the items listed above, in satisfactory condition unless you note otherwise in writing with ${companyName.value} at the time of delivery.`,
        '',
        `${companyName.value} may rely on this electronic signature to the same extent as a handwritten signature.`,
    ].join('\n');
});

const consentLabel = computed(
    () =>
        `I acknowledge receipt of the goods listed above. I confirm they were delivered as described and, to the best of my knowledge, are in good condition, or I have noted any exceptions with the driver before signing. I authorize ${companyName.value} to rely on this electronic signature for this delivery.`,
);

const signAction = computed(() => {
    if (!props.record?.uuid) return '#';
    return route('deliveries.sign', props.record.uuid);
});

const lineItems = computed(() => (Array.isArray(props.record?.items) ? props.record.items : []));

const itemName = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    return unit?.asset?.display_name ?? variant?.display_name ?? item.name ?? 'Asset';
};

const itemVariantLabel = (item) => (item.asset_variant ?? item.assetVariant)?.display_name ?? null;

const itemUnitLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) {
        return item.serial_number_snapshot ?? null;
    }
    return unit.display_name ?? unit.serial_number ?? unit.hin ?? unit.sku ?? null;
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '—';
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
    } catch {
        return '—';
    }
};

const customerEmail = computed(
    () => props.record.customer?.email ?? props.record.customer?.contact?.email ?? null,
);
const customerPhone = computed(
    () => props.record.customer?.phone ?? props.record.customer?.contact?.phone ?? null,
);
const customerAddressLine1 = computed(
    () => props.record.customer?.address_line1 ?? props.record.customer?.address_line_1 ?? null,
);
</script>

<template>
    <div class="bg-white shadow-lg print:shadow-none">
        <!-- Header -->
        <div class="border-b-4 border-gray-900 px-8 py-6 print:border-b-2">
            <div class="flex items-start justify-between">
                <div class="flex items-start gap-6">
                    <div v-if="effectiveLogoUrl" class="flex-shrink-0">
                        <img :src="effectiveLogoUrl" alt="Company Logo" class="h-20 w-auto max-w-[150px] object-contain" />
                    </div>
                    <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                        <span class="material-icons text-4xl text-gray-400">business</span>
                    </div>

                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ record.subsidiary?.display_name || account.name || 'Company Name' }}
                        </h1>
                        <div class="mt-2 text-sm text-gray-600 space-y-1">
                            <p v-if="record.location?.address_line_1 || record.location?.address_line1">
                                {{ record.location.address_line_1 || record.location.address_line1 }}
                                <span v-if="record.location?.address_line_2 || record.location?.address_line2"
                                    >, {{ record.location.address_line_2 || record.location.address_line2 }}</span
                                >
                            </p>
                            <p v-if="record.location?.city">
                                {{ record.location.city }}<span v-if="record.location?.state">, {{ record.location.state }}</span>
                                {{ record.location?.postal_code }}
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
                <div>
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Customer Information</h2>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="space-y-2">
                            <div class="font-semibold text-gray-900 text-lg">
                                {{ record.customer?.display_name || '—' }}
                            </div>
                            <div v-if="customerEmail" class="flex items-center gap-2 text-sm text-gray-600">
                                <span class="material-icons text-sm">email</span>
                                {{ customerEmail }}
                            </div>
                            <div v-if="customerPhone" class="flex items-center gap-2 text-sm text-gray-600">
                                <span class="material-icons text-sm">phone</span>
                                {{ customerPhone }}
                            </div>
                            <div v-if="customerAddressLine1" class="flex items-start gap-2 text-sm text-gray-600 mt-3">
                                <span class="material-icons text-sm mt-0.5">location_on</span>
                                <div>
                                    <div>{{ customerAddressLine1 }}</div>
                                    <div v-if="record.customer?.address_line2 || record.customer?.address_line_2">
                                        {{ record.customer.address_line2 || record.customer.address_line_2 }}
                                    </div>
                                    <div v-if="record.customer?.city">
                                        {{ record.customer.city }}<span v-if="record.customer?.state">, {{ record.customer.state }}</span>
                                        {{ record.customer?.postal_code }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="(record.asset_unit || record.assetUnit) && lineItems.length === 0">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Asset Information</h2>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="space-y-2">
                            <div class="font-semibold text-gray-900 text-lg">
                                {{ (record.asset_unit || record.assetUnit)?.display_name || '—' }}
                            </div>
                            <div
                                v-if="(record.asset_unit || record.assetUnit)?.asset?.make?.display_name"
                                class="text-sm text-gray-600"
                            >
                                <span class="font-medium">Make:</span>
                                {{ (record.asset_unit || record.assetUnit).asset.make.display_name }}
                            </div>
                            <div v-if="(record.asset_unit || record.assetUnit)?.asset?.year" class="text-sm text-gray-600">
                                <span class="font-medium">Year:</span>
                                {{ (record.asset_unit || record.assetUnit).asset.year }}
                            </div>
                            <div v-if="(record.asset_unit || record.assetUnit)?.serial_number" class="text-sm text-gray-600">
                                <span class="font-medium">Serial:</span>
                                {{ (record.asset_unit || record.assetUnit).serial_number }}
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
            <div v-if="record.customer_notes" class="mt-4">
                <span class="text-sm font-medium text-gray-600">Notes:</span>
                <p class="text-sm text-gray-900 mt-1 whitespace-pre-line">{{ record.customer_notes }}</p>
            </div>
        </div>

        <!-- Line items -->
        <div class="px-8 py-6 border-t border-gray-200">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Items in this delivery</h2>
            <div v-if="lineItems.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
                No line items are recorded for this delivery.
            </div>
            <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Asset</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Variant</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-700">Unit / serial</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in lineItems" :key="item.id">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ itemName(item) }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ itemVariantLabel(item) ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ itemUnitLabel(item) ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Already signed (public only) -->
        <div v-if="isSigned && !isPreview" class="px-8 py-6 border-t-2 border-gray-900 bg-green-50">
            <div class="text-center">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="material-icons text-2xl text-green-600">check_circle</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-green-900 mb-2">Delivery Confirmed</h3>
                <p class="text-sm text-green-700 mb-4">
                    This delivery has been signed and confirmed by {{ record.recipient_name || 'the recipient' }} on
                    {{ formatDate(record.signed_at) }}.
                </p>
                <div v-if="record.signature_url" class="text-center">
                    <p class="text-sm text-gray-600 mb-2">Signature:</p>
                    <img :src="record.signature_url" alt="Signature" class="max-w-xs mx-auto border border-gray-300 rounded" />
                </div>
            </div>
        </div>

        <!-- Live signature form (public, unsigned) -->
        <div v-else-if="canSign" class="px-8 py-8 border-t-2 border-gray-900 print:hidden">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-6">Customer Signature</h2>
            <PublicSignatureForm
                :action="signAction"
                submit-label="Confirm delivery & sign"
                :acknowledgement-text="deliveryAckBody"
                :consent-label="consentLabel"
                :include-recipient-name="true"
            />
        </div>

        <!-- Preview: blank signature block -->
        <div v-else-if="isPreview && !isSigned" class="px-8 py-6 border-t-2 border-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Customer Signature</h2>
            <div v-if="deliveryAckBody" class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-line">{{ deliveryAckBody }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                <div>
                    <div class="border-b-2 border-gray-900 pb-1 mb-2 h-24"></div>
                    <div class="text-sm text-gray-600">Customer Signature</div>
                </div>
                <div>
                    <div class="border-b-2 border-gray-900 pb-1 mb-2 h-24"></div>
                    <div class="text-sm text-gray-600">Date</div>
                </div>
            </div>
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
</template>
