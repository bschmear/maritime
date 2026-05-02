<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    vendor: Object,
    recentWarrantyClaims: { type: Array, default: () => [] },
    counts: Object,
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
    <ClientPortalLayout title="Overview">
        <Head title="Vendor Portal" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">
                Welcome{{ vendor?.first_name ? `, ${vendor.first_name}` : '' }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">Manufacturer warranty claims assigned to your organization.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <Link
                :href="route('vendor.portal.warranty-claims.index')"
                class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow group no-underline"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-primary-100 text-primary-600">
                        <span class="material-icons text-xl">verified_user</span>
                    </div>
                    <span class="material-icons text-gray-300 group-hover:text-gray-400 transition-colors text-sm">
                        arrow_forward
                    </span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ counts?.warrantyClaims ?? 0 }}</p>
                <p class="text-sm text-gray-500 mt-0.5">Warranty claims</p>
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900 text-sm">Recent warranty claims</h3>
                    <Link
                        :href="route('vendor.portal.warranty-claims.index')"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 no-underline"
                    >
                        View all
                    </Link>
                </div>

                <div v-if="recentWarrantyClaims?.length" class="divide-y divide-gray-50">
                    <div
                        v-for="c in recentWarrantyClaims"
                        :key="c.id"
                        class="px-5 py-3 hover:bg-gray-50 transition-colors"
                    >
                        <Link
                            class="flex items-center justify-between no-underline"
                            :href="route('vendor.portal.warranty-claims.show', c.id)"
                        >
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ c.display_name ?? `Claim #${c.id}` }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ c.vendor?.display_name ?? '' }}
                                    <span v-if="c.updated_at"> · {{ c.updated_at }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-medium text-gray-600">{{ formatMoney(c.total_amount) }}</span>
                                <span class="text-xs font-medium px-2 py-1 rounded-full" :class="statusBadgeClass(c.status)">
                                    {{ statusLabel(c.status) }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-gray-400">No warranty claims yet</div>
            </div>
        </div>
    </ClientPortalLayout>
</template>
