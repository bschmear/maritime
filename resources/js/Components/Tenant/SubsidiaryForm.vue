<script setup>
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
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
const logoFile = ref(null);
const logoPreview = ref(props.imageUrls?.logo ?? props.record?.logo_url ?? null);

const timezoneEnumKey = 'App\\Enums\\Timezone';

const initial = computed(() => props.record ?? {});

const form = useForm({
    display_name: initial.value.display_name ?? '',
    legal_name: initial.value.legal_name ?? '',
    inactive: initial.value.inactive ?? false,
    notes: initial.value.notes ?? '',
    email: initial.value.email ?? '',
    phone: initial.value.phone ?? '',
    website: initial.value.website ?? '',
    timezone: initial.value.timezone ?? '',
    address_line_1: initial.value.address_line_1 ?? '',
    address_line_2: initial.value.address_line_2 ?? '',
    city: initial.value.city ?? '',
    state: initial.value.state ?? '',
    postal_code: initial.value.postal_code ?? '',
    country: initial.value.country ?? 'US',
    latitude: initial.value.latitude ?? null,
    longitude: initial.value.longitude ?? null,
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

const onLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    logoFile.value = file;
    if (file) {
        logoPreview.value = URL.createObjectURL(file);
    }
};

const clearLogo = () => {
    logoFile.value = null;
    logoPreview.value = null;
    form.logo = null;
};

const preparePayload = () => {
    const data = { ...form.data() };
    data.inactive = !!(data.inactive === true || data.inactive === 1 || data.inactive === '1');
    if (data.timezone === '') {
        data.timezone = null;
    }
    if (logoFile.value) {
        data.logo = logoFile.value;
    }
    return data;
};

const submit = () => {
    const url = isEdit.value
        ? route(`${props.recordType}.update`, props.record.id)
        : route(`${props.recordType}.store`);

    form.clearErrors();
    const payload = preparePayload();
    Object.keys(payload).forEach((key) => {
        form[key] = payload[key];
    });

    const options = {
        preserveScroll: true,
        forceFormData: !!logoFile.value,
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

    if (isEdit.value) {
        if (logoFile.value) {
            form.post(url, { ...options, _method: 'put' });
        } else {
            form.put(url, options);
        }
    } else {
        form.post(url, { ...options, forceFormData: !!logoFile.value });
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
                            {{ isEdit ? 'Edit subsidiary' : 'New subsidiary' }}
                        </h2>
                        <p class="max-w-xl text-sm text-primary-100">
                            {{
                                isEdit
                                    ? 'Update branding and contact details for this business unit.'
                                    : 'Set up a business unit with its own address and branding.'
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

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Physical location for this subsidiary.</p>
                    </header>
                    <div class="min-h-[220px] p-5">
                        <AddressAutocomplete
                            :street="form.address_line_1"
                            :unit="form.address_line_2"
                            :city="form.city"
                            :state="form.state"
                            :state-code="form.state"
                            :postal-code="form.postal_code"
                            :country="form.country"
                            :latitude="form.latitude"
                            :longitude="form.longitude"
                            @update="onAddressUpdate"
                        />
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
