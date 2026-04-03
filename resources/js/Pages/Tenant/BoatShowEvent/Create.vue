<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import BoatShowEventFormFields from '@/Components/Tenant/BoatShowEventFormFields.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    recordType: { type: String, required: true },
    initialData: { type: Object, default: () => ({}) },
    extraRouteParams: { type: Object, default: () => ({}) },
    parentBoatShow: { type: Object, default: null },
    boatShowOptions: { type: Array, default: () => [] },
    recipientUserOptions: { type: Array, default: () => [] },
});

const isNested = computed(() => props.parentBoatShow !== null);

const form = useForm({
    boat_show_id: props.initialData.boat_show_id ?? null,
    display_name: '',
    year: new Date().getFullYear(),
    starts_at: '',
    ends_at: '',
    venue: '',
    booth: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    latitude: '',
    longitude: '',
    active: 1,
    auto_followup: 1,
    delay_amount: 1,
    delay_unit: 'days',
    recipient_user_ids: [],
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    if (props.parentBoatShow) {
        items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
        items.push({
            label: props.parentBoatShow.name,
            href: route('boat-shows.show', props.parentBoatShow.routeKey),
        });
        items.push({
            label: 'Events',
            href: route('boat-shows.events.index', props.extraRouteParams),
        });
    } else {
        items.push({ label: 'Boat Show Events', href: route('boat-show-events.index') });
    }

    items.push({ label: 'Create' });
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
        return;
    }

    form
        .transform((data) => {
            const out = { ...data };
            out.active = out.active === true || out.active === 1 || out.active === '1' ? 1 : 0;
            out.auto_followup =
                out.auto_followup === true || out.auto_followup === 1 || out.auto_followup === '1' ? 1 : 0;
            if (out.starts_at === '') {
                out.starts_at = null;
            }
            if (out.ends_at === '') {
                out.ends_at = null;
            }
            if (out.boat_show_id != null && out.boat_show_id !== '') {
                out.boat_show_id = Number(out.boat_show_id);
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
        .post(storeUrl.value, {
            preserveScroll: true,
        });
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
                            New boat show event
                        </h2>
                        <p v-if="parentBoatShow" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
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

        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <form class="space-y-8" @submit.prevent="submit">
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

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :disabled="form.processing"
                        @click="router.visit(cancelHref)"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing || (!isNested && !boatShowOptions.length)"
                    >
                        {{ form.processing ? 'Saving…' : 'Create event' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
