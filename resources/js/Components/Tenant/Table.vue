<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import Form from '@/Components/Tenant/Form.vue';

const props = defineProps({
    records: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        default: null,
    },
    formSchema: {
        type: Object,
        default: null,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    recordType: {
        type: String,
        default: '',
    },
    recordTitle: {
        type: String,
        default: '',
    },
});

const showCreateModal = ref(false);
const showSuccessModal = ref(false);
const showViewModal = ref(false);
const createdRecordId = ref(null);
const selectedRecords = ref(new Set());
const selectAll = ref(false);
const selectedRecord = ref(null);

const columns = computed(() => {
    if (!props.schema || !props.schema.columns) {
        return [];
    }
    return props.schema.columns;
});

const isFirstPage = computed(() => {
    return props.records.current_page === 1;
});

const hasRecords = computed(() => {
    return props.records.data && props.records.data.length > 0;
});

const showEmptyState = computed(() => {
    return !hasRecords.value && isFirstPage.value;
});

const getEnumOption = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (!fieldDef || !fieldDef.enum || !value) {
        return null;
    }
    
    const enumOptions = props.enumOptions[fieldDef.enum];
    if (!enumOptions || !Array.isArray(enumOptions)) {
        return null;
    }
    
    return enumOptions.find(opt => opt.id === value || opt.value === value) || null;
};

const getEnumLabel = (fieldKey, value) => {
    const option = getEnumOption(fieldKey, value);
    return option ? option.name : value;
};

const getColorClass = (color) => {
    if (!color) return '';
    // Map color names to Tailwind CSS classes
    const colorMap = {
        'blue': 'bg-blue-500',
        'green': 'bg-green-500',
        'teal': 'bg-teal-500',
        'gray': 'bg-gray-500',
        'purple': 'bg-purple-500',
        'yellow': 'bg-yellow-500',
        'orange': 'bg-orange-500',
        'red': 'bg-red-500',
        'pink': 'bg-pink-500',
        'indigo': 'bg-indigo-500',
    };
    return colorMap[color] || `bg-${color}-500`;
};

const getRecordValue = (record, column) => {
    const key = typeof column === 'string' ? column : column.key;
    const rawValue = record[key] ?? '';
    
    // Check if this field has an enum definition
    const fieldDef = props.fieldsSchema[key];
    if (fieldDef && fieldDef.enum && rawValue !== '') {
        return getEnumLabel(key, rawValue);
    }
    
    return rawValue;
};

const hasEnumColor = (column, record) => {
    const key = typeof column === 'string' ? column : column.key;
    const rawValue = record[key] ?? '';
    const option = getEnumOption(key, rawValue);
    return option && option.color;
};

const getColumnLabel = (column) => {
    // Use label from column definition if available
    if (column.label) {
        return column.label;
    }
    // Fallback to fieldsSchema label
    if (props.fieldsSchema[column.key]?.label) {
        return props.fieldsSchema[column.key].label;
    }
    // Fallback to key
    return column.key;
};

const getShowUrl = (recordId) => {
    return route(`${props.recordType}.show`, recordId);
};

const getEditUrl = (recordId) => {
    return route(`${props.recordType}.edit`, recordId);
};

const handleRecordCreated = (recordId) => {
    createdRecordId.value = recordId;
    showCreateModal.value = false;
    showSuccessModal.value = true;
};

const viewRecord = () => {
    if (createdRecordId.value) {
        window.location.href = getShowUrl(createdRecordId.value);
    }
};

const backToPage = () => {
    showSuccessModal.value = false;
    createdRecordId.value = null;
};

const toggleSelectAll = () => {
    if (selectAll.value) {
        props.records.data.forEach(record => selectedRecords.value.add(record.id));
    } else {
        selectedRecords.value.clear();
    }
};

const toggleRecordSelection = (recordId) => {
    if (selectedRecords.value.has(recordId)) {
        selectedRecords.value.delete(recordId);
    } else {
        selectedRecords.value.add(recordId);
    }
    // Update select all checkbox state
    selectAll.value = selectedRecords.value.size === props.records.data.length && props.records.data.length > 0;
};

const isRecordSelected = (recordId) => {
    return selectedRecords.value.has(recordId);
};

const handleViewOnPage = (record) => {
    // console.log(record);
    selectedRecord.value = record;
    showViewModal.value = true;
};

const handleNavigateToItem = (recordId) => {
    window.location.href = getShowUrl(recordId);
};

const closeViewModal = () => {
    showViewModal.value = false;
    selectedRecord.value = null;
};

const handleRecordUpdated = (updatedRecord) => {
    // Find and update the record in the table
    if (updatedRecord && updatedRecord.id) {
        const recordIndex = props.records.data.findIndex(r => r.id === updatedRecord.id);
        if (recordIndex !== -1) {
            // Update the record in place
            Object.assign(props.records.data[recordIndex], updatedRecord);
        }
    }
    // Close the modal
    closeViewModal();
};

// Watch for records data changes to update select all state
watch(() => props.records.data, () => {
    if (props.records.data.length === 0) {
        selectAll.value = false;
    } else {
        selectAll.value = selectedRecords.value.size === props.records.data.length;
    }
}, { immediate: true });
</script>

<template>
    <section class="bg-gray-50 dark:bg-gray-900 w-full flex flex-col">
        <div class="w-full grow">
            <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden h-full flex flex-col">
                <div v-if="!showEmptyState"  class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                    <div class="w-full md:w-1/2">
                        <form class="w-full md:max-w-sm flex-1 md:mr-4">
                            <label for="default-search" class="text-sm font-medium text-gray-900 sr-only dark:text-white">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg aria-hidden="true" class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="search" id="default-search" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Search..." required="">
                                <button type="submit" class="text-white absolute right-0 bottom-0 top-0 bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-r-lg text-sm px-4 py-2 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Search</button>
                            </div>
                        </form>
                    </div>
                    <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                        <button
                            @click="showCreateModal = true"
                            class="flex items-center justify-center text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800"
                        >
                            <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add {{ recordTitle }}
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="showEmptyState" class="flex flex-col items-center justify-center py-16 px-4 h-full">
                    <div class="flex items-center justify-center w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                        <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                        No {{ recordType }} yet
                    </h3>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm">
                        Get started by creating your first {{ recordTitle }} to begin managing your data.
                    </p>
                    <button
                        @click="showCreateModal = true"
                        class="flex items-center justify-center text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800"
                    >
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Create your first {{ recordTitle }}
                    </button>
                </div>

                <!-- Table with Data -->
                <div v-else class="overflow-x-auto grow">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3 w-12">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                </th>
                                <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3">
                                    {{ getColumnLabel(column) }}
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 w-20 sticky right-0 z-10
                                           bg-gradient-to-r from-gray-50/0 to-gray-50/100
                                           dark:from-gray-700/0 dark:to-gray-700/100">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in records.data" :key="record.id" class="border-b dark:border-gray-700">
                                <td class="px-4 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="isRecordSelected(record.id)"
                                        @change="toggleRecordSelection(record.id)"
                                        class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                </td>
                                <td v-for="column in columns" :key="column.key" class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    <template v-if="column.key === 'id'">
                                        {{ getRecordValue(record, column) }}
                                    </template>
                                    <template v-else-if="hasEnumColor(column, record)">
                                        <div class="flex items-center">
                                            <div
                                                :class="[getColorClass(getEnumOption(column.key, record[column.key])?.color), 'w-3 h-3 mr-2 border rounded-full']"
                                            ></div>
                                            {{ getRecordValue(record, column) }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        {{ getRecordValue(record, column) }}
                                    </template>
                                </td>
                                <td class="px-4 py-2 sticky right-0  z-10
                                            bg-gradient-to-r from-gray-50/0 to-gray-50/100
                                            dark:from-gray-8800/0 dark:to-gray-800/100">
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click="handleViewOnPage(record)"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                            title="View on page"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="handleNavigateToItem(record.id)"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                            title="Navigate to item"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!hasRecords">
                                <td :colspan="columns.length + 3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No records found for this page
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav v-if="records.links && records.links.length > 3" class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-4" aria-label="Table navigation">
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.from }}</span>
                        to
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.to }}</span>
                        of
                        <span class="font-semibold text-gray-900 dark:text-white">{{ records.total }}</span>
                    </span>
                    <div class="inline-flex items-stretch -space-x-px">
                        <template v-for="(link, index) in records.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'flex items-center justify-center text-sm py-2 px-3 leading-tight',
                                    link.active
                                        ? 'z-10 text-primary-600 bg-primary-50 border border-primary-300 hover:bg-primary-100 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white',
                                    index === 0 ? 'rounded-l-lg' : '',
                                    index === records.links.length - 1 ? 'rounded-r-lg' : ''
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                :class="[
                                    'flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400',
                                    index === 0 ? 'rounded-l-lg' : '',
                                    index === records.links.length - 1 ? 'rounded-r-lg' : ''
                                ]"
                            />
                        </template>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="4xl">
            <!-- Modal header (fixed) -->
            <div class="flex items-start justify-between p-4 border-b dark:border-gray-700 flex-shrink-0">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Create {{ recordTitle }}
                </h3>
                <button
                    @click="showCreateModal = false"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body (scrollable) -->
            <div class="overflow-y-auto flex-1">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    :enum-options="enumOptions"
                    mode="create"
                    :prevent-redirect="true"
                    @created="handleRecordCreated"
                    @submit="() => {}"
                    @cancel="showCreateModal = false"
                />
            </div>
        </Modal>

        <!-- Success Modal -->
        <Modal :show="showSuccessModal" @close="backToPage" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    Record Created
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ recordTitle }} has been successfully created.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        @click="viewRecord"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    >
                        View Record
                    </button>
                    <button
                        @click="backToPage"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Back to Page
                    </button>
                </div>
            </div>
        </Modal>

        <!-- View/Edit Modal -->
        <Modal :show="showViewModal" @close="closeViewModal" max-width="4xl">
            <!-- Modal header (fixed) -->
            <div class="flex items-start justify-between p-4 border-b dark:border-gray-700 flex-shrink-0">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ selectedRecord ? `View ${recordTitle}` : '' }}
                </h3>
                <button
                    @click="closeViewModal"
                    type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body (scrollable) -->
            <div class="overflow-y-auto flex-1">
                <Form
                    v-if="selectedRecord"
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record="selectedRecord"
                    :record-type="recordType"
                    :record-title="recordTitle"
                    :enum-options="enumOptions"
                    mode="edit"
                    :prevent-redirect="true"
                    @updated="handleRecordUpdated"
                    @submit="closeViewModal"
                    @cancel="closeViewModal"
                />
            </div>
        </Modal>
    </section>
</template>
