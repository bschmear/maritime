<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
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
    return (r.claim_number && String(r.claim_number).trim()) || `Claim #${r.id}`;
});

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Warranty claims', href: indexHref.value },
    { label: claimLabel.value },
]);

const statusEnumKey = 'App\\Enums\\WarrantyClaim\\Status';

function enumLabel(enumKey, raw) {
    if (raw == null || raw === '') {
        return '—';
    }
    const opts = props.enumOptions[enumKey] || [];
    const hit = opts.find(
        (o) =>
            o.id === raw ||
            o.value === raw ||
            String(o.id) === String(raw) ||
            String(o.value) === String(raw),
    );
    return hit?.name ?? String(raw);
}

const fmtMoney = (v) => {
    if (v == null || v === '') {
        return '—';
    }
    return Number(v).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
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
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ claimLabel }}
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-950/30"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ enumLabel(statusEnumKey, record.status?.value ?? record.status) }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ fmtMoney(record.total_amount) }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:col-span-2">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Manufacturer</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ record.vendor?.display_name ?? '—' }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Invoice</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        <Link
                            v-if="record.invoice_id"
                            :href="route('invoices.show', record.invoice_id)"
                            class="text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {{ record.invoice?.display_name ?? `INV-${record.invoice?.sequence ?? record.invoice_id}` }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Work order</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        <Link
                            v-if="record.work_order_id"
                            :href="route('workorders.show', record.work_order_id)"
                            class="text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {{ record.work_order?.display_name ?? `WO #${record.work_order_id}` }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div v-if="record.notes" class="sm:col-span-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Notes</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-gray-100">{{ record.notes }}</dd>
                </div>
                <div v-if="record.rejection_reason" class="sm:col-span-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Rejection reason</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-gray-100">{{ record.rejection_reason }}</dd>
                </div>
            </dl>

            <div v-if="record.line_items?.length || record.lineItems?.length" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    Line items
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Price</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in (record.lineItems || record.line_items || [])" :key="row.id">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ row.description }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ row.quantity }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ fmtMoney(row.price) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ fmtMoney(row.cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete warranty claim?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    This cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
