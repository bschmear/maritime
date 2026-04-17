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
    { label: 'Home', href: route('dashboard') },
    { label: 'Delivery Locations', href: route('delivery-locations.index') },
    { label: props.record.display_name ?? props.record.name },
]);

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('delivery-locations.destroy', props.record.id), {
        onFinish: () => { isDeleting.value = false; showDelete.value = false; },
    });
};
</script>

<template>
    <Head :title="record.display_name ?? record.name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ record.display_name ?? record.name }}
                    </h2>
                    <div class="flex gap-2">
                        <Link
                            :href="route('delivery-locations.edit', record.id)"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md"
                        >
                            <span class="material-icons text-base">edit</span>
                            Edit
                        </Link>
                        <button
                            @click="showDelete = true"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md"
                        >
                            <span class="material-icons text-base">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Address</h3>
                <div class="text-sm text-gray-700 dark:text-gray-300 space-y-0.5">
                    <div v-if="record.address_line_1">{{ record.address_line_1 }}</div>
                    <div v-if="record.address_line_2">{{ record.address_line_2 }}</div>
                    <div>
                        <span v-if="record.city">{{ record.city }}</span><span v-if="record.state">, {{ record.state }}</span>
                        <span v-if="record.postal_code"> {{ record.postal_code }}</span>
                    </div>
                    <div v-if="!record.address_line_1 && !record.city" class="text-gray-400 italic">No address recorded</div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Contact</h3>
                <dl class="text-sm space-y-2">
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="text-gray-900 dark:text-white">{{ record.contact_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Phone</dt>
                        <dd class="text-gray-900 dark:text-white">{{ record.contact_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            <span :class="[
                                'inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full',
                                record.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                            ]">{{ record.active ? 'Active' : 'Inactive' }}</span>
                        </dd>
                    </div>
                </dl>
            </div>
            <div v-if="record.notes" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 md:col-span-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-2">Notes</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ record.notes }}</p>
            </div>
        </div>

        <Modal :show="showDelete" @close="showDelete = false" max-width="md">
            <div class="p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Location</h3>
                <p class="mt-2 text-sm text-gray-500">
                    Are you sure you want to delete {{ record.display_name ?? record.name }}?
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button @click="confirmDelete" :disabled="isDeleting"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md disabled:opacity-50">
                        {{ isDeleting ? 'Deleting…' : 'Delete' }}
                    </button>
                    <button @click="showDelete = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md">
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </TenantLayout>
</template>
