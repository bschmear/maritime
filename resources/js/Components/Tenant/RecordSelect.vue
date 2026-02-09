<script setup>
    import { ref, computed, watch } from 'vue';
    import axios from 'axios';
    import Form from '@/Components/Tenant/Form.vue';
    
    const props = defineProps({
        field: {
            type: Object,
            required: true
        },
        modelValue: {
            type: [String, Number, null],
            default: null
        },
        disabled: {
            type: Boolean,
            default: false
        },
        id: {
            type: String,
            required: true
        },
        enumOptions: {
            type: Array,
            default: () => []
        },
        record: {
            type: Object,
            default: null
        },
        fieldKey: {
            type: String,
            default: ''
        },
        filterBy: {
            type: String,
            default: null
        },
        filterValue: {
            type: [String, Number, null],
            default: null
        }
    });
    
    const emit = defineEmits(['update:modelValue']);
    
    const showModal = ref(false);
    const showCreateModal = ref(false);
    const selectedRecordId = ref(props.modelValue || null);
    const selectedRecordName = ref('');
    const records = ref([]);
    const searchQuery = ref('');
    const isLoading = ref(false);
    const currentPage = ref(1);
    const totalPages = ref(1);
    const perPage = 10;

    // Enhanced modal state
    const showEnhancedModal = ref(false);
    const enhancedModalTab = ref('existing');
    const isLoadingForm = ref(false);
    const createFormData = ref(null);
    
    const getRecordDisplayName = (record) => {
        if (!record) return '';
    
        // 1. display_name
        if (record.display_name) return record.display_name;
    
        // 2. first_name + last_name
        const hasFirst = !!record.first_name;
        const hasLast = !!record.last_name;
    
        if (hasFirst && hasLast) return `${record.first_name} ${record.last_name}`;
        if (hasFirst) return record.first_name;
        if (hasLast) return record.last_name;
    
        // 3. name
        if (record.name) return record.name;
    
        // 4. email
        if (record.email) return record.email;
    
        // 5. final fallback
        return `Record #${record.id}`;
    };
    
    // Get the selected record display name
    const selectedRecordDisplay = computed(() => {
        if (!selectedRecordId.value) return '';
    
        if (selectedRecordName.value) {
            return selectedRecordName.value;
        }
    
        // Check in fetched records first
        const record = records.value.find(r => r.id === selectedRecordId.value);
        if (record) {
            return getRecordDisplayName(record);
        }
    
        // Check in enumOptions (pre-loaded options)
        if (props.enumOptions && props.enumOptions.length > 0) {
            const option = props.enumOptions.find(o => o.id === selectedRecordId.value || o.value === selectedRecordId.value);
            if (option) {
                return option.name || option.display_name || '';
            }
        }
    
        return '';
    });
    
    // Open modal
    const openModal = () => {
        if (!props.disabled) {
            if (props.field.create) {
                showEnhancedModal.value = true;
                enhancedModalTab.value = 'existing';
                // Load first 10 records ordered by display_name
                if (records.value.length === 0) {
                    fetchRecords(true);
                }
            } else {
                showModal.value = true;
                // If modal is opened and we don't have records yet, fetch them
                if (records.value.length === 0) {
                    fetchRecords();
                }
            }
        }
    };
    
    // Close modal
    const closeModal = () => {
        showModal.value = false;
        searchQuery.value = '';
        currentPage.value = 1;
    };

    // Close enhanced modal
    const closeEnhancedModal = () => {
        showEnhancedModal.value = false;
        enhancedModalTab.value = 'existing';
        searchQuery.value = '';
        currentPage.value = 1;
    };

    // Handle record selection in enhanced modal
    const selectRecordInEnhanced = (record) => {
        selectedRecordId.value = record.id;
        selectedRecordName.value = getRecordDisplayName(record);
        emit('update:modelValue', record.id);
        closeEnhancedModal();
    };

    // Open create new tab in enhanced modal
    const openCreateNewTab = async () => {
        enhancedModalTab.value = 'create';

        // Load form data if not already loaded
        if (!createFormData.value) {
            isLoadingForm.value = true;
            try {
                const type = props.field.typeDomain;
                const response = await axios.get(route('records.select-form', { type: type }), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                createFormData.value = response.data;
            } catch (error) {
                console.error('Error loading form data:', error);
                return;
            } finally {
                isLoadingForm.value = false;
            }
        }
    };

    // Handle record created in enhanced modal
    const handleRecordCreated = (recordId) => {
        // Refresh the records list to include the new record
        fetchRecords();
        // Select the newly created record
        selectedRecordId.value = recordId;
        emit('update:modelValue', recordId);
        closeEnhancedModal();
    };
    
    // Clear selection
    const clearSelection = () => {
        selectedRecordId.value = null;
        selectedRecordName.value = '';
        emit('update:modelValue', null);
    };
    
    // Watch for search query changes
    watch(searchQuery, async () => {
        currentPage.value = 1;
        await fetchRecords();
    });
    
    // Fetch records from the domain
    const fetchRecords = async (initialLoad = false) => {
        if (!props.field.typeDomain) return;

        isLoading.value = true;

        try {
            const domain = props.field.typeDomain.toLowerCase();
            const routeName = 'records.lookup';

            // Build the URL with query parameters
            const url = new URL(route(routeName), window.location.origin);
            url.searchParams.append('page', currentPage.value);
            url.searchParams.append('per_page', perPage);
            url.searchParams.append('type', domain);

            // For initial load (first page, no search), order by display_name
            if (initialLoad && currentPage.value === 1 && !searchQuery.value) {
                url.searchParams.append('order_by', 'display_name');
                url.searchParams.append('order_direction', 'asc');
            }

            if (searchQuery.value) {
                url.searchParams.append('search', searchQuery.value);
            }

            // Add filter parameters if provided
            if (props.filterBy && props.filterValue !== null && props.filterValue !== '') {
                url.searchParams.append('filters', JSON.stringify([{
                    field: props.filterBy,
                    operator: 'equals',
                    value: props.filterValue
                }]));
            }
            
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const text = await response.text();
                console.error('Response not OK:', response.status, text.substring(0, 500));
                throw new Error(`Failed to fetch records: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Handle response format from lookup endpoint
            if (data.records) {
                records.value = data.records;
                totalPages.value = data.meta?.last_page || 1;
            } else {
                console.warn('Unexpected response format:', data);
                records.value = [];
            }
        } catch (error) {
            console.error('Error fetching records:', error);
            records.value = [];
        } finally {
            isLoading.value = false;
        }
    };
    
    // Handle record selection and confirm
    const selectRecord = (record) => {
        selectedRecordId.value = record.id;
        selectedRecordName.value = getRecordDisplayName(record);
        emit('update:modelValue', record.id);
        closeModal();
    };

    const openCreateModal = async () => {
        if (!createFormData.value) {
            // Load form data if not already loaded
            isLoadingForm.value = true;
            try {
                // Use the typeDomain directly (e.g., 'BoatMake')
                const type = props.field.typeDomain;
                console.log('Loading form for type:', type);
                console.log('Route URL:', route('records.select-form', { type: type }));
                const response = await axios.get(route('records.select-form', { type: type }), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                console.log('Form data loaded:', response.data);
                createFormData.value = response.data;
            } catch (error) {
                console.error('Error loading form data:', error);
                return; // Don't open modal if loading failed
            } finally {
                isLoadingForm.value = false;
            }
        }

        showCreateModal.value = true;
        showModal.value = false; // Close the select modal
    };

    const closeCreateModal = () => {
        showCreateModal.value = false;
        showModal.value = true; // Re-open the select modal
    };

    // Helper function for create modal
    const getCreateRecordType = () => {
        if (props.field.typeDomain) {
            // Convert PascalCase to plural lowercase (e.g., BoatMake -> boatmakes)
            return props.field.typeDomain.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase() + 's';
        }
        return props.fieldKey.replace('_id', 's');
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
    
    // Initialize selectedRecordName from various sources
    watch(() => [props.record, props.fieldKey, props.enumOptions, props.modelValue], () => {
        if (props.modelValue && !selectedRecordName.value) {
            // 1. Try to get from eagerly loaded relationship
            if (props.record && props.fieldKey) {
                const relationshipName = props.fieldKey.replace('_id', '');
                const relatedRecord = props.record[relationshipName];
                
                if (relatedRecord && relatedRecord.display_name) {
                    selectedRecordName.value = relatedRecord.display_name;
                    return;
                }
            }
            
            // 2. Fallback to enumOptions
            if (props.enumOptions && props.enumOptions.length > 0) {
                const option = props.enumOptions.find(o => o.id === props.modelValue || o.value === props.modelValue);
                if (option) {
                    selectedRecordName.value = option.name || option.display_name || '';
                }
            }
        }
    }, { immediate: true });
    </script>
    
    <template>
        <div class="relative">
            <!-- Input Field -->
            <div class="relative">
                <input
                    :id="id"
                    type="text"
                    :value="selectedRecordDisplay"
                    @click="openModal"
                    readonly
                    :placeholder="`Select ${field.label}...`"
                    :disabled="disabled"
                    :tabindex="disabled ? -1 : 0"
                    class="input-style"
                    :class="disabled ? '' : 'cursor-pointer'"
                />
                <div v-if="!disabled" class="absolute inset-y-0 right-0 flex items-center pr-2 gap-1">
                    <!-- Clear Button -->
                    <button
                        v-if="selectedRecordId"
                        @click.stop="clearSelection"
                        type="button"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <!-- Open Modal Button -->
                    <button
                        @click.stop="openModal"
                        type="button"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>
            </div>
    
            <!-- Modal Overlay -->
            <div v-if="showModal && !disabled" @click="closeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
                <div @click.stop class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-lg shadow-xl dark:bg-gray-800 flex flex-col">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Select {{ field.label }}
                        </h3>
                        <div class="flex items-center space-x-2">
                            <!-- Add New Button -->
                            <button
                                v-if="field.addNew"
                                @click="openCreateModal"
                                type="button"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add New
                            </button>
                            <!-- Close Button -->
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
                    </div>
    
                    <!-- Modal Body -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <!-- Search Input -->
                        <div class="mb-3">
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
                                            {{ getRecordDisplayName(record) }}
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
                            <p class="mt-2">No {{ field.label }}s found</p>
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

            <!-- Create Modal -->
            <div v-if="showCreateModal && !disabled" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
                <div class="relative w-full max-w-4xl max-h-[90vh] bg-white rounded-lg shadow-xl dark:bg-gray-800 flex flex-col">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Create New {{ field.label }}
                        </h3>
                        <button
                            @click="closeCreateModal"
                            type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="isLoadingForm" class="flex justify-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div v-if="!createFormData && !isLoadingForm" class="text-center text-gray-500 py-8">
                            Form data not loaded
                        </div>
                        <div v-else-if="createFormData && !createFormData.formSchema" class="text-center text-red-500 py-8">
                            Form schema is empty - check if {{ createFormData.recordTitle }} has a form.json file
                        </div>
                        <Form
                            v-else-if="createFormData && createFormData.formSchema"
                            :schema="createFormData.formSchema"
                            :fields-schema="createFormData.fieldsSchema"
                            :record-type="createFormData.recordType"
                            :record-title="createFormData.recordTitle"
                            :enum-options="createFormData.enumOptions"
                            mode="create"
                            :prevent-redirect="true"
                            @created="handleRecordCreated"
                            @cancel="closeCreateModal"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Modal (when create=true) -->
        <div v-if="showEnhancedModal && !disabled" @keydown.escape="closeEnhancedModal" class="fixed inset-0 z-50 overflow-y-auto">
            <!-- Background overlay with blur -->
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm transition-opacity" @click="closeEnhancedModal"></div>

            <!-- Modal container -->
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Modal panel -->
                <div @click.stop class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl transform transition-all w-full max-w-4xl max-h-[90vh] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                                <span class="material-icons text-primary-600 dark:text-primary-400">{{ enhancedModalTab === 'existing' ? 'link' : 'add' }}</span>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ enhancedModalTab === 'existing' ? 'Select' : 'Create New' }} {{ field.label }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ enhancedModalTab === 'existing' ? 'Choose from existing records or create a new one' : 'Fill out the form below to create a new record' }}</p>
                            </div>
                        </div>
                        <button
                            @click="closeEnhancedModal"
                            type="button"
                            class="flex items-center justify-center w-10 h-10 rounded-full text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all"
                        >
                            <span class="material-icons">close</span>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="px-6 pt-4 border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex gap-1">
                            <button
                                @click="enhancedModalTab = 'existing'; fetchRecords(true)"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-all',
                                    enhancedModalTab === 'existing'
                                        ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                ]"
                            >
                                <span class="material-icons text-sm">link</span>
                                Select Existing
                            </button>
                            <button
                                @click="openCreateNewTab"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-all',
                                    enhancedModalTab === 'create'
                                        ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                ]"
                            >
                                <span class="material-icons text-sm">add</span>
                                Create New
                            </button>
                        </nav>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <!-- Select Existing Tab -->
                        <div v-if="enhancedModalTab === 'existing'" class="space-y-4">
                            <!-- Search -->
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">search</span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search records..."
                                    class="rounded-xl w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-transparent transition-all"
                                    @input="fetchRecords"
                                />
                            </div>

                            <!-- Search Results -->
                            <div class="space-y-3 min-h-[300px]">
                                <!-- Loading State -->
                                <div v-if="isLoading" class="text-center py-12">
                                    <span class="material-icons animate-spin text-primary-600 dark:text-primary-400 text-4xl">sync</span>
                                    <p class="text-gray-500 dark:text-gray-400 mt-2">Loading...</p>
                                </div>

                                <!-- No Results -->
                                <div v-else-if="records.length === 0" class="text-center py-12">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                                        <span class="material-icons text-gray-400 dark:text-gray-500 text-3xl">search_off</span>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">No records found</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">{{ searchQuery.trim() ? 'Try a different search term' : 'No records available' }}</p>
                                </div>

                                <!-- Results List -->
                                <template v-else>
                                    <div
                                        v-for="record in records"
                                        :key="record.id"
                                        class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl hover:border-primary-300 dark:hover:border-primary-600 hover:bg-primary-50/30 dark:hover:bg-primary-900/20 transition-all group"
                                    >
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <span class="material-icons text-gray-600 dark:text-gray-300">business</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                    {{ getRecordDisplayName(record) }}
                                                </h4>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">ID: {{ record.id }}</p>
                                            </div>
                                        </div>
                                        <button
                                            @click="selectRecordInEnhanced(record)"
                                            type="button"
                                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-primary-600 dark:bg-primary-600 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
                                        >
                                            <span class="material-icons text-sm">check</span>
                                            <span>Select</span>
                                        </button>
                                    </div>

                                    <!-- Pagination -->
                                    <div v-if="totalPages > 1" class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <button
                                            @click="prevPage"
                                            :disabled="currentPage === 1 || isLoading"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                        >
                                            <span class="material-icons text-sm">chevron_left</span>
                                            Previous
                                        </button>

                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            Page {{ currentPage }} of {{ totalPages }}
                                        </span>

                                        <button
                                            @click="nextPage"
                                            :disabled="currentPage === totalPages || isLoading"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                        >
                                            Next
                                            <span class="material-icons text-sm">chevron_right</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Create New Tab -->
                        <div v-if="enhancedModalTab === 'create'" class="space-y-4">
                            <div v-if="isLoadingForm" class="text-center py-12">
                                <span class="material-icons animate-spin text-primary-600 dark:text-primary-400 text-4xl">sync</span>
                                <p class="text-gray-500 dark:text-gray-400 mt-2">Loading form...</p>
                            </div>
                            <div v-else-if="!createFormData" class="text-center py-12">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                                    <span class="material-icons text-gray-400 dark:text-gray-500 text-4xl">error</span>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Unable to Load Form</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Please try again later</p>
                            </div>
                            <Form
                                v-else
                                :schema="createFormData.formSchema"
                                :fields-schema="createFormData.fieldsSchema"
                                :record-type="createFormData.recordType"
                                :record-title="createFormData.recordTitle"
                                :enum-options="createFormData.enumOptions"
                                mode="create"
                                :prevent-redirect="true"
                                @created="handleRecordCreated"
                                @cancel="enhancedModalTab = 'existing'"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
