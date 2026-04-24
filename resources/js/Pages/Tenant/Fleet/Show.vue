<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, watch } from 'vue';

const page = usePage();
const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) return;
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) showToast('success', msg);
    },
    { immediate: true },
);
watch(
    () => page.props.flash?.error,
    (msg) => {
        if (msg) showToast('error', msg);
    },
    { immediate: true },
);

const props = defineProps({
    record: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
});

const typeLabel = computed(() => (props.record.type === 'truck' ? 'Truck' : 'Trailer'));

const statusLabel = computed(() => {
    const s = props.statuses?.find((o) => o.value === props.record.status);
    return s?.label ?? props.record.status ?? '—';
});

/** For the gray page header (matches list semantics). */
const statusBadgeHeader = computed(() => {
    const s = props.record.status;
    if (s === 'active') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }
    if (s === 'inactive') {
        return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
    }
    if (s === 'maintenance') {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
});

/** For the primary blue gradient band. */
const statusBadgeOnBlue = computed(() => {
    const s = props.record.status;
    if (s === 'active') {
        return 'bg-white/20 text-white border border-white/35';
    }
    if (s === 'inactive') {
        return 'bg-white/15 text-primary-100 border border-white/25';
    }
    if (s === 'maintenance') {
        return 'bg-amber-400/25 text-amber-50 border border-amber-200/40';
    }
    return 'bg-white/15 text-white border border-white/20';
});

const pageTitle = computed(() => props.record.display_name ?? 'Fleet');

const formatDate = (v) => {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const confirmDelete = () => {
    if (confirm('Delete this fleet item? This cannot be undone.')) {
        router.delete(route('fleet.destroy', props.record.id), {
            preserveScroll: true,
        });
    }
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Fleet', href: route('fleet.index', { tab: props.record.type === 'truck' ? 'trucks' : 'trailers' }) },
    { label: pageTitle.value },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ pageTitle }}
                        </h2>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold"
                            :class="statusBadgeHeader"
                        >
                            {{ statusLabel }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('fleet.edit', record.id)"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white transition-colors hover:bg-primary-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-md font-medium text-red-700 transition-colors hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40"
                            @click="confirmDelete"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex w-full flex-col space-y-6 p-4">
            <!-- Main card: blue header band + body -->
            <div
                class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 dark:from-primary-700 dark:to-primary-800"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-primary-200/90">
                                Fleet · {{ typeLabel }}
                            </p>
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-2xl font-bold tracking-tight text-white">
                                    {{ record.display_name }}
                                </h1>
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-sm font-semibold"
                                    :class="statusBadgeOnBlue"
                                >
                                    {{ statusLabel }}
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <div class="text-xs font-semibold uppercase tracking-wide text-primary-200/90">License plate</div>
                            <div class="font-mono text-2xl font-bold text-white">
                                {{ record.license_plate || '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <h3
                                class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                Vehicle
                            </h3>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Make / Model
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ [record.make, record.model].filter(Boolean).join(' ') || '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Year
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.year ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    VIN
                                </div>
                                <div class="text-md break-all text-gray-900 dark:text-white">
                                    {{ record.vin ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Size
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.size ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3
                                class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                Location &amp; use
                            </h3>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Location
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.location?.display_name ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Mileage
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.mileage != null ? `${record.mileage} mi` : '—' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Engine / usage hours
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.hours != null ? record.hours : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3
                            class="mb-3 border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                        >
                            Maintenance
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Last service
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatDate(record.last_maintenance_at) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Next due
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatDate(record.next_maintenance_due_at) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Interval (days)
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.maintenance_interval_days ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="record.notes"
                        class="rounded-lg border border-gray-100 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                    >
                        <div
                            class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Notes
                        </div>
                        <p class="whitespace-pre-wrap text-md text-gray-800 dark:text-gray-200">
                            {{ record.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
