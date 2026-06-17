<script setup>
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';

const props = defineProps({
    record: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    recordType: { type: String, default: 'locations' },
});

const emit = defineEmits(['saved', 'cancelled']);

const locationTypeEnumKey = 'App\\Enums\\Locations\\LocationType';
const timezoneEnumKey = 'App\\Enums\\Timezone';

const isEdit = computed(() => props.mode === 'edit' && props.record?.id);
const isCreate = computed(() => props.mode === 'create');

const initial = computed(() => props.record ?? {});

const { validationSubmitOptions } = useFormValidationToast(() => props.fieldsSchema);

const form = useForm({
    display_name: initial.value.display_name ?? '',
    location_type: initial.value.location_type ?? null,
    inactive: Boolean(initial.value.inactive),
    phone: initial.value.phone ?? '',
    email: initial.value.email ?? '',
    timezone: initial.value.timezone ?? (props.mode === 'create' ? props.account?.timezone ?? '' : ''),
    notes: initial.value.notes ?? '',
    manager_user_id: initial.value.manager_user_id ?? initial.value.manager_user?.id ?? initial.value.managerUser?.id ?? null,
    delivery_approver_user_id: initial.value.delivery_approver_user_id ?? initial.value.delivery_approver?.id ?? initial.value.deliveryApprover?.id ?? null,
    address_line_1: initial.value.address_line_1 ?? '',
    address_line_2: initial.value.address_line_2 ?? '',
    city: initial.value.city ?? '',
    state: initial.value.state ?? '',
    postal_code: initial.value.postal_code ?? '',
    country: initial.value.country ?? 'US',
    latitude: initial.value.latitude ?? null,
    longitude: initial.value.longitude ?? null,
});

const locationLabel = computed(() => {
    if (props.record?.display_name?.trim()) {
        return props.record.display_name.trim();
    }
    return isEdit.value ? `Location #${props.record.id}` : 'New location';
});

const locationTypeOptions = computed(() => props.enumOptions[locationTypeEnumKey] ?? []);
const timezoneOptions = computed(() => {
    const fromProps = props.timezones?.length ? props.timezones : props.enumOptions[timezoneEnumKey] ?? [];
    return fromProps.map((tz) => ({
        value: tz.value ?? tz.id,
        label: tz.name ?? tz.label ?? tz.value ?? tz.id,
    }));
});

const defaultLocationTypeId = computed(
    () => locationTypeOptions.value.find((t) => t.value === 'dealership')?.id
        ?? locationTypeOptions.value[0]?.id
        ?? 1,
);

if (isCreate.value && form.location_type == null) {
    form.location_type = defaultLocationTypeId.value;
}

const managerField = computed(() => ({ type: 'record', typeDomain: 'User', label: 'Manager' }));
const deliveryApproverField = computed(() => ({
    type: 'record',
    typeDomain: 'User',
    label: 'Delivery approver',
}));

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street ?? '';
    form.address_line_2 = data.unit ?? '';
    form.city = data.city ?? '';
    form.state = data.stateCode || data.state || '';
    form.postal_code = data.postalCode ?? '';
    form.country = data.countryCode || data.country || '';
    form.latitude = data.latitude ?? null;
    form.longitude = data.longitude ?? null;
};

const submit = () => {
    const url = isEdit.value
        ? route(`${props.recordType}.update`, props.record.id)
        : route(`${props.recordType}.store`);

    form.clearErrors();

    const options = validationSubmitOptions({
        onSuccess: (page) => {
            if (isEdit.value) {
                emit('saved', {});
                return;
            }
            emit('saved', {
                recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
            });
        },
    });

    if (isEdit.value) {
        form.put(url, options);
    } else {
        form.post(url, options);
    }
};
</script>

<template>
    <div class="w-full">
        <form class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-6" @submit.prevent="submit">
            <div
                class="relative overflow-hidden lg:col-span-12 sm:rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 shadow-lg dark:from-primary-700 dark:via-primary-800 dark:to-primary-950"
            >
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 h-full w-full">
                        <path
                            d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z"
                            fill="white"
                        />
                        <path
                            d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z"
                            fill="white"
                            opacity="0.5"
                        />
                    </svg>
                </div>
                <div class="relative flex flex-col gap-4 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold text-white">
                            {{ isEdit ? 'Edit location' : 'New location' }}
                        </h2>
                        <p class="max-w-xl text-sm text-primary-100">
                            {{
                                isEdit
                                    ? 'Update address, contact details, and delivery approval settings for this location.'
                                    : 'Add a physical location with address, contact info, and optional delivery approver.'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="isEdit"
                        class="rounded-lg bg-white/15 px-4 py-2.5 text-right backdrop-blur-sm"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-primary-100">Editing</p>
                        <p class="text-lg font-semibold text-white">{{ locationLabel }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 lg:col-span-5">
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Identity
                        </h3>
                    </header>
                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Location name <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.display_name" type="text" required class="input-style" />
                            <p v-if="form.errors.display_name" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.display_name }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Location type <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.location_type" required class="input-style">
                                <option v-for="opt in locationTypeOptions" :key="opt.id ?? opt.value" :value="opt.id ?? opt.value">
                                    {{ opt.name ?? opt.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.location_type" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.location_type }}
                            </p>
                        </div>
                        <label
                            class="flex cursor-pointer select-none items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-900/40"
                        >
                            <input
                                v-model="form.inactive"
                                type="checkbox"
                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-900 dark:text-white">Inactive</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Inactive locations are hidden from new assignments.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Management
                        </h3>
                    </header>
                    <div class="space-y-4 p-5">
                        <RecordSelect
                            v-model="form.manager_user_id"
                            :field="managerField"
                            :error="form.errors.manager_user_id"
                        />
                        <div>
                            <RecordSelect
                                v-model="form.delivery_approver_user_id"
                                :field="deliveryApproverField"
                                :error="form.errors.delivery_approver_user_id"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Notified when a delivery request starts from this location. Falls back to manager if empty.
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="input-style w-full" />
                            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-5 lg:col-span-7">
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Address
                        </h3>
                    </header>
                    <div class="p-5">
                        <AddressAutocomplete
                            :street="form.address_line_1"
                            :unit="form.address_line_2"
                            :city="form.city"
                            :state="form.state"
                            :postalCode="form.postal_code"
                            :country="form.country"
                            @update="onAddressUpdate"
                        />
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Contact
                        </h3>
                    </header>
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <input v-model="form.email" type="email" class="input-style" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                            <input v-model="form.phone" type="tel" class="input-style" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.phone }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                            <select v-model="form.timezone" class="input-style">
                                <option value="">—</option>
                                <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                            </select>
                            <p v-if="form.errors.timezone" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.timezone }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="emit('cancelled')"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <span class="material-icons text-base" :class="{ 'animate-spin': form.processing }">
                            {{ form.processing ? 'sync' : 'save' }}
                        </span>
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create location') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
