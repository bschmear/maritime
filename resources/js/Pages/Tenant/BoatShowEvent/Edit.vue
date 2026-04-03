<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import BoatShowEventFormFields from '@/Components/Tenant/BoatShowEventFormFields.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    extraRouteParams: { type: Object, default: () => ({}) },
    recipientUserOptions: { type: Array, default: () => [] },
});

const isNested = computed(() => Object.keys(props.extraRouteParams).length > 0);

const eventKey = computed(() => props.record.id);

const parentShow = computed(() => props.record.boat_show ?? props.record.show ?? null);

const parentShowLabel = computed(() => {
    const p = parentShow.value;
    if (!p) return '';
    return p.display_name ?? p.name ?? 'Boat show';
});

const parentShowHref = computed(() => {
    const p = parentShow.value;
    if (!p) return null;
    const key = p.slug ?? p.id;

    return route('boat-shows.show', key);
});

const indexHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.index', props.extraRouteParams)
        : route('boat-show-events.index')
);

const showHref = computed(() =>
    isNested.value
        ? route('boat-shows.events.show', { ...props.extraRouteParams, event: eventKey.value })
        : route('boat-show-events.show', eventKey.value)
);

const updateUrl = computed(() =>
    isNested.value
        ? route('boat-shows.events.update', { ...props.extraRouteParams, event: eventKey.value })
        : route('boat-show-events.update', eventKey.value)
);

function dateInput(val) {
    if (!val) return '';
    if (typeof val === 'string') {
        return val.length >= 10 ? val.slice(0, 10) : val;
    }
    return '';
}

const recipientIds = [...(props.record.recipients?.user_ids ?? [])].map((id) => Number(id)).filter((n) => n > 0);

const form = useForm({
    boat_show_id: props.record.boat_show_id,
    display_name: props.record.display_name ?? '',
    year: props.record.year ?? new Date().getFullYear(),
    starts_at: dateInput(props.record.starts_at),
    ends_at: dateInput(props.record.ends_at),
    venue: props.record.venue ?? '',
    booth: props.record.booth ?? '',
    address_line_1: props.record.address_line_1 ?? '',
    address_line_2: props.record.address_line_2 ?? '',
    city: props.record.city ?? '',
    state: props.record.state ?? '',
    country: props.record.country ?? '',
    postal_code: props.record.postal_code ?? '',
    latitude: props.record.latitude ?? '',
    longitude: props.record.longitude ?? '',
    active: props.record.active ? 1 : 0,
    auto_followup: props.record.auto_followup !== false && props.record.auto_followup !== 0 ? 1 : 0,
    delay_amount: props.record.delay_amount ?? 1,
    delay_unit: props.record.delay_unit ?? 'days',
    recipient_user_ids: recipientIds,
});

const breadcrumbItems = computed(() => {
    const items = [{ label: 'Home', href: route('dashboard') }];

    if (isNested.value && parentShow.value) {
        items.push({ label: 'Boat Shows', href: route('boat-shows.index') });
        items.push({
            label: parentShowLabel.value,
            href: parentShowHref.value,
        });
        items.push({ label: 'Events', href: indexHref.value });
    } else {
        items.push({ label: 'Boat Show Events', href: indexHref.value });
    }

    items.push({
        label: props.record.display_name ?? `Event #${props.record.id}`,
        href: showHref.value,
    });
    items.push({ label: 'Edit' });

    return items;
});

const parentBoatShowForForm = computed(() =>
    parentShow.value
        ? {
              id: parentShow.value.id,
              name: parentShowLabel.value,
              routeKey: parentShow.value.slug ?? parentShow.value.id,
          }
        : null
);

const submit = () => {
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
        .put(updateUrl.value, {
            preserveScroll: true,
        });
};

const fieldError = (key) => {
    const err = form.errors[key];
    return Array.isArray(err) ? err[0] : err;
};
</script>

<template>
    <Head :title="`Edit ${record.display_name ?? 'boat show event'}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Edit boat show event
                        </h2>
                        <p v-if="record.display_name" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ record.display_name }}
                        </p>
                    </div>
                    <Link
                        :href="showHref"
                        class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-[18px]">arrow_back</span>
                        Back to event
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto flex w-full max-w-4xl flex-col space-y-6 p-4">
            <form class="space-y-8" @submit.prevent="submit">
                <BoatShowEventFormFields
                    :form="form"
                    :field-error="fieldError"
                    :is-nested="isNested"
                    :parent-boat-show="parentBoatShowForForm"
                    :boat-show-options="[]"
                    :recipient-user-options="recipientUserOptions"
                    mode="edit"
                    address-field-id="boat-show-event-edit-address"
                />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :disabled="form.processing"
                        @click="router.visit(showHref)"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
