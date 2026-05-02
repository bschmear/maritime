<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    canRespond: { type: Boolean, default: false },
    statuses: { type: Array, default: () => [] },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

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
    const opts = props.statuses || [];
    const hit = opts.find(
        (o) => o.id === raw || o.value === raw || String(o.id) === String(raw) || String(o.value) === String(raw),
    );
    return hit?.name ?? String(raw);
};

const lineItems = computed(() => props.record.line_items || []);

const lineTotalSum = computed(() =>
    lineItems.value.reduce((sum, row) => sum + (Number(row.line_total_cost) || 0), 0),
);

const showReject = ref(false);

const approveForm = useForm({});
const rejectForm = useForm({
    rejection_reason: '',
    vendor_notes: '',
});

const approve = () => {
    approveForm.post(route('vendor.portal.warranty-claims.approve', props.record.id));
};

const reject = () => {
    rejectForm.post(route('vendor.portal.warranty-claims.reject', props.record.id), {
        onSuccess: () => {
            showReject.value = false;
        },
    });
};

const publicReviewUrl = computed(() => {
    if (!props.record.uuid) return null;
    try {
        return route('warranty-claims.review', props.record.uuid);
    } catch {
        return `/warranty-claims/${props.record.uuid}/review`;
    }
});
</script>

<template>
    <ClientPortalLayout :title="claimRef">
        <Head :title="`${claimRef} - Vendor Portal`" />

        <div class="mb-4">
            <Link
                :href="route('vendor.portal.warranty-claims.index')"
                class="text-sm font-medium text-primary-600 hover:text-primary-700 no-underline inline-flex items-center gap-1"
            >
                <span class="material-icons text-base">arrow_back</span>
                Back to warranty claims
            </Link>
        </div>

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
        >
            {{ flashError }}
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Warranty claim</p>
                    <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ claimRef }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ formatDate(record.created_at) }}</p>
                </div>
                <div class="text-right">
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800"
                    >
                        {{ statusLabel(record.status) }}
                    </span>
                    <p class="text-sm text-gray-600 mt-2">
                        Total
                        <span class="font-semibold text-gray-900">{{ formatMoney(record.total_amount) }}</span>
                    </p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Manufacturer</p>
                        <p class="text-gray-900 mt-1">{{ record.vendor?.display_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Work order</p>
                        <p class="text-gray-900 mt-1">
                            {{ record.work_order?.display_name || record.work_order?.work_order_number || '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Location</p>
                        <p class="text-gray-900 mt-1">{{ record.location?.display_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Subsidiary</p>
                        <p class="text-gray-900 mt-1">{{ record.subsidiary?.display_name ?? '—' }}</p>
                    </div>
                </div>

                <div v-if="record.vendor_notes" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Manufacturer notes</p>
                    <p class="text-gray-800 whitespace-pre-wrap">{{ record.vendor_notes }}</p>
                </div>

                <div v-if="record.rejection_reason" class="rounded-lg border border-red-100 bg-red-50 p-4 text-sm">
                    <p class="text-xs font-semibold text-red-700 uppercase mb-2">Rejection reason</p>
                    <p class="text-red-900 whitespace-pre-wrap">{{ record.rejection_reason }}</p>
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Line items</h2>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Description</th>
                                    <th class="px-3 py-2 font-medium text-right">Qty</th>
                                    <th class="px-3 py-2 font-medium text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in lineItems" :key="row.id">
                                    <td class="px-3 py-2 text-gray-800">{{ row.description }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700">{{ row.quantity ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right text-gray-900">
                                        {{ formatMoney(row.line_total_cost) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200">
                                <tr>
                                    <td colspan="2" class="px-3 py-2 text-right font-semibold text-gray-700">Sum</td>
                                    <td class="px-3 py-2 text-right font-semibold text-gray-900">
                                        {{ formatMoney(lineTotalSum) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div v-if="publicReviewUrl" class="text-sm text-gray-600">
                    <span class="font-medium text-gray-800">Public preview:</span>
                    <a :href="publicReviewUrl" class="text-primary-600 hover:text-primary-700 break-all ml-1">{{
                        publicReviewUrl
                    }}</a>
                </div>

                <div v-if="canRespond" class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
                    <button
                        type="button"
                        :disabled="approveForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
                        @click="approve"
                    >
                        <span class="material-icons text-base">check_circle</span>
                        Approve claim
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50"
                        @click="showReject = !showReject"
                    >
                        <span class="material-icons text-base">cancel</span>
                        Reject claim
                    </button>
                </div>

                <div v-if="canRespond && showReject" class="rounded-lg border border-gray-200 p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rejection reason (required)</label>
                        <textarea
                            v-model="rejectForm.rejection_reason"
                            rows="4"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Explain why this claim is rejected…"
                        />
                        <p v-if="rejectForm.errors.rejection_reason" class="mt-1 text-sm text-red-600">
                            {{ rejectForm.errors.rejection_reason }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes to provider (optional)</label>
                        <textarea
                            v-model="rejectForm.vendor_notes"
                            rows="3"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Optional context for the service provider…"
                        />
                    </div>
                    <button
                        type="button"
                        :disabled="rejectForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50"
                        @click="reject"
                    >
                        Submit rejection
                    </button>
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>
