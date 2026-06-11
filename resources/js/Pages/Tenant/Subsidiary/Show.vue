<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Sublist from '@/Components/Tenant/Sublist.vue';
import Modal from '@/Components/Modal.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'subsidiaries' },
    recordTitle: { type: String, default: 'Subsidiary' },
    domainName: { type: String, default: 'Subsidiary' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
});

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const subsidiaryLabel = computed(
    () => props.record.display_name?.trim() || `Subsidiary #${props.record.id}`,
);

const logoUrl = computed(() => props.imageUrls?.logo ?? props.record.logo_url ?? null);

const indexHref = computed(() => route(`${props.recordType}.index`));
const editHref = computed(() => route(`${props.recordType}.edit`, props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Subsidiaries', href: indexHref.value },
    { label: subsidiaryLabel.value },
]);

const isActive = computed(() => !props.record.inactive);

const statusBadgeClass = computed(() =>
    isActive.value
        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
);

const timezoneLabel = computed(() => {
    const tz = props.record.timezone;
    if (!tz) {
        return null;
    }
    return props.record.timezone_label ?? tz;
});

const addressLines = computed(() => {
    const r = props.record;
    const lines = [];
    if (r.address_line_1) {
        lines.push(r.address_line_1);
    }
    if (r.address_line_2) {
        lines.push(r.address_line_2);
    }
    const cityLine = [r.city, r.state, r.postal_code].filter(Boolean).join(', ');
    if (cityLine) {
        lines.push(cityLine);
    }
    if (r.country) {
        lines.push(r.country);
    }
    return lines;
});

const hasAddress = computed(() => addressLines.value.length > 0 || props.record.full_address);

const mapsUrl = computed(() => {
    if (props.record.latitude && props.record.longitude) {
        return `https://www.google.com/maps?q=${props.record.latitude},${props.record.longitude}`;
    }
    const full = props.record.full_address || addressLines.value.join(', ');
    if (full) {
        return `https://www.google.com/maps?q=${encodeURIComponent(full)}`;
    }
    return null;
});

const websiteHref = computed(() => {
    const site = props.record.website?.trim();
    if (!site) {
        return null;
    }
    return site.startsWith('http') ? site : `https://${site}`;
});

const formattedPhone = computed(() => {
    const p = props.record.phone;
    if (!p) {
        return null;
    }
    return formatPhoneNumber(p) || p;
});

const visibleSublists = computed(() => props.formSchema?.sublists ?? []);

const fmtDate = (val) => {
    if (!val) {
        return '—';
    }
    const d = new Date(val);
    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route(`${props.recordType}.destroy`, props.record.id), {
        onSuccess: () => router.visit(route(`${props.recordType}.index`)),
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`${subsidiaryLabel} — Subsidiary`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ subsidiaryLabel }}
                        </h2>
                        <span
                            :class="[
                                'hidden sm:inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                statusBadgeClass,
                            ]"
                        >
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    isActive ? 'bg-green-500' : 'bg-gray-400',
                                ]"
                            />
                            {{ isActive ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="indexHref"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <span class="material-icons text-[16px]">arrow_back</span>
                            Subsidiaries
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400"
                            @click="showDeleteModal = true"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                        <Link
                            :href="editHref"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full space-y-4 p-4">
            <div
                class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 shadow-lg dark:from-primary-700 dark:via-primary-800 dark:to-primary-950"
            >
                <div class="absolute inset-0 opacity-10">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="absolute bottom-0 h-full w-full">
                        <path
                            d="M0,200 C200,100 400,250 600,180 C800,110 1000,220 1200,160 L1200,300 L0,300 Z"
                            fill="white"
                        />
                        <path
                            d="M0,240 C300,170 500,270 700,210 C900,150 1100,240 1200,200 L1200,300 L0,300 Z"
                            fill="white"
                            opacity="0.5"
                        />
                    </svg>
                </div>

                <div class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2 select-none opacity-[0.1]">
                    <span class="material-icons text-[180px] leading-none text-white">corporate_fare</span>
                </div>

                <div class="relative px-6 py-8 sm:px-10 sm:py-10">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-3">
                            <div class="min-w-0 space-y-3">
                                <img
                                    v-if="logoUrl"
                                    :src="logoUrl"
                                    :alt="`${subsidiaryLabel} logo`"
                                    class="max-w-[200px] w-full rounded-md bg-white p-1.5 object-contain object-left"
                                />
                                <div class="min-w-0 space-y-1">
                                    <h1 class="text-2xl font-bold leading-tight text-white sm:text-3xl">
                                        {{ subsidiaryLabel }}
                                    </h1>
                                    <p v-if="record.legal_name" class="text-sm font-medium text-primary-100">
                                        {{ record.legal_name }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white sm:hidden"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            isActive ? 'bg-green-300' : 'bg-gray-300',
                                        ]"
                                    />
                                    {{ isActive ? 'Active' : 'Inactive' }}
                                </span>
                                <div
                                    v-if="timezoneLabel"
                                    class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm"
                                >
                                    <span class="material-icons text-[16px] text-white">schedule</span>
                                    <span class="text-sm font-medium text-white">{{ timezoneLabel }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                v-if="record.email"
                                :href="`mailto:${record.email}`"
                                class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/20"
                            >
                                <span class="material-icons text-[16px] text-white">mail</span>
                                <span class="max-w-[14rem] truncate text-sm font-medium text-white sm:max-w-xs">
                                    {{ record.email }}
                                </span>
                            </a>
                            <a
                                v-if="formattedPhone"
                                :href="`tel:${record.phone}`"
                                class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/20"
                            >
                                <span class="material-icons text-[16px] text-white">phone</span>
                                <span class="text-sm font-medium text-white">{{ formattedPhone }}</span>
                            </a>
                            <a
                                v-if="websiteHref"
                                :href="websiteHref"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 rounded-lg bg-white/15 px-3 py-2 backdrop-blur-sm transition-colors hover:bg-white/20"
                            >
                                <span class="material-icons text-[16px] text-white">language</span>
                                <span class="max-w-[14rem] truncate text-sm font-medium text-white sm:max-w-xs">
                                    {{ record.website }}
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <div class="space-y-6 lg:col-span-8">
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header
                            class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <span class="material-icons text-base text-gray-500 dark:text-gray-400">place</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Address</h3>
                        </header>
                        <div class="p-5">
                            <div v-if="hasAddress" class="flex items-start gap-3">
                                <span class="material-icons mt-0.5 shrink-0 text-[20px] text-gray-400">location_on</span>
                                <div class="space-y-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                                    <template v-if="addressLines.length">
                                        <div v-for="line in addressLines" :key="line">{{ line }}</div>
                                    </template>
                                    <div v-else-if="record.full_address">{{ record.full_address }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No address on file.</p>
                            <a
                                v-if="mapsUrl"
                                :href="mapsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                <span class="material-icons text-[16px]">map</span>
                                Open in Google Maps
                            </a>
                        </div>
                    </section>

                    <section
                        v-if="record.notes"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header
                            class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <span class="material-icons text-base text-gray-500 dark:text-gray-400">notes</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notes</h3>
                        </header>
                        <div class="p-5">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                {{ record.notes }}
                            </p>
                        </div>
                    </section>

                    <Sublist
                        v-if="visibleSublists.length > 0 && domainName"
                        :parent-record="record"
                        :parent-domain="domainName"
                        :sublists="visibleSublists"
                    />
                </div>

                <div class="space-y-6 lg:col-span-4">
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <header class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Record details</h3>
                        </header>
                        <dl class="divide-y divide-gray-100 p-5 dark:divide-gray-700/60">
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd class="text-gray-900 dark:text-white">{{ fmtDate(record.created_at) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                                <dd class="text-gray-900 dark:text-white">{{ fmtDate(record.updated_at) }}</dd>
                            </div>
                            <div
                                v-if="record.latitude && record.longitude"
                                class="flex justify-between gap-3 py-2 text-sm"
                            >
                                <dt class="text-gray-500 dark:text-gray-400">Coordinates</dt>
                                <dd class="font-mono text-xs text-gray-900 dark:text-white">
                                    {{ Number(record.latitude).toFixed(4) }},
                                    {{ Number(record.longitude).toFixed(4) }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                >
                    <span class="material-icons text-2xl text-red-600 dark:text-red-400">delete</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete subsidiary</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ subsidiaryLabel }}</span>?
                    Linked locations and users will be unlinked. This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        <span v-if="isDeleting" class="material-icons animate-spin text-[16px]">sync</span>
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
