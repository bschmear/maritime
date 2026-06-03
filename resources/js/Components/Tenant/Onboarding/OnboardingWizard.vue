<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';

const props = defineProps({
    onboarding: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['completed']);

const step = ref(props.onboarding.initial_step ?? 1);
const totalSteps = 5;
const completionStep = 6;

watch(
    () => props.onboarding.initial_step,
    (v) => {
        if (typeof v === 'number' && v >= 1 && v <= totalSteps) {
            step.value = v;
        }
    }
);

const stepLabels = ['Subsidiary', 'Locations', 'Brands', 'Stripe', 'Branding'];

const subsidiaryForm = useForm({
    display_name: '',
});

const defaultLocationTypeId = computed(
    () =>
        props.onboarding.location_types?.find((t) => t.value === 'dealership')?.id
        ?? props.onboarding.location_types?.[0]?.id
        ?? 1,
);

const locationForm = useForm({
    subsidiary_id: props.onboarding.subsidiaries?.[0]?.id ?? null,
    display_name: '',
    location_type: defaultLocationTypeId.value,
    email: '',
    phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    latitude: null,
    longitude: null,
});

const addedLocations = computed(() => props.onboarding.locations ?? []);
const hasLocations = computed(() => addedLocations.value.length > 0);

watch(
    () => props.onboarding.subsidiaries,
    (subs) => {
        if (subs?.length && !locationForm.subsidiary_id) {
            locationForm.subsidiary_id = subs[0].id;
        }
    },
    { deep: true }
);

const manufacturerSearch = ref('');
const selectedBrandSlugs = ref([]);

const existingBrandKeySet = computed(() => new Set(props.onboarding.existingBrandKeys ?? []));

const filteredManufacturers = computed(() => {
    const list = props.onboarding.manufacturers ?? [];
    const q = manufacturerSearch.value.trim().toLowerCase();
    if (!q) {
        return list;
    }
    return list.filter((m) => {
        const name = (m.display_name ?? '').toLowerCase();
        const slug = (m.slug ?? '').toLowerCase();

        return name.includes(q) || slug.includes(q);
    });
});

function isBrandAlreadyAdded(slug) {
    return existingBrandKeySet.value.has(slug);
}

const manufacturerBySlug = computed(() => {
    const map = {};
    for (const m of props.onboarding.manufacturers ?? []) {
        map[m.slug] = m;
    }
    return map;
});

const pendingSelectedBrands = computed(() =>
    selectedBrandSlugs.value
        .filter((slug) => !isBrandAlreadyAdded(slug))
        .map((slug) => ({
            slug,
            display_name: manufacturerBySlug.value[slug]?.display_name ?? slug,
        }))
);

function removeSelectedBrand(slug) {
    selectedBrandSlugs.value = selectedBrandSlugs.value.filter((s) => s !== slug);
}

function onLocationAddressUpdate(data) {
    locationForm.address_line_1 = data.street || '';
    locationForm.address_line_2 = data.unit || '';
    locationForm.city = data.city || '';
    locationForm.state = data.stateCode || data.state || '';
    locationForm.postal_code = data.postalCode || '';
    locationForm.country = data.countryCode || data.country || '';
    locationForm.latitude = data.latitude ?? null;
    locationForm.longitude = data.longitude ?? null;
}

const finalizeForm = useForm({
    logo: null,
    default_timezone: props.onboarding.default_timezone ?? 'America/Chicago',
    brand_color: props.onboarding.default_brand_color ?? '#3B82F6',
});

watch(
    () => props.onboarding.default_timezone,
    (v) => {
        if (v) {
            finalizeForm.default_timezone = v;
        }
    }
);

watch(
    () => props.onboarding.default_brand_color,
    (v) => {
        if (v) {
            finalizeForm.brand_color = v;
        }
    }
);

function reloadOnboarding() {
    router.reload({ only: ['onboarding'] });
}

function submitSubsidiary() {
    subsidiaryForm.post(route('onboarding.subsidiary'), {
        preserveScroll: true,
        onSuccess: () => {
            subsidiaryForm.reset();
            step.value = 2;
            reloadOnboarding();
        },
    });
}

function submitLocation() {
    locationForm.post(route('onboarding.location'), {
        preserveScroll: true,
        onSuccess: () => {
            locationForm.reset(
                'display_name',
                'email',
                'phone',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
                'latitude',
                'longitude'
            );
            locationForm.location_type = defaultLocationTypeId.value;
            reloadOnboarding();
        },
    });
}

function continueFromLocations() {
    if (!hasLocations.value) {
        return;
    }
    step.value = 3;
}

function submitBrands() {
    const keys = selectedBrandSlugs.value.filter((slug) => !isBrandAlreadyAdded(slug));
    router.post(
        route('onboarding.brands'),
        { brand_keys: keys },
        {
            preserveScroll: true,
            onSuccess: () => {
                step.value = 4;
                reloadOnboarding();
            },
        }
    );
}

function skipBrands() {
    router.post(
        route('onboarding.brands'),
        { brand_keys: [] },
        {
            preserveScroll: true,
            onSuccess: () => {
                step.value = 4;
                reloadOnboarding();
            },
        }
    );
}

function openStripeConnect() {
    window.location.href = props.onboarding.stripe_connect_from_onboarding_url;
}

function skipStripe() {
    step.value = 5;
}

function onLogoChange(e) {
    const file = e.target.files?.[0];
    finalizeForm.logo = file || null;
}

function submitFinalize() {
    finalizeForm.post(route('onboarding.finalize'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            step.value = completionStep;
        },
    });
}

function finishOnboarding() {
    emit('completed');
    router.visit(route('account.index'));
}

const canSubmitLocation = computed(
    () =>
        locationForm.subsidiary_id
        && locationForm.display_name?.trim()
        && locationForm.location_type != null
        && locationForm.location_type !== '',
);
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div
            class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
            role="dialog"
            aria-modal="true"
            aria-labelledby="onboarding-title"
        >
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 id="onboarding-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ step === completionStep ? 'Setup complete' : 'Welcome — set up your workspace' }}
                </h2>
                <p v-if="step !== completionStep" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Complete these steps to configure subsidiaries, locations, brands, payments, and branding.
                </p>
                <ol v-if="step !== completionStep" class="mt-4 flex flex-wrap gap-2">
                    <li
                        v-for="n in totalSteps"
                        :key="n"
                        class="flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            step === n
                                ? 'bg-primary-600 text-white'
                                : step > n
                                  ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                  : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        "
                    >
                        <span class="tabular-nums">{{ n }}</span>
                        <span>{{ stepLabels[n - 1] }}</span>
                    </li>
                </ol>
            </div>

            <div class="px-6 py-5">
                <!-- Step 1 -->
                <div v-show="step === 1" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">Add a subsidiary</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        A subsidiary represents a legal entity or division you operate under (used for reporting, invoices, and
                        locations).
                    </p>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Display name</label>
                        <input
                            v-model="subsidiaryForm.display_name"
                            type="text"
                            class="input-style w-full"
                            autocomplete="organization"
                        >
                        <p v-if="subsidiaryForm.errors.display_name" class="mt-1 text-sm text-red-600">
                            {{ subsidiaryForm.errors.display_name }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="subsidiaryForm.processing || !subsidiaryForm.display_name?.trim()"
                        @click="submitSubsidiary"
                    >
                        Save and continue
                    </button>
                </div>

                <!-- Step 2 -->
                <div v-show="step === 2" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">Add locations</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Add every physical site you operate. Each location is linked to a subsidiary and has a type
                        (dealership, marina, service center, and so on).
                    </p>

                    <div
                        v-if="addedLocations.length"
                        class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50"
                    >
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Added locations ({{ addedLocations.length }})
                        </p>
                        <ul class="space-y-2">
                            <li
                                v-for="loc in addedLocations"
                                :key="loc.id"
                                class="flex flex-wrap items-baseline justify-between gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"
                            >
                                <div class="min-w-0">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ loc.display_name }}</span>
                                    <p
                                        v-if="loc.address_summary"
                                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ loc.address_summary }}
                                    </p>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                                    <span v-if="loc.location_type_label">{{ loc.location_type_label }}</span>
                                    <span v-if="loc.location_type_label && loc.subsidiary_labels"> · </span>
                                    <span v-if="loc.subsidiary_labels">{{ loc.subsidiary_labels }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subsidiary</label>
                        <select v-model.number="locationForm.subsidiary_id" class="input-style w-full">
                            <option v-for="s in onboarding.subsidiaries" :key="s.id" :value="s.id">
                                {{ s.label }}
                            </option>
                        </select>
                        <p v-if="locationForm.errors.subsidiary_id" class="mt-1 text-sm text-red-600">
                            {{ locationForm.errors.subsidiary_id }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Location name</label>
                        <input v-model="locationForm.display_name" type="text" class="input-style w-full" required>
                        <p v-if="locationForm.errors.display_name" class="mt-1 text-sm text-red-600">
                            {{ locationForm.errors.display_name }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Location type</label>
                        <select v-model.number="locationForm.location_type" class="input-style w-full" required>
                            <option v-for="t in onboarding.location_types" :key="t.id" :value="t.id">
                                {{ t.name }}
                            </option>
                        </select>
                        <p v-if="locationForm.errors.location_type" class="mt-1 text-sm text-red-600">
                            {{ locationForm.errors.location_type }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                        <AddressAutocomplete
                            id="onboarding-location-address"
                            :street="locationForm.address_line_1"
                            :unit="locationForm.address_line_2"
                            :city="locationForm.city"
                            :state="locationForm.state"
                            :stateCode="locationForm.state"
                            :postalCode="locationForm.postal_code"
                            :country="locationForm.country"
                            :latitude="locationForm.latitude"
                            :longitude="locationForm.longitude"
                            @update="onLocationAddressUpdate"
                        />
                        <p v-if="locationForm.errors.address_line_1" class="mt-1 text-sm text-red-600">
                            {{ locationForm.errors.address_line_1 }}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email (optional)</label>
                            <input v-model="locationForm.email" type="email" class="input-style w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone (optional)</label>
                            <input v-model="locationForm.phone" type="tel" class="input-style w-full">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="locationForm.processing || !canSubmitLocation"
                            @click="submitLocation"
                        >
                            Add location
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            :disabled="!hasLocations"
                            @click="continueFromLocations"
                        >
                            Continue to brands
                        </button>
                    </div>
                    <p v-if="!hasLocations" class="text-xs text-gray-500 dark:text-gray-400">
                        Add at least one location before continuing.
                    </p>
                </div>

                <!-- Step 3 -->
                <div v-show="step === 3" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">What brands do you work with?</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Select manufacturers to add. Each uses a stable catalog key (slug) shared with the inventory database. You
                        can manage brands anytime under Brands.
                    </p>
                    <div
                        v-if="pendingSelectedBrands.length"
                        class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800/50 dark:bg-primary-950/30"
                    >
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-primary-800 dark:text-primary-200">
                            Selected brands ({{ pendingSelectedBrands.length }})
                        </p>
                        <ul class="flex flex-wrap gap-2">
                            <li
                                v-for="brand in pendingSelectedBrands"
                                :key="brand.slug"
                                class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-white py-1 pl-3 pr-1 text-sm text-gray-900 dark:border-primary-700 dark:bg-gray-900 dark:text-white"
                            >
                                <span>{{ brand.display_name }}</span>
                                <button
                                    type="button"
                                    class="rounded-full p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                    :aria-label="`Remove ${brand.display_name}`"
                                    @click="removeSelectedBrand(brand.slug)"
                                >
                                    <span class="sr-only">Remove</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input
                            v-model="manufacturerSearch"
                            type="search"
                            class="input-style w-full"
                            placeholder="Filter by name or slug…"
                            autocomplete="off"
                        >
                    </div>
                    <div
                        class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-600"
                    >
                        <label
                            v-for="m in filteredManufacturers"
                            :key="m.slug"
                            class="flex cursor-pointer items-center gap-2 py-1.5 text-sm text-gray-800 dark:text-gray-200"
                            :class="isBrandAlreadyAdded(m.slug) ? 'cursor-not-allowed opacity-60' : ''"
                        >
                            <input
                                v-model="selectedBrandSlugs"
                                type="checkbox"
                                class="rounded border-gray-300"
                                :value="m.slug"
                                :disabled="isBrandAlreadyAdded(m.slug)"
                            >
                            <span>{{ m.display_name }}</span>
                            <span class="ml-auto font-mono text-xs text-gray-400">{{ m.slug }}</span>
                        </label>
                        <p v-if="!(onboarding.manufacturers?.length)" class="text-sm text-gray-500">No manufacturers in the catalog.</p>
                        <p
                            v-else-if="!filteredManufacturers.length"
                            class="text-sm text-gray-500"
                        >
                            No matches for that search.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="submitBrands"
                        >
                            Save and continue
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="skipBrands"
                        >
                            Skip
                        </button>
                    </div>
                </div>

                <!-- Step 4 -->
                <div v-show="step === 4" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">Configure Stripe</h3>
                    <div
                        v-if="onboarding.stripe_just_returned"
                        class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                    >
                        Stripe saved your progress. You can continue to the next step, or open Stripe again if you need to finish
                        anything else.
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Stripe is a payments company used to accept credit cards, ACH, and other methods securely. When you connect,
                        customer invoice payments can be deposited to your business according to Stripe’s rules.
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        You will leave this page briefly to complete Stripe’s onboarding, then return here automatically.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="onboarding.stripe_just_returned"
                            type="button"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                            @click="step = 5"
                        >
                            Continue to branding
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            @click="openStripeConnect"
                        >
                            Connect with Stripe
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="skipStripe"
                        >
                            Skip for now
                        </button>
                    </div>
                </div>

                <!-- Step 5 -->
                <div v-show="step === 5" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">Logo, timezone, and brand color</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        These appear across your tenant experience. You can change them anytime under Account settings.
                    </p>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo (optional)</label>
                        <input type="file" accept="image/*" class="text-sm" @change="onLogoChange">
                        <p v-if="finalizeForm.errors.logo" class="mt-1 text-sm text-red-600">{{ finalizeForm.errors.logo }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                        <select v-model="finalizeForm.default_timezone" class="input-style w-full">
                            <option v-for="tz in onboarding.timezones" :key="tz.id" :value="tz.id">
                                {{ tz.name }}
                            </option>
                        </select>
                        <p v-if="finalizeForm.errors.default_timezone" class="mt-1 text-sm text-red-600">
                            {{ finalizeForm.errors.default_timezone }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Brand color</label>
                        <div class="flex items-center gap-3">
                            <input v-model="finalizeForm.brand_color" type="color" class="h-10 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600">
                            <input v-model="finalizeForm.brand_color" type="text" class="input-style max-w-[10rem] font-mono text-sm">
                        </div>
                        <p v-if="finalizeForm.errors.brand_color" class="mt-1 text-sm text-red-600">
                            {{ finalizeForm.errors.brand_color }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="finalizeForm.processing"
                        @click="submitFinalize"
                    >
                        Finish setup
                    </button>
                </div>

                <!-- Completion -->
                <div v-show="step === completionStep" class="space-y-5">
                    <div class="flex flex-col items-center text-center sm:px-4">
                        <div
                            class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40"
                            aria-hidden="true"
                        >
                            <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Success — you&apos;re all set to get started
                        </h3>
                        <p class="mt-2 max-w-md text-sm text-gray-600 dark:text-gray-300">
                            Your workspace setup is complete. We highly recommend testing in
                            <strong class="font-semibold text-gray-800 dark:text-white">sandbox mode</strong>
                            before you go live with customers.
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700/80 dark:bg-amber-950/40 dark:text-amber-100"
                        role="status"
                    >
                        <p class="font-semibold">Sandbox mode</p>
                        <p class="mt-1.5 leading-relaxed">
                            While sandbox mode is on, emails and text messages are routed back to you (the signed-in user)
                            instead of the customer&apos;s real address or phone number. Use this to try invoices, estimates,
                            deliveries, and notifications safely. Turn sandbox off under
                            <strong>Account → General Account Settings</strong> when you are ready to contact customers for real.
                        </p>
                    </div>

                    <div class="flex justify-center pt-2">
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            @click="finishOnboarding"
                        >
                            Get started
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
