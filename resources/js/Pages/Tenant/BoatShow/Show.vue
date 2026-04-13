<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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
});

const showKey = computed(() => props.record.id ?? props.record.slug);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Boat Shows', href: route('boat-shows.index') },
    { label: props.record.display_name ?? 'Boat show' },
]);

const formatDate = (val) =>
    val ? new Date(val).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const formatEventRange = (ev) => {
    const a = formatDate(ev.starts_at);
    const b = formatDate(ev.ends_at);
    if (a === '—' && b === '—') {
        return '—';
    }
    if (a === b || b === '—') {
        return a;
    }
    return `${a} – ${b}`;
};

const locationLine = (ev) =>
    [ev.venue, [ev.city, ev.state].filter(Boolean).join(', '), ev.country].filter(Boolean).join(' · ') || '—';

const eventShowHref = (ev) =>
    route('boat-shows.events.show', { boatShow: showKey.value, event: ev.id });
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
                <!-- Main: details + event lists -->
                <div class="col-span-12 2xl:col-span-9 space-y-4">
                    <div class="rounded-lg bg-white p-6 shadow-md dark:bg-gray-800 sm:p-8">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                            <div
                                v-if="imageUrls?.logo"
                                class="shrink-0 overflow-hidden rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img
                                    :src="imageUrls.logo"
                                    alt=""
                                    class="h-28 w-28 object-contain sm:h-36 sm:w-36"
                                />
                            </div>
                            <div class="min-w-0 flex-1 space-y-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ record.display_name }}
                                    </h1>
                                    <p
                                        v-if="record.description"
                                        class="mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line"
                                    >
                                        {{ record.description }}
                                    </p>
                                    <p
                                        v-else
                                        class="mt-2 text-sm text-gray-400 dark:text-gray-500 italic"
                                    >
                                        No description
                                    </p>
                                </div>
                                <div v-if="record.website" class="flex flex-wrap gap-3 text-sm">
                                    <a
                                        :href="record.website"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    >
                                        <span class="material-icons text-[18px]">language</span>
                                        Website
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming -->
                    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800 overflow-hidden">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Upcoming &amp; current events
                            </h2>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ upcomingEvents.length }} total
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold">Event</th>
                                        <th class="px-6 py-3 font-semibold">Year</th>
                                        <th class="px-6 py-3 font-semibold">Dates</th>
                                        <th class="px-6 py-3 font-semibold">Location</th>
                                        <th class="px-6 py-3 font-semibold w-24"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="ev in upcomingEvents"
                                        :key="ev.id"
                                        class="border-b border-gray-100 dark:border-gray-700"
                                    >
                                        <td class="px-6 py-4 font-medium">
                                            {{ ev.display_name }}
                                            <span
                                                v-if="ev.active"
                                                class="ms-2 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"
                                            >
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">{{ ev.year }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ formatEventRange(ev) }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ locationLine(ev) }}</td>
                                        <td class="px-6 py-4">
                                            <Link
                                                :href="eventShowHref(ev)"
                                                class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium"
                                            >
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
                    <div class="rounded-lg bg-white shadow-md dark:bg-gray-800 overflow-hidden">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Past events
                            </h2>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ pastEvents.length }} total
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-900 dark:text-white">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold">Event</th>
                                        <th class="px-6 py-3 font-semibold">Year</th>
                                        <th class="px-6 py-3 font-semibold">Dates</th>
                                        <th class="px-6 py-3 font-semibold">Location</th>
                                        <th class="px-6 py-3 font-semibold w-24"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="ev in pastEvents"
                                        :key="ev.id"
                                        class="border-b border-gray-100 dark:border-gray-700"
                                    >
                                        <td class="px-6 py-4 font-medium text-gray-700 dark:text-gray-200">
                                            {{ ev.display_name }}
                                        </td>
                                        <td class="px-6 py-4">{{ ev.year }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ formatEventRange(ev) }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ locationLine(ev) }}</td>
                                        <td class="px-6 py-4">
                                            <Link
                                                :href="eventShowHref(ev)"
                                                class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium"
                                            >
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

                <!-- Sidebar -->
                <div class="col-span-12 2xl:col-span-3">
                    <div class="space-y-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800 sm:p-6">
                        <h2 class="text-lg text-gray-500 dark:text-gray-400">Summary</h2>
                        <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-4">
                            <li class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Upcoming</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ upcomingEvents.length }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Past</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ pastEvents.length }}</span>
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
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
