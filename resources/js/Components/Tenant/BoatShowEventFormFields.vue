<script setup>
import DateInput from '@/Components/Tenant/FormComponents/Date.vue';
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
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
            <select
                id="boat_show_id"
                v-model="form.boat_show_id"
                required
                class="input-style mt-2 w-full max-w-xl"
                :class="{ 'ring-2 ring-red-500': fieldError('boat_show_id') }"
            >
                <option disabled :value="null">Select a boat show</option>
                <option v-for="opt in boatShowOptions" :key="opt.id" :value="opt.id">
                    {{ opt.name }}
                </option>
            </select>
            <p v-if="fieldError('boat_show_id')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                {{ fieldError('boat_show_id') }}
            </p>
            <p v-if="!boatShowOptions.length" class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                No boat shows exist yet. Create a boat show first, then add events from its page.
            </p>
        </div>

        <!-- Event details -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
            <h3 class="mb-6 text-base font-semibold text-gray-900 dark:text-white">Event details</h3>
            <div class="grid gap-6 sm:grid-cols-12">
                <div class="sm:col-span-12">
                    <label for="display_name" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
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
                    <label for="year" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
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
                    <label for="starts_at" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Starts</label>
                    <DateInput id="starts_at" v-model="form.starts_at" />
                    <p v-if="fieldError('starts_at')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('starts_at') }}
                    </p>
                </div>

                <div class="sm:col-span-4">
                    <label for="ends_at" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">Ends</label>
                    <DateInput id="ends_at" v-model="form.ends_at" />
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

                <div class="sm:col-span-12">
                    <label for="recipient_user_ids" class="mb-2 block text-sm font-bold text-gray-900 dark:text-white">
                        Notify users
                    </label>
                    <select
                        id="recipient_user_ids"
                        v-model="form.recipient_user_ids"
                        multiple
                        class="input-style min-h-[140px] w-full max-w-xl"
                        size="6"
                    >
                        <option v-for="u in recipientUserOptions" :key="u.id" :value="u.id">
                            {{ u.name }} — {{ u.email }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Cmd/Ctrl to select multiple. Empty = account owner.</p>
                    <p v-if="fieldError('recipient_user_ids')" class="mt-2 text-sm text-red-600 dark:text-red-500">
                        {{ fieldError('recipient_user_ids') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Venue address -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-800 sm:p-8">
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
    </div>
</template>
