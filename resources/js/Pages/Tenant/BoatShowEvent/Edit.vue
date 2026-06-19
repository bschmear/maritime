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

function buildAutoDisplayName(boatShowName, year, booth) {
    const showName = String(boatShowName ?? '').trim();
    const yearPart = year !== null && year !== undefined && year !== '' ? String(year) : '';
    const boothPart = String(booth ?? '').trim();
    const parts = [showName, yearPart].filter((p) => p !== '');
    let name = parts.join(' ');
    if (boothPart !== '') {
        name = name !== '' ? `${name} — Booth ${boothPart}` : `Booth ${boothPart}`;
    }
    return name !== '' ? name : 'Boat show event';
}

const recipientIds = [...(props.record.recipients?.user_ids ?? [])].map((id) => Number(id)).filter((n) => n > 0);

const storedDisplayName = props.record.display_name ?? '';
const autoDisplayNameOnLoad = buildAutoDisplayName(
    parentShowLabel.value,
    props.record.year,
    props.record.booth,
);
const usesCustomDisplayName = storedDisplayName !== '' && storedDisplayName !== autoDisplayNameOnLoad;

const form = useForm({
    boat_show_id: props.record.boat_show_id,
    display_name: usesCustomDisplayName ? storedDisplayName : '',
    use_custom_display_name: usesCustomDisplayName ? 1 : 0,
    year: props.record.year ?? '',
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
        items.push({ label: parentShowLabel.value, href: parentShowHref.value });
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

        <div class="w-full p-4">
            <form id="boat-show-event-form" class="space-y-8 pb-28" @submit.prevent="submit">
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
            </form>

            <Teleport to="body">
                <div class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] backdrop-blur supports-[backdrop-filter]:bg-white/90 dark:border-gray-700 dark:bg-gray-900/95 dark:supports-[backdrop-filter]:bg-gray-900/90">
                    <div class="flex w-full items-center justify-end gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="form.processing"
                            @click="router.visit(showHref)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="boat-show-event-form"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ form.processing ? 'Saving…' : 'Save changes' }}
                        </button>
                    </div>
                </div>
            </Teleport>
        </div>
    </TenantLayout>
</template>
