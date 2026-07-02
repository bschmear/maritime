<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Start typing an address…' },
    minCharacters: { type: Number, default: 3 },
    debounceMs: { type: Number, default: 300 },
});

const emit = defineEmits(['select', 'clear']);

const query = ref('');
const selectedAddress = ref(null);
const suggestions = ref([]);
const isLoading = ref(false);
const isOpen = ref(false);
const activeIndex = ref(-1);
const loadError = ref(null);

let debounceTimer = null;
let requestId = 0;

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            clearSuggestions();
        }
    },
);

function formatSuggestionLabel(address) {
    if (address.formattedAddress) {
        return address.formattedAddress;
    }

    if (address.placeLabel) {
        return address.placeLabel;
    }

    const parts = [
        address.number,
        address.street,
        address.city,
        address.stateCode || address.state,
        address.postalCode,
    ].filter(Boolean);

    return parts.join(', ');
}

function clearSuggestions() {
    suggestions.value = [];
    isOpen.value = false;
    activeIndex.value = -1;
}

function reset() {
    query.value = '';
    selectedAddress.value = null;
    loadError.value = null;
    clearSuggestions();
    clearTimeout(debounceTimer);
}

function clearInput() {
    query.value = '';
    selectedAddress.value = null;
    clearSuggestions();
    emit('clear');
}

function onInput(event) {
    const value = event.target.value;
    query.value = value;
    selectedAddress.value = null;
    loadError.value = null;
    clearTimeout(debounceTimer);

    if (value.trim().length < props.minCharacters) {
        clearSuggestions();
        return;
    }

    debounceTimer = setTimeout(() => {
        fetchSuggestions(value.trim());
    }, props.debounceMs);
}

async function fetchSuggestions(value) {
    const currentRequest = ++requestId;
    isLoading.value = true;

    try {
        const { data } = await axios.get(route('address-autocomplete.search'), {
            params: { query: value },
        });

        if (currentRequest !== requestId) {
            return;
        }

        suggestions.value = Array.isArray(data.data) ? data.data : [];
        isOpen.value = suggestions.value.length > 0;
        activeIndex.value = suggestions.value.length > 0 ? 0 : -1;
    } catch (error) {
        if (currentRequest !== requestId) {
            return;
        }

        console.error('Address autocomplete search failed:', error);
        loadError.value = 'Unable to load address suggestions.';
        clearSuggestions();
    } finally {
        if (currentRequest === requestId) {
            isLoading.value = false;
        }
    }
}

function selectSuggestion(address) {
    selectedAddress.value = address;
    query.value = formatSuggestionLabel(address);
    emit('select', address);
    clearSuggestions();
}

function onKeydown(event) {
    if (!isOpen.value || suggestions.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            activeIndex.value <= 0 ? suggestions.value.length - 1 : activeIndex.value - 1;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const address = suggestions.value[activeIndex.value];
        if (address) {
            selectSuggestion(address);
        }
    } else if (event.key === 'Escape') {
        clearSuggestions();
    }
}

function onBlur() {
    window.setTimeout(() => {
        isOpen.value = false;

        if (!selectedAddress.value) {
            if (query.value !== '') {
                clearInput();
            }
            return;
        }

        const expectedLabel = formatSuggestionLabel(selectedAddress.value);
        if (query.value.trim() !== expectedLabel.trim()) {
            clearInput();
        }
    }, 150);
}

defineExpose({ reset });
</script>

<template>
    <div class="relative w-full">
        <input
            type="text"
            :value="query"
            :disabled="disabled"
            :placeholder="placeholder"
            autocomplete="off"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 disabled:opacity-50"
            @input="onInput"
            @keydown="onKeydown"
            @blur="onBlur"
            @focus="isOpen = suggestions.length > 0"
        />

        <p v-if="isLoading" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Searching…</p>
        <p v-else-if="loadError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ loadError }}</p>

        <ul
            v-if="isOpen"
            class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-700"
        >
            <li
                v-for="(address, index) in suggestions"
                :key="`${address.formattedAddress || address.placeLabel || index}-${index}`"
            >
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-600"
                    :class="{ 'bg-gray-100 dark:bg-gray-600': index === activeIndex }"
                    @mousedown.prevent="selectSuggestion(address)"
                >
                    {{ formatSuggestionLabel(address) }}
                </button>
            </li>
        </ul>
    </div>
</template>
