<script setup>
/** Public specification sheet (UUID link, no portal login) — content aligned with `Portal/SpecSheetShow.vue`. */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    documentRef: { type: String, default: null },
    headline: String,
    subhead: { type: String, default: null },
    description: { type: String, default: null },
    makeName: { type: String, default: null },
    year: { type: [Number, String], default: null },
    specRows: { type: Array, default: () => [] },
    primaryImageUrl: { type: String, default: null },
    account: Object,
    logoUrl: { type: String, default: null },
    dealerHeader: { type: Object, default: () => ({}) },
    sentAt: { type: String, default: null },
    appName: { type: String, default: 'Maritime' },
    termsUrl: { type: String, default: '/terms' },
});

const dh = computed(() => props.dealerHeader ?? {});

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

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    const cleaned = String(phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};

const handlePrint = () => window.print();

const headerLogo = computed(() => dh.value.logo_url || props.logoUrl || null);
</script>

<template>
    <Head :title="`${headline} — Specification sheet`" />

    <div class="min-h-screen bg-gray-100 print:bg-white">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <p class="mb-6 text-center text-sm text-gray-500 print:hidden">
                Specification sheet from
                {{ dh.display_name || account?.settings?.business_name || account?.name || 'your dealer' }}
            </p>

            <div id="spec-sheet-print-root" class="mx-auto w-full max-w-5xl print:max-w-none">
                <div
                    class="overflow-hidden rounded-none border border-gray-200 bg-white shadow-lg sm:rounded-lg print:border-0 print:shadow-none print:rounded-none"
                >
                    <div class="border-b-4 border-gray-900 px-6 py-6 sm:px-8 print:border-b-2">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-1 items-start gap-6">
                                <div v-if="headerLogo" class="flex-shrink-0">
                                    <img :src="headerLogo" alt="" class="h-20 max-w-[180px] object-contain" />
                                </div>
                                <div
                                    v-else
                                    class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded bg-gray-200 print:bg-gray-100"
                                >
                                    <span class="material-icons text-4xl text-gray-400">business</span>
                                </div>
                                <div class="min-w-0">
                                    <h1 class="truncate text-2xl font-bold text-gray-900">
                                        {{ dh.display_name || 'Company' }}
                                    </h1>
                                    <div class="mt-2 space-y-1 text-sm text-gray-600">
                                        <p v-if="dh.address_line1">
                                            {{ dh.address_line1 }}
                                            <span v-if="dh.address_line2">, {{ dh.address_line2 }}</span>
                                        </p>
                                        <p v-if="dh.city">
                                            {{ dh.city }}<span v-if="dh.state">, {{ dh.state }}</span>
                                            {{ dh.postal_code }}
                                        </p>
                                        <p v-if="dh.phone" class="flex items-center gap-1">
                                            <span class="material-icons text-sm">phone</span>
                                            {{ formatPhoneNumber(dh.phone) }}
                                        </p>
                                        <p v-if="dh.email" class="flex items-center gap-1 break-all">
                                            <span class="material-icons flex-shrink-0 text-sm">email</span>
                                            {{ dh.email }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div class="text-sm font-medium uppercase text-gray-600">Specification sheet</div>
                                <div class="font-mono text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                    {{ documentRef || '—' }}
                                </div>
                                <div v-if="sentAt" class="mt-1 text-sm text-gray-600">
                                    {{ formatDate(sentAt) }}
                                </div>
                                <div v-else class="mt-1 text-sm text-gray-600">Public specification sheet</div>
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-6 sm:px-8">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Model &amp; specifications
                                </h2>
                                <div class="rounded-lg border border-gray-200 bg-white p-4">
                                    <div class="text-lg font-semibold leading-snug text-gray-900">
                                        {{ headline }}
                                    </div>
                                    <div v-if="subhead" class="mt-1 text-base text-gray-800">
                                        {{ subhead }}
                                    </div>
                                    <div class="mt-3 space-y-1 text-sm text-gray-600">
                                        <div v-if="makeName">
                                            <span class="font-medium text-gray-700">Make:</span> {{ makeName }}
                                        </div>
                                        <div v-if="year">
                                            <span class="font-medium text-gray-700">Year:</span> {{ year }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="primaryImageUrl">
                                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 print:hidden">
                                    Photo
                                </h2>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white print:hidden">
                                    <img :src="primaryImageUrl" alt="" class="aspect-[4/3] w-full object-cover" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="description" class="border-t border-gray-200 px-6 py-6 sm:px-8">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Description</h2>
                        <div class="prose prose-sm max-w-none">
                            <p class="whitespace-pre-line text-gray-900">{{ description }}</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-6 sm:px-8">
                        <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Additional specifications
                        </h2>

                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-gray-900">
                                    <th class="py-3 pr-4 text-left text-sm font-semibold text-gray-900">Specification</th>
                                    <th class="py-3 pl-4 text-right text-sm font-semibold text-gray-900">Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="(row, idx) in specRows"
                                    :key="idx"
                                    class="hover:bg-gray-50 print:hover:bg-transparent"
                                >
                                    <td class="align-top py-3 pr-4 text-gray-800">{{ row.label }}</td>
                                    <td class="align-top py-3 pl-4 text-right font-medium text-gray-900">
                                        {{ row.value ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="!specRows?.length" class="py-10 text-center text-sm text-gray-500">
                            No specification values recorded for this sheet.
                        </div>
                    </div>

                    <div class="bg-gray-900 px-6 py-4 text-center text-xs text-white print:bg-gray-900">
                        <p>Powered by <a :href="termsUrl" target="_blank" rel="noopener noreferrer" class="underline">{{ appName }}</a></p>
                        <p v-if="dh.phone" class="mt-1">
                            Questions? Call us at {{ formatPhoneNumber(dh.phone) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-center print:hidden">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
                        @click="handlePrint"
                    >
                        <span class="material-icons text-sm">print</span>
                        Print copy
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .min-h-screen {
        min-height: auto;
    }
}
</style>
