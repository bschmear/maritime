<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    orders: {
        type: Array,
        default: () => [],
    },
    technicians: {
        type: Array,
        default: () => [],
    },
    weekLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const groupByTechnician = ref(true);
const printing = ref(false);
const qrCodes = ref({});
const qrLoading = ref(false);

const UNASSIGNED_TECH_ID = 0;

const techName = (techId) => {
    if (Number(techId) === UNASSIGNED_TECH_ID) {
        return 'Unassigned';
    }
    return props.technicians.find((t) => Number(t.id) === Number(techId))?.name ?? '—';
};

const statusLabel = (status) => (status || '').replace(/_/g, ' ');

const sortedOrders = computed(() =>
    [...props.orders].sort((a, b) => {
        const techCmp = Number(a.technician_id) - Number(b.technician_id);
        if (techCmp !== 0) {
            return techCmp;
        }
        const dateCmp = String(a.start_date).localeCompare(String(b.start_date));
        if (dateCmp !== 0) {
            return dateCmp;
        }
        return String(a.start_time_label || '').localeCompare(String(b.start_time_label || ''));
    }),
);

const groupedSections = computed(() => {
    if (!groupByTechnician.value) {
        return [{ techId: null, techName: null, orders: sortedOrders.value }];
    }

    const sections = [];
    const byTech = new Map();

    for (const order of sortedOrders.value) {
        const key = Number(order.technician_id);
        if (!byTech.has(key)) {
            byTech.set(key, []);
        }
        byTech.get(key).push(order);
    }

    const techIds = [...byTech.keys()].sort((a, b) => {
        if (a === UNASSIGNED_TECH_ID) {
            return -1;
        }
        if (b === UNASSIGNED_TECH_ID) {
            return 1;
        }
        return a - b;
    });

    for (const techId of techIds) {
        sections.push({
            techId,
            techName: techName(techId),
            orders: byTech.get(techId) ?? [],
        });
    }

    return sections;
});

const loadQrCodes = async () => {
    const urls = [...new Set(props.orders.map((o) => o.show_url).filter(Boolean))];
    if (!urls.length) {
        qrCodes.value = {};
        return;
    }

    qrLoading.value = true;
    try {
        const { data } = await axios.post(route('scheduling.qr-codes'), { urls });
        qrCodes.value = data.codes ?? {};
    } catch (error) {
        console.error('Failed to load QR codes:', error);
        qrCodes.value = {};
    } finally {
        qrLoading.value = false;
    }
};

onMounted(loadQrCodes);
watch(() => props.orders, loadQrCodes, { deep: true });

const handlePrint = () => {
    printing.value = true;
    setTimeout(() => {
        window.print();
        printing.value = false;
    }, 100);
};
</script>

<template>
    <div class="schedule-preview-shell min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-semibold text-gray-900 dark:text-white lg:text-lg">
                        Print schedule
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ weekLabel }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            v-model="groupByTechnician"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                        />
                        Group by technician
                    </label>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="emit('close')"
                    >
                        <span class="material-icons text-[18px]">close</span>
                        Close
                    </button>

                    <button
                        type="button"
                        :disabled="printing || qrLoading || !orders.length"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handlePrint"
                    >
                        <span v-if="printing || qrLoading" class="material-icons animate-spin text-[18px]">refresh</span>
                        <span v-else class="material-icons text-[18px]">print</span>
                        {{ printing ? 'Preparing…' : qrLoading ? 'Loading QR…' : 'Print' }}
                    </button>
                </div>
            </div>
        </div>

        <div id="schedule-print-root" class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <div class="schedule-document-for-print rounded-lg bg-white p-8 text-gray-900 shadow-lg print:rounded-none print:shadow-none">
                <header class="border-b-2 border-gray-900 pb-4 print:break-inside-avoid">
                    <h1 class="text-2xl font-bold">Work Order Schedule</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ weekLabel }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ orders.length }} work order{{ orders.length === 1 ? '' : 's' }}
                        <span v-if="groupByTechnician"> · grouped by technician</span>
                    </p>
                </header>

                <div v-if="!orders.length" class="py-12 text-center text-sm text-gray-500">
                    No work orders scheduled for this week.
                </div>

                <div v-else class="mt-6 space-y-8">
                    <section
                        v-for="(section, sectionIndex) in groupedSections"
                        :key="section.techId ?? `flat-${sectionIndex}`"
                        class="print:break-inside-avoid"
                    >
                        <h2
                            v-if="groupByTechnician && section.techName"
                            class="mb-3 border-b border-gray-300 pb-2 text-lg font-semibold text-gray-900"
                        >
                            {{ section.techName }}
                        </h2>

                        <div class="space-y-3">
                            <article
                                v-for="order in section.orders"
                                :key="order.id"
                                class="flex items-start justify-between gap-4 border border-gray-200 rounded-lg p-4 print:break-inside-avoid"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-gray-900">
                                            {{ order.work_order_label || order.title }}
                                        </h3>
                                        <span
                                            v-if="!groupByTechnician"
                                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                                        >
                                            {{ techName(order.technician_id) }}
                                        </span>
                                    </div>

                                    <p v-if="order.service_ticket_number" class="mt-1 text-sm text-gray-600">
                                        Service ticket: ST-{{ order.service_ticket_number }}
                                    </p>

                                    <p v-if="order.description" class="mt-2 text-sm text-gray-700">
                                        {{ order.description }}
                                    </p>

                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-gray-600 sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs uppercase tracking-wide text-gray-400">Start</dt>
                                            <dd>{{ order.start_date_label }} · {{ order.start_time_label }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs uppercase tracking-wide text-gray-400">End</dt>
                                            <dd>{{ order.end_date_label }} · {{ order.end_time_label }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs uppercase tracking-wide text-gray-400">Hours</dt>
                                            <dd>{{ order.planned_hours }} hrs</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs uppercase tracking-wide text-gray-400">Status</dt>
                                            <dd class="capitalize">{{ statusLabel(order.status) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="shrink-0 text-center">
                                    <img
                                        v-if="order.show_url && qrCodes[order.show_url]"
                                        :src="qrCodes[order.show_url]"
                                        :alt="`QR code for ${order.work_order_label || order.title}`"
                                        class="h-24 w-24 rounded border border-gray-200"
                                    />
                                    <div
                                        v-else
                                        class="flex h-24 w-24 items-center justify-center rounded border border-dashed border-gray-300 text-xs text-gray-400"
                                    >
                                        QR
                                    </div>
                                    <p class="mt-1 max-w-[6rem] text-[10px] leading-tight text-gray-500">
                                        Scan to open work order
                                    </p>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
