<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import BoatShowEventFormFields from '@/Components/Tenant/BoatShowEventFormFields.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormValidationToast } from '@/composables/useFormValidationToast';

const props = defineProps({
    recordType: { type: String, required: true },
    initialData: { type: Object, default: () => ({}) },
    extraRouteParams: { type: Object, default: () => ({}) },
    parentBoatShow: { type: Object, default: null },
    boatShowOptions: { type: Array, default: () => [] },
    recipientUserOptions: { type: Array, default: () => [] },
    duplicateSource: { type: Object, default: null },
});

const isNested = computed(() => props.parentBoatShow !== null);

const { validationSubmitOptions, showToast } = useFormValidationToast();

function initialRecipientIds() {
    const raw = props.initialData.recipient_user_ids;
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.map((id) => Number(id)).filter((n) => Number.isFinite(n) && n > 0);
}

const form = useForm({
    duplicate_from_event_id: props.initialData.duplicate_from_event_id ?? null,
    boat_show_id: props.initialData.boat_show_id ?? null,
    display_name: '',
    use_custom_display_name: 0,
    year: props.initialData.year ?? '',
    starts_at: props.initialData.starts_at ?? '',
    ends_at: props.initialData.ends_at ?? '',
    venue: props.initialData.venue ?? '',
    booth: props.initialData.booth ?? '',
    address_line_1: props.initialData.address_line_1 ?? '',
    address_line_2: props.initialData.address_line_2 ?? '',
    city: props.initialData.city ?? '',
    state: props.initialData.state ?? '',
    country: props.initialData.country ?? '',
    postal_code: props.initialData.postal_code ?? '',
    latitude: props.initialData.latitude ?? '',
    longitude: props.initialData.longitude ?? '',
    active: props.initialData.active ?? 1,
    auto_followup: props.initialData.auto_followup ?? 1,
    delay_amount: props.initialData.delay_amount ?? 1,
    delay_unit: props.initialData.delay_unit ?? 'days',
    recipient_user_ids: initialRecipientIds(),
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    if (props.parentBoatShow) {
        items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
        items.push({
            label: props.parentBoatShow.name,
            href: route('boat-shows.show', props.parentBoatShow.routeKey),
        });
    } else {
        items.push({ label: 'Boat Show Events', href: route('boat-show-events.index') });
    }

    items.push({ label: props.duplicateSource ? 'Duplicate event' : 'Create' });
    return items;
});

const cancelHref = computed(() =>
    props.parentBoatShow
        ? route('boat-shows.show', props.parentBoatShow.routeKey)
        : route('boat-show-events.index')
);

const boatShowShowHref = computed(() =>
    props.parentBoatShow ? route('boat-shows.show', props.parentBoatShow.routeKey) : null
);

const storeUrl = computed(() => route(`${props.recordType}.store`, props.extraRouteParams));

const submit = () => {
    if (!isNested.value && (form.boat_show_id === null || form.boat_show_id === '')) {
        form.setError('boat_show_id', 'Select a boat show.');
        showToast('error', 'Select a boat show.');
        return;
    }

    form
        .transform((data) => {
            const out = { ...data };
            out.active = out.active === true || out.active === 1 || out.active === '1' ? 1 : 0;
            out.auto_followup =
                out.auto_followup === true || out.auto_followup === 1 || out.auto_followup === '1' ? 1 : 0;
            out.use_custom_display_name =
                out.use_custom_display_name === true || out.use_custom_display_name === 1 || out.use_custom_display_name === '1'
                    ? 1
                    : 0;
            if (!out.use_custom_display_name) {
                out.display_name = '';
            }
            if (out.starts_at === '') {
                out.starts_at = null;
            }
            if (out.ends_at === '') {
                out.ends_at = null;
            }
            if (out.boat_show_id != null && out.boat_show_id !== '') {
                out.boat_show_id = Number(out.boat_show_id);
            }
            if (out.duplicate_from_event_id != null && out.duplicate_from_event_id !== '') {
                out.duplicate_from_event_id = Number(out.duplicate_from_event_id);
            } else {
                out.duplicate_from_event_id = null;
            }
            const numOrNull = (v) => {
                if (v === '' || v === null || v === undefined) {
                    return null;
                }
                const n = Number(v);

                return Number.isFinite(n) ? n : null;
            };
            out.latitude = numOrNull(out.latitude);
            out.longitude = numOrNull(out.longitude);
            out.delay_amount = Number(out.delay_amount) || 0;
            const rawIds = Array.isArray(out.recipient_user_ids) ? out.recipient_user_ids : [];
            out.recipient_user_ids = rawIds
                .map((id) => Number(id))
                .filter((n) => Number.isFinite(n) && n > 0);

            return out;
        })
        .post(storeUrl.value, validationSubmitOptions());
};

const fieldError = (key) => {
    const err = form.errors[key];
    return Array.isArray(err) ? err[0] : err;
};
</script>

<template>
    <Head title="Create Boat Show Event" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ duplicateSource ? 'Duplicate boat show event' : 'New boat show event' }}
                        </h2>
                        <p v-if="duplicateSource" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Copying settings from
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ duplicateSource.display_name }}</span>.
                            Dates are cleared; checklist, assets, and layout copy when you save.
                        </p>
                        <p v-else-if="parentBoatShow" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            For
                            <Link
                                :href="boatShowShowHref"
                                class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                {{ parentBoatShow.name }}
                            </Link>
                        </p>
                    </div>
                    <Link
                        v-if="boatShowShowHref"
                        :href="boatShowShowHref"
                        class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-[18px]">arrow_back</span>
                        Back to boat show
                    </Link>
                </div>
            </div>
        </template>

        <div class="w-full p-4">
            <form id="boat-show-event-form" class="space-y-8 pb-28" @submit.prevent="submit">
                <BoatShowEventFormFields
                    :form="form"
                    :field-error="fieldError"
                    :is-nested="isNested"
                    :parent-boat-show="parentBoatShow"
                    :boat-show-options="boatShowOptions"
                    :recipient-user-options="recipientUserOptions"
                    mode="create"
                    address-field-id="boat-show-event-create-address"
                />
            </form>

            <Teleport to="body">
                <div class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] backdrop-blur supports-[backdrop-filter]:bg-white/90 dark:border-gray-700 dark:bg-gray-900/95 dark:supports-[backdrop-filter]:bg-gray-900/90">
                    <div class="flex w-full items-center justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="form.processing"
                            @click="router.visit(cancelHref)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="boat-show-event-form"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing || (!isNested && !form.boat_show_id)"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ form.processing ? 'Saving…' : 'Create event' }}
                        </button>
                    </div>
                </div>
            </Teleport>
        </div>
    </TenantLayout>
</template>
