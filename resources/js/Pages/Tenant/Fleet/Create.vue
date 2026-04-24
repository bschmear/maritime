<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import FleetFormStatusPanel from '@/Components/Tenant/FleetFormStatusPanel.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    fleetType: { type: String, required: true },
    locations: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const form = useForm({
    type: props.fleetType,
    display_name: '',
    license_plate: '',
    vin: '',
    make: '',
    model: '',
    year: '',
    size: '',
    location_id: '',
    status: 'active',
    last_maintenance_at: '',
    next_maintenance_due_at: '',
    maintenance_interval_days: '',
    mileage: '',
    hours: '',
    notes: '',
});

const typeLabel = computed(() => (props.fleetType === 'truck' ? 'Truck' : 'Trailer'));

const pageTitle = computed(() => `New ${typeLabel.value}`);

const headerEyebrow = computed(() => (props.fleetType === 'truck' ? 'TRUCK' : 'TRAILER'));

const cancelHref = computed(() => route('fleet.index', { tab: props.fleetType === 'truck' ? 'trucks' : 'trailers' }));

const inputClass =
    'mt-1 block w-full rounded-lg border border-gray-300 py-2 px-3 text-md shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';

const submit = () => {
    form
        .transform((data) => {
            const n = { ...data };
            ['location_id', 'year', 'mileage', 'hours', 'maintenance_interval_days'].forEach((k) => {
                if (n[k] === '' || n[k] === undefined) {
                    n[k] = null;
                }
            });
            if (n.location_id !== null) {
                n.location_id = Number(n.location_id);
            }
            return n;
        })
        .post(route('fleet.store'), { preserveScroll: true });
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Fleet', href: route('fleet.index', { tab: props.fleetType === 'truck' ? 'trucks' : 'trailers' }) },
    { label: 'Create' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ pageTitle }}
                </h2>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-6 lg:grid-cols-12">
                    <!-- Main column -->
                    <div class="space-y-6 lg:col-span-8">
                        <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                            <!-- Primary header (matches Invoice form) -->
                            <div
                                class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wider text-primary-200/90"
                                        >
                                            New fleet · {{ headerEyebrow }}
                                        </p>
                                        <h1 class="mt-1 text-2xl font-bold text-white">NEW FLEET</h1>
                                        <p class="mt-1 text-sm text-primary-100">
                                            Add a new {{ typeLabel.toLowerCase() }} for scheduling and delivery assignment.
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-white"
                                        >
                                            <span class="material-icons text-[20px]">{{
                                                fleetType === 'truck' ? 'local_shipping' : 'rv_hookup'
                                            }}</span>
                                            <span class="text-sm font-semibold">{{ typeLabel }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6 p-6">
                                <template v-for="(err, key) in form.errors" :key="key">
                                    <p
                                        v-if="key !== 'status'"
                                        class="text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ err }}
                                    </p>
                                </template>

                                <FleetFormStatusPanel
                                    v-model="form.status"
                                    :statuses="statuses"
                                    :error="form.errors.status"
                                />

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.display_name"
                                            type="text"
                                            required
                                            :class="inputClass"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >License plate</label
                                        >
                                        <input v-model="form.license_plate" type="text" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Location</label
                                        >
                                        <select v-model="form.location_id" :class="inputClass">
                                            <option value="">—</option>
                                            <option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">
                                                {{ loc.display_name
                                                }}{{ loc.city ? ` — ${loc.city}` : '' }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >VIN</label
                                        >
                                        <input v-model="form.vin" type="text" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Make</label
                                        >
                                        <input v-model="form.make" type="text" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Model</label
                                        >
                                        <input v-model="form.model" type="text" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Year</label
                                        >
                                        <input v-model="form.year" type="number" min="1950" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Size / dimensions</label
                                        >
                                        <input v-model="form.size" type="text" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Mileage</label
                                        >
                                        <input v-model="form.mileage" type="number" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Engine / usage hours</label
                                        >
                                        <input v-model="form.hours" type="number" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Last maintenance</label
                                        >
                                        <input v-model="form.last_maintenance_at" type="date" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Next due</label
                                        >
                                        <input
                                            v-model="form.next_maintenance_due_at"
                                            type="date"
                                            :class="inputClass"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Interval (days)</label
                                        >
                                        <input
                                            v-model="form.maintenance_interval_days"
                                            type="number"
                                            :class="inputClass"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Notes</label
                                    >
                                    <textarea v-model="form.notes" rows="3" :class="inputClass" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 lg:col-span-4">
                        <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                            <div
                                class="flex items-center justify-between border-b border-gray-200 bg-gray-700 px-5 py-4 dark:border-gray-600"
                            >
                                <span class="text-sm font-semibold text-white">Actions</span>
                            </div>
                            <div class="space-y-3 p-5">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span
                                        class="material-icons text-[18px]"
                                        :class="{ 'animate-spin': form.processing }"
                                    >{{ form.processing ? 'sync' : 'check' }}</span>
                                    {{ form.processing ? 'Saving...' : 'Create fleet item' }}
                                </button>
                                <Link
                                    :href="cancelHref"
                                    class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
