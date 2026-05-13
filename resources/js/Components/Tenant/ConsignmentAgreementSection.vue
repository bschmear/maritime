<script setup>
import { Link, router } from '@inertiajs/vue3';

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

const quickCreateDraft = () => {
    router.post(route('assetunits.consignment-agreement.store', props.record.id), {}, { preserveScroll: true });
};

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
    <div v-if="record.is_consignment" class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">Consignment agreement</h3>
                <p v-if="context.needs_agreement" class="mt-1 text-sm text-amber-800 dark:text-amber-300">
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
                    v-if="context.needs_agreement && !context.draft"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-amber-400 bg-white px-3 py-2 text-sm font-medium text-amber-950 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-100 dark:hover:bg-amber-900/60"
                    @click="quickCreateDraft"
                >
                    Quick draft
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
    </div>
</template>
