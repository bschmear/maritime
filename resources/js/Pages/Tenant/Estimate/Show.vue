<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'estimates' },
    recordTitle: { type: String, default: 'Estimate' },
    domainName: { type: String, default: 'Estimate' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const sendApprovalForm = useForm({});
const sendApprovalRequest = () => {
    sendApprovalForm.post(route('estimates.send-approval', props.record.id), {
        preserveScroll: true,
    });
};

const revisionForm = useForm({});
const createRevision = () => {
    revisionForm.post(route('estimates.revision', props.record.id));
};

const isLocked    = computed(() => !!props.record?.is_locked);
const hasRevision = computed(() => !!props.record?.revision);

const canSendApproval = computed(() => {
    const v = statusInfo.value?.value;
    return (v === 'draft' || v === 'sent') && !hasRevision.value;
});

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);

const estimateLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `Estimate #${props.record?.id}`
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Estimates', href: route('estimates.index') },
    { label: estimateLabel.value },
]);

const primaryVersion = computed(() =>
    props.record?.primary_version ?? props.record?.primaryVersion ?? null
);

const lineItems = computed(() => primaryVersion.value?.line_items ?? primaryVersion.value?.lineItems ?? []);

const assetLines = computed(() =>
    lineItems.value.filter(
        (li) => li.itemable_type === 'App\\Domain\\Asset\\Models\\Asset'
    )
);

const inventoryLines = computed(() =>
    lineItems.value.filter(
        (li) => li.itemable_type === 'App\\Domain\\InventoryItem\\Models\\InventoryItem'
    )
);

const lineBaseTotal = (item) =>
    Math.max(0, Number(item.unit_price || 0) * Number(item.quantity || 1) - Number(item.discount || 0));

const lineTotal = (item) => {
    const addonsTotal = (item.addons || []).reduce(
        (sum, addon) => sum + Number(addon.price || 0) * Number(addon.quantity || 1),
        0
    );
    return lineBaseTotal(item) + addonsTotal;
};

const addonSubtotalForItems = (items) =>
    items.reduce((sum, item) =>
        sum + (item.addons ?? []).reduce((s, a) => s + Number(a.price || 0) * Number(a.quantity || 1), 0), 0);

const assetBaseSubtotal = computed(() =>
    assetLines.value.reduce((sum, item) => sum + lineBaseTotal(item), 0));

const assetAddonSubtotal = computed(() => addonSubtotalForItems(assetLines.value));

const inventoryBaseSubtotal = computed(() =>
    inventoryLines.value.reduce((sum, item) => sum + lineBaseTotal(item), 0));

const inventoryAddonSubtotal = computed(() => addonSubtotalForItems(inventoryLines.value));

const assetSubtotal = computed(() =>
    assetLines.value.reduce((sum, item) => sum + lineTotal(item), 0)
);

const inventorySubtotal = computed(() =>
    inventoryLines.value.reduce((sum, item) => sum + lineTotal(item), 0)
);

const combinedSubtotal = computed(() => assetSubtotal.value + inventorySubtotal.value);
const taxAmount = computed(() =>
    combinedSubtotal.value * (Number(props.record?.tax_rate || primaryVersion.value?.tax_rate || 0) / 100)
);
const grandTotal = computed(() => combinedSubtotal.value + taxAmount.value);
const taxRate = computed(() =>
    props.record?.tax_rate ?? primaryVersion.value?.tax_rate ?? 0
);

const formatCurrency = (value) =>
    value != null && value !== ''
        ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isExpired = computed(() => {
    if (!props.record?.expiration_date) return false;
    return new Date(props.record.expiration_date) < new Date();
});

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    } catch { return '—'; }
};

// Tailwind-safe text classes keyed by the color name from EstimateStatus::color()
const STATUS_TEXT = {
    gray:   'text-gray-700 dark:text-gray-300',
    blue:   'text-blue-700 dark:text-blue-300',
    yellow: 'text-yellow-700 dark:text-yellow-300',
    green:  'text-green-700 dark:text-green-300',
    red:    'text-red-700 dark:text-red-300',
    orange: 'text-orange-700 dark:text-orange-300',
    purple: 'text-purple-700 dark:text-purple-300',
    slate:  'text-slate-700 dark:text-slate-300',
};

const STATUS_ENUM_KEY = 'App\\Enums\\Estimate\\EstimateStatus';

const statusOptions = computed(() =>
    props.enumOptions?.[STATUS_ENUM_KEY] ?? []
);

// Matches by integer id OR string value so it works regardless of what the DB stores
const statusInfo = computed(() => {
    const s = props.record?.status;
    return statusOptions.value.find(o => o.id == s || o.value == s)
        ?? { id: 0, value: '', name: s ?? 'Unknown', color: 'gray', bgClass: 'bg-gray-100 dark:bg-gray-700' };
});

const statusTextClass = computed(() =>
    STATUS_TEXT[statusInfo.value?.color] ?? 'text-gray-700 dark:text-gray-300'
);

const isApproved = computed(() => statusInfo.value?.value === 'approved' || !!props.record?.signed_at);
const isDeclined = computed(() => statusInfo.value?.value === 'declined' || !!props.record?.declined_at);

const handleDelete = () => { showDeleteModal.value = true; };
const cancelDelete = () => { showDeleteModal.value = false; };

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('estimates.destroy', recordIdentifier.value), {
        onSuccess: () => router.visit(route('estimates.index')),
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`${estimateLabel} - Estimate`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ estimateLabel }}
            </h2>
                    <div class="flex items-center gap-2">
                        <!-- Send for Approval -->
                        <button
                            v-if="canSendApproval"
                            @click="sendApprovalRequest"
                            :disabled="sendApprovalForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 rounded-lg transition-colors"
                            :title="record.sent_at ? `Resend (last sent ${new Date(record.sent_at).toLocaleDateString()})` : 'Send estimate to customer for approval'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ sendApprovalForm.processing ? 'Sending…' : (record.sent_at ? 'Resend for Approval' : 'Send for Approval') }}
                        </button>

                        <!-- Create Revision (locked) -->
                        <button
                            v-if="isLocked && !hasRevision"
                            @click="createRevision"
                            :disabled="revisionForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600 disabled:opacity-60 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-base">content_copy</span>
                            {{ revisionForm.processing ? 'Creating…' : 'Create Revision' }}
                        </button>

                        <!-- Edit (unlocked only) -->
                        <Link
                            v-if="!isLocked"
                            :href="route('estimates.edit', record.id)"
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

            <!-- ── "Newer version exists" banner ── -->
            <div
                v-if="hasRevision"
                class="flex items-center justify-between gap-4 px-5 py-3.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg"
            >
                <div class="flex items-center gap-3">
                    <span class="material-icons text-amber-500 dark:text-amber-400">new_releases</span>
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        <span class="font-semibold">This estimate has been superseded.</span>
                        A newer revision was created.
                    </p>
                </div>
                <Link
                    :href="route('estimates.show', record.revision.id)"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors whitespace-nowrap"
                >
                    View Latest Version
                    <span class="material-icons text-base">arrow_forward</span>
                </Link>
            </div>

            <!-- ── "This is a revision of…" banner ── -->
            <div
                v-if="record.revised_from_id"
                class="flex items-center gap-3 px-5 py-3.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg"
            >
                <span class="material-icons text-blue-500 dark:text-blue-400 text-base">history</span>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    This is a revision of
                    <Link
                        :href="route('estimates.show', record.revised_from_id)"
                        class="font-semibold underline hover:no-underline"
                    >
                        EST-{{ record.revisedFrom?.sequence ?? record.revised_from_id }}
                    </Link>.
                </p>
            </div>

            <!-- ── Locked banner ── -->
            <div
                v-if="isLocked && !hasRevision"
                class="flex items-center justify-between gap-4 px-5 py-3.5 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg"
            >
                <div class="flex items-center gap-3">
                    <span class="material-icons text-yellow-600 dark:text-yellow-400">lock</span>
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <span class="font-semibold">This estimate is locked</span> — it has been sent for approval and cannot be edited.
                        Create a revision to make changes.
                    </p>
                </div>
                <button
                    @click="createRevision"
                    :disabled="revisionForm.processing"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-yellow-500 hover:bg-yellow-600 disabled:opacity-60 rounded-lg transition-colors whitespace-nowrap"
                >
                    <span class="material-icons text-base">content_copy</span>
                    {{ revisionForm.processing ? 'Creating…' : 'Create Revision' }}
                </button>
            </div>

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
                                    <div class="flex items-center gap-3 mb-1">
                                        <h1 class="text-2xl font-bold text-white">ESTIMATE</h1>
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                            :class="[statusInfo.bgClass, statusTextClass]"
                                        >
                                            {{ statusInfo.name }}
                                        </span>
                                    </div>
                                    <p class="text-primary-100 text-base">Customer estimate details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ estimateLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: Customer & Lead -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Customer & Lead
                                    </h3>

                                    <!-- Customer -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Customer</div>
                                        <Link
                                            v-if="record.customer"
                                            :href="route('customers.show', record.customer_id)"
                                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                        >
                                            {{ record.customer.display_name }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                    </div>

                                    <!-- Opportunity -->
                                    <div v-if="record.opportunity_id">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Opportunity</div>
                                        <Link
                                            v-if="record.opportunity"
                                            :href="route('opportunities.show', record.opportunity_id)"
                                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                        >
                                            {{ record.opportunity.display_name }}
                                        </Link>
                                        <div v-else class="text-sm text-gray-400 dark:text-gray-500">—</div>
                                    </div>

                                    <!-- Salesperson -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Salesperson</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.salesperson?.display_name ?? record.user?.display_name ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Estimate Details -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Estimate Details
                                    </h3>

                                    <!-- Issue Date -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Issue Date</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(record.issue_date) }}</div>
                                    </div>

                                    <!-- Expiration Date -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Expiration Date</div>
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(record.expiration_date) }}</div>
                                            <span
                                                v-if="isExpired && record.expiration_date"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                            >
                                                Expired
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Version -->
                                    <div v-if="primaryVersion">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Version</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">v{{ primaryVersion.version }}</div>
                                    </div>

                                    <!-- Tax Rate -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Tax Rate</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ taxRate }}%</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes & Terms -->
                            <div
                                v-if="record.notes || record.terms"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-4"
                            >
                                <div v-if="record.notes">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.notes }}</div>
                                </div>
                                <div v-if="record.terms">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Terms</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.terms }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================
                         Assets
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Assets</h2>
                        </div>

                        <div v-if="assetLines.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asset</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Year</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="(item, index) in assetLines" :key="index">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
                                                <div v-if="item.itemable?.make?.display_name" class="text-xs text-gray-400 dark:text-gray-500">
                                                    {{ item.itemable.make.display_name }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ item.itemable?.year || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                                {{ item.discount > 0 ? `-${formatCurrency(item.discount)}` : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineBaseTotal(item)) }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-[160px]">{{ item.notes || '—' }}</td>
                                        </tr>
                                        <!-- Add-on sub-rows -->
                                        <tr
                                            v-for="(addon, addonIdx) in (item.addons || [])"
                                            :key="`asset-addon-${index}-${addonIdx}`"
                                            class="bg-primary-50/40 dark:bg-primary-900/10"
                                        >
                                            <td class="pl-10 pr-4 py-2 text-xs text-gray-600 dark:text-gray-400 italic" colspan="2">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">
                                                {{ formatCurrency(Number(addon.price) * Number(addon.quantity)) }}
                                            </td>
                                            <td class="px-4 py-2 text-xs text-gray-400">{{ addon.notes || '—' }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Assets Subtotal
                                        </td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(assetSubtotal) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="text-sm text-gray-400 dark:text-gray-500">No assets on this estimate</p>
                        </div>
                    </div>

                    <!-- ============================
                         Parts & Accessories
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Parts &amp; Accessories</h2>
                        </div>

                        <div v-if="inventoryLines.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">SKU</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-24">Discount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="(item, index) in inventoryLines" :key="index">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ item.name }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ item.itemable?.sku || '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="px-4 py-3 text-right text-red-500 dark:text-red-400">
                                                {{ item.discount > 0 ? `-${formatCurrency(item.discount)}` : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(lineBaseTotal(item)) }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-[160px]">{{ item.notes || '—' }}</td>
                                        </tr>
                                        <!-- Add-on sub-rows -->
                                        <tr
                                            v-for="(addon, addonIdx) in (item.addons || [])"
                                            :key="`inv-addon-${index}-${addonIdx}`"
                                            class="bg-primary-50/40 dark:bg-primary-900/10"
                                        >
                                            <td class="pl-10 pr-4 py-2 text-xs text-gray-600 dark:text-gray-400 italic" colspan="2">
                                                ↳ {{ addon.name }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(addon.price) }}</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-400">—</td>
                                            <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">{{ addon.quantity }}</td>
                                            <td class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300">
                                                {{ formatCurrency(Number(addon.price) * Number(addon.quantity)) }}
                                            </td>
                                            <td class="px-4 py-2 text-xs text-gray-400">{{ addon.notes || '—' }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Parts &amp; Accessories Subtotal
                                        </td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(inventorySubtotal) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center py-12 text-center px-6">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-sm text-gray-400 dark:text-gray-500">No parts or accessories on this estimate</p>
                        </div>
                    </div>

                    <!-- ============================
                         Authorization & Signature
                         ============================ -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Authorization &amp; Signature</h2>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                :class="[statusInfo.bgClass, statusTextClass]"
                            >
                                <span v-if="isApproved" class="material-icons text-sm">check_circle</span>
                                <span v-else-if="isDeclined" class="material-icons text-sm">cancel</span>
                                {{ statusInfo.name }}
                            </span>
                        </div>

                        <!-- Approved -->
                        <div v-if="isApproved" class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed By</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ record.signed_name || '—' }}</div>
                                    </div>
                                    <div v-if="record.signed_email">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed Email</div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">{{ record.signed_email }}</div>
                                    </div>
                                    <div v-if="record.signed_at">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Signed At</div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(record.signed_at) }}</div>
                                    </div>
                                    <div v-if="record.signed_ip">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">IP Address</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ record.signed_ip }}</div>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Signature</div>
                                    <!-- Drawn signature image -->
                                    <div v-if="record.signature_url" class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <img :src="record.signature_url" alt="Customer Signature" class="max-h-32 w-auto" />
                                    </div>
                                    <!-- Typed / name-only fallback -->
                                    <div v-else-if="record.signed_name" class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <p class="text-3xl text-gray-800 dark:text-gray-200 signature-cursive">{{ record.signed_name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Declined -->
                        <div v-else-if="isDeclined" class="p-6 space-y-4">
                            <div>
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Declined At</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(record.declined_at) }}</div>
                            </div>
                            <div v-if="record.decline_reason">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Reason</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 rounded-lg p-3">
                                    {{ record.decline_reason }}
                                </div>
                            </div>
                        </div>

                        <!-- Not yet actioned -->
                        <div v-else class="p-6">
                            <div class="flex flex-col items-center justify-center py-6 text-center">
                                <span class="material-icons text-4xl text-gray-300 dark:text-gray-600 mb-3">draw</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="record.sent_at">Sent to customer on {{ formatDateTime(record.sent_at) }}. Awaiting signature.</span>
                                    <span v-else>This estimate has not been sent for approval yet.</span>
                                </p>
                                <button
                                    v-if="canSendApproval"
                                    @click="sendApprovalRequest"
                                    :disabled="sendApprovalForm.processing"
                                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 rounded-lg transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ sendApprovalForm.processing ? 'Sending…' : (record.sent_at ? 'Resend for Approval' : 'Send for Approval') }}
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================
                     Sidebar
                     ============================ -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden ">
                        <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <!-- Status badge -->
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg" :class="[statusInfo.bgClass, statusTextClass]">
                                <span class="text-xs font-medium uppercase tracking-wide">Status</span>
                                <span class="text-sm font-semibold">{{ statusInfo.name }}</span>
                            </div>

                            <!-- Send for Approval -->
                            <button
                                v-if="canSendApproval"
                                @click="sendApprovalRequest"
                                :disabled="sendApprovalForm.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {{ sendApprovalForm.processing ? 'Sending…' : (record.sent_at ? 'Resend for Approval' : 'Send for Approval') }}
                            </button>

                            <!-- Sent at info -->
                            <div v-if="record.sent_at" class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                Last sent {{ formatDateTime(record.sent_at) }}
                            </div>

                            <!-- Create Revision (locked) -->
                            <button
                                v-if="isLocked && !hasRevision"
                                @click="createRevision"
                                :disabled="revisionForm.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600 disabled:opacity-60 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-base text-sm">content_copy</span>
                                {{ revisionForm.processing ? 'Creating Revision…' : 'Create Revision' }}
                            </button>

                            <!-- View Latest (superseded) -->
                            <Link
                                v-if="hasRevision"
                                :href="route('estimates.show', record.revision.id)"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-base text-sm">arrow_forward</span>
                                View Latest Version
                            </Link>

                            <!-- Edit (unlocked) -->
                            <Link
                                v-if="!isLocked"
                                :href="route('estimates.edit', record.id)"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Estimate
                            </Link>
                            <button
                                @click="handleDelete"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            >
                                Delete Estimate
                            </button>
                        </div>
                    </div>

                    <!-- Estimate Total -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Estimate Total</span>
                        </div>
                        <div class="p-5 space-y-3">

                            <template v-if="assetLines.length > 0">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Assets</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(assetBaseSubtotal) }}</span>
                                </div>
                                <div v-if="assetAddonSubtotal > 0" class="flex justify-between items-center text-sm pl-3 border-l-2 border-primary-200 dark:border-primary-800">
                                    <span class="text-gray-400 dark:text-gray-500">Asset Add-ons</span>
                                    <span class="text-gray-500 dark:text-gray-400">+ {{ formatCurrency(assetAddonSubtotal) }}</span>
                                </div>
                            </template>
                            <template v-if="inventoryLines.length > 0">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Parts &amp; Acc.</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(inventoryBaseSubtotal) }}</span>
                                </div>
                                <div v-if="inventoryAddonSubtotal > 0" class="flex justify-between items-center text-sm pl-3 border-l-2 border-primary-200 dark:border-primary-800">
                                    <span class="text-gray-400 dark:text-gray-500">Parts Add-ons</span>
                                    <span class="text-gray-500 dark:text-gray-400">+ {{ formatCurrency(inventoryAddonSubtotal) }}</span>
                                </div>
                            </template>

                            <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Subtotal</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(combinedSubtotal) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Tax ({{ taxRate }}%)</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ formatCurrency(taxAmount) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-600">
                                <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(grandTotal) }}</span>
                            </div>

                            <!-- Stored totals from version (fallback / source of truth) -->
                            <div v-if="primaryVersion?.total != null" class="pt-3 border-t border-dashed border-gray-200 dark:border-gray-600 space-y-1.5">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-400 dark:text-gray-500">Saved Subtotal</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ formatCurrency(primaryVersion.subtotal) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-400 dark:text-gray-500">Saved Total</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ formatCurrency(primaryVersion.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estimate Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Estimate Info</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div v-if="record.opportunity" class="flex justify-between items-start gap-2">
                                <span class="text-gray-500 dark:text-gray-400 shrink-0">Opportunity</span>
                                <Link
                                    :href="route('opportunities.show', record.opportunity.id)"
                                    class="font-medium text-primary-600 dark:text-primary-400 hover:underline text-right"
                                >
                                    {{ record.opportunity.display_name }}
                                </Link>
                            </div>
                            <div v-if="primaryVersion" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Version</span>
                                <span class="font-medium text-gray-900 dark:text-white">v{{ primaryVersion.version }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Issue Date</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.issue_date) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Expiration</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.expiration_date) }}</span>
                                    <span
                                        v-if="isExpired && record.expiration_date"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                    >
                                        Expired
                                    </span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.created_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.updated_at) }}</span>
                            </div>
                            <div v-if="record.sent_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Sent for Approval</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatDate(record.sent_at) }}</span>
                            </div>
                            <div v-if="record.signed_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Signed</span>
                                <span class="text-green-600 dark:text-green-400 font-medium">{{ formatDate(record.signed_at) }}</span>
                            </div>
                            <div v-if="record.declined_at" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Declined</span>
                                <span class="text-red-600 dark:text-red-400 font-medium">{{ formatDate(record.declined_at) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Estimate</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete <span class="font-medium text-gray-700 dark:text-gray-300">{{ estimateLabel }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="isDeleting" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ isDeleting ? 'Deleting...' : 'Delete Estimate' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap');
.signature-cursive { font-family: 'Dancing Script', cursive; }
</style>