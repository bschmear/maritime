<template>
    <div
        class="min-w-[700px] overflow-hidden rounded-xl border border-stone-200 bg-stone-50 font-sans text-stone-900 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-100"
    >
        <!-- Header: light = stone panel + dark text; dark = navy + light text -->
        <div
            class="flex items-center justify-between border-b border-stone-200 bg-stone-100 px-5 py-4 text-stone-800 dark:border-white/5 dark:bg-[#1a1a2e] dark:text-white"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md border border-stone-300 bg-white text-lg text-stone-800 shadow-sm transition hover:bg-stone-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:shadow-none dark:hover:bg-white/20"
                    @click="changeDay(-1)"
                >
                    ←
                </button>
                <span class="text-lg font-medium tracking-wide text-stone-900 dark:text-white md:text-lg">
                    {{ formattedDate }}
                </span>
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md border border-stone-300 bg-white text-lg text-stone-800 shadow-sm transition hover:bg-stone-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:shadow-none dark:hover:bg-white/20"
                    @click="changeDay(1)"
                >
                    →
                </button>
            </div>
            <div
                class="flex flex-wrap items-center justify-end gap-x-5 gap-y-2 text-md text-stone-600 dark:text-white/70"
            >
                <p v-if="scheduleError" class="text-sm text-red-600 dark:text-red-400 max-w-md text-right">
                    {{ scheduleError }}
                </p>
                <p v-else-if="scheduleLoading" class="text-sm text-stone-500 dark:text-white/50">Loading…</p>
                <div
                    class="flex items-center gap-1.5 rounded-md border border-stone-200 bg-white px-1.5 py-1 shadow-sm dark:border-white/15 dark:bg-white/5 dark:shadow-none"
                    :title="zoomHint"
                >
                    <span class="hidden pl-0.5 text-sm text-stone-500 sm:inline dark:text-white/50">View</span>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded text-base leading-none text-stone-800 transition enabled:hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                        :disabled="viewHourCount <= 1"
                        aria-label="Show fewer hours on the schedule"
                        @click="zoomIn"
                    >
                        −
                    </button>
                    <span
                        class="min-w-[9.5rem] text-center text-sm font-medium tabular-nums text-stone-800 dark:text-white/90"
                    >
                        {{ viewRangeLabel }}
                    </span>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded text-base leading-none text-stone-800 transition enabled:hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                        :disabled="viewHourCount >= maxViewHours"
                        aria-label="Show more hours on the schedule (zoom out)"
                        @click="zoomOut"
                    >
                        +
                    </button>
                </div>
                <div class="flex items-center gap-5">
                    <span class="flex items-center gap-2 text-stone-700 dark:text-white/80">
                        <span class="h-3 w-3 rounded-sm bg-blue-500" />
                        At location
                    </span>
                    <span class="flex items-center gap-2 text-stone-700 dark:text-white/80">
                        <span
                            class="h-1.5 w-6 rounded-sm bg-amber-500 shadow-sm ring-1 ring-amber-600/20 dark:ring-amber-700/20"
                        />
                        Travel
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid: horizontal scroll on timeline only; name column stays fixed (sticky) -->
        <div class="overflow-x-auto overflow-y-visible">
            <!-- Time axis -->
            <div
                class="flex w-full min-w-[1200px] items-stretch border-b border-stone-200 dark:border-gray-600"
            >
                <div
                    class="sticky left-0 z-20 h-11 w-48 min-w-48 shrink-0 self-stretch border-r border-stone-200 bg-stone-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.12)] dark:border-gray-600 dark:bg-gray-800/80"
                />
                <div class="relative h-11 min-w-0 flex-1 bg-stone-100 dark:bg-gray-800/80">
                    <!-- One label per 1h cell, centered in that cell (not on the boundary lines) -->
                    <div
                        v-for="slot in hourLabelSlots"
                        :key="'hlabel-' + slot.h"
                        class="absolute bottom-0 top-0 flex items-end justify-center pb-2"
                        :style="{ left: slot.leftPct + '%', transform: 'translateX(-50%)' }"
                    >
                        <span
                            class="whitespace-nowrap text-sm font-medium text-gray-600 dark:text-gray-400 md:text-md"
                        >
                            {{ slot.text }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Technician rows -->
            <div
                v-for="tech in technicians"
                :key="tech.id"
                class="flex w-full min-w-[1200px] h-20 min-h-[80px] items-stretch border-b border-stone-200 transition-colors dark:border-gray-600"
                :class="dragOverTech === tech.id ? 'bg-indigo-50 dark:bg-indigo-950/30' : ''"
                @dragover.prevent="onDragOver(tech.id)"
                @dragleave="onDragLeave"
                @drop="onDrop(tech.id)"
            >
                <div
                    class="sticky left-0 z-20 flex w-48 min-w-48 shrink-0 flex-col justify-center gap-0.5 self-stretch border-r border-stone-200 bg-white px-3 py-2 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)] dark:border-gray-600"
                    :class="dragOverTech === tech.id ? 'bg-indigo-50 dark:bg-indigo-950/30' : 'dark:bg-gray-800'"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                        >
                            {{ initials(tech.name) }}
                        </div>
                        <span class="truncate text-md font-semibold leading-tight text-stone-800 dark:text-gray-100">
                            {{ tech.name }}
                        </span>
                    </div>
                    <p class="pl-11 text-sm text-stone-500 dark:text-gray-400">
                        Total deliveries: <span class="font-medium text-stone-700 dark:text-gray-300">{{ deliveriesForTech(tech.id).length }}</span>
                    </p>
                </div>

                <div class="relative h-20 min-h-[80px] min-w-0 flex-1 overflow-visible">
                    <div
                        v-for="m in gridLineMinutes"
                        :key="'line-' + m + '-' + tech.id"
                        class="pointer-events-none absolute top-0 bottom-0 w-px bg-stone-200 dark:bg-gray-600"
                        :style="{ left: minuteToViewPercent(m) + '%' }"
                    />

                    <template v-for="delivery in deliveriesForTech(tech.id)" :key="deliveryKey(tech.id, delivery)">
                        <!-- Outbound travel (clipped to visible day when leg starts before 6:00) -->
                        <div
                            class="travel-line travel-line--to"
                            :style="travelToStyle(delivery)"
                            :title="`Travel to ${delivery.end_location}: ${travelMins(delivery)} min`"
                        />
                        <!-- At-location / drop-off block -->
                        <div
                            class="absolute top-1/2 z-[2] flex h-14 min-w-0.5 -translate-y-1/2 cursor-grab flex-col justify-center overflow-hidden rounded-md bg-blue-500 px-2 text-white shadow-md shadow-blue-500/35 transition hover:brightness-110 active:scale-[0.99] active:cursor-grabbing"
                            :style="deliveryBlockStyle(delivery)"
                            :title="`${delivery.display_name} — ${delivery.customer_name}\n${delivery.start_location} → ${delivery.end_location}\nScheduled: ${formatTime(delivery.scheduled_at)}`"
                            draggable="true"
                            @dragstart="onDragStart(delivery, tech.id)"
                            @dragend="onDragEnd"
                            @click="selectedDelivery = delivery"
                        >
                            <span class="truncate text-sm font-bold leading-tight text-white">
                                {{ delivery.display_name }}
                            </span>
                            <span class="truncate text-[11px] text-white/90">
                                {{ delivery.customer_name }}
                            </span>
                        </div>
                        <!-- Return travel (clipped when leg runs past end of day window) -->
                        <div
                            class="travel-line travel-line--back"
                            :style="travelBackStyle(delivery)"
                            :title="`Return from ${delivery.end_location}: ${travelMins(delivery)} min`"
                        />
                    </template>
                </div>
            </div>
            <div
                v-if="!technicians.length && !scheduleLoading"
                class="flex min-h-[120px] w-full items-center justify-center border-b border-stone-200 bg-white px-6 py-10 text-sm text-stone-600 dark:border-gray-600 dark:bg-gray-900/30 dark:text-gray-300"
            >
                <span v-if="scheduleError">Could not load schedule data.</span>
                <span v-else>No technicians on file. Mark users as technicians to see delivery rows here.</span>
            </div>
        </div>

        <!-- Detail panel -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="selectedDelivery"
                class="relative border-t-2 border-blue-500 bg-white px-5 py-4 dark:border-primary-500 dark:bg-gray-800"
            >
                <button
                    type="button"
                    class="absolute right-4 top-3 text-md text-gray-500 transition hover:text-gray-800 dark:hover:text-gray-200"
                    aria-label="Close"
                    @click="selectedDelivery = null"
                >
                    ✕
                </button>
                <div class="mb-3 flex flex-wrap items-baseline gap-2.5 pr-8">
                    <Link
                        v-if="selectedDelivery.id"
                        :href="route('deliveries.show', selectedDelivery.id)"
                        class="text-md font-bold text-blue-500 hover:text-blue-600 hover:underline dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        {{ selectedDelivery.display_name }}
                    </Link>
                    <span v-else class="text-md font-bold text-blue-500 dark:text-primary-400">
                        {{ selectedDelivery.display_name }}
                    </span>
                    <span class="text-md text-stone-600 dark:text-gray-300">
                        {{ selectedDelivery.customer_name }}
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-x-5 sm:gap-y-2.5">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">From</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ selectedDelivery.start_location }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">To</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ selectedDelivery.end_location }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Leave by</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ formatTime(selectedDelivery.time_to_leave_by) }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Scheduled</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ formatTime(selectedDelivery.scheduled_at) }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Travel</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ travelMins(selectedDelivery) }} min each way
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">At location</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ selectedDelivery.delivery_duration_minutes || 15 }} min
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Truck</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ fleetScheduleUnitLabel(selectedDelivery, 'truck') }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Trailer</span>
                        <span class="text-md font-medium text-stone-900 dark:text-white">
                            {{ fleetScheduleUnitLabel(selectedDelivery, 'trailer') }}
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import axios from 'axios';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { ref, computed, watch, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTimezone } from '@/composables/useTimezone';

dayjs.extend(utc);
dayjs.extend(timezone);

const props = defineProps({
    filterLocationId: { type: [Number, String, null], default: null },
});

const { accountTimezone } = useTimezone();

const DAY_START_HOUR = 6;
/** Grid ends at 8:00 PM so the 7:00–8:00 PM “7 PM” hour is a full column; lines at each hour. */
const DAY_END_HOUR = 20;
const TOTAL_HOURS = DAY_END_HOUR - DAY_START_HOUR;
const DAY_MINUTES_TOTAL = TOTAL_HOURS * 60;

/** Calendar day (Y-m-d) in account timezone — matches schedule-board `date` filter. */
const viewingDateYmd = ref(dayjs().tz(accountTimezone.value).format('YYYY-MM-DD'));
const selectedDelivery = ref(null);
const dragPayload = ref(null);
const dragSourceTech = ref(null);
const dragOverTech = ref(null);
const scheduleLoading = ref(false);
const scheduleError = ref(null);

/** 1h … up to full 6 AM–7 PM; default = full day */
const viewHourCount = ref(TOTAL_HOURS);
const maxViewHours = TOTAL_HOURS;

const technicians = ref([]);
const deliveries = ref({});

async function loadScheduleBoard() {
    scheduleLoading.value = true;
    scheduleError.value = null;
    try {
        const params = { date: viewingDateYmd.value };
        const lid = props.filterLocationId;
        if (lid != null && lid !== '' && Number(lid) > 0) {
            params.location_id = Number(lid);
        }
        const { data } = await axios.get(route('deliveries.schedule-board'), { params });
        technicians.value = data.technicians || [];
        deliveries.value = data.deliveriesByTechnician || {};
    } catch (e) {
        scheduleError.value = e.response?.data?.message || 'Could not load the schedule.';
        technicians.value = [];
        deliveries.value = {};
    } finally {
        scheduleLoading.value = false;
    }
}

watch(viewingDateYmd, () => {
    loadScheduleBoard();
});

watch(
    () => props.filterLocationId,
    () => {
        loadScheduleBoard();
    },
);

onMounted(() => {
    loadScheduleBoard();
});

const viewSpanMinutes = computed(() => viewHourCount.value * 60);

/**
 * Hour boundaries: 6:00, 7:00, …, 7:00 PM end — lines sit between cells, not in the text.
 * Includes the closing 8:00 PM line.
 */
const gridLineMinutes = computed(() => {
    const v1 = Math.min(viewSpanMinutes.value, DAY_MINUTES_TOTAL);
    const out = [];
    for (let m = 0; m <= v1; m += 60) {
        if (m > DAY_MINUTES_TOTAL) {
            break;
        }
        out.push(m);
    }
    if (v1 > 0 && v1 <= DAY_MINUTES_TOTAL && (out.length === 0 || out[out.length - 1] < v1)) {
        if (v1 % 60 !== 0) {
            out.push(v1);
        }
    }
    return out;
});

/**
 * One label per 1h cell, centered in that column (6–7a → 6 AM … 7–8p → 7 PM).
 */
const hourLabelSlots = computed(() => {
    const v1 = Math.min(viewSpanMinutes.value, DAY_MINUTES_TOTAL);
    const out = [];
    for (let h = DAY_START_HOUR; h < DAY_END_HOUR; h += 1) {
        const blockStart = (h - DAY_START_HOUR) * 60;
        const blockEnd = blockStart + 60;
        if (blockStart >= v1) {
            break;
        }
        const visibleEnd = Math.min(blockEnd, v1);
        const center = (blockStart + visibleEnd) / 2;
        out.push({ h, leftPct: minuteToViewPercent(center), text: formatHour(h) });
    }
    return out;
});

const viewRangeLabel = computed(() => {
    const h = viewHourCount.value;
    const startH = DAY_START_HOUR;
    const endH = startH + h;
    if (h >= maxViewHours) {
        return 'Full day (6a–8p)';
    }
    return `${h}h · ${formatHour(startH)} – ${formatHour(endH)}`;
});

const zoomHint = computed(
    () =>
        'Default is 6 AM–8 PM (full 7:00–8:00 PM block at the end). Use − / + to change how many hours are visible.',
);

const formattedDate = computed(() =>
    dayjs.tz(viewingDateYmd.value, accountTimezone.value).format('dddd, MMMM D, YYYY'),
);

function deliveriesForTech(techId) {
    return deliveries.value[String(techId)] || [];
}

function deliveryKey(techId, delivery) {
    return `${techId}::${delivery.id ?? delivery.display_name}`;
}

function parseDate(str) {
    if (!str) {
        return new Date(NaN);
    }
    const s = String(str);
    if (s.includes('T') || s.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(s)) {
        return new Date(s);
    }
    return new Date(s.replace(' ', 'T'));
}

function minutesFromDayStart(dateStr) {
    const d = parseDate(dateStr);
    if (isNaN(d.getTime())) {
        return 0;
    }
    return (d.getHours() - DAY_START_HOUR) * 60 + d.getMinutes();
}

/** Server-computed minutes from 6:00 AM on the board date (account TZ); falls back if missing. */
function blockStartMinutes(delivery) {
    const raw = delivery?.block_start_minutes;
    if (raw != null && Number.isFinite(Number(raw))) {
        return Number(raw);
    }
    return minutesFromDayStart(delivery.scheduled_at);
}

/**
 * Map [t0, t1] in day minutes (0…day end) into % of the current view window
 * (always 6:00–6:00+viewSpan, default first hour only).
 */
function segmentInView(t0, t1) {
    const v0 = 0;
    const v1 = Math.min(viewSpanMinutes.value, DAY_MINUTES_TOTAL);
    const span = v1 - v0;
    if (span <= 0 || t1 <= t0) {
        return { left: '0%', width: '0%' };
    }
    const a = Math.max(t0, v0);
    const b = Math.min(t1, v1);
    if (b <= a) {
        return { left: '0%', width: '0%' };
    }
    return {
        left: `${((a - v0) / span) * 100}%`,
        width: `${Math.max(((b - a) / span) * 100, 0.4)}%`,
    };
}

/** Minutes from 6:00 AM start (0) along the x-axis of the current view. */
function minuteToViewPercent(minute) {
    const v1 = Math.min(viewSpanMinutes.value, DAY_MINUTES_TOTAL);
    if (v1 <= 0) {
        return 0;
    }
    return (Math.min(minute, v1) / v1) * 100;
}

function travelMins(delivery) {
    return Math.max(0, Math.round((delivery.estimated_travel_duration_seconds || 0) / 60));
}

const FLEET_NONE_ASSIGNED = 'None assigned';

/** Schedule-board payload uses snake_case from API. */
function fleetScheduleUnitLabel(delivery, role) {
    const d = delivery ?? {};
    const label = role === 'truck' ? d.fleet_truck_label : d.fleet_trailer_label;
    if (label != null && String(label).trim() !== '') {
        return String(label).trim();
    }
    const rawId = role === 'truck' ? d.fleet_truck_id : d.fleet_trailer_id;
    const id = rawId != null && rawId !== '' ? Number(rawId) : NaN;
    if (Number.isFinite(id) && id > 0) {
        return `Unit #${id}`;
    }
    return FLEET_NONE_ASSIGNED;
}

function formatHour(h) {
    if (h === 12) {
        return '12 PM';
    }
    return h < 12 ? `${h} AM` : `${h - 12} PM`;
}

function formatTime(str) {
    const d = parseDate(str);
    if (isNaN(d.getTime())) {
        return '—';
    }
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function initials(name) {
    return name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function deliveryBlockStyle(delivery) {
    const s = blockStartMinutes(delivery);
    const durMin = delivery.delivery_duration_minutes || 15;
    const t0 = Math.max(0, s);
    const t1 = Math.min(s + durMin, DAY_MINUTES_TOTAL);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function travelToStyle(delivery) {
    const A = blockStartMinutes(delivery);
    const T = travelMins(delivery);
    if (T <= 0) {
        return { left: '0%', width: '0%' };
    }
    const D = A - T;
    if (D >= DAY_MINUTES_TOTAL) {
        return { left: '0%', width: '0%' };
    }
    const t0 = Math.max(0, D);
    const t1 = Math.min(A, DAY_MINUTES_TOTAL);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function travelBackStyle(delivery) {
    const startMin = blockStartMinutes(delivery);
    const durMin = delivery.delivery_duration_minutes || 15;
    const E = startMin + durMin;
    const T = travelMins(delivery);
    if (T <= 0) {
        return { left: '0%', width: '0%' };
    }
    if (E >= DAY_MINUTES_TOTAL) {
        return { left: '0%', width: '0%' };
    }
    const t0 = E;
    const t1 = Math.min(E + T, DAY_MINUTES_TOTAL);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function zoomIn() {
    if (viewHourCount.value > 1) {
        viewHourCount.value -= 1;
    }
}

function zoomOut() {
    if (viewHourCount.value < maxViewHours) {
        viewHourCount.value += 1;
    }
}

function changeDay(delta) {
    viewingDateYmd.value = dayjs.tz(viewingDateYmd.value, accountTimezone.value).add(delta, 'day').format('YYYY-MM-DD');
}

function onDragStart(delivery, techId) {
    dragPayload.value = delivery;
    dragSourceTech.value = techId;
}

function onDragEnd() {
    dragPayload.value = null;
    dragSourceTech.value = null;
    dragOverTech.value = null;
}

function onDragOver(techId) {
    dragOverTech.value = techId;
}

function onDragLeave() {
    dragOverTech.value = null;
}

async function onDrop(targetTechId) {
    const delivery = dragPayload.value;
    const sourceTech = dragSourceTech.value;
    if (!delivery || !sourceTech || sourceTech === targetTechId) {
        onDragEnd();
        return;
    }
    if (!delivery.id) {
        onDragEnd();
        return;
    }
    try {
        await axios.put(route('deliveries.update', delivery.id), {
            technician_id: Number(targetTechId),
        });
        await loadScheduleBoard();
    } catch (e) {
        scheduleError.value = e.response?.data?.message || 'Could not reassign technician.';
    }
    onDragEnd();
}
</script>

<style scoped>
.travel-line {
    position: absolute;
    top: 50%;
    z-index: 1;
    height: 6px;
    min-width: 4px;
    border-radius: 3px;
    transform: translateY(-50%);
    pointer-events: none;
    box-sizing: border-box;
}

/* Slightly above / below block center so lines read as separate from the blue “at location” block */
.travel-line--to {
    top: 34%;
    background: linear-gradient(90deg, #b45309, #d97706, #f59e0b);
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15), 0 1px 2px rgba(0, 0, 0, 0.12);
}

.travel-line--back {
    top: 66%;
    background: linear-gradient(90deg, #f59e0b, #fbbf24, #fde68a);
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
    opacity: 0.95;
}
</style>
