<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    shipments: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Shipments' },
]);

function formatMoney(cents) {
    if (cents == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}

function recipientLabel(shipment) {
    if (shipment.contact) {
        return shipment.contact.display_name ?? `${shipment.contact.first_name ?? ''} ${shipment.contact.last_name ?? ''}`.trim();
    }
    return shipment.vendor?.display_name ?? shipment.recipient_name ?? '—';
}
</script>

<template>
    <Head title="Shipments" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Breadcrumb :items="breadcrumbItems" />
                    <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">Shipments</h2>
                </div>
                <Link
                    :href="route('shipments.create')"
                    class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                >
                    New shipment
                </Link>
            </div>
        </template>

        <div class="mx-auto w-full px-4 py-6">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Shipment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recipient</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Carrier</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="shipment in shipments.data" :key="shipment.id">
                            <td class="px-4 py-3 text-sm">
                                <Link :href="route('shipments.show', shipment.id)" class="font-medium text-primary-600 hover:underline">
                                    {{ shipment.display_name }}
                                </Link>
                                <div v-if="shipment.tracking_code" class="text-xs text-gray-500">{{ shipment.tracking_code }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ recipientLabel(shipment) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                    {{ shipment.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span v-if="shipment.carrier">{{ shipment.carrier }}<span v-if="shipment.service"> · {{ shipment.service }}</span></span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatMoney(shipment.rate_cents) }}</td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500">{{ shipment.created_at ? new Date(shipment.created_at).toLocaleString() : '—' }}</td>
                        </tr>
                        <tr v-if="!shipments.data?.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No shipments yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
