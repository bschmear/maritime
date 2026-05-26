<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import DeliveryReviewDocument from '@/Components/Tenant/DeliveryReviewDocument.vue';

const props = defineProps({
    record: { type: Object, required: true },
    account: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    enumOptions: { type: Object, default: () => ({}) },
    checklistItems: { type: Array, default: () => [] },
    deliverySignatureSms: {
        type: Object,
        default: () => ({
            category_enabled: false,
            offered: false,
            hint: null,
        }),
    },
});

const emit = defineEmits(['close']);

const page = usePage();

const effectiveLogoUrl = computed(() => props.logoUrl ?? props.account?.logo_url ?? null);

const printing = ref(false);
const showSignatureDeliveryModal = ref(false);
const signatureDelivery = ref('email');

const isSigned = computed(() => !!props.record?.signed_at);

const customerEmail = computed(() => props.record?.customer?.email ?? props.record?.customer?.contact?.email ?? '');

const signatureEmailPreview = computed(() => {
    if (page.props.tenant_sandbox_mode) {
        return page.props.auth?.user?.email ?? '';
    }
    return customerEmail.value;
});

const signatureModalSubtitle = computed(() =>
    page.props.tenant_sandbox_mode
        ? 'Sandbox is on: choose how you want to receive the test. Email and SMS go to you, not the customer.'
        : 'Choose how to notify the customer.',
);

const sendSignatureForm = useForm({ delivery: 'email' });

const openSignatureDeliveryModal = () => {
    if (isSigned.value) return;
    signatureDelivery.value = 'email';
    showSignatureDeliveryModal.value = true;
};

const closeSignatureDeliveryModal = () => {
    showSignatureDeliveryModal.value = false;
};

const confirmSendSignature = () => {
    sendSignatureForm.delivery = signatureDelivery.value;
    sendSignatureForm.post(route('deliveries.send-signature-request', props.record.id), {
        preserveState: true,
        onSuccess: () => {
            closeSignatureDeliveryModal();
        },
    });
};

const handlePrint = () => {
    printing.value = true;
    try {
        window.open(route('deliveries.print', props.record.id), '_blank');
    } catch {
        setTimeout(() => window.print(), 50);
    }
    printing.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Action bar (matches ServiceTicketPreview) -->
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm print:hidden">
            <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-2 lg:py-4">
                <div class="flex items-center justify-between gap-2 lg:gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white lg:text-lg truncate">
                            Customer Preview
                        </h2>
                        <p class="hidden text-sm text-gray-500 dark:text-gray-400 lg:block mt-0.5">
                            This is how the delivery will appear to the customer
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 lg:gap-3">
                        <button
                            type="button"
                            aria-label="Close preview"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 lg:px-4"
                            @click="$emit('close')"
                        >
                            <span class="material-icons text-[18px]">close</span>
                            <span class="hidden lg:inline">Close</span>
                        </button>

                        <button
                            v-if="!isSigned"
                            type="button"
                            :aria-label="sendSignatureForm.processing ? 'Sending signature request' : 'Send signature request to customer'"
                            :aria-busy="sendSignatureForm.processing"
                            :disabled="sendSignatureForm.processing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-orange-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="openSignatureDeliveryModal"
                        >
                            <span v-if="sendSignatureForm.processing" class="material-icons animate-spin text-[18px]">refresh</span>
                            <span v-else class="material-icons text-[18px]">send</span>
                            <span class="hidden lg:inline">{{ sendSignatureForm.processing ? 'Sending…' : 'Send to Customer' }}</span>
                        </button>

                        <button
                            type="button"
                            :aria-label="printing ? 'Preparing print' : 'Print preview'"
                            :aria-busy="printing"
                            :disabled="printing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-2.5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 lg:px-4"
                            @click="handlePrint"
                        >
                            <span v-if="printing" class="material-icons animate-spin text-[18px]">refresh</span>
                            <span v-else class="material-icons text-[18px]">print</span>
                            <span class="hidden lg:inline">{{ printing ? 'Preparing…' : 'Print' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Printable document (matches public /deliveries/{uuid}/review) -->
        <div id="delivery-print-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:p-0 print:max-w-none">
            <DeliveryReviewDocument
                :record="record"
                :account="account"
                :logo-url="effectiveLogoUrl"
                mode="preview"
            />
        </div>

        <!-- Send to customer: email vs email + SMS -->
        <Modal :show="showSignatureDeliveryModal" max-width="md" @close="closeSignatureDeliveryModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Send to customer</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ signatureModalSubtitle }}
                </p>
                <p
                    v-if="page.props.tenant_sandbox_mode"
                    class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                    <span>Uses your login email for the message and your staff user profile phone for SMS (matched by email).</span>
                </p>
                <p v-if="signatureEmailPreview" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="page.props.tenant_sandbox_mode">Email will be sent to you at </template>
                    <template v-else>Email goes to </template>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ signatureEmailPreview }}</span>
                </p>
                <p v-if="sendSignatureForm.errors.delivery" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ sendSignatureForm.errors.delivery }}
                </p>
                <p v-if="sendSignatureForm.errors.error" class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ sendSignatureForm.errors.error }}
                </p>

                <fieldset class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                        <input v-model="signatureDelivery" type="radio" name="signature_delivery" value="email" class="mt-1 text-primary-600" />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email only</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">Send the signature request by email.</span>
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-3 rounded-lg border p-3"
                        :class="
                            deliverySignatureSms.offered
                                ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                                : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                        "
                    >
                        <input
                            v-model="signatureDelivery"
                            type="radio"
                            name="signature_delivery"
                            value="email_sms"
                            class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                            :disabled="!deliverySignatureSms.offered"
                        />
                        <span>
                            <span class="font-medium text-gray-900 dark:text-white">Email and SMS</span>
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                                Also send a short text with the review link.
                            </span>
                            <span
                                v-if="!deliverySignatureSms.offered && deliverySignatureSms.hint"
                                class="mt-1 block text-sm text-amber-800 dark:text-amber-200"
                            >
                                {{ deliverySignatureSms.hint }}
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        :disabled="sendSignatureForm.processing"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="closeSignatureDeliveryModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="sendSignatureForm.processing || (signatureDelivery === 'email_sms' && !deliverySignatureSms.offered)"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50"
                        @click="confirmSendSignature"
                    >
                        <span v-if="sendSignatureForm.processing" class="material-icons animate-spin text-base">refresh</span>
                        {{ sendSignatureForm.processing ? 'Sending…' : 'Send' }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style>
@media print {
    .sticky {
        display: none !important;
    }

    .bg-white {
        background-color: white !important;
    }

    .shadow-lg {
        box-shadow: none !important;
    }

    @page {
        margin: 0.5in;
    }
}
</style>
