<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import ConsignmentAgreementForm from '@/Components/Tenant/ConsignmentAgreementForm.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, default: 'consignmentagreements' },
    recordTitle: { type: String, default: 'Consignment agreement' },
    pluralTitle: { type: String, default: 'Consignment agreements' },
    domainName: { type: String, default: 'ConsignmentAgreement' },
    formSchema: { type: Object, default: null },
    fieldsSchema: { type: Object, default: () => ({}) },
    enumOptions: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    canMutate: { type: Boolean, default: true },
    reviewUrl: { type: String, default: null },
});

const label = computed(() => {
    const ref = props.record.display_name;
    if (ref != null && ref !== '') {
        return `#${ref}`;
    }
    return `Agreement #${props.record.id}`;
});

const indexHref = computed(() => route('consignmentagreements.index'));
const editHref = computed(() => route('consignmentagreements.edit', props.record.id));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: props.pluralTitle, href: indexHref.value },
    { label: label.value },
]);

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const cancelDelete = () => {
    showDeleteModal.value = false;
};

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('consignmentagreements.destroy', props.record.id), {
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
    <Head :title="label" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ label }}
                    </h2>
                    <div v-if="canMutate" class="flex flex-wrap gap-2">
                        <Link
                            :href="editHref"
                            class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            @click="showDeleteModal = true"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="w-full grid gap-6 lg:grid-cols-12">
            <div class="min-w-0 flex-1 space-y-4 lg:col-span-8">
                <div
                    v-if="reviewUrl"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <a
                        :href="reviewUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 font-medium text-amber-950 underline hover:no-underline dark:text-amber-100"
                    >
                        <span class="material-icons text-base">open_in_new</span>
                        Public review &amp; sign page
                    </a>
                </div>

                <ConsignmentAgreementForm
                    mode="view"
                    :record="record"
                    :fields-schema="fieldsSchema"
                    :enum-options="enumOptions"
                />
            </div>

            <aside class="w-full shrink-0 lg:col-span-4 space-y-6">
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 sticky top-[140px] ">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        Summary
                    </div>
                    <dl class="space-y-3 px-4 py-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                                {{ record.signed_at ? 'Signed' : 'Unsigned' }}
                            </dd>
                        </div>
                        <div v-if="record.asset_unit || record.assetUnit">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit</dt>
                            <dd class="mt-0.5 text-gray-900 dark:text-gray-100">
                                {{
                                    (record.asset_unit || record.assetUnit).display_name
                                        || `Unit #${(record.asset_unit || record.assetUnit).id}`
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>

        <Modal :show="showDeleteModal" max-width="md" @close="cancelDelete">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete agreement</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Only unsigned drafts can be removed. This cannot be undone.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        :disabled="isDeleting"
                        @click="cancelDelete"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
