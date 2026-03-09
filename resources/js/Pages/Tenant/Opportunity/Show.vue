<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'opportunities' },
    recordTitle: { type: String, default: 'Opportunity' },
    domainName: { type: String, default: 'Opportunity' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    qualificationEnumOptions: { type: Object, default: () => ({}) },
});

const opportunityLabel = computed(() =>
    props.record?.sequence ? `OPP-${props.record.sequence}` : `Opportunity #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Opportunities', href: route('opportunities.index') },
    { label: opportunityLabel.value },
]);

const formatCurrency = (value) =>
    value != null && value !== '' ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getEnumLabel = (options, value) => {
    if (!options?.length || value == null) return '—';
    const opt = options.find(o => o.id == value || o.value == value);
    return opt ? opt.name : String(value);
};

const stageLabel = computed(() =>
    getEnumLabel(props.enumOptions['App\\Enums\\Opportunity\\Stage'], props.record?.stage)
);
const statusLabel = computed(() =>
    getEnumLabel(props.enumOptions['App\\Enums\\Opportunity\\Status'], props.record?.status)
);

const lineItems = computed(() => props.record?.inventory_items ?? []);
const lineTotal = (item) => Number(item.pivot?.unit_price || 0) * Number(item.pivot?.quantity || 0);
const lineItemsSubtotal = computed(() => lineItems.value.reduce((sum, item) => sum + lineTotal(item), 0));

const qualification = computed(() => props.record?.qualification ?? null);

const qualBudgetLabel = computed(() =>
    getEnumLabel(props.qualificationEnumOptions?.budget_range, qualification.value?.budget_range)
);
const qualTimelineLabel = computed(() =>
    getEnumLabel(props.qualificationEnumOptions?.purchase_timeline, qualification.value?.purchase_timeline)
);

const handleDelete = () => {
    if (!confirm('Are you sure you want to delete this opportunity?')) return;
    router.delete(route('opportunities.destroy', props.record.id));
};
</script>

<template>
    <Head :title="`${opportunityLabel} - Opportunity`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ opportunityLabel }}
                    </h2>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('opportunities.edit', record.id)"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </Link>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ============================
                     Main Column
                     ============================ -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">OPPORTUNITY</h1>
                                    <p class="text-primary-100 text-sm mt-1">Sales opportunity details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ opportunityLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Customer + Qualification + Salesperson / Deal Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: People -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer & Lead
                                    </h3>

                                    <!-- Customer -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.customer_id?.label || 'Customer' }}
                                        </div>
                                        <Link
                                            v-if="record.customer"
                                            :href="route('customers.show', record.customer_id)"
                                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                        >
                                            {{ record.customer.display_name }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                    </div>

                                    <!-- Qualification -->
                                    <div v-if="record.qualification_id">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.qualification_id?.label || 'Qualification' }}
                                        </div>
                                        <Link
                                            v-if="record.qualification"
                                            :href="route('qualifications.show', record.qualification_id)"
                                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                        >
                                            {{ record.qualification.display_name }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                    </div>

                                    <!-- Salesperson -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.user_id?.label || 'Salesperson' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.salesperson?.display_name ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Deal Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Deal Details
                                    </h3>

                                    <!-- Stage -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.stage?.label || 'Stage' }}
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ stageLabel }}
                                        </span>
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.status?.label || 'Status' }}
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                            {{ statusLabel }}
                                        </span>
                                    </div>

                                    <!-- Expected Close Date -->
                                    <div v-if="fieldsSchema.expected_close_date">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.expected_close_date?.label || 'Expected Close Date' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ formatDate(record.expected_close_date) }}
                                        </div>
                                    </div>

                                    <!-- Probability -->
                                    <div v-if="fieldsSchema.probability">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.probability?.label || 'Probability' }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                <div
                                                    class="bg-primary-600 h-1.5 rounded-full transition-all"
                                                    :style="{ width: `${record.probability ?? 0}%` }"
                                                ></div>
                                            </div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-10 text-right">
                                                {{ record.probability ?? 0 }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Requirements -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
                                    Product Requirements
                                </h3>
                                <div class="flex flex-wrap gap-3">
                                    <span
                                        :class="record.needs_engine
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="record.needs_engine" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_engine?.label || 'Needs Engine' }}
                                    </span>
                                    <span
                                        :class="record.needs_trailer
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="record.needs_trailer" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_trailer?.label || 'Needs Trailer' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div
                                v-if="record.customer_notes || record.internal_notes"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4"
                            >
                                <div v-if="record.customer_notes">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        {{ fieldsSchema.customer_notes?.label || 'Customer Notes' }}
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                        {{ record.customer_notes }}
                                    </div>
                                </div>
                                <div v-if="record.internal_notes">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        {{ fieldsSchema.internal_notes?.label || 'Internal Notes' }}
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                        {{ record.internal_notes }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================
                         Inventory Line Items
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Inventory Items</h2>
                        </div>

                        <div v-if="lineItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">SKU</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="item in lineItems"
                                        :key="item.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                    >
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.display_name }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ item.sku || '—' }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.pivot?.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.pivot?.quantity ?? 1 }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineTotal(item)) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-[160px]">{{ item.pivot?.notes || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Items Subtotal</td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-sm text-gray-400 dark:text-gray-500">No inventory items attached</p>
                        </div>
                    </div>

                    <!-- ============================
                         Qualification Product Requirements
                         ============================ -->
                    <div v-if="qualification" class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-700 dark:from-purple-700 dark:to-purple-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-bold text-white">Qualification Details</h2>
                                    <p class="text-purple-100 text-xs mt-0.5">Product requirements from the linked qualification</p>
                                </div>
                                <Link
                                    :href="route('qualifications.show', record.qualification_id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-white/20 hover:bg-white/30 rounded-lg transition-colors"
                                >
                                    View Qualification
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </Link>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-5">

                                <!-- Desired Brand -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Desired Brand</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.desired_brand?.display_name ?? '—' }}
                                    </div>
                                </div>

                                <!-- Desired Model -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Desired Model</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.desired_model || '—' }}</div>
                                </div>

                                <!-- Preferred Length -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Preferred Length</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.preferred_length ? `${qualification.preferred_length} ft` : '—' }}
                                    </div>
                                </div>

                                <!-- Max Weight -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Max Weight</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ qualification.max_weight ? `${qualification.max_weight} lbs` : '—' }}
                                    </div>
                                </div>

                                <!-- Budget Range -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Budget Range</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualBudgetLabel }}</div>
                                </div>

                                <!-- Purchase Timeline -->
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Purchase Timeline</div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualTimelineLabel }}</div>
                                </div>

                                <!-- Boolean badges row -->
                                <div class="col-span-full flex flex-wrap gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <span
                                        :class="qualification.needs_engine
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="qualification.needs_engine" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Needs Engine
                                    </span>
                                    <span
                                        :class="qualification.needs_trailer
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="qualification.needs_trailer" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Needs Trailer
                                    </span>
                                    <span
                                        :class="qualification.requires_delivery
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="qualification.requires_delivery" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Requires Delivery
                                    </span>
                                </div>

                                <!-- Delivery location details (only when requires_delivery) -->
                                <template v-if="qualification.requires_delivery">
                                    <div v-if="qualification.delivery_location">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Delivery Location</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_location }}</div>
                                    </div>
                                    <div v-if="qualification.delivery_state">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">State</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_state }}</div>
                                    </div>
                                    <div v-if="qualification.delivery_country">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Country</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ qualification.delivery_country }}</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                        <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <Link
                                :href="route('opportunities.edit', record.id)"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Opportunity
                            </Link>
                            <button
                                @click="handleDelete"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            >
                                Delete Opportunity
                            </button>
                        </div>
                    </div>

                    <!-- Deal Value -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Deal Value</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ fieldsSchema.estimated_value?.label || 'Estimated Value' }}
                                </span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(record.estimated_value) }}
                                </span>
                            </div>
                            <div v-if="lineItems.length > 0" class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Items Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(lineItemsSubtotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Item Count</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ lineItems.length }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opportunity Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Opportunity Info</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div v-if="record.opened_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Opened</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.opened_at) }}</span>
                            </div>
                            <div v-if="record.expected_close_date" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Expected Close</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.expected_close_date) }}</span>
                            </div>
                            <div v-if="record.won_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Won</span>
                                <span class="font-medium text-green-700 dark:text-green-400">{{ formatDate(record.won_at) }}</span>
                            </div>
                            <div v-if="record.lost_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Lost</span>
                                <span class="font-medium text-red-600 dark:text-red-400">{{ formatDate(record.lost_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.created_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.updated_at) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </TenantLayout>
</template>
