<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    onboarding: {
        type: Object,
        required: true,
    },
});

const step = ref(props.onboarding.initial_step ?? 1);
const totalSteps = 5;

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

const locationForm = useForm({
    subsidiary_id: props.onboarding.subsidiaries?.[0]?.id ?? null,
    display_name: '',
    email: '',
    phone: '',
});

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
            locationForm.reset('display_name', 'email', 'phone');
            step.value = 3;
            reloadOnboarding();
        },
    });
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
        onSuccess: () => {
            /* redirect handled server-side */
        },
    });
}

const canSubmitLocation = computed(() => locationForm.subsidiary_id && locationForm.display_name?.trim());
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
                    Welcome — set up your workspace
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Complete these steps to configure subsidiaries, locations, brands, payments, and branding.
                </p>
                <ol class="mt-4 flex flex-wrap gap-2">
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
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">Add a location</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Locations are physical sites. Link each location to the subsidiary it belongs to.
                    </p>
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
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="locationForm.processing || !canSubmitLocation"
                        @click="submitLocation"
                    >
                        Save and continue
                    </button>
                </div>

                <!-- Step 3 -->
                <div v-show="step === 3" class="space-y-4">
                    <h3 class="text-md font-medium text-gray-900 dark:text-white">What brands do you work with?</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Select manufacturers to add. Each uses a stable catalog key (slug) shared with the inventory database. You
                        can manage brands anytime under Brands.
                    </p>
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
                        Save and go to Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
