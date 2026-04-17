<script setup>
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
});

const items = computed(() => Array.isArray(props.record?.items) ? props.record.items : []);

const itemName = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    const variant = item.asset_variant ?? item.assetVariant ?? null;
    return unit?.asset?.display_name ?? variant?.display_name ?? item.name ?? 'Asset';
};
const itemVariantLabel = (item) => (item.asset_variant ?? item.assetVariant)?.display_name ?? null;
const itemUnitLabel = (item) => {
    const unit = item.asset_unit ?? item.assetUnit ?? null;
    if (!unit) return item.serial_number_snapshot ?? null;
    return unit.display_name ?? unit.serial_number ?? unit.hin ?? unit.sku ?? null;
};

const formatDate = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch { return '—'; }
};
const formatDateTime = (v) => {
    if (!v) return '—';
    try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    } catch { return '—'; }
};
</script>

<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="border-b-4 border-gray-900 px-8 py-6">
            <div class="flex items-start justify-between gap-6">
                <div class="flex items-start gap-6">
                    <div v-if="account?.logo_url" class="flex-shrink-0">
                        <img :src="account.logo_url" alt="Company Logo" class="h-20 w-auto object-contain" />
                    </div>
                    <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                        <span class="material-icons text-4xl text-gray-400">business</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ record.subsidiary?.display_name || account?.name || 'Company Name' }}
                        </h1>
                        <div class="mt-1 text-sm text-gray-600 space-y-0.5">
                            <p v-if="account?.phone">{{ account.phone }}</p>
                            <p v-if="account?.email">{{ account.email }}</p>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium text-gray-600 uppercase tracking-wide">Delivery</div>
                    <div class="text-3xl font-bold text-gray-900 font-mono">{{ record.display_name }}</div>
                    <div class="text-sm text-gray-600 mt-1">{{ formatDate(record.scheduled_at ?? record.created_at) }}</div>
                </div>
            </div>
        </div>

        <!-- Customer + Deliver To -->
        <div class="px-8 py-6 bg-gray-50 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Customer</div>
                <div class="bg-white rounded border border-gray-200 p-4 space-y-1">
                    <div class="font-semibold text-gray-900">
                        {{ record.customer?.display_name || record.customer?.contact?.display_name || '—' }}
                    </div>
                    <div v-if="record.customer?.contact?.email" class="text-sm text-gray-600">{{ record.customer.contact.email }}</div>
                    <div v-if="record.customer?.contact?.phone" class="text-sm text-gray-600">{{ record.customer.contact.phone }}</div>
                </div>
            </div>
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Deliver To</div>
                <div class="bg-white rounded border border-gray-200 p-4 text-sm text-gray-700 space-y-0.5">
                    <div v-if="record.delivery_location?.name" class="font-semibold text-gray-900">
                        {{ record.delivery_location.name }}
                    </div>
                    <div v-if="record.address_line_1">{{ record.address_line_1 }}</div>
                    <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
                    <div v-if="record.city || record.state || record.postal_code">
                        <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span>
                        <span v-if="record.postal_code"> {{ record.postal_code }}</span>
                    </div>
                    <div v-if="!record.address_line_1 && !record.city" class="text-gray-400 italic">No address recorded</div>
                </div>
            </div>
        </div>

        <!-- Schedule -->
        <div class="px-8 py-5 border-t border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Scheduled</div>
                <div class="text-sm text-gray-900">{{ formatDateTime(record.scheduled_at) }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Estimated Arrival</div>
                <div class="text-sm text-gray-900">{{ formatDateTime(record.estimated_arrival_at) }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Delivered</div>
                <div class="text-sm text-gray-900">{{ formatDateTime(record.delivered_at) }}</div>
            </div>
        </div>

        <!-- Assets -->
        <div class="px-8 py-6 border-t border-gray-200">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Assets</div>
            <div v-if="!items.length" class="text-sm text-gray-500 italic">No assets recorded for this delivery.</div>
            <table v-else class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Asset</th>
                        <th class="px-3 py-2 text-left font-semibold">Variant</th>
                        <th class="px-3 py-2 text-left font-semibold">Unit / Serial</th>
                        <th class="px-3 py-2 text-right font-semibold">Qty</th>
                        <th class="px-3 py-2 text-center font-semibold">Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-t border-gray-200">
                        <td class="px-3 py-2 text-gray-900">
                            <div class="font-medium">{{ itemName(item) }}</div>
                        </td>
                        <td class="px-3 py-2 text-gray-700">{{ itemVariantLabel(item) ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ itemUnitLabel(item) ?? '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-700">{{ Number(item.quantity ?? 1) }}</td>
                        <td class="px-3 py-2 text-center">
                            <span
                                class="inline-flex items-center justify-center w-5 h-5 border-2 border-gray-900 rounded-sm"
                                :class="item.delivered_at ? 'bg-gray-900' : 'bg-white'"
                            >
                                <svg v-if="item.delivered_at" viewBox="0 0 20 20" fill="white" class="w-3.5 h-3.5">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.8 6.79-6.8a1 1 0 011.42 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Customer notes -->
        <div v-if="record.customer_notes" class="px-8 py-6 border-t border-gray-200">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notes</div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ record.customer_notes }}</p>
        </div>

        <!-- Signature block -->
        <div class="px-8 py-6 border-t-2 border-gray-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <div class="border-b-2 border-gray-900 h-20"></div>
                    <div class="text-sm text-gray-600 mt-1">Customer Signature</div>
                </div>
                <div>
                    <div class="border-b-2 border-gray-900 h-20"></div>
                    <div class="text-sm text-gray-600 mt-1">Date</div>
                </div>
            </div>
            <div class="mt-6">
                <div class="border-b border-gray-900"></div>
                <div class="text-sm text-gray-600 mt-1">Print Name</div>
            </div>
        </div>
    </div>
</template>
