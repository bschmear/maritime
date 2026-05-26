<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    context: {
        type: Object,
        required: true,
    },
    record: {
        type: Object,
        required: true,
    },
});

const showMarkConsignmentModal = ref(false);
const isMarkingConsignment = ref(false);

const quickCreateDraft = () => {
    if (props.context?.not_marked_consignment) {
        showMarkConsignmentModal.value = true;
        return;
    }
    router.post(route('assetunits.consignment-agreement.store', props.record.id), {}, { preserveScroll: true });
};

const cancelMarkConsignment = () => {
    showMarkConsignmentModal.value = false;
};

const confirmMarkConsignment = () => {
    isMarkingConsignment.value = true;
    router.post(
        route('assetunits.consignment-agreement.store', props.record.id),
        { mark_as_consignment: true },
        {
            preserveScroll: true,
            onFinish: () => {
                isMarkingConsignment.value = false;
                showMarkConsignmentModal.value = false;
            },
        },
    );
};

const markPreview = computed(() => props.context?.mark_consignment_preview ?? null);

const canConfirmMarkConsignment = computed(() => markPreview.value?.can_mark === true);

const markConsignmentOwnerName = computed(() => markPreview.value?.owner_name || 'the agreement owner');

const copyReviewLink = async () => {
    const url = props.context?.review_url;
    if (!url || !navigator.clipboard) {
        return;
    }
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // ignore
    }
};
</script>

<template>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">Consignment agreement</h3>
                <p v-if="context.not_marked_consignment" class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                    This unit is not marked as consignment. Create an agreement to mark it as consignment and start the signing flow.
                </p>
                <p v-else-if="context.needs_agreement" class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                    This unit is on consignment. Open the agreement editor to complete details, then share the public link for signature.
                </p>
                <p v-else class="mt-1 text-sm text-emerald-800 dark:text-emerald-300">
                    A signed consignment agreement is on file for this unit.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="context.needs_agreement && context.create_url"
                    :href="context.create_url"
                    class="inline-flex items-center gap-1 rounded-md bg-amber-700 px-3 py-2 text-sm font-medium text-white hover:bg-amber-800"
                >
                    New agreement
                </Link>
                <button
                    v-if="context.needs_agreement && !context.draft && !context.not_marked_consignment"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-amber-400 bg-white px-3 py-2 text-sm font-medium text-amber-950 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-100 dark:hover:bg-amber-900/60"
                    @click="quickCreateDraft"
                >
                    Quick draft
                </button>
                <button
                    v-if="context.not_marked_consignment"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-amber-700 px-3 py-2 text-sm font-medium text-white hover:bg-amber-800"
                    @click="quickCreateDraft"
                >
                    Mark as consignment &amp; create draft
                </button>
                <Link
                    v-if="context.needs_agreement && context.draft_edit_url"
                    :href="context.draft_edit_url"
                    class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                >
                    Continue editing draft
                </Link>
                <Link
                    v-if="!context.needs_agreement && context.signed_record_url"
                    :href="context.signed_record_url"
                    class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-900 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100"
                >
                    <span class="material-icons text-base">description</span>
                    View agreement record
                </Link>
                <a
                    v-if="context.signed_review_url && !context.needs_agreement"
                    :href="context.signed_review_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-900 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100"
                >
                    <span class="material-icons text-base">open_in_new</span>
                    View signed agreement
                </a>
                <a
                    v-if="context.review_url"
                    :href="context.review_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-100"
                >
                    <span class="material-icons text-base">open_in_new</span>
                    Open public page
                </a>
                <button
                    v-if="context.review_url"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-100"
                    @click="copyReviewLink"
                >
                    <span class="material-icons text-base">content_copy</span>
                    Copy link
                </button>
            </div>
        </div>

        <Modal :show="showMarkConsignmentModal" max-width="md" @close="cancelMarkConsignment">
            <div class="p-6">
                <h3 class="text-center text-lg font-medium text-gray-900 dark:text-white">Not marked as consignment</h3>
                <p class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                    This unit is not marked as consignment. Continuing will update this asset unit and create a draft agreement.
                </p>
                <div
                    v-if="canConfirmMarkConsignment"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <p class="font-medium">The asset unit will be updated to:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Consignment = Yes</li>
                        <li>Customer owned = Yes</li>
                        <li>Customer = {{ markConsignmentOwnerName }} (agreement owner)</li>
                    </ul>
                </div>
                <p
                    v-else
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    This unit needs a customer with a linked contact before you can mark it as consignment. Assign a customer on the unit, or use
                    <Link
                        v-if="context.create_url"
                        :href="context.create_url"
                        class="font-medium underline hover:no-underline"
                        @click="cancelMarkConsignment"
                    >
                        New agreement
                    </Link>
                    to choose an owner first.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="isMarkingConsignment || !canConfirmMarkConsignment"
                        @click="confirmMarkConsignment"
                    >
                        {{ isMarkingConsignment ? 'Creating…' : 'Mark as consignment & continue' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        :disabled="isMarkingConsignment"
                        @click="cancelMarkConsignment"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
