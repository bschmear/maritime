<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    shares: Object,
});

const variantLabel = (share) => {
    if (!share?.asset_variant_id) return 'Base asset';
    const v = share.asset_variant;
    return v?.display_name || v?.name || `Variant #${share.asset_variant_id}`;
};
</script>

<template>
    <ClientPortalLayout title="Specification sheets">
        <Head title="Specification sheets – Customer Portal" />

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Specification sheets</h2>
                <p class="text-sm text-gray-500 mt-1">Links shared with you by your dealer.</p>
            </div>

            <div v-if="shares?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Asset</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Specification</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Shared</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="share in shares.data" :key="share.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                {{ share.asset?.display_name || `Asset #${share.asset_id}` }}
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ variantLabel(share) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ share.sent_at }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="route('portal.specSheet.show', share.uuid)"
                                    class="text-primary-600 hover:text-primary-700 font-medium text-sm no-underline"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">description</span>
                <p class="text-sm text-gray-500">No specification sheets yet.</p>
            </div>
        </div>

        <div v-if="shares?.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in shares.links" :key="link.label">
                <component
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url"
                    class="px-3 py-1.5 text-xs rounded-lg border transition-colors no-underline"
                    :class="link.active ? 'bg-primary-600 text-white border-primary-600' : link.url ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' : 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'"
                    v-html="link.label"
                />
            </template>
        </div>
    </ClientPortalLayout>
</template>
