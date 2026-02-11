<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { loadRadar } from '../Utils/RadarLoader';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    id: {
        type: String,
        default: () => `radar-autocomplete-${Math.random().toString(36).substr(2, 9)}`
    },
    street: { type: String, default: '' },
    unit: { type: String, default: '' },
    city: { type: String, default: '' },
    state: { type: String, default: '' },
    stateCode: { type: String, default: '' },
    postalCode: { type: String, default: '' },
    country: { type: String, default: '' },
    countryCode: { type: String, default: '' },
    latitude: { type: [Number, String], default: '' },
    longitude: { type: [Number, String], default: '' },
    disabled: { type: Boolean, default: false }
});

const emit = defineEmits(['update']);

const modalOpen = ref(false);
const radarLoaded = ref(false);
const unitInput = ref('');
const addressInfo = ref(null);

const hasAddress = computed(() => !!props.street);

const fullAddressDisplay = computed(() => {
    if (!hasAddress.value) return '';
    const parts = [
        props.street,
        props.unit ? `Unit ${props.unit}` : null,
        `${props.city}, ${props.state} ${props.postalCode}`,
        props.country
    ].filter(Boolean);
    return parts.join('\n');
});

const openModal = () => {
    if (props.disabled) return;
    unitInput.value = props.unit;
    modalOpen.value = true;
    
    // Initialize Radar when modal opens
    setTimeout(() => {
        initializeRadar();
    }, 100);
};

const closeModal = () => {
    modalOpen.value = false;
    addressInfo.value = null;
};

const initializeRadar = async () => {
    if (radarLoaded.value && document.getElementById(props.id)?.innerHTML) return;

    try {
        const radar = await loadRadar();
        const page = usePage();
        const publishableKey = page.props.radar?.publishable;

        if (!publishableKey) {
            console.error('Radar publishable key not found in Inertia props');
            return;
        }

        // Re-initialize might be needed if key wasn't set globally yet? 
        // RadarLoader initializes with empty string? NO, the user code had `Radar.initialize('')`. 
        // We should initialize with the key.
        if (!window.radarInitialized) {
            radar.initialize(publishableKey);
            window.radarInitialized = true;
        }

        // Clear container first to prevent duplicates
        const container = document.getElementById(props.id);
        if (container) container.innerHTML = '';

        radar.ui.autocomplete({
            container: props.id,
            responsive: true,
            width: '100%',
            debounceMS: 300,
            minCharacters: 3,
            onSelection: (result) => {
                addressInfo.value = result;
            },
        });
        
        radarLoaded.value = true;
    } catch (error) {
        console.error('Failed to initialize Radar autocomplete:', error);
    }
};

const saveAddress = () => {
    if (addressInfo.value) {
        // Radar result mapping
        const result = addressInfo.value;
        const newAddress = {
            street: (result.number ? result.number + ' ' : '') + (result.street || ''),
            unit: unitInput.value,
            city: result.city || '',
            state: result.state || '',
            stateCode: result.stateCode || '',
            postalCode: result.postalCode || '',
            country: result.country || '',
            countryCode: result.countryCode || '',
            latitude: result.latitude || null,
            longitude: result.longitude || null,
        };
        
        // If street is empty but we have a label/name (e.g. place name), use that?
        // Radar usually returns structured address.
        if (!newAddress.street && result.addressLabel) {
             newAddress.street = result.addressLabel;
        }

        emit('update', newAddress);
        closeModal();
    } else if (unitInput.value !== props.unit) {
        // Only unit changed
        emit('update', { 
            street: props.street, 
            unit: unitInput.value,
            city: props.city,
            state: props.state,
            stateCode: props.stateCode,
            postalCode: props.postalCode,
            country: props.country,
            countryCode: props.countryCode,
            latitude: props.latitude,
            longitude: props.longitude
        });
        closeModal();
    }
};

const clearAddress = () => {
    emit('update', {
        street: '',
        unit: '',
        city: '',
        state: '',
        stateCode: '',
        postalCode: '',
        country: '',
        countryCode: '',
        latitude: null,
        longitude: null
    });
};
</script>

<template>
    <div class="address-autocomplete-wrapper">
        <!-- Display State -->
        <div v-if="!hasAddress">
            <button 
                type="button" 
                @click="openModal"
                :disabled="disabled"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
                Add Address
            </button>
        </div>

        <div v-else class="p-3 bg-gray-50 rounded-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600">
            <div class="whitespace-pre-wrap text-sm text-gray-900 dark:text-white mb-3">{{ fullAddressDisplay }}</div>
            
            <div class="flex space-x-3" v-if="!disabled">
                <button 
                    type="button" 
                    @click="openModal" 
                    class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                >
                    Change Address
                </button>
                <button 
                    type="button" 
                    @click="clearAddress" 
                    class="text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400"
                >
                    Remove Address
                </button>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="modalOpen" @close="closeModal" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ hasAddress ? 'Change Address' : 'Add Address' }}
                </h3>

                <div class="space-y-2">

                    <!-- Radar Autocomplete Container -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Address Search
                        </label>
                        <div :id="id" class="relative w-full" style="min-height: 50px;"></div>
                    </div>
                    <!-- Unit Number -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Unit / Suite (Optional)
                        </label>
                        <input 
                            type="text" 
                            v-model="unitInput"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            placeholder="Apt 4B"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveAddress"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    >
                        Set Address
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style>
/* Radar CSS overrides if needed */
.radar-autocomplete-wrapper {
    width: 100%;
}
</style>
