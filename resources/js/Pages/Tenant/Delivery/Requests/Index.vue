<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    deliveries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    locationOptions: { type: Array, default: () => [] },
    approverOptions: { type: Array, default: () => [] },
    canCreateDelivery: { type: Boolean, default: false },
    pendingCount: { type: Number, default: 0 },
});

const locationFilter = ref(props.filters?.location_id ? String(props.filters.location_id) : '');
const approverFilter = ref(props.filters?.approver_id ? String(props.filters.approver_id) : '');

watch(
    () => props.filters?.location_id,
    (v) => { locationFilter.value = v ? String(v) : ''; },
);

watch(
    () => props.filters?.approver_id,
    (v) => { approverFilter.value = v ? String(v) : ''; },
);

const applyFilters = () => {
    router.get(route('deliveries.requests.index'), {
        location_id: locationFilter.value || undefined,
        approver_id: approverFilter.value || undefined,
    }, { preserveState: true });
};

const formatDateTime = (v) => {
    if (!v) return '—';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Deliveries', href: route('deliveries.index') },
    { label: 'Delivery requests' },
]);

const hasResults = computed(() => (props.deliveries?.data ?? []).length > 0);
const showPagination = computed(() => hasResults.value && (props.deliveries?.total ?? 0) > 0);

const paginationFrom = computed(() =>
    (props.deliveries.current_page - 1) * props.deliveries.per_page + 1,
);

const paginationTo = computed(() =>
    Math.min(props.deliveries.current_page * props.deliveries.per_page, props.deliveries.total),
);

/** Effective approver for a delivery's depart-from location (dedicated approver, else manager). */
const effectiveApprover = (delivery) => {
    const loc = delivery?.location;
    if (!loc) {
        return null;
    }

    const dedicated = loc.delivery_approver ?? loc.deliveryApprover ?? null;
    const manager = loc.manager_user ?? loc.managerUser ?? null;

    return dedicated ?? manager;
};

const effectiveApproverName = (delivery) => effectiveApprover(delivery)?.display_name ?? '—';
</script>

<template>
    <Head title="Delivery requests" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Delivery requests</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ pendingCount }} pending approval
                        </p>
                    </div>
                    <Link
                        v-if="!canCreateDelivery"
                        :href="route('deliveries.requests.create')"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-500"
                    >
                        <span class="material-icons text-lg">add</span>
                        Create delivery request
                    </Link>
                    <Link
                        v-else
                        :href="route('deliveries.requests.create')"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-medium text-amber-800 shadow-sm transition-colors hover:bg-amber-50 dark:border-amber-700 dark:bg-gray-800 dark:text-amber-200 dark:hover:bg-amber-950/40"
                    >
                        <span class="material-icons text-lg">add</span>
                        New request
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 p-4">
            <!-- Filters -->
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Depart-from location
                        </label>
                        <select
                            v-model="locationFilter"
                            class="input-style w-full text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            @change="applyFilters"
                        >
                            <option value="">All locations</option>
                            <option
                                v-for="loc in locationOptions"
                                :key="loc.id"
                                :value="String(loc.id)"
                            >
                                {{ loc.display_name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Approver
                        </label>
                        <select
                            v-model="approverFilter"
                            class="input-style w-full text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            @change="applyFilters"
                        >
                            <option value="">All approvers</option>
                            <option
                                v-for="user in approverOptions"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.display_name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="!hasResults"
                class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800"
            >
                <span class="material-icons text-5xl text-gray-300 dark:text-gray-600">pending_actions</span>
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">No pending delivery requests</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="locationFilter || approverFilter">Try clearing the filters.</template>
                    <template v-else>Submitted requests will appear here until approved.</template>
                </p>
                <Link
                    :href="route('deliveries.requests.create')"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                >
                    <span class="material-icons text-base">add</span>
                    Create request
                </Link>
            </div>

            <template v-else>
                <!-- Mobile cards -->
                <div class="grid grid-cols-1 gap-3 md:hidden">
                    <Link
                        v-for="d in deliveries.data"
                        :key="d.id"
                        :href="route('deliveries.show', d.id)"
                        class="block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-colors hover:border-amber-300 hover:bg-amber-50/30 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-amber-700 dark:hover:bg-amber-950/20"
                    >
                        <div class="flex items-start justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary-700 dark:text-primary-300">
                                    {{ d.display_name }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ d.customer?.display_name ?? '—' }}
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                <span class="material-icons text-[12px]">schedule</span>
                                Pending
                            </span>
                        </div>
                        <dl class="space-y-2.5 p-4 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Location</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-gray-100">
                                    {{ d.location?.display_name ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Approver</dt>
                                <dd class="text-right text-gray-900 dark:text-gray-100">
                                    {{ effectiveApproverName(d) }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Requested by</dt>
                                <dd class="text-right text-gray-900 dark:text-gray-100">
                                    {{ d.requested_by?.display_name ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Scheduled</dt>
                                <dd class="text-right font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatDateTime(d.scheduled_at) }}
                                </dd>
                            </div>
                            <div v-if="d.technician?.display_name || d.technician?.name" class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Driver</dt>
                                <dd class="text-right text-gray-900 dark:text-gray-100">
                                    {{ d.technician?.display_name ?? d.technician?.name }}
                                </dd>
                            </div>
                        </dl>
                    </Link>
                </div>

                <!-- Desktop table -->
                <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Delivery
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Customer
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Location
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Approver
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Requested by
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Scheduled
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr
                                    v-for="d in deliveries.data"
                                    :key="d.id"
                                    class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <Link
                                            :href="route('deliveries.show', d.id)"
                                            class="font-medium text-primary-700 hover:underline dark:text-primary-300"
                                        >
                                            {{ d.display_name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ d.customer?.display_name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ d.location?.display_name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <Link
                                            v-if="effectiveApprover(d)?.id"
                                            :href="route('users.show', effectiveApprover(d).id)"
                                            class="text-primary-700 hover:underline dark:text-primary-300"
                                            @click.stop
                                        >
                                            {{ effectiveApproverName(d) }}
                                        </Link>
                                        <span v-else>{{ effectiveApproverName(d) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ d.requested_by?.display_name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ formatDateTime(d.scheduled_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (desktop, inside table card) -->
                    <div
                        v-if="showPagination"
                        class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ paginationFrom }}–{{ paginationTo }} of {{ deliveries.total }}
                        </p>
                        <div class="flex gap-2">
                            <Link
                                v-if="deliveries.prev_page_url"
                                :href="deliveries.prev_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            >
                                Previous
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="cursor-not-allowed rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-400 opacity-50 dark:border-gray-600 dark:bg-gray-700"
                            >
                                Previous
                            </button>
                            <Link
                                v-if="deliveries.next_page_url"
                                :href="deliveries.next_page_url"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            >
                                Next
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="cursor-not-allowed rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-400 opacity-50 dark:border-gray-600 dark:bg-gray-700"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination (mobile) -->
                <div
                    v-if="showPagination"
                    class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:hidden"
                >
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ paginationFrom }}–{{ paginationTo }} of {{ deliveries.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="deliveries.prev_page_url"
                            :href="deliveries.prev_page_url"
                            class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-center text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            Previous
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="flex-1 cursor-not-allowed rounded-lg border border-gray-300 py-2.5 text-center text-sm font-medium text-gray-400 opacity-50 dark:border-gray-600 dark:bg-gray-700"
                        >
                            Previous
                        </button>
                        <Link
                            v-if="deliveries.next_page_url"
                            :href="deliveries.next_page_url"
                            class="flex-1 rounded-lg border border-gray-300 bg-white py-2.5 text-center text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            Next
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="flex-1 cursor-not-allowed rounded-lg border border-gray-300 py-2.5 text-center text-sm font-medium text-gray-400 opacity-50 dark:border-gray-600 dark:bg-gray-700"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </TenantLayout>
</template>
