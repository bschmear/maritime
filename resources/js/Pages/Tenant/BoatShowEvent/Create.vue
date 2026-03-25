<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, required: true },
    initialData: { type: Object, default: () => ({}) },
    extraRouteParams: { type: Object, default: () => ({}) },
    parentBoatShow: { type: Object, default: null },
    /** Global create only: pick parent show */
    boatShowOptions: { type: Array, default: () => [] },
});

const isNested = computed(() => props.parentBoatShow !== null);

const form = useForm({
    boat_show_id: props.initialData.boat_show_id ?? null,
    display_name: '',
    year: new Date().getFullYear(),
    starts_at: '',
    ends_at: '',
    venue: '',
    booth: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    latitude: '',
    longitude: '',
    active: 1,
});

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street ?? '';
    form.address_line_2 = data.unit ?? '';
    form.city = data.city ?? '';
    form.state = data.state || data.stateCode || '';
    form.country = data.country ?? '';
    form.postal_code = data.postalCode ?? '';
    form.latitude = data.latitude != null && data.latitude !== '' ? data.latitude : '';
    form.longitude = data.longitude != null && data.longitude !== '' ? data.longitude : '';
};

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    if (props.parentBoatShow) {
        items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
        items.push({
            label: props.parentBoatShow.name,
            href: route('boat-shows.show', props.parentBoatShow.routeKey),
        });
        items.push({
            label: 'Events',
            href: route('boat-shows.events.index', props.extraRouteParams),
        });
    } else {
        items.push({ label: 'Boat Show Events', href: route('boat-show-events.index') });
    }

    items.push({ label: 'Create' });
    return items;
});

const cancelHref = computed(() =>
    props.parentBoatShow
        ? route('boat-shows.show', props.parentBoatShow.routeKey)
        : route('boat-show-events.index')
);

const boatShowShowHref = computed(() =>
    props.parentBoatShow ? route('boat-shows.show', props.parentBoatShow.routeKey) : null
);

const storeUrl = computed(() => route(`${props.recordType}.store`, props.extraRouteParams));

const submit = () => {
    if (!isNested.value && (form.boat_show_id === null || form.boat_show_id === '')) {
        form.setError('boat_show_id', 'Select a boat show.');
        return;
    }

    form
        .transform((data) => {
            const out = { ...data };
            out.active = out.active === true || out.active === 1 || out.active === '1' ? 1 : 0;
            if (out.starts_at === '') {
                out.starts_at = null;
            }
            if (out.ends_at === '') {
                out.ends_at = null;
            }
            if (out.boat_show_id != null && out.boat_show_id !== '') {
                out.boat_show_id = Number(out.boat_show_id);
            }
            const numOrNull = (v) => {
                if (v === '' || v === null || v === undefined) {
                    return null;
                }
                const n = Number(v);

                return Number.isFinite(n) ? n : null;
            };
            out.latitude = numOrNull(out.latitude);
            out.longitude = numOrNull(out.longitude);

            return out;
        })
        .post(storeUrl.value, {
            preserveScroll: true,
        });
};

const fieldError = (key) => {
    const err = form.errors[key];
    return Array.isArray(err) ? err[0] : err;
};
</script>

<template>
    <Head title="Create Boat Show Event" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            New boat show event
                        </h2>
                        <p
                            v-if="parentBoatShow"
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            For
                            <Link
                                :href="boatShowShowHref"
                                class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                {{ parentBoatShow.name }}
                            </Link>
                        </p>
                    </div>
                    <Link
                        v-if="boatShowShowHref"
                        :href="boatShowShowHref"
                        class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-[18px]">arrow_back</span>
                        Back to boat show
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full max-w-4xl mx-auto p-4 space-y-6">
            <form class="space-y-8" @submit.prevent="submit">
                <!-- Parent boat show -->
                <div
                    v-if="isNested"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Boat show
                    </p>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ parentBoatShow.name }}
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Events are created for this show. To use a different show, start from that show’s page.
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <label for="boat_show_id" class="block text-sm font-bold text-gray-900 dark:text-white">
                        Boat show <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="boat_show_id"
                        v-model="form.boat_show_id"
                        required
                        class="input-style mt-2 w-full max-w-xl"
                        :class="{ 'ring-2 ring-red-500': fieldError('boat_show_id') }"
                    >
                        <option disabled :value="null">Select a boat show</option>
                        <option
                            v-for="opt in boatShowOptions"
                            :key="opt.id"
                            :value="opt.id"
                        >
                            {{ opt.name }}
                        </option>
                    </select>
                    <p v-if="fieldError('boat_show_id')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('boat_show_id') }}
                    </p>
                    <p
                        v-if="!boatShowOptions.length"
                        class="mt-2 text-sm text-amber-700 dark:text-amber-400"
                    >
                        No boat shows exist yet. Create a boat show first, then add events from its page.
                    </p>
                </div>

                <!-- Event details -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
                    <h3 class="mb-6 text-base font-semibold text-gray-900 dark:text-white">
                        Event details
                    </h3>
                    <div class="grid gap-6 sm:grid-cols-12">
                        <div class="sm:col-span-12">
                            <label for="display_name" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Display name <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="display_name"
                                v-model="form.display_name"
                                type="text"
                                required
                                maxlength="255"
                                class="input-style w-full"
                                placeholder="e.g. Miami 2026"
                            />
                            <p v-if="fieldError('display_name')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('display_name') }}
                            </p>
                        </div>

                        <div class="sm:col-span-4">
                            <label for="year" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Year <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="year"
                                v-model.number="form.year"
                                type="number"
                                required
                                min="2000"
                                max="2100"
                                step="1"
                                class="input-style w-full"
                            />
                            <p v-if="fieldError('year')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('year') }}
                            </p>
                        </div>

                        <div class="sm:col-span-4">
                            <label for="starts_at" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Starts
                            </label>
                            <DateInput id="starts_at" v-model="form.starts_at" />
                            <p v-if="fieldError('starts_at')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('starts_at') }}
                            </p>
                        </div>

                        <div class="sm:col-span-4">
                            <label for="ends_at" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Ends
                            </label>
                            <DateInput id="ends_at" v-model="form.ends_at" />
                            <p v-if="fieldError('ends_at')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('ends_at') }}
                            </p>
                        </div>

                        <div class="sm:col-span-6">
                            <label for="venue" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Venue
                            </label>
                            <input
                                id="venue"
                                v-model="form.venue"
                                type="text"
                                maxlength="255"
                                class="input-style w-full"
                            />
                            <p v-if="fieldError('venue')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('venue') }}
                            </p>
                        </div>

                        <div class="sm:col-span-6">
                            <label for="booth" class="block mb-2 text-sm font-bold text-gray-900 dark:text-white">
                                Booth
                            </label>
                            <input
                                id="booth"
                                v-model="form.booth"
                                type="text"
                                maxlength="255"
                                class="input-style w-full"
                            />
                            <p v-if="fieldError('booth')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('booth') }}
                            </p>
                        </div>

                        <div class="sm:col-span-12">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/50">
                                <input
                                    v-model="form.active"
                                    type="checkbox"
                                    :true-value="1"
                                    :false-value="0"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Active</span>
                            </label>
                            <p v-if="fieldError('active')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                                {{ fieldError('active') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Venue address (Radar) -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
                    <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                        Venue address
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(optional)</span>
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Search for the street address; city, state, postal code, and map coordinates are filled automatically.
                    </p>
                    <AddressAutocomplete
                        id="boat-show-event-create-address"
                        :street="form.address_line_1"
                        :unit="form.address_line_2"
                        :city="form.city"
                        :state="form.state"
                        :postal-code="form.postal_code"
                        :country="form.country"
                        :latitude="form.latitude"
                        :longitude="form.longitude"
                        :disabled="form.processing"
                        @update="onAddressUpdate"
                    />
                    <p v-if="fieldError('address_line_1')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('address_line_1') }}
                    </p>
                    <p v-if="fieldError('city')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('city') }}</p>
                    <p v-if="fieldError('state')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('state') }}</p>
                    <p v-if="fieldError('country')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('country') }}</p>
                    <p v-if="fieldError('postal_code')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('postal_code') }}</p>
                    <p v-if="fieldError('latitude')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('latitude') }}</p>
                    <p v-if="fieldError('longitude')" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ fieldError('longitude') }}</p>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :disabled="form.processing"
                        @click="router.visit(cancelHref)"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing || (!isNested && !boatShowOptions.length)"
                    >
                        {{ form.processing ? 'Saving…' : 'Create event' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
