<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { VueSignaturePad } from 'vue-signature-pad';

const props = defineProps({
    userId: { type: [Number, String], required: true },
    canEdit: { type: Boolean, default: false },
    signature: {
        type: Object,
        default: () => ({
            method: null,
            url: null,
            typed: null,
            saved_at: null,
        }),
    },
    displayName: { type: String, default: '' },
});

const editing = ref(!props.signature?.url && !props.signature?.typed);
const signatureMode = ref(
    props.signature?.method === 5 || props.signature?.typed ? 'type' : 'draw',
);
const signaturePadRef = ref(null);
const typedSignature = ref(props.signature?.typed ?? '');
const saveError = ref('');

const signForm = useForm({
    signature_method: 'draw',
    signature_data: '',
});

const signaturePadOptions = {
    penColor: '#1a1a2e',
    minWidth: 1,
    maxWidth: 3,
};

const clearSignature = () => signaturePadRef.value?.clearSignature();
const undoSignature = () => signaturePadRef.value?.undoSignature();

const startEditing = () => {
    editing.value = true;
    saveError.value = '';
};

const cancelEditing = () => {
    editing.value = false;
    saveError.value = '';
    typedSignature.value = props.signature?.typed ?? '';
    signatureMode.value = props.signature?.method === 5 || props.signature?.typed ? 'type' : 'draw';
};

const saveSignature = () => {
    saveError.value = '';

    if (signatureMode.value === 'draw') {
        if (!signaturePadRef.value) {
            saveError.value = 'Signature pad is not ready. Please try again.';

            return;
        }
        const { isEmpty, data } = signaturePadRef.value.saveSignature();
        if (isEmpty) {
            saveError.value = 'Please draw your signature before saving.';

            return;
        }
        signForm.signature_data = data;
        signForm.signature_method = 'draw';
    } else {
        const typed = typedSignature.value.trim();
        if (!typed) {
            saveError.value = 'Please type your signature before saving.';

            return;
        }
        signForm.signature_data = typed;
        signForm.signature_method = 'type';
    }

    signForm.put(route('users.signature.update', props.userId), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
        onError: () => {
            saveError.value = 'Could not save signature. Please try again.';
        },
    });
};

const removeSignature = () => {
    if (!confirm('Remove saved signature?')) {
        return;
    }

    router.delete(route('users.signature.destroy', props.userId), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = true;
            typedSignature.value = '';
            saveError.value = '';
        },
    });
};
</script>

<template>
    <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Saved signature</h4>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Used to auto-fill signatures on forms you complete in Helmful.
                </p>
            </div>
            <div v-if="canEdit && !editing && (signature.url || signature.typed)" class="flex gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900"
                    @click="startEditing"
                >
                    Update
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-transparent px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                    @click="removeSignature"
                >
                    Remove
                </button>
            </div>
        </div>

        <div
            v-if="!editing && (signature.url || signature.typed)"
            class="signature-preview rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-500 dark:bg-gray-100"
        >
            <div v-if="signature.url" class="flex justify-center">
                <img :src="signature.url" alt="Saved signature" class="max-h-28 w-auto" />
            </div>
            <div v-else-if="signature.typed" class="flex justify-center py-2">
                <p class="signature-cursive text-4xl text-gray-900">{{ signature.typed }}</p>
            </div>
            <p v-if="signature.saved_at" class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                Saved {{ new Date(signature.saved_at).toLocaleString() }}
            </p>
        </div>

        <div v-else-if="canEdit" class="space-y-4">
            <div v-if="saveError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ saveError }}
            </div>

            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium transition-colors',
                        signatureMode === 'draw'
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                    ]"
                    @click="signatureMode = 'draw'"
                >
                    Draw
                </button>
                <button
                    type="button"
                    :class="[
                        'border-l border-gray-300 px-4 py-2 text-sm font-medium transition-colors dark:border-gray-600',
                        signatureMode === 'type'
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                    ]"
                    @click="signatureMode = 'type'"
                >
                    Type
                </button>
            </div>

            <div v-show="signatureMode === 'draw'">
                <div class="signature-preview relative overflow-hidden rounded-lg border-2 border-gray-300 bg-white dark:border-gray-500 dark:bg-gray-100">
                    <VueSignaturePad
                        ref="signaturePadRef"
                        width="100%"
                        height="180px"
                        :options="signaturePadOptions"
                    />
                    <div class="pointer-events-none absolute bottom-4 left-4 right-4 border-b border-gray-300" />
                </div>
                <div class="mt-2 flex gap-3 text-sm text-gray-500">
                    <button type="button" class="hover:text-gray-700 dark:hover:text-gray-300" @click="undoSignature">Undo</button>
                    <button type="button" class="hover:text-gray-700 dark:hover:text-gray-300" @click="clearSignature">Clear</button>
                </div>
            </div>

            <div v-show="signatureMode === 'type'">
                <input
                    v-model="typedSignature"
                    type="text"
                    :placeholder="displayName || 'Type your full name'"
                    class="input-style w-full dark:bg-gray-900 dark:text-white"
                />
                <div
                    v-if="typedSignature.trim()"
                    class="signature-preview mt-3 flex justify-center rounded-lg border border-gray-200 bg-white px-4 py-6 dark:border-gray-500 dark:bg-gray-100"
                >
                    <p class="signature-cursive text-4xl text-gray-900">{{ typedSignature }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" class="btn-primary" :disabled="signForm.processing" @click="saveSignature">
                    {{ signForm.processing ? 'Saving…' : 'Save signature' }}
                </button>
                <button
                    v-if="signature.url || signature.typed"
                    type="button"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900"
                    :disabled="signForm.processing"
                    @click="cancelEditing"
                >
                    Cancel
                </button>
            </div>
        </div>

        <p v-else-if="!signature.url && !signature.typed" class="text-sm text-gray-500 dark:text-gray-400">
            No signature saved.
        </p>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap');

.signature-cursive {
    font-family: 'Dancing Script', cursive;
}
</style>
