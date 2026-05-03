<script setup>
/** Portal specification sheet — visual shell aligned with `Tenant/Public/ServiceTicketReview.vue`. */
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import AssignedUserContactCard from '@/Components/Portal/AssignedUserContactCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    shareUuid: String,
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
    assignedUser: { type: Object, default: null },
    assetOptions: { type: Array, default: () => [] },
    savedSelections: { type: Array, default: () => [] },
    specSheetOptionsSaveUrl: { type: String, default: '' },
});

const selections = ref([]);

watch(
    () => props.savedSelections,
    (v) => {
        selections.value = (v || []).map((s) => ({
            option_id: s.option_id,
            option_value_id: s.option_value_id,
        }));
    },
    { immediate: true, deep: true },
);

const isSelected = (optionId, valueId) =>
    selections.value.some(
        (s) => Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId),
    );

const toggleMulti = (optionId, valueId, checked) => {
    const rest = selections.value.filter(
        (s) => !(Number(s.option_id) === Number(optionId) && Number(s.option_value_id) === Number(valueId)),
    );
    if (checked) {
        selections.value = [...rest, { option_id: optionId, option_value_id: valueId }];
    } else {
        selections.value = rest;
    }
};

const setSingle = (optionId, valueId) => {
    const rest = selections.value.filter((s) => Number(s.option_id) !== Number(optionId));
    selections.value = [...rest, { option_id: optionId, option_value_id: valueId }];
};

const saveSelections = () => {
    if (!props.specSheetOptionsSaveUrl) return;
    router.post(props.specSheetOptionsSaveUrl, { selections: selections.value }, { preserveScroll: true });
};

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

const formatMoney = (n) => {
    if (n === null || n === undefined || n === '') return '—';
    const x = Number(n);
    if (Number.isNaN(x)) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(x);
};

const headerLogo = computed(() => dh.value.logo_url || props.logoUrl || null);
</script>

<template>
    <ClientPortalLayout title="Specification sheet">
        <Head :title="`${headline} — Specification sheet`" />

        <div
            class="max-w-7xl mx-auto w-full -m-6 mb-0 p-6 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(260px,20rem)] gap-6 lg:gap-8 items-start print:block print:p-0 print:max-w-none print:m-0"
        >
            <div id="spec-sheet-print-root" class="min-w-0 w-full print:max-w-none">
                <div class="bg-white shadow-lg border border-gray-200 rounded-none sm:rounded-lg overflow-hidden print:shadow-none print:border-0 print:rounded-none">
                <!-- Company header (same rhythm as ServiceTicketReview) -->
                <div class="border-b-4 border-gray-900 px-6 sm:px-8 py-6 print:border-b-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-6 min-w-0">
                            <div v-if="headerLogo" class="flex-shrink-0">
                                <img :src="headerLogo" alt="" class="h-20 w-auto max-w-[180px] object-contain" />
                            </div>
                            <div
                                v-else
                                class="flex-shrink-0 h-20 w-20 bg-gray-200 rounded flex items-center justify-center print:bg-gray-100"
                            >
                                <span class="material-icons text-4xl text-gray-400">business</span>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-2xl font-bold text-gray-900 truncate">
                                    {{ dh.display_name || 'Company' }}
                                </h1>
                                <div class="mt-2 text-sm text-gray-600 space-y-1">
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
                                        <span class="material-icons text-sm flex-shrink-0">email</span>
                                        {{ dh.email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-medium text-gray-600 uppercase">Specification sheet</div>
                            <div class="text-2xl sm:text-3xl font-bold text-gray-900 font-mono tracking-tight">
                                {{ documentRef || '—' }}
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ formatDate(sentAt) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject summary -->
                <div class="px-6 sm:px-8 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                Model &amp; specifications
                            </h2>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <div class="font-semibold text-gray-900 text-lg leading-snug">
                                    {{ headline }}
                                </div>
                                <div v-if="subhead" class="text-base text-gray-800 mt-1">
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
                            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 print:hidden">
                                Photo
                            </h2>
                            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden print:hidden">
                                <img :src="primaryImageUrl" alt="" class="w-full object-cover aspect-[4/3]" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="description" class="px-6 sm:px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Description</h2>
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-900 whitespace-pre-line">{{ description }}</p>
                    </div>
                </div>

                <!-- Configurable options -->
                <div
                    v-if="assetOptions?.length"
                    class="px-6 sm:px-8 py-6 border-t border-gray-200 print:break-inside-avoid"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Available options
                        </h2>
                        <button
                            v-if="specSheetOptionsSaveUrl"
                            type="button"
                            class="print:hidden inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                            @click="saveSelections"
                        >
                            Save my selections
                        </button>
                    </div>
                    <div class="space-y-6">
                        <div v-for="opt in assetOptions" :key="opt.option_id" class="rounded-lg border border-gray-200 bg-gray-50/80 p-4">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ opt.name }}
                                <span v-if="opt.is_required" class="text-red-500">*</span>
                            </div>
                            <div v-if="opt.input_type === 'multi_select'" class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="v in opt.values"
                                    :key="v.id"
                                    class="inline-flex items-center gap-2 text-sm text-gray-800"
                                >
                                    <input
                                        type="checkbox"
                                        class="print:hidden rounded border-gray-300"
                                        :checked="isSelected(opt.option_id, v.id)"
                                        @change="toggleMulti(opt.option_id, v.id, $event.target.checked)"
                                    />
                                    <span>{{ v.label }}</span>
                                    <span class="text-gray-500 tabular-nums">{{ formatMoney(v.price) }}</span>
                                </label>
                            </div>
                            <div v-else class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="v in opt.values"
                                    :key="v.id"
                                    class="inline-flex items-center gap-2 text-sm text-gray-800"
                                >
                                    <input
                                        type="radio"
                                        class="print:hidden"
                                        :name="`portal-ao-${opt.option_id}`"
                                        :checked="isSelected(opt.option_id, v.id)"
                                        @change="setSingle(opt.option_id, v.id)"
                                    />
                                    <span
                                        v-if="v.color_hex"
                                        class="inline-block h-4 w-4 rounded border border-gray-300"
                                        :style="{ backgroundColor: v.color_hex }"
                                    />
                                    <span>{{ v.label }}</span>
                                    <span class="text-gray-500 tabular-nums">{{ formatMoney(v.price) }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All specifications (table — same spirit as Service Items on service ticket) -->
                <div class="px-6 sm:px-8 py-6 border-t border-gray-200">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Additional specifications
                    </h2>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-900">
                                <th class="text-left py-3 pr-4 text-sm font-semibold text-gray-900">Specification</th>
                                <th class="text-right py-3 pl-4 text-sm font-semibold text-gray-900">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(row, idx) in specRows" :key="idx" class="hover:bg-gray-50 print:hover:bg-transparent">
                                <td class="py-3 pr-4 text-gray-800 align-top">{{ row.label }}</td>
                                <td class="py-3 pl-4 text-right font-medium text-gray-900 align-top">
                                    {{ row.value ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="!specRows?.length" class="text-center py-10 text-gray-500 text-sm">
                        No specification values recorded for this sheet.
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 sm:px-8 py-4 bg-gray-900 text-white text-center text-xs print:bg-gray-900">
                    <p>Powered by <a :href="termsUrl" target="_blank" rel="noopener noreferrer" class="underline">{{ appName }}</a></p>
                    <p v-if="dh.phone" class="mt-1">
                        Questions? Call us at {{ formatPhoneNumber(dh.phone) }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-center print:hidden">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors shadow-sm"
                    @click="handlePrint"
                >
                    <span class="material-icons text-sm">print</span>
                    Print copy
                </button>
            </div>
            </div>

            <aside
                v-if="assignedUser"
                class="print:hidden w-full lg:sticky lg:top-6 lg:self-start shrink-0"
            >
                <AssignedUserContactCard :assigned-user="assignedUser" class="w-full" />
            </aside>
        </div>
    </ClientPortalLayout>
</template>

<style scoped>
@media print {
    :deep(main) {
        padding: 0 !important;
    }
}
</style>
