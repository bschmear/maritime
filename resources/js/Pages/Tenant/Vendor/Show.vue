<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const isUpdatingPrimaryContact = ref(false);
const isSendingVendorPortalLink = ref(false);
/** Scroll target for the linked-contacts sublist (primary contact empty state). */
const vendorContactsSection = ref(null);

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

/** Contacts linked to this vendor (ManyToMany), sorted for picker labels. */
const linkedVendorContacts = computed(() => {
    const raw = props.record.linkedContacts ?? props.record.linked_contacts;
    if (!Array.isArray(raw)) {
        return [];
    }
    return [...raw].sort((a, b) =>
        contactPersonLabel(a).localeCompare(contactPersonLabel(b), undefined, { sensitivity: 'base' }),
    );
});

const canSendVendorPortalLink = computed(() => {
    const c = primaryContact.value;
    if (!c?.id) {
        return false;
    }
    const email = String(c.email ?? '').trim();
    if (!email) {
        return false;
    }
    return linkedVendorContacts.value.some((x) => Number(x.id) === Number(c.id));
});

const sendVendorPortalLink = () => {
    isSendingVendorPortalLink.value = true;
    router.post(
        route('vendors.send-vendor-portal-link', props.record.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSendingVendorPortalLink.value = false;
            },
        },
    );
};

const patchPrimaryContact = (contactId) => {
    const id = Number(contactId);
    if (!id || Number(props.record.primary_contact_id) === id) {
        return;
    }
    isUpdatingPrimaryContact.value = true;
    router.patch(
        route('vendors.primary-contact', props.record.id),
        { contact_id: id },
        {
            preserveScroll: true,
            onFinish: () => {
                isUpdatingPrimaryContact.value = false;
            },
        },
    );
};

const onPrimaryContactSelectChange = (event) => {
    const raw = event.target?.value;
    if (raw === '' || raw == null) {
        return;
    }
    patchPrimaryContact(raw);
};

const scrollToVendorContacts = () => {
    nextTick(() => {
        vendorContactsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

/** When no contacts: jump to vendor contacts sublist, or open new contact if sublist is unavailable. */
const goAddVendorContact = () => {
    if (visibleSublists.value.length > 0 && props.domainName) {
        scrollToVendorContacts();
        return;
    }
    router.visit(route('contacts.create'));
};

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
    if (domain === 'Contact' || domain === 'WarrantyClaim') {
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

/** Integer 0–5 for read-only stars; null when unset or invalid (show em dash). */
const ratingDisplayValue = computed(() => {
    const v = props.record?.rating;
    if (v == null || v === '') {
        return null;
    }
    const n = Number(v);
    if (!Number.isFinite(n)) {
        return null;
    }
    return Math.min(5, Math.max(0, Math.round(n)));
});

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

const hasQuickBooksData = computed(() => {
    const r = props.record;
    return !!(
        r.quickbooks_id
        || r.qbo_acct_num
        || r.print_on_check_name
        || r.term_ref_name
        || r.open_balance != null
        || r.overdue_balance != null
        || r.vendor_1099
    );
});

const hasBankingInfo = computed(() => {
    const r = props.record;
    return !!(
        r.ach_bank_name
        || r.ach_account_number_masked
        || r.ach_routing_number_masked
        || r.tax_identifier_masked
    );
});

const bankingEmptyMessage = computed(() => {
    if (hasBankingInfo.value) {
        return '';
    }
    if (props.record.quickbooks_id) {
        return 'QuickBooks did not return ACH or tax ID for this vendor. Intuit often withholds full account numbers from the API even when bank details exist in the QBO UI. Add bank details manually under Edit.';
    }

    return 'No bank or tax information on file. Add under Edit, or enter in QuickBooks and re-import.';
});

const hasQboContactFields = computed(() => {
    const r = props.record;
    return !!(
        r.contact_first_name
        || r.contact_last_name
        || r.contact_title
        || r.contact_email
        || r.contact_phone
        || r.mobile_phone
        || r.fax
    );
});

const qboContactName = computed(() => {
    const r = props.record;
    return [r.contact_first_name, r.contact_last_name].filter(Boolean).join(' ').trim() || null;
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
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-md font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Vendors
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-md font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-4 py-2 text-md font-medium text-white transition-colors hover:bg-teal-700"
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
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-200/90">Vendor</p>
                            <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
                                {{ vendorLabel }}
                            </h1>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-if="record.vendor_code"
                                    class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 font-mono text-sm font-medium text-teal-100"
                                >
                                    {{ record.vendor_code }}
                                </span>
                                <span
                                    v-if="record.is_verified"
                                    class="inline-flex items-center gap-1 rounded-full bg-teal-400/25 px-2.5 py-0.5 text-sm font-semibold text-teal-50"
                                >
                                    <span class="material-icons text-[11px]">verified</span>
                                    Verified
                                </span>
                                <span
                                    v-for="tag in tagsList"
                                    :key="String(tag)"
                                    class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-0.5 text-sm font-medium text-slate-100"
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
                                        class="mt-0.5 inline-flex items-center gap-1.5 text-md font-semibold text-white hover:text-teal-100"
                                    >
                                        <span class="material-icons text-[18px]">person</span>
                                        {{ contactPersonLabel(primaryContact) }}
                                    </Link>
                                    <div class="mt-1 flex flex-wrap justify-end gap-2 text-sm text-teal-100/90">
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
                                    class="mt-1 text-md leading-relaxed text-teal-100/85"
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
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Company &amp; operations
                            </span>
                        </div>
                        <div
                            class="grid grid-cols-1 divide-y divide-gray-50 dark:divide-gray-700/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                        >
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">business</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Legal company name
                                        </p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
                                            {{ fmt.empty(record.company_name) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">category</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Vendor type
                                        </p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
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
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
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
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
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
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
                                            {{ fmt.empty(record.status_reason) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">star</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Rating
                                        </p>
                                        <p
                                            v-if="ratingDisplayValue === null"
                                            class="text-md font-medium text-gray-900 dark:text-white"
                                        >
                                            —
                                        </p>
                                        <div
                                            v-else
                                            class="flex items-center gap-0.5 pt-0.5"
                                            role="img"
                                            :aria-label="`Rating ${ratingDisplayValue} out of 5`"
                                        >
                                            <template v-for="star in 5" :key="star">
                                                <svg
                                                    class="h-[18px] w-[18px] shrink-0"
                                                    :class="
                                                        star <= ratingDisplayValue
                                                            ? 'text-yellow-400 dark:text-yellow-400'
                                                            : 'text-gray-300 dark:text-gray-600'
                                                    "
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                                    />
                                                </svg>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">payments</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            Payment terms
                                        </p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
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
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
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
                                        <p class="text-md font-medium capitalize text-gray-900 dark:text-white">
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
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Location
                            </span>
                        </div>
                        <div class="flex items-start gap-3 px-5 py-4">
                            <span class="material-icons mt-0.5 shrink-0 text-[20px] text-gray-400">location_on</span>
                            <div
                                v-if="hasAddress"
                                class="min-w-0 flex-1 text-md leading-relaxed text-gray-900 dark:text-white"
                            >
                                <p v-if="record.address_line_1">{{ record.address_line_1 }}</p>
                                <p v-if="record.address_line_2">{{ record.address_line_2 }}</p>
                                <p v-if="record.city || record.state || record.postal_code">
                                    {{ [record.city, record.state, record.postal_code].filter(Boolean).join(', ') }}
                                </p>
                                <p v-if="record.country">{{ record.country }}</p>
                            </div>
                            <p v-else class="text-md text-gray-500 dark:text-gray-400">
                                No address on file. Add one when editing the vendor.
                            </p>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
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
                                    class="mt-0.5 block truncate text-md font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    {{ record.website }}
                                </a>
                                <p v-else class="mt-0.5 text-md text-gray-300 dark:text-gray-600">—</p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">LinkedIn</p>
                                <a
                                    v-if="record.linkedin"
                                    :href="record.linkedin"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-0.5 block truncate text-md font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    Profile
                                </a>
                                <p v-else class="mt-0.5 text-md text-gray-300 dark:text-gray-600">—</p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Facebook</p>
                                <a
                                    v-if="record.facebook"
                                    :href="record.facebook"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-0.5 block truncate text-md font-medium text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    Page
                                </a>
                                <p v-else class="mt-0.5 text-md text-gray-300 dark:text-gray-600">—</p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Contract
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 sm:divide-x sm:divide-gray-50 dark:sm:divide-gray-700/60">
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Start</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white">
                                    {{ fmt.date(record.contract_start) ?? '—' }}
                                </p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">End</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white">
                                    {{ fmt.date(record.contract_end) ?? '—' }}
                                </p>
                            </div>
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</p>
                                <p class="text-md font-medium text-gray-900 dark:text-white">
                                    {{ contractStatusDisplay }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="hasQboContactFields"
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                QuickBooks contact
                            </span>
                        </div>
                        <div
                            class="grid grid-cols-1 divide-y divide-gray-50 dark:divide-gray-700/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                        >
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div v-if="qboContactName" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">person</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Name</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ qboContactName }}</p>
                                    </div>
                                </div>
                                <div v-if="record.contact_title" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">badge</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Title</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.contact_title }}</p>
                                    </div>
                                </div>
                                <div v-if="record.contact_email" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">email</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Email</p>
                                        <a
                                            :href="`mailto:${record.contact_email}`"
                                            class="text-md font-medium text-teal-600 hover:underline dark:text-teal-400"
                                        >{{ record.contact_email }}</a>
                                    </div>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div v-if="record.contact_phone" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">phone</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Phone</p>
                                        <a
                                            :href="`tel:${record.contact_phone}`"
                                            class="text-md font-medium text-gray-900 dark:text-white hover:underline"
                                        >{{ record.contact_phone }}</a>
                                    </div>
                                </div>
                                <div v-if="record.mobile_phone" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">smartphone</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Mobile</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.mobile_phone }}</p>
                                    </div>
                                </div>
                                <div v-if="record.fax" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">print</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Fax</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.fax }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="hasQuickBooksData"
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    QuickBooks &amp; payments
                                </span>
                                <span
                                    v-if="record.quickbooks_id"
                                    class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                >
                                    <span class="material-icons text-[12px]">sync</span>
                                    Linked
                                </span>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-1 divide-y divide-gray-50 dark:divide-gray-700/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                        >
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div v-if="record.quickbooks_id" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">tag</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">QuickBooks ID</p>
                                        <p class="font-mono text-md font-medium text-gray-900 dark:text-white">{{ record.quickbooks_id }}</p>
                                    </div>
                                </div>
                                <div v-if="record.qbo_acct_num" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">numbers</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Account #</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.qbo_acct_num }}</p>
                                    </div>
                                </div>
                                <div v-if="record.print_on_check_name" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">receipt</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Print on check</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.print_on_check_name }}</p>
                                    </div>
                                </div>
                                <div v-if="record.term_ref_name" class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">schedule</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">QBO payment terms</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">{{ record.term_ref_name }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">account_balance_wallet</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Open balance (A/P)</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
                                            {{ fmt.currency(record.open_balance) ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">warning</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Overdue balance</p>
                                        <p
                                            class="text-md font-medium"
                                            :class="Number(record.overdue_balance) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
                                        >
                                            {{ fmt.currency(record.overdue_balance) ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-3.5">
                                    <span class="material-icons shrink-0 text-[18px] text-gray-400">description</span>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Track for 1099</p>
                                        <p class="text-md font-medium text-gray-900 dark:text-white">
                                            {{ record.vendor_1099 ? 'Yes' : 'No' }}
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
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Notes
                            </span>
                        </div>
                        <div class="flex items-start gap-3 px-5 py-4">
                            <span class="material-icons mt-0.5 shrink-0 text-[18px] text-gray-400">notes</span>
                            <p
                                v-if="record.notes"
                                class="whitespace-pre-wrap text-md leading-relaxed text-gray-800 dark:text-gray-200"
                            >
                                {{ record.notes }}
                            </p>
                            <p v-else class="text-md text-gray-300 dark:text-gray-600">—</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 ">
                    <div class="sticky top-[140px] space-y-4 ">
                        <div class=" space-y-4 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Record
                                </span>
                            </div>
                            <ul class="divide-y divide-gray-50 text-md dark:divide-gray-700/60">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">person_pin</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Assigned to</span>
                                    <span
                                        class="max-w-[55%] truncate text-right text-sm font-medium"
                                        :class="assignedUserName ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'"
                                    >
                                        {{ assignedUserName || '—' }}
                                    </span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">event</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Created</span>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ fmt.datetime(record.created_at) }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">update</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Updated</span>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ fmt.datetime(record.updated_at) }}</span>
                                </li>
                                <li v-if="record.verified_at" class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">verified</span>
                                    <span class="flex-1 text-gray-500 dark:text-gray-400">Verified at</span>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ fmt.datetime(record.verified_at) }}</span>
                                </li>
                            </ul>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Banking &amp; tax
                                </span>
                            </div>
                            <div class="px-5 py-4">
                                <template v-if="hasBankingInfo">
                                    <ul class="space-y-3 text-md">
                                        <li v-if="record.ach_bank_name">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Bank name</p>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ record.ach_bank_name }}</p>
                                        </li>
                                        <li v-if="record.ach_account_number_masked">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Account number</p>
                                            <p class="font-mono font-medium text-gray-900 dark:text-white">{{ record.ach_account_number_masked }}</p>
                                        </li>
                                        <li v-if="record.ach_routing_number_masked">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Routing number</p>
                                            <p class="font-mono font-medium text-gray-900 dark:text-white">{{ record.ach_routing_number_masked }}</p>
                                        </li>
                                        <li v-if="record.tax_identifier_masked">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Tax ID / EIN</p>
                                            <p class="font-mono font-medium text-gray-900 dark:text-white">{{ record.tax_identifier_masked }}</p>
                                        </li>
                                    </ul>
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        Sensitive values are stored encrypted and shown masked.
                                    </p>
                                </template>
                                <template v-else>
                                    <div class="flex items-start gap-3">
                                        <span class="material-icons mt-0.5 shrink-0 text-[18px] text-gray-400">account_balance</span>
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-300">No bank or tax details on file.</p>
                                            <p class="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                                {{ bankingEmptyMessage }}
                                            </p>
                                            <Link
                                                :href="editHref"
                                                class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                                            >
                                                <span class="material-icons text-[16px]">edit</span>
                                                Add on Edit
                                            </Link>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Primary contact
                                </span>
                            </div>
                            <div class="px-5 py-4">
                                <div v-if="linkedVendorContacts.length === 0" class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                                    <p>No contacts linked yet. Link a contact to this vendor, then choose who is primary.</p>
                                    <button
                                        type="button"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                        @click="goAddVendorContact"
                                    >
                                        <span class="material-icons text-[18px]">person_add</span>
                                        Add contact
                                    </button>
                                </div>
                                <div v-else-if="linkedVendorContacts.length === 1" class="space-y-3">
                                    <div>
                                        <Link
                                            :href="route('contacts.show', linkedVendorContacts[0].id)"
                                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                                        >
                                            <span class="material-icons text-[18px]">person</span>
                                            {{ contactPersonLabel(linkedVendorContacts[0]) }}
                                        </Link>
                                        <p
                                            v-if="linkedVendorContacts[0].email"
                                            class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ linkedVendorContacts[0].email }}
                                        </p>
                                    </div>
                                    <p
                                        v-if="Number(record.primary_contact_id) === Number(linkedVendorContacts[0].id)"
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        This is the primary contact for this vendor.
                                    </p>
                                    <button
                                        v-else
                                        type="button"
                                        :disabled="isUpdatingPrimaryContact"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-100 disabled:opacity-50 dark:border-teal-800 dark:bg-teal-900/30 dark:text-teal-100 dark:hover:bg-teal-900/50"
                                        @click="patchPrimaryContact(linkedVendorContacts[0].id)"
                                    >
                                        <span class="material-icons text-[18px]">star</span>
                                        {{ isUpdatingPrimaryContact ? 'Saving…' : 'Set as primary contact' }}
                                    </button>
                                </div>
                                <div v-else class="space-y-2">
                                    <label class="block text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Contact
                                    </label>
                                    <select
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                        :value="record.primary_contact_id != null ? String(record.primary_contact_id) : ''"
                                        :disabled="isUpdatingPrimaryContact"
                                        @change="onPrimaryContactSelectChange($event)"
                                    >
                                        <option value="" disabled>Select primary…</option>
                                        <option
                                            v-for="c in linkedVendorContacts"
                                            :key="c.id"
                                            :value="String(c.id)"
                                        >
                                            {{ contactPersonLabel(c) }}
                                        </option>
                                    </select>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Only linked contacts can be primary. Add or remove contacts in the list below.
                                    </p>
                                </div>
                                <div
                                    v-if="canSendVendorPortalLink"
                                    class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700"
                                >
                                    <button
                                        type="button"
                                        :disabled="isSendingVendorPortalLink"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700/80"
                                        @click="sendVendorPortalLink"
                                    >
                                        <span class="material-icons text-[18px]">outgoing_mail</span>
                                        {{
                                            isSendingVendorPortalLink
                                                ? 'Sending…'
                                                : 'Email manufacturer portal link'
                                        }}
                                    </button>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Sends sign-in and registration links to
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ primaryContact.email }}</span>
                                        (primary contact).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                id="vendor-linked-contacts"
                ref="vendorContactsSection"
                class="scroll-mt-28"
            >
                <Sublist
                    v-if="visibleSublists.length > 0 && domainName"
                    :parent-record="record"
                    :parent-domain="domainName"
                    :sublists="visibleSublists"
                    @sublist-mutated="onSublistMutated"
                />
            </div>
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
                <h3 class="text-xl font-medium text-gray-900 dark:text-white">Delete vendor</h3>
                <p class="mt-2 text-md text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ vendorLabel }}</span>
                    ? This cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-md font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete vendor' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
