<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    access: Object,
    record: Object,
    recordType: String,
});

const recordLabel = computed(() => {
    const labels = {
        estimate: 'Estimate',
        invoice: 'Invoice',
        service_ticket: 'Service Ticket',
        delivery: 'Delivery',
    };
    return labels[props.recordType] || 'Record';
});

const recordTitle = computed(() => {
    if (props.recordType === 'estimate') {
        return props.record?.title || `Estimate #${props.record?.id}`;
    }
    if (props.recordType === 'invoice') {
        return props.record?.invoice_number || `Invoice #${props.record?.id}`;
    }
    if (props.recordType === 'service_ticket') {
        return props.record?.title || `Ticket #${props.record?.id}`;
    }
    return `${recordLabel.value} #${props.record?.id}`;
});
</script>

<template>
    <Head :title="`${recordLabel} - Customer Portal`" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-white text-base">storefront</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900">Customer Portal</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">Shared document</span>
                    <Link
                        :href="route('portal.login')"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 no-underline"
                    >
                        Sign in for full access
                    </Link>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
            <!-- Record type badge -->
            <div class="mb-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-medium">
                    <span class="material-icons text-sm">
                        {{ recordType === 'estimate' ? 'request_quote' : recordType === 'invoice' ? 'receipt_long' : recordType === 'service_ticket' ? 'build_circle' : 'local_shipping' }}
                    </span>
                    {{ recordLabel }}
                </span>
            </div>

            <!-- Record card -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h1 class="text-xl font-bold text-gray-900">{{ recordTitle }}</h1>
                    <p v-if="access.expires_at" class="text-xs text-gray-400 mt-1">
                        Link expires: {{ new Date(access.expires_at).toLocaleDateString() }}
                    </p>
                </div>

                <!-- Record details -->
                <div class="px-6 py-6">
                    <div class="space-y-4">
                        <div v-for="(value, key) in record" :key="key" class="flex items-start">
                            <template v-if="!['id', 'created_at', 'updated_at', 'deleted_at'].includes(key) && value !== null">
                                <dt class="w-40 flex-shrink-0 text-sm font-medium text-gray-500 capitalize">
                                    {{ key.replace(/_/g, ' ') }}
                                </dt>
                                <dd class="text-sm text-gray-900">{{ value }}</dd>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div class="mt-8 p-6 bg-primary-50 border border-primary-100 rounded-xl text-center">
                <h3 class="font-semibold text-primary-900 mb-1">Want to see all your records?</h3>
                <p class="text-sm text-primary-700 mb-4">Create a free portal account to access all your estimates, invoices, and documents in one place.</p>
                <Link
                    :href="route('portal.register')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all no-underline"
                >
                    <span class="material-icons text-base">person_add</span>
                    Create Portal Account
                </Link>
            </div>
        </main>
    </div>
</template>
