<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import Form from '@/Components/Tenant/Form.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    recordType: { type: String, required: true },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    imageUrls: { type: Object, default: () => ({}) },
    account: { type: Object, default: null },
    timezones: { type: Array, default: () => [] },
    extraRouteParams: { type: Object, default: () => ({}) },
});

const isNested = computed(() => Object.keys(props.extraRouteParams).length > 0);

const eventKey = computed(() => props.record.id);

/** Parent show from loaded relation (`show`) or legacy `boat_show` key */
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

const handleCancel = () => {
    router.visit(showHref.value);
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
                        <p
                            v-if="record.display_name"
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
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

        <div class="mx-auto flex w-full max-w-4xl flex-col space-y-6">
            <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800 sm:rounded-lg">
                <Form
                    :schema="formSchema"
                    :fields-schema="fieldsSchema"
                    :record="record"
                    :enum-options="enumOptions"
                    :image-urls="imageUrls"
                    :account="account"
                    :timezones="timezones"
                    :extra-route-params="extraRouteParams"
                    :record-type="recordType"
                    record-title="Boat Show Event"
                    mode="edit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </TenantLayout>
</template>
