<script setup>
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

dayjs.extend(utc);
dayjs.extend(timezone);

const props = defineProps({
    /** Calendar day in `YYYY-MM-DD` (account/local timezone). */
    modelValue: { type: String, required: true },
    timezone: { type: String, default: 'UTC' },
    /** Optional label for the trigger button. */
    ariaLabel: { type: String, default: 'Pick a date' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);
const viewYear = ref(0);
const viewMonth = ref(0);

function syncViewFromModel() {
    const m = dayjs.tz(props.modelValue, 'YYYY-MM-DD', props.timezone);
    if (!m.isValid()) {
        const now = dayjs().tz(props.timezone);
        viewYear.value = now.year();
        viewMonth.value = now.month();
        return;
    }
    viewYear.value = m.year();
    viewMonth.value = m.month();
}

watch(() => props.modelValue, syncViewFromModel, { immediate: true });
watch(() => props.timezone, syncViewFromModel);

const monthTitle = computed(() =>
    dayjs.tz(`${viewYear.value}-${String(viewMonth.value + 1).padStart(2, '0')}-01`, props.timezone).format('MMMM YYYY'),
);

const selectedYmd = computed(() => props.modelValue);

const todayYmd = computed(() => dayjs().tz(props.timezone).format('YYYY-MM-DD'));

const weeks = computed(() => {
    const first = dayjs.tz(`${viewYear.value}-${String(viewMonth.value + 1).padStart(2, '0')}-01`, props.timezone);
    const startPad = first.day();
    const daysInMonth = first.daysInMonth();
    const cells = [];
    for (let i = 0; i < startPad; i++) {
        cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const ymd = first.date(d).format('YYYY-MM-DD');
        cells.push({
            day: d,
            ymd,
            isToday: ymd === todayYmd.value,
            isSelected: ymd === selectedYmd.value,
        });
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }
    const rows = [];
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7));
    }
    return rows;
});

function toggleOpen() {
    open.value = !open.value;
    if (open.value) {
        syncViewFromModel();
    }
}

function shiftMonth(delta) {
    let m = viewMonth.value + delta;
    let y = viewYear.value;
    if (m > 11) {
        m = 0;
        y += 1;
    } else if (m < 0) {
        m = 11;
        y -= 1;
    }
    viewMonth.value = m;
    viewYear.value = y;
}

function pickDay(ymd) {
    emit('update:modelValue', ymd);
    open.value = false;
}

function pickToday() {
    pickDay(todayYmd.value);
}

function onDocumentClick(event) {
    if (!open.value) return;
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick, true));
onUnmounted(() => document.removeEventListener('click', onDocumentClick, true));
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-white/20 dark:bg-white/10 dark:text-white dark:shadow-none dark:hover:bg-white/20"
            :aria-label="ariaLabel"
            :aria-expanded="open"
            @click.stop="toggleOpen"
        >
            <span class="material-icons text-xl leading-none" aria-hidden="true">calendar_today</span>
        </button>

        <div
            v-if="open"
            class="absolute left-0 top-full z-50 mt-2 w-[17rem] rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-600 dark:bg-gray-800"
            role="dialog"
            aria-label="Choose date"
            @click.stop
        >
            <div class="mb-2 flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Previous month"
                    @click="shiftMonth(-1)"
                >
                    <span class="material-icons text-lg">chevron_left</span>
                </button>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ monthTitle }}</span>
                <button
                    type="button"
                    class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Next month"
                    @click="shiftMonth(1)"
                >
                    <span class="material-icons text-lg">chevron_right</span>
                </button>
            </div>

            <div class="mb-1 grid grid-cols-7">
                <div
                    v-for="label in ['S', 'M', 'T', 'W', 'T', 'F', 'S']"
                    :key="label"
                    class="py-1 text-center text-[10px] font-semibold uppercase text-gray-400 dark:text-gray-500"
                >
                    {{ label }}
                </div>
            </div>

            <div class="space-y-0.5">
                <div v-for="(row, ri) in weeks" :key="ri" class="grid grid-cols-7 gap-0.5">
                    <template v-for="(cell, ci) in row" :key="`${ri}-${ci}`">
                        <span v-if="!cell" class="h-8" />
                        <button
                            v-else
                            type="button"
                            :class="[
                                'h-8 w-full rounded-md text-sm font-medium transition-colors',
                                cell.isSelected
                                    ? 'bg-primary-600 text-white'
                                    : cell.isToday
                                      ? 'ring-1 ring-primary-500 text-primary-700 dark:text-primary-300'
                                      : 'text-gray-800 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700',
                            ]"
                            @click="pickDay(cell.ymd)"
                        >
                            {{ cell.day }}
                        </button>
                    </template>
                </div>
            </div>

            <button
                type="button"
                class="mt-2 w-full rounded-md border border-gray-200 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="pickToday"
            >
                Today
            </button>
        </div>
    </div>
</template>
