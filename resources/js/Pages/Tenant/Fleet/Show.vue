<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, reactive, ref, watch } from 'vue';

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

const formatMoney = (n) => {
    if (n == null || n === '') return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(n));
};

/** Whole numbers with thousands separators (e.g. 68,500). */
const formatInteger = (n) => {
    if (n == null || n === '') {
        return '';
    }
    const num = Number(n);
    if (Number.isNaN(num)) {
        return String(n);
    }
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(num);
};

const maintenanceLogs = computed(() => props.record.maintenance_logs ?? []);

const maintenanceTypeField = Object.freeze({
    type: 'record',
    typeDomain: 'MaintenanceType',
    label: 'Maintenance type(s)',
    create: true,
});

const maintenanceLookupExtras = computed(() => {
    const t = props.record.type;
    if (t === 'truck' || t === 'trailer') {
        return { fleet_applies: t };
    }
    return {};
});

const newLogForm = useForm({
    performed_at: new Date().toISOString().slice(0, 10),
    type_ids: [],
    cost: '',
    mileage: '',
    hours: '',
    notes: '',
});

const submitNewLog = () => {
    newLogForm
        .transform((data) => {
            const n = { ...data };
            const raw = n.type_ids;
            const arr = Array.isArray(raw) ? raw : [];
            n.type_ids = [...new Set(arr.map((x) => Number(x)).filter((x) => !Number.isNaN(x) && x > 0))];
            return n;
        })
        .post(route('fleet.maintenance.store', props.record.id), {
            preserveScroll: true,
            onSuccess: () => {
                newLogForm.reset();
                newLogForm.performed_at = new Date().toISOString().slice(0, 10);
                newLogForm.type_ids = [];
            },
        });
};

const editingId = ref(null);
const editDraft = reactive({
    performed_at: '',
    type_ids: [],
    cost: '',
    mileage: '',
    hours: '',
    notes: '',
});

const editLogTypeHints = computed(() => {
    if (!editingId.value) {
        return [];
    }
    const log = maintenanceLogs.value.find((l) => l.id === editingId.value);
    return log?.maintenance_types ?? [];
});

const startEditLog = (log) => {
    editingId.value = log.id;
    editDraft.performed_at = log.performed_at ?? '';
    const ids = Array.isArray(log.type_ids) && log.type_ids.length
        ? log.type_ids.map((x) => Number(x)).filter((x) => !Number.isNaN(x) && x > 0)
        : (log.maintenance_types ?? []).map((t) => t.id);
    editDraft.type_ids = [...new Set(ids)];
    editDraft.cost = log.cost ?? '';
    editDraft.mileage = log.mileage ?? '';
    editDraft.hours = log.hours ?? '';
    editDraft.notes = log.notes ?? '';
};

const cancelEditLog = () => {
    editingId.value = null;
};

const saveEditLog = () => {
    if (!editingId.value) return;
    const typeIds = Array.isArray(editDraft.type_ids)
        ? [...new Set(editDraft.type_ids.map((x) => Number(x)).filter((x) => !Number.isNaN(x) && x > 0))]
        : [];
    const payload = {
        performed_at: editDraft.performed_at,
        type_ids: typeIds,
        cost: editDraft.cost === '' ? null : editDraft.cost,
        mileage: editDraft.mileage === '' ? null : editDraft.mileage,
        hours: editDraft.hours === '' ? null : editDraft.hours,
        notes: editDraft.notes,
    };
    router.patch(route('fleet.maintenance.update', [props.record.id, editingId.value]), payload, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

const deleteLog = (log) => {
    if (!confirm('Delete this maintenance record?')) return;
    router.delete(route('fleet.maintenance.destroy', [props.record.id, log.id]), { preserveScroll: true });
};

const formatMaintenanceTypeNames = (log) => {
    const types = log?.maintenance_types;
    if (types?.length) {
        return types.map((t) => t.display_name).join(', ');
    }
    return '—';
};

const formatEnumLabel = (v) => {
    if (!v) return '—';
    return String(v)
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const weightUnit = computed(() => props.record.weight_unit || 'lbs');

const formatWeight = (v) => (v != null ? `${formatInteger(v)} ${weightUnit.value}` : '—');

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
                            :href="route('fleet.maintenance.index')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-md font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <span class="material-icons text-[16px]">history</span>
                            Maintenance reports
                        </Link>
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
                            <div v-if="record.type === 'truck'">
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Fuel type
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatEnumLabel(record.fuel_type) }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3
                                class="border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                Location &amp; use
                            </h3>
                            <div v-if="record.type === 'truck'">
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
                                    {{ record.mileage != null ? `${formatInteger(record.mileage)} mi` : '—' }}
                                </div>
                            </div>
                            <div v-if="record.type === 'truck'">
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
                            {{ record.type === 'truck' ? 'Truck capacity' : 'Trailer capacity' }}
                        </h3>
                        <div v-if="record.type === 'truck'" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Weight unit
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ weightUnit }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Towing capacity
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatWeight(record.towing_capacity) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Payload capacity
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatWeight(record.payload_capacity) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    GVWR
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatWeight(record.gvwr) }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Weight capacity
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ formatWeight(record.weight_capacity) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Weight unit
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ weightUnit }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Axle count
                                </div>
                                <div class="text-md text-gray-900 dark:text-white">
                                    {{ record.axle_count ?? '—' }}
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

                    <!-- Service history: this unit only (full company report lives under Maintenance reports) -->
                    <div>
                        <div
                            class="mb-3 flex flex-col gap-2 border-b border-gray-200 pb-2 sm:flex-row sm:items-end sm:justify-between dark:border-gray-700"
                        >
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Service history
                                <span class="mt-0.5 block text-xs font-normal normal-case text-gray-400 dark:text-gray-500">
                                    This vehicle only · {{ maintenanceLogs.length }}
                                    {{ maintenanceLogs.length === 1 ? 'record' : 'records' }}
                                </span>
                            </h3>
                            <Link
                                :href="route('fleet.maintenance.index', { fleet_id: record.id })"
                                class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                <span class="material-icons text-md">list_alt</span>
                                Open full report (this unit)
                            </Link>
                        </div>

                        <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Date
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Type
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Cost
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Mi / Hrs
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Notes
                                        </th>
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <template v-for="log in maintenanceLogs" :key="log.id">
                                        <tr v-if="editingId !== log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                            <td class="whitespace-nowrap px-3 py-2 text-md text-gray-900 dark:text-gray-100">
                                                {{ formatDate(log.performed_at) }}
                                            </td>
                                            <td class="px-3 py-2 text-md text-gray-800 dark:text-gray-200">
                                                {{ formatMaintenanceTypeNames(log) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-md text-gray-700 dark:text-gray-300">
                                                {{ formatMoney(log.cost) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-md text-gray-600 dark:text-gray-400">
                                                <span v-if="log.mileage != null">{{ formatInteger(log.mileage) }} mi</span>
                                                <span v-if="log.mileage != null && log.hours != null"> · </span>
                                                <span v-if="log.hours != null">{{ log.hours }} h</span>
                                                <span v-if="log.mileage == null && log.hours == null">—</span>
                                            </td>
                                            <td class="max-w-xs truncate px-3 py-2 text-sm text-gray-500 dark:text-gray-400" :title="log.notes">
                                                {{ log.notes || '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right text-md">
                                                <Link
                                                    :href="route('fleet.maintenance.show', log.id)"
                                                    class="mr-2 font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    View
                                                </Link>
                                                <button
                                                    type="button"
                                                    class="mr-2 font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                                    @click="startEditLog(log)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="font-medium text-red-600 hover:underline dark:text-red-400"
                                                    @click="deleteLog(log)"
                                                >
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-else class="bg-gray-50 dark:bg-gray-900/50">
                                            <td colspan="6" class="p-4">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Date</label>
                                                        <input
                                                            v-model="editDraft.performed_at"
                                                            type="date"
                                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                    <div class="sm:col-span-2 lg:col-span-3">
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Maintenance type</label>
                                                        <RecordSelect
                                                            id="edit_maintenance_type_id"
                                                            :field="maintenanceTypeField"
                                                            v-model="editDraft.type_ids"
                                                            multiple
                                                            :multi-hints="editLogTypeHints"
                                                            :enum-options="[]"
                                                            :record="record"
                                                            field-key="type_ids"
                                                            :extra-lookup-params="maintenanceLookupExtras"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cost</label>
                                                        <input
                                                            v-model="editDraft.cost"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Mileage</label>
                                                        <input
                                                            v-model="editDraft.mileage"
                                                            type="number"
                                                            min="0"
                                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Hours</label>
                                                        <input
                                                            v-model="editDraft.hours"
                                                            type="number"
                                                            min="0"
                                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                    <div class="sm:col-span-2 lg:col-span-3">
                                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Notes</label>
                                                        <textarea
                                                            v-model="editDraft.notes"
                                                            rows="2"
                                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        ></textarea>
                                                    </div>
                                                </div>
                                                <div class="mt-3 flex gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                                                        @click="saveEditLog"
                                                    >
                                                        Save
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                        @click="cancelEditLog"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="!maintenanceLogs.length">
                                        <td colspan="6" class="px-3 py-8 text-center text-md text-gray-500 dark:text-gray-400">
                                            No service records yet. Add one below.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-if="$page.props.errors?.maintenance" class="mb-3 text-sm text-red-600 dark:text-red-400">
                            {{ $page.props.errors.maintenance }}
                        </p>
                        <ul
                            v-if="Object.keys(newLogForm.errors).length"
                            class="mb-3 list-inside list-disc text-sm text-red-600 dark:text-red-400"
                        >
                            <li v-for="(msg, key) in newLogForm.errors" :key="key">
                                {{ Array.isArray(msg) ? msg[0] : msg }}
                            </li>
                        </ul>

                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-900/30">
                            <h4 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Add service record</h4>
                            <form class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submitNewLog">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Date</label>
                                    <input
                                        v-model="newLogForm.performed_at"
                                        type="date"
                                        required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Maintenance type</label>
                                    <RecordSelect
                                        id="new_maintenance_type_id"
                                        :field="maintenanceTypeField"
                                        v-model="newLogForm.type_ids"
                                        multiple
                                        :enum-options="[]"
                                        :record="record"
                                        field-key="type_ids"
                                        :extra-lookup-params="maintenanceLookupExtras"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cost</label>
                                    <input
                                        v-model="newLogForm.cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Mileage</label>
                                    <input
                                        v-model="newLogForm.mileage"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Hours</label>
                                    <input
                                        v-model="newLogForm.hours"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Notes</label>
                                    <textarea
                                        v-model="newLogForm.notes"
                                        rows="2"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    ></textarea>
                                </div>
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <button
                                        type="submit"
                                        :disabled="newLogForm.processing"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                                    >
                                        <span class="material-icons text-lg">add</span>
                                        Add record
                                    </button>
                                </div>
                            </form>
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
