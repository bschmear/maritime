<script setup>
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';

function parseLocalDate(ymd) {
    if (!ymd || typeof ymd !== 'string' || ymd.length < 10) {
        return null;
    }
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) {
        return null;
    }

    return new Date(y, m - 1, d);
}

function formatYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/** Sunday on or after the start date (typical boat show end day). */
function sundayOnOrAfterStart(ymd) {
    const start = parseLocalDate(ymd);
    if (!start) {
        return '';
    }
    const day = start.getDay();
    const daysUntilSunday = day === 0 ? 0 : 7 - day;
    start.setDate(start.getDate() + daysUntilSunday);

    return formatYmd(start);
}

const props = defineProps({
    form: { type: Object, required: true },
    fieldError: { type: Function, required: true },
    isNested: { type: Boolean, default: false },
    parentBoatShow: { type: Object, default: null },
    boatShowOptions: { type: Array, default: () => [] },
    recipientUserOptions: { type: Array, default: () => [] },
    /** 'create' | 'edit' — only create shows boat show picker when not nested */
    mode: { type: String, default: 'create' },
    addressFieldId: { type: String, default: 'boat-show-event-address' },
});

const boatShowField = computed(() => ({
    type: 'record',
    typeDomain: 'BoatShow',
    label: 'Boat Show',
    relationship: 'show',
    required: true,
    create: true,
}));

const pseudoRecord = computed(() => {
    if (!props.parentBoatShow) {
        return null;
    }

    return {
        boat_show_id: props.parentBoatShow.id,
        show: {
            id: props.parentBoatShow.id,
            display_name: props.parentBoatShow.name,
        },
    };
});

const boatShowEnumOptions = computed(() =>
    (props.boatShowOptions ?? []).map((opt) => ({
        id: opt.id,
        name: opt.name,
        display_name: opt.name,
    })),
);

function yearFromDate(ymd) {
    const date = parseLocalDate(ymd);

    return date ? date.getFullYear() : null;
}

function syncYearFromDates() {
    const startYear = yearFromDate(props.form.starts_at);
    const endYear = yearFromDate(props.form.ends_at);

    if (startYear !== null) {
        props.form.year = startYear;
    } else if (endYear !== null) {
        props.form.year = endYear;
    } else {
        props.form.year = '';
    }
}

watch(
    () => props.form.starts_at,
    (start) => {
        if (!start) {
            props.form.ends_at = '';
            syncYearFromDates();
            return;
        }
        syncYearFromDates();
        props.form.ends_at = sundayOnOrAfterStart(start);
    },
);

watch(
    () => props.form.ends_at,
    (end) => {
        const start = props.form.starts_at;
        if (!end || !start || end >= start) {
            syncYearFromDates();
            return;
        }
        props.form.ends_at = start;
    },
);

onMounted(() => {
    syncYearFromDates();
});

function isRecipientSelected(userId) {
    const id = Number(userId);
    if (!Array.isArray(props.form.recipient_user_ids)) {
        return false;
    }

    return props.form.recipient_user_ids.map((x) => Number(x)).includes(id);
}

function toggleRecipient(userId) {
    const id = Number(userId);
    if (!Array.isArray(props.form.recipient_user_ids)) {
        props.form.recipient_user_ids = [];
    }
    const arr = props.form.recipient_user_ids.map((x) => Number(x));
    const i = arr.indexOf(id);
    if (i >= 0) {
        arr.splice(i, 1);
    } else {
        arr.push(id);
    }
    props.form.recipient_user_ids = arr;
}

function buildAutoDisplayName(boatShowName, year, booth) {
    const showName = String(boatShowName ?? '').trim();
    const yearPart = year !== null && year !== undefined && year !== '' ? String(year) : '';
    const boothPart = String(booth ?? '').trim();

    const parts = [showName, yearPart].filter((p) => p !== '');
    let name = parts.join(' ');

    if (boothPart !== '') {
        name = name !== '' ? `${name} — Booth ${boothPart}` : `Booth ${boothPart}`;
    }

    return name !== '' ? name : 'Boat show event';
}

const resolvedBoatShowName = computed(() => {
    if (props.parentBoatShow?.name) {
        return props.parentBoatShow.name;
    }
    const id = Number(props.form.boat_show_id);
    if (!id) {
        return '';
    }
    const match = (props.boatShowOptions ?? []).find((opt) => Number(opt.id) === id);

    return match?.name ?? '';
});

const autoDisplayName = computed(() =>
    buildAutoDisplayName(resolvedBoatShowName.value, props.form.year, props.form.booth),
);

const effectiveDisplayName = computed(() =>
    props.form.use_custom_display_name === true
    || props.form.use_custom_display_name === 1
    || props.form.use_custom_display_name === '1'
        ? String(props.form.display_name ?? '').trim() || autoDisplayName.value
        : autoDisplayName.value,
);
</script>

<template>
    <div class="space-y-8">
        <!-- Parent boat show -->
        <div
            v-if="isNested && parentBoatShow"
            class="rounded-lg border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Boat show
            </p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                {{ parentBoatShow.name }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                <span v-if="mode === 'create'">
                    Events are created for this show. To use a different show, start from that show’s page.
                </span>
                <span v-else>
                    To change the parent show, contact support or recreate the event.
                </span>
            </p>
        </div>

        <div
            v-else-if="mode === 'create'"
            class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <label for="boat_show_id" class="block text-sm font-bold text-gray-900 dark:text-white">
                Boat show <span class="text-red-500">*</span>
            </label>
            <div class="mt-2 max-w-xl">
                <RecordSelect
                    id="boat_show_id"
                    v-model="form.boat_show_id"
                    :field="boatShowField"
                    :enum-options="boatShowEnumOptions"
                    :record="pseudoRecord"
                    field-key="boat_show_id"
                />
            </div>
            <p v-if="fieldError('boat_show_id')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                {{ fieldError('boat_show_id') }}
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Search existing boat shows or use Create New to add one inline.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            <div class="space-y-8 lg:col-span-2">
        <!-- Event details -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
            <h3 class="mb-6 text-base font-semibold text-gray-900 dark:text-white">Event details</h3>
            <div class="grid gap-6 sm:grid-cols-12">
                <div class="sm:col-span-12">
                    <p class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Event name</p>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/50">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ effectiveDisplayName }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Generated from boat show, year, and booth. Enable custom name below to override.
                        </p>
                    </div>
                </div>

                <div class="sm:col-span-12">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/50"
                    >
                        <input
                            v-model="form.use_custom_display_name"
                            type="checkbox"
                            :true-value="1"
                            :false-value="0"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Use custom display name</span>
                    </label>
                </div>

                <div v-if="form.use_custom_display_name" class="sm:col-span-12">
                    <label for="display_name" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Custom display name
                    </label>
                    <input
                        id="display_name"
                        v-model="form.display_name"
                        type="text"
                        maxlength="255"
                        class="input-style w-full"
                        placeholder="e.g. Miami VIP preview"
                    />
                    <p v-if="fieldError('display_name')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('display_name') }}
                    </p>
                </div>

                <div class="sm:col-span-6">
                    <label for="starts_at" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Starts</label>
                    <DateInput id="starts_at" v-model="form.starts_at" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Year is set automatically from the start date.
                    </p>
                    <p v-if="fieldError('starts_at')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('starts_at') }}
                    </p>
                    <p v-if="fieldError('year')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('year') }}
                    </p>
                </div>

                <div class="sm:col-span-6">
                    <label for="ends_at" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Ends</label>
                    <DateInput id="ends_at" v-model="form.ends_at" :min="form.starts_at || ''" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Defaults to the Sunday on or after the start date. You can change it manually.
                    </p>
                    <p v-if="fieldError('ends_at')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('ends_at') }}
                    </p>
                </div>

                <div class="sm:col-span-6">
                    <label for="venue" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Venue</label>
                    <input id="venue" v-model="form.venue" type="text" maxlength="255" class="input-style w-full" />
                    <p v-if="fieldError('venue')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('venue') }}
                    </p>
                </div>

                <div class="sm:col-span-6">
                    <label for="booth" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Booth</label>
                    <input id="booth" v-model="form.booth" type="text" maxlength="255" class="input-style w-full" />
                    <p v-if="fieldError('booth')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('booth') }}
                    </p>
                </div>

                <div class="sm:col-span-12">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/50"
                    >
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

        <!-- Follow-up email (per event) -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
            <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Follow-up email</h3>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                After a lead is submitted from the public event page, a delayed email can be sent using the shared template.
                Edit wording in
                <Link :href="route('boat-show-email-templates.index')" class="text-primary-600 hover:underline dark:text-primary-400">
                    Follow-up emails
                </Link>
                . Leave recipients empty to notify the account owner only.
            </p>
            <div class="grid gap-6 sm:grid-cols-12">
                <div class="sm:col-span-12">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700/50"
                    >
                        <input
                            v-model="form.auto_followup"
                            type="checkbox"
                            :true-value="1"
                            :false-value="0"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Enable automatic follow-up</span>
                    </label>
                    <p v-if="fieldError('auto_followup')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('auto_followup') }}
                    </p>
                </div>

                <div class="sm:col-span-4">
                    <label for="delay_amount" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Delay</label>
                    <input
                        id="delay_amount"
                        v-model.number="form.delay_amount"
                        type="number"
                        min="0"
                        class="input-style w-full"
                    />
                    <p v-if="fieldError('delay_amount')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('delay_amount') }}
                    </p>
                </div>

                <div class="sm:col-span-4">
                    <label for="delay_unit" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Unit</label>
                    <select id="delay_unit" v-model="form.delay_unit" class="input-style w-full">
                        <option value="minutes">Minutes</option>
                        <option value="hours">Hours</option>
                        <option value="days">Days</option>
                    </select>
                    <p v-if="fieldError('delay_unit')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('delay_unit') }}
                    </p>
                </div>
            </div>
        </div>
            </div>

            <div class="space-y-6">
                <!-- Venue address -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                    <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                        Venue address
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(optional)</span>
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Search for the street address; city, state, postal code, and map coordinates are filled automatically.
                    </p>
                    <AddressAutocomplete
                        :id="addressFieldId"
                        :street="form.address_line_1"
                        :unit="form.address_line_2"
                        :city="form.city"
                        :state="form.state"
                        :postal-code="form.postal_code"
                        :country="form.country"
                        :latitude="form.latitude"
                        :longitude="form.longitude"
                        :disabled="form.processing"
                        @update="
                            (data) => {
                                form.address_line_1 = data.street ?? '';
                                form.address_line_2 = data.unit ?? '';
                                form.city = data.city ?? '';
                                form.state = data.state || data.stateCode || '';
                                form.country = data.country ?? '';
                                form.postal_code = data.postalCode ?? '';
                                form.latitude = data.latitude != null && data.latitude !== '' ? data.latitude : '';
                                form.longitude = data.longitude != null && data.longitude !== '' ? data.longitude : '';
                            }
                        "
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

                <!-- Notify users -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                    <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Notify users</h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Staff who receive follow-up notifications. Leave all unchecked to notify the account owner only.
                    </p>
                    <div
                        v-if="recipientUserOptions.length"
                        class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700/50"
                    >
                        <label
                            v-for="u in recipientUserOptions"
                            :key="u.id"
                            class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 hover:bg-white dark:hover:bg-gray-700"
                        >
                            <input
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                :checked="isRecipientSelected(u.id)"
                                @change="toggleRecipient(u.id)"
                            />
                            <span class="min-w-0 text-sm text-gray-800 dark:text-gray-200">
                                <span class="block font-medium">{{ u.name }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ u.email }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No staff users available.</p>
                    <p v-if="fieldError('recipient_user_ids')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('recipient_user_ids') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
