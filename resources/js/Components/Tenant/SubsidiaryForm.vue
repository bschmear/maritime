<script setup>
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    account: { type: Object, default: null },
    imageUrls: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    recordType: { type: String, default: 'subsidiaries' },
});

const emit = defineEmits(['saved', 'cancelled']);

const isEdit = computed(() => props.mode === 'edit' && props.record?.id);
const isCreate = computed(() => props.mode === 'create');
const logoPreview = ref(props.imageUrls?.logo ?? props.record?.logo_url ?? null);
const logoInputRef = ref(null);
const locationValidationError = ref('');

const timezoneEnumKey = 'App\\Enums\\Timezone';
const locationTypeEnumKey = 'App\\Enums\\Locations\\LocationType';

const initial = computed(() => props.record ?? {});

const form = useForm({
    display_name: initial.value.display_name ?? '',
    legal_name: initial.value.legal_name ?? '',
    inactive: initial.value.inactive ?? false,
    notes: initial.value.notes ?? '',
    email: initial.value.email ?? '',
    phone: initial.value.phone ?? '',
    website: initial.value.website ?? '',
    timezone: initial.value.timezone ?? (props.mode === 'create' ? props.account?.timezone ?? '' : ''),
    logo: null,
});

const subsidiaryLabel = computed(() => {
    if (props.record?.display_name?.trim()) {
        return props.record.display_name.trim();
    }
    return isEdit.value ? `Subsidiary #${props.record.id}` : 'New subsidiary';
});

const timezoneOptions = computed(() => {
    const fromProps = props.timezones?.length ? props.timezones : props.enumOptions[timezoneEnumKey] ?? [];
    return fromProps.map((tz) => ({
        value: tz.value ?? tz.id,
        label: tz.name ?? tz.label ?? tz.value ?? tz.id,
    }));
});

const locationTypeOptions = computed(() => props.enumOptions[locationTypeEnumKey] ?? []);

const defaultLocationTypeId = computed(
    () => locationTypeOptions.value.find((t) => t.value === 'dealership')?.id
        ?? locationTypeOptions.value[0]?.id
        ?? 1,
);

const emptyLocation = () => ({
    display_name: '',
    location_type: defaultLocationTypeId.value,
    email: '',
    phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'US',
    latitude: null,
    longitude: null,
});

const locationDrafts = ref([emptyLocation()]);

const addLocationDraft = () => {
    locationDrafts.value.push(emptyLocation());
};

const removeLocationDraft = (index) => {
    if (locationDrafts.value.length <= 1) {
        return;
    }
    locationDrafts.value.splice(index, 1);
};

const onLocationAddressUpdate = (index, data) => {
    const loc = locationDrafts.value[index];
    if (!loc) {
        return;
    }
    loc.address_line_1 = data.street ?? '';
    loc.address_line_2 = data.unit ?? '';
    loc.city = data.city ?? '';
    loc.state = data.stateCode || data.state || '';
    loc.postal_code = data.postalCode ?? '';
    loc.country = data.countryCode || data.country || '';
    loc.latitude = data.latitude ?? null;
    loc.longitude = data.longitude ?? null;
};

const locationFieldError = (index, field) => form.errors[`locations.${index}.${field}`];

const hasLogoUpload = computed(() => form.logo instanceof File);

const onLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.logo = file;
    if (file) {
        logoPreview.value = URL.createObjectURL(file);
    }
};

const clearLogo = () => {
    form.logo = null;
    logoPreview.value = null;
    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
};

const transformPayload = (data) => {
    const payload = {
        ...data,
        inactive: !!(data.inactive === true || data.inactive === 1 || data.inactive === '1'),
        timezone: data.timezone === '' ? null : data.timezone,
    };

    if (isCreate.value) {
        payload.locations = locationDrafts.value.map((loc) => ({
            display_name: loc.display_name?.trim() ?? '',
            location_type: Number(loc.location_type),
            email: loc.email?.trim() || null,
            phone: loc.phone?.trim() || null,
            address_line_1: loc.address_line_1?.trim() || null,
            address_line_2: loc.address_line_2?.trim() || null,
            city: loc.city?.trim() || null,
            state: loc.state?.trim() || null,
            postal_code: loc.postal_code?.trim() || null,
            country: loc.country?.trim() || null,
            latitude: loc.latitude ?? null,
            longitude: loc.longitude ?? null,
        }));
    }

    return payload;
};

const validateLocations = () => {
    locationValidationError.value = '';

    if (!isCreate.value) {
        return true;
    }

    if (!locationDrafts.value.length) {
        locationValidationError.value = 'Add at least one location.';
        return false;
    }

    for (let i = 0; i < locationDrafts.value.length; i++) {
        const loc = locationDrafts.value[i];
        if (!loc.display_name?.trim()) {
            locationValidationError.value = `Location ${i + 1} needs a name.`;
            return false;
        }
        if (!loc.location_type) {
            locationValidationError.value = `Location ${i + 1} needs a type.`;
            return false;
        }
    }

    return true;
};

const submit = () => {
    if (!validateLocations()) {
        return;
    }

    const url = isEdit.value
        ? route(`${props.recordType}.update`, props.record.id)
        : route(`${props.recordType}.store`);

    form.clearErrors();

    const options = {
        preserveScroll: true,
        onSuccess: (page) => {
            if (isEdit.value) {
                emit('saved', {});
                return;
            }
            emit('saved', {
                recordId: page.props.flash?.recordId ?? page.props.flash?.record_id,
            });
        },
    };

    if (isEdit.value && hasLogoUpload.value) {
        form.transform((data) => ({
            ...transformPayload(data),
            _method: 'put',
        })).post(url, {
            ...options,
            forceFormData: true,
        });
        return;
    }

    if (isEdit.value) {
        form.transform(transformPayload).put(url, options);
        return;
    }

    form.transform(transformPayload).post(url, {
        ...options,
        forceFormData: hasLogoUpload.value,
    });
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
                            {{ isEdit ? 'Edit subsidiary' : 'New subsidiary' }}
                        </h2>
                        <p class="max-w-xl text-sm text-primary-100">
                            {{
                                isEdit
                                    ? 'Update branding and contact details for this business unit.'
                                    : 'Set up a business unit with one or more physical locations and branding.'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="isEdit"
                        class="rounded-lg bg-white/15 px-4 py-2.5 text-right backdrop-blur-sm"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-primary-100">Editing</p>
                        <p class="text-lg font-semibold text-white">{{ subsidiaryLabel }}</p>
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
                                Subsidiary name <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.display_name" type="text" required class="input-style" />
                            <p v-if="form.errors.display_name" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.display_name }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Legal name</label>
                            <input v-model="form.legal_name" type="text" class="input-style" />
                            <p v-if="form.errors.legal_name" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.legal_name }}
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
                                    Inactive subsidiaries are hidden from new assignments.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Branding
                        </h3>
                    </header>
                    <div class="space-y-4 p-5">
                        <div v-if="logoPreview" class="relative inline-block">
                            <img
                                :src="logoPreview"
                                alt="Subsidiary logo preview"
                                class="h-24 w-auto max-w-full rounded-lg border border-gray-200 bg-white object-contain p-2 dark:border-gray-600"
                            />
                            <button
                                type="button"
                                class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white shadow hover:bg-red-600"
                                @click="clearLogo"
                            >
                                <span class="material-icons text-[16px]">close</span>
                            </button>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo</label>
                            <input
                                ref="logoInputRef"
                                type="file"
                                accept="image/*"
                                class="input-style"
                                @change="onLogoChange"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Used on service tickets and customer-facing documents. Max 2MB.
                            </p>
                            <p v-if="form.errors.logo" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.logo }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-5 lg:col-span-7">
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
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Website</label>
                            <input v-model="form.website" type="text" class="input-style" placeholder="https://" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                            <select v-model="form.timezone" class="input-style">
                                <option value="">Select timezone</option>
                                <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">
                                    {{ tz.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.timezone" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.timezone }}</p>
                        </div>
                    </div>
                </section>

                <section
                    v-if="isCreate"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Locations
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Add physical locations for this subsidiary. Each becomes a location record.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                @click="addLocationDraft"
                            >
                                <span class="material-icons text-[18px]">add</span>
                                Add location
                            </button>
                        </div>
                    </header>

                    <div class="space-y-4 p-5">
                        <p v-if="locationValidationError" class="text-sm text-red-600 dark:text-red-400">
                            {{ locationValidationError }}
                        </p>
                        <p v-if="form.errors.locations" class="text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.locations }}
                        </p>

                        <div
                            v-for="(loc, index) in locationDrafts"
                            :key="index"
                            class="rounded-xl border border-gray-200 bg-gray-50/80 dark:border-gray-600 dark:bg-gray-900/30"
                        >
                            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-600">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Location {{ index + 1 }}
                                </h4>
                                <button
                                    v-if="locationDrafts.length > 1"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                                    @click="removeLocationDraft(index)"
                                >
                                    <span class="material-icons text-[18px]">delete</span>
                                    Remove
                                </button>
                            </div>

                            <div class="space-y-4 p-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Location name <span class="text-red-500">*</span>
                                        </label>
                                        <input v-model="loc.display_name" type="text" class="input-style" />
                                        <p
                                            v-if="locationFieldError(index, 'display_name')"
                                            class="mt-1 text-xs text-red-600 dark:text-red-400"
                                        >
                                            {{ locationFieldError(index, 'display_name') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Location type <span class="text-red-500">*</span>
                                        </label>
                                        <select v-model.number="loc.location_type" class="input-style">
                                            <option v-for="t in locationTypeOptions" :key="t.id" :value="t.id">
                                                {{ t.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="locationFieldError(index, 'location_type')"
                                            class="mt-1 text-xs text-red-600 dark:text-red-400"
                                        >
                                            {{ locationFieldError(index, 'location_type') }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                                    <AddressAutocomplete
                                        :id="`subsidiary-location-address-${index}`"
                                        :street="loc.address_line_1"
                                        :unit="loc.address_line_2"
                                        :city="loc.city"
                                        :state="loc.state"
                                        :state-code="loc.state"
                                        :postal-code="loc.postal_code"
                                        :country="loc.country"
                                        :latitude="loc.latitude"
                                        :longitude="loc.longitude"
                                        @update="(data) => onLocationAddressUpdate(index, data)"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                        <input v-model="loc.email" type="email" class="input-style" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                                        <input v-model="loc.phone" type="tel" class="input-style" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50/50 dark:border-gray-600 dark:bg-gray-900/20"
                >
                    <div class="flex items-start gap-3 p-5">
                        <span class="material-icons text-xl text-gray-400">place</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Locations are managed separately</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Add or link locations from the subsidiary detail page after saving.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</h3>
                    </header>
                    <div class="p-5">
                        <textarea v-model="form.notes" rows="5" class="input-style resize-y min-h-[120px]" />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
                    </div>
                </section>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end lg:col-span-12">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    @click="emit('cancelled')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-transparent bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span class="material-icons text-[18px]" :class="{ 'animate-spin': form.processing }">
                        {{ form.processing ? 'sync' : 'save' }}
                    </span>
                    {{ form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create subsidiary' }}
                </button>
            </div>
        </form>
    </div>
</template>
