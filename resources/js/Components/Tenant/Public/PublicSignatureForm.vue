<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    /** POST URL (e.g. route('consignment-agreements.sign', uuid)) */
    action: { type: String, required: true },
    submitLabel: { type: String, default: 'Sign document' },
    /** Optional grey box above signature controls */
    acknowledgementText: { type: String, default: '' },
    /** Shown next to consent checkbox (plain text) */
    consentLabel: { type: String, required: true },
    /** When true, POST includes recipient_name equal to trimmed signed_name */
    includeRecipientName: { type: Boolean, default: false },
});

const emit = defineEmits(['signed']);

const signatureMode = ref('draw');
const signaturePadRef = ref(null);
const typedSignature = ref('');
const consent = ref(false);
const approvalError = ref('');

const signForm = useForm({
    signature_method: 'draw',
    signature_data: '',
    signed_name: '',
    recipient_name: '',
    consent: false,
});

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature = () => signaturePadRef.value?.undoSignature();

const submit = () => {
    approvalError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) {
            approvalError.value = 'Signature pad is not ready. Please try again.';
            return;
        }
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            approvalError.value = 'Please draw your signature before submitting.';
            return;
        }
        signForm.signature_data = data;
        signForm.signature_method = 'draw';
    } else {
        const typed = typedSignature.value.trim();
        if (!typed) {
            approvalError.value = 'Please type your signature before submitting.';
            return;
        }
        signForm.signature_data = typed;
        signForm.signature_method = 'type';
    }

    if (!signForm.signed_name.trim()) {
        approvalError.value = 'Please enter your printed name.';
        return;
    }

    if (!consent.value) {
        approvalError.value = 'Please accept the acknowledgement to continue.';
        return;
    }

    signForm.consent = consent.value;
    if (props.includeRecipientName) {
        signForm.recipient_name = signForm.signed_name.trim();
    }

    signForm.post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            emit('signed');
            window.location.reload();
        },
        onError: (errors) => {
            approvalError.value = 'Could not submit signature. Please try again.';
            if (errors.signature_data) {
                approvalError.value = Array.isArray(errors.signature_data)
                    ? errors.signature_data[0]
                    : errors.signature_data;
            }
        },
    });
};
</script>

<template>
    <div>
        <div v-if="acknowledgementText" class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-5">
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-900">{{ acknowledgementText }}</p>
        </div>

        <div v-if="approvalError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm text-red-700">{{ approvalError }}</p>
        </div>

        <div class="mb-6">
            <label class="mb-3 block text-sm font-medium text-gray-700">Signature</label>
            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors',
                        signatureMode === 'draw' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                    ]"
                    @click="signatureMode = 'draw'"
                >
                    <span class="material-icons text-sm">draw</span>
                    Draw
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 border-l border-gray-300 px-4 py-2.5 text-sm font-medium transition-colors',
                        signatureMode === 'type' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                    ]"
                    @click="signatureMode = 'type'"
                >
                    <span class="material-icons text-sm">keyboard</span>
                    Type
                </button>
            </div>
        </div>

        <div v-show="signatureMode === 'draw'" class="mb-6">
            <div class="relative overflow-hidden rounded-lg border-2 border-gray-300 bg-white">
                <VueSignaturePad
                    ref="signaturePadRef"
                    width="100%"
                    height="200px"
                    :options="signaturePadOptions"
                />
                <div class="pointer-events-none absolute bottom-4 left-4 right-4 border-b border-gray-300" />
            </div>
            <div class="mt-2 flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
                    @click="undoSignature"
                >
                    <span class="material-icons text-sm">undo</span>
                    Undo
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-700"
                    @click="clearSignature"
                >
                    <span class="material-icons text-sm">clear</span>
                    Clear
                </button>
            </div>
        </div>

        <div v-show="signatureMode === 'type'" class="mb-6">
            <label class="mb-2 block text-sm font-medium text-gray-700">Type your name</label>
            <input
                v-model="typedSignature"
                type="text"
                autocomplete="name"
                placeholder="Type your full name"
                class="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg transition-colors focus:border-gray-900 focus:ring-0"
            />
            <div
                v-if="typedSignature.trim()"
                class="mt-4 flex items-end justify-center rounded-lg border-2 border-gray-200 bg-white px-6 py-8"
            >
                <div class="w-full text-center">
                    <p
                        class="signature-cursive inline-block min-w-[200px] border-b border-gray-300 pb-2 text-4xl text-gray-900"
                    >
                        {{ typedSignature }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <label for="public-sig-signed-name" class="mb-2 block text-sm font-medium text-gray-700">Print name</label>
            <input
                id="public-sig-signed-name"
                v-model="signForm.signed_name"
                type="text"
                autocomplete="name"
                placeholder="Your full legal name"
                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-gray-900 focus:ring-0"
            />
            <p v-if="signForm.errors.signed_name" class="mt-1 text-sm text-red-600">{{ signForm.errors.signed_name }}</p>
        </div>

        <div class="mb-8">
            <label class="flex cursor-pointer items-start gap-3">
                <input
                    v-model="consent"
                    type="checkbox"
                    class="mt-1 h-5 w-5 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                />
                <span class="text-sm leading-relaxed text-gray-700">{{ consentLabel }}</span>
            </label>
            <p v-if="signForm.errors.consent" class="ml-8 mt-1 text-sm text-red-600">{{ signForm.errors.consent }}</p>
        </div>

        <div v-if="Object.keys(signForm.errors).length" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
            <ul class="space-y-1 text-sm text-red-700">
                <li v-for="(error, key) in signForm.errors" :key="key">{{ error }}</li>
            </ul>
        </div>

        <button
            type="button"
            :disabled="signForm.processing || !consent"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-8 py-3 text-base font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
            @click="submit"
        >
            <span v-if="signForm.processing" class="material-icons animate-spin text-sm">refresh</span>
            <span v-else class="material-icons text-sm">draw</span>
            {{ signForm.processing ? 'Submitting…' : submitLabel }}
        </button>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}
</style>
