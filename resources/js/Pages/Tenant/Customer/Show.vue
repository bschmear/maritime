<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import CustomerForm from '@/Components/Tenant/CustomerForm.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import { ref, computed } from 'vue';

const page = usePage();

const props = defineProps({
    record: { type: Object, required: true },
    domainName: { type: String, default: 'Customer' },
    recordType: { type: String, default: 'customers' },
    recordTitle: { type: String, default: 'Customers' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    imageUrls: { type: Object, default: () => ({}) },
});

const isEditMode = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const sendingPortal = ref(false);

const sublists = computed(() => props.formSchema?.sublists || []);

const displayName = computed(
    () =>
        props.record.display_name?.trim() ||
        [props.record.first_name, props.record.last_name].filter(Boolean).join(' ') ||
        `Customer #${props.record.id}`,
);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.recordTitle, href: route(`${props.recordType}.index`) },
    { label: displayName.value },
]);

function enumLabel(fieldKey, val) {
    const v = val !== undefined ? val : props.record?.[fieldKey];
    if (v === null || v === undefined || v === '') return null;
    const def = props.fieldsSchema?.[fieldKey];
    if (!def?.enum) return String(v);
    const opts = props.enumOptions?.[def.enum] || [];
    const hit = opts.find(
        (o) =>
            o.id === v || o.value === v || String(o.id) === String(v) || String(o.value) === String(v),
    );
    return hit?.name ?? String(v);
}

function formatDate(d) {
    if (!d) return null;
    try {
        return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return String(d);
    }
}

function formatMoney(value, currency) {
    if (value === null || value === undefined || value === '') return null;
    const num = parseFloat(value);
    if (isNaN(num)) return null;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || props.record?.currency || 'USD',
        minimumFractionDigits: 2,
    }).format(num);
}

function formatNumber(value) {
    if (value === null || value === undefined || value === '') return null;
    const num = Number(value);
    if (isNaN(num)) return null;
    return new Intl.NumberFormat('en-US').format(num);
}

function boolLabel(v) {
    if (v === true || v === 1 || v === '1' || v === 'true') return 'Yes';
    if (v === false || v === 0 || v === '0' || v === 'false') return 'No';
    return null;
}

const statusLabel = computed(() => enumLabel('status_id'));
const priorityLabel = computed(() => enumLabel('priority_id'));
const sourceLabel = computed(() => enumLabel('source_id'));

const statusColor = computed(() => {
    const s = String(props.record?.status_id ?? '').toLowerCase();
    const n = statusLabel.value?.toLowerCase() ?? '';
    if (n.includes('active') || s === '1') return 'green';
    if (n.includes('inactive') || n.includes('closed')) return 'red';
    if (n.includes('prospect') || n.includes('lead')) return 'blue';
    return 'gray';
});

const priorityColor = computed(() => {
    const n = priorityLabel.value?.toLowerCase() ?? '';
    if (n.includes('high') || n.includes('urgent')) return 'red';
    if (n.includes('medium') || n.includes('normal')) return 'yellow';
    if (n.includes('low')) return 'green';
    return 'gray';
});

const handleSaved = () => {
    isEditMode.value = false;
    router.reload({ only: ['record'] });
};

const handleCancelEdit = () => {
    isEditMode.value = false;
};

const sendPortalLink = () => {
    const cid = props.record.contact_id;
    if (!cid || !props.record.email || sendingPortal.value) {
        return;
    }
    sendingPortal.value = true;
    router.post(route('contacts.send-portal-link', cid), {}, {
        preserveScroll: true,
        onFinish: () => {
            sendingPortal.value = false;
        },
    });
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => router.visit(route(`${props.recordType}.index`)),
        onError: () => { isDeleting.value = false; },
        onFinish: () => { isDeleting.value = false; showDeleteModal.value = false; },
    });
};
</script>

<template>
    <Head :title="`${recordTitle} — ${displayName}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ displayName }}
                    </h2>
                    <div v-if="!isEditMode" class="flex items-center gap-2">
                        <button
                            class="inline-flex items-center px-4 py-2 text-md font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700"
                            @click="isEditMode = true"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <button
                            class="inline-flex items-center px-4 py-2 text-md font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
                            @click="showDeleteModal = true"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <button
                            class="inline-flex items-center px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                            @click="handleCancelEdit"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── EDIT MODE ─────────────────────────────────────────── -->
        <div v-if="isEditMode" class="w-full">
            <CustomerForm
                :record="record"
                :form-schema="formSchema"
                :fields-schema="fieldsSchema"
                :enum-options="enumOptions"
                :account="account"
                :timezones="timezones"
                :image-urls="imageUrls"
                mode="edit"
                :record-type="recordType"
                :record-title="recordTitle"
                @saved="handleSaved"
                @cancelled="handleCancelEdit"
            />
        </div>

        <!-- ── VIEW / DASHBOARD MODE ─────────────────────────────── -->
        <template v-else>
            <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                {{ page.props.flash.error }}
            </div>

            <!-- Hero header card -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-800 px-6 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-white">{{ displayName }}</h1>
                            <p v-if="record.company" class="text-primary-100 text-md mt-0.5">
                                {{ record.company }}
                                <span v-if="record.title || record.position">
                                    · {{ [record.title, record.position].filter(Boolean).join(', ') }}
                                </span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span
                                    v-if="statusLabel"
                                    :class="{
                                        'bg-green-100 text-green-800': statusColor === 'green',
                                        'bg-red-100 text-red-800': statusColor === 'red',
                                        'bg-blue-100 text-blue-800': statusColor === 'blue',
                                        'bg-gray-100 text-gray-700': statusColor === 'gray',
                                    }"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-md font-semibold"
                                >
                                    {{ statusLabel }}
                                </span>
                                <!-- <span
                                    v-if="priorityLabel"
                                    :class="{
                                        'bg-red-100 text-red-800': priorityColor === 'red',
                                        'bg-yellow-100 text-yellow-800': priorityColor === 'yellow',
                                        'bg-green-100 text-green-800': priorityColor === 'green',
                                        'bg-gray-100 text-gray-700': priorityColor === 'gray',
                                    }"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-md font-semibold"
                                >
                                    {{ priorityLabel }}
                                </span> -->
                                <span
                                    v-if="enumLabel('account_status')"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-md font-semibold bg-white/20 text-white"
                                >
                                    {{ enumLabel('account_status') }}
                                </span>
                                <span
                                    v-if="enumLabel('customer_type')"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-md font-semibold bg-white/20 text-white"
                                >
                                    {{ enumLabel('customer_type') }}
                                </span>
                                <span v-if="record.tier" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-md font-semibold bg-white/20 text-white">
                                    Tier: {{ record.tier }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Link
                                v-if="record.contact_id"
                                :href="route('contacts.show', record.contact_id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-md font-medium bg-white/15 text-white hover:bg-white/25 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Contact Record
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Stats strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Lifetime Value</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ formatMoney(record.lifetime_value) ?? '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Orders</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ record.total_orders !== null && record.total_orders !== undefined ? formatNumber(record.total_orders) : '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avg. Order</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ formatMoney(record.average_order_value) ?? '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ formatMoney(record.current_balance) ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Two-column dashboard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- ── Left column (2/3) ─────────────────────────── -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Contact Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Contact</h3>
                            <Link
                                v-if="record.contact_id"
                                :href="route('contacts.show', record.contact_id)"
                                class="text-md text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                View contact record →
                            </Link>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <template v-if="record.email">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Email</p>
                                    <a :href="`mailto:${record.email}`" class="text-md text-primary-600 dark:text-primary-400 hover:underline">{{ record.email }}</a>
                                </div>
                            </template>
                            <template v-if="record.secondary_email">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Secondary Email</p>
                                    <a :href="`mailto:${record.secondary_email}`" class="text-md text-primary-600 dark:text-primary-400 hover:underline">{{ record.secondary_email }}</a>
                                </div>
                            </template>
                            <template v-if="record.phone">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Phone</p>
                                    <a :href="`tel:${record.phone}`" class="text-md text-gray-900 dark:text-gray-100 hover:underline">{{ record.phone }}</a>
                                </div>
                            </template>
                            <template v-if="record.mobile">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Mobile</p>
                                    <a :href="`tel:${record.mobile}`" class="text-md text-gray-900 dark:text-gray-100 hover:underline">{{ record.mobile }}</a>
                                </div>
                            </template>
                            <template v-if="enumLabel('preferred_contact_method')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Preferred Contact</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('preferred_contact_method') }}</p>
                                </div>
                            </template>
                            <template v-if="enumLabel('preferred_contact_time')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Best Time to Contact</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('preferred_contact_time') }}</p>
                                </div>
                            </template>

                            <!-- Address block -->
                            <template v-if="record.address_line_1 || record.city">
                                <div class="sm:col-span-2">
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Address</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100 leading-relaxed">
                                        {{ [record.address_line_1, record.address_line_2].filter(Boolean).join(', ') }}<br v-if="record.address_line_1 || record.address_line_2" />
                                        {{ [record.city, record.state, record.postal_code].filter(Boolean).join(', ') }}<br v-if="record.city || record.state || record.postal_code" />
                                        {{ record.country }}
                                    </p>
                                </div>
                            </template>

                            <!-- Socials from contact -->
                            <template v-if="record.contact?.website">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Website</p>
                                    <a
                                        :href="record.contact.website.startsWith('http') ? record.contact.website : `https://${record.contact.website}`"
                                        target="_blank" rel="noopener noreferrer"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline break-all"
                                    >{{ record.contact.website }}</a>
                                </div>
                            </template>
                            <template v-if="record.contact?.linkedin">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">LinkedIn</p>
                                    <a
                                        :href="record.contact.linkedin.startsWith('http') ? record.contact.linkedin : `https://${record.contact.linkedin}`"
                                        target="_blank" rel="noopener noreferrer"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline break-all"
                                    >{{ record.contact.linkedin }}</a>
                                </div>
                            </template>
                            <template v-if="record.contact?.facebook">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Facebook</p>
                                    <a
                                        :href="record.contact.facebook.startsWith('http') ? record.contact.facebook : `https://${record.contact.facebook}`"
                                        target="_blank" rel="noopener noreferrer"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline break-all"
                                    >{{ record.contact.facebook }}</a>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Account</h3>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <template v-if="record.subsidiary?.display_name">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Subsidiary</p>
                                    <Link
                                        :href="route('subsidiaries.show', record.subsidiary.id)"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline"
                                    >
                                        {{ record.subsidiary.display_name }}
                                    </Link>
                                </div>
                            </template>
                            <template v-else-if="record.subsidiary_id">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Subsidiary</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100 font-mono">#{{ record.subsidiary_id }}</p>
                                </div>
                            </template>
                            <template v-if="enumLabel('account_status')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Account Status</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('account_status') }}</p>
                                </div>
                            </template>
                            <template v-if="enumLabel('customer_type')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Customer Type</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('customer_type') }}</p>
                                </div>
                            </template>
                            <template v-if="record.tier">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Tier</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ record.tier }}</p>
                                </div>
                            </template>
                            <template v-if="record.billing_email">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Billing Email</p>
                                    <a :href="`mailto:${record.billing_email}`" class="text-md text-primary-600 dark:text-primary-400 hover:underline">{{ record.billing_email }}</a>
                                </div>
                            </template>
                            <template v-if="enumLabel('payment_terms')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Preferred Payment Terms</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('payment_terms') }}</p>
                                </div>
                            </template>
                            <template v-if="enumLabel('payment_method')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Payment Method</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('payment_method') }}</p>
                                </div>
                            </template>
                            <template v-if="enumLabel('currency')">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Currency</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ enumLabel('currency') }}</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Billing & Tax -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Billing &amp; Tax</h3>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <template v-if="record.credit_limit !== null && record.credit_limit !== undefined && record.credit_limit !== ''">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Credit Limit</p>
                                    <p class="text-md font-semibold text-gray-900 dark:text-gray-100">{{ formatMoney(record.credit_limit) }}</p>
                                </div>
                            </template>
                            <template v-if="record.tax_id">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Tax ID / EIN</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100 font-mono">{{ record.tax_id }}</p>
                                </div>
                            </template>
                            <div>
                                <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Tax Exempt</p>
                                <p class="text-md text-gray-900 dark:text-gray-100">{{ boolLabel(record.tax_exempt) ?? '—' }}</p>
                            </div>
                            <template v-if="record.tax_exempt && record.tax_exempt_reason">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Exempt Reason</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ record.tax_exempt_reason }}</p>
                                </div>
                            </template>
                            <template v-if="record.purchase_order_required">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">PO Required</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ record.purchase_order_required }}</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Contract & Dates -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Contract &amp; Dates</h3>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <template v-if="record.contract_start">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Contract Start</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ formatDate(record.contract_start) }}</p>
                                </div>
                            </template>
                            <template v-if="record.contract_end">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Contract End</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ formatDate(record.contract_end) }}</p>
                                </div>
                            </template>
                            <div>
                                <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Auto Renew</p>
                                <p class="text-md text-gray-900 dark:text-gray-100">{{ boolLabel(record.auto_renew) ?? '—' }}</p>
                            </div>
                            <template v-if="record.first_purchase_at">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">First Purchase</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ formatDate(record.first_purchase_at) }}</p>
                                </div>
                            </template>
                            <template v-if="record.last_purchase_at">
                                <div>
                                    <p class="text-md text-gray-500 dark:text-gray-400 font-medium mb-0.5">Last Purchase</p>
                                    <p class="text-md text-gray-900 dark:text-gray-100">{{ formatDate(record.last_purchase_at) }}</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="record.notes" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Notes</h3>
                        </div>
                        <div class="p-5">
                            <p class="text-md text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ record.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Right sidebar (1/3) ─────────────────────── -->
                <div class="space-y-6">

                    <!-- CRM Snapshot -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">CRM</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <template v-if="record.subsidiary?.display_name">
                                <div class="flex justify-between items-start gap-2">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium shrink-0">Subsidiary</span>
                                    <Link
                                        :href="route('subsidiaries.show', record.subsidiary.id)"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline text-right"
                                    >
                                        {{ record.subsidiary.display_name }}
                                    </Link>
                                </div>
                            </template>
                            <template v-if="record.assigned_user?.display_name">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Assigned To</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.assigned_user.display_name }}</span>
                                </div>
                            </template>
                            <template v-if="sourceLabel">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Source</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ sourceLabel }}</span>
                                </div>
                            </template>
                            <template v-if="record.lead_score !== null && record.lead_score !== undefined">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Lead Score</span>
                                    <span class="text-md font-semibold text-gray-900 dark:text-gray-100 text-right">{{ record.lead_score }}</span>
                                </div>
                            </template>
                            <template v-if="record.last_contacted_at">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Last Contacted</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ formatDate(record.last_contacted_at) }}</span>
                                </div>
                            </template>
                            <template v-if="record.next_followup_at">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Next Follow-Up</span>
                                    <span class="text-md font-medium text-primary-600 dark:text-primary-400 text-right">{{ formatDate(record.next_followup_at) }}</span>
                                </div>
                            </template>
                            <template v-if="record.inactive">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Inactive</span>
                                    <span class="text-md font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Yes</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Customer portal -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Customer portal</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <p class="text-md text-gray-600 dark:text-gray-300 leading-relaxed">
                                Email the linked contact’s <strong>primary email</strong> a message with your customer portal link: how to <strong>create an account</strong> (first visit) or <strong>sign in</strong>.
                            </p>
                            <button
                                type="button"
                                :disabled="!record.contact_id || !record.email || sendingPortal"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Email portal sign-in and registration links"
                                @click="sendPortalLink"
                            >
                                <span class="material-icons text-[18px]">mail</span>
                                {{ sendingPortal ? 'Sending…' : 'Send portal email' }}
                            </button>
                            <p v-if="!record.email" class="text-md text-amber-700 dark:text-amber-400/90">
                                Add a primary email on the contact record to send the portal message.
                            </p>
                            <p v-else-if="!record.contact_id" class="text-md text-amber-700 dark:text-amber-400/90">
                                This customer is not linked to a contact; portal email cannot be sent.
                            </p>
                        </div>
                    </div>

                    <!-- Purchase Intent -->
                    <div
                        v-if="record.purchase_timeline || record.budget_min || record.budget_max || record.interested_model || record.has_trade_in"
                        class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Purchase Intent</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <template v-if="enumLabel('purchase_timeline')">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Timeline</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ enumLabel('purchase_timeline') }}</span>
                                </div>
                            </template>
                            <template v-if="record.interested_model">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Interested In</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.interested_model }}</span>
                                </div>
                            </template>
                            <template v-if="record.budget_min || record.budget_max">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Budget Range</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">
                                        <template v-if="record.budget_min && record.budget_max">
                                            {{ formatMoney(record.budget_min) }} – {{ formatMoney(record.budget_max) }}
                                        </template>
                                        <template v-else-if="record.budget_min">From {{ formatMoney(record.budget_min) }}</template>
                                        <template v-else>Up to {{ formatMoney(record.budget_max) }}</template>
                                    </span>
                                </div>
                            </template>
                            <template v-if="record.has_trade_in">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Trade-In</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">
                                        {{ record.trade_in_value ? formatMoney(record.trade_in_value) : 'Yes' }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Loyalty & Activity -->
                    <div
                        v-if="record.loyalty_points || record.converted_from_lead_id"
                        class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Loyalty</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <template v-if="record.loyalty_points !== null && record.loyalty_points !== undefined">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Points</span>
                                    <span class="text-md font-semibold text-gray-900 dark:text-gray-100">{{ formatNumber(record.loyalty_points) }}</span>
                                </div>
                            </template>
                            <template v-if="record.converted_from_lead_id">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Converted from Lead</span>
                                    <Link
                                        :href="route('leads.show', record.converted_from_lead_id)"
                                        class="text-md text-primary-600 dark:text-primary-400 hover:underline text-right"
                                    >
                                        Lead #{{ record.converted_from_lead_id }}
                                    </Link>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Acquisition / Marketing -->
                    <div
                        v-if="record.campaign || record.medium || record.source_details || record.referrer || record.utm_source || record.marketing_opt_in"
                        class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Acquisition</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <template v-if="record.campaign">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Campaign</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.campaign }}</span>
                                </div>
                            </template>
                            <template v-if="record.medium">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Medium</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.medium }}</span>
                                </div>
                            </template>
                            <template v-if="record.source_details">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Source Details</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.source_details }}</span>
                                </div>
                            </template>
                            <template v-if="record.referrer">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Referrer</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right">{{ record.referrer }}</span>
                                </div>
                            </template>
                            <template v-if="record.utm_source">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">UTM Source</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right font-mono text-md">{{ record.utm_source }}</span>
                                </div>
                            </template>
                            <template v-if="record.utm_campaign">
                                <div class="flex justify-between items-start">
                                    <span class="text-md text-gray-500 dark:text-gray-400 font-medium">UTM Campaign</span>
                                    <span class="text-md text-gray-900 dark:text-gray-100 text-right font-mono text-md">{{ record.utm_campaign }}</span>
                                </div>
                            </template>
                            <div class="flex justify-between items-start">
                                <span class="text-md text-gray-500 dark:text-gray-400 font-medium">Marketing Opt-In</span>
                                <span
                                    :class="record.marketing_opt_in ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                    class="text-md font-semibold px-2 py-0.5 rounded-full"
                                >
                                    {{ record.marketing_opt_in ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sublists -->
            <div v-if="sublists.length > 0 && domainName" class="mt-6">
                <Sublist :parent-record="record" :parent-domain="domainName" :sublists="sublists" />
            </div>
        </template>

        <!-- Delete modal -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Delete Customer</h3>
                <p class="mt-2 text-md text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete <strong>{{ displayName }}</strong>? This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-md font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        <svg v-if="isDeleting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        :disabled="isDeleting"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-md font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 disabled:opacity-50"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
