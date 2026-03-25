<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    recordTitle: { type: String, default: 'Boat Show Event' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    domainName: { type: String, default: 'BoatShowEvent' },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const isNested = computed(() => Object.keys(props.extraRouteParams).length > 0);

const indexHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.index', props.extraRouteParams)
        : route('boat-show-events.index')
);

const editHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.edit', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.edit', props.record.id)
);

const parentLabel = computed(() =>
    isNested.value ? 'Events (show)' : 'Boat Show Events'
);

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    if (isNested.value && props.record.boat_show) {
        items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
        items.push({
            label: props.record.boat_show.name,
            href: route('boat-shows.show', props.record.boat_show.id),
        });
        items.push({ label: 'Events', href: indexHref.value });
    } else {
        items.push({ label: 'Boat Show Events', href: indexHref.value });
    }

    items.push({ label: props.record.display_name ?? `Event #${props.record.id}` });
    return items;
});

// ── Date helpers ─────────────────────────────────────────────────────────────
const formatDate = (val) => {
    if (!val) return null;
    return new Date(val).toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
    });
};

const formatDateShort = (val) => {
    if (!val) return null;
    return new Date(val).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatYear = (val) => {
    if (!val) return null;
    return new Date(val).getFullYear();
};

const dateRange = computed(() => {
    const s = props.record.starts_at;
    const e = props.record.ends_at;
    if (s && e) return `${formatDateShort(s)} – ${formatDate(e)}`;
    if (s) return `From ${formatDate(s)}`;
    if (e) return `Until ${formatDate(e)}`;
    return null;
});

const isUpcoming = computed(() => {
    if (!props.record.starts_at) return false;
    return new Date(props.record.starts_at) > new Date();
});

const isActive = computed(() => {
    const now = new Date();
    const s = props.record.starts_at ? new Date(props.record.starts_at) : null;
    const e = props.record.ends_at ? new Date(props.record.ends_at) : null;
    if (s && e) return now >= s && now <= e;
    return false;
});

const isPast = computed(() => {
    if (!props.record.ends_at) return false;
    return new Date(props.record.ends_at) < new Date();
});

const eventStatus = computed(() => {
    if (!props.record.active) return { label: 'Inactive', color: 'text-gray-500 dark:text-gray-400', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
    if (isActive.value) return { label: 'Live Now', color: 'text-green-600 dark:text-green-400', dot: 'bg-green-500 animate-pulse', badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' };
    if (isUpcoming.value) return { label: 'Upcoming', color: 'text-blue-600 dark:text-blue-400', dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' };
    if (isPast.value) return { label: 'Past', color: 'text-gray-500 dark:text-gray-400', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' };
    return { label: 'Active', color: 'text-green-600 dark:text-green-400', dot: 'bg-green-500', badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' };
});

const hasAddress = computed(() =>
    props.record.address_line_1 || props.record.city || props.record.state
);

const fullAddress = computed(() => {
    const parts = [
        props.record.address_line_1,
        props.record.address_line_2,
        [props.record.city, props.record.state, props.record.postal_code].filter(Boolean).join(', '),
        props.record.country,
    ].filter(Boolean);
    return parts.join('\n');
});

const mapsUrl = computed(() => {
    if (props.record.latitude && props.record.longitude) {
        return `https://www.google.com/maps?q=${props.record.latitude},${props.record.longitude}`;
    }
    if (hasAddress.value) {
        const q = encodeURIComponent(fullAddress.value.replace(/\n/g, ', '));
        return `https://www.google.com/maps?q=${q}`;
    }
    return null;
});

const confirmDelete = () => {
    if (!confirm(`Delete "${props.record.display_name}"? This cannot be undone.`)) return;
    const url = isNested.value
        ? route('boat-shows.events.destroy', { ...props.extraRouteParams, event: props.record.id })
        : route('boat-show-events.destroy', props.record.id);
    router.delete(url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="record.display_name ?? 'Boat Show Event'" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 truncate">
                            {{ record.display_name ?? `Event #${record.id}` }}
                        </h2>
                        <span :class="['hidden sm:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold', eventStatus.badge]">
                            <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                            {{ eventStatus.label }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            {{ parentLabel }}
                        </Link>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full p-4 space-y-4">

            <!-- ================================================================
                 HERO BANNER
                 ================================================================ -->
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 dark:from-primary-700 dark:via-primary-800 dark:to-primary-950 shadow-lg">

                <!-- Decorative wave pattern -->
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 w-full h-full">
                        <path d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z" fill="white"/>
                        <path d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z" fill="white" opacity="0.5"/>
                    </svg>
                </div>

                <!-- Anchor watermark -->
                <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-[0.1] select-none pointer-events-none">
                    <span class="material-icons" style="font-size: 180px;">anchor</span>
                </div>

                <div class="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">

                        <!-- Left: title block -->
                        <div class="space-y-3">
                            <!-- Boat show parent link -->
                            <div v-if="record.boat_show" class="flex items-center gap-2">
                                <span class="material-icons text-[14px] text-white">sailing</span>
                                <Link
                                    :href="route('boat-shows.show', record.boat_show.id)"
                                    class="text-sm font-medium text-white hover:text-white transition-colors"
                                >
                                    {{ record.boat_show.name }}
                                </Link>
                            </div>

                            <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight">
                                {{ record.display_name }}
                            </h1>

                            <!-- Date range pill -->
                            <div v-if="dateRange || record.year" class="flex flex-wrap items-center gap-3">
                                <div v-if="dateRange" class="flex items-center gap-2 text-white">
                                    <span class="material-icons text-[16px] text-white">calendar_month</span>
                                    <span class="text-sm font-medium">{{ dateRange }}</span>
                                </div>
                                <div v-if="record.year" class="flex items-center gap-2 text-white">
                                    <span class="material-icons text-[16px] text-white">tag</span>
                                    <span class="text-sm font-medium">{{ record.year }}</span>
                                </div>
                            </div>

                            <!-- Status badge mobile -->
                            <div class="sm:hidden">
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white']">
                                    <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                                    {{ eventStatus.label }}
                                </span>
                            </div>
                        </div>

                        <!-- Right: key stats chips -->
                        <div class="flex flex-wrap gap-2">
                            <div v-if="record.venue" class="flex items-center gap-2 rounded-lg bg-white/15 backdrop-blur-sm px-3 py-2">
                                <span class="material-icons text-[16px] text-white">location_city</span>
                                <span class="text-sm text-white font-medium">{{ record.venue }}</span>
                            </div>
                            <div v-if="record.booth" class="flex items-center gap-2 rounded-lg bg-white/15 backdrop-blur-sm px-3 py-2">
                                <span class="material-icons text-[16px] text-white">store</span>
                                <span class="text-sm text-white font-medium">Booth {{ record.booth }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 BODY: 2-col grid
                 ================================================================ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- Left / Main column -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Event Details card -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                Event Details
                            </h2>
                        </div>
                        <div class="divide-y divide-gray-50 dark:divide-gray-700/60">

                            <!-- Display name -->
                            <div class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">badge</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Display Name</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.display_name }}</p>
                                </div>
                            </div>

                            <!-- Year -->
                            <div v-if="record.year" class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">tag</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Year</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.year }}</p>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div v-if="record.starts_at || record.ends_at" class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">date_range</span>
                                <div class="min-w-0 grid grid-cols-2 gap-4 w-full">
                                    <div v-if="record.starts_at">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Starts</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(record.starts_at) }}</p>
                                    </div>
                                    <div v-if="record.ends_at">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Ends</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(record.ends_at) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue -->
                            <div v-if="record.venue" class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_city</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Venue</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.venue }}</p>
                                </div>
                            </div>

                            <!-- Booth -->
                            <div v-if="record.booth" class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">store</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Booth</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.booth }}</p>
                                </div>
                            </div>

                            <!-- Boat show -->
                            <div v-if="record.boat_show" class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">sailing</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Boat Show</p>
                                    <Link
                                        :href="route('boat-shows.show', record.boat_show.id)"
                                        class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
                                    >
                                        {{ record.boat_show.name }}
                                    </Link>
                                </div>
                            </div>

                            <!-- Status row -->
                            <div class="flex items-start gap-4 px-6 py-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">toggle_on</span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">Status</p>
                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold', eventStatus.badge]">
                                        <span :class="['w-1.5 h-1.5 rounded-full', eventStatus.dot]"></span>
                                        {{ eventStatus.label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Venue address card -->
                    <div v-if="hasAddress" class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                Venue Address
                            </h2>
                            <a
                                v-if="mapsUrl"
                                :href="mapsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                <span class="material-icons text-[14px]">open_in_new</span>
                                Open in Maps
                            </a>
                        </div>
                        <div class="px-6 py-5 space-y-4">

                            <!-- Address lines -->
                            <div class="flex items-start gap-4">
                                <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_on</span>
                                <address class="not-italic text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                    <span v-if="record.address_line_1" class="block font-medium text-gray-900 dark:text-white">{{ record.address_line_1 }}</span>
                                    <span v-if="record.address_line_2" class="block">{{ record.address_line_2 }}</span>
                                    <span class="block">
                                        {{ [record.city, record.state, record.postal_code].filter(Boolean).join(', ') }}
                                    </span>
                                    <span v-if="record.country" class="block text-gray-500 dark:text-gray-400">{{ record.country }}</span>
                                </address>
                            </div>

                            <!-- Coordinates -->
                            <div v-if="record.latitude && record.longitude" class="flex items-center gap-4">
                                <span class="material-icons text-[20px] text-gray-400 shrink-0">my_location</span>
                                <p class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ Number(record.latitude).toFixed(6) }}, {{ Number(record.longitude).toFixed(6) }}
                                </p>
                            </div>

                            <!-- Static map placeholder / embed hint -->
                            <div v-if="mapsUrl" class="mt-2 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                                <a
                                    :href="mapsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-2 py-6 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group"
                                >
                                    <span class="material-icons text-[32px] group-hover:scale-110 transition-transform">map</span>
                                    <span>View on Google Maps</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right / Sidebar column -->
                <div class="space-y-4">

                    <!-- Quick actions -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 bg-gray-700 border-b border-gray-600">
                            <span class="text-sm font-semibold text-white">Actions</span>
                        </div>
                        <div class="p-4 space-y-2">
                            <Link
                                :href="editHref"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-[18px]">edit</span>
                                Edit Event
                            </Link>
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                @click="confirmDelete"
                            >
                                <span class="material-icons text-[18px]">delete_outline</span>
                                Delete Event
                            </button>
                        </div>
                    </div>

                    <!-- Meta card -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Record Info</span>
                        </div>
                        <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                            <li v-if="record.id" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">tag</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">ID</span>
                                <span class="font-mono text-xs text-gray-900 dark:text-white">{{ record.id }}</span>
                            </li>
                            <li v-if="record.created_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">calendar_today</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</span>
                            </li>
                            <li v-if="record.updated_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">update</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ formatDate(record.updated_at) }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">toggle_on</span>
                                <span class="text-gray-500 dark:text-gray-400 flex-1">Active</span>
                                <span :class="record.active ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                                    <span class="material-icons text-[18px]">{{ record.active ? 'check_circle' : 'cancel' }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Timeline card (if dates available) -->
                    <div v-if="record.starts_at || record.ends_at" class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Timeline</span>
                        </div>
                        <div class="p-5">
                            <ol class="relative ms-2 border-s border-gray-200 dark:border-gray-700 space-y-4">
                                <li class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span :class="['material-icons text-[20px]', (record.starts_at && new Date(record.starts_at) <= new Date()) ? 'text-green-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Event starts</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ record.starts_at ? formatDate(record.starts_at) : 'TBD' }}
                                        </p>
                                    </div>
                                </li>
                                <li v-if="record.ends_at" class="ms-6">
                                    <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800">
                                        <span :class="['material-icons text-[20px]', isPast ? 'text-green-500' : 'text-gray-300 dark:text-gray-600']">check_circle</span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Event ends</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ formatDate(record.ends_at) }}</p>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>