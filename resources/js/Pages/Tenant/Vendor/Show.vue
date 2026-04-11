<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'vendors' },
    recordTitle: { type: String, default: 'Vendor' },
    domainName: { type: String, default: 'Vendor' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const vendorLabel = computed(() => {
    const r = props.record;
    return r.display_name?.trim() || `Vendor #${r.id}`;
});

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Vendors', href: indexHref.value },
    { label: vendorLabel.value },
]);

const primaryContact = computed(() => props.record.primaryContact ?? props.record.primary_contact ?? null);

const contactPersonLabel = (c) => {
    if (!c) {
        return '';
    }
    return (
        c.display_name?.trim()
        || [c.first_name, c.last_name].filter(Boolean).join(' ').trim()
        || c.company
        || `Contact #${c.id}`
    );
};

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const onSublistMutated = ({ domain }) => {
    if (domain === 'Contact') {
        router.reload({ only: ['record'], preserveScroll: true });
    }
};

const fmt = {
    date: (val) => {
        if (!val) {
            return null;
        }
        const d = new Date(val);
        return Number.isNaN(d.getTime()) ? val : d.toLocaleDateString('en-US', { dateStyle: 'medium' });
    },
    datetime: (val) => {
        if (!val) {
            return '—';
        }
        const d = new Date(val);
        return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    },
    currency: (val) => {
        if (val == null) {
            return null;
        }
        return Number(val).toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 });
    },
    empty: (val) => val ?? '—',
};

const vendorTypeEnumKey = 'App\\Enums\\Entity\\VendorType';
const vendorStatusEnumKey = 'App\\Enums\\Entity\\VendorStatus';
const preferredMethodEnumKey = 'App\\Enums\\Entity\\PreferredContactMethod';
const paymentTermsEnumKey = 'App\\Enums\\Entity\\PaymentTerms';
const contractStatusEnumKey = 'App\\Enums\\Entity\\ContractStatus';

function enumLabel(enumKey, raw) {
    if (raw == null || raw === '') {
        return '—';
    }
    const opts = props.enumOptions[enumKey] || [];
    const hit = opts.find(
        (o) =>
            o.id === raw ||
            o.value === raw ||
            String(o.id) === String(raw) ||
            String(o.value) === String(raw) ||
            Number(o.id) === Number(raw),
    );
    if (hit?.name) {
        return hit.name;
    }
    return typeof raw === 'string' ? raw.replace(/_/g, ' ') : String(raw);
}

const vendorTypeDisplay = computed(() => enumLabel(vendorTypeEnumKey, props.record.vendor_type));
const statusDisplay = computed(() => enumLabel(vendorStatusEnumKey, props.record.status_id));
const preferredMethodDisplay = computed(() => enumLabel(preferredMethodEnumKey, props.record.preferred_contact_method));
const paymentTermsDisplay = computed(() => enumLabel(paymentTermsEnumKey, props.record.payment_terms));
const contractStatusDisplay = computed(() => enumLabel(contractStatusEnumKey, props.record.contract_status));

const assignedUserName = computed(() => {
    const u = props.record.assigned_user;
    if (!u || typeof u !== 'object') {
        return null;
    }
    return u.display_name ?? u.name ?? u.email ?? null;
});

const tagsList = computed(() => {
    const t = props.record.tags;
    if (Array.isArray(t)) {
        return t.filter((x) => x != null && String(x).trim() !== '');
    }
    return [];
});

const hasAddress = computed(() => {
    const r = props.record;
    return !!(r.address_line_1 || r.address_line_2 || r.city || r.state || r.postal_code || r.country);
});

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => router.visit(route(`${props.recordType}.index`)),
        onError: () => {
            isDeleting.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`${vendorLabel} — Vendor`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ vendorLabel }}
                    </h2>
                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Vendors
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-teal-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6 p-4">
            <!-- Hero: distinct from Contact (teal / slate supply-chain) -->
            <div
                class="relative overflow-hidden rounded-xl bg-gradient-to-br from-slate-800 via-teal-900 to-slate-950 shadow-lg dark:from-slate-900 dark:via-teal-950 dark:to-black"
            >
                <div class="pointer-events-none absolute inset-0 opacity-[0.12]">
                    <svg class="absolute inset-0 h-full w-full" preserveAspectRatio="none">
                        <defs>
                            <pattern id="vendor-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                                <path d="M 32 0 L 0 0 0 32" fill="none" stroke="white" stroke-width="0.5" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#vendor-grid)" />
                    </svg>
                </div>
                <div
                    class="pointer-events-none absolute -right-4 top-1/2 h-48 w-48 -translate-y-1/2 rounded-full bg-teal-400/20 blur-3xl"
                />
                <div class="relative px-6 py-7 sm:px-10 sm:py-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2.5">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-200/90">Vendor</p>
                            <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
                                {{ vendorLabel }}
                            </h1>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-if="record.vendor_code"
                                    class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 font-mono text-xs font-medium text-teal-100"
                                >
                                    {{ record.vendor_code }}
                                </span>
                                <span
                                    v-if="record.is_verified"
                                    class="inline-flex items-center gap-1 rounded-full bg-teal-400/25 px-2.5 py-0.5 text-xs font-semibold text-teal-50"
                                >
                                    <span class="material-icons text-[11px]">verified</span>
                                    Verified
                                </span>
                                <span
                                    v-for="tag in tagsList"
                                    :key="String(tag)"
                                    class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-medium text-slate-100"
                                >
                                    {{ tag }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:items-end">
                            <div class="w-full max-w-sm text-right sm:max-w-md">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-teal-200/80">
                                    Primary contact
                                </p>
                                <template v-if="primaryContact">
                                    <Link
                                        :href="route('contacts.show', primaryContact.id)"
                                        class="mt-0.5 inline-flex items-center gap-1.5 text-sm font-semibold text-white hover:text-teal-100"
                                    >
                                        <span class="material-icons text-[18px]">person</span>
                                        {{ contactPersonLabel(primaryContact) }}
                                    </Link>
                                    <div class="mt-1 flex flex-wrap justify-end gap-2 text-xs text-teal-100/90">
                                        <a
                                            v-if="primaryContact.email"
                                            :href="`mailto:${primaryContact.email}`"
                                            class="hover:underline"
                                        >
                                            {{ primaryContact.email }}
                                        </a>
                                        <a
                                            v-if="primaryContact.phone"
                                            :href="`tel:${primaryContact.phone}`"
                                            class="hover:underline"
                                        >
                                            {{ primaryContact.phone }}
                                        </a>
                                    </div>
                                </template>
                                <p
                                    v-else
                                    class="mt-1 text-sm leading-relaxed text-teal-100/85"
                                >
                                    None selected. Add contacts below, then set a primary on
                                    <Link
                                        :href="editHref"
                                        class="font-semibold text-white underline decoration-teal-300/60 underline-offset-2 hover:text-teal-50"
                                    >
                                        Edit
                                    </Link>
                                    .
                                </p>
                            </div>
                 
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Company &amp; operations
                            </span>
                        </div>
                        <div
                            class="grid grid-cols-1 divide-y divide-gray-50 dark:divide-gray-700/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                        >
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">category</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Vendor type
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ vendorTypeDisplay }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">factory</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Industry
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ fmt.empty(record.industry) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">flag</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Status
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ statusDisplay }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">comment</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Status reason
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ fmt.empty(record.status_reason) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">star_half</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Rating
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ record.rating != null ? `${record.rating} / 5` : '—' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">payments</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Payment terms
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ paymentTermsDisplay }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">account_balance</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Credit limit
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ fmt.currency(record.credit_limit) ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">contact_mail</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Preferred method
                                        </p>
                                        <p class="text-sm font-medium capitalize text-gray-900 dark:text-white">
                                            {{ preferredMethodDisplay }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Location
                            </span>
                        </div>
                        <div class="flex items-start gap-3 px-5 py-4">
                            <span class="material-icons mt-0.5 shrink-0 text-[20px] text-gray-400">location_on</span>
                            <div
                                v-if="hasAddress"
                                class="min-w-0 flex-1 text-sm leading-relaxed text-gray-900 dark:text-white"
                            >
                                <p v-if="record.address_line_1">{{ record.address_line_1 }}</p>
                                <p v-if="record.address_line_2">{{ record.address_line_2 }}</p>
                                <p v-if="record.city || record.state || record.postal_code">
                                    {{ [record.city, record.state, record.postal_code].filter(Boolean).join(', ') }}
                                </p>
                                <p v-if="record.country">{{ record.country }}</p>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                No address on file. Add one when editing the vendor.
                            </p>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Web &amp; social
                            </span>
                        </div>
                        <div class="grid grid-cols-1 divide-y divide-gray-50 dark:divide-gray-700/60 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Website</p>
                                <a
                                    v-if="record.website"
                                    :href="record.website"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-0.5 block truncate text-sm font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    {{ record.website }}
                                </a>
                                <p v-else class="mt-0.5 text-sm text-gray-300 dark:text-gray-600">—</p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">LinkedIn</p>
                                <a
                                    v-if="record.linkedin"
                                    :href="record.linkedin"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-0.5 block truncate text-sm font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    Profile
                                </a>
                                <p v-else class="mt-0.5 text-sm text-gray-300 dark:text-gray-600">—</p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Facebook</p>
                                <a
                                    v-if="record.facebook"
                                    :href="record.facebook"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-0.5 block truncate text-sm font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    Page
                                </a>
                                <p v-else class="mt-0.5 text-sm text-gray-300 dark:text-gray-600">—</p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Contract
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 sm:divide-x sm:divide-gray-50 dark:sm:divide-gray-700/60">
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Start</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ fmt.date(record.contract_start) ?? '—' }}
                                </p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">End</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ fmt.date(record.contract_end) ?? '—' }}
                                </p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ contractStatusDisplay }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Notes
                            </span>
                        </div>
                        <div class="flex items-start gap-3 px-5 py-4">
                            <span class="material-icons mt-0.5 shrink-0 text-[18px] text-gray-400">notes</span>
                            <p
                                v-if="record.notes"
                                class="whitespace-pre-wrap text-sm leading-relaxed text-gray-800 dark:text-gray-200"
                            >
                                {{ record.notes }}
                            </p>
                            <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        class="sticky top-[140px] space-y-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Record
                            </span>
                        </div>
                        <ul class="divide-y divide-gray-50 text-sm dark:divide-gray-700/60">
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">person_pin</span>
                                <span class="flex-1 text-gray-500 dark:text-gray-400">Assigned to</span>
                                <span
                                    class="max-w-[55%] truncate text-right text-xs font-medium"
                                    :class="assignedUserName ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'"
                                >
                                    {{ assignedUserName || '—' }}
                                </span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">event</span>
                                <span class="flex-1 text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ fmt.datetime(record.created_at) }}</span>
                            </li>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">update</span>
                                <span class="flex-1 text-gray-500 dark:text-gray-400">Updated</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ fmt.datetime(record.updated_at) }}</span>
                            </li>
                            <li v-if="record.verified_at" class="flex items-center gap-3 px-5 py-3">
                                <span class="material-icons text-[16px] text-gray-400">verified</span>
                                <span class="flex-1 text-gray-500 dark:text-gray-400">Verified at</span>
                                <span class="text-xs text-gray-900 dark:text-white">{{ fmt.datetime(record.verified_at) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <Sublist
                v-if="visibleSublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="visibleSublists"
                @sublist-mutated="onSublistMutated"
            />
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                >
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete vendor</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ vendorLabel }}</span>
                    ? This cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete vendor' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
