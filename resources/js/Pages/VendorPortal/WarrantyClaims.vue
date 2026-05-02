<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    claims: Object,
    statuses: { type: Array, default: () => [] },
});

const statusLabel = (status) => props.statuses.find((s) => s.value === status || s.id === status)?.name ?? status ?? '—';

const statusBadgeClass = (status) => {
    const s = props.statuses.find((x) => x.value === status || x.id === status);
    if (!s) return 'bg-gray-100 text-gray-600';
    const map = {
        gray: 'bg-gray-100 text-gray-700',
        yellow: 'bg-yellow-100 text-yellow-700',
        green: 'bg-green-100 text-green-700',
        red: 'bg-red-100 text-red-700',
        orange: 'bg-orange-100 text-orange-700',
        purple: 'bg-purple-100 text-purple-700',
        slate: 'bg-slate-100 text-slate-700',
        blue: 'bg-blue-100 text-blue-800',
        indigo: 'bg-indigo-100 text-indigo-800',
        zinc: 'bg-zinc-100 text-zinc-800',
    };
    return map[s.color] ?? 'bg-gray-100 text-gray-700';
};

const formatMoney = (v) =>
    v != null
        ? `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : '—';
</script>

<template>
    <ClientPortalLayout title="Warranty claims">
        <Head title="Warranty claims - Vendor Portal" />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Warranty claims</h2>
                <p class="text-sm text-gray-500 mt-0.5">Full history for your manufacturer organizations</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Claim</th>
                            <th class="px-4 py-3 font-medium">Manufacturer</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                            <th class="px-4 py-3 font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in claims.data" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('vendor.portal.warranty-claims.show', c.id)"
                                    class="font-medium text-primary-600 hover:text-primary-700 no-underline"
                                >
                                    {{ c.display_name ?? `Claim #${c.id}` }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ c.vendor?.display_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium px-2 py-1 rounded-full" :class="statusBadgeClass(c.status)">
                                    {{ statusLabel(c.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900">{{ formatMoney(c.total_amount) }}</td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ c.updated_at ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="claims.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="link in claims.links" :key="link.label">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        :href="link.url"
                        class="px-3 py-1.5 text-xs rounded-lg border transition-colors no-underline"
                        :class="
                            link.active
                                ? 'bg-primary-600 text-white border-primary-600'
                                : link.url
                                  ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'
                                  : 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'
                        "
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </ClientPortalLayout>
</template>
