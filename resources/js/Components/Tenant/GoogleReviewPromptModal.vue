<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    prompt: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const defaultMessage = computed(() => {
    const fromPrompt = props.prompt?.default_message?.trim?.();
    if (fromPrompt) {
        return fromPrompt;
    }

    const subsidiary = props.prompt?.subsidiary_name?.trim?.();
    if (subsidiary) {
        return `We appreciate your business with ${subsidiary}. We'd appreciate it if you could leave us a Google review.`;
    }

    return 'We appreciate your business. We\'d appreciate it if you could leave us a Google review.';
});

const sendForm = useForm({
    message: defaultMessage.value,
});

watch(
    () => [props.show, defaultMessage.value],
    ([show, message]) => {
        if (show) {
            sendForm.message = message;
            sendForm.clearErrors();
        }
    },
);

const customerEmail = computed(() => props.prompt?.customer_email?.trim?.() ?? '');

const customerLabel = computed(() => {
    const name = props.prompt?.customer_name?.trim?.();
    if (name) {
        return name;
    }
    return customerEmail.value || 'the customer';
});

const subsidiaryName = computed(() => props.prompt?.subsidiary_name ?? 'this location');

const canSend = computed(() => {
    if (!customerEmail.value || props.prompt?.sandbox_mode) {
        return false;
    }

    return sendForm.message.trim().length >= 10;
});

const close = () => {
    if (!sendForm.processing) {
        emit('close');
    }
};

const sendReviewRequest = () => {
    if (!canSend.value || !props.prompt?.transaction_id) {
        return;
    }

    sendForm.post(route('transactions.send-google-review-request', props.prompt.transaction_id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <div class="flex items-start gap-3">
                <span class="material-icons shrink-0 text-3xl text-amber-500 dark:text-amber-400">star</span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Send a Google review request?
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        This deal is assigned to <strong class="text-gray-900 dark:text-white">{{ subsidiaryName }}</strong>.
                        Only send this if the customer had a great experience — nothing goes out automatically.
                    </p>
                </div>
            </div>

            <div
                v-if="prompt?.sandbox_mode"
                class="mt-4 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                <span class="material-icons shrink-0 text-base text-amber-600 dark:text-amber-400">science</span>
                <span>Sandbox mode is on. Google review emails are disabled until you turn sandbox off in Account settings.</span>
            </div>

            <div
                v-else-if="!customerEmail"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
            >
                Add an email address for {{ customerLabel }} before sending a review request.
            </div>

            <template v-else>
                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-600 dark:bg-gray-900/40">
                    <p class="text-gray-700 dark:text-gray-300">
                        Email <strong class="text-gray-900 dark:text-white">{{ customerLabel }}</strong>
                        at <strong class="text-gray-900 dark:text-white">{{ customerEmail }}</strong>
                        with a link to leave a Google review.
                    </p>
                </div>

                <div class="mt-4">
                    <label
                        for="google-review-message"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Email message
                    </label>
                    <textarea
                        id="google-review-message"
                        v-model="sendForm.message"
                        rows="5"
                        class="input-style resize-y min-h-[120px]"
                        :disabled="sendForm.processing"
                    />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Edit the message below before sending. The email will include a button to your Google review link.
                    </p>
                    <p v-if="sendForm.errors.message" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ sendForm.errors.message }}
                    </p>
                </div>
            </template>

            <p v-if="sendForm.errors.error" class="mt-3 text-sm text-red-600 dark:text-red-400">
                {{ sendForm.errors.error }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    :disabled="sendForm.processing"
                    @click="close"
                >
                    Not now
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSend || sendForm.processing"
                    @click="sendReviewRequest"
                >
                    <span v-if="sendForm.processing" class="material-icons animate-spin text-[18px]">sync</span>
                    <span v-else class="material-icons text-[18px]">send</span>
                    {{ sendForm.processing ? 'Sending…' : 'Send review email' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
