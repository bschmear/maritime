<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import ScorePanel from '@/Components/Tenant/ScorePanel.vue';
import LeadSchemaShowSections from '@/Components/Tenant/Lead/LeadSchemaShowSections.vue';
import Modal from '@/Components/Modal.vue';
import SendSurveyModal from '@/Components/Tenant/SendSurveyModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'leads' },
    recordTitle: { type: String, default: 'Lead' },
    domainName: { type: String, default: 'Lead' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    scores: { type: Array, default: () => [] },
    scoreScorableType: { type: String, default: 'Lead' },
});

const showDeleteModal = ref(false);
const showSendSurveyModal = ref(false);
const isDeleting = ref(false);
const isConverting = ref(false);

const leadLabel = computed(() => {
    const r = props.record;
    return (
        r.display_name?.trim()
        || [r.first_name, r.last_name].filter(Boolean).join(' ').trim()
        || r.company
        || `Lead #${r.id}`
    );
});

const surveyRecipientEmail = computed(() => {
    const r = props.record;
    return (r.email || r.contact?.email || '').trim();
});

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Leads', href: indexHref.value },
    { label: leadLabel.value },
]);

const sublists = computed(() => props.formSchema?.sublists || []);

const customerCreateHref = computed(() => {
    const cid = props.record?.contact_id ?? props.record?.contact?.id;
    if (cid != null) {
        return route('customers.create', { contact_id: cid });
    }
    return route('customers.create');
});

const linkedCustomerProfile = computed(() => props.record?.converted_customer ?? null);
const hasCustomerProfile = computed(() => Boolean(linkedCustomerProfile.value?.id));
const hasContact = computed(() => Boolean(props.record?.contact?.id ?? props.record?.contact_id));

const fmt = {
    date: (val) => {
        if (!val) return null;
        const d = new Date(val);
        return Number.isNaN(d.getTime()) ? val : d.toLocaleDateString('en-US', { dateStyle: 'medium' });
    },
    datetime: (val) => {
        if (!val) return '—';
        const d = new Date(val);
        return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    },
    currency: (val) => {
        if (val == null || val === '') return null;
        const num = Number(val);
        if (Number.isNaN(num)) return null;
        return num.toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 });
    },
    empty: (val) => (val == null || val === '' ? '—' : val),
};

function enumLabel(fieldKey, val) {
    const v = val !== undefined ? val : props.record?.[fieldKey];
    if (v == null || v === '') return null;
    const def = props.fieldsSchema?.[fieldKey];
    if (!def?.enum) return String(v);
    const opts = props.enumOptions?.[def.enum] || [];
    const hit = opts.find(
        (o) => o.id === v || o.value === v || String(o.id) === String(v) || String(o.value) === String(v),
    );
    return hit?.name ?? String(v);
}

function findOption(enumClass, val) {
    if (val == null) return null;
    const options = props.enumOptions?.[enumClass] ?? [];
    return options.find((o) => o.id === val || o.value === val || o.id === Number(val)) ?? null;
}

const statusOption = computed(() => findOption('App\\Enums\\Leads\\Status', props.record?.status_id));
const priorityOption = computed(() => findOption('App\\Enums\\Entity\\Priority', props.record?.priority_id));
const sourceLabel = computed(() => enumLabel('source_id'));

const assignedUserName = computed(() => {
    const u = props.record.assigned_user;
    if (!u || typeof u !== 'object') return null;
    return u.display_name ?? u.name ?? null;
});

const budgetDisplay = computed(() => {
    const min = props.record.budget_min;
    const max = props.record.budget_max;
    if (min != null && max != null) {
        return `${fmt.currency(min)} – ${fmt.currency(max)}`;
    }
    if (min != null) return `From ${fmt.currency(min)}`;
    if (max != null) return `Up to ${fmt.currency(max)}`;
    return enumLabel('budget_range');
});

const displayLeadScore = computed(() => {
    const latest = props.record?.latest_score;
    if (latest != null && latest !== '') {
        return latest;
    }

    const legacy = props.record?.lead_score;
    if (legacy != null && legacy !== '') {
        return legacy;
    }

    return null;
});

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => router.visit(indexHref.value),
        onError: () => { isDeleting.value = false; },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};

const convertToCustomer = () => {
    if (isConverting.value || hasCustomerProfile.value) return;
    isConverting.value = true;
    router.post(route('leads.convert', props.record.id), {}, {
        preserveScroll: true,
        onFinish: () => { isConverting.value = false; },
    });
};
</script>

<template>
    <Head :title="`${leadLabel} — Lead`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 truncate">
                        {{ leadLabel }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Leads
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 transition-colors"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                            :disabled="!surveyRecipientEmail"
                            title="Send a survey invitation by email"
                            @click="showSendSurveyModal = true"
                        >
                            <span class="material-icons text-[16px]">assignment</span>
                            Send survey
                        </button>
                        <Link
                            v-if="!hasCustomerProfile"
                            :href="customerCreateHref"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">person_add</span>
                            Add customer
                        </Link>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full flex-col space-y-6">
            <div
                v-if="page.props.flash?.success"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200"
            >
                {{ page.props.flash.error }}
            </div>

            <!-- Hero -->
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-amber-600 via-amber-700 to-amber-950 dark:from-amber-700 dark:via-amber-800 dark:to-amber-950 shadow-lg">
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 w-full h-full">
                        <path d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z" fill="white" />
                        <path d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z" fill="white" opacity="0.5" />
                    </svg>
                </div>
                <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-[0.08] select-none pointer-events-none">
                    <span class="material-icons" style="font-size: 180px">trending_up</span>
                </div>
                <div class="relative px-6 py-7 sm:px-10 sm:py-8">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5">
                        <div class="space-y-2.5">
                            <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight">{{ leadLabel }}</h1>
                            <p v-if="record.company" class="text-amber-100 text-sm sm:text-base">
                                {{ record.company }}
                                <span v-if="record.title || record.position" class="text-amber-200/80">
                                    · {{ [record.title, record.position].filter(Boolean).join(', ') }}
                                </span>
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-if="record.converted"
                                    class="inline-flex items-center gap-1 rounded-full bg-green-400/30 px-2.5 py-0.5 text-sm font-semibold text-green-100"
                                >
                                    <span class="material-icons text-[11px]">check_circle</span>
                                    Converted
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-sm font-medium text-white"
                                >
                                    <span class="material-icons text-[11px]">bolt</span>
                                    Active lead
                                </span>
                                <span
                                    v-if="statusOption"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold"
                                    :class="statusOption.bgClass"
                                >
                                    {{ statusOption.name }}
                                </span>
                                <span
                                    v-if="priorityOption"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold bg-white/20 text-white"
                                >
                                    {{ priorityOption.name }} priority
                                </span>
                                <span
                                    v-if="record.is_qualified"
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-400/30 px-2.5 py-0.5 text-sm font-semibold text-blue-100"
                                >
                                    <span class="material-icons text-[11px]">verified</span>
                                    Qualified
                                </span>
                                <span
                                    v-if="hasCustomerProfile"
                                    class="inline-flex items-center gap-1 rounded-full bg-green-400/30 px-2.5 py-0.5 text-sm font-semibold text-green-100"
                                >
                                    <span class="material-icons text-[11px]">shopping_bag</span>
                                    Customer profile
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a
                                v-if="record.email"
                                :href="`mailto:${record.email}`"
                                class="flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm text-white font-medium hover:bg-white/25 transition-colors"
                            >
                                <span class="material-icons text-[15px]">mail</span>
                                {{ record.email }}
                            </a>
                            <a
                                v-if="record.phone"
                                :href="`tel:${record.phone}`"
                                class="flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm text-white font-medium hover:bg-white/25 transition-colors"
                            >
                                <span class="material-icons text-[15px]">phone</span>
                                {{ record.phone }}
                            </a>
                            <a
                                v-if="record.mobile"
                                :href="`tel:${record.mobile}`"
                                class="flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-sm text-white/80 hover:bg-white/20 transition-colors"
                            >
                                <span class="material-icons text-[15px]">smartphone</span>
                                {{ record.mobile }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats strip -->
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700">
                    <div class="px-5 py-4">
                        <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Lead score</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ displayLeadScore ?? '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Next follow-up</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ fmt.date(record.next_followup_at) ?? '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Budget</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5 truncate">
                            {{ budgetDisplay ?? '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Last contacted</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ fmt.date(record.last_contacted_at) ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Overview grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 space-y-4">
                    <div class="flex items-center justify-end">
                        <Link
                            v-if="hasContact"
                            :href="route('contacts.show', record.contact?.id ?? record.contact_id)"
                            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                        >
                            Open linked contact →
                        </Link>
                    </div>

                    <LeadSchemaShowSections
                        :record="record"
                        :form-schema="formSchema"
                        :fields-schema="fieldsSchema"
                        :enum-options="enumOptions"
                    />
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <div class="space-y-4">
                        <!-- Record info -->
                        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Record info</span>
                            </div>
                            <ul class="divide-y divide-gray-50 dark:divide-gray-700/60 text-sm">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">flag</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Status</span>
                                    <span
                                        v-if="statusOption"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="statusOption.bgClass"
                                    >{{ statusOption.name }}</span>
                                    <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">priority_high</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Priority</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ priorityOption?.name ?? '—' }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">travel_explore</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Source</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ sourceLabel ?? record.source ?? '—' }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">person_pin</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Assigned to</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ assignedUserName ?? '—' }}</span>
                                </li>
                                <li v-if="record.converted_at" class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">check_circle</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Converted</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">{{ fmt.date(record.converted_at) }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">calendar_today</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Created</span>
                                    <span class="text-gray-900 dark:text-white">{{ fmt.datetime(record.created_at) }}</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px] text-gray-400">update</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex-1">Updated</span>
                                    <span class="text-gray-900 dark:text-white">{{ fmt.datetime(record.updated_at) }}</span>
                                </li>
                            </ul>
                        </div>

                       
                        <ScorePanel
                            :scorable-type="scoreScorableType"
                            :scorable-id="record.id"
                            :subscription-level="3"
                            :initial-scores="scores"
                        />
                    </div>
                     <!-- Linked records -->
                     <div class=" sticky top-[140px] rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Linked records</span>
                            </div>
                            <ul class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px]" :class="hasContact ? 'text-primary-500' : 'text-gray-300 dark:text-gray-600'">person</span>
                                    <span class="text-sm flex-1" :class="hasContact ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'">Contact</span>
                                    <Link
                                        v-if="hasContact"
                                        :href="route('contacts.show', record.contact?.id ?? record.contact_id)"
                                        class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                    >View</Link>
                                    <span v-else class="text-sm text-gray-300 dark:text-gray-600">—</span>
                                </li>
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="material-icons text-[16px]" :class="hasCustomerProfile ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'">shopping_bag</span>
                                    <span class="text-sm flex-1" :class="hasCustomerProfile ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'">Customer</span>
                                    <Link
                                        v-if="hasCustomerProfile"
                                        :href="route('customers.show', linkedCustomerProfile.id)"
                                        class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                    >View</Link>
                                    <Link
                                        v-else
                                        :href="customerCreateHref"
                                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                    >Create</Link>
                                </li>
                            </ul>
                            <div v-if="!hasCustomerProfile" class="px-5 pb-4 space-y-2">
                                <button
                                    type="button"
                                    :disabled="isConverting || !hasContact"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="convertToCustomer"
                                >
                                    <span class="material-icons text-[18px]">swap_horiz</span>
                                    {{ isConverting ? 'Converting…' : 'Convert to customer' }}
                                </button>
                                <p v-if="!hasContact" class="text-xs text-amber-700 dark:text-amber-400/90">
                                    Link a contact before converting.
                                </p>
                            </div>
                            <div v-else class="px-5 pb-4">
                                <Link
                                    :href="route('customers.show', linkedCustomerProfile.id)"
                                    class="flex items-center gap-2 px-3 py-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-sm font-medium text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                                >
                                    <span class="material-icons text-lg">open_in_new</span>
                                    Open customer profile
                                </Link>
                            </div>
                        </div>

                </div>
            </div>

            <Sublist
                v-if="sublists.length > 0 && domainName"
                :parent-record="record"
                :parent-domain="domainName"
                :sublists="sublists"
            />
        </div>

        <SendSurveyModal
            :show="showSendSurveyModal"
            record-type="lead"
            :record-id="record.id"
            :recipient-email="surveyRecipientEmail"
            :recipient-name="leadLabel"
            @close="showSendSurveyModal = false"
        />

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete lead</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ leadLabel }}</span>?
                    This cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete lead' }}
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
