<script setup>
import { ref, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import AddressSearchInput from '@/Components/AddressSearchInput.vue';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    buttonLabel: { type: String, default: 'Add address' },
});

const emit = defineEmits(['saved']);

const modalOpen = ref(false);
const unitInput = ref('');
const addressInfo = ref(null);
const labelInput = ref('');
const isPrimary = ref(false);
const addressSearchRef = ref(null);

const canSave = computed(() => !!addressInfo.value);

const openModal = () => {
    if (props.disabled) {
        return;
    }
    labelInput.value = '';
    isPrimary.value = false;
    unitInput.value = '';
    addressInfo.value = null;
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
};

watch(modalOpen, (open) => {
    if (!open) {
        addressInfo.value = null;
        addressSearchRef.value?.reset();
    }
});

const onAddressSelected = (result) => {
    addressInfo.value = result;
};

const onAddressCleared = () => {
    addressInfo.value = null;
};

const buildStreetLine = (result) => {
    let street = (result.number ? `${result.number} ` : '') + (result.street || '');
    if (!street && (result.addressLabel || result.placeLabel)) {
        street = result.addressLabel || result.placeLabel;
    }
    return street;
};

const saveAddress = () => {
    if (!addressInfo.value) {
        return;
    }

    const result = addressInfo.value;
    const street = buildStreetLine(result);

    emit('saved', {
        label: labelInput.value.trim() || null,
        is_primary: isPrimary.value,
        address_line_1: street,
        address_line_2: unitInput.value.trim() || null,
        city: result.city || '',
        state: result.stateCode || result.state || '',
        postal_code: result.postalCode || '',
        country: result.countryCode || result.country || '',
        latitude: result.latitude ?? null,
        longitude: result.longitude ?? null,
    });
    closeModal();
};
defineExpose({ open: openModal });
</script>

<template>
    <div class="contact-address-autocomplete w-full">
        <button
            type="button"
            @click="openModal"
            :disabled="disabled"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
        >
            <span class="material-icons text-[18px]">add_location_alt</span>
            {{ buttonLabel }}
        </button>

        <Modal :show="modalOpen" @close="closeModal" max-width="lg">
            <div class="p-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Add contact address</h3>

                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Label <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <input
                            v-model="labelInput"
                            type="text"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                            placeholder="Home, Billing, Marina…"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="contact-address-primary"
                            v-model="isPrimary"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <label for="contact-address-primary" class="text-sm font-medium text-gray-900 dark:text-gray-200">
                            Set as primary address
                        </label>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Address search
                        </label>
                        <AddressSearchInput
                            ref="addressSearchRef"
                            :disabled="disabled"
                            @select="onAddressSelected"
                            @clear="onAddressCleared"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Type at least 3 characters, then pick a result.
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Unit / suite <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <input
                            v-model="unitInput"
                            type="text"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                            placeholder="Apt 4B"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="!canSave"
                        @click="saveAddress"
                        class="rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Save address
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
