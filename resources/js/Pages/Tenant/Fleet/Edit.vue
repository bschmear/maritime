<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import FleetFormStatusPanel from '@/Components/Tenant/FleetFormStatusPanel.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    fuelTypes: { type: Array, default: () => [] },
    weightUnits: { type: Array, default: () => [] },
});

const form = useForm({
    type: props.record.type,
    display_name: props.record.display_name ?? '',
    license_plate: props.record.license_plate ?? '',
    vin: props.record.vin ?? '',
    make: props.record.make ?? '',
    model: props.record.model ?? '',
    year: props.record.year ?? '',
    size: props.record.size ?? '',
    fuel_type: props.record.fuel_type ?? '',
    weight_capacity: props.record.weight_capacity ?? '',
    weight_unit: props.record.weight_unit ?? 'lbs',
    towing_capacity: props.record.towing_capacity ?? '',
    payload_capacity: props.record.payload_capacity ?? '',
    gvwr: props.record.gvwr ?? '',
    axle_count: props.record.axle_count ?? '',
    location_id: props.record.location_id != null ? String(props.record.location_id) : '',
    status: props.record.status ?? 'active',
    last_maintenance_at: props.record.last_maintenance_at ?? '',
    next_maintenance_due_at: props.record.next_maintenance_due_at ?? '',
    maintenance_interval_days: props.record.maintenance_interval_days ?? '',
    mileage: props.record.mileage ?? '',
    hours: props.record.hours ?? '',
    notes: props.record.notes ?? '',
});

const typeLabel = computed(() => (props.record.type === 'truck' ? 'Truck' : 'Trailer'));

const isTruck = computed(() => props.record.type === 'truck');

const isTrailer = computed(() => props.record.type === 'trailer');

const pseudoRecord = computed(() => props.record ?? null);

/** `create: true` enables RecordSelect’s add-new flow (same as schema-driven Form.vue fields). */
const locationRecordField = Object.freeze({
    type: 'record',
    typeDomain: 'Location',
    label: 'Location',
    create: true,
});

const pageTitle = computed(
    () => `${props.record.display_name || typeLabel.value}`,
);

const headerEyebrow = computed(() => (props.record.type === 'truck' ? 'TRUCK' : 'TRAILER'));

const indexTab = computed(() => (props.record.type === 'truck' ? 'trucks' : 'trailers'));

const inputClass =
    'mt-1 block w-full rounded-lg border border-gray-300 py-2 px-3 text-md shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';

const submit = () => {
    form
        .transform((data) => {
            const n = { ...data };
            [
                'location_id',
                'year',
                'mileage',
                'hours',
                'maintenance_interval_days',
                'weight_capacity',
                'towing_capacity',
                'payload_capacity',
                'gvwr',
                'axle_count',
            ].forEach((k) => {
                if (n[k] === '' || n[k] === undefined) {
                    n[k] = null;
                }
            });
            ['fuel_type'].forEach((k) => {
                if (n[k] === '' || n[k] === undefined) {
                    n[k] = null;
                }
            });
            if (n.weight_unit === '' || n.weight_unit === undefined || n.weight_unit === null) {
                n.weight_unit = 'lbs';
            }
            if (n.location_id !== null) {
                n.location_id = Number(n.location_id);
            }
            if (props.record.type === 'truck') {
                n.weight_capacity = null;
                n.axle_count = null;
            } else {
                n.fuel_type = null;
                n.towing_capacity = null;
                n.payload_capacity = null;
                n.gvwr = null;
                n.mileage = null;
                n.hours = null;
            }
            return n;
        })
        .put(route('fleet.update', props.record.id), { preserveScroll: true });
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Fleet', href: route('fleet.index', { tab: indexTab.value }) },
    { label: props.record.display_name ?? 'Edit' },
]);
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
               
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-6 lg:grid-cols-12">
                    <div class="space-y-6 lg:col-span-8">
                        <div class="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                            <div
                                class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wider text-primary-200/90"
                                        >
                                            Edit fleet · {{ headerEyebrow }}
                                        </p>
                                        <h1 class="mt-1 text-2xl font-bold text-white">EDIT {{ pageTitle }}</h1>
                                        <p class="mt-1 text-sm text-primary-100">
                                            Update
                                            <span class="font-medium text-white">{{
                                                record.display_name || 'this unit'
                                            }}</span>
                                            — status, location, and maintenance in one place.
                                        </p>
                                        <p class="mt-2 text-sm text-primary-200/90">
                                            <Link
                                                :href="route('fleet.show', record.id)"
                                                class="font-medium text-white underline decoration-white/50 underline-offset-2 hover:decoration-white"
                                            >
                                                View profile
                                            </Link>
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-white"
                                        >
                                            <span class="material-icons text-[20px]">{{
                                                record.type === 'truck' ? 'local_shipping' : 'rv_hookup'
                                            }}</span>
                                            <span class="text-sm font-semibold">{{ typeLabel }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6 p-6">
                                <template v-for="(err, key) in form.errors" :key="key">
                                    <p
                                        v-if="!['status', 'location_id'].includes(key)"
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
                                        <RecordSelect
                                            id="location_id"
                                            :field="locationRecordField"
                                            v-model="form.location_id"
                                            :enum-options="locations"
                                            :record="pseudoRecord"
                                            field-key="location_id"
                                        />
                                        <p v-if="form.errors.location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.location_id }}
                                        </p>
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
                                    <div v-if="isTruck">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Fuel type</label
                                        >
                                        <select v-model="form.fuel_type" :class="inputClass">
                                            <option value="">—</option>
                                            <option v-for="o in fuelTypes" :key="o.value" :value="o.value">
                                                {{ o.label ?? o.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <template v-if="isTruck">
                                        <div class="md:col-span-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                Truck capacity
                                            </h3>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Weight unit</label
                                            >
                                            <select v-model="form.weight_unit" :class="inputClass">
                                                <option v-for="o in weightUnits" :key="o.value" :value="o.value">
                                                    {{ o.label ?? o.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Towing capacity ({{ form.weight_unit || 'lbs' }})</label
                                            >
                                            <input v-model="form.towing_capacity" type="number" min="0" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Payload capacity ({{ form.weight_unit || 'lbs' }})</label
                                            >
                                            <input v-model="form.payload_capacity" type="number" min="0" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >GVWR ({{ form.weight_unit || 'lbs' }})</label
                                            >
                                            <input v-model="form.gvwr" type="number" min="0" :class="inputClass" />
                                        </div>
                                    </template>
                                    <template v-if="isTrailer">
                                        <div class="md:col-span-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                Trailer capacity
                                            </h3>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Weight capacity ({{ form.weight_unit || 'lbs' }})</label
                                            >
                                            <input v-model="form.weight_capacity" type="number" min="0" :class="inputClass" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Weight unit</label
                                            >
                                            <select v-model="form.weight_unit" :class="inputClass">
                                                <option v-for="o in weightUnits" :key="o.value" :value="o.value">
                                                    {{ o.label ?? o.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Axle count</label
                                            >
                                            <input v-model="form.axle_count" type="number" min="0" max="20" :class="inputClass" />
                                        </div>
                                    </template>
                                    <div v-if="isTruck">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Mileage</label
                                        >
                                        <input v-model="form.mileage" type="number" :class="inputClass" />
                                    </div>
                                    <div v-if="isTruck">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Engine / usage hours</label
                                        >
                                        <input v-model="form.hours" type="number" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Last maintenance</label
                                        >
                                        <input
                                            v-model="form.last_maintenance_at"
                                            type="date"
                                            :class="inputClass"
                                        />
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
                                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                                </button>
                                <Link
                                    :href="route('fleet.show', record.id)"
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
