<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shipment: { type: Object, required: true },
});

const buyingRateId = ref('');
const autoNotify = ref(false);
const sendSms = ref(false);
const actionMessage = ref(usePage().props.flash?.success ?? '');

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Shipments', href: route('shipments.index') },
    { label: props.shipment.display_name },
]);

const canShopRates = computed(() => !['purchased', 'in_transit', 'delivered', 'refunded'].includes(props.shipment.status));
const canBuy = computed(() => props.shipment.status === 'rated' || props.shipment.status === 'draft');
const canNotify = computed(() => ['purchased', 'in_transit', 'delivered'].includes(props.shipment.status));

function formatMoney(amount) {
    if (amount == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(amount));
}

function formatMoneyCents(cents) {
    if (cents == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);
}

function shopRates() {
    router.post(route('shipments.rates', props.shipment.id), {}, { preserveScroll: true });
}

function buyRate(rateId) {
    buyingRateId.value = rateId;
    router.post(route('shipments.buy', props.shipment.id), {
        rate_id: rateId,
        auto_notify: autoNotify.value,
    }, {
        preserveScroll: true,
        onFinish: () => { buyingRateId.value = ''; },
    });
}

function notifyRecipient() {
    router.post(route('shipments.notify', props.shipment.id), {
        send_sms: sendSms.value,
    }, { preserveScroll: true });
}

function refundShipment() {
    if (! confirm('Refund this shipment label?')) return;
    router.post(route('shipments.refund', props.shipment.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="shipment.display_name" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Breadcrumb :items="breadcrumbItems" />
                    <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ shipment.display_name }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ shipment.status_label }} · {{ shipment.recipient_name }}</p>
                </div>
                <Link :href="route('shipments.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">All shipments</Link>
            </div>
        </template>

        <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
            <div v-if="actionMessage" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ actionMessage }}</div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Details</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Recipient</dt><dd class="font-medium text-gray-900 dark:text-white">{{ shipment.recipient_name }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd>{{ shipment.recipient_email || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Carrier</dt><dd>{{ shipment.carrier || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Service</dt><dd>{{ shipment.service || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Tracking</dt><dd>{{ shipment.tracking_code || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Label cost</dt><dd>{{ formatMoneyCents(shipment.rate_cents) }}</dd></div>
                </dl>
                <div v-if="shipment.label_url" class="mt-4">
                    <a :href="shipment.label_url" target="_blank" rel="noopener" class="text-sm font-medium text-primary-600 hover:underline">Download label</a>
                </div>
                <div v-if="shipment.public_tracking_url" class="mt-2">
                    <a :href="shipment.public_tracking_url" target="_blank" rel="noopener" class="text-sm text-primary-600 hover:underline">Carrier tracking page</a>
                </div>
            </section>

            <section v-if="canShopRates" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Rate shopping</h3>
                    <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="shopRates">
                        {{ shipment.rates_snapshot?.length ? 'Refresh rates' : 'Get rates' }}
                    </button>
                </div>
                <div v-if="shipment.rates_snapshot?.length" class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left">Carrier</th>
                                <th class="px-4 py-2 text-left">Service</th>
                                <th class="px-4 py-2 text-left">Est. days</th>
                                <th class="px-4 py-2 text-left">Rate</th>
                                <th class="px-4 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="rate in shipment.rates_snapshot" :key="rate.id">
                                <td class="px-4 py-2">{{ rate.carrier }}</td>
                                <td class="px-4 py-2">{{ rate.service }}</td>
                                <td class="px-4 py-2">{{ rate.delivery_days ?? rate.est_delivery_days ?? '—' }}</td>
                                <td class="px-4 py-2">{{ formatMoney(rate.rate) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-primary-600 px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 disabled:opacity-50"
                                        :disabled="buyingRateId === rate.id"
                                        @click="buyRate(rate.id)"
                                    >
                                        {{ buyingRateId === rate.id ? 'Buying…' : 'Buy label' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <label v-if="shipment.rates_snapshot?.length" class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="autoNotify" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                    Send tracking notification after purchase
                </label>
            </section>

            <section v-if="canNotify" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Notification</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Send a tracking link to the recipient{{ shipment.notified_at ? ` (last sent ${new Date(shipment.notified_at).toLocaleString()})` : '' }}.
                </p>
                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="sendSms" type="checkbox" class="rounded border-gray-300 text-primary-600" />
                    Also send SMS when phone is available
                </label>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="notifyRecipient">
                        Send tracking notification
                    </button>
                    <a :href="shipment.track_url" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                        Preview public page
                    </a>
                </div>
            </section>

            <section v-if="shipment.status === 'purchased' || shipment.status === 'in_transit'" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Refund</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Request a refund for the purchased label through EasyPost.</p>
                <button type="button" class="mt-4 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" @click="refundShipment">
                    Refund label
                </button>
            </section>
        </div>
    </TenantLayout>
</template>
