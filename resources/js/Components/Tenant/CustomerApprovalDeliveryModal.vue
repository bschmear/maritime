<script setup>
import Modal from '@/Components/Modal.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const delivery = defineModel('delivery', { type: String, default: 'email' });

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Send for approval' },
    subtitle: { type: String, default: '' },
    emailPreview: { type: String, default: '' },
    smsOffer: {
        type: Object,
        default: () => ({ offered: false, hint: null }),
    },
    deliveryError: { type: String, default: '' },
    processing: { type: Boolean, default: false },
});

defineEmits(['close', 'confirm']);

const page = usePage();

const sandboxMode = computed(() => Boolean(page.props.tenant_sandbox_mode));
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ subtitle }}
            </p>
            <p
                v-if="sandboxMode"
                class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
                <span>Uses your login email for the message and your staff user profile phone for SMS (matched by email).</span>
            </p>
            <p v-if="emailPreview" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                <template v-if="sandboxMode">Email will be sent to you at </template>
                <template v-else>Email goes to </template>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ emailPreview }}</span>
            </p>
            <p v-if="deliveryError" class="mt-2 text-sm text-red-600 dark:text-red-400">
                {{ deliveryError }}
            </p>

            <fieldset class="mt-4 space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                    <input v-model="delivery" type="radio" name="approval_delivery" value="email" class="mt-1 text-primary-600" />
                    <span>
                        <span class="font-medium text-gray-900 dark:text-white">Email only</span>
                        <span class="mt-0.5 block whitespace-normal text-sm text-gray-500 dark:text-gray-400">Send the approval request by email.</span>
                    </span>
                </label>
                <label
                    class="flex items-start gap-3 rounded-lg border p-3"
                    :class="
                        smsOffer.offered
                            ? 'cursor-pointer border-gray-200 dark:border-gray-600'
                            : 'cursor-not-allowed border-gray-100 opacity-60 dark:border-gray-700'
                    "
                >
                    <input
                        v-model="delivery"
                        type="radio"
                        name="approval_delivery"
                        value="email_sms"
                        class="mt-1 text-primary-600 disabled:cursor-not-allowed"
                        :disabled="!smsOffer.offered"
                    />
                    <span>
                        <span class="font-medium text-gray-900 dark:text-white">Email and SMS</span>
                        <span class="mt-0.5 block whitespace-normal text-sm text-gray-500 dark:text-gray-400">
                            Also send a short text with the review link.
                        </span>
                        <span
                            v-if="!smsOffer.offered && smsOffer.hint"
                            class="mt-1 block whitespace-normal text-sm text-amber-800 dark:text-amber-200"
                        >
                            {{ smsOffer.hint }}
                        </span>
                    </span>
                </label>
            </fieldset>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="$emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50"
                    @click="$emit('confirm')"
                >
                    <span v-if="processing" class="material-icons animate-spin text-base">refresh</span>
                    {{ processing ? 'Sending…' : 'Send' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
