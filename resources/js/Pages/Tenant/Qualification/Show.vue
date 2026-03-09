<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { ref, computed, getCurrentInstance } from 'vue';

const { proxy } = getCurrentInstance();

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'qualifications',
    },
    recordTitle: {
        type: String,
        default: 'Qualification',
    },
    domainName: {
        type: String,
        default: 'Qualification',
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
    imageUrls: {
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
    leadData: {
        type: Object,
        default: null,
    },
    opportunityStageOptions: {
        type: Array,
        default: () => [],
    },
    opportunityStatusOptions: {
        type: Array,
        default: () => [],
    },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showLeadNotConvertedModal = ref(false);
const isConverting = ref(false);
const formRef = ref(null);

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);
const sublists = computed(() => props.formSchema?.sublists || []);
const linkedOpportunities = computed(() => props.record?.opportunities ?? []);
const isConverted = computed(() => linkedOpportunities.value.length > 0);

const getStageLabel = (id) => props.opportunityStageOptions.find(o => o.id === id)?.name ?? `Stage ${id}`;
const getStageBg = (id) => props.opportunityStageOptions.find(o => o.id === id)?.bgClass ?? 'bg-gray-200';
const getStatusLabel = (id) => props.opportunityStatusOptions.find(o => o.id === id)?.name ?? `Status ${id}`;
const getStatusBg = (id) => props.opportunityStatusOptions.find(o => o.id === id)?.bgClass ?? 'bg-gray-200';

const formatCurrency = (val) => {
    if (val == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Qualifications', href: route('qualifications.index') },
    { label: props.record?.display_name ?? 'Qualification' },
]);

const opportunityCreateUrl = computed(() => {
    return route('opportunities.create') + `?from=qualification&id=${props.record?.id}`;
});

const handleEdit = () => { isEditMode.value = true; };
const handleCancel = () => { isEditMode.value = false; };
const handleSave = () => { formRef.value?.submitForm(); };

const handleSubmit = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};

const handleUpdated = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};

const handleDelete = () => { showDeleteModal.value = true; };
const cancelDelete = () => { showDeleteModal.value = false; };

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('qualifications.destroy', recordIdentifier.value), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const handleCreateOpportunity = () => {
    if (props.leadData?.converted_customer_id) {
        router.visit(opportunityCreateUrl.value);
    } else {
        showLeadNotConvertedModal.value = true;
    }
};

const closeLeadNotConvertedModal = () => {
    showLeadNotConvertedModal.value = false;
};

const convertLeadAndCreateOpportunity = () => {
    if (!props.leadData?.id) return;

    isConverting.value = true;
    proxy.$root.showLoading('Converting lead to customer...');

    router.post(route('leads.convert', props.leadData.id), {}, {
        onSuccess: () => {
            showLeadNotConvertedModal.value = false;
            proxy.$root.hideLoading();
            proxy.$root.createToast('success', 'Lead converted to customer successfully.');
            router.visit(opportunityCreateUrl.value);
        },
        onError: () => {
            proxy.$root.hideLoading();
            proxy.$root.createToast('error', 'Failed to convert lead. Please try again.');
        },
        onFinish: () => {
            isConverting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`${recordTitle} - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ record?.display_name }}
                        </h2>
                        <span
                            v-if="isConverted"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300"
                        >
                            Opportunity Created
                        </span>
                    </div>

                    <!-- View Mode Buttons -->
                    <div v-if="!isEditMode" class="flex items-center space-x-3">
                        <button
                            v-if="!isConverted"
                            @click="handleCreateOpportunity"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Create Opportunity
                        </button>
                        <button
                            @click="handleEdit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <button
                            @click="handleDelete"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>

                    <!-- Edit Mode Buttons -->
                    <div v-else class="flex items-center space-x-3">
                        <button
                            @click="handleSave"
                            :disabled="formRef?.isProcessing"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="formRef?.isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ formRef?.isProcessing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <button
                            @click="handleCancel"
                            :disabled="formRef?.isProcessing"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex gap-6">
            <!-- Main Content -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <Form
                        ref="formRef"
                        :schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :record="record"
                        :record-type="recordType"
                        :record-title="recordTitle"
                        :record-identifier="recordIdentifier"
                        :enum-options="enumOptions"
                        :image-urls="imageUrls"
                        :account="account"
                        :timezones="timezones"
                        :mode="isEditMode ? 'edit' : 'view'"
                        :prevent-redirect="true"
                        :form-id="`form-${recordType}-${record?.id ?? record?.uuid}`"
                        @submit="handleSubmit"
                        @updated="handleUpdated"
                        @cancel="handleCancel"
                    />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-80 flex-shrink-0 space-y-6">
                <!-- Qualification Info Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg p-5">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Qualification Info</h3>

                    <div class="space-y-3 text-sm">
                        <!-- Lead Info -->
                        <div v-if="leadData" class="flex flex-col gap-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Lead</span>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('leads.show', leadData.id)"
                                    class="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    {{ leadData.display_name }}
                                </Link>
                                <span
                                    v-if="leadData.converted"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                                >
                                    Customer
                                </span>
                            </div>
                        </div>

                        <!-- Converted at -->
                        <div v-if="record?.converted_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Opportunity Created</span>
                            <span class="font-medium text-primary-600 dark:text-primary-400">
                                {{ new Date(record.converted_at).toLocaleDateString() }}
                            </span>
                        </div>

                        <!-- Created at -->
                        <div v-if="record?.created_at" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ new Date(record.created_at).toLocaleDateString() }}
                            </span>
                        </div>

                        <!-- Create Opportunity Action -->
                        <div class="pt-2">
                            <button
                                @click="handleCreateOpportunity"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Create Opportunity
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Linked Opportunities -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Opportunities</h3>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                            {{ linkedOpportunities.length }}
                        </span>
                    </div>

                    <div v-if="linkedOpportunities.length === 0" class="px-5 py-4">
                        <p class="text-xs text-gray-400 dark:text-gray-500 text-center">No opportunities yet</p>
                    </div>

                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <Link
                            v-for="opp in linkedOpportunities"
                            :key="opp.id"
                            :href="route('opportunities.show', opp.id)"
                            class="flex items-start justify-between px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                        >
                            <div class="flex flex-col gap-1 min-w-0">
                                <span class="text-sm font-medium text-primary-600 dark:text-primary-400 group-hover:underline">
                                    OPP-{{ opp.sequence ?? opp.id }}
                                </span>
                                <div class="flex flex-wrap gap-1 mt-0.5">
                                    <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium', getStageBg(opp.stage)]">
                                        {{ getStageLabel(opp.stage) }}
                                    </span>
                                    <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium', getStatusBg(opp.status)]">
                                        {{ getStatusLabel(opp.status) }}
                                    </span>
                                </div>
                            </div>
                            <span v-if="opp.estimated_value" class="text-xs font-semibold text-gray-700 dark:text-gray-300 ml-2 mt-0.5 flex-shrink-0">
                                {{ formatCurrency(opp.estimated_value) }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sublists below the form -->
        <div v-if="sublists.length > 0 && domainName" class="mt-6">
            <Sublist
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="sublists"
            />
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Delete Qualification</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete {{ record?.display_name }}? This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center space-x-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                    <button
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Customer Required Modal -->
        <Modal :show="showLeadNotConvertedModal" @close="closeLeadNotConvertedModal" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Record Required</h3>
                    <button @click="closeLeadNotConvertedModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mb-6 space-y-3">
                    <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm text-amber-700 dark:text-amber-300">
                            <p class="font-medium mb-1">Opportunities require a Customer record.</p>
                            <p>The lead <strong>{{ leadData?.display_name }}</strong> hasn't been converted to a customer yet. You'll need to convert the lead first before creating an opportunity.</p>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">What happens next:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>The lead will be converted to a customer record</li>
                            <li>You'll be taken to the new opportunity form</li>
                            <li>The customer and qualification will be pre-filled</li>
                        </ol>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="closeLeadNotConvertedModal"
                        type="button"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="convertLeadAndCreateOpportunity"
                        :disabled="isConverting"
                        type="button"
                        class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isConverting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isConverting ? 'Converting...' : 'Convert Lead & Create Opportunity' }}
                    </button>
                </div>
            </div>
        </Modal>

    </TenantLayout>
</template>
