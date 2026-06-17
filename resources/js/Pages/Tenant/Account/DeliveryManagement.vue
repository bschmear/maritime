<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    showInactive: { type: Boolean, default: false },
    account: { type: Object, default: null },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const approverField = { type: 'record', typeDomain: 'User', label: 'Delivery approver' };

const form = useForm({
    approvers: props.locations.map((loc) => ({
        location_id: loc.id,
        delivery_approver_user_id: loc.delivery_approver_user_id ?? null,
    })),
});

watch(
    () => props.locations,
    (rows) => {
        form.approvers = rows.map((loc) => ({
            location_id: loc.id,
            delivery_approver_user_id: loc.delivery_approver_user_id ?? null,
        }));
    },
    { deep: true },
);

const toggleInactive = () => {
    router.get(
        route('account.delivery-management.index'),
        { show_inactive: props.showInactive ? undefined : 1 },
        { preserveState: true },
    );
};

const save = () => {
    form.patch(route('account.delivery-management.update'), { preserveScroll: true });
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Account', href: route('account.index') },
    { label: 'Delivery management' },
]);

const totalPending = computed(() =>
    props.locations.reduce((sum, loc) => sum + (loc.pending_request_count ?? 0), 0),
);

const unconfiguredCount = computed(() =>
    props.locations.filter((loc) => !loc.effective_approver).length,
);
</script>

<template>
    <Head title="Delivery management" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="w-full space-y-4 p-4">
            <!-- Hero banner -->
            <div
                class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 shadow-lg dark:from-primary-700 dark:via-primary-800 dark:to-primary-950"
            >
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 h-full w-full">
                        <path d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z" fill="white" />
                        <path d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z" fill="white" opacity="0.5" />
                    </svg>
                </div>
                <div class="pointer-events-none absolute right-6 top-1/2 -translate-y-1/2 select-none opacity-[0.08]">
                    <span class="material-icons text-[160px] leading-none text-white">local_shipping</span>
                </div>

                <div class="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-2">
                            <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
                                Delivery management
                            </h1>
                            <p class="max-w-2xl text-sm text-primary-100">
                                Choose who receives delivery request notifications for each depart-from location. Leave blank to fall back to the location manager.
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-3">
                            <div
                                v-if="totalPending > 0"
                                class="flex items-center gap-2 rounded-lg bg-amber-400/20 px-3 py-2 backdrop-blur-sm"
                            >
                                <span class="material-icons text-[16px] text-amber-200">schedule</span>
                                <span class="text-sm font-semibold text-amber-100">
                                    {{ totalPending }} pending {{ totalPending === 1 ? 'request' : 'requests' }}
                                </span>
                            </div>
                            <div
                                v-if="unconfiguredCount > 0"
                                class="flex items-center gap-2 rounded-lg bg-red-400/20 px-3 py-2 backdrop-blur-sm"
                            >
                                <span class="material-icons text-[16px] text-red-200">warning</span>
                                <span class="text-sm font-semibold text-red-100">
                                    {{ unconfiguredCount }} unconfigured
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flash success -->
            <div
                v-if="flashSuccess"
                class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200"
            >
                <span class="material-icons text-[18px] text-green-500">check_circle</span>
                {{ flashSuccess }}
            </div>

            <!-- Main form card -->
            <form @submit.prevent="save">
                <!-- Toolbar -->
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input
                            type="checkbox"
                            :checked="showInactive"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                            @change="toggleInactive"
                        />
                        Show inactive locations
                    </label>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
                    >
                        <span class="material-icons text-[16px]">{{ form.processing ? 'hourglass_empty' : 'save' }}</span>
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>

                <!-- Location cards -->
                <div v-if="locations.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
                    <span class="material-icons text-4xl text-gray-300 dark:text-gray-600">location_off</span>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No locations found.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="(loc, index) in locations"
                        :key="loc.id"
                        class="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-800"
                        :class="loc.inactive
                            ? 'border-gray-200 opacity-70 dark:border-gray-700'
                            : 'border-gray-200 dark:border-gray-700'"
                    >
                        <!-- Card header -->
                        <div class="flex items-start justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                            <div class="min-w-0">
                                <Link
                                    :href="route('locations.show', loc.id)"
                                    class="block truncate text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                >
                                    {{ loc.display_name }}
                                </Link>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span
                                    v-if="loc.inactive"
                                    class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                >
                                    Inactive
                                </span>
                                <Link
                                    v-if="loc.pending_request_count > 0"
                                    :href="route('deliveries.requests.index', { location_id: loc.id })"
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    <span class="material-icons text-[12px]">schedule</span>
                                    {{ loc.pending_request_count }} pending
                                </Link>
                            </div>
                        </div>

                        <!-- Card body -->
                        <div class="space-y-4 p-4">
                            <!-- Manager -->
                            <div class="flex items-center gap-2 text-sm">
                                <span class="material-icons text-[16px] text-gray-400">person</span>
                                <span class="text-gray-500 dark:text-gray-400">Manager:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ loc.manager?.display_name ?? '—' }}
                                </span>
                            </div>

                            <!-- Approver selector -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Delivery approver
                                </label>
                                <RecordSelect
                                    v-model="form.approvers[index].delivery_approver_user_id"
                                    :field="approverField"
                                />
                            </div>

                            <!-- Effective approver -->
                            <div
                                class="flex items-start gap-2 rounded-lg px-3 py-2.5 text-xs"
                                :class="loc.effective_approver
                                    ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-300'
                                    : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-300'"
                            >
                                <span
                                    class="material-icons mt-0.5 text-[14px]"
                                    :class="loc.effective_approver ? 'text-green-500' : 'text-red-500'"
                                >
                                    {{ loc.effective_approver ? 'check_circle' : 'error' }}
                                </span>
                                <span v-if="loc.effective_approver">
                                    Effective: <strong>{{ loc.effective_approver.display_name }}</strong>
                                    <span v-if="loc.effective_approver.uses_manager_fallback" class="opacity-75"> (uses manager)</span>
                                </span>
                                <span v-else>
                                    Not configured — delivery requests cannot be submitted
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom save bar (sticky on mobile) -->
                <div
                    v-if="locations.length > 0"
                    class="sticky bottom-4 mt-4 flex justify-end"
                >
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-primary-700 disabled:opacity-50"
                    >
                        <span class="material-icons text-[16px]">{{ form.processing ? 'hourglass_empty' : 'save' }}</span>
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
