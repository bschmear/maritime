<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import ContactAddressAutocomplete from '@/Components/ContactAddressAutocomplete.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();

const props = defineProps({
    record:         { type: Object, required: true },
    recordType:     { type: String, default: 'contacts' },
    recordTitle:    { type: String, default: 'Contact' },
    domainName:     { type: String, default: 'Contact' },
    formSchema:     { type: Object, default: null },
    fieldsSchema:   { type: Object, default: () => ({}) },
    enumOptions:    { type: Object, default: () => ({}) },
    imageUrls:      { type: Object, default: () => ({}) },
    account:        { type: Object, default: null },
    timezones:      { type: Array,  default: () => [] },
    availableSpecs: { type: Array,  default: () => [] },
});

const showDeleteModal = ref(false);
const isDeleting      = ref(false);
const postingAddress  = ref(false);
const sendingPortal   = ref(false);

const contactLabel = computed(() => {
    const r = props.record;
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || `Contact #${r.id}`
    );
});

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref  = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home',     href: route('dashboard') },
    { label: 'Contacts', href: indexHref.value },
    { label: contactLabel.value },
]);

const hasLead     = computed(() => (props.record.leads?.length ?? 0) > 0);
const hasCustomer = computed(() => !!props.record.customer?.id);
const hasVendors  = computed(() => (props.record.vendors?.length ?? 0) > 0);

/** New customer form with this contact (and address) pre-filled — see CustomerController::create. */
const createCustomerWithContactHref = computed(
    () => `${route('customers.create')}?contact_id=${props.record.id}`,
);

const primaryAddress = computed(() => {
    const list = props.record.addresses ?? [];
    return list.find((a) => a.is_primary) ?? list[0] ?? null;
});

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const onContactAddressSaved = (payload) => {
    postingAddress.value = true;
    router.post(route(`${props.recordType}.addresses.store`, props.record.id), payload, {
        preserveScroll: true,
        onFinish: () => {
            postingAddress.value = false;
        },
    });
};

/** Sublist edits don’t update this page’s `record.addresses`; reload that prop so Primary address stays in sync. */
const onSublistMutated = ({ domain }) => {
    if (domain === 'ContactAddress') {
        router.reload({ only: ['record'], preserveScroll: true });
    }
};

const fmt = {
    date: (val) => {
        if (!val) return null;
        const d = new Date(val);
        return isNaN(d.getTime()) ? val : d.toLocaleDateString('en-US', { dateStyle: 'medium' });
    },
    datetime: (val) => {
        if (!val) return '—';
        const d = new Date(val);
        return isNaN(d.getTime()) ? '—' : d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    },
    currency: (val) => {
        if (val == null) return null;
        return Number(val).toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 });
    },
    empty: (val) => val ?? '—',
};

const assignedUserName = computed(() => {
    const u = props.record.assigned_user;
    if (!u || typeof u !== 'object') return null;
    return u.display_name ?? u.name ?? u.email ?? null;
});

const contactStatusEnumKey = 'App\\Enums\\Entity\\ContactStatus';
const contactStageEnumKey = 'App\\Enums\\Entity\\ContactStage';

const recordStatusDisplay = computed(() => {
    const raw = props.record.status;
    if (raw == null || raw === '') return '—';
    const opts = props.enumOptions[contactStatusEnumKey] || [];
    const hit = opts.find(
        (o) => o.id === raw || o.value === raw || String(o.id) === String(raw) || String(o.value) === String(raw)
    );
    if (hit?.name) return hit.name;
    return typeof raw === 'string' ? raw.replace(/_/g, ' ') : String(raw);
});

const recordStageDisplay = computed(() => {
    const raw = props.record.stage_id;
    if (raw == null || raw === '') return '—';
    const opts = props.enumOptions[contactStageEnumKey] || [];
    const hit = opts.find(
        (o) => o.id === raw || o.value === raw || Number(o.id) === Number(raw) || String(o.value) === String(raw)
    );
    if (hit?.name) return hit.name;
    return typeof raw === 'string' ? raw.replace(/_/g, ' ') : String(raw);
});

const customerRows = computed(() => {
    const c = props.record.customer ?? {};
    return [
        { label: 'Account status',  value: c.account_status },
        { label: 'Customer type',   value: c.customer_type },
        { label: 'Tier',            value: c.tier },
        { label: 'Credit limit',    value: fmt.currency(c.credit_limit) },
        { label: 'Current balance', value: fmt.currency(c.current_balance) },
        { label: 'Payment terms',   value: c.payment_terms ? `${c.payment_terms} days` : null },
        { label: 'Payment method',  value: c.payment_method },
        { label: 'Tax ID',          value: c.tax_id },
        { label: 'Tax exempt',      value: c.tax_exempt != null ? (c.tax_exempt ? 'Yes' : 'No') : null },
        { label: 'Billing email',   value: c.billing_email },
        { label: 'Lifetime value',  value: fmt.currency(c.lifetime_value) },
        { label: 'Total orders',    value: c.total_orders != null ? String(c.total_orders) : null },
        { label: 'First purchase',  value: fmt.date(c.first_purchase_at) },
        { label: 'Last purchase',   value: fmt.date(c.last_purchase_at) },
        { label: 'Contract start',  value: fmt.date(c.contract_start) },
        { label: 'Contract end',    value: fmt.date(c.contract_end) },
    ];
});


const sendPortalLink = () => {
    if (! props.record.email || sendingPortal.value) {
        return;
    }
    sendingPortal.value = true;
    router.post(route('contacts.send-portal-link', props.record.id), {}, {
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
        onError:   () => { isDeleting.value = false; },
        onFinish:  () => { isDeleting.value = false; showDeleteModal.value = false; },
    });
};
</script>

<template>
    <Head :title="`${contactLabel} — Contact`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 truncate">
                        {{ contactLabel }}
                    </h2>
                    <div class="flex items-center gap-2 shrink-0">
                        <Link :href="indexHref" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Contacts
                        </Link>
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 transition-colors" @click="showDeleteModal = true">
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                     <!--    <button
                            v-if="record.email"
                            type="button"
                            :disabled="sendingPortal"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                            title="Email portal sign-in and registration links to this contact"
                            @click="sendPortalLink"
                        >
                            <span class="material-icons text-[16px]">outgoing_mail</span>
                            {{ sendingPortal ? 'Sending…' : 'Portal email' }}
                        </button> -->
                        <Link :href="editHref" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6 ">
            <div v-if="page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                {{ page.props.flash.error }}
            </div>

            <!-- Hero -->
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 dark:from-primary-700 dark:via-primary-800 dark:to-primary-950 shadow-lg">
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 w-full h-full">
                        <path d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z" fill="white"/>
                        <path d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z" fill="white" opacity="0.5"/>
                    </svg>
                </div>
                <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-[0.08] select-none pointer-events-none">
                    <span class="material-icons" style="font-size:180px">contacts</span>
                </div>
                <div class="relative px-6 py-7 sm:px-10 sm:py-8">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5">
                        <div class="space-y-2.5">
                            <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight">{{ contactLabel }}</h1>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-if="record.type" class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-md font-medium text-white capitalize">{{ record.type }}</span>
                                <span v-if="hasLead"     class="inline-flex items-center gap-1 rounded-full bg-amber-400/30 px-2.5 py-0.5 text-md font-semibold text-amber-100"><span class="material-icons text-[11px]">trending_up</span> Lead</span>
                                <span v-if="hasCustomer" class="inline-flex items-center gap-1 rounded-full bg-green-400/30 px-2.5 py-0.5 text-md font-semibold text-green-100"><span class="material-icons text-[11px]">shopping_bag</span> Customer</span>
                                <span v-if="hasVendors" class="inline-flex items-center gap-1 rounded-full bg-blue-400/30 px-2.5 py-0.5 text-md font-semibold text-blue-100"><span class="material-icons text-[11px]">storefront</span> Vendor</span>
                                <span v-if="record.inactive" class="inline-flex items-center gap-1 rounded-full bg-red-400/30 px-2.5 py-0.5 text-md font-semibold text-red-100"><span class="material-icons text-[11px]">pause_circle</span> Inactive</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a v-if="record.email"           :href="`mailto:${record.email}`"           class="flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm text-white font-medium hover:bg-white/25 transition-colors"><span class="material-icons text-[15px]">mail</span>{{ record.email }}</a>
                            <a v-if="record.secondary_email" :href="`mailto:${record.secondary_email}`" class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-sm text-white/80 hover:bg-white/20 transition-colors"><span class="material-icons text-[15px]">mail_outline</span>{{ record.secondary_email }}</a>
                            <a v-if="record.phone"           :href="`tel:${record.phone}`"              class="flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm text-white font-medium hover:bg-white/25 transition-colors"><span class="material-icons text-[15px]">phone</span>{{ record.phone }}</a>
                            <a v-if="record.mobile"          :href="`tel:${record.mobile}`"             class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-sm text-white/80 hover:bg-white/20 transition-colors"><span class="material-icons text-[15px]">smartphone</span>{{ record.mobile }}</a>
                            <div v-if="record.company" class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-sm text-white/80">
                                <span class="material-icons text-[15px]">apartment</span>
                                {{ record.company }}<span v-if="record.position" class="text-white/50 ml-1">· {{ record.position }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overview grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- Left 2/3 -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Contact information -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact information</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-50 dark:divide-gray-700/60">
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">mail</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Email</p>
                                        <a v-if="record.email" :href="`mailto:${record.email}`" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block">{{ record.email }}</a>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">mail_outline</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Secondary email</p>
                                        <a v-if="record.secondary_email" :href="`mailto:${record.secondary_email}`" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block">{{ record.secondary_email }}</a>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">phone</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Phone</p>
                                        <a v-if="record.phone" :href="`tel:${record.phone}`" class="text-sm font-medium text-gray-900 dark:text-white hover:underline">{{ record.phone }}</a>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">smartphone</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Mobile</p>
                                        <a v-if="record.mobile" :href="`tel:${record.mobile}`" class="text-sm font-medium text-gray-900 dark:text-white hover:underline">{{ record.mobile }}</a>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">contact_phone</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Preferred method</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ record.preferred_contact_method || '—' }}</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">schedule</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Preferred time</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ record.preferred_contact_time || '—' }}</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">language</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Website</p>
                                        <a v-if="record.website" :href="record.website" target="_blank" rel="noopener" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block">{{ record.website }}</a>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 flex items-center gap-3">
                                    <span class="material-icons text-[18px] text-gray-400 shrink-0">link</span>
                                    <div>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Social</p>
                                        <div v-if="record.linkedin || record.facebook" class="flex gap-3 mt-0.5">
                                            <a v-if="record.linkedin" :href="record.linkedin" target="_blank" rel="noopener" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">LinkedIn</a>
                                            <a v-if="record.facebook" :href="record.facebook" target="_blank" rel="noopener" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Facebook</a>
                                        </div>
                                        <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Company -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Company</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-50 dark:divide-gray-700/60">
                            <div class="px-5 py-3.5 flex items-center gap-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">apartment</span>
                                <div>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Company</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.company || '—' }}</p>
                                </div>
                            </div>
                            <div class="px-5 py-3.5 flex items-center gap-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">work</span>
                                <div>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Title</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.title || '—' }}</p>
                                </div>
                            </div>
                            <div class="px-5 py-3.5 flex items-center gap-3">
                                <span class="material-icons text-[18px] text-gray-400 shrink-0">work_outline</span>
                                <div>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">Position</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ record.position || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary address (all addresses in Addresses sublist below) -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Primary address</span>
                            <ContactAddressAutocomplete
                                :disabled="postingAddress"
                                @saved="onContactAddressSaved"
                            />
                        </div>
                        <div class="px-5 py-4 flex items-start gap-3">
                            <span class="material-icons text-[20px] text-gray-400 mt-0.5 shrink-0">location_on</span>
                            <div v-if="primaryAddress" class="min-w-0 flex-1 text-sm text-gray-900 dark:text-white leading-relaxed">
                                <div v-if="primaryAddress.label" class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="text-md font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-full px-2 py-0.5">{{ primaryAddress.label }}</span>
                                </div>
                                <p v-if="primaryAddress.address_line_1">{{ primaryAddress.address_line_1 }}</p>
                                <p v-if="primaryAddress.address_line_2">{{ primaryAddress.address_line_2 }}</p>
                                <p v-if="primaryAddress.city || primaryAddress.state || primaryAddress.postal_code">{{ [primaryAddress.city, primaryAddress.state, primaryAddress.postal_code].filter(Boolean).join(', ') }}</p>
                                <p v-if="primaryAddress.country">{{ primaryAddress.country }}</p>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                No primary address yet. Use <strong class="font-medium text-gray-700 dark:text-gray-300">Add address</strong> or manage addresses in the <strong class="font-medium text-gray-700 dark:text-gray-300">Addresses</strong> tab below.
                            </p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</span>
                        </div>
                        <div class="px-5 py-4 flex items-start gap-3">
                            <span class="material-icons text-[18px] text-gray-400 mt-0.5 shrink-0">notes</span>
                            <p v-if="record.notes" class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ record.notes }}</p>
                            <p v-else class="text-sm text-gray-300 dark:text-gray-600">—</p>
                        </div>
                    </div>

                    <!-- Customer profile -->
                    <div v-if="hasCustomer" class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer profile</span>
                            <Link v-if="record.customer?.id" :href="route('customers.show', record.customer.id)" class="text-md font-medium text-primary-600 dark:text-primary-400 hover:underline">Open customer →</Link>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2">
                            <div v-for="row in customerRows" :key="row.label" class="px-5 py-3.5 border-b border-gray-50 dark:border-gray-700/60 last:border-b-0 odd:sm:border-r odd:sm:border-gray-50 odd:dark:sm:border-gray-700/60">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide">{{ row.label }}</p>
                                <p :class="row.value ? 'text-sm font-medium text-gray-900 dark:text-white' : 'text-sm text-gray-300 dark:text-gray-600'">{{ row.value || '—' }}</p>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Sidebar 1/3 -->
                <div class="space-y-4">
                    <div class="sticky top-[140px] space-y-4">
                        <!-- Record info -->
                        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden ">
                            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Record info</span>
                            </div>
                            <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">

                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">flag</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Contact status</span>
                                    <span class="text-md font-medium" :class="recordStatusDisplay !== '—' ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'">{{ recordStatusDisplay }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">flag</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Stage</span>
                                    <span class="text-md font-medium" :class="recordStageDisplay !== '—' ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'">{{ recordStageDisplay }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">travel_explore</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Source</span>
                                    <span class="text-md font-medium" :class="record.source ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'">{{ record.source || '—' }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">person_pin</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Assigned to</span>
                                    <span class="text-md font-medium" :class="assignedUserName ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600'">{{ assignedUserName || '—' }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">calendar_today</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                    <span class="text-md text-gray-900 dark:text-white">{{ fmt.datetime(record.created_at) }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">update</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                    <span class="text-md text-gray-900 dark:text-white">{{ fmt.datetime(record.updated_at) }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Customer portal -->
                        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer portal</span>
                            </div>
                            <div class="px-5 py-4 space-y-3">
                                <p class="text-md text-gray-600 dark:text-gray-300 leading-relaxed">
                                    Email this contact a link to your customer portal with short instructions: how to <strong>create an account</strong> (first visit) or <strong>sign in</strong>, using their primary email.
                                </p>
                                <ul class="text-md text-gray-500 dark:text-gray-400 space-y-3 list-disc pl-4">
                                    <li>The message goes to the <strong>primary email</strong> on this contact.</li>
                                    <li v-if="hasCustomer">
                                        <strong>Create account</strong> in the portal email works for this contact because they are already linked as a <strong>customer</strong> with that email.
                                    </li>
                                    <li v-else class="marker:text-gray-400">
                                        <span class="block -ml-1 pl-1">
                                            <strong>Create account</strong> succeeds only when this contact is already linked as a <strong>customer</strong> with that email. If they are not a customer yet, set that up first or they will need to contact you.
                                        </span>
                                        <Link
                                            :href="createCustomerWithContactHref"
                                            class="mt-2 ml-1 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-800 hover:bg-primary-100 dark:border-primary-800/60 dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-900/50 no-underline"
                                        >
                                            <span class="material-icons text-[18px]">person_add</span>
                                            Create customer profile
                                        </Link>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                            Opens the new customer form with this contact and primary address pre-filled.
                                        </span>
                                    </li>
                                </ul>
                                <button
                                    type="button"
                                    :disabled="!record.email || sendingPortal"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="sendPortalLink"
                                >
                                    <span class="material-icons text-[18px]">mail</span>
                                    {{ sendingPortal ? 'Sending…' : 'Send portal email' }}
                                </button>
                                <p v-if="!record.email" class="text-md text-amber-700 dark:text-amber-400/90">
                                    Add a primary email on this contact to send the portal message.
                                </p>
                            </div>
                        </div>

                        <!-- Roles -->
                        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Roles</span>
                            </div>
                            <ul class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px]" :class="hasLead ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600'">trending_up</span>
                                    <span class="text-sm flex-1" :class="hasLead ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'">Lead</span>
                                    <Link v-if="record.leads?.[0]?.id" :href="route('leads.show', record.leads[0].id)" class="text-md text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                                    <span v-else class="text-md text-gray-300 dark:text-gray-600">—</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px]" :class="hasCustomer ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'">shopping_bag</span>
                                    <span class="text-sm flex-1" :class="hasCustomer ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'">Customer</span>
                                    <Link v-if="record.customer?.id" :href="route('customers.show', record.customer.id)" class="text-md text-primary-600 dark:text-primary-400 hover:underline">View</Link>
                                    <Link
                                        v-else
                                        :href="createCustomerWithContactHref"
                                        class="text-md font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                    >
                                        Create
                                    </Link>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px]" :class="hasVendors ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600'">storefront</span>
                                    <span class="text-sm flex-1" :class="hasVendors ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'">Vendor</span>
                                    <span class="text-md text-gray-300 dark:text-gray-600">{{ hasVendors ? record.vendors.length : '—' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Sublists -->
            <Sublist
                v-if="visibleSublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="visibleSublists"
                @sublist-mutated="onSublistMutated"
            />

        </div>

        <!-- Delete modal -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete contact</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ contactLabel }}</span>?
                    This cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button type="button" :disabled="isDeleting" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50" @click="confirmDelete">
                        {{ isDeleting ? 'Deleting…' : 'Delete contact' }}
                    </button>
                    <button type="button" :disabled="isDeleting" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600" @click="showDeleteModal = false">
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>

    </TenantLayout>
</template>
