<script setup>
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    mode: { type: String, default: 'create' },
});

const emit = defineEmits(['saved', 'cancelled']);

const isEdit = computed(() => props.mode === 'edit' && props.record);
const recordForSelect = computed(() => props.record ?? {});

const initial = computed(() => props.record ?? {});

const form = useForm({
    name: initial.value.name ?? '',
    subsidiary_id: initial.value.subsidiary_id ?? null,
    address_line_1: initial.value.address_line_1 ?? '',
    address_line_2: initial.value.address_line_2 ?? '',
    city: initial.value.city ?? '',
    state: initial.value.state ?? '',
    postal_code: initial.value.postal_code ?? '',
    country: initial.value.country ?? '',
    latitude: initial.value.latitude ?? null,
    longitude: initial.value.longitude ?? null,
    contact_name: initial.value.contact_name ?? '',
    contact_phone: initial.value.contact_phone ?? '',
    notes: initial.value.notes ?? '',
    active: initial.value.active ?? true,
});

const subsidiaryField = computed(() => ({
    type: 'record',
    typeDomain: 'Subsidiary',
    label: 'Subsidiary',
}));

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street || '';
    form.address_line_2 = data.unit || '';
    form.city = data.city || '';
    form.state = data.stateCode || data.state || '';
    form.postal_code = data.postalCode || '';
    form.country = data.countryCode || data.country || '';
    form.latitude = data.latitude ?? null;
    form.longitude = data.longitude ?? null;
};

const submit = () => {
    if (isEdit.value && props.record?.id) {
        form.put(route('delivery-locations.update', props.record.id), {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    } else {
        form.post(route('delivery-locations.store'), {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    }
};
</script>

<template>
    <div class="w-full">
        <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-6">
            <!-- Page title strip (full width) -->
            <div class="lg:col-span-12 bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 shadow-lg sm:rounded-lg px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    {{ isEdit ? 'Edit location' : 'New common location' }}
                </h2>
                <p class="text-primary-100 text-sm mt-1 max-w-4xl">
                    {{ isEdit ? 'Update name, subsidiary, and defaults for deliveries.' : 'Save a reusable delivery address your team can pick from.' }}
                </p>
            </div>

            <!-- Basics column -->
            <div class="lg:col-span-5 bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden flex flex-col min-h-0">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Location details
                    </h3>
                </div>
                <div class="p-6 space-y-5 flex-1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="organization"
                            class="input-style"
                            placeholder="e.g. Main marina dock"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Subsidiary</label>
                        <RecordSelect
                            id="subsidiary_id"
                            :field="subsidiaryField"
                            :record="recordForSelect"
                            v-model="form.subsidiary_id"
                            field-key="subsidiary_id"
                        />
                        <p v-if="form.errors.subsidiary_id" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ form.errors.subsidiary_id }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional — tie this location to a subsidiary for reporting.</p>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer select-none rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-4 py-3">
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 shrink-0"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">Active</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Inactive locations stay hidden from delivery pickers.</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Address column -->
            <div class="lg:col-span-7 bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden flex flex-col min-h-0">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Address
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Search or refine the physical location for this site.</p>
                </div>
                <div class="p-6 flex-1 min-h-[200px]">
                    <AddressAutocomplete
                        :street="form.address_line_1"
                        :unit="form.address_line_2"
                        :city="form.city"
                        :state="form.state"
                        :stateCode="form.state"
                        :postalCode="form.postal_code"
                        :country="form.country"
                        :latitude="form.latitude"
                        :longitude="form.longitude"
                        @update="onAddressUpdate"
                    />
                </div>
            </div>

            <!-- Contact & notes: three columns on xl, two on md -->
            <div class="lg:col-span-12 bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Site contact &amp; notes
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 xl:items-start gap-5 xl:gap-6">
                    <div class="md:col-span-1 xl:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact name</label>
                        <input
                            v-model="form.contact_name"
                            type="text"
                            autocomplete="name"
                            class="input-style"
                            placeholder="Who meets the driver"
                        />
                        <p v-if="form.errors.contact_name" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ form.errors.contact_name }}</p>
                    </div>
                    <div class="md:col-span-1 xl:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact phone</label>
                        <input
                            v-model="form.contact_phone"
                            type="tel"
                            autocomplete="tel"
                            class="input-style"
                            placeholder="+1 (555) 000-0000"
                        />
                        <p v-if="form.errors.contact_phone" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ form.errors.contact_phone }}</p>
                    </div>
                    <div class="md:col-span-2 xl:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="6"
                            class="input-style resize-y min-h-[140px]"
                            placeholder="Gate codes, dock assignment, parking, hours…"
                        />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ form.errors.notes }}</p>
                    </div>
                </div>
            </div>

            <p v-if="form.errors.general" class="lg:col-span-12 text-sm text-red-600 dark:text-red-400">{{ form.errors.general }}</p>

            <div class="lg:col-span-12 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <button
                    type="button"
                    class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    @click="emit('cancelled')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex justify-center items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg border border-transparent disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <span class="material-icons text-[18px]" :class="{ 'animate-spin': form.processing }">
                        {{ form.processing ? 'sync' : 'save' }}
                    </span>
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create location') }}
                </button>
            </div>
        </form>
    </div>
</template>
