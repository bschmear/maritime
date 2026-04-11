<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Form from '@/Components/Tenant/Form.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import ScorePanel from '@/Components/Tenant/ScorePanel.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    record: {
        type: Object,
        required: true,
    },
    recordType: {
        type: String,
        default: 'leads',
    },
    recordTitle: {
        type: String,
        default: 'Lead',
    },
    domainName: {
        type: String,
        default: 'Lead',
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
    scores: {
        type: Array,
        default: () => [],
    },
    /** Canonical morph class for scores (from PHP {@see Lead::class}); avoids template escaping bugs. */
    scoreScorableType: {
        type: String,
        default: 'Lead',
    },
});

const isEditMode      = ref(false);
const showDeleteModal = ref(false);
const isDeleting      = ref(false);
const formRef         = ref(null);

const recordIdentifier = computed(() => props.record?.id ?? props.record?.uuid);
const sublists         = computed(() => props.formSchema?.sublists || []);

const customerCreateHref = computed(() => {
    const cid = props.record?.contact_id ?? props.record?.contact?.id;
    if (cid != null) {
        return route('customers.create', { contact_id: cid });
    }
    return route('customers.create');
});

const breadcrumbItems = computed(() => [
    { label: 'Home',  href: route('dashboard') },
    { label: 'Leads', href: route('leads.index') },
    { label: props.record?.display_name ?? 'Lead' },
]);

// ── edit ──────────────────────────────────────────────────────────────────────
const handleEdit   = () => { isEditMode.value = true; };
const handleCancel = () => { isEditMode.value = false; };
const handleSave   = () => { formRef.value?.submitForm(); };

const handleSubmit = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};
const handleUpdated = () => {
    isEditMode.value = false;
    router.reload({ only: ['record', 'imageUrls'] });
};

// ── delete ────────────────────────────────────────────────────────────────────
const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('leads.destroy', recordIdentifier.value), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const findOption = (enumClass, val) => {
    if (val == null) return null;
    const options = props.enumOptions?.[enumClass] ?? [];
    return options.find((o) => o.id === val || o.value === val || o.id === Number(val)) ?? null;
};

const statusOption   = computed(() => findOption('App\\Enums\\Leads\\Status',   props.record?.status_id));
const priorityOption = computed(() => findOption('App\\Enums\\Entity\\Priority', props.record?.priority_id));
</script>

<template>
    <Head :title="`${recordTitle} - ${record?.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <!-- Title + status badge -->
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ record?.display_name }}
                        </h2>
                        <span
                            v-if="record?.converted"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300"
                        >
                            Converted
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300"
                        >
                            Active lead
                        </span>
                    </div>

                    <!-- View mode actions -->
                    <div v-if="!isEditMode" class="flex items-center gap-2">
                        <Link
                            v-if="!record?.converted"
                            :href="customerCreateHref"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Add customer profile
                        </Link>
                        <button
                            v-if="!record?.converted"
                            @click="handleEdit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <button
                            @click="showDeleteModal = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-md hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-800 dark:border-red-700 dark:hover:bg-red-900/20"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>

                    <!-- Edit mode actions -->
                    <div v-else class="flex items-center gap-2">
                        <button
                            @click="handleSave"
                            :disabled="formRef?.isProcessing"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="formRef?.isProcessing" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ formRef?.isProcessing ? 'Saving…' : 'Save changes' }}
                        </button>
                        <button
                            @click="isEditMode = false"
                            :disabled="formRef?.isProcessing"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Page body ─────────────────────────────────────────────────────── -->
        <div class="flex gap-6">

            <!-- Main column -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
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

                <!-- Sublists -->
                <div v-if="sublists.length > 0 && domainName" class="mt-6">
                    <Sublist
                        :parent-record="record"
                        :parent-domain="domainName"
                        :sublists="sublists"
                    />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-80 lg:w-96 flex-shrink-0 space-y-4">

                <!-- Lead status card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Lead status</h3>
                    </div>
                    <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <span
                                v-if="statusOption"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="statusOption.bgClass"
                            >
                                {{ statusOption.name }}
                            </span>
                            <span v-else class="font-medium text-gray-400 dark:text-gray-500">—</span>
                        </li>
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Priority</span>
                            <span
                                v-if="priorityOption"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="priorityOption.bgClass"
                            >
                                {{ priorityOption.name }}
                            </span>
                            <span v-else class="font-medium text-gray-400 dark:text-gray-500">—</span>
                        </li>
                        <li v-if="record?.converted_at" class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Converted</span>
                            <span class="font-medium text-green-600 dark:text-green-400">
                                {{ new Date(record.converted_at).toLocaleDateString() }}
                            </span>
                        </li>
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ record?.created_at ? new Date(record.created_at).toLocaleDateString() : '—' }}
                            </span>
                        </li>
                    </ul>

                    <!-- Convert CTA (unconverted) -->
                    <div v-if="!record?.converted" class="px-4 pb-4 pt-2">
                        <Link
                            :href="customerCreateHref"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Add customer profile
                        </Link>
                    </div>

                    <!-- View customer link (converted) -->
                    <div v-else class="px-4 pb-4 pt-2">
                        <Link
                            :href="route('customers.show', record.converted_customer.id)"
                            class="flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-sm font-medium text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                        >
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            View customer record
                        </Link>
                    </div>
                </div>

                <!-- Contact / roles card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact</h3>
                    </div>
                    <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Contact record</span>
                            <Link
                                v-if="record?.contact?.id"
                                :href="route('contacts.show', record.contact.id)"
                                class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                {{ record.contact.display_name ?? 'View →' }}
                            </Link>
                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                        </li>
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-gray-500 dark:text-gray-400">Customer profile</span>
                            <Link
                                v-if="record?.converted_customer?.id"
                                :href="route('customers.show', record.converted_customer.id)"
                                class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                View →
                            </Link>
                            <span v-else class="text-gray-400 dark:text-gray-500">None</span>
                        </li>
                    </ul>
                </div>

                <!-- Score panel -->
                <ScorePanel
                    :scorable-type="scoreScorableType"
                    :scorable-id="record.id"
                    :subscription-level="3"
                    :initial-scores="scores"
                />

            </div>
        </div>

        <!-- ── Delete modal ──────────────────────────────────────────────────── -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete lead</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete <span class="font-medium text-gray-700 dark:text-gray-300">{{ record?.display_name }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isDeleting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        @click="showDeleteModal = false"
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>


    </TenantLayout>
</template>