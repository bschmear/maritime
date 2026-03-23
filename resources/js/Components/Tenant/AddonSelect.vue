<script setup>
/**
 * Add-on picker modeled after RecordSelect: catalog lookup + “Create new” via the same
 * Form/select-form flow as other domains. Selecting or creating always persists the
 * catalog row in `addons` and emits a line-item payload with `addon_id` set.
 */
import { ref, watch } from 'vue';
import axios from 'axios';
import Form from '@/Components/Tenant/Form.vue';

const props = defineProps({
    /** When true, the full-screen picker is visible (v-model:open). */
    open: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /** Ring / button accent: matches Estimate (primary) or Transaction (blue). */
    accent: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'blue'].includes(v),
    },
});

const emit = defineEmits(['update:open', 'picked', 'close']);

const DOMAIN = 'AddOn';
/** Must match GeneralController lookup (studly('AddOn') → AddOn domain). */
const LOOKUP_TYPE = 'AddOn';

const searchQuery = ref('');
const records = ref([]);
const isLoading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = 10;

const enhancedModalTab = ref('existing');
const isLoadingForm = ref(false);
const createFormData = ref(null);

const accentRing = () => (props.accent === 'blue' ? 'focus:ring-blue-500' : 'focus:ring-primary-500');
const accentBorderHover = () =>
    props.accent === 'blue'
        ? 'hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20'
        : 'hover:border-primary-400 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20';
const accentBtn = () =>
    props.accent === 'blue'
        ? 'bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700'
        : 'bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700';
const accentText = () =>
    props.accent === 'blue'
        ? 'text-blue-600 dark:text-blue-400'
        : 'text-primary-600 dark:text-primary-400';
const accentTabActive = () =>
    props.accent === 'blue'
        ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
        : 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400';
const accentSpin = () => (props.accent === 'blue' ? 'text-blue-600' : 'text-primary-600');

const getRecordDisplayName = (record) => {
    if (!record) return '';
    if (record.display_name) return record.display_name;
    if (record.name) return record.name;
    return `Add-on #${record.id}`;
};

const closeModal = () => {
    emit('update:open', false);
    emit('close');
    searchQuery.value = '';
    currentPage.value = 1;
    enhancedModalTab.value = 'existing';
};

const buildLinePayload = (record) => ({
    id: null,
    addon_id: record.id,
    name: record.name ?? getRecordDisplayName(record),
    price: Number(record.default_price) || 0,
    quantity: 1,
    notes: '',
    taxable: true,
});

const emitPicked = (record) => {
    emit('picked', buildLinePayload(record));
    closeModal();
};

const fetchAddonById = async (id) => {
    const res = await axios.get(route('addons.show', { addon: id }), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return res.data?.record;
};

const fetchRecords = async () => {
    isLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', LOOKUP_TYPE);
        url.searchParams.append('page', currentPage.value);
        url.searchParams.append('per_page', perPage);
        // Alphabetical catalog list (backend maps display_name → name for AddOn)
        url.searchParams.append('order_by', 'display_name');
        url.searchParams.append('order_direction', 'asc');
        if (searchQuery.value.trim()) {
            url.searchParams.append('search', searchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Failed to fetch add-ons: ${response.status}`);
        const data = await response.json();
        records.value = data.records || [];
        totalPages.value = data.meta?.last_page || 1;
    } catch (err) {
        console.error(err);
        records.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(searchQuery, async () => {
    currentPage.value = 1;
    await fetchRecords();
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && !props.disabled) {
            enhancedModalTab.value = 'existing';
            searchQuery.value = '';
            currentPage.value = 1;
            await fetchRecords();
        }
    },
);

const selectRecord = (record) => {
    emitPicked(record);
};

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

const openCreateNewTab = async () => {
    enhancedModalTab.value = 'create';
    if (!createFormData.value) {
        isLoadingForm.value = true;
        try {
            const response = await axios.get(route('records.select-form', { type: DOMAIN }), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            createFormData.value = response.data;
        } catch (error) {
            console.error('Error loading add-on form:', error);
        } finally {
            isLoadingForm.value = false;
        }
    }
};

const handleRecordCreated = async (recordId) => {
    try {
        const record = await fetchAddonById(recordId);
        if (record) {
            emitPicked(record);
            return;
        }
    } catch (e) {
        console.error('Could not load new add-on:', e);
    }
    await fetchRecords();
    closeModal();
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && !disabled"
            class="fixed inset-0 z-[55] overflow-y-auto"
            @keydown.escape="closeModal"
        >
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm transition-opacity" @click="closeModal"></div>

            <div class="flex min-h-screen items-center justify-center p-4">
                <div
                    class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800"
                    @click.stop
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg"
                                :class="
                                    accent === 'blue'
                                        ? 'bg-blue-100 dark:bg-blue-900/30'
                                        : 'bg-primary-100 dark:bg-primary-900/30'
                                "
                            >
                                <span class="material-icons" :class="accentText()">{{
                                    enhancedModalTab === 'existing' ? 'extension' : 'add'
                                }}</span>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ enhancedModalTab === 'existing' ? 'Select' : 'Create' }} add-on
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Choose a catalog add-on or create a new one (saved to your add-ons list).
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition-all hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            @click="closeModal"
                        >
                            <span class="material-icons">close</span>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-gray-200 px-6 pt-4 dark:border-gray-700">
                        <nav class="flex gap-1">
                            <button
                                type="button"
                                :class="[
                                    'flex items-center gap-2 rounded-t-lg px-4 py-2.5 text-sm font-medium transition-all',
                                    enhancedModalTab === 'existing'
                                        ? `bg-white dark:bg-gray-800 ${accentTabActive()}`
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700/50',
                                ]"
                                @click="enhancedModalTab = 'existing'; fetchRecords()"
                            >
                                <span class="material-icons text-sm">link</span>
                                Select existing
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'flex items-center gap-2 rounded-t-lg px-4 py-2.5 text-sm font-medium transition-all',
                                    enhancedModalTab === 'create'
                                        ? `bg-white dark:bg-gray-800 ${accentTabActive()}`
                                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700/50',
                                ]"
                                @click="openCreateNewTab"
                            >
                                <span class="material-icons text-sm">add</span>
                                Create new
                            </button>
                        </nav>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto ">
                        <!-- Existing -->
                        <div v-if="enhancedModalTab === 'existing'" class="space-y-4 p-4">
                            <div class="relative">
                                <span
                                    class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                                    >search</span
                                >
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search add-ons by name..."
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-10 pr-4 text-gray-900 placeholder-gray-400 transition-all focus:border-transparent focus:outline-none focus:ring-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                                    :class="accentRing()"
                                />
                            </div>

                            <div class="min-h-[280px] space-y-3">
                                <div v-if="isLoading" class="py-12 text-center">
                                    <svg
                                        class="mx-auto h-8 w-8 animate-spin"
                                        :class="accentSpin()"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400">Loading…</p>
                                </div>

                                <div v-else-if="records.length === 0" class="py-12 text-center">
                                    <p class="font-medium text-gray-500 dark:text-gray-400">No add-ons found</p>
                                    <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                        {{ searchQuery.trim() ? 'Try a different search' : 'Create a new add-on in the other tab' }}
                                    </p>
                                </div>

                                <template v-else>
                                    <div
                                        v-for="record in records"
                                        :key="record.id"
                                        class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 transition-all dark:border-gray-700 dark:bg-gray-800"
                                        :class="accentBorderHover()"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <h4 class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ getRecordDisplayName(record) }}
                                            </h4>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                <span v-if="record.default_price != null">${{ Number(record.default_price).toFixed(2) }}</span>
                                                <span v-if="record.description" class="ml-2">{{ record.description }}</span>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            :class="[
                                                'ml-3 flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50',
                                                accentBtn(),
                                            ]"
                                            @click="selectRecord(record)"
                                        >
                                            <span class="material-icons text-sm">check</span>
                                            Select
                                        </button>
                                    </div>

                                    <div
                                        v-if="totalPages > 1"
                                        class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700"
                                    >
                                        <button
                                            type="button"
                                            :disabled="currentPage === 1 || isLoading"
                                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                            @click="prevPage"
                                        >
                                            <span class="material-icons text-sm">chevron_left</span>
                                            Previous
                                        </button>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            Page {{ currentPage }} of {{ totalPages }}
                                        </span>
                                        <button
                                            type="button"
                                            :disabled="currentPage === totalPages || isLoading"
                                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                            @click="nextPage"
                                        >
                                            Next
                                            <span class="material-icons text-sm">chevron_right</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Create -->
                        <div v-if="enhancedModalTab === 'create'" class="space-y-4">
                            <div v-if="isLoadingForm" class="py-12 text-center">
                                <svg
                                    class="mx-auto h-8 w-8 animate-spin"
                                    :class="accentSpin()"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">Loading form…</p>
                            </div>
                            <div v-else-if="!createFormData" class="py-12 text-center text-gray-500">Could not load form.</div>
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

                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-700/50">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
