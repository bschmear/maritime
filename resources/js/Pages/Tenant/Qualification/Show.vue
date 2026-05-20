<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, getCurrentInstance, ref } from 'vue';

const appInstance = getCurrentInstance();

const toast = (type, message) => {
    const root = appInstance?.proxy?.$root;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, message);
    }
};

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'qualifications' },
    recordTitle: { type: String, default: 'Qualification' },
    domainName: { type: String, default: 'Qualification' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    leadData: { type: Object, default: null },
    opportunityStageOptions: { type: Array, default: () => [] },
    opportunityStatusOptions: { type: Array, default: () => [] },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showLeadNotConvertedModal = ref(false);
const isConverting = ref(false);

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);
const linkedOpportunities = computed(() => props.record?.opportunities ?? []);
const hasOpportunities = computed(() => linkedOpportunities.value.length > 0);

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const qualificationNotes = computed(() => props.record?.notes ?? []);

const primaryNote = computed(() => qualificationNotes.value[0] ?? null);

const qualificationLabel = computed(() =>
    props.record?.display_name ? props.record.display_name : `QLF-${props.record?.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Qualifications', href: route('qualifications.index') },
    { label: qualificationLabel.value },
]);

const opportunityCreateUrl = computed(
    () => route('opportunities.create') + `?from=qualification&id=${props.record?.id}`,
);

const getEnumLabel = (enumKey, value) => {
    if (value == null || value === '') return '—';
    const opts = props.enumOptions?.[enumKey] ?? [];
    const opt = opts.find((o) => o.id == value || o.value == value);
    return opt ? (opt.name ?? opt.label) : String(value);
};

const getEnumBadge = (enumKey, value) => {
    const opts = props.enumOptions?.[enumKey] ?? [];
    const opt = opts.find((o) => o.id == value || o.value == value);
    return {
        name: opt?.name ?? opt?.label ?? String(value ?? '—'),
        bgClass: opt?.bgClass ?? 'bg-gray-100 dark:bg-gray-700',
        textClass: opt?.color ? `text-${opt.color}-700 dark:text-${opt.color}-300` : 'text-gray-700 dark:text-gray-300',
    };
};

const STATUS_ENUM = 'App\\Enums\\Leads\\Status';
const statusBadge = computed(() => getEnumBadge(STATUS_ENUM, props.record?.status));

const intendedUseLabel = computed(() =>
    getEnumLabel('App\\Enums\\Entity\\IntendedUse', props.record?.intended_use),
);
const ownershipTypeLabel = computed(() =>
    getEnumLabel('App\\Enums\\Entity\\OwnershipType', props.record?.ownership_type),
);
const budgetRangeLabel = computed(() =>
    getEnumLabel('App\\Enums\\Entity\\BudgetRange', props.record?.budget_range),
);
const purchaseTimelineLabel = computed(() =>
    getEnumLabel('App\\Enums\\Entity\\PurchaseTimeline', props.record?.purchase_timeline),
);
const leadSourceLabel = computed(() =>
    getEnumLabel('App\\Enums\\Entity\\Source', props.record?.lead_source),
);

const getStageLabel = (id) => props.opportunityStageOptions.find((o) => o.id === id)?.name ?? `Stage ${id}`;
const getStageBg = (id) => props.opportunityStageOptions.find((o) => o.id === id)?.bgClass ?? 'bg-gray-200';
const getStatusLabel = (id) => props.opportunityStatusOptions.find((o) => o.id === id)?.name ?? `Status ${id}`;
const getStatusBg = (id) => props.opportunityStatusOptions.find((o) => o.id === id)?.bgClass ?? 'bg-gray-200';

const formatCurrency = (val) => {
    if (val == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const leadDisplayName = computed(() => {
    if (props.leadData?.display_name) return props.leadData.display_name;
    const lead = props.record?.lead;
    return lead?.display_name ?? '—';
});

const handleDelete = () => {
    showDeleteModal.value = true;
};

const cancelDelete = () => {
    showDeleteModal.value = false;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('qualifications.destroy', recordIdentifier.value), {
        onSuccess: () => router.visit(route('qualifications.index')),
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

    router.post(
        route('leads.convert', props.leadData.id),
        { redirect: opportunityCreateUrl.value },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                showLeadNotConvertedModal.value = false;
                const flash = page.props.flash ?? {};
                if (flash.error) {
                    toast('error', flash.error);
                    return;
                }
                if (flash.success) {
                    toast('success', flash.success);
                }
                if (!page.url?.includes('/opportunities/create')) {
                    router.visit(opportunityCreateUrl.value);
                }
            },
            onError: (errors) => {
                const message =
                    errors?.subsidiary_id?.[0]
                    ?? errors?.contact_id?.[0]
                    ?? Object.values(errors ?? {})?.flat?.()?.[0]
                    ?? 'Failed to convert lead. Please try again.';
                toast('error', message);
            },
            onFinish: () => {
                isConverting.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`${qualificationLabel} - Qualification`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ qualificationLabel }}
                        </h2>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                            :class="[statusBadge.bgClass, statusBadge.textClass]"
                        >
                            {{ statusBadge.name }}
                        </span>
                        <span
                            v-if="hasOpportunities"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300"
                        >
                            Opportunity Created
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="handleCreateOpportunity"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Create Opportunity
                        </button>
                        <Link
                            :href="route('qualifications.edit', record.id)"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </Link>
                        <button
                            type="button"
                            @click="handleDelete"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- Main column -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Header card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-2xl font-bold text-white">QUALIFICATION</h1>
                                    <p class="text-primary-100 text-base mt-1">Lead qualification &amp; product requirements</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-primary-200 text-xs font-medium">Reference</div>
                                    <div class="text-white text-lg font-mono">{{ qualificationLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Lead & assignment | Qualification details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Lead &amp; assignment
                                    </h3>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.lead_id?.label || 'Lead' }}
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <Link
                                                v-if="record.lead_id"
                                                :href="route('leads.show', record.lead_id)"
                                                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                            >
                                                {{ leadDisplayName }}
                                            </Link>
                                            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                            <span
                                                v-if="leadData?.converted"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                                            >
                                                Customer
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.user_id?.label || 'Salesperson' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.user?.display_name ?? '—' }}
                                        </div>
                                    </div>

                                    <div v-if="fieldsSchema.lead_source">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.lead_source?.label || 'Lead Source' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ leadSourceLabel }}</div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide border-b pb-2 border-gray-200 dark:border-gray-700">
                                        Qualification details
                                    </h3>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.status?.label || 'Status' }}
                                        </div>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="[statusBadge.bgClass, statusBadge.textClass]"
                                        >
                                            {{ statusBadge.name }}
                                        </span>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.intended_use?.label || 'Intended Use' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ intendedUseLabel }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.ownership_type?.label || 'Ownership Type' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ ownershipTypeLabel }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.purchase_timeline?.label || 'Purchase Timeline' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ purchaseTimelineLabel }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product requirements -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
                                    Product requirements
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.desired_brand?.label || 'Desired Brand' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.desired_brand?.display_name ?? '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.desired_model?.label || 'Desired Model' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.desired_model || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.preferred_length?.label || 'Preferred Length' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.preferred_length != null ? `${record.preferred_length} ft` : '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.max_weight?.label || 'Max Weight' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ record.max_weight != null ? `${record.max_weight} lbs` : '—' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.budget_range?.label || 'Budget Range' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ budgetRangeLabel }}</div>
                                    </div>
                                </div>

                                <div class="col-span-full flex flex-wrap gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <span
                                        :class="record.needs_engine
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="record.needs_engine" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_engine?.label || 'Needs Engine' }}
                                    </span>
                                    <span
                                        :class="record.needs_trailer
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="record.needs_trailer" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.needs_trailer?.label || 'Needs Trailer' }}
                                    </span>
                                    <span
                                        :class="record.requires_delivery
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                                    >
                                        <svg v-if="record.requires_delivery" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ fieldsSchema.requires_delivery?.label || 'Requires Delivery' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Delivery -->
                            <div
                                v-if="record.requires_delivery || record.delivery_location || record.delivery_state || record.delivery_country"
                                class="border-t border-gray-200 dark:border-gray-700 pt-5"
                            >
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
                                    Delivery
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_location?.label || 'Delivery Location' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_location || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_state?.label || 'Delivery State' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_state || '—' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                            {{ fieldsSchema.delivery_country?.label || 'Delivery Country' }}
                                        </div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ record.delivery_country || '—' }}</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <button
                                type="button"
                                @click="handleCreateOpportunity"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Create Opportunity
                            </button>
                            <Link
                                :href="route('qualifications.edit', record.id)"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Qualification
                            </Link>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-700 dark:bg-gray-700">
                            <span class="text-sm font-semibold text-white">Summary</span>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">Status</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right">{{ statusBadge.name }}</span>
                            </div>
                            <div v-if="record.qualified_at" class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">Qualified</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(record.qualified_at) }}</span>
                            </div>
                            <div v-if="record.converted_at" class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">Opportunity created</span>
                                <span class="font-medium text-primary-600 dark:text-primary-400">{{ formatDate(record.converted_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(record.created_at) }}</span>
                            </div>
                            <div v-if="record.updated_at" class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">Updated</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(record.updated_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Opportunities</span>
                                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ linkedOpportunities.length }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Linked opportunities -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
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
                                <span
                                    v-if="opp.estimated_value"
                                    class="text-xs font-semibold text-gray-700 dark:text-gray-300 ml-2 mt-0.5 flex-shrink-0"
                                >
                                    {{ formatCurrency(opp.estimated_value) }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes (full width) -->
            <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Notes</h2>
                    <span
                        v-if="primaryNote?.user?.display_name"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        Updated by {{ primaryNote.user.display_name }}
                        <template v-if="primaryNote.updated_at">
                            · {{ formatDateTime(primaryNote.updated_at) }}
                        </template>
                    </span>
                </div>
                <div v-if="primaryNote?.body" class="p-6">
                    <div
                        class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300"
                        v-html="primaryNote.body"
                    />
                </div>
                <div v-else class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No notes yet.
                    <Link
                        :href="route('qualifications.edit', record.id)"
                        class="text-primary-600 dark:text-primary-400 hover:underline ml-1"
                    >
                        Add notes
                    </Link>
                </div>
            </div>

            <!-- Sublists -->
            <Sublist
                v-if="visibleSublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="visibleSublists"
            />
        </div>

        <!-- Delete modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Delete Qualification</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete {{ qualificationLabel }}? This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        @click="cancelDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Customer required modal -->
        <Modal :show="showLeadNotConvertedModal" @close="closeLeadNotConvertedModal" max-width="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Record Required</h3>
                    <button type="button" @click="closeLeadNotConvertedModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
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
                            <p>The lead <strong>{{ leadData?.display_name }}</strong> hasn't been converted to a customer yet.</p>
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
                        type="button"
                        @click="closeLeadNotConvertedModal"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="convertLeadAndCreateOpportunity"
                        :disabled="isConverting"
                        class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
                    >
                        {{ isConverting ? 'Converting...' : 'Convert Lead & Create Opportunity' }}
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
