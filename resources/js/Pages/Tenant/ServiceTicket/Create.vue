<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    formSchema: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        required: true,
    },
    enumOptions: {
        type: Object,
        default: () => ({}),
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
    ];

    if (currentStep.value === 1) {
        items.push({ label: 'Select Customer' });
    } else if (currentStep.value === 2) {
        items.push({ label: 'Select Asset' });
    } else {
        items.push({ label: 'Create Service Ticket' });
    }

    return items;
});

// ==============================
// Wizard State (3 steps)
// ==============================
const currentStep = ref(1);
const totalSteps = 3;

// Step 1: Customer selection
const selectedCustomer = ref(null);
const customerSearchQuery = ref('');
const customerRecords = ref([]);
const customerIsLoading = ref(false);
const showCreateCustomerForm = ref(false);

// New customer form
const newCustomerForm = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    company: '',
});

// Step 2: Asset Unit selection
const selectedAssetUnit = ref(null);
const assetSearchQuery = ref('');
const assetRecords = ref([]);
const assetIsLoading = ref(false);

// Final data to pass to the form
const initialFormData = ref(null);

// ==============================
// Step Progress
// ==============================
const stepProgress = computed(() => {
    return (currentStep.value / totalSteps) * 100;
});

// ==============================
// Customer Search & Selection (Step 1)
// ==============================
const fetchCustomers = async () => {
    customerIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'Customer');
        url.searchParams.append('per_page', '20');
        if (customerSearchQuery.value.trim()) {
            url.searchParams.append('search', customerSearchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Failed to fetch customers: ${response.status}`);
        const data = await response.json();
        customerRecords.value = data.records || data.data || data || [];
    } catch (error) {
        console.error('Error fetching customers:', error);
        customerRecords.value = [];
    } finally {
        customerIsLoading.value = false;
    }
};

const searchCustomers = () => {
    fetchCustomers();
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    // Also load full customer record with relationships for form initialization
    fetchCustomerDetails(customer.id).then(() => {
        goToAssetStep();
    });
};

const goToAssetStep = () => {
    currentStep.value = 2;
    assetSearchQuery.value = '';
    selectedAssetUnit.value = null;
    fetchAssets();
};

// ==============================
// Create New Customer
// ==============================
const createCustomer = async () => {
    if (!newCustomerForm.value.first_name || !newCustomerForm.value.last_name) return;

    customerIsLoading.value = true;
    try {
        const response = await fetch(route('customers.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                first_name: newCustomerForm.value.first_name,
                last_name: newCustomerForm.value.last_name,
                display_name: `${newCustomerForm.value.first_name} ${newCustomerForm.value.last_name}`,
                email: newCustomerForm.value.email || null,
                phone: newCustomerForm.value.phone || null,
                mobile: newCustomerForm.value.mobile || null,
                company: newCustomerForm.value.company || null,
            }),
        });

        if (!response.ok) {
            const errorData = await response.json();
            console.error('Failed to create customer:', errorData);
            return;
        }

        const data = await response.json();
        const newCustomer = data.record || data;

        if (newCustomer && newCustomer.id) {
            // Load full customer details for proper form initialization
            await fetchCustomerDetails(newCustomer.id);
            showCreateCustomerForm.value = false;
            goToAssetStep();
        } else {
            await fetchCustomers();
            const found = customerRecords.value.find(c =>
                c.display_name === `${newCustomerForm.value.first_name} ${newCustomerForm.value.last_name}`
            );
            if (found) {
                selectedCustomer.value = found;
                showCreateCustomerForm.value = false;
                goToAssetStep();
            }
        }
    } catch (error) {
        console.error('Error creating customer:', error);
    } finally {
        customerIsLoading.value = false;
    }
};

const toggleCreateCustomerForm = () => {
    showCreateCustomerForm.value = !showCreateCustomerForm.value;
    if (showCreateCustomerForm.value) {
        newCustomerForm.value = {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            mobile: '',
            company: '',
        };
    }
};

// ==============================
// Asset Unit Search & Selection (Step 2)
// ==============================
const fetchAssets = async () => {
    if (!selectedCustomer.value) return;

    assetIsLoading.value = true;
    try {
        const url = new URL(route('records.lookup'), window.location.origin);
        url.searchParams.append('type', 'assetunit');
        url.searchParams.append('per_page', '50');
        url.searchParams.append('filters', JSON.stringify([
            {
                'field': 'customer_id',
                'operator': 'equals',
                'value': selectedCustomer.value.id
            }
        ]));
        if (assetSearchQuery.value.trim()) {
            url.searchParams.append('search', assetSearchQuery.value.trim());
        }

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Failed to fetch assets: ${response.status}`);
        const data = await response.json();
        assetRecords.value = data.records || data.data || data || [];
    } catch (error) {
        console.error('Error fetching assets:', error);
        assetRecords.value = [];
    } finally {
        assetIsLoading.value = false;
    }
};

const searchAssets = () => {
    fetchAssets();
};

const selectAsset = (asset) => {
    selectedAssetUnit.value = asset;
    // Also load full asset record with relationships for form initialization
    fetchAssetDetails(asset.id).then(() => {
        proceedToTicketForm();
    });
};

const skipAssetStep = () => {
    selectedAssetUnit.value = null;
    proceedToTicketForm();
};

// ==============================
// Fetch Full Record Details
// ==============================
const fetchCustomerDetails = async (customerId) => {
    try {
        const response = await fetch(route('customers.show', customerId), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            selectedCustomer.value = data.record || data;
        }
    } catch (error) {
        console.error('Error fetching customer details:', error);
    }
};

const fetchAssetDetails = async (assetId) => {
    try {
        const response = await fetch(route('assetunits.show', assetId), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            selectedAssetUnit.value = data.record || data;
        }
    } catch (error) {
        console.error('Error fetching asset details:', error);
    }
};

// ==============================
// Proceed to Ticket Form (Step 3)
// ==============================
const proceedToTicketForm = () => {
    const data = {
        customer_id: selectedCustomer.value.id,
        customer: selectedCustomer.value, // Include full customer record
    };

    if (selectedAssetUnit.value) {
        data.asset_unit_id = selectedAssetUnit.value.id;
        data.asset_unit = selectedAssetUnit.value; // Include full asset record
    }

    initialFormData.value = data;
    currentStep.value = 3;
};

// ==============================
// Navigation
// ==============================
const goBackToCustomerStep = () => {
    currentStep.value = 1;
    initialFormData.value = null;
};

const goBackToAssetStep = () => {
    currentStep.value = 2;
    initialFormData.value = null;
    fetchAssets();
};

const handleCancelled = () => {
    router.visit(route('servicetickets.index'));
};

// Load customers on mount
fetchCustomers();
</script>

<template>
    <Head title="Create Service Ticket" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <!-- ==============================
             Step 1: Customer Selection
             ============================== -->
        <div v-if="currentStep === 1" class="max-w-4xl mx-auto space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden w-full">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white/20 text-white font-bold text-lg">
                            1
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">Select a Customer</h1>
                            <p class="text-blue-100 text-sm mt-0.5">Choose an existing customer or create a new one</p>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="flex items-center gap-2 mt-4">
                        <div class="flex-1 h-1.5 rounded-full bg-white/40">
                            <div class="h-full rounded-full bg-white transition-all" :style="{ width: stepProgress + '%' }"></div>
                        </div>
                        <span class="text-xs text-blue-100 font-medium">Step {{ currentStep }} of {{ totalSteps }}</span>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Toggle: Search / Create -->
                    <div class="flex gap-3 mb-6">
                        <button
                            @click="showCreateCustomerForm = false"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-medium transition-all border',
                                !showCreateCustomerForm
                                    ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300'
                                    : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                            ]"
                        >
                            <span class="material-icons text-base">search</span>
                            Select Existing
                        </button>
                        <button
                            @click="toggleCreateCustomerForm"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-medium transition-all border',
                                showCreateCustomerForm
                                    ? 'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700 text-green-700 dark:text-green-300'
                                    : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
                            ]"
                        >
                            <span class="material-icons text-base">person_add</span>
                            Create New
                        </button>
                    </div>

                    <!-- Search Existing Customer -->
                    <div v-if="!showCreateCustomerForm">
                        <div class="relative mb-4">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <span class="material-icons text-gray-400">search</span>
                            </div>
                            <input
                                v-model="customerSearchQuery"
                                @input="searchCustomers"
                                @keyup.enter="searchCustomers"
                                type="text"
                                placeholder="Search customers by name, company, email..."
                                class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            />
                        </div>

                        <div v-if="customerIsLoading" class="flex justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        </div>

                        <div v-else-if="customerRecords.length > 0" class="space-y-2 max-h-96 overflow-y-auto">
                            <button
                                v-for="customer in customerRecords"
                                :key="customer.id"
                                @click="selectCustomer(customer)"
                                type="button"
                                class="w-full text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all group"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">
                                            {{ customer.display_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-3">
                                            <span v-if="customer.company">{{ customer.company }}</span>
                                            <span v-if="customer.email">{{ customer.email }}</span>
                                            <span v-if="customer.phone">{{ customer.phone }}</span>
                                        </div>
                                    </div>
                                    <span class="material-icons text-gray-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all">
                                        arrow_forward
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div v-else class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">person_search</span>
                            <p class="text-gray-500 dark:text-gray-400 mb-1">
                                {{ customerSearchQuery.trim() ? 'No customers found matching your search' : 'No customers available' }}
                            </p>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ customerSearchQuery.trim() ? 'Try a different search term or create a new customer' : 'Create a new customer to get started' }}
                            </p>
                        </div>
                    </div>

                    <!-- Create New Customer Form -->
                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="newCustomerForm.first_name"
                                    type="text"
                                    placeholder="First name"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="newCustomerForm.last_name"
                                    type="text"
                                    placeholder="Last name"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company</label>
                            <input
                                v-model="newCustomerForm.company"
                                type="text"
                                placeholder="Company name (optional)"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                <input
                                    v-model="newCustomerForm.email"
                                    type="email"
                                    placeholder="email@example.com"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                                <input
                                    v-model="newCustomerForm.phone"
                                    type="tel"
                                    placeholder="(555) 123-4567"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mobile</label>
                            <input
                                v-model="newCustomerForm.mobile"
                                type="tel"
                                placeholder="(555) 987-6543"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                            <button
                                @click="showCreateCustomerForm = false"
                                type="button"
                                class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="createCustomer"
                                type="button"
                                :disabled="!newCustomerForm.first_name || !newCustomerForm.last_name || customerIsLoading"
                                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <span v-if="customerIsLoading" class="material-icons text-sm animate-spin">refresh</span>
                                <span v-else class="material-icons text-sm">person_add</span>
                                {{ customerIsLoading ? 'Creating...' : 'Create Customer & Continue' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             Step 2: Asset Unit Selection
             ============================== -->
        <div v-else-if="currentStep === 2" class="max-w-4xl mx-auto space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white/20 text-white font-bold text-lg">
                            2
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">Select an Asset</h1>
                            <p class="text-blue-100 text-sm mt-0.5">Choose an asset associated with {{ selectedCustomer?.display_name }}, or skip this step</p>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="flex items-center gap-2 mt-4">
                        <div class="flex-1 h-1.5 rounded-full bg-white/40">
                            <div class="h-full rounded-full bg-white transition-all" :style="{ width: stepProgress + '%' }"></div>
                        </div>
                        <span class="text-xs text-blue-100 font-medium">Step {{ currentStep }} of {{ totalSteps }}</span>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Selected Customer Badge -->
                    <div class="mb-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <span class="material-icons text-blue-600 dark:text-blue-400 text-sm">person</span>
                        <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                            Customer: {{ selectedCustomer?.display_name }}
                        </span>
                        <button @click="goBackToCustomerStep" class="ml-2 text-blue-500 hover:text-blue-700 dark:hover:text-blue-300">
                            <span class="material-icons text-sm">edit</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative mb-4">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <span class="material-icons text-gray-400">search</span>
                        </div>
                        <input
                            v-model="assetSearchQuery"
                            @input="searchAssets"
                            @keyup.enter="searchAssets"
                            type="text"
                            placeholder="Search assets by name, serial number, HIN..."
                            class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                    </div>

                    <!-- Loading -->
                    <div v-if="assetIsLoading" class="flex justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>

                    <!-- Asset List -->
                    <div v-else-if="assetRecords.length > 0" class="space-y-2 max-h-96 overflow-y-auto">
                        <button
                            v-for="asset in assetRecords"
                            :key="asset.id"
                            @click="selectAsset(asset)"
                            type="button"
                            class="w-full text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all group"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">
                                        {{ asset.display_name }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-3">
                                        <span v-if="asset.serial_number" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">tag</span>
                                            {{ asset.serial_number }}
                                        </span>
                                        <span v-if="asset.hin" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">directions_boat</span>
                                            {{ asset.hin }}
                                        </span>
                                        <span v-if="asset.sku" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">inventory_2</span>
                                            {{ asset.sku }}
                                        </span>
                                    </div>
                                </div>
                                <span class="material-icons text-gray-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all">
                                    arrow_forward
                                </span>
                            </div>
                        </button>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
                        <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">directions_boat</span>
                        <p class="text-gray-500 dark:text-gray-400 mb-1">
                            {{ assetSearchQuery.trim() ? 'No assets found matching your search' : 'No assets found for this customer' }}
                        </p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            You can skip this step and add an asset later, or select one from the ticket form.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="pt-6 border-t border-gray-200 dark:border-gray-700 mt-6 flex justify-between">
                        <button
                            @click="goBackToCustomerStep"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-sm">arrow_back</span>
                            Back
                        </button>
                        <button
                            @click="skipAssetStep"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                        >
                            Skip & Continue
                            <span class="material-icons text-sm">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==============================
             Step 3: Service Ticket Form
             ============================== -->
        <div v-else-if="currentStep === 3">
            <!-- Back Button & Selection Summary -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <button
                    @click="goBackToAssetStep"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                >
                    <span class="material-icons text-base">arrow_back</span>
                    Back to Asset Selection
                </button>

                <div v-if="selectedCustomer" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <span class="material-icons text-blue-600 dark:text-blue-400 text-sm">person</span>
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                        {{ selectedCustomer.display_name }}
                    </span>
                </div>

                <div v-if="selectedAssetUnit" class="inline-flex items-center gap-2 px-4 py-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <span class="material-icons text-green-600 dark:text-green-400 text-sm">directions_boat</span>
                    <span class="text-sm font-medium text-green-700 dark:text-green-300">
                        {{ selectedAssetUnit.display_name }}
                    </span>
                </div>
            </div>

            <ServiceTicketForm
                :record="initialFormData"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                mode="create"
                @cancelled="handleCancelled"
            />
        </div>
    </TenantLayout>
</template>
