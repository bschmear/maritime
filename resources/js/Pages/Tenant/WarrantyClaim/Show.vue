<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'warrantyclaims' },
    recordTitle: { type: String, default: 'Warranty claim' },
    domainName: { type: String, default: 'WarrantyClaim' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const claimLabel = computed(() => {
    const r = props.record;
    return r.display_name || (r.sequence != null ? `WCL-${r.sequence}` : null) || `Claim #${r.id}`;
});

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const isSublistVisible = (sub) => {
    if (!sub?.conditional || typeof sub.conditional !== 'object') return true;
    const { key, value, operator = 'equals' } = sub.conditional;
    const current = props.record[key];
    const boolCurrent = current === true || current === 1;
    switch (operator) {
        case 'equals': case 'eq':
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
        case 'not_equals': case 'neq':
            return typeof value === 'boolean' ? boolCurrent !== value : current != value;
        default:
            return typeof value === 'boolean' ? boolCurrent === value : current == value;
    }
};

const visibleSublists = computed(() => (props.formSchema?.sublists || []).filter(isSublistVisible));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Warranty claims', href: indexHref.value },
    { label: claimLabel.value },
]);

const statusEnumKey = 'App\\Enums\\WarrantyClaim\\Status';

function enumLabel(enumKey, raw) {
    if (raw == null || raw === '') return '—';
    const opts = props.enumOptions[enumKey] || [];
    const hit = opts.find(
        (o) => o.id === raw || o.value === raw || String(o.id) === String(raw) || String(o.value) === String(raw),
    );
    return hit?.name ?? String(raw);
}

const statusName = computed(() => enumLabel(statusEnumKey, props.record.status?.value ?? props.record.status));

// Simple status → color mapping — extend to match your enum values
const statusColors = {
    draft:    { bg: 'bg-gray-100 dark:bg-gray-700',    text: 'text-gray-700 dark:text-gray-300' },
    pending:  { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-800 dark:text-yellow-300' },
    approved: { bg: 'bg-green-100 dark:bg-green-900/30',  text: 'text-green-800 dark:text-green-300' },
    rejected: { bg: 'bg-red-100 dark:bg-red-900/30',   text: 'text-red-800 dark:text-red-300' },
    closed:   { bg: 'bg-blue-100 dark:bg-blue-900/30',  text: 'text-blue-800 dark:text-blue-300' },
};

const statusColor = computed(() => {
    const raw = String(props.record.status?.value ?? props.record.status ?? '').toLowerCase();
    return statusColors[raw] ?? statusColors.draft;
});

const fmtMoney = (v) => {
    if (v == null || v === '') return '—';
    return Number(v).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
};

const fmtDate = (v) => {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="claimLabel" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ claimLabel }}
                        </h2>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                            :class="[statusColor.bg, statusColor.text]"
                        >
                            {{ statusName }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
                        >
                            <span class="material-icons text-base">edit</span>
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-base">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ── Main Column ── -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <h1 class="text-2xl font-bold text-white">WARRANTY CLAIM</h1>
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                            :class="[statusColor.bg, statusColor.text]"
                                        >
                                            {{ statusName }}
                                        </span>
                                    </div>
                                    <p class="text-blue-100 text-base">Warranty claim details</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-blue-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ claimLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Left: Claim Info -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Claim Info
                                    </h3>

                                    <!-- Manufacturer -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Manufacturer</div>
                                        <div class="text-base text-gray-900 dark:text-gray-100">
                                            {{ record.vendor?.display_name ?? '—' }}
                                        </div>
                                    </div>

                                    <!-- Work Order -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Work Order</div>
                                        <div class="text-base">
                                            <Link
                                                v-if="record.work_order_id"
                                                :href="route('workorders.show', record.work_order_id)"
                                                class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                            >
                                                {{ record.work_order?.display_name ?? `WO #${record.work_order_id}` }}
                                            </Link>
                                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                        </div>
                                    </div>

                                    <!-- Total -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Amount</div>
                                        <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                            {{ fmtMoney(record.total_amount) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Assignment -->
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Assignment
                                    </h3>

                                    <!-- Subsidiary -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Subsidiary</div>
                                        <div class="text-base text-gray-900 dark:text-gray-100">
                                            {{ record.subsidiary?.display_name ?? '—' }}
                                        </div>
                                    </div>

                                    <!-- Location -->
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Location</div>
                                        <div class="text-base text-gray-900 dark:text-gray-100">
                                            {{ record.location?.display_name ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes & Rejection -->
                            <div
                                v-if="record.notes || record.rejection_reason"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5 grid grid-cols-1 md:grid-cols-2 gap-6"
                            >
                                <div v-if="record.notes">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</div>
                                    <div class="text-base text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</div>
                                </div>
                                <div v-if="record.rejection_reason">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Rejection Reason</div>
                                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 rounded-lg px-4 py-3 text-base text-red-800 dark:text-red-300 whitespace-pre-line leading-relaxed">
                                        {{ record.rejection_reason }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items Card -->
                    <div
                        v-if="record.line_items?.length || record.lineItems?.length"
                        class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden"
                    >
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Line Items</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Description</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-20">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide w-28">Cost</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="row in (record.lineItems || record.line_items || [])"
                                        :key="row.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                    >
                                        <td class="px-4 py-3 text-base font-medium text-gray-900 dark:text-gray-100">{{ row.description }}</td>
                                        <td class="px-4 py-3 text-right text-base text-gray-700 dark:text-gray-300">{{ row.quantity }}</td>
                                        <td class="px-4 py-3 text-right text-base text-gray-700 dark:text-gray-300">{{ fmtMoney(row.price) }}</td>
                                        <td class="px-4 py-3 text-right text-base font-semibold text-gray-900 dark:text-white">{{ fmtMoney(row.cost) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Total
                                        </td>
                                        <td class="px-4 py-3 text-right text-base font-bold text-gray-900 dark:text-white">
                                            {{ fmtMoney(record.total_amount) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Sublists -->
                    <div
                        v-if="visibleSublists.length > 0 && formSchema"
                        class="space-y-6"
                    >
                        <Sublist
                            :key="`warranty-claim-sublist-${record?.id || 'new'}`"
                            :parent-record="record"
                            parent-domain="WarrantyClaim"
                            :sublists="visibleSublists"
                        />
                    </div>
                </div>

                <!-- ── Sidebar ── -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <Link
                                :href="editHref"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-base">edit</span>
                                Edit Claim
                            </Link>
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                @click="showDeleteModal = true"
                            >
                                <span class="material-icons text-base">delete</span>
                                Delete Claim
                            </button>
                        </div>
                    </div>

                    <!-- Claim Summary -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Claim Summary</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Status</span>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    :class="[statusColor.bg, statusColor.text]"
                                >
                                    {{ statusName }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Manufacturer</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right max-w-[60%] truncate">
                                    {{ record.vendor?.display_name ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Work Order</span>
                                <Link
                                    v-if="record.work_order_id"
                                    :href="route('workorders.show', record.work_order_id)"
                                    class="font-medium text-primary-600 dark:text-primary-400 hover:underline text-right"
                                >
                                    {{ record.work_order?.display_name ?? `WO #${record.work_order_id}` }}
                                </Link>
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Total Amount</span>
                                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                    {{ fmtMoney(record.total_amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Claim Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Claim Info</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div v-if="record.subsidiary" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Subsidiary</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right">{{ record.subsidiary.display_name }}</span>
                            </div>
                            <div v-if="record.location" class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Location</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right">{{ record.location.display_name }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ fmtDate(record.created_at) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ fmtDate(record.updated_at) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <span class="material-icons text-red-600 dark:text-red-400 text-2xl">delete</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Warranty Claim</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ claimLabel }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 transition-colors"
                        @click="confirmDelete"
                    >
                        <span v-if="isDeleting" class="material-icons text-base animate-spin">refresh</span>
                        {{ isDeleting ? 'Deleting…' : 'Delete Claim' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>