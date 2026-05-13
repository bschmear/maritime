<script setup>
import { watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

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

const emptyFields = () => ({
    agreement_date: '',
    boat_description: '',
    motor_description: '',
    other_description: '',
    boat_title_signed_delivered: false,
    owner_seller_name: '',
    owner_address: '',
    owner_phone_1: '',
    owner_phone_2: '',
    notes: '',
    asking_boat: '',
    asking_motor: '',
    asking_other: '',
    asking_sold: '',
    minimum_boat: '',
    minimum_motor: '',
    minimum_other: '',
    minimum_sold: '',
});

const form = useForm(emptyFields());

const syncFromDraft = () => {
    const d = props.context?.draft;
    if (!d) {
        form.reset();
        form.clearErrors();

        return;
    }
    form.agreement_date = d.agreement_date || '';
    form.boat_description = d.boat_description ?? '';
    form.motor_description = d.motor_description ?? '';
    form.other_description = d.other_description ?? '';
    form.boat_title_signed_delivered = !!d.boat_title_signed_delivered;
    form.owner_seller_name = d.owner_seller_name ?? '';
    form.owner_address = d.owner_address ?? '';
    form.owner_phone_1 = d.owner_phone_1 ?? '';
    form.owner_phone_2 = d.owner_phone_2 ?? '';
    form.notes = d.notes ?? '';
    form.asking_boat = d.asking_boat ?? '';
    form.asking_motor = d.asking_motor ?? '';
    form.asking_other = d.asking_other ?? '';
    form.asking_sold = d.asking_sold ?? '';
    form.minimum_boat = d.minimum_boat ?? '';
    form.minimum_motor = d.minimum_motor ?? '';
    form.minimum_other = d.minimum_other ?? '';
    form.minimum_sold = d.minimum_sold ?? '';
};

watch(
    () => props.context?.draft,
    () => {
        syncFromDraft();
    },
    { immediate: true, deep: true },
);

const createDraft = () => {
    router.post(route('assetunits.consignment-agreement.store', props.record.id), {}, { preserveScroll: true });
};

const saveDraft = () => {
    form.transform((data) => ({
        ...data,
        boat_title_signed_delivered: !!data.boat_title_signed_delivered,
    })).put(route('assetunits.consignment-agreement.update', props.record.id), { preserveScroll: true });
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
                    This unit is on consignment. Create a draft, complete the details, then share the customer link for signature.
                </p>
                <p v-else class="mt-1 text-sm text-emerald-800 dark:text-emerald-300">
                    A signed consignment agreement is on file for this unit.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="context.needs_agreement && !context.draft"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-amber-700 px-3 py-2 text-sm font-medium text-white hover:bg-amber-800"
                    @click="createDraft"
                >
                    Create agreement draft
                </button>
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

        <div v-if="context.draft && context.needs_agreement" class="mt-4 space-y-4 border-t border-amber-200 pt-4 dark:border-amber-800">
            <p class="text-xs font-medium uppercase tracking-wide text-amber-900/80 dark:text-amber-200/90">Dealer prefill (saved to the agreement)</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Date</label>
                    <input
                        v-model="form.agreement_date"
                        type="date"
                        class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input
                        id="boat_title_signed"
                        v-model="form.boat_title_signed_delivered"
                        type="checkbox"
                        class="h-4 w-4 rounded border-amber-300 text-amber-700"
                    />
                    <label for="boat_title_signed" class="text-sm text-amber-900 dark:text-amber-200">Boat title signed &amp; delivered</label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Boat</label>
                    <textarea v-model="form.boat_description" rows="2" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Motor</label>
                    <textarea v-model="form.motor_description" rows="2" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Other</label>
                    <textarea v-model="form.other_description" rows="2" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Owner / seller</label>
                    <input
                        v-model="form.owner_seller_name"
                        type="text"
                        class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Address</label>
                    <textarea v-model="form.owner_address" rows="2" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Phone 1</label>
                    <input v-model="form.owner_phone_1" type="text" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Phone 2</label>
                    <input v-model="form.owner_phone_2" type="text" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-amber-900 dark:text-amber-200">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-md border border-amber-200 px-3 py-2 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-amber-900 dark:text-amber-200">Asking price</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Boat $</label>
                            <input v-model="form.asking_boat" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Motor $</label>
                            <input v-model="form.asking_motor" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Other $</label>
                            <input v-model="form.asking_other" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Sold $</label>
                            <input v-model="form.asking_sold" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-amber-900 dark:text-amber-200">Minimum price</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Boat $</label>
                            <input v-model="form.minimum_boat" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Motor $</label>
                            <input v-model="form.minimum_motor" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Other $</label>
                            <input v-model="form.minimum_other" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-amber-800 dark:text-amber-300">Sold $</label>
                            <input v-model="form.minimum_sold" type="text" class="mt-0.5 w-full rounded-md border border-amber-200 px-2 py-1.5 text-sm dark:border-amber-800 dark:bg-gray-900 dark:text-white" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                    @click="saveDraft"
                >
                    Save draft
                </button>
            </div>
        </div>
    </div>
</template>
