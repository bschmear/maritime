<template>
    <div
        class="min-w-[700px] overflow-hidden rounded-xl border border-gray-200 bg-gray-50 font-sans text-gray-900 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-100"
    >
        <!-- Header: light = gray panel + dark text; dark = navy + light text -->
        <div
            class="flex items-center justify-between border-b border-gray-200 bg-gray-100 px-5 py-4 text-gray-800 dark:border-white/5 dark:bg-[#1a1a2e] dark:text-white"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-lg text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:shadow-none dark:hover:bg-white/20"
                    @click="changeDay(-1)"
                >
                    ←
                </button>
                <span class="text-lg font-medium tracking-wide text-gray-900 dark:text-white md:text-lg">
                    {{ formattedDate }}
                </span>
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-lg text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:shadow-none dark:hover:bg-white/20"
                    @click="changeDay(1)"
                >
                    →
                </button>
            </div>
            <div
                class="flex flex-wrap items-center justify-end gap-x-5 gap-y-2 text-md text-gray-600 dark:text-white/70"
            >
                <p v-if="scheduleError" class="text-sm text-red-600 dark:text-red-400 max-w-md text-right">
                    {{ scheduleError }}
                </p>
                <p v-else-if="scheduleLoading" class="text-sm text-gray-500 dark:text-white/50">Loading…</p>
                <div
                    class="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-white px-2 py-1.5 shadow-sm dark:border-white/15 dark:bg-white/5 dark:shadow-none"
                    :title="timelineHint"
                >
                    <div class="flex items-center gap-1 text-sm text-gray-700 dark:text-white/80">
                        <span class="hidden text-gray-500 sm:inline dark:text-white/50">Start</span>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-base leading-none text-gray-800 transition enabled:hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                            :disabled="dayStartHour <= 0"
                            aria-label="Move start one hour earlier"
                            @click="nudgeStartHour(-1)"
                        >
                            −
                        </button>
                        <span
                            class="min-w-[5.25rem] text-center text-sm font-medium tabular-nums text-gray-900 dark:text-white"
                        >
                            {{ formatHour(dayStartHour) }}
                        </span>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-base leading-none text-gray-800 transition enabled:hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                            :disabled="dayStartHour >= dayEndHour - 1"
                            aria-label="Move start one hour later"
                            @click="nudgeStartHour(1)"
                        >
                            +
                        </button>
                    </div>
                    <span class="text-gray-400 dark:text-white/40" aria-hidden="true">→</span>
                    <div class="flex items-center gap-1 text-sm text-gray-700 dark:text-white/80">
                        <span class="hidden text-gray-500 sm:inline dark:text-white/50">End</span>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-base leading-none text-gray-800 transition enabled:hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                            :disabled="dayEndHour <= dayStartHour + 1"
                            aria-label="Move end one hour earlier"
                            @click="nudgeEndHour(-1)"
                        >
                            −
                        </button>
                        <span
                            class="min-w-[5.25rem] text-center text-sm font-medium tabular-nums text-gray-900 dark:text-white"
                        >
                            {{ timelineEndLabel }}
                        </span>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-base leading-none text-gray-800 transition enabled:hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-white/90 dark:enabled:hover:bg-white/15"
                            :disabled="dayEndHour >= 24"
                            aria-label="Move end one hour later"
                            @click="nudgeEndHour(1)"
                        >
                            +
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <span class="flex items-center gap-2 text-gray-700 dark:text-white/80">
                        <span class="h-3 w-3 rounded-sm bg-blue-500" />
                        At location
                    </span>
                    <span class="flex items-center gap-2 text-gray-700 dark:text-white/80">
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
                class="flex w-full min-w-[1200px] items-stretch border-b border-gray-200 dark:border-gray-600"
            >
                <div
                    class="sticky left-0 z-20 h-11 w-48 min-w-48 shrink-0 self-stretch border-r border-gray-200 bg-gray-100 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.12)] dark:border-gray-600 dark:bg-gray-800/80"
                />
                <div class="relative h-11 min-w-0 flex-1 bg-gray-100 dark:bg-gray-800/80">
                    <div
                        v-if="grayOverlayLeftPct > 0"
                        class="pointer-events-none absolute inset-y-0 left-0 z-0 bg-gray-300/55 dark:bg-gray-950/40"
                        :style="{ width: grayOverlayLeftPct + '%' }"
                    />
                    <div
                        v-if="grayOverlayRightPct > 0"
                        class="pointer-events-none absolute inset-y-0 right-0 z-0 bg-gray-300/55 dark:bg-gray-950/40"
                        :style="{ width: grayOverlayRightPct + '%' }"
                    />
                    <!-- Hour ticks for alignment with rows below -->
                    <div
                        v-for="m in gridLineMinutes"
                        :key="'haxis-line-' + m"
                        class="pointer-events-none absolute top-0 bottom-0 z-[1] w-px bg-gray-300 dark:bg-gray-600"
                        :style="{ left: minuteToViewPercent(m) + '%' }"
                    />
                    <!-- One label per 1h cell, centered in that cell (not on the boundary lines) -->
                    <div
                        v-for="slot in hourLabelSlots"
                        :key="'hlabel-' + slot.h"
                        class="absolute bottom-0 top-0 z-[2] flex items-end justify-center pb-2"
                        :style="{ left: slot.leftPct + '%', transform: 'translateX(-50%)' }"
                    >
                        <span
                            class="relative z-10 whitespace-nowrap text-sm font-medium text-gray-600 dark:text-gray-400 md:text-md"
                        >
                            {{ slot.text }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Technician rows -->
            <template v-for="(tech, techRowIndex) in technicians" :key="tech.id">
                <div
                    v-if="techRowIndex > 0"
                    class="flex w-full min-w-[1200px] shrink-0 items-stretch border-t-2 border-gray-300 bg-gray-100 dark:border-gray-500 dark:bg-gray-800/90"
                    role="separator"
                    aria-hidden="true"
                >
                    <div
                        class="sticky left-0 z-20 w-48 min-w-48 shrink-0 border-r border-gray-300 bg-gray-100 dark:border-gray-500 dark:bg-gray-800/90"
                    />
                    <div class="min-h-[2px] min-w-0 flex-1 bg-gray-100 dark:bg-gray-800/90" />
                </div>
                <div
                    class="flex w-full min-w-[1200px] h-20 min-h-[80px] items-stretch border-b border-gray-200 transition-colors dark:border-gray-600"
                    :class="dragOverTech === tech.id ? 'bg-primary-50 dark:bg-primary-950/30' : ''"
                    @dragover.prevent="onDragOver(tech.id)"
                    @dragleave="onDragLeave"
                    @drop="onDrop(tech.id)"
                >
                    <div
                        class="sticky left-0 z-20 flex w-48 min-w-48 shrink-0 flex-col justify-center gap-0.5 self-stretch border-r border-gray-200 bg-white px-3 py-2 shadow-[4px_0_12px_-4px_rgba(0,0,0,0.1)] dark:border-gray-600"
                        :class="dragOverTech === tech.id ? 'bg-primary-50 dark:bg-primary-950/30' : 'dark:bg-gray-800'"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                {{ initials(tech.name) }}
                            </div>
                            <span class="truncate text-md font-semibold leading-tight text-gray-800 dark:text-gray-100">
                                {{ tech.name }}
                            </span>
                        </div>
                        <p class="pl-11 text-sm text-gray-500 dark:text-gray-400">
                            Total deliveries: <span class="font-medium text-gray-700 dark:text-gray-300">{{ deliveriesForTech(tech.id).length }}</span>
                        </p>
                    </div>

                    <div
                        class="relative h-20 min-h-[80px] min-w-0 flex-1 overflow-visible"
                        :class="
                            dragOverTech === tech.id ? 'bg-primary-50 dark:bg-primary-950/50' : 'bg-white dark:bg-gray-900/35'
                        "
                    >
                        <div
                            v-if="grayOverlayLeftPct > 0"
                            class="pointer-events-none absolute inset-y-0 left-0 z-0 bg-gray-200/70 dark:bg-gray-950/45"
                            :style="{ width: grayOverlayLeftPct + '%' }"
                        />
                        <div
                            v-if="grayOverlayRightPct > 0"
                            class="pointer-events-none absolute inset-y-0 right-0 z-0 bg-gray-200/70 dark:bg-gray-950/45"
                            :style="{ width: grayOverlayRightPct + '%' }"
                        />
                        <div
                            v-for="m in gridLineMinutes"
                            :key="'line-' + m + '-' + tech.id"
                            class="pointer-events-none absolute top-0 bottom-0 z-[1] w-px bg-gray-200 dark:bg-gray-500"
                            :style="{ left: minuteToViewPercent(m) + '%' }"
                        />

                        <template v-for="delivery in deliveriesForTech(tech.id)" :key="deliveryKey(tech.id, delivery)">
                            <!-- Outbound travel (clipped when leg starts before the timeline window) -->
                            <div
                                class="travel-line travel-line--to z-[2]"
                                :style="travelToStyle(delivery)"
                                :title="`Travel to ${delivery.end_location}: ${travelOutboundMinsDisplayed(delivery)} min`"
                            />
                            <div
                                v-if="travelOutboundMinsDisplayed(delivery) > 0"
                                class="travel-line-label travel-line-label--below-to pointer-events-none"
                                :style="travelToLabelStyle(delivery)"
                            >
                                <span class="material-icons travel-line-label__icon" aria-hidden="true">directions_car</span>
                                <span class="travel-line-label__time">{{ travelOutboundMinsDisplayed(delivery) }} min</span>
                            </div>
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
                            <!-- Return travel (clipped when leg runs past the timeline window) -->
                            <div
                                v-if="travelReturnMins(delivery) > 0"
                                class="travel-line-label travel-line-label--above-back pointer-events-none"
                                :style="travelBackLabelStyle(delivery)"
                            >
                                <span class="material-icons travel-line-label__icon" aria-hidden="true">directions_car</span>
                                <span class="travel-line-label__time">{{ travelReturnMins(delivery) }} min</span>
                            </div>
                            <div
                                class="travel-line travel-line--back z-[2]"
                                :style="travelBackStyle(delivery)"
                                :title="`Return from ${delivery.end_location}: ${travelReturnMins(delivery)} min`"
                            />
                        </template>
                    </div>
                </div>
            </template>
            <div
                v-if="!technicians.length && !scheduleLoading"
                class="flex min-h-[120px] w-full items-center justify-center border-b border-gray-200 bg-white px-6 py-10 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-900/30 dark:text-gray-300"
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
                    <span class="text-md text-gray-600 dark:text-gray-300">
                        {{ selectedDelivery.customer_name }}
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-x-5 sm:gap-y-2.5">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">From</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            {{ selectedDelivery.start_location }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">To</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            {{ selectedDelivery.end_location }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Leave by</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            {{ formatTime(selectedDelivery.time_to_leave_by) }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:col-span-3 border-t border-gray-200 pt-3 dark:border-gray-600">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Scheduled arrival</span>
                        <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">
                            Wall time in {{ accountTimezone }}. Saving updates this delivery and refreshes the board.
                        </p>
                        <div class="flex max-w-xl flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                            <input
                                v-model="detailScheduledLocal"
                                type="datetime-local"
                                class="input-style min-w-0 flex-1 bg-white dark:bg-gray-900"
                                :disabled="detailTimeSaving"
                            />
                            <button
                                type="button"
                                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="detailTimeSaving || !detailTimeDirty"
                                @click="saveDetailScheduledAt"
                            >
                                {{ detailTimeSaving ? 'Saving…' : 'Save time' }}
                            </button>
                        </div>
                        <p v-if="detailTimeError" class="text-sm text-red-600 dark:text-red-400">{{ detailTimeError }}</p>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Travel</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            To: {{ travelOutboundMinsDisplayed(selectedDelivery) }} min · Back:
                            {{ travelReturnMins(selectedDelivery) }} min
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">At location</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            {{ selectedDelivery.delivery_duration_minutes || 15 }} min
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Truck</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
                            {{ fleetScheduleUnitLabel(selectedDelivery, 'truck') }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold uppercase tracking-wider text-gray-400">Trailer</span>
                        <span class="text-md font-medium text-gray-900 dark:text-white">
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

/** UTC / ISO from server → `datetime-local` string in account TZ (same as DeliveryForm). */
function serverUtcToAccountDatetimeLocal(value) {
    if (!value) {
        return '';
    }
    const m = dayjs(value);
    if (!m.isValid()) {
        return '';
    }
    return m.tz(accountTimezone.value).format('YYYY-MM-DDTHH:mm');
}

/** `datetime-local` wall clock in account TZ → UTC ISO for the API. */
function accountDatetimeLocalToUtcIso(localStr) {
    if (!localStr || !String(localStr).trim()) {
        return null;
    }
    const m = dayjs.tz(String(localStr).trim(), 'YYYY-MM-DDTHH:mm', accountTimezone.value);
    if (!m.isValid()) {
        return null;
    }
    return m.utc().toISOString();
}

/** First hour on the axis (0 = midnight). `block_start_minutes` from the API is minutes from midnight on the board date. */
const dayStartHour = ref(6);
/** Exclusive end hour (20 = axis ends at 8:00 PM tick; 24 = full calendar day). */
const dayEndHour = ref(20);

function formatHour(h) {
    if (h === 0) {
        return '12 AM';
    }
    if (h === 12) {
        return '12 PM';
    }
    if (h === 24) {
        return 'Midnight';
    }
    return h < 12 ? `${h} AM` : `${h - 12} PM`;
}

/** Calendar day (Y-m-d) in account timezone — matches schedule-board `date` filter. */
const viewingDateYmd = ref(dayjs().tz(accountTimezone.value).format('YYYY-MM-DD'));
const selectedDelivery = ref(null);
const detailScheduledLocal = ref('');
const detailTimeSaving = ref(false);
const detailTimeError = ref(null);
const dragPayload = ref(null);
const dragSourceTech = ref(null);
const dragOverTech = ref(null);
const scheduleLoading = ref(false);
const scheduleError = ref(null);

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

function minutesFromMidnight(dateStr) {
    const d = parseDate(dateStr);
    if (isNaN(d.getTime())) {
        return 0;
    }
    return d.getHours() * 60 + d.getMinutes();
}

function travelMins(delivery) {
    return Math.max(0, Math.round((delivery.estimated_travel_duration_seconds || 0) / 60));
}

function travelReturnMins(delivery) {
    const r = delivery.estimated_return_travel_duration_seconds;
    if (r != null && Number(r) > 0) {
        return Math.max(0, Math.round(Number(r) / 60));
    }

    return travelMins(delivery);
}

/** Minutes from midnight on the board date (same basis as API `block_start_minutes`). */
function blockStartMinutesMidnight(delivery) {
    const raw = delivery?.block_start_minutes;
    if (raw != null && Number.isFinite(Number(raw))) {
        return Number(raw);
    }
    return minutesFromMidnight(delivery.scheduled_at);
}

/** Absolute-minute departure (outbound start), before subtracting view origin. */
function outboundDepartureAbsoluteMinutes(delivery) {
    const A = blockStartMinutesMidnight(delivery);
    const T = travelMins(delivery);
    let D = A - T;
    const rawLeave = delivery?.leave_by_minutes;
    if (rawLeave != null && Number.isFinite(Number(rawLeave))) {
        const leaveAbs = Number(rawLeave);
        if (Number.isFinite(leaveAbs) && leaveAbs < D) {
            D = leaveAbs;
        }
    }
    return D;
}

const VIEW_RANGE_PAD_MIN = 30;

/** Expands the horizontal axis to include every delivery (travel + at-location + return); Start/End stays the preferred “office hours” window for gray shading only. */
const scheduleViewBounds = computed(() => {
    const windowStartMin = dayStartHour.value * 60;
    const windowEndMin = dayEndHour.value * 60;

    const flat = Object.values(deliveries.value).flat();
    let minAbs = Infinity;
    let maxAbs = -Infinity;
    for (const d of flat) {
        const A = blockStartMinutesMidnight(d);
        if (!Number.isFinite(A)) {
            continue;
        }
        const D = outboundDepartureAbsoluteMinutes(d);
        if (!Number.isFinite(D)) {
            continue;
        }
        const dur = d.delivery_duration_minutes || 15;
        const R = travelReturnMins(d);
        const endAbs = A + dur + R;
        if (!Number.isFinite(endAbs)) {
            continue;
        }
        minAbs = Math.min(minAbs, D);
        maxAbs = Math.max(maxAbs, endAbs);
    }

    let viewStartMin = windowStartMin;
    let viewEndMin = windowEndMin;

    if (minAbs !== Infinity && maxAbs !== -Infinity) {
        viewStartMin = Math.min(windowStartMin, minAbs - VIEW_RANGE_PAD_MIN);
        viewEndMin = Math.max(windowEndMin, maxAbs + VIEW_RANGE_PAD_MIN);
    }

    viewStartMin = Math.max(0, Math.floor(viewStartMin / 60) * 60);
    viewEndMin = Math.min(24 * 60, Math.max(viewStartMin + 60, Math.ceil(viewEndMin / 60) * 60));

    const spanMin = Math.max(1, viewEndMin - viewStartMin);

    return {
        viewStartMin,
        viewEndMin,
        windowStartMin,
        windowEndMin,
        spanMin,
    };
});

const timelineSpanMinutes = computed(() => scheduleViewBounds.value.spanMin);

const grayOverlayLeftPct = computed(() => {
    const { viewStartMin, windowStartMin, spanMin } = scheduleViewBounds.value;
    if (spanMin <= 0) {
        return 0;
    }
    const w = Math.max(0, windowStartMin - viewStartMin);
    return (w / spanMin) * 100;
});

const grayOverlayRightPct = computed(() => {
    const { viewEndMin, windowEndMin, spanMin } = scheduleViewBounds.value;
    if (spanMin <= 0) {
        return 0;
    }
    const w = Math.max(0, viewEndMin - windowEndMin);
    return (w / spanMin) * 100;
});

const gridLineMinutes = computed(() => {
    const v0 = scheduleViewBounds.value.viewStartMin;
    const v1 = scheduleViewBounds.value.viewEndMin;
    const span = v1 - v0;
    const out = [];
    let tick = Math.ceil(v0 / 60) * 60;
    for (; tick < v1; tick += 60) {
        out.push(tick - v0);
    }
    if (span > 0) {
        if (out.length === 0 || out[0] > 0) {
            out.unshift(0);
        }
        if (out[out.length - 1] < span) {
            out.push(span);
        }
    }
    return out;
});

const hourLabelSlots = computed(() => {
    const v0 = scheduleViewBounds.value.viewStartMin;
    const v1 = scheduleViewBounds.value.viewEndMin;
    const span = v1 - v0;
    if (span <= 0) {
        return [];
    }
    const out = [];
    const hStart = Math.ceil(v0 / 60);
    const hEndExclusive = Math.min(24, Math.ceil(v1 / 60));
    for (let h = hStart; h < hEndExclusive; h += 1) {
        const blockStart = h * 60 - v0;
        const blockEnd = Math.min((h + 1) * 60 - v0, span);
        if (blockStart >= span) {
            break;
        }
        const visibleEnd = Math.min(blockEnd, span);
        const center = (blockStart + visibleEnd) / 2;
        out.push({ h, leftPct: (center / span) * 100, text: formatHour(h) });
    }
    return out;
});

const timelineEndLabel = computed(() =>
    dayEndHour.value === 24 ? 'Midnight (end)' : formatHour(dayEndHour.value),
);

const timelineHint = computed(() => {
    const span = dayEndHour.value - dayStartHour.value;
    return `Preferred window: ${span}h (Start–End). The axis expands when deliveries fall outside; shaded bands are outside that window.`;
});

function nudgeStartHour(delta) {
    const next = dayStartHour.value + delta;
    if (next < 0 || next > dayEndHour.value - 1) {
        return;
    }
    dayStartHour.value = next;
}

function nudgeEndHour(delta) {
    const next = dayEndHour.value + delta;
    if (next < dayStartHour.value + 1 || next > 24) {
        return;
    }
    dayEndHour.value = next;
}

watch(dayStartHour, (v) => {
    if (dayEndHour.value <= v) {
        dayEndHour.value = Math.min(24, v + 1);
    }
});

watch(dayEndHour, (v) => {
    if (v <= dayStartHour.value) {
        dayStartHour.value = Math.max(0, v - 1);
    }
});

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

watch(selectedDelivery, (d) => {
    detailTimeError.value = null;
    if (!d?.id) {
        detailScheduledLocal.value = '';
        return;
    }
    detailScheduledLocal.value = serverUtcToAccountDatetimeLocal(d.scheduled_at);
});

const detailTimeDirty = computed(() => {
    if (!selectedDelivery.value?.id) {
        return false;
    }
    const cur = serverUtcToAccountDatetimeLocal(selectedDelivery.value.scheduled_at);
    return (detailScheduledLocal.value || '') !== (cur || '');
});

function findDeliveryOnBoard(id) {
    for (const list of Object.values(deliveries.value)) {
        if (!Array.isArray(list)) {
            continue;
        }
        const found = list.find((x) => x.id === id);
        if (found) {
            return found;
        }
    }
    return null;
}

async function saveDetailScheduledAt() {
    const d = selectedDelivery.value;
    if (!d?.id || detailTimeSaving.value) {
        return;
    }
    detailTimeError.value = null;
    const iso = accountDatetimeLocalToUtcIso(detailScheduledLocal.value);
    if (!iso) {
        detailTimeError.value = 'Enter a valid date and time.';
        return;
    }
    detailTimeSaving.value = true;
    try {
        await axios.put(route('deliveries.update', d.id), { scheduled_at: iso });
        await loadScheduleBoard();
        const fresh = findDeliveryOnBoard(d.id);
        if (fresh) {
            selectedDelivery.value = fresh;
        } else {
            selectedDelivery.value = null;
        }
    } catch (e) {
        const errs = e.response?.data?.errors;
        const flat = errs && typeof errs === 'object' ? Object.values(errs).flat() : [];
        detailTimeError.value =
            e.response?.data?.message || (flat.length ? flat.join(' ') : null) || 'Could not save scheduled time.';
    } finally {
        detailTimeSaving.value = false;
    }
}

const formattedDate = computed(() =>
    dayjs.tz(viewingDateYmd.value, accountTimezone.value).format('dddd, MMMM D, YYYY'),
);

function deliveriesForTech(techId) {
    return deliveries.value[String(techId)] || [];
}

function deliveryKey(techId, delivery) {
    return `${techId}::${delivery.id ?? delivery.display_name}`;
}

/** Minutes from expanded view origin (API uses minutes from midnight on the board date). */
function blockStartMinutes(delivery) {
    return blockStartMinutesMidnight(delivery) - scheduleViewBounds.value.viewStartMin;
}

/** Map [t0, t1] in timeline-relative minutes into % of the full timeline width. */
function segmentInView(t0, t1) {
    const v0 = 0;
    const v1 = timelineSpanMinutes.value;
    const span = v1 - v0;
    if (span <= 0 || t1 <= t0 || !Number.isFinite(t0) || !Number.isFinite(t1)) {
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

/** Minutes from `dayStartHour` (0) along the x-axis. */
function minuteToViewPercent(minute) {
    const v1 = timelineSpanMinutes.value;
    if (v1 <= 0) {
        return 0;
    }
    return (Math.min(minute, v1) / v1) * 100;
}

/** Left edge of outbound travel on the timeline (view minutes). */
function outboundDepartureViewMinutes(delivery) {
    return outboundDepartureAbsoluteMinutes(delivery) - scheduleViewBounds.value.viewStartMin;
}

/** Outbound bar duration in minutes (arrival at scheduled block minus departure). */
function travelOutboundMinsDisplayed(delivery) {
    const A = blockStartMinutes(delivery);
    const D = outboundDepartureViewMinutes(delivery);
    return Math.max(0, Math.round(A - D));
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
    const cap = timelineSpanMinutes.value;
    const t0 = Math.max(0, s);
    const t1 = Math.min(s + durMin, cap);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function travelToStyle(delivery) {
    const A = blockStartMinutes(delivery);
    const D = outboundDepartureViewMinutes(delivery);
    if (!(A > D)) {
        return { left: '0%', width: '0%' };
    }
    const cap = timelineSpanMinutes.value;
    if (D >= cap) {
        return { left: '0%', width: '0%' };
    }
    const t0 = Math.max(0, D);
    const t1 = Math.min(A, cap);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function travelBackStyle(delivery) {
    const startMin = blockStartMinutes(delivery);
    const durMin = delivery.delivery_duration_minutes || 15;
    const E = startMin + durMin;
    const T = travelReturnMins(delivery);
    if (T <= 0) {
        return { left: '0%', width: '0%' };
    }
    const cap = timelineSpanMinutes.value;
    if (E >= cap) {
        return { left: '0%', width: '0%' };
    }
    const t0 = E;
    const t1 = Math.min(E + T, cap);
    if (t0 >= t1) {
        return { left: '0%', width: '0%' };
    }
    return segmentInView(t0, t1);
}

function travelToLabelStyle(delivery) {
    const s = travelToStyle(delivery);
    if (s.width === '0%' || Number.parseFloat(String(s.width)) === 0) {
        return { display: 'none' };
    }
    return {
        position: 'absolute',
        left: s.left,
        width: s.width,
        top: 'calc(34% + 5px)',
        zIndex: 3,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        lineHeight: 1,
    };
}

function travelBackLabelStyle(delivery) {
    const s = travelBackStyle(delivery);
    if (s.width === '0%' || Number.parseFloat(String(s.width)) === 0) {
        return { display: 'none' };
    }
    return {
        position: 'absolute',
        left: s.left,
        width: s.width,
        top: 'calc(66% - 28px)',
        zIndex: 3,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        lineHeight: 1,
    };
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

.travel-line-label {
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: #78350f;
}

.dark .travel-line-label {
    color: #fde68a;
}

.travel-line-label__icon {
    font-size: 13px !important;
    line-height: 1 !important;
    color: #92400e;
}

.dark .travel-line-label__icon {
    color: #fcd34d;
}

.travel-line-label__time {
    margin-top: 1px;
    white-space: nowrap;
}

/* Time above, car just above the return bar (same DOM order as outbound: icon then text) */
.travel-line-label--above-back {
    flex-direction: column-reverse;
}

.travel-line-label--above-back .travel-line-label__time {
    margin-top: 0;
    margin-bottom: 1px;
}
</style>
