<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    field: {
        type: Object,
        required: true
    },
    modelValue: {
        type: [String, Number, null],
        default: null
    },
    selectedType: {
        type: [String, null],
        default: null
    },
    disabled: {
        type: Boolean,
        default: false
    },
    id: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['update:modelValue', 'update:selectedType']);

const showModal = ref(false);
const selectedMorphType = ref(props.selectedType || '');
const selectedRecordId = ref(props.modelValue || null);
const selectedRecordName = ref('');
const records = ref([]);
const searchQuery = ref('');
const isLoading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = 10;

// Get the selected morph config
const selectedMorphConfig = computed(() => {
    if (!selectedMorphType.value) return null;
    return props.field.morphable_types?.find(t => t.value === selectedMorphType.value);
});

// Get the selected record display name
const selectedRecordDisplay = computed(() => {
    if (!selectedRecordId.value) return '';
    if (selectedRecordName.value) return selectedRecordName.value;
    const record = records.value.find(r => r.id === selectedRecordId.value);
    return record ? record.display_name : '';
});

// Get display text for input field
const displayText = computed(() => {
    if (!selectedRecordId.value || !selectedMorphType.value) {
        return '';
    }
    const typeName = props.field.morphable_types?.find(t => t.value === selectedMorphType.value)?.label || 'Unknown';
    return `${typeName}: ${selectedRecordDisplay.value}`;
});

// Open modal
const openModal = () => {
    if (!props.disabled) {
        showModal.value = true;
    }
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    searchQuery.value = '';
    currentPage.value = 1;
};

// Clear selection
const clearSelection = () => {
    selectedMorphType.value = '';
    selectedRecordId.value = null;
    selectedRecordName.value = '';
    emit('update:selectedType', '');
    emit('update:modelValue', null);
    records.value = [];
};

// Watch for type changes and fetch records (only in modal)
watch(selectedMorphType, async (newType) => {
    if (newType && showModal.value) {
        currentPage.value = 1;
        await fetchRecords();
    } else if (!newType) {
        records.value = [];
    }
});

// Watch for search query changes
watch(searchQuery, async () => {
    currentPage.value = 1;
    await fetchRecords();
});

// Fetch records from the selected domain
const fetchRecords = async () => {
    if (!selectedMorphConfig.value) return;
    
    isLoading.value = true;
    
    try {
        const domain = selectedMorphConfig.value.domain.toLowerCase();
        
        // Build the URL with query parameters
        const url = new URL(route(`${domain}s.index`), window.location.origin);
        url.searchParams.append('page', currentPage.value);
        url.searchParams.append('per_page', perPage);
        if (searchQuery.value) {
            url.searchParams.append('search', searchQuery.value);
        }
        
        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const text = await response.text();
            console.error('Response not OK:', response.status, text);
            throw new Error(`Failed to fetch records: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Handle different response formats
        if (data.records) {
            records.value = data.records;
            totalPages.value = data.meta?.last_page || 1;
        } else if (Array.isArray(data)) {
            records.value = data;
            totalPages.value = 1;
        } else if (data.data) {
            records.value = data.data;
            totalPages.value = data.meta?.last_page || data.last_page || 1;
        } else {
            console.warn('Unexpected response format:', data);
            records.value = [];
        }
    } catch (error) {
        console.error('Error fetching morph records:', error);
        records.value = [];
    } finally {
        isLoading.value = false;
    }
};

// Handle record selection and confirm
const selectRecord = (record) => {
    selectedRecordId.value = record.id;
    selectedRecordName.value = record.display_name;
    emit('update:modelValue', record.id);
    emit('update:selectedType', selectedMorphType.value);
    closeModal();
};

// Handle pagination
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
        fetchRecords();
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
        fetchRecords();
    }
};

// Watch for props changes (when editing existing record)
watch(() => props.modelValue, (newValue) => {
    selectedRecordId.value = newValue;
});

watch(() => props.selectedType, (newValue) => {
    selectedMorphType.value = newValue;
});
</script>

<template>
    <div class="relative">
        <!-- Input Field -->
        <div class="relative">
            <input
                :id="id"
                type="text"
                :value="displayText"
                @click="openModal"
                readonly
                :placeholder="`Select ${field.label}...`"
                :disabled="disabled"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 pr-20 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 gap-1">
                <!-- Clear Button -->
                <button
                    v-if="selectedRecordId"
                    @click.stop="clearSelection"
                    type="button"
                    :disabled="disabled"
                    class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-50"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <!-- Open Modal Button -->
                <button
                    @click.stop="openModal"
                    type="button"
                    :disabled="disabled"
                    class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-50"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Modal Overlay -->
        <div v-if="showModal" @click="closeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
            <div @click.stop class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-lg shadow-xl dark:bg-gray-800 flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ field.label }}
                    </h3>
                    <button
                        @click="closeModal"
                        type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Type Selection -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                            Select Type
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="morphType in field.morphable_types"
                                :key="morphType.value"
                                @click="selectedMorphType = morphType.value"
                                type="button"
                                class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                                :class="selectedMorphType === morphType.value
                                    ? 'bg-blue-600 text-white border-blue-600 dark:bg-blue-500 dark:border-blue-500'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600'"
                            >
                                {{ morphType.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Record Selection (Shows when type is selected) -->
                    <div v-if="selectedMorphType">
                        <div class="mb-3">
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Select {{ selectedMorphConfig?.label }}
                            </label>
                            
                            <!-- Search Input -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search..."
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                >
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isLoading" class="flex justify-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <!-- Records List -->
                        <div v-else-if="records.length > 0" class="space-y-2">
                            <div
                                v-for="record in records"
                                :key="record.id"
                                @click="selectRecord(record)"
                                class="p-3 border rounded-lg cursor-pointer transition-colors"
                                :class="selectedRecordId === record.id 
                                    ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ record.display_name }}
                                        </p>
                                        <p v-if="record.email" class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ record.email }}
                                        </p>
                                    </div>
                                    <div v-if="selectedRecordId === record.id" class="ml-2">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="mt-2">No {{ selectedMorphConfig?.label }}s found</p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="records.length > 0 && totalPages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                @click="prevPage"
                                :disabled="currentPage === 1"
                                class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                Previous
                            </button>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Page {{ currentPage }} of {{ totalPages }}
                            </span>
                            <button
                                @click="nextPage"
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
