<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InvoiceDocumentBody from '@/Components/Tenant/InvoiceDocumentBody.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
});

const title = computed(() =>
    props.record?.display_name ? `Invoice ${props.record.display_name}` : 'Invoice',
);
</script>

<template>
    <Head :title="title" />

    <GuestLayout>
        <div class="min-h-screen bg-gray-100 py-8 px-4 sm:px-6">
            <div class="mx-auto max-w-3xl">
                <p class="mb-4 text-center text-sm text-gray-500">
                    Invoice from
                    {{ account?.settings?.business_name ?? account?.business_name ?? 'your service provider' }}
                </p>
                <InvoiceDocumentBody
                    :record="record"
                    :account="account"
                    :enum-options="enumOptions"
                    :logo-url="logoUrl"
                />
            </div>
        </div>
    </GuestLayout>
</template>
