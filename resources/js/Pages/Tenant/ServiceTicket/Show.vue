<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ServiceTicketForm from '@/Components/Tenant/ServiceTicketForm.vue';
import ServiceTicketPreview from '@/Components/Tenant/ServiceTicketPreview.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    fieldsSchema: {
        type: Object,
        default: () => ({}),
    },
    formSchema: {
        type: Object,
        default: null,
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
    return [
        { label: 'Home', href: route('dashboard') },
        { label: 'Service Tickets', href: route('servicetickets.index') },
        { label: props.record.service_ticket_number || 'View' },
    ];
});

const getEnumLabel = (fieldKey, value) => {
    const fieldDef = props.fieldsSchema[fieldKey];
    if (fieldDef && fieldDef.enum) {
        const options = props.enumOptions[fieldDef.enum] || [];
        const option = options.find(opt => opt.id === value || opt.value === value);
        return option ? option.name : value;
    }
    return value;
};

const formatDateTime = (value) => {
    if (!value) return '—';
    try {
        let date;
        if (typeof value === 'string') {
            date = new Date(value);
        } else if (value instanceof Date) {
            date = value;
        } else {
            return '—';
        }
        if (isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);
    } catch (error) {
        console.warn('Date formatting error:', error, value);
        return '—';
    }
};

// Quick action state
const selectedStatus = ref(props.record.status);
const statusChanged = ref(false);
const updatingStatus = ref(false);
const approving = ref(false);

// Preview state
const showPreview = ref(false);

// Dropdown menu state
const showActionsMenu = ref(false);

// Status options from enum
const statusOptions = computed(() => {
    return props.enumOptions['App\\Enums\\ServiceTicket\\Status'] || [];
});

const updateStatus = async () => {
    if (selectedStatus.value === props.record.status) {
        statusChanged.value = false;
        return;
    }

    updatingStatus.value = true;
    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            status: selectedStatus.value
        }, {
            preserveState: true,
            preserveScroll: true,
        });
        statusChanged.value = false;
    } catch (error) {
        console.error('Failed to update status:', error);
    } finally {
        updatingStatus.value = false;
    }
};

const approveTicket = async () => {
    approving.value = true;
    try {
        await router.patch(route('servicetickets.update', props.record.id), {
            approved: true,
            status: 4 // Approved status
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    } catch (error) {
        console.error('Failed to approve ticket:', error);
    } finally {
        approving.value = false;
    }
};

const deleteTicket = () => {
    if (confirm('Are you sure you want to delete this service ticket?')) {
        router.delete(route('servicetickets.destroy', props.record.id));
    }
};

const openPreview = () => {
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
};
</script>

<template>
    <Head :title="`Service Ticket - ${record.display_name || record.uuid}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-4">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Service Ticket Details
                    </h2>

                    <!-- Desktop Actions -->
                    <div class="hidden lg:flex items-center gap-2">
                        <Link :href="route('servicetickets.index')">
                            <button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back to List
                            </button>
                        </Link>
                        <button
                            @click="openPreview"
                            class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors whitespace-nowrap"
                        >
                            <span class="material-icons text-sm mr-1">visibility</span>
                            Customer Preview
                        </button>
                        <Link :href="route('servicetickets.edit', record.id)">
                            <button class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>
                        <button
                            @click="deleteTicket"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons">delete_forever</span>
                        </button>
                    </div>

                    <!-- Mobile Actions - With Dropdown -->
                    <div class="flex items-center gap-2 lg:hidden">
                        <Link :href="route('servicetickets.index')" class="flex-1">
                            <button class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-2">arrow_back</span>
                                Back
                            </button>
                        </Link>
                        
                        <Link :href="route('servicetickets.edit', record.id)" class="flex-1">
                            <button class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <span class="material-icons text-sm mr-1">edit</span>
                                Edit
                            </button>
                        </Link>

                        <!-- Dropdown Menu -->
                        <div class="relative">
                            <button
                                @click="showActionsMenu = !showActionsMenu"
                                class="inline-flex items-center justify-center p-2.5 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                <span class="material-icons">more_vert</span>
                            </button>

                            <!-- Dropdown Backdrop -->
                            <div
                                v-if="showActionsMenu"
                                @click="showActionsMenu = false"
                                class="fixed inset-0 z-40"
                            ></div>
                            
                            <!-- Dropdown Menu -->
                            <div
                                v-if="showActionsMenu"
                                class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
                            >
                                <div class="py-1">
                                    <button
                                        @click="openPreview(); showActionsMenu = false"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <span class="material-icons text-base text-purple-600 dark:text-purple-400">visibility</span>
                                        <span>Customer Preview</span>
                                    </button>
                                    <button
                                        @click="deleteTicket(); showActionsMenu = false"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors border-t border-gray-100 dark:border-gray-700"
                                    >
                                        <span class="material-icons text-base">delete_forever</span>
                                        <span>Delete Ticket</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
        </template>

        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10 shadow-md full-w-margin">
            <div class="w-full px-4 sm:px-6 py-4 sm:py-5">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-6">
                    <!-- Left side: Status, Expedite, Approval -->
                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3 sm:gap-4 flex-1">
                        <!-- Status Section -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Status:</span>
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="selectedStatus"
                                    class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all flex-1 sm:flex-none"
                                    @change="statusChanged = true"
                                >
                                    <option v-for="status in statusOptions" :key="status.id" :value="status.id">
                                        {{ status.name }}
                                    </option>
                                </select>
                                <button
                                    v-if="statusChanged"
                                    @click="updateStatus"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="updatingStatus"
                                >
                                    <span v-if="updatingStatus" class="material-icons text-sm animate-spin">refresh</span>
                                    <span v-else class="material-icons text-sm">save</span>
                                    Update
                                </button>
                            </div>
                        </div>

                        <!-- Expedite Section -->
                        <div class="flex items-center justify-between sm:justify-start gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Expedite:</span>
                            <span v-if="record.expedite" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded-md text-sm font-semibold shadow-sm whitespace-nowrap">
                                <span class="material-icons text-base">priority_high</span>
                                Yes
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md text-sm font-medium whitespace-nowrap">
                                No
                            </span>
                        </div>

                        <!-- Approval Section -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Approved:</span>
                            <div class="flex items-center gap-2">
                                <span v-if="record.approved" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-md text-sm font-semibold shadow-sm whitespace-nowrap">
                                    <span class="material-icons text-base">check_circle</span>
                                    Approved
                                </span>
                                <button
                                    v-if="!record.approved"
                                    @click="approveTicket"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all shadow-sm whitespace-nowrap"
                                    :disabled="approving"
                                >
                                    <span v-if="approving" class="material-icons text-sm animate-spin">refresh</span>
                                    <span v-else class="material-icons text-sm">check_circle</span>
                                    Approve
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right side: Timestamps -->
                    <div class="flex flex-row sm:gap-4 lg:gap-6 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:py-2.5 rounded-lg">
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.created_at) }}</span>
                        </div>
                        <div class="border-l border-gray-300 dark:border-gray-600 sm:hidden"></div>
                        <div class="flex flex-col flex-1 sm:flex-none">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Updated</span>
                            <span class="text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-200">{{ formatDateTime(record.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full space-y-4 md:space-y-6">
            <ServiceTicketForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                mode="show"
            />
        </div>

        <!-- Preview Modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="fixed inset-0 z-[100] overflow-y-auto">
                <ServiceTicketPreview
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    @close="closePreview"
                />
            </div>
        </Teleport>
    </TenantLayout>
</template>