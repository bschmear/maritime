<script setup>
import FleetFormStatusPanel from '@/Components/Tenant/FleetFormStatusPanel.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed } from 'vue';

const props = defineProps({
    fleetType: {
        type: String,
        required: true,
        validator: (v) => ['truck', 'trailer'].includes(v),
    },
    initialLocationId: {
        type: [Number, String, null],
        default: null,
    },
    initialLocation: {
        type: Object,
        default: null,
    },
    lockLocation: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['created', 'cancelled']);

const statuses = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'maintenance', label: 'In maintenance' },
];

const typeLabel = computed(() => (props.fleetType === 'truck' ? 'Truck' : 'Trailer'));

const locationRecordField = Object.freeze({
    type: 'record',
    typeDomain: 'Location',
    label: 'Location',
    create: true,
});

const recordForSelect = computed(() => {
    const locId = props.initialLocationId != null && props.initialLocationId !== ''
        ? Number(props.initialLocationId)
        : null;

    if (!locId) {
        return {};
    }

    return {
        location_id: locId,
        location: props.initialLocation
            ? { id: locId, display_name: props.initialLocation.display_name ?? props.initialLocation.name }
            : { id: locId, display_name: `Location #${locId}` },
    };
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
    location_id: props.initialLocationId != null && props.initialLocationId !== ''
        ? Number(props.initialLocationId)
        : null,
    status: 'active',
    weight_unit: 'lbs',
});

const inputClass =
    'mt-1 block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white';

const normalizePayload = (data) => {
    const n = { ...data };
    ['location_id', 'year'].forEach((k) => {
        if (n[k] === '' || n[k] === undefined) {
            n[k] = null;
        }
    });
    if (n.location_id !== null) {
        n.location_id = Number(n.location_id);
    }
    return n;
};

const submit = async () => {
    form.clearErrors();
    const payload = normalizePayload(form.data());

    try {
        const { data } = await axios.post(route('fleet.store'), payload, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (data?.success === false) {
            form.errors = data.errors ?? { general: data.message ?? 'Failed to save.' };

            return;
        }

        const recordId = data?.recordId ?? data?.record?.id;
        if (recordId == null) {
            form.errors = { general: 'Fleet item was saved but the server did not return an id.' };

            return;
        }

        emit('created', recordId, data.record ?? null);
    } catch (error) {
        if (error.response?.status === 422) {
            form.errors = error.response.data?.errors ?? {};
        } else {
            form.errors = {
                general: error.response?.data?.message ?? error.message ?? 'Failed to save.',
            };
        }
    }
};
</script>

<template>
    <div class="p-4 sm:p-6 space-y-5">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Add a new {{ typeLabel.toLowerCase() }} to your fleet. It will be available for this delivery once created.
        </p>

        <FleetFormStatusPanel
            v-model="form.status"
            :statuses="statuses"
            :error="form.errors.status"
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Name <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.display_name"
                    type="text"
                    required
                    :class="inputClass"
                    :placeholder="fleetType === 'truck' ? 'e.g. Service Truck 1' : 'e.g. Enclosed trailer A'"
                />
                <p v-if="form.errors.display_name" class="mt-1 text-xs text-red-500 dark:text-red-400">
                    {{ form.errors.display_name }}
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">License plate</label>
                <input v-model="form.license_plate" type="text" :class="inputClass" />
                <p v-if="form.errors.license_plate" class="mt-1 text-xs text-red-500 dark:text-red-400">
                    {{ form.errors.license_plate }}
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                <p
                    v-if="lockLocation && form.location_id"
                    class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-200"
                >
                    {{ recordForSelect.location?.display_name ?? `Location #${form.location_id}` }}
                </p>
                <RecordSelect
                    v-else
                    id="fleet_embedded_location_id"
                    :field="locationRecordField"
                    v-model="form.location_id"
                    :record="recordForSelect"
                    field-key="location_id"
                />
                <p v-if="form.errors.location_id" class="mt-1 text-xs text-red-500 dark:text-red-400">
                    {{ form.errors.location_id }}
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Make</label>
                <input v-model="form.make" type="text" :class="inputClass" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
                <input v-model="form.model" type="text" :class="inputClass" />
            </div>
        </div>

        <p v-if="form.errors.general" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.general }}</p>

        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 dark:border-gray-700 sm:flex-row sm:justify-end">
            <button
                type="button"
                class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                @click="emit('cancelled')"
            >
                Back to list
            </button>
            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex justify-center items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg border border-transparent disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                @click="submit"
            >
                <span class="material-icons text-[18px]" :class="{ 'animate-spin': form.processing }">
                    {{ form.processing ? 'sync' : 'save' }}
                </span>
                {{ form.processing ? 'Saving…' : `Create ${typeLabel.toLowerCase()}` }}
            </button>
        </div>
    </div>
</template>
