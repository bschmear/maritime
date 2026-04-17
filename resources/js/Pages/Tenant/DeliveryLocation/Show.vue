<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
});

const showDelete = ref(false);
const isDeleting = ref(false);

const breadcrumbItems = computed(() => [
    { label: 'Home',               href: route('dashboard') },
    { label: 'Delivery Locations', href: route('delivery-locations.index') },
    { label: props.record.display_name ?? props.record.name },
]);

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('delivery-locations.destroy', props.record.id), {
        onFinish: () => { isDeleting.value = false; showDelete.value = false; },
    });
};

const locationLabel = computed(() => props.record.display_name ?? props.record.name);

const addressLines = computed(() => {
    const parts = [];
    if (props.record.address_line_1) parts.push(props.record.address_line_1);
    if (props.record.address_line_2) parts.push(props.record.address_line_2);
    const cityLine = [props.record.city, props.record.state, props.record.postal_code].filter(Boolean).join(', ');
    if (cityLine) parts.push(cityLine);
    if (props.record.country) parts.push(props.record.country);
    return parts;
});

const hasAddress = computed(() => addressLines.value.length > 0);

const mapsUrl = computed(() => {
    if (props.record.latitude && props.record.longitude) {
        return `https://www.google.com/maps?q=${props.record.latitude},${props.record.longitude}`;
    }
    if (hasAddress.value) {
        const q = encodeURIComponent(addressLines.value.join(', '));
        return `https://www.google.com/maps?q=${q}`;
    }
    return null;
});
</script>

<template>
    <Head :title="locationLabel" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ locationLabel }}
                        </h2>
                        <span :class="[
                            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold',
                            record.active
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                        ]">
                            {{ record.active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('delivery-locations.edit', record.id)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">edit</span>
                            Edit
                        </Link>
                        <button
                            type="button"
                            @click="showDelete = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        >
                            <span class="material-icons text-[16px]">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full flex flex-col space-y-6">
            <div class="grid gap-6 lg:grid-cols-12">

                <!-- ── Main column ─────────────────────────────────────── -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Main info card -->
                    <section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center gap-2">
                            <span class="material-icons text-gray-500 dark:text-gray-400 text-base">location_on</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Location Details</h3>
                        </header>

                        <div class="p-5 space-y-6">

                            <!-- Address + Contact side by side -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Address -->
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                        Address
                                    </p>
                                    <div v-if="hasAddress" class="flex items-start gap-3">
                                        <span class="material-icons text-gray-400 dark:text-gray-500 text-[20px] mt-0.5 shrink-0">place</span>
                                        <div class="text-sm text-gray-800 dark:text-gray-200 space-y-0.5 leading-relaxed">
                                            <div v-for="line in addressLines" :key="line">{{ line }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500">
                                        <span class="material-icons text-[18px]">location_off</span>
                                        No address recorded
                                    </div>
                                </div>

                                <!-- Contact info -->
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                        Contact
                                    </p>
                                    <div class="space-y-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="material-icons text-gray-400 dark:text-gray-500 text-[18px] shrink-0">person</span>
                                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                                {{ record.contact_name || '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2.5">
                                            <span class="material-icons text-gray-400 dark:text-gray-500 text-[18px] shrink-0">phone</span>
                                            <a
                                                v-if="record.contact_phone"
                                                :href="`tel:${record.contact_phone}`"
                                                class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                            >{{ record.contact_phone }}</a>
                                            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="border-t border-gray-100 dark:border-gray-700" />

                            <!-- Status row -->
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</span>
                                <span :class="[
                                    'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold',
                                    record.active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                ]">
                                    {{ record.active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Notes card -->
                    <section v-if="record.notes" class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center gap-2">
                            <span class="material-icons text-gray-500 dark:text-gray-400 text-base">notes</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notes</h3>
                        </header>
                        <div class="p-5">
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ record.notes }}</p>
                        </div>
                    </section>

                </div>

                <!-- ── Sidebar ──────────────────────────────────────────── -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Actions -->
                    <section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Actions</span>
                        </header>
                        <div class="p-4 space-y-2.5">
                            <Link
                                :href="route('delivery-locations.edit', record.id)"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-[16px]">edit</span>
                                Edit Location
                            </Link>
                            <button
                                type="button"
                                @click="showDelete = true"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            >
                                <span class="material-icons text-[16px]">delete</span>
                                Delete Location
                            </button>
                        </div>
                    </section>

                    <!-- Google Maps card -->
                    <section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center gap-2">
                            <span class="material-icons text-gray-500 dark:text-gray-400 text-base">map</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Map</h3>
                        </header>
                        <div class="p-4">
                            <div v-if="mapsUrl">
                                <!-- Static map preview placeholder -->
                                <div class="w-full h-36 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 mb-3 flex items-center justify-center border border-gray-200 dark:border-gray-600">
                                    <div class="text-center">
                                        <span class="material-icons text-3xl text-gray-300 dark:text-gray-500 block mb-1">map</span>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ addressLines[0] ?? 'Location' }}</p>
                                    </div>
                                </div>
                                <a
                                    :href="mapsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                >
                                    <span class="material-icons text-[16px]">open_in_new</span>
                                    Open in Google Maps
                                </a>
                            </div>
                            <div v-else class="text-center py-4">
                                <span class="material-icons text-2xl text-gray-300 dark:text-gray-600 block mb-1">location_off</span>
                                <p class="text-xs text-gray-400 dark:text-gray-500">No address to map</p>
                            </div>
                        </div>
                    </section>

                    <!-- Location meta -->
                    <section class="bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <header class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Details</span>
                        </header>
                        <div class="p-4 space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Created</span>
                                <span class="text-gray-900 dark:text-white">
                                    {{ record.created_at ? new Date(record.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                <span class="text-gray-900 dark:text-white">
                                    {{ record.updated_at ? new Date(record.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }}
                                </span>
                            </div>
                            <template v-if="record.latitude && record.longitude">
                                <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500 dark:text-gray-400">Coordinates</span>
                                    <span class="font-medium text-gray-900 dark:text-white font-mono text-xs">
                                        {{ Number(record.latitude).toFixed(4) }}, {{ Number(record.longitude).toFixed(4) }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </section>

                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Modal :show="showDelete" @close="showDelete = false" max-width="md">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <span class="material-icons text-red-600 dark:text-red-400 text-2xl">delete</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Location</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ locationLabel }}</span>?
                    This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 transition-colors"
                    >
                        <span v-if="isDeleting" class="material-icons text-[16px] animate-spin">sync</span>
                        {{ isDeleting ? 'Deleting…' : 'Delete Location' }}
                    </button>
                    <button
                        type="button"
                        @click="showDelete = false"
                        :disabled="isDeleting"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>