<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    estimates: Object,
    statuses:  { type: Array, default: () => [] },
});

// Tailwind-safe text classes keyed by EstimateStatus::color()
const STATUS_TEXT = {
    gray: 'text-gray-700', blue: 'text-blue-700', yellow: 'text-yellow-800',
    green: 'text-green-700', red: 'text-red-700', orange: 'text-orange-700',
    purple: 'text-purple-700', slate: 'text-slate-700',
};

const resolveStatus = (raw) =>
    props.statuses.find(o => o.id == raw || o.value === raw) ?? null;

const statusBadgeClass = (raw) => {
    const opt = resolveStatus(raw);
    if (!opt) return 'bg-gray-100 text-gray-600';
    return [opt.bgClass, STATUS_TEXT[opt.color] ?? 'text-gray-700'].join(' ');
};

const statusName = (raw) => resolveStatus(raw)?.name ?? raw ?? 'Draft';

const fmt = (v) =>
    v != null ? `$${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—';

const fmtDate = (v) =>
    v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <ClientPortalLayout title="Estimates">
        <Head title="Estimates - Customer Portal" />

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Your Estimates</h2>
            </div>

            <!-- Table -->
            <div v-if="estimates?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Estimate #</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider text-right">Subtotal</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider text-right">Tax</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider text-right">Total</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="est in estimates.data" :key="est.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <Link
                                    :href="route('portal.estimate.show', est.id)"
                                    class="font-medium text-primary-600 hover:text-primary-700 hover:underline"
                                >
                                    {{ est.display_name || `Estimate #${est.id}` }}
                                </Link>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                    :class="statusBadgeClass(est.status)"
                                >
                                    {{ statusName(est.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 tabular-nums">
                                {{ fmt(est.primary_version?.subtotal) }}
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 tabular-nums">
                                {{ fmt(est.primary_version?.tax) }}
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900 tabular-nums">
                                {{ fmt(est.primary_version?.total) }}
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ fmtDate(est.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">request_quote</span>
                <p class="text-sm text-gray-500">No estimates found.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="estimates?.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in estimates.links" :key="link.label">
                <component
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url"
                    class="px-3 py-1.5 text-xs rounded-lg border transition-colors no-underline"
                    :class="link.active
                        ? 'bg-primary-600 text-white border-primary-600'
                        : link.url
                            ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'
                            : 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'"
                    v-html="link.label"
                />
            </template>
        </div>
    </ClientPortalLayout>
</template>
