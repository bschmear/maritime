<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useTimezone } from '@/composables/useTimezone';
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        default: null
    },
    recordType: {
        type: String,
        required: true
    },
    formSchema: {
        type: Object,
        required: true
    },
    fieldsSchema: {
        type: Object,
        required: true
    },
    enumOptions: {
        type: Object,
        default: () => ({})
    },
    account: {
        type: Object,
        default: null
    },
    timezones: {
        type: Array,
        default: () => []
    },
    mode: {
        type: String,
        default: 'create', // 'create', 'edit', 'show'
        validator: (value) => ['create', 'edit', 'show'].includes(value)
    },
    serviceItems: {
        type: Array,
        default: () => [] // From ServiceItem table, with toWorkOrderDefaults()
    }
});

const emit = defineEmits(['saved', 'cancelled']);

const pluralTitle = computed(() => {
    // Convert WorkOrder to Work Orders
    return props.recordType.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/\b\w/g, l => l.toUpperCase());
});

// Service Items Management
const showServiceItemModal = ref(false);
const editingLineIndex = ref(null);

// Line items (WorkOrderServiceItem structure: unit_price, unit_cost, display_name, etc.)
const lineItems = ref([]);

// Service item lookup state
const serviceItemSearchQuery = ref('');
const serviceItemRecords = ref([]);
const serviceItemCurrentPage = ref(1);
const serviceItemTotalPages = ref(1);
const serviceItemPerPage = ref(10);
const serviceItemIsLoading = ref(false);

// Billing type options from enum
const billingTypeOptions = computed(() => props.enumOptions?.billing_type || []);

const getBillingTypeLabel = (billingType) => {
    const option = billingTypeOptions.value.find(opt => opt.value === billingType);
    return option?.name || 'Unknown';
};

// ==============================
// Line Item Calculations
// ==============================

// Customer Price
const calculateLineItemPrice = (item) => {
    const rate = Number(item.unit_price) || 0;
    const quantity = Number(item.quantity) || 1;
    const estimatedHours = Number(item.estimated_hours) || 0;

    let total = 0;

    switch (item.billing_type) {
        case 1: // Hourly
            total = estimatedHours * rate;
            break;

        case 2: // Flat
            total = rate;
            break;

        case 3: // Quantity
        default:
            total = quantity * rate;
            break;
    }

    if (item.warranty) {
        total = 0;
    }

    return total;
};

// Internal Cost
const calculateLineItemCost = (item) => {
    const cost = Number(item.unit_cost) || 0;
    const quantity = Number(item.quantity) || 1;
    const actualHours = Number(item.actual_hours) || 0;

    let total = 0;

    switch (item.billing_type) {
        case 1: // Hourly
            total = actualHours * cost;
            break;

        case 2: // Flat
            total = cost;
            break;

        case 3: // Quantity
        default:
            total = quantity * cost;
            break;
    }

    return total;
};



const selectedServiceItem = ref(null);
// WorkOrderServiceItem structure
const lineItemForm = ref({
    service_item_id: null,
    display_name: '',
    description: '',
    quantity: 1,
    unit_price: 0,
    unit_cost: 0,
    estimated_hours: 1,
    actual_hours: null,
    billable: true,
    warranty: false,
    billing_type: null
});

const addServiceItemLine = () => {
    editingLineIndex.value = null;
    lineItemForm.value = {
        service_item_id: null,
        display_name: '',
        description: '',
        quantity: 1,
        unit_price: 0,
        unit_cost: 0,
        estimated_hours: 1,
        actual_hours: 0,
        billable: true,
        warranty: false,
        billing_type: null
    };
    selectedServiceItem.value = null;
    serviceItemSearchQuery.value = '';
    serviceItemCurrentPage.value = 1;
    fetchServiceItems(true);
    showServiceItemModal.value = true;
};

const editServiceItemLine = (index) => {
    editingLineIndex.value = index;
    const item = lineItems.value[index];
    lineItemForm.value = {
        service_item_id: item.service_item_id,
        display_name: item.display_name ?? '',
        description: item.description ?? '',
        quantity: item.quantity ?? 1,
        unit_price: item.unit_price ?? 0,
        unit_cost: item.unit_cost ?? 0,
        estimated_hours: item.estimated_hours ?? 1,
        actual_hours: item.actual_hours ?? 0,
        billable: item.billable ?? true,
        warranty: item.warranty ?? false,
        billing_type: item.billing_type ?? null
    };
    // For editing, we'll pre-fill the form but allow user to change service item via modal
    selectedServiceItem.value = {
        id: item.service_item_id,
        display_name: item.display_name,
        code: item.code,
        billing_type: item.billing_type,
        default_rate: item.unit_price,
        default_cost: item.unit_cost,
        default_hours: item.estimated_hours,
        billable: item.billable,
        warranty_eligible: item.warranty
    };
    showServiceItemModal.value = true;
};

const removeServiceItemLine = (index) => {
    lineItems.value.splice(index, 1);
};

// Apply ServiceItem.toWorkOrderDefaults() when selecting
const selectServiceItem = (item) => {
    selectedServiceItem.value = item;
    lineItemForm.value.service_item_id = item.id;
    lineItemForm.value.display_name = item.display_name;
    lineItemForm.value.description = item.description ?? item.display_name;
    lineItemForm.value.unit_price = Number(item.default_rate) ?? 0;
    lineItemForm.value.unit_cost = Number(item.default_cost) ?? 0;
    lineItemForm.value.estimated_hours = Number(item.default_hours) ?? 1;
    lineItemForm.value.actual_hours = 0;
    lineItemForm.value.billable = item.billable ?? true;
    lineItemForm.value.warranty = item.warranty_eligible ?? false;
    lineItemForm.value.billing_type = item.billing_type ?? null;
};

const saveLineItem = () => {
    if (editingLineIndex.value !== null) {
        lineItems.value[editingLineIndex.value] = { ...lineItemForm.value };
    } else {
        lineItems.value.push({ ...lineItemForm.value });
    }
    showServiceItemModal.value = false;
};

const cancelLineItem = () => {
    showServiceItemModal.value = false;
};

// Fetch service items with pagination and search
const fetchServiceItems = async (resetPage = false) => {
    if (resetPage) {
        serviceItemCurrentPage.value = 1;
    }

    serviceItemIsLoading.value = true;

    try {
        const url = new URL(route('workorders.service-items.lookup'), window.location.origin);
        url.searchParams.append('page', serviceItemCurrentPage.value);
        url.searchParams.append('per_page', serviceItemPerPage.value);

        if (serviceItemSearchQuery.value.trim()) {
            url.searchParams.append('search', serviceItemSearchQuery.value.trim());
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
            throw new Error(`Failed to fetch service items: ${response.status}`);
        }

        const data = await response.json();

        serviceItemRecords.value = data.records || [];
        serviceItemTotalPages.value = data.meta?.last_page || 1;
    } catch (error) {
        console.error('Error fetching service items:', error);
        serviceItemRecords.value = [];
    } finally {
        serviceItemIsLoading.value = false;
    }
};

// Search service items
const searchServiceItems = () => {
    fetchServiceItems(true);
};

// Pagination methods
const nextServiceItemPage = () => {
    if (serviceItemCurrentPage.value < serviceItemTotalPages.value) {
        serviceItemCurrentPage.value++;
        fetchServiceItems();
    }
};

const prevServiceItemPage = () => {
    if (serviceItemCurrentPage.value > 1) {
        serviceItemCurrentPage.value--;
        fetchServiceItems();
    }
};

// ==============================
// Aggregated Totals
// ==============================

const lineItemsEstimatedHours = computed(() =>
    lineItems.value.reduce(
        (sum, item) => sum + (Number(item.estimated_hours) || 0),
        0
    )
);

const lineItemsActualHours = computed(() =>
    lineItems.value.reduce(
        (sum, item) => sum + (Number(item.actual_hours) || 0),
        0
    )
);

const lineItemsLaborCost = computed(() =>
    lineItems.value.reduce((sum, item) => {
        if (item.billing_type === 1) {
            return sum + calculateLineItemCost(item);
        }
        return sum;
    }, 0)
);

const lineItemsPartsCost = computed(() =>
    lineItems.value.reduce((sum, item) => {
        if (item.billing_type === 3) {
            return sum + calculateLineItemCost(item);
        }
        return sum;
    }, 0)
);

const lineItemsTotalCost = computed(() =>
    lineItems.value.reduce(
        (sum, item) => sum + calculateLineItemCost(item),
        0
    )
);

// Customer Side

const lineItemsSubtotal = computed(() =>
    lineItems.value.reduce((sum, item) => {
        if (!item.billable) return sum;
        return sum + calculateLineItemPrice(item);
    }, 0)
);

const lineItemsEstimatedTax = computed(() => {
    const rate = Number(form.tax_rate) || 0;
    return lineItemsSubtotal.value * (rate / 100);
});

const lineItemsGrandTotal = computed(() =>
    lineItemsSubtotal.value + lineItemsEstimatedTax.value
);

// Helper functions
const getEnumOptions = (fieldKey) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        return props.enumOptions[fieldDef.enum] || [];
    }
    return [];
};

const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const isFieldRequired = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.required === true;
};

const isFieldDisabled = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.disabled === true;
};

const isFieldReadonly = (fieldKey) => {
    return props.fieldsSchema[fieldKey]?.readOnly === true || props.mode === 'show';
};

// Use global timezone composable
const { convertUTCToTimezone, convertTimezoneToUTC, timezoneLabels, accountTimezone, accountTimezoneLabel } = useTimezone();

const isFieldDisabledByFilter = (fieldKey) => {
    const field = props.fieldsSchema[fieldKey];
    if (field && field.filterby) {
        // Check if the filter field has a value
        const filterFieldValue = form[field.filterby];
        return !filterFieldValue || filterFieldValue === '';
    }
    return false;
};

const getFieldFilterValue = (fieldKey) => {
    const field = props.fieldsSchema[fieldKey];
    if (field && field.filterby) {
        return form[field.filterby] || null;
    }
    return null;
};

const formatCurrency = (value) => {
    return value != null ? `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '$0.00';
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
};

// Initialize form with schema-driven defaults or existing record data
const formData = {};
Object.keys(props.fieldsSchema).forEach(key => {
    const field = props.fieldsSchema[key];

    // Use existing record data if in edit mode
    if (props.record && props.record[key] !== undefined) {
        const value = props.record[key];

        // Handle date/datetime fields - convert UTC to account timezone for display
        if ((field.type === 'datetime' || field.type === 'date') && value) {
            let utcDate;
            if (value instanceof Date) {
                utcDate = value;
            } else if (typeof value === 'string') {
                const parsedDate = new Date(value);
                if (!isNaN(parsedDate.getTime())) {
                    utcDate = parsedDate;
                } else {
                    formData[key] = value;
                    return;
                }
            } else {
                formData[key] = value;
                return;
            }

            // Convert UTC date to account timezone for display
            const timezoneDate = convertUTCToTimezone(utcDate.toISOString(), accountTimezone.value);
            formData[key] = field.type === 'datetime'
                ? timezoneDate.toISOString().slice(0, 16)
                : timezoneDate.toISOString().split('T')[0];
        } else {
            formData[key] = value;
        }
    } else if (field.default !== undefined && field.default !== null) {
        formData[key] = field.default;
    } else if (field.default_value !== undefined && field.default_value !== null) {
        formData[key] = field.default_value;
    } else if (field.type === 'boolean' || field.type === 'checkbox') {
        formData[key] = false;
    } else if (field.type === 'select') {
        // For select fields with enums, use explicit default or auto-select first option
        const enumOptions = getEnumOptions(key);
        if (enumOptions && enumOptions.length > 0) {
            // Check if there's an explicit default value
            if (field.default !== undefined && field.default !== null) {
                // Find the option with matching value
                const defaultOption = enumOptions.find(opt => opt.value === field.default);
                if (defaultOption) {
                    formData[key] = defaultOption.id;
                } else {
                    formData[key] = enumOptions[0].id;
                }
            } else {
                formData[key] = enumOptions[0].id;
            }
        } else {
            formData[key] = null;
        }
    } else if (field.type === 'record') {
        formData[key] = null;
    } else {
        formData[key] = '';
    }
});

// Set default dates for new Work Orders
if (props.mode === 'create') {
    const accountTz = accountTimezone.value;

    // Get current date in account timezone
    const now = new Date();
    const localNow = new Date(now.toLocaleString('en-US', { timeZone: accountTz }));

    // Get tomorrow in account timezone
    const tomorrow = new Date(localNow);
    tomorrow.setDate(localNow.getDate() + 1);
    tomorrow.setHours(8, 0, 0, 0); // 8am in account timezone

    // Get due date (7 days from tomorrow) in account timezone
    const dueDate = new Date(localNow);
    dueDate.setDate(localNow.getDate() + 8); // tomorrow + 7 days
    dueDate.setHours(17, 0, 0, 0); // 5pm in account timezone

    // Convert to UTC for storage
    const utcNow = new Date();
    const offset = utcNow.getTime() - localNow.getTime();

    const startUTC = new Date(tomorrow.getTime() + offset);
    const dueUTC = new Date(dueDate.getTime() + offset);

    // Format for datetime-local input (which expects local time, not UTC)
    // We want to display the account timezone time, so we use the local dates directly
    const formatForInput = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    };

    formData.scheduled_start_at = formatForInput(tomorrow);
    formData.due_at = formatForInput(dueDate);
}
// Ensure tax_rate is included in form data
if (!formData.hasOwnProperty('tax_rate')) {
    formData.tax_rate = props.record?.tax_rate ?? 0;
}

const form = useForm(formData);

// Initialize line items from record.service_items when editing
watch(() => props.record?.service_items, (items) => {
    if (items && Array.isArray(items) && items.length > 0) {
        lineItems.value = items.map(li => ({
            service_item_id: li.service_item_id,
            display_name: li.display_name,
            description: li.description,
            quantity: li.quantity ?? 1,
            unit_price: li.unit_price ?? 0,
            unit_cost: li.unit_cost ?? 0,
            estimated_hours: li.estimated_hours ?? 1,
            actual_hours: li.actual_hours ?? 0,
            billable: li.billable ?? true,
            warranty: li.warranty ?? false,
            billing_type: li.billing_type ?? null
        }));
    }
}, { immediate: true });

// Track if form has been initialized (to prevent watchers from clearing values on load)
const formInitialized = ref(false);
setTimeout(() => {
    formInitialized.value = true;
}, 100);

// Watch for subsidiary changes to clear dependent fields
watch(() => form.subsidiary_id, (newValue, oldValue) => {
    if (formInitialized.value && oldValue !== undefined && newValue !== oldValue) {
        // Clear location when subsidiary changes
        form.location_id = null;
    }
});

// Watch for customer changes to clear dependent fields
watch(() => form.customer_id, (newValue, oldValue) => {
    if (formInitialized.value && oldValue !== undefined && newValue !== oldValue) {
        // Clear asset unit when customer changes
        form.asset_unit_id = null;
    }
});

// Watch for asset unit changes to auto-populate customer
watch(() => form.asset_unit_id, async (newValue, oldValue) => {
    if (formInitialized.value && newValue && oldValue !== undefined && newValue !== oldValue) {
        try {
            // Fetch the asset unit data to get its customer
            const response = await axios.get(route('assetunits.show', newValue));
            const assetUnit = response.data;

            // If the asset unit has a customer and we don't have one set, auto-populate it
            if (assetUnit.customer_id && !form.customer_id) {
                form.customer_id = assetUnit.customer_id;
            }
        } catch (error) {
            console.error('Failed to fetch asset unit data:', error);
        }
    }
});

// Watch for location changes to auto-populate tax rate
watch(() => form.location_id, async (newValue, oldValue) => {
    if (formInitialized.value && newValue && newValue !== oldValue) {
        try {
            // Fetch tax rate for the selected location
            const response = await axios.get(route('workorders.location-tax-rate'), {
                params: { location_id: newValue }
            });

            const taxRate = response.data.tax_rate;
            // Always update the tax rate when location changes (user can still override)
            if (taxRate !== null) {
                form.tax_rate = taxRate;
            }
        } catch (error) {
            console.error('Failed to fetch location tax rate:', error);
        }
    }
});

// Initialize form data properly for asset_unit_id
if (props.record && props.record.asset_unit_id !== undefined) {
    formData.asset_unit_id = props.record.asset_unit_id;
}

const submit = () => {
    // Ensure all form data is sent by using transform
    form.transform((data) => {
        const allData = { ...form.data() };

        // Override with the current values to ensure everything is sent
        Object.keys(allData).forEach(key => {
            if (form[key] !== undefined) {
                allData[key] = form[key];
            }
        });

        // Explicitly ensure tax_rate and status are included
        allData.tax_rate = form.tax_rate;
        allData.status = form.status;

        // Add service items in WorkOrderServiceItem format
        allData.service_items = lineItems.value.map((li, idx) => ({
            service_item_id: li.service_item_id,
            display_name: li.display_name,
            description: li.description,
            quantity: Number(li.quantity) || 1,
            unit_price: Number(li.unit_price) || 0,
            unit_cost: Number(li.unit_cost) || 0,
            estimated_hours: Number(li.estimated_hours) || 0,
            actual_hours: Number(li.actual_hours) || 0,
            billable: li.billable ?? true,
            warranty: li.warranty ?? false,
            billing_type: li.billing_type ? Number(li.billing_type) : null,
            sort_order: idx
        }));

        // Note: Totals will be calculated by WorkOrderCalculator service after save

        // Convert date/datetime fields from account timezone back to UTC
        Object.keys(props.fieldsSchema).forEach(key => {
            const field = props.fieldsSchema[key];
            if ((field.type === 'datetime' || field.type === 'date') && allData[key]) {
                // Convert account timezone date back to UTC for database storage
                const timezoneDate = new Date(allData[key]);
                const utcDate = convertTimezoneToUTC(timezoneDate.toISOString(), accountTimezone.value);
                allData[key] = field.type === 'datetime'
                    ? utcDate.toISOString().slice(0, 16)
                    : utcDate.toISOString().split('T')[0];
            }
        });

        return allData;
    });

    if (props.mode === 'edit') {
        form.put(route('workorders.update', props.record.id), {
            onSuccess: (page) => {
                // Redirect to show page after successful update
                window.location.href = route('workorders.show', props.record.id);
            },
            onError: (errors) => {
                console.error('Update failed with errors:', errors);
            }
        });
    } else {
        form.post(route('workorders.store'), {
            onSuccess: (page) => {
                // Inertia redirects to show page; ensure we're on the right URL
                const recordId = page.props.record?.id ?? page.props.workorder?.id;
                if (recordId) {
                    window.location.href = route('workorders.show', recordId);
                }
            },
            onError: (errors) => {
                console.error('Create failed with errors:', errors);
            }
        });
    }
};

const saveDraft = () => {
    form.draft = true;
    form.status = 1; // Draft status ID
    submit();
};

const saveAndOpen = () => {
    form.draft = false;
    // Only set status to Open (2) if it's currently Draft (1) or not set
    // This allows users to manually set other statuses and have them preserved
    if (!form.status || form.status === 1) {
        form.status = 2; // Open status ID
    }
    submit();
};

const handleCancel = () => {
    emit('cancelled');
};
</script>

<template>

    <div class="w-full flex flex-col space-y-4 md:space-y-6">


        <div class="">
            <form @submit.prevent="submit">
                <div class="grid gap-4 lg:gap-6  lg:grid-cols-12">
            <!-- Main Work Order Form -->
            <div
                :class="{
                    'lg:col-span-9': mode !== 'show',
                    'lg:col-span-12': mode === 'show',
                    'space-y-6': true
                }"
            >
                <!-- Work Order Header -->
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-white">{{ mode === 'edit' ? 'EDIT' : 'WORK ORDER' }}</h1>
                                <p class="text-blue-100 text-sm mt-1">{{ mode === 'edit' ? 'Update Work Order' : 'Service Request Form' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-sm font-medium">WO #</div>
                                <div class="text-white text-lg font-mono">{{ form.work_order_number || record?.work_order_number || 'Auto-generated' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Customer & Unit Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Customer Information
                                </h3>

                                <!-- Customer Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.customer_id?.label || 'Customer' }} {{ isFieldRequired('customer_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'customer_id'"
                                        :field="fieldsSchema.customer_id"
                                        v-model="form.customer_id"
                                        :disabled="isFieldReadonly('customer_id')"
                                        :enum-options="getEnumOptions('customer_id')"
                                        :record="record"
                                        field-key="customer_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.customer?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Asset Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.asset_unit_id?.label || 'Asset' }}
                                        {{ isFieldRequired('asset_unit_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'asset_unit_id'"
                                        :field="fieldsSchema.asset_unit_id"
                                        v-model="form.asset_unit_id"
                                        :disabled="isFieldReadonly('asset_unit_id') || isFieldDisabledByFilter('asset_unit_id')"
                                        :enum-options="getEnumOptions('asset_unit_id')"
                                        :record="record"
                                        field-key="asset_unit_id"
                                        filter-by="customer_id"
                                        :filter-value="form.customer_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.asset_unit?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Subsidiary Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.subsidiary_id?.label || 'Subsidiary' }}
                                        {{ isFieldRequired('subsidiary_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'subsidiary_id'"
                                        :field="fieldsSchema.subsidiary_id"
                                        v-model="form.subsidiary_id"
                                        :disabled="isFieldReadonly('subsidiary_id')"
                                        :enum-options="getEnumOptions('subsidiary_id')"
                                        :record="record"
                                        field-key="subsidiary_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.subsidiary?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Location Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.location_id?.label || 'Location' }}
                                        {{ isFieldRequired('location_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'location_id'"
                                        :field="fieldsSchema.location_id"
                                        v-model="form.location_id"
                                        :disabled="isFieldReadonly('location_id') || isFieldDisabledByFilter('location_id')"
                                        :enum-options="getEnumOptions('location_id')"
                                        :record="record"
                                        field-key="location_id"
                                        :filter-by="fieldsSchema.location_id.filterby || null"
                                        :filter-value="getFieldFilterValue('location_id')"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.location?.display_name || '—' }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                    Assignment & Scheduling
                                </h3>

                                <!-- Assigned User Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.assigned_user_id?.label || 'Assigned Technician' }}
                                        {{ isFieldRequired('assigned_user_id') ? '*' : '' }}
                                    </label>
                                    <RecordSelect
                                        v-if="mode !== 'show'"
                                        :id="'assigned_user_id'"
                                        :field="fieldsSchema.assigned_user_id"
                                        v-model="form.assigned_user_id"
                                        :disabled="isFieldReadonly('assigned_user_id')"
                                        :enum-options="getEnumOptions('assigned_user_id')"
                                        :record="record"
                                        field-key="assigned_user_id"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.assigned_user?.display_name || 'Unassigned' }}
                                    </p>
                                </div>

                                <!-- Scheduled Date/Time -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.scheduled_start_at?.label || 'Scheduled Date/Time' }}
                                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">
                                            ({{ accountTimezoneLabel }})
                                        </span>
                                        {{ isFieldRequired('scheduled_start_at') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.scheduled_start_at"
                                        type="datetime-local"
                                        :readonly="isFieldReadonly('scheduled_start_at')"
                                        class="input-style"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record?.scheduled_start_at) }}
                                    </p>
                                </div>

                                <!-- Due Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.due_at?.label || 'Due Date' }}
                                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">
                                            ({{ accountTimezoneLabel }})
                                        </span>
                                        {{ isFieldRequired('due_at') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.due_at"
                                        type="datetime-local"
                                        :readonly="isFieldReadonly('due_at')"
                                        class="input-style"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record?.due_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Work Order Details -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                Work Order Details
                            </h3>

                            <div class="space-y-4">
                                <!-- Display Name / Title -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.display_name?.label || 'Summary' }} {{ isFieldRequired('display_name') ? '*' : '' }}
                                    </label>
                                    <input
                                        v-if="mode !== 'show'"
                                        v-model="form.display_name"
                                        type="text"
                                        :placeholder="fieldsSchema.display_name?.placeholder || ''"
                                        :readonly="isFieldReadonly('display_name')"
                                        class="input-style"
                                        :required="isFieldRequired('display_name')"
                                    />
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ record?.display_name || '—' }}
                                    </p>
                                </div>

                                <!-- Description -->
                                <div v-if="mode !== 'show' || record?.description">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.description?.label || 'Description' }}
                                        {{ isFieldRequired('description') ? '*' : '' }}
                                    </label>
                                    <textarea
                                        v-if="mode !== 'show'"
                                        v-model="form.description"
                                        rows="4"
                                        :placeholder="fieldsSchema.description?.help || ''"
                                        :readonly="isFieldReadonly('description')"
                                        class="input-style resize-none"
                                    ></textarea>
                                    <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">
                                        {{ record?.description || '—' }}
                                    </p>
                                </div>

                                <!-- Internal Notes -->
                                <div v-if="mode !== 'show' || record?.internal_notes">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ fieldsSchema.internal_notes?.label || 'Internal Notes' }}
                                        {{ isFieldRequired('internal_notes') ? '*' : '' }}
                                    </label>
                                    <textarea
                                        v-if="mode !== 'show'"
                                        v-model="form.internal_notes"
                                        rows="3"
                                        :placeholder="fieldsSchema.internal_notes?.help || ''"
                                        :readonly="isFieldReadonly('internal_notes')"
                                        class="input-style resize-none"
                                    ></textarea>
                                    <p v-else class="text-sm text-gray-900 dark:text-white whitespace-pre-line leading-relaxed">
                                        {{ record?.internal_notes }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="material-icons text-xs align-middle">lock</span>
                                        These notes are internal only and will not appear on customer-facing documents
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Service Items Section -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">
                                    Service Items
                                </h3>
                                <button
                                    v-if="mode !== 'show'"
                                    @click="addServiceItemLine"
                                    type="button"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                >
                                    <span class="material-icons text-base">add_circle</span>
                                    Add Item
                                </button>
                            </div>

                            <!-- Service Items Table -->
                            <div v-if="lineItems.length > 0" class="overflow-x-auto -mx-6 sm:mx-0">
                            <div class="inline-block min-w-full align-middle">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Qty
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Est Hrs
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Act Hrs
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Warranty
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Unit Price
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Unit Cost
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Line Total
                                    </th>
                                    <th v-if="mode !== 'show'" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                    v-for="(item, index) in lineItems"
                                    :key="index"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                    >
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ item.display_name || item.description }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-medium">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-medium">
                                        {{ item.estimated_hours ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-medium">
                                        <span v-if="item.actual_hours > 0" class="text-blue-600 dark:text-blue-400">
                                        {{ item.actual_hours }}
                                        </span>
                                        <span v-else>{{ item.actual_hours }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                                        >
                                        {{ getBillingTypeLabel(item.billing_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">
                                        <span v-if="item.warranty" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                            <span class="material-icons text-xs mr-1">verified_user</span>
                                            Yes
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            No
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                        {{ formatCurrency(item.unit_price) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                        {{ formatCurrency(item.unit_cost) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-semibold">
                                        {{ formatCurrency(calculateLineItemPrice(item)) }}
                                        </td>

                                    <td v-if="mode !== 'show'" class="px-4 py-3 text-sm text-right">
                                        <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="editServiceItemLine(index)"
                                            type="button"
                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                                        >
                                            <span class="material-icons text-base">edit</span>
                                        </button>
                                        <button
                                            @click="removeServiceItemLine(index)"
                                            type="button"
                                            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                        >
                                            <span class="material-icons text-base">delete</span>
                                        </button>
                                        </div>
                                    </td>
                                    </tr>
                                </tbody>
                                </table>
                            </div>
                            </div>

                            <!-- Empty State -->
                            <div
                            v-else
                            class="text-center py-12 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700"
                            >
                            <span class="material-icons text-5xl text-gray-400 dark:text-gray-600 mb-3 block">receipt_long</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No service items added yet</p>
                            <p v-if="mode !== 'show'" class="text-xs text-gray-400 dark:text-gray-500 mt-1">Click "Add Item" to get started</p>
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-6">
                            Financial Summary
                        </h3>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

                            <!-- Internal -->
                            <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">
                                Internal
                            </h4>

                            <div class="space-y-4 text-gray-900 dark:text-white">

                                <div class="flex justify-between">
                                <span>Estimated Hours</span>
                                <span class="font-medium">{{ lineItemsEstimatedHours.toFixed(2) }}</span>
                                </div>

                                <div class="flex justify-between">
                                <span>Actual Hours</span>
                                <span class="font-medium">{{ lineItemsActualHours.toFixed(2) }}</span>
                                </div>

                                <div class="flex justify-between">
                                <span>Labor Cost</span>
                                <span class="font-medium">{{ formatCurrency(lineItemsLaborCost) }}</span>
                                </div>

                                <div class="flex justify-between">
                                <span>Parts & Materials</span>
                                <span class="font-medium">{{ formatCurrency(lineItemsPartsCost) }}</span>
                                </div>

                                <div class="flex justify-between text-lg font-semibold border-t pt-3">
                                <span>Total Internal Cost</span>
                                <span>{{ formatCurrency(lineItemsTotalCost) }}</span>
                                </div>
                            </div>
                            </div>

                            <!-- Customer -->
                            <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">
                                Customer
                            </h4>

                            <div class="space-y-4 text-gray-900 dark:text-white">

                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span class="font-medium">{{ formatCurrency(lineItemsSubtotal) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Tax Rate</span>
                                    <div v-if="mode !== 'show'" class="flex items-center gap-1">
                                        <input
                                        v-model.number="form.tax_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-16 px-2 py-1 text-sm text-right bg-white dark:bg-gray-700 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        />
                                        <span class="text-sm">%</span>
                                    </div>
                                    <span v-else class="font-medium">{{ form.tax_rate ?? 0 }}%</span>
                                </div>

                                <div class="flex justify-between">
                                    <span>Tax</span>
                                    <span class="font-medium">{{ formatCurrency(lineItemsEstimatedTax) }}</span>
                                </div>
                                <div class="spacer-10 lg:h-6"></div>
                                <div class="flex justify-between text-2xl font-bold border-t pt-4 ">
                                <span>Total Due</span>
                                <span>{{ formatCurrency(lineItemsGrandTotal) }}</span>
                                </div>
                            </div>
                            </div>
                        </div>
                        </div>


                        <!-- Flags & Options -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide mb-4">
                                Additional Options
                            </h3>

                            <div class="flex flex-wrap gap-3" v-if="mode !== 'show'">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.billable"
                                        type="checkbox"
                                        :disabled="isFieldReadonly('billable')"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.billable?.label || 'Billable' }}</span>
                                </label>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.warranty"
                                        type="checkbox"
                                        :disabled="isFieldReadonly('warranty')"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ fieldsSchema.warranty?.label || 'Warranty Work' }}</span>
                                </label>
                            </div>
                            <div v-else class="flex flex-wrap gap-3">
                                <span v-if="record?.billable" class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full text-sm font-medium">
                                    <span class="material-icons text-sm">check_circle</span>
                                    {{ fieldsSchema.billable?.label || 'Billable' }}
                                </span>
                                <span v-if="record?.warranty" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm font-medium">
                                    <span class="material-icons text-sm">verified_user</span>
                                    {{ fieldsSchema.warranty?.label || 'Warranty Work' }}
                                </span>
                                <span v-if="!record?.billable && !record?.warranty" class="text-sm text-gray-500 dark:text-gray-400 italic">
                                    No additional flags
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Sidebar -->
            <div class="lg:col-span-3 w-full" v-if="mode !== 'show'">
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden sticky top-5">
                    <div class="flex justify-between items-center p-4 sm:px-5 font-semibold text-gray-900 bg-gray-100 dark:text-white dark:bg-gray-700">
                        Actions
                    </div>

                    <div class="p-4 sm:p-5 space-y-6">
                            <!-- Classification -->
                            <div class="space-y-4">
                                <!-- Type Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Type
                                    </label>
                                    <select
                                        v-if="mode !== 'show'"
                                        v-model="form.type"
                                        :disabled="isFieldReadonly('type')"
                                        class="input-style"
                                    >
                                        <option v-for="option in getEnumOptions('type')" :key="option.id" :value="option.id">
                                            {{ option.name }}
                                        </option>
                                    </select>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('type', record?.type) }}
                                    </p>
                                </div>

                                <!-- Priority Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Priority
                                    </label>
                                    <div v-if="mode !== 'show'" class="grid grid-cols-2 gap-2">
                                        <button
                                            v-for="priority in getEnumOptions('priority')"
                                            :key="priority.id"
                                            type="button"
                                            :disabled="isFieldReadonly('priority')"
                                            @click="form.priority = priority.id"
                                            :class="[
                                                'flex flex-col items-center gap-1 p-2 rounded-lg border-2 transition-all text-xs font-medium',
                                                form.priority === priority.id
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                                            ]"
                                        >
                                            <span class="material-icons text-lg" :class="`text-${priority.color}-500`">
                                                priority_high
                                            </span>
                                            <span :class="form.priority === priority.id ? 'text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'">
                                                {{ priority.name }}
                                            </span>
                                        </button>
                                    </div>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('priority', record?.priority) }}
                                    </p>
                                </div>

                                <!-- Status Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                        Status
                                    </label>
                                    <select
                                        v-if="mode !== 'show'"
                                        v-model="form.status"
                                        :disabled="isFieldReadonly('status')"
                                        class="input-style"
                                    >
                                        <option v-for="option in getEnumOptions('status')" :key="option.id" :value="option.id">
                                            {{ option.name }}
                                        </option>
                                    </select>
                                    <p v-else class="text-sm text-gray-900 dark:text-white">
                                        {{ getEnumLabel('status', record?.status) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Save Actions -->
                            <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700" v-if="mode !== 'show'">
                                <button
                                    @click="saveAndOpen"
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing" class="material-icons text-sm mr-2 animate-spin">refresh</span>
                                    <span v-else class="material-icons text-sm mr-2">check_circle</span>
                                    {{ mode === 'edit' ? (form.processing ? 'Updating...' : 'Update & Continue') : (form.processing ? 'Creating...' : 'Create & Open') }}
                                </button>

                                <button
                                    @click="saveDraft"
                                    type="button"
                                    :disabled="form.processing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span class="material-icons text-sm mr-2">save</span>
                                    Save as Draft
                                </button>

                                <button
                                    @click="handleCancel"
                                    type="button"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                                >
                                    <span class="material-icons text-sm mr-2">close</span>
                                    Cancel
                                </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </form>
        </div>

        <!-- Service Item Selection Modal -->
        <div v-if="showServiceItemModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="cancelLineItem"></div>

                <!-- Modal panel -->
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-white">
                                {{ editingLineIndex !== null ? 'Edit Service Item' : 'Add Service Item' }}
                            </h3>
                            <button @click="cancelLineItem" type="button" class="text-blue-100 hover:text-white transition-colors">
                                <span class="material-icons">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- Service Item Selection -->
                        <div v-if="!selectedServiceItem">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Select a Service Item
                            </label>

                            <!-- Search Input -->
                            <div class="relative mb-4">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="serviceItemSearchQuery"
                                    @input="searchServiceItems"
                                    type="text"
                                    placeholder="Search service items..."
                                    class="rounded-xl w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-transparent transition-all"
                                />
                            </div>

                            <!-- Loading State -->
                            <div v-if="serviceItemIsLoading" class="flex justify-center py-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            </div>

                            <!-- Service Items List -->
                            <div v-else-if="serviceItemRecords.length > 0" class="space-y-2 max-h-64 overflow-y-auto">
                                <button
                                    v-for="item in serviceItemRecords"
                                    :key="item.id"
                                    @click="selectServiceItem(item)"
                                    type="button"
                                    class="w-full text-left p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                                >
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ item.display_name }}
                                    </div>
                                </button>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="text-center py-8 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
                                <span class="material-icons text-4xl text-gray-400 dark:text-gray-600 mb-2 block">receipt_long</span>
                                <p class="text-gray-500 dark:text-gray-400 mb-1">
                                    {{ serviceItemSearchQuery.trim() ? 'No service items found' : 'No service items available' }}
                                </p>
                                <p v-if="serviceItemSearchQuery.trim()" class="text-sm text-gray-400 dark:text-gray-500">
                                    Try a different search term
                                </p>
                            </div>

                            <!-- Pagination -->
                            <div v-if="serviceItemRecords.length > 0 && serviceItemTotalPages > 1" class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                                <button
                                    @click="prevServiceItemPage"
                                    :disabled="serviceItemCurrentPage === 1"
                                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                                >
                                    Previous
                                </button>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    Page {{ serviceItemCurrentPage }} of {{ serviceItemTotalPages }}
                                </span>
                                <button
                                    @click="nextServiceItemPage"
                                    :disabled="serviceItemCurrentPage === serviceItemTotalPages"
                                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <!-- Line Item Form -->
                        <div v-else class="space-y-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ selectedServiceItem.display_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-2">
                                            <span>{{ selectedServiceItem.code }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300">
                                                {{ getBillingTypeLabel(selectedServiceItem.billing_type) }}
                                            </span>
                                        </div>
                                    </div>
                                    <button @click="selectedServiceItem = null" type="button" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-sm">
                                        Change
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <input
                                    v-model="lineItemForm.description"
                                    type="text"
                                    class="input-style"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Quantity
                                    </label>
                                    <input
                                        v-model.number="lineItemForm.quantity"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="input-style"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Estimated Hours
                                    </label>
                                    <input
                                        v-model.number="lineItemForm.estimated_hours"
                                        type="number"
                                        min="0"
                                        step="0.25"
                                        class="input-style"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Actual Hours
                                    </label>
                                    <input
                                        v-model.number="lineItemForm.actual_hours"
                                        type="number"
                                        min="0"
                                        step="0.25"
                                        class="input-style"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Billing Type
                                    </label>
                                    <select
                                        v-model.number="lineItemForm.billing_type"
                                        class="input-style"
                                    >
                                        <option :value="null">-- Select --</option>
                                        <option v-for="option in billingTypeOptions" :key="option.id" :value="option.value">
                                            {{ option.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Warranty Checkbox -->
                            <div class="col-span-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="lineItemForm.warranty"
                                        type="checkbox"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Warranty Work</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Unit Price
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            v-model.number="lineItemForm.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="input-style pl-8"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Unit Cost
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            v-model.number="lineItemForm.unit_cost"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="input-style pl-8"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Line Total</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(lineItemForm.quantity * lineItemForm.unit_price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex gap-3 justify-end">
                        <button
                            @click="cancelLineItem"
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveLineItem"
                            type="button"
                            :disabled="!selectedServiceItem"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ editingLineIndex !== null ? 'Update Item' : 'Add Item' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>