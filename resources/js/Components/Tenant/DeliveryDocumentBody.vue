<script setup>
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, default: () => ({}) },
    /** `customer` — leave-behind for the customer; `internal` — office / driver with ops details. */
    variant: {
        type: String,
        default: 'customer',
        validator: (v) => v === 'customer' || v === 'internal',
    },
});

const isCustomer = computed(() => props.variant === 'customer');
const isInternal = computed(() => props.variant === 'internal');

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

const travelMinutes = computed(() => {
    const s = props.record?.estimated_travel_duration_seconds;
    if (s == null || !Number.isFinite(Number(s)) || Number(s) <= 0) {
        return null;
    }
    return Math.round(Number(s) / 60);
});

const fleetLabel = (fleet) => {
    if (!fleet) return null;
    const n = fleet.display_name ?? fleet.name ?? null;
    if (n != null && String(n).trim() !== '') {
        return String(n).trim();
    }
    return fleet.id ? `Unit #${fleet.id}` : null;
};

const workOrderLabel = computed(() => {
    const r = props.record;
    const wo = r.work_order ?? r.workOrder;
    if (wo) {
        return `WO-${wo.work_order_number ?? wo.id}`;
    }
    return r.work_order_id ? `#${r.work_order_id}` : '—';
});

const transactionLabel = computed(() => {
    const r = props.record;
    return r.transaction?.display_name || (r.transaction_id ? `#${r.transaction_id}` : '—');
});

function technicianLabel(u) {
    if (!u) return '—';
    if (u.display_name) return u.display_name;
    const parts = [u.first_name, u.last_name].filter(Boolean);
    return parts.length ? parts.join(' ') : '—';
}
</script>

<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="border-b-2 border-gray-900 px-6 py-3">
            <div
                v-if="isCustomer"
                class="mb-2 rounded border border-gray-400 bg-gray-50 px-2 py-1 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-700"
            >
                Customer copy
            </div>
            <div
                v-else
                class="mb-2 rounded border border-amber-600 bg-amber-50 px-2 py-1 text-center text-[11px] font-semibold uppercase tracking-wide text-amber-900"
            >
                Internal copy — not for customer
            </div>
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0 flex-1">
                    <div v-if="account?.logo_url" class="flex-shrink-0 max-w-[100px]">
                        <img :src="account.logo_url" alt="" class="max-h-10 w-full object-contain object-left" />
                    </div>
                    <div v-else class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded flex items-center justify-center">
                        <span class="material-icons text-xl text-gray-400">business</span>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base font-bold text-gray-900 leading-tight truncate">
                            {{ record.subsidiary?.display_name || account?.name || 'Company Name' }}
                        </h1>
                        <div class="mt-0.5 text-[11px] text-gray-600 leading-snug space-y-0">
                            <p v-if="account?.phone">{{ account.phone }}</p>
                            <p v-if="account?.email">{{ account.email }}</p>
                        </div>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Delivery</div>
                    <div class="text-lg font-bold text-gray-900 font-mono leading-tight">{{ record.display_name }}</div>
                    <div class="text-[11px] text-gray-600 mt-0.5">{{ formatDate(record.scheduled_at ?? record.created_at) }}</div>
                </div>
            </div>
        </div>

        <!-- Customer + Deliver To (single row) -->
        <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 grid grid-cols-2 gap-4 text-xs">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Customer</div>
                <div class="font-semibold text-gray-900 leading-snug">
                    {{ record.customer?.display_name || record.customer?.contact?.display_name || '—' }}
                </div>
                <div v-if="record.customer?.contact?.email" class="text-gray-600 truncate">{{ record.customer.contact.email }}</div>
                <div v-if="record.customer?.contact?.phone" class="text-gray-600">{{ record.customer.contact.phone }}</div>
            </div>
            <div class="min-w-0 border-l border-gray-200 pl-4">
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Deliver To</div>
                <div v-if="record.delivery_location?.name || record.deliveryLocation?.display_name || record.deliveryLocation?.name" class="font-semibold text-gray-900">
                    {{ record.delivery_location?.name ?? record.deliveryLocation?.display_name ?? record.deliveryLocation?.name }}
                </div>
                <div v-if="record.address_line_1" class="text-gray-800 leading-snug">{{ record.address_line_1 }}</div>
                <div v-if="record.address_line_2" class="text-gray-700">{{ record.address_line_2 }}</div>
                <div v-if="record.city || record.state || record.postal_code" class="text-gray-700">
                    <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span>
                    <span v-if="record.postal_code"> {{ record.postal_code }}</span>
                </div>
                <div v-if="!record.address_line_1 && !record.city" class="text-gray-400 italic">No address recorded</div>
            </div>
        </div>

        <!-- Schedule -->
        <div
            class="px-6 py-3 border-t border-gray-200 grid gap-3 text-xs"
            :class="isInternal ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-3'"
        >
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
            <template v-if="isInternal">
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Leave by</div>
                    <div class="text-sm text-gray-900">{{ formatDateTime(record.time_to_leave_by) }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Est. travel (one way)</div>
                    <div class="text-sm text-gray-900">{{ travelMinutes != null ? `${travelMinutes} min` : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">At-location (plan)</div>
                    <div class="text-sm text-gray-900">
                        {{ record.delivery_duration_minutes != null ? `${record.delivery_duration_minutes} min` : '—' }}
                    </div>
                </div>
            </template>
        </div>

        <!-- Internal operations (internal copy only) -->
        <div v-if="isInternal" class="px-6 py-3 border-t border-gray-200 text-xs">
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Operations</div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <span class="font-semibold text-gray-600">Technician:</span>
                    <span class="ml-1 text-gray-900">{{ technicianLabel(record.technician) }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-600">Origin / location:</span>
                    <span class="ml-1 text-gray-900">{{ record.location?.display_name ?? '—' }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-600">Truck:</span>
                    <span class="ml-1 text-gray-900">{{ fleetLabel(record.fleet_truck ?? record.fleetTruck) ?? '—' }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-600">Trailer:</span>
                    <span class="ml-1 text-gray-900">{{ fleetLabel(record.fleet_trailer ?? record.fleetTrailer) ?? '—' }}</span>
                </div>
                <div v-if="record.work_order_id || record.work_order || record.workOrder" class="sm:col-span-2">
                    <span class="font-semibold text-gray-600">Work order:</span>
                    <span class="ml-1 font-mono text-gray-900">{{ workOrderLabel }}</span>
                </div>
                <div v-if="record.transaction_id || record.transaction" class="sm:col-span-2">
                    <span class="font-semibold text-gray-600">Transaction:</span>
                    <span class="ml-1 text-gray-900">{{ transactionLabel }}</span>
                </div>
            </div>
        </div>

        <!-- Assets -->
        <div class="px-6 py-3 border-t border-gray-200">
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Assets</div>
            <div v-if="!items.length" class="text-sm text-gray-500 italic">No assets recorded for this delivery.</div>
            <table v-else class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Asset</th>
                        <th class="px-3 py-2 text-left font-semibold">Variant</th>
                        <th class="px-3 py-2 text-left font-semibold">Unit / Serial</th>
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
        <div v-if="record.customer_notes" class="px-6 py-3 border-t border-gray-200">
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Customer notes</div>
            <p class="text-xs text-gray-700 whitespace-pre-line">{{ record.customer_notes }}</p>
        </div>

        <!-- Internal notes (internal copy only) -->
        <div v-if="isInternal && record.internal_notes" class="px-6 py-3 border-t border-gray-200">
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Internal notes</div>
            <p class="text-xs text-gray-700 whitespace-pre-line">{{ record.internal_notes }}</p>
        </div>

        <!-- Signature block -->
        <div class="px-6 py-4 border-t-2 border-gray-900">
            <template v-if="isCustomer">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <div class="h-14 border-b-2 border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Customer Signature</div>
                    </div>
                    <div>
                        <div class="h-14 border-b-2 border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Date</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="border-b border-gray-900"></div>
                    <div class="mt-1 text-[11px] text-gray-600">Print Name</div>
                </div>
            </template>
            <template v-else>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <div class="h-14 border-b-2 border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Technician / driver signature</div>
                    </div>
                    <div>
                        <div class="h-14 border-b-2 border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Date</div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-6">
                    <div>
                        <div class="h-12 border-b border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Dispatcher / office</div>
                    </div>
                    <div>
                        <div class="h-12 border-b border-gray-900"></div>
                        <div class="mt-1 text-[11px] text-gray-600">Date</div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
