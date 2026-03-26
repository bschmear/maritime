<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Table from '@/Components/Tenant/Table.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    schema: { type: Object, default: null },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    recordType: { type: String, default: 'boat-shows' },
    recordTitle: { type: String, default: 'BoatShow' },
    pluralTitle: { type: String, default: 'Boat Shows' },
    extraRouteParams: { type: Object, default: () => ({}) },
    initialCreateData: { type: Object, default: () => ({}) },

    // Array of: { id, name, start_date: 'YYYY-MM-DD', end_date: 'YYYY-MM-DD', location, description, url }
    events: { type: Array, default: () => [] },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle },
]);

// ── Calendar state ──────────────────────────────────────────────
const today = new Date();
today.setHours(0, 0, 0, 0);

const viewYear  = ref(today.getFullYear());
const viewMonth = ref(today.getMonth());

const prevMonth = () => {
    if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value--; }
    else viewMonth.value--;
};
const nextMonth = () => {
    if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value++; }
    else viewMonth.value++;
};
const resetToday = () => {
    viewYear.value  = today.getFullYear();
    viewMonth.value = today.getMonth();
};

const monthLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, 1)
        .toLocaleString('default', { month: 'long', year: 'numeric' })
);

const calendarDays = computed(() => {
    const firstDay    = new Date(viewYear.value, viewMonth.value, 1).getDay();
    const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < firstDay; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    return cells;
});

// ── Event helpers ───────────────────────────────────────────────
const parseDate = (str) => {
    const [y, m, d] = str.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setHours(0, 0, 0, 0);
    return dt;
};

const cutoff90 = computed(() => {
    const d = new Date(today);
    d.setDate(d.getDate() + 90);
    return d;
});

const upcomingEvents = computed(() =>
    [...props.events]
        .filter(e => {
            const start = parseDate(e.start_date);
            return start >= today && start <= cutoff90.value;
        })
        .sort((a, b) => parseDate(a.start_date) - parseDate(b.start_date))
);

// day number → events[] for the viewed month
const eventsByDay = computed(() => {
    const map = {};
    upcomingEvents.value.forEach(e => {
        const start = parseDate(e.start_date);
        const end   = parseDate(e.end_date ?? e.start_date);
        const cur   = new Date(start);
        while (cur <= end) {
            if (cur.getFullYear() === viewYear.value && cur.getMonth() === viewMonth.value) {
                const d = cur.getDate();
                if (!map[d]) map[d] = [];
                if (!map[d].find(x => x.id === e.id)) map[d].push(e);
            }
            cur.setDate(cur.getDate() + 1);
        }
    });
    return map;
});

const eventDaysInView = computed(() => new Set(Object.keys(eventsByDay.value).map(Number)));

const eventsInViewMonth = computed(() =>
    upcomingEvents.value.filter(e => {
        const start = parseDate(e.start_date);
        return start.getFullYear() === viewYear.value && start.getMonth() === viewMonth.value;
    })
);

const isToday = (day) =>
    day === today.getDate() &&
    viewMonth.value === today.getMonth() &&
    viewYear.value  === today.getFullYear();

const formatRange = (startStr, endStr) => {
    const s    = parseDate(startStr);
    const e    = endStr ? parseDate(endStr) : null;
    const opts = { month: 'short', day: 'numeric' };
    if (!e || s.getTime() === e.getTime()) return s.toLocaleDateString('default', opts);
    if (s.getMonth() === e.getMonth()) return `${s.toLocaleDateString('default', opts)} – ${e.getDate()}`;
    return `${s.toLocaleDateString('default', opts)} – ${e.toLocaleDateString('default', opts)}`;
};

const formatRangeLong = (startStr, endStr) => {
    const s    = parseDate(startStr);
    const e    = endStr ? parseDate(endStr) : null;
    const full = { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' };
    if (!e || s.getTime() === e.getTime()) return s.toLocaleDateString('default', full);
    const short = { weekday: 'short', month: 'long', day: 'numeric' };
    if (s.getFullYear() === e.getFullYear()) {
        if (s.getMonth() === e.getMonth())
            return `${s.toLocaleDateString('default', short)} – ${e.toLocaleDateString('default', { weekday: 'short', day: 'numeric' })}, ${s.getFullYear()}`;
        return `${s.toLocaleDateString('default', short)} – ${e.toLocaleDateString('default', short)}, ${s.getFullYear()}`;
    }
    return `${s.toLocaleDateString('default', full)} – ${e.toLocaleDateString('default', full)}`;
};

const daysUntil = (dateStr) => {
    const diff = Math.round((parseDate(dateStr) - today) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Tomorrow';
    return `In ${diff} days`;
};

const durationDays = (startStr, endStr) => {
    if (!endStr) return 1;
    return Math.round((parseDate(endStr) - parseDate(startStr)) / 86400000) + 1;
};

// ── Modal state ─────────────────────────────────────────────────
const selectedEvent  = ref(null);
const modalDayEvents = ref([]);
const showDayPicker  = ref(false);

const openEvent = (event) => {
    modalDayEvents.value = [];
    showDayPicker.value  = false;
    selectedEvent.value  = event;
};

const openDay = (day) => {
    const events = eventsByDay.value[day];
    if (!events?.length) return;
    if (events.length === 1) {
        openEvent(events[0]);
    } else {
        selectedEvent.value  = null;
        modalDayEvents.value = events;
        showDayPicker.value  = true;
    }
};

const closeModal = () => {
    selectedEvent.value  = null;
    modalDayEvents.value = [];
    showDayPicker.value  = false;
};

const eventUrl = (event) =>
    event.url ?? route(`${props.recordType}.show`, {
        [props.recordType.replace(/-/g, '_').replace(/s$/, '')]: event.id,
        ...props.extraRouteParams,
    });
</script>

<template>
    <Head :title="pluralTitle" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <!-- ── Main layout ── -->
        <div class="flex gap-6 items-start">

            <!-- Table -->
            <div class="min-w-0 flex-1">
                <Table
                    :records="records"
                    :schema="schema"
                    :form-schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    :plural-title="pluralTitle"
                    :extra-route-params="extraRouteParams"
                    :initial-create-data="initialCreateData"
                />
            </div>

            <!-- ── Calendar Sidebar ── -->
            <aside class="w-96 shrink-0 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">

                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-700">
                    <h2 class="text-sm font-semibold text-white tracking-wide uppercase">Upcoming Shows</h2>
                    <span class="text-xs text-white font-medium">Next 90 days</span>
                </div>

                <!-- Mini Calendar -->
                <div class="px-4 pt-4 pb-3">
                    <div class="flex items-center justify-between mb-3">
                        <button @click="prevMonth"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors"
                                aria-label="Previous month">
                            <span class="material-icons text-base leading-none">chevron_left</span>
                        </button>
                        <button @click="resetToday"
                                class="text-xs font-semibold text-gray-700 hover:text-blue-600 transition-colors px-1">
                            {{ monthLabel }}
                        </button>
                        <button @click="nextMonth"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors"
                                aria-label="Next month">
                            <span class="material-icons text-base leading-none">chevron_right</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 mb-1">
                        <div v-for="dow in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="dow"
                             class="text-center text-[10px] font-semibold text-gray-400 uppercase py-0.5">
                            {{ dow }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-y-0.5">
                        <div v-for="(day, idx) in calendarDays" :key="idx"
                             class="flex items-center justify-center h-7 relative">
                            <template v-if="day">
                                <button
                                    :disabled="!eventDaysInView.has(day)"
                                    @click="openDay(day)"
                                    :class="[
                                        'text-xs w-6 h-6 flex items-center justify-center rounded-full font-medium transition-colors',
                                        isToday(day)
                                            ? 'bg-blue-600 text-white font-bold'
                                            : eventDaysInView.has(day)
                                                ? 'bg-blue-100 text-blue-700 font-semibold cursor-pointer hover:bg-blue-200'
                                                : 'text-gray-600 cursor-default',
                                    ]"
                                >{{ day }}</button>
                                <span v-if="eventDaysInView.has(day) && !isToday(day)"
                                      class="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-blue-400 pointer-events-none" />
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Event List -->
                <div class="border-t border-gray-100">
                    <div v-if="eventsInViewMonth.length" class="divide-y divide-gray-50">
                        <button
                            v-for="event in eventsInViewMonth"
                            :key="event.id"
                            @click="openEvent(event)"
                            class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors group"
                        >
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 w-0.5 self-stretch rounded-full bg-blue-500 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-800 truncate group-hover:text-blue-700 transition-colors leading-snug">
                                        {{ event.name }}
                                    </p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">
                                        {{ formatRange(event.start_date, event.end_date) }}
                                    </p>
                                    <p v-if="event.location" class="text-[11px] text-gray-400 truncate mt-0.5 flex items-center gap-0.5">
                                        <span class="material-icons text-[11px]">place</span>
                                        {{ event.location }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold text-blue-500 bg-blue-50 rounded px-1.5 py-0.5 whitespace-nowrap mt-0.5">
                                    {{ daysUntil(event.start_date) }}
                                </span>
                            </div>
                        </button>
                    </div>

                    <div v-else class="px-4 py-6 text-center">
                        <span class="material-icons text-3xl text-gray-200 block mb-1">event_busy</span>
                        <p class="text-xs text-gray-400">No upcoming shows this month</p>
                    </div>

                    <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50">
                        <p class="text-[11px] text-gray-400 text-center">
                            <span class="font-semibold text-gray-600">{{ upcomingEvents.length }}</span>
                            upcoming show{{ upcomingEvents.length !== 1 ? 's' : '' }} in the next 90 days
                        </p>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ── Event Modal ── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="selectedEvent || showDayPicker"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4">

                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal" />

                    <!-- ── Day picker (multiple events on one day) ── -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        appear
                    >
                        <div v-if="showDayPicker"
                             class="relative z-10 w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="material-icons text-blue-500 text-xl">event</span>
                                    <h3 class="font-semibold text-gray-800 text-sm">Multiple Shows This Day</h3>
                                </div>
                                <button @click="closeModal"
                                        class="text-gray-400 hover:text-gray-600 transition-colors -mr-1 p-0.5 rounded hover:bg-gray-100">
                                    <span class="material-icons text-xl">close</span>
                                </button>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <button
                                    v-for="event in modalDayEvents"
                                    :key="event.id"
                                    @click="openEvent(event)"
                                    class="w-full text-left px-5 py-3.5 hover:bg-blue-50 transition-colors group flex items-center gap-3"
                                >
                                    <span class="material-icons text-blue-300 text-xl group-hover:text-blue-500 transition-colors">sailing</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-800 group-hover:text-blue-700 transition-colors truncate">
                                            {{ event.name }}
                                        </p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ formatRange(event.start_date, event.end_date) }}
                                        </p>
                                    </div>
                                    <span class="material-icons text-gray-300 group-hover:text-blue-400 transition-colors text-base">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- ── Single event detail ── -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        appear
                    >
                        <div v-if="selectedEvent"
                             class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">

                            <!-- Colored header -->
                            <div class="bg-gradient-to-br from-blue-600 to-blue-500 px-5 py-5 relative">
                                <button @click="closeModal"
                                        class="absolute top-3.5 right-3.5 text-white/60 hover:text-white transition-colors p-0.5 rounded-full hover:bg-white/10">
                                    <span class="material-icons text-xl">close</span>
                                </button>
                                <div class="flex items-start gap-3.5">
                                    <div class="bg-white/20 rounded-xl p-2.5 mt-0.5 shrink-0">
                                        <span class="material-icons text-white text-2xl">sailing</span>
                                    </div>
                                    <div class="pr-8 min-w-0">
                                        <p class="text-white/60 text-[11px] font-semibold uppercase tracking-widest mb-1">Boat Show</p>
                                        <h2 class="text-white font-bold text-lg leading-snug">{{ selectedEvent.name }}</h2>
                                        <span class="inline-flex items-center gap-1 mt-2.5 text-[11px] font-semibold text-blue-100 bg-white/20 rounded-full px-2.5 py-1">
                                            <span class="material-icons text-[13px]">schedule</span>
                                            {{ daysUntil(selectedEvent.start_date) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Info rows -->
                            <div class="px-5 py-4 space-y-4">

                                <div class="flex items-start gap-3.5">
                                    <span class="material-icons text-blue-400 text-xl mt-0.5 shrink-0">calendar_month</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide">Date</p>
                                        <p class="text-sm text-gray-800 font-medium mt-0.5">
                                            {{ formatRangeLong(selectedEvent.start_date, selectedEvent.end_date) }}
                                        </p>
                                        <p v-if="durationDays(selectedEvent.start_date, selectedEvent.end_date) > 1"
                                           class="text-xs text-gray-400 mt-0.5 flex items-center gap-0.5">
                                            <span class="material-icons text-[12px]">info_outline</span>
                                            {{ durationDays(selectedEvent.start_date, selectedEvent.end_date) }}-day event
                                        </p>
                                    </div>
                                </div>

                                <div v-if="selectedEvent.location" class="flex items-start gap-3.5">
                                    <span class="material-icons text-blue-400 text-xl mt-0.5 shrink-0">place</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide">Location</p>
                                        <p class="text-sm text-gray-800 font-medium mt-0.5">{{ selectedEvent.location }}</p>
                                    </div>
                                </div>

                                <div v-if="selectedEvent.description" class="flex items-start gap-3.5">
                                    <span class="material-icons text-blue-400 text-xl mt-0.5 shrink-0">notes</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide">Notes</p>
                                        <p class="text-sm text-gray-600 mt-0.5 leading-relaxed">{{ selectedEvent.description }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer actions -->
                            <div class="px-5 pb-5 pt-1 flex items-center gap-2.5">
                                <a :href="eventUrl(selectedEvent)"
                                   class="flex-1 inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                                    <span class="material-icons text-[18px]">open_in_new</span>
                                    View Event
                                </a>
                                <button @click="closeModal"
                                        class="inline-flex items-center justify-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                                    <span class="material-icons text-[18px]">close</span>
                                    Close
                                </button>
                            </div>
                        </div>
                    </Transition>

                </div>
            </Transition>
        </Teleport>

    </TenantLayout>
</template>
