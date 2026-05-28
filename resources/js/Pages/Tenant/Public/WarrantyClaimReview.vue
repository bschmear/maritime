<script setup>
import PublicBrandingFooter from '@/Components/Tenant/Public/PublicBrandingFooter.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
});

const page = usePage();
const appName = computed(() => page.props.app?.name ?? import.meta.env.VITE_APP_NAME ?? 'Laravel');
const appUrl = (typeof window !== 'undefined' && window.location?.origin) ? window.location.origin : '';
const termsUrl = appUrl ? `${appUrl.replace(/\/$/, '')}/terms` : null;

const claimRef = computed(
    () => props.record.display_name || props.record.claim_number || `Claim #${props.record.id}`,
);

const formatMoney = (value) => {
    if (value == null || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } catch {
        return '—';
    }
};

const statusLabel = (raw) => {
    if (raw == null || raw === '') return '—';
    const opts = props.enumOptions?.status || [];
    const hit = opts.find(
        (o) => o.id === raw || o.value === raw || String(o.id) === String(raw) || String(o.value) === String(raw),
    );
    return hit?.name ?? String(raw);
};

const costTypeLabel = (raw) => {
    const opts = props.enumOptions?.cost_type || [];
    const hit = opts.find((o) => o.value === raw || o.id === raw);
    return hit?.name ?? (raw === 'fixed' ? 'Fixed total' : 'Quantity × cost');
};

const lineItems = computed(() => props.record.line_items || props.record.lineItems || []);

const serviceLineDisplayName = (row) =>
    row?.work_order_service_item?.display_name
    || row?.workOrderServiceItem?.display_name
    || (row?.work_order_service_item_id ? `Work order line #${row.work_order_service_item_id}` : 'Line item');

const lineTotalSum = computed(() =>
    lineItems.value.reduce((sum, row) => sum + (Number(row.line_total_cost) || 0), 0),
);

const claimImages = computed(() => props.record.images || []);
const claimDocuments = computed(() => props.record.documents || []);

const vendorPortalLoginHref = '/vendor/portal/login';

const handlePrint = () => window.print();

onMounted(() => {
    try {
        const q = new URLSearchParams(window.location.search);
        if (q.get('autoprint') === '1') {
            setTimeout(() => window.print(), 800);
        }
    } catch {
        /* ignore */
    }
});
</script>

<template>
    <Head :title="`Warranty claim ${claimRef}`" />

    <div class="min-h-screen bg-gray-100 flex flex-col">
        <div id="warranty-claim-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full print:p-0 print:mx-0 print:max-w-none">
            <div class="mb-4 flex flex-wrap items-center justify-end gap-3 print:hidden">
                <a
                    :href="vendorPortalLoginHref"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700"
                >
                    <span class="material-icons text-base">login</span>
                    Vendor portal
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    @click="handlePrint"
                >
                    <span class="material-icons text-base">print</span>
                    Print
                </button>
            </div>

            <div class="bg-white shadow-lg print:shadow-none rounded-lg overflow-hidden print:rounded-none">
                <div class="border-b-4 border-gray-900 px-8 print:px-0 py-6 print:border-b-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-6 min-w-0">
                            <div v-if="logoUrl" class="flex-shrink-0">
                                <img :src="logoUrl" alt="Company logo" class="h-20 w-auto max-w-[180px] object-contain" />
                            </div>
                            <div v-else class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center">
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-2xl font-bold text-gray-900">
                                    {{ record.subsidiary?.display_name || account?.name || 'Company' }}
                                </h1>
                                <p class="mt-1 text-sm text-gray-600">
                                    Warranty claim for vendor:
                                    <span class="font-semibold text-gray-900">{{ record.vendor?.display_name || '—' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-medium text-gray-600 uppercase">Warranty claim</div>
                            <div class="text-2xl sm:text-3xl font-bold text-gray-900 font-mono">
                                {{ claimRef }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ formatDate(record.created_at) }}
                            </div>
                            <div class="mt-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800"
                                >
                                    {{ statusLabel(record.status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-8 print:px-0 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Work order</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200 text-sm text-gray-800">
                                {{ record.work_order?.display_name || record.work_order?.work_order_number || '—' }}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Location</h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200 text-sm text-gray-800">
                                {{ record.location?.display_name || '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="record.notes" class="px-8 print:px-0 py-6 border-b border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Claim notes</h2>
                    <p class="text-sm text-gray-900 whitespace-pre-line">{{ record.notes }}</p>
                </div>

                <div
                    v-if="claimImages.length"
                    class="px-8 print:px-0 py-6 border-b border-gray-200 print:break-inside-avoid"
                >
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Photos</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        <a
                            v-for="img in claimImages"
                            :key="img.id"
                            :href="img.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-100"
                        >
                            <img
                                :src="img.url"
                                :alt="img.display_name || 'Claim photo'"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </a>
                    </div>
                </div>

                <div v-if="claimDocuments.length" class="px-8 print:px-0 py-6 border-b border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Documents</h2>
                    <ul class="list-disc pl-5 text-sm text-gray-800 space-y-1">
                        <li v-for="doc in claimDocuments" :key="doc.id">
                            {{ doc.display_name }}<span v-if="doc.file_extension" class="text-gray-500">.{{ doc.file_extension }}</span>
                        </li>
                    </ul>
                </div>

                <div class="px-8 print:px-0 py-6 border-b border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Line items</h2>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Coverage</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-700">Qty</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-700">Cost</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-700">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template v-for="row in lineItems" :key="row.id">
                                    <tr>
                                        <td class="px-3 py-2 text-gray-600">{{ costTypeLabel(row.cost_type) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ row.quantity }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ formatMoney(row.cost) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-900 font-medium">
                                            {{ formatMoney(row.line_total_cost) }}
                                        </td>
                                    </tr>
                                    <tr class="bg-gray-50/90">
                                        <td colspan="4" class="px-3 py-3 border-t border-gray-100 text-sm">
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Service line</div>
                                                    <div class="font-medium text-gray-900">{{ serviceLineDisplayName(row) }}</div>
                                                </div>
                                                <div v-if="(row.description || '').trim()">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Service description</div>
                                                    <div class="text-gray-800 whitespace-pre-line">{{ row.description }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0.5">Vendor feedback</div>
                                                    <div v-if="(row.notes || '').trim()" class="text-gray-800 whitespace-pre-line">{{ row.notes }}</div>
                                                    <div v-else class="text-gray-400">—</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-right">
                            <div class="text-xs font-semibold uppercase text-gray-500">Total claim</div>
                            <div class="text-xl font-bold text-gray-900 tabular-nums">
                                {{ formatMoney(record.total_amount != null ? record.total_amount : lineTotalSum) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-8 print:px-0 py-6 print:hidden">
                    <div class="rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900">
                        <p class="font-medium">Respond in the vendor portal</p>
                        <p class="mt-1 text-primary-800">
                            To approve or reject this claim and add vendor notes, sign in at the vendor portal.
                        </p>
                        <a
                            :href="vendorPortalLoginHref"
                            class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary-700 hover:text-primary-900"
                        >
                            Open vendor portal
                            <span class="material-icons text-base">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <PublicBrandingFooter :app-name="appName" :app-url="appUrl || '#'" :terms-url="termsUrl" />
    </div>
</template>
