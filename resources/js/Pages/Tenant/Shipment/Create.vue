<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useSubsidiaryLocationAutofill } from '@/composables/useSubsidiaryLocationAutofill';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    contacts: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
});

const recipientType = ref('contact');
const record = ref({});

const subsidiaryField = {
    type: 'record',
    typeDomain: 'Subsidiary',
    label: 'Subsidiary',
    required: true,
};

const locationField = {
    type: 'record',
    typeDomain: 'Location',
    label: 'Location',
    required: true,
    filterby: 'subsidiary_id',
};

const form = useForm({
    recipient_type: 'contact',
    contact_id: '',
    vendor_id: '',
    subsidiary_id: null,
    location_id: null,
    to_address: {
        name: '',
        company: '',
        street1: '',
        street2: '',
        city: '',
        state: '',
        zip: '',
        country: 'US',
        phone: '',
        email: '',
    },
    parcel: {
        length: 10,
        width: 8,
        height: 4,
        weight: 16,
    },
    notes: '',
});

useSubsidiaryLocationAutofill(form, () => ({
    subsidiary_id: subsidiaryField,
    location_id: locationField,
}), { assumeFiltered: true });

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Shipments', href: route('shipments.index') },
    { label: 'New' },
]);

function setRecipientType(type) {
    recipientType.value = type;
    form.recipient_type = type;
}

function submit() {
    form.post(route('shipments.store'));
}
</script>

<template>
    <Head title="New shipment" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">New shipment</h2>
            </div>
        </template>

        <div class="mx-auto w-full max-w-3xl px-4 py-6">
            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ship from</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Choose the subsidiary and location this shipment departs from.
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subsidiary *</label>
                            <RecordSelect
                                id="subsidiary_id"
                                :field="subsidiaryField"
                                v-model="form.subsidiary_id"
                                :record="record"
                                field-key="subsidiary_id"
                            />
                            <p v-if="form.errors.subsidiary_id" class="mt-1 text-sm text-red-600">{{ form.errors.subsidiary_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Location *</label>
                            <RecordSelect
                                id="location_id"
                                :field="locationField"
                                v-model="form.location_id"
                                :disabled="!form.subsidiary_id"
                                :record="record"
                                field-key="location_id"
                                filter-by="subsidiary_id"
                                :filter-value="form.subsidiary_id"
                            />
                            <p v-if="form.errors.location_id" class="mt-1 text-sm text-red-600">{{ form.errors.location_id }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Recipient</h3>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium" :class="recipientType === 'contact' ? 'bg-primary-600 text-white' : 'border border-gray-300 text-gray-700'" @click="setRecipientType('contact')">Contact</button>
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium" :class="recipientType === 'vendor' ? 'bg-primary-600 text-white' : 'border border-gray-300 text-gray-700'" @click="setRecipientType('vendor')">Vendor</button>
                    </div>
                    <div v-if="recipientType === 'contact'" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact</label>
                        <select v-model="form.contact_id" required class="input-style mt-1">
                            <option value="" disabled>Select contact</option>
                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                {{ contact.display_name || `${contact.first_name} ${contact.last_name}` }}
                            </option>
                        </select>
                    </div>
                    <div v-else class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</label>
                        <select v-model="form.vendor_id" required class="input-style mt-1">
                            <option value="" disabled>Select vendor</option>
                            <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.display_name }}</option>
                        </select>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ship to</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2"><input v-model="form.to_address.name" placeholder="Recipient name" class="input-style" /></div>
                        <div class="sm:col-span-2"><input v-model="form.to_address.street1" required placeholder="Street" class="input-style" /></div>
                        <div class="sm:col-span-2"><input v-model="form.to_address.street2" placeholder="Street 2" class="input-style" /></div>
                        <div><input v-model="form.to_address.city" required placeholder="City" class="input-style" /></div>
                        <div><input v-model="form.to_address.state" required placeholder="State" class="input-style" /></div>
                        <div><input v-model="form.to_address.zip" required placeholder="ZIP" class="input-style" /></div>
                        <div><input v-model="form.to_address.country" placeholder="Country" class="input-style" /></div>
                        <div><input v-model="form.to_address.phone" placeholder="Phone" class="input-style" /></div>
                        <div><input v-model="form.to_address.email" type="email" placeholder="Email" class="input-style" /></div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parcel</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div><label class="text-sm text-gray-600">Length (in)</label><input v-model.number="form.parcel.length" type="number" step="0.1" min="0.1" required class="input-style mt-1" /></div>
                        <div><label class="text-sm text-gray-600">Width (in)</label><input v-model.number="form.parcel.width" type="number" step="0.1" min="0.1" required class="input-style mt-1" /></div>
                        <div><label class="text-sm text-gray-600">Height (in)</label><input v-model.number="form.parcel.height" type="number" step="0.1" min="0.1" required class="input-style mt-1" /></div>
                        <div><label class="text-sm text-gray-600">Weight (oz)</label><input v-model.number="form.parcel.weight" type="number" step="0.1" min="0.1" required class="input-style mt-1" /></div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Notes</h3>
                    <textarea v-model="form.notes" rows="3" class="input-style mt-3 w-full" />
                </section>

                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" :disabled="form.processing">
                    Create shipment
                </button>
            </form>
        </div>
    </TenantLayout>
</template>
