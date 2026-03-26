<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Checklist from '@/Components/Tenant/Checklist.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'boat-shows' },
    recordTitle: { type: String, default: 'Boat Show' },
    domainName: { type: String, default: 'BoatShow' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    upcomingEvents: { type: Array, default: () => [] },
    pastEvents: { type: Array, default: () => [] },
    checklist: { type: Object, default: () => ({ name: 'Show Checklist', items: [] }) },
    tasks: { type: Array, default: () => [] },
});

const showKey = computed(() => props.record.id ?? props.record.slug);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Boat Shows', href: route('boat-shows.index') },
    { label: props.record.display_name ?? 'Boat show' },
]);

// ── Tabs ────────────────────────────────────────────────────────
const tabs = [
    { key: 'events',   label: 'Events',         icon: 'event' },
    { key: 'layout',   label: 'Layout Designer', icon: 'dashboard_customize' },
    { key: 'checklist',label: 'Checklist',       icon: 'checklist' },
    { key: 'tasks',    label: 'Tasks',           icon: 'task_alt' },
];
const activeTab = ref('events');

// ── Formatting ──────────────────────────────────────────────────
const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const formatEventRange = (ev) => {
    const a = formatDate(ev.starts_at);
    const b = formatDate(ev.ends_at);
    if (a === '—' && b === '—') return '—';
    if (a === b || b === '—') return a;
    return `${a} – ${b}`;
};

const locationLine = (ev) =>
    [ev.venue, [ev.city, ev.state].filter(Boolean).join(', '), ev.country]
        .filter(Boolean).join(' · ') || '—';

const eventShowHref = (ev) =>
    route('boat-shows.events.show', { boatShow: showKey.value, event: ev.id });

// ── Checklist local state ────────────────────────────────────────
const checklistData = ref(props.checklist);
</script>

<template>
    <Head :title="record.display_name ?? 'Boat show'" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-col gap-3 mt-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ record.display_name }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('boat-shows.events.create', showKey)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">add</span>
                            Add event
                        </Link>
                        <Link
                            :href="route('boat-shows.edit', showKey)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit show
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full">
            <div class="grid grid-cols-12 gap-4 p-4">

                <!-- ── Main content column ── -->
                <div class="col-span-12 2xl:col-span-9 space-y-4">

                    <!-- Show info card -->
                    <div class="rounded-lg bg-white p-6 shadow-md dark:bg-gray-800 sm:p-8">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                            <div
                                v-if="imageUrls?.logo"
                                class="shrink-0 overflow-hidden rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img :src="imageUrls.logo" alt="" class="h-28 w-28 object-contain sm:h-36 sm:w-36" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ record.display_name }}
                                    </h1>
                                    <p v-if="record.description"
                                       class="mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">
                                        {{ record.description }}
                                    </p>
                                    <p v-else class="mt-2 text-sm text-gray-400 dark:text-gray-500 italic">
                                        No description
                                    </p>
                                </div>
                                <div v-if="record.website" class="flex flex-wrap gap-3 text-sm">
                                    <a :href="record.website" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                        <span class="material-icons text-[18px]">language</span>
                                        Website
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Tabbed section ── -->
                    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800 overflow-hidden">

                        <!-- Tab bar -->
                        <div class="border-b border-gray-100 dark:border-gray-700 px-2 flex items-center gap-1 overflow-x-auto">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="[
                                    'flex items-center gap-1.5 px-4 py-3.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                                    activeTab === tab.key
                                        ? 'border-primary-600 text-primary-600 dark:text-primary-400 dark:border-primary-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
                                ]"
                            >
                                <span class="material-icons text-[17px]">{{ tab.icon }}</span>
                                {{ tab.label }}
                                <!-- Event count badges -->
                                <span
                                    v-if="tab.key === 'events'"
                                    class="ml-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {{ upcomingEvents.length + pastEvents.length }}
                                </span>
                                <span
                                    v-if="tab.key === 'tasks'"
                                    class="ml-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {{ tasks.length }}
                                </span>
                            </button>
                        </div>

                        <!-- ── EVENTS TAB ── -->
                        <div v-show="activeTab === 'events'">

                            <!-- Upcoming -->
                            <div>
                                <div class="px-6 py-4 flex items-center justify-between border-b border-gray-50 dark:border-gray-700/50">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                                        <span class="material-icons text-[16px] text-green-500">upcoming</span>
                                        Upcoming &amp; current
                                    </h3>
                                    <span class="text-xs text-gray-400">{{ upcomingEvents.length }} total</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                            <tr>
                                                <th class="px-6 py-3 font-semibold">Event</th>
                                                <th class="px-6 py-3 font-semibold">Year</th>
                                                <th class="px-6 py-3 font-semibold">Dates</th>
                                                <th class="px-6 py-3 font-semibold">Location</th>
                                                <th class="px-6 py-3 font-semibold w-24"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="ev in upcomingEvents" :key="ev.id"
                                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                                <td class="px-6 py-4 font-medium">
                                                    {{ ev.display_name }}
                                                    <span v-if="ev.active"
                                                          class="ms-2 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                        Active
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">{{ ev.year }}</td>
                                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ formatEventRange(ev) }}</td>
                                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ locationLine(ev) }}</td>
                                                <td class="px-6 py-4">
                                                    <Link :href="eventShowHref(ev)"
                                                          class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium">
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                            <tr v-if="!upcomingEvents.length">
                                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                                    No upcoming events. Add one to get started.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Past -->
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <div class="px-6 py-4 flex items-center justify-between border-b border-gray-50 dark:border-gray-700/50">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                                        <span class="material-icons text-[16px] text-gray-400">history</span>
                                        Past events
                                    </h3>
                                    <span class="text-xs text-gray-400">{{ pastEvents.length }} total</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                            <tr>
                                                <th class="px-6 py-3 font-semibold">Event</th>
                                                <th class="px-6 py-3 font-semibold">Year</th>
                                                <th class="px-6 py-3 font-semibold">Dates</th>
                                                <th class="px-6 py-3 font-semibold">Location</th>
                                                <th class="px-6 py-3 font-semibold w-24"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="ev in pastEvents" :key="ev.id"
                                                class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                                <td class="px-6 py-4 font-medium text-gray-700 dark:text-gray-200">{{ ev.display_name }}</td>
                                                <td class="px-6 py-4">{{ ev.year }}</td>
                                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ formatEventRange(ev) }}</td>
                                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ locationLine(ev) }}</td>
                                                <td class="px-6 py-4">
                                                    <Link :href="eventShowHref(ev)"
                                                          class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium">
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                            <tr v-if="!pastEvents.length">
                                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                                    No past events yet.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ── LAYOUT BUILDER TAB ── -->
                        <div v-show="activeTab === 'layout'" class="p-6">
                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Layout Builder</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        Design and configure the visual layout for this show's events.
                                    </p>
                                </div>
                                <!-- Placeholder action -->
                                <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors opacity-50 cursor-not-allowed" disabled>
                                    <span class="material-icons text-[16px]">add</span>
                                    New Layout
                                </button>
                            </div>

                            <!-- Skeleton placeholder grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- Layout card skeleton × 3 -->
                                <div v-for="n in 3" :key="n"
                                     class="rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 p-4 flex flex-col gap-3 animate-pulse">
                                    <div class="h-32 rounded-md bg-gray-200 dark:bg-gray-600" />
                                    <div class="h-3.5 w-2/3 rounded bg-gray-200 dark:bg-gray-600" />
                                    <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-600" />
                                    <div class="flex gap-2 mt-auto pt-1">
                                        <div class="h-8 flex-1 rounded-md bg-gray-200 dark:bg-gray-600" />
                                        <div class="h-8 w-8 rounded-md bg-gray-200 dark:bg-gray-600" />
                                    </div>
                                </div>
                                <!-- "New" card -->
                                <button class="rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 p-4 flex flex-col items-center justify-center gap-2 text-gray-400 hover:border-primary-400 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-colors min-h-[160px]">
                                    <span class="material-icons text-3xl">add_circle_outline</span>
                                    <span class="text-sm font-medium">Create layout</span>
                                </button>
                            </div>
                        </div>

                        <!-- ── CHECKLIST TAB ── -->
                        <div v-show="activeTab === 'checklist'" class="p-6">

                            <Checklist
                                v-model="checklistData"
                                @save-template="handleSaveTemplate"
                            />

                        </div>

                        <!-- ── TASKS TAB ── -->
                        <div v-show="activeTab === 'tasks'" class="p-6">
                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tasks</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        Manage and assign tasks related to this show.
                                    </p>
                                </div>
                                <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors opacity-50 cursor-not-allowed" disabled>
                                    <span class="material-icons text-[16px]">add</span>
                                    Add task
                                </button>
                            </div>

                            <!-- Kanban-style column skeletons -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div v-for="(col, i) in [
                                        { label: 'To Do',       color: 'bg-gray-400' },
                                        { label: 'In Progress', color: 'bg-blue-400' },
                                        { label: 'Done',        color: 'bg-green-400' },
                                    ]" :key="i"
                                     class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 overflow-hidden">
                                    <!-- Column header -->
                                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <span :class="['w-2 h-2 rounded-full shrink-0', col.color]" />
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ col.label }}</span>
                                    </div>
                                    <!-- Task card skeletons -->
                                    <div class="p-3 space-y-2 animate-pulse">
                                        <div v-for="n in (i === 1 ? 3 : 2)" :key="n"
                                             class="rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 p-3 space-y-2 shadow-sm">
                                            <div class="h-3 w-3/4 rounded bg-gray-200 dark:bg-gray-600" />
                                            <div class="h-2.5 w-1/2 rounded bg-gray-200 dark:bg-gray-600" />
                                            <div class="flex items-center justify-between pt-1">
                                                <div class="h-5 w-16 rounded-full bg-gray-200 dark:bg-gray-600" />
                                                <div class="h-5 w-5 rounded-full bg-gray-200 dark:bg-gray-600" />
                                            </div>
                                        </div>
                                        <!-- Add task ghost -->
                                        <button class="w-full text-left px-3 py-2 text-xs text-gray-400 hover:text-primary-500 hover:bg-white dark:hover:bg-gray-700 rounded-md border border-dashed border-gray-200 dark:border-gray-600 flex items-center gap-1.5 transition-colors">
                                            <span class="material-icons text-[14px]">add</span>
                                            Add task
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- /tabbed section -->

                </div>
                <!-- /main column -->

                <!-- ── Sidebar ── -->
                <div class="col-span-12 2xl:col-span-3">
                    <div class="space-y-4">

                        <!-- Summary card -->
                        <div class="rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:p-6 space-y-4">
                            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Summary</h2>
                            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-4">
                                <li class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Upcoming</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ upcomingEvents.length }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Past</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ pastEvents.length }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Tasks</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ tasks.length }}</span>
                                </li>
                            </ul>
                            <div class="space-y-2">
                                <Link
                                    :href="route('boat-shows.events.create', showKey)"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
                                >
                                    <span class="material-icons text-[18px]">add</span>
                                    Add event
                                </Link>
                                <Link
                                    :href="route('boat-shows.edit', showKey)"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                >
                                    <span class="material-icons text-[18px]">edit</span>
                                    Edit boat show
                                </Link>
                                <Link
                                    :href="route('boat-shows.index')"
                                    class="flex w-full items-center justify-center text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 pt-1"
                                >
                                    All boat shows
                                </Link>
                            </div>
                        </div>

                        <!-- Quick-jump cards to each tab -->
                        <div class="rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:p-5 space-y-2">
                            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Jump to</h2>
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="[
                                    'flex w-full items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                                    activeTab === tab.key
                                        ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                ]"
                            >
                                <span :class="['material-icons text-[18px]', activeTab === tab.key ? 'text-primary-500' : 'text-gray-400']">
                                    {{ tab.icon }}
                                </span>
                                {{ tab.label }}
                            </button>
                        </div>

                    </div>
                </div>
                <!-- /sidebar -->

            </div>
        </div>
    </TenantLayout>
</template>
