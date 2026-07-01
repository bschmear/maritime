<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    shipment: { type: Object, required: true },
    account: { type: Object, default: null },
});
</script>

<template>
    <Head :title="`Track ${shipment.tracking_code || shipment.display_name}`" />

    <div class="min-h-screen bg-gray-50 px-4 py-10 text-gray-800">
        <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl bg-white shadow-lg">
            <div class="bg-blue-700 px-6 py-8 text-white">
                <p class="text-sm uppercase tracking-wide text-blue-100">{{ account?.name || 'Shipment tracking' }}</p>
                <h1 class="mt-2 text-2xl font-bold">{{ shipment.display_name }}</h1>
                <p v-if="shipment.tracking_code" class="mt-2 font-mono text-lg">{{ shipment.tracking_code }}</p>
            </div>

            <div class="space-y-6 px-6 py-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="font-medium">{{ shipment.status_label }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Recipient</p>
                        <p class="font-medium">{{ shipment.recipient_name || '—' }}</p>
                    </div>
                    <div v-if="shipment.carrier">
                        <p class="text-sm text-gray-500">Carrier</p>
                        <p class="font-medium">{{ shipment.carrier }}</p>
                    </div>
                    <div v-if="shipment.service">
                        <p class="text-sm text-gray-500">Service</p>
                        <p class="font-medium">{{ shipment.service }}</p>
                    </div>
                </div>

                <div v-if="shipment.to_address" class="rounded-lg bg-gray-50 p-4 text-sm">
                    <p class="font-medium text-gray-900">Delivery address</p>
                    <p class="mt-2 text-gray-700">
                        {{ shipment.to_address.name }}<br>
                        {{ shipment.to_address.street1 }}<br>
                        <span v-if="shipment.to_address.street2">{{ shipment.to_address.street2 }}<br></span>
                        {{ shipment.to_address.city }}, {{ shipment.to_address.state }} {{ shipment.to_address.zip }}
                    </p>
                </div>

                <div v-if="shipment.tracking_events?.length" class="space-y-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Updates</h2>
                    <div v-for="(event, index) in [...shipment.tracking_events].reverse()" :key="index" class="rounded-lg border border-gray-200 p-3 text-sm">
                        <p class="font-medium text-gray-900">{{ event.status || 'Update' }}</p>
                        <p v-if="event.message" class="text-gray-600">{{ event.message }}</p>
                        <p v-if="event.datetime" class="mt-1 text-xs text-gray-500">{{ new Date(event.datetime).toLocaleString() }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a
                        v-if="shipment.public_tracking_url"
                        :href="shipment.public_tracking_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                    >
                        View carrier tracking
                    </a>
                    <a
                        v-if="shipment.label_url"
                        :href="shipment.label_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Download label
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
