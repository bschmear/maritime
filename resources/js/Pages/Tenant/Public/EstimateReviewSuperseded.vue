<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'superseded',
    },
    estimateDisplayName: { type: String, required: true },
    expirationDateLabel: { type: String, default: null },
    salesperson: {
        type: Object,
        default: null,
    },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
});

const isExpired = computed(() => props.variant === 'expired');

const companyName = computed(() => props.account?.name || 'Company');

const pageTitle = computed(() =>
    isExpired.value
        ? `Estimate expired — ${props.estimateDisplayName}`
        : `Estimate unavailable — ${props.estimateDisplayName}`,
);

const headline = computed(() =>
    isExpired.value ? 'This estimate has expired' : 'This estimate is no longer valid',
);

const formatPhoneNumber = (phone) => {
    if (!phone) return '';
    const cleaned = String(phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-screen bg-gray-100">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-8 pt-10 pb-6 border-b border-gray-100 flex flex-col items-center text-center">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="companyName"
                        class="h-12 sm:h-14 object-contain mb-6"
                    />
                    <div
                        :class="[
                            'inline-flex items-center justify-center w-16 h-16 rounded-full mb-4',
                            isExpired ? 'bg-orange-100 text-orange-700' : 'bg-amber-100 text-amber-700',
                        ]"
                    >
                        <span class="material-icons text-4xl">{{ isExpired ? 'event_busy' : 'info' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ headline }}</h1>

                    <template v-if="isExpired">
                        <p class="text-gray-600 mt-3 text-sm sm:text-base max-w-xl">
                            The estimate
                            <strong>{{ estimateDisplayName }}</strong>
                            is no longer valid because it has expired. Please reach out to extend this estimate or to
                            request a new one.
                        </p>
                        <p v-if="expirationDateLabel" class="text-sm text-gray-500 mt-3">
                            Expiration date:
                            <span class="font-medium text-gray-700">{{ expirationDateLabel }}</span>
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-gray-600 mt-3 text-sm sm:text-base max-w-xl">
                            The estimate
                            <strong>{{ estimateDisplayName }}</strong>
                            has been updated and is not available for review in its previous form.
                        </p>
                    </template>
                </div>

                <div class="px-8 py-8 space-y-6">
                    <template v-if="salesperson && (salesperson.name || salesperson.email || salesperson.phone)">
                        <p class="text-sm text-gray-700 text-center">
                            <template v-if="isExpired">
                                You can contact the salesperson below to extend this estimate or request a new one.
                            </template>
                            <template v-else> Please reach out to the salesperson listed below with any questions. </template>
                        </p>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 space-y-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Salesperson</p>
                            <p v-if="salesperson.name" class="text-lg font-semibold text-gray-900">
                                {{ salesperson.name }}
                            </p>
                            <p v-if="salesperson.email" class="text-sm text-gray-700">
                                <a :href="'mailto:' + salesperson.email" class="text-primary-600 hover:underline">{{
                                    salesperson.email
                                }}</a>
                            </p>
                            <p v-if="salesperson.phone" class="text-sm text-gray-700">
                                <a :href="'tel:' + String(salesperson.phone).replace(/\D/g, '')" class="hover:underline">{{
                                    formatPhoneNumber(salesperson.phone)
                                }}</a>
                            </p>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-700 text-center">
                        <template v-if="isExpired">
                            Please reach out to extend this estimate or to request a new one.
                        </template>
                        <template v-else> Please reach out for questions about this estimate. </template>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
