<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

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
    transactionId: {
        type: Number,
        default: null,
    },
    /** When present (from ?transaction_id= or ?asset_unit_id=), prefill the create wizard. */
    transactionBootstrap: {
        type: Object,
        default: null,
    },
    defaultSubsidiaryId: {
        type: Number,
        default: null,
    },
});

const createBootstrap = computed(() => props.transactionBootstrap);

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

// Step 1: Customer selection (RecordSelect + same nested customer/contact shape as ServiceTicketForm)
const selectedCustomer = ref(null);
const customerId = ref(null);
const showCreateCustomerForm = ref(false);
const customerIsLoading = ref(false);
const customerCreateError = ref('');
/** 'customer' creates contact + customer profile; 'contact' creates contact only. */
const newPersonSaveAs = ref('customer');
const pendingContactId = ref(null);
const pendingContactName = ref('');
/** Suppresses auto-advance when re-syncing customerId after navigating back to step 1 */
const skipCustomerIdAdvance = ref(false);

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

/** While applying createBootstrap on first paint (avoids a blank step 1 flash). */
const wizardInitLoading = ref(Boolean(
    createBootstrap.value?.customer_id || (createBootstrap.value?.asset_units?.length > 0),
));

/** Asset rows from the deal; reused when returning to step 2 from the form. */
const prefetchedTransactionAssetUnits = ref([]);

// ==============================
// Step Progress
// ==============================
const stepProgress = computed(() => {
    return (currentStep.value / totalSteps) * 100;
});

// Parent `record` stub for RecordSelect (customer + contact for display_name resolution)
const wizardRecord = computed(() => {
    const c = selectedCustomer.value;
    if (!c?.id) {
        return {};
    }
    const contact = c.contact;
    return {
        customer: {
            id: c.id,
            display_name: c.display_name,
            contact: contact
                ? { id: contact.id, display_name: contact.display_name }
                : null,
        },
    };
});

const customerField = computed(
    () => props.fieldsSchema?.customer_id || {
        type: 'record',
        typeDomain: 'Customer',
        label: 'Customer',
        required: true,
        create: true,
    },
);

const goToAssetStep = () => {
    currentStep.value = 2;
    assetSearchQuery.value = '';
    selectedAssetUnit.value = null;
    if (prefetchedTransactionAssetUnits.value.length) {
        assetRecords.value = prefetchedTransactionAssetUnits.value;
    } else {
        fetchAssets();
    }
};

/**
 * When customer is chosen in RecordSelect (list pick or "create new" in the picker),
 * v-model updates. Load full customer (contact, etc.) like RecordController + step 2.
 */
watch(customerId, async (id) => {
    if (skipCustomerIdAdvance.value) {
        return;
    }
    if (currentStep.value !== 1) {
        return;
    }
    if (!id) {
        selectedCustomer.value = null;
        return;
    }
    const requested = id;
    await fetchCustomerDetails(requested);
    if (customerId.value !== requested || currentStep.value !== 1) {
        return;
    }
    if (selectedCustomer.value?.id == requested) {
        goToAssetStep();
    }
});

// ==============================
// Create New Contact / Customer
// ==============================
const buildNewPersonPayload = () => ({
    first_name: newCustomerForm.value.first_name,
    last_name: newCustomerForm.value.last_name,
    display_name: `${newCustomerForm.value.first_name} ${newCustomerForm.value.last_name}`.trim(),
    email: newCustomerForm.value.email || null,
    phone: newCustomerForm.value.phone || null,
    mobile: newCustomerForm.value.mobile || null,
    company: newCustomerForm.value.company || null,
});

const postJson = async (url, payload) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));

    return { response, data };
};

const formatCreateError = (data, fallback) => {
    const subsidiaryError = data?.errors?.subsidiary_id?.[0];
    const firstFieldError = Object.values(data?.errors || {}).flat()?.[0];

    return subsidiaryError || firstFieldError || data?.message || fallback;
};

const applyCreatedCustomer = (newCustomer) => {
    if (newCustomer?.id) {
        pendingContactId.value = null;
        pendingContactName.value = '';
        customerId.value = newCustomer.id;
        showCreateCustomerForm.value = false;
        return true;
    }

    customerCreateError.value = 'Customer was created but no id was returned. Try selecting them from the list.';

    return false;
};

const createCustomerProfile = async (extra = {}) => {
    const payload = { ...buildNewPersonPayload(), ...extra };

    if (!payload.subsidiary_id && props.defaultSubsidiaryId) {
        payload.subsidiary_id = props.defaultSubsidiaryId;
    }

    const { response, data } = await postJson(route('customers.store'), payload);

    if (!response.ok) {
        customerCreateError.value = formatCreateError(
            data,
            'Could not create customer. Add a subsidiary under Settings if none exists.',
        );
        console.error('Failed to create customer:', data);

        return false;
    }

    return applyCreatedCustomer(data.record || data);
};

const submitNewPerson = async () => {
    if (!newCustomerForm.value.first_name || !newCustomerForm.value.last_name) {
        return;
    }

    customerCreateError.value = '';
    customerIsLoading.value = true;

    try {
        if (newPersonSaveAs.value === 'contact') {
            const { response, data } = await postJson(route('contacts.store'), buildNewPersonPayload());

            if (!response.ok) {
                customerCreateError.value = formatCreateError(data, 'Could not create contact.');
                console.error('Failed to create contact:', data);

                return;
            }

            const contact = data.record || data;

            if (contact?.id) {
                pendingContactId.value = contact.id;
                pendingContactName.value = contact.display_name || buildNewPersonPayload().display_name;
            } else {
                customerCreateError.value = 'Contact was created but no id was returned.';
            }

            return;
        }

        await createCustomerProfile();
    } catch (error) {
        customerCreateError.value = 'Network error while saving. Please try again.';
        console.error('Error saving person:', error);
    } finally {
        customerIsLoading.value = false;
    }
};

const promotePendingContactToCustomer = async () => {
    if (!pendingContactId.value) {
        return;
    }

    customerCreateError.value = '';
    customerIsLoading.value = true;

    try {
        const payload = {
            contact_id: pendingContactId.value,
        };

        if (props.defaultSubsidiaryId) {
            payload.subsidiary_id = props.defaultSubsidiaryId;
        }

        await createCustomerProfile(payload);
    } catch (error) {
        customerCreateError.value = 'Network error while creating customer profile. Please try again.';
        console.error('Error promoting contact to customer:', error);
    } finally {
        customerIsLoading.value = false;
    }
};

const clearPendingContact = () => {
    pendingContactId.value = null;
    pendingContactName.value = '';
    customerCreateError.value = '';
};

const toggleCreateCustomerForm = () => {
    showCreateCustomerForm.value = !showCreateCustomerForm.value;
    if (showCreateCustomerForm.value) {
        customerCreateError.value = '';
        pendingContactId.value = null;
        pendingContactName.value = '';
        newPersonSaveAs.value = 'customer';
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

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const searchAssets = debounce(() => {
    fetchAssets();
}, 300);

const variantLabel = (unit) => {
    const v = unit.asset_variant;
    return (v?.display_name || v?.name || '').trim();
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

    if (props.transactionId) {
        data.transaction_id = props.transactionId;
    }

    const boot = createBootstrap.value;
    if (boot?.subsidiary_id != null && boot.subsidiary_id !== '') {
        data.subsidiary_id = Number(boot.subsidiary_id);
    }
    if (boot?.location_id != null && boot.location_id !== '') {
        data.location_id = Number(boot.location_id);
    }
    if (boot?.subsidiary?.id != null) {
        data.subsidiary = { id: boot.subsidiary.id, display_name: boot.subsidiary.display_name ?? '' };
    }
    if (boot?.location?.id != null) {
        data.location = { id: boot.location.id, display_name: boot.location.display_name ?? '' };
    }

    initialFormData.value = data;
    currentStep.value = 3;
};

// ==============================
// Navigation
// ==============================
const goBackToCustomerStep = async () => {
    skipCustomerIdAdvance.value = true;
    currentStep.value = 1;
    initialFormData.value = null;
    if (selectedCustomer.value?.id) {
        customerId.value = selectedCustomer.value.id;
    }
    await nextTick();
    skipCustomerIdAdvance.value = false;
};

const goBackToAssetStep = () => {
    currentStep.value = 2;
    initialFormData.value = null;
    if (prefetchedTransactionAssetUnits.value.length) {
        assetRecords.value = prefetchedTransactionAssetUnits.value;
    } else {
        fetchAssets();
    }
};

const handleCancelled = () => {
    router.visit(route('servicetickets.index'));
};

const applyCreateBootstrap = async () => {
    const boot = createBootstrap.value;
    if (!boot) {
        return;
    }

    const units = Array.isArray(boot.asset_units) ? boot.asset_units : [];
    prefetchedTransactionAssetUnits.value = units;

    if (!boot.customer_id && units.length === 0) {
        return;
    }

    wizardInitLoading.value = true;
    try {
        if (boot.customer_id) {
            skipCustomerIdAdvance.value = true;
            customerId.value = boot.customer_id;
            await fetchCustomerDetails(boot.customer_id);
            await nextTick();
            skipCustomerIdAdvance.value = false;

            if (!selectedCustomer.value?.id) {
                currentStep.value = 1;
                return;
            }
        }

        assetRecords.value = units;

        if (units.length > 0) {
            const first = units[0];
            selectedAssetUnit.value = first;
            await fetchAssetDetails(first.id);

            if (selectedCustomer.value?.id) {
                proceedToTicketForm();
            } else {
                currentStep.value = 2;
            }
        } else if (selectedCustomer.value?.id) {
            currentStep.value = 2;
            await fetchAssets();
        }
    } finally {
        wizardInitLoading.value = false;
    }
};

onMounted(() => {
    if (createBootstrap.value?.customer_id || createBootstrap.value?.asset_units?.length) {
        applyCreateBootstrap();
    }
});

</script>

<template>
    <Head title="Create Service Ticket" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div v-if="createBootstrap?.transaction?.id" class="max-w-4xl mx-auto mb-4">
            <Link
                :href="route('transactions.show', createBootstrap.transaction.id)"
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400"
            >
                <span class="material-icons text-base">arrow_back</span>
                Back to {{ createBootstrap.transaction.display_name }}
            </Link>
        </div>

        <div v-else-if="createBootstrap?.asset_unit?.id" class="max-w-4xl mx-auto mb-4">
            <Link
                :href="route('assetunits.show', createBootstrap.asset_unit.id)"
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400"
            >
                <span class="material-icons text-base">arrow_back</span>
                Back to {{ createBootstrap.asset_unit.display_name }}
            </Link>
        </div>

        <div v-if="wizardInitLoading" class="max-w-4xl mx-auto">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-10 flex flex-col items-center justify-center gap-4">
                <div class="animate-spin rounded-full h-10 w-10 border-2 border-gray-200 border-t-blue-600" />
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                    Loading customer and assets from this deal…
                </p>
            </div>
        </div>

        <template v-else>
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
                            <p class="text-blue-100 text-sm mt-0.5">
                                Service tickets use a customer profile (contact + customer record). Search existing customers or create new.
                            </p>
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

                    <!-- Same customer picker as ServiceTicketForm (records.lookup + contact display) -->
                    <div v-if="!showCreateCustomerForm" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ fieldsSchema?.customer_id?.label || 'Customer' }} <span class="text-red-500">*</span>
                        </label>
                        <RecordSelect
                            id="serviceticket_wizard_customer_id"
                            :field="customerField"
                            v-model="customerId"
                            :record="wizardRecord"
                            :enum-options="enumOptions?.customer_id || []"
                            field-key="customer_id"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            After you select a customer, the next step opens automatically.
                        </p>
                    </div>

                    <!-- Create New Contact / Customer Form -->
                    <div v-else class="space-y-4">
                        <div
                            v-if="pendingContactId"
                            class="rounded-lg border border-green-200 bg-green-50 px-4 py-4 dark:border-green-800 dark:bg-green-900/20"
                        >
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                Contact saved: {{ pendingContactName }}
                            </p>
                            <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                                Service tickets require a customer profile. Add one to continue this ticket, or view the contact only.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                    :disabled="customerIsLoading"
                                    @click="promotePendingContactToCustomer"
                                >
                                    <span v-if="customerIsLoading" class="material-icons text-sm animate-spin">refresh</span>
                                    Create customer profile & continue
                                </button>
                                <Link
                                    :href="route('contacts.show', pendingContactId)"
                                    class="inline-flex items-center rounded-lg border border-green-300 px-4 py-2 text-sm font-medium text-green-800 hover:bg-green-100 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-900/40"
                                >
                                    View contact
                                </Link>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-green-800 underline dark:text-green-200"
                                    @click="clearPendingContact"
                                >
                                    Create another
                                </button>
                            </div>
                        </div>

                        <template v-else>
                        <div class="space-y-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Save as
                            </p>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex cursor-pointer items-center gap-2 text-gray-800 dark:text-gray-200">
                                    <input
                                        v-model="newPersonSaveAs"
                                        type="radio"
                                        value="customer"
                                        class="text-primary-600"
                                        :disabled="customerIsLoading"
                                    >
                                    <span>Customer (contact + customer profile)</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-gray-800 dark:text-gray-200">
                                    <input
                                        v-model="newPersonSaveAs"
                                        type="radio"
                                        value="contact"
                                        class="text-primary-600"
                                        :disabled="customerIsLoading"
                                    >
                                    <span>Contact only</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Customer creates a contact record and a customer profile for service tickets. Contact only saves the person without a customer profile.
                            </p>
                        </div>

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

                        <p
                            v-if="customerCreateError"
                            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300"
                        >
                            {{ customerCreateError }}
                        </p>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                            <button
                                @click="showCreateCustomerForm = false"
                                type="button"
                                class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitNewPerson"
                                type="button"
                                :disabled="!newCustomerForm.first_name || !newCustomerForm.last_name || customerIsLoading"
                                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <span v-if="customerIsLoading" class="material-icons text-sm animate-spin">refresh</span>
                                <span v-else class="material-icons text-sm">person_add</span>
                                {{
                                    customerIsLoading
                                        ? 'Saving…'
                                        : newPersonSaveAs === 'contact'
                                            ? 'Save contact'
                                            : 'Create customer & continue'
                                }}
                            </button>
                        </div>
                        </template>
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
                            placeholder="Search by hull ID, serial, or variant name..."
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
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 flex flex-wrap items-center gap-3">
                                        <span v-if="asset.hin" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">directions_boat</span>
                                            Hull ID: {{ asset.hin }}
                                        </span>
                                        <span v-if="asset.serial_number" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">tag</span>
                                            Serial: {{ asset.serial_number }}
                                        </span>
                                        <span v-if="variantLabel(asset)" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">category</span>
                                            Variant: {{ variantLabel(asset) }}
                                        </span>
                                        <span v-if="asset.sku" class="flex items-center gap-1">
                                            <span class="material-icons text-xs">inventory_2</span>
                                            SKU: {{ asset.sku }}
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

            <p
                v-if="prefetchedTransactionAssetUnits.length > 1"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100"
            >
                This deal includes {{ prefetchedTransactionAssetUnits.length }} serialized units. The first unit is selected for this ticket. Use
                <span class="font-semibold">Back to Asset Selection</span>
                to choose a different unit.
            </p>

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
        </template>

    </TenantLayout>
</template>
