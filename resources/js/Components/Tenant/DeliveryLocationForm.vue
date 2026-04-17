<script setup>
import AddressAutocomplete from '@/Components/AddressAutocomplete.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    mode: { type: String, default: 'create' },
});

const emit = defineEmits(['saved', 'cancelled']);

const isEdit = computed(() => props.mode === 'edit' && props.record);
const r = props.record ?? {};

const form = useForm({
    name: r.name ?? '',
    subsidiary_id: r.subsidiary_id ?? null,
    address_line_1: r.address_line_1 ?? '',
    address_line_2: r.address_line_2 ?? '',
    city: r.city ?? '',
    state: r.state ?? '',
    postal_code: r.postal_code ?? '',
    country: r.country ?? '',
    latitude: r.latitude ?? null,
    longitude: r.longitude ?? null,
    contact_name: r.contact_name ?? '',
    contact_phone: r.contact_phone ?? '',
    notes: r.notes ?? '',
    active: r.active ?? true,
});

const onAddressUpdate = (data) => {
    form.address_line_1 = data.street || '';
    form.address_line_2 = data.unit || '';
    form.city = data.city || '';
    form.state = data.stateCode || data.state || '';
    form.postal_code = data.postalCode || '';
    form.country = data.country || '';
    form.latitude = data.latitude ?? null;
    form.longitude = data.longitude ?? null;
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('delivery-locations.update', r.id), {
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
    <form @submit.prevent="submit" class="space-y-6">
        <section class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Basics</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Name <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" required class="block w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" v-model="form.active" />
                Active
            </label>
        </section>

        <section class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Address</h3>
            <AddressAutocomplete
                :street="form.address_line_1"
                :unit="form.address_line_2"
                :city="form.city"
                :state="form.state"
                :postal-code="form.postal_code"
                :country="form.country"
                :latitude="form.latitude"
                :longitude="form.longitude"
                @update="onAddressUpdate"
            />
        </section>

        <section class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact Name</label>
                <input v-model="form.contact_name" class="block w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact Phone</label>
                <input v-model="form.contact_phone" class="block w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                <textarea v-model="form.notes" rows="3" class="block w-full rounded-md border-gray-300 text-sm" />
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <button type="button" @click="emit('cancelled')"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" :disabled="form.processing"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 disabled:opacity-60">
                <span class="material-icons text-base">save</span>
                {{ isEdit ? 'Save Changes' : 'Create Location' }}
            </button>
        </div>
    </form>
</template>
