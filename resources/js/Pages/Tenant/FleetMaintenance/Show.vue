<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    log: { type: Object, required: true },
});

const formatDate = (v) => {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatMoney = (n) => {
    if (n == null || n === '') return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(n));
};

const fleetTab = computed(() => (props.log.fleet?.type === 'trailer' ? 'trailers' : 'trucks'));

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Fleet', href: route('fleet.index', { tab: fleetTab.value }) },
    { label: 'Maintenance', href: route('fleet.maintenance.index') },
    { label: `Record #${props.log.id}` },
]);

const pageTitle = computed(() => {
    const unit = props.log.fleet?.display_name;
    return unit ? `${unit} · service` : `Maintenance #${props.log.id}`;
});
</script>

<template>
    <Head :title="pageTitle" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <h2 class="mt-4 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ pageTitle }}
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 p-4">
            <div
                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Performed</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatDate(log.performed_at) }}</p>
                </div>
                <dl class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fleet unit</dt>
                        <dd class="mt-1">
                            <Link
                                v-if="log.fleet"
                                :href="route('fleet.show', log.fleet.id)"
                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ log.fleet.display_name }}
                            </Link>
                            <span v-else class="text-gray-400">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">License plate</dt>
                        <dd class="mt-1 font-mono text-md text-gray-900 dark:text-white">
                            {{ log.fleet?.license_plate || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Maintenance type(s)</dt>
                        <dd class="mt-1 text-md text-gray-900 dark:text-white">
                            <template v-if="(log.maintenance_types || []).length">
                                <ul class="list-inside list-disc space-y-1">
                                    <li v-for="t in log.maintenance_types" :key="t.id">
                                        {{ t.display_name }}
                                        <span v-if="t.category" class="text-sm text-gray-500 dark:text-gray-400"> — {{ t.category }}</span>
                                    </li>
                                </ul>
                            </template>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost</dt>
                        <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ formatMoney(log.cost) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mileage</dt>
                        <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ log.mileage != null ? log.mileage : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Hours</dt>
                        <dd class="mt-1 text-md text-gray-900 dark:text-white">{{ log.hours != null ? log.hours : '—' }}</dd>
                    </div>
                </dl>
                <div v-if="log.notes" class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</p>
                    <p class="mt-2 whitespace-pre-wrap text-md text-gray-800 dark:text-gray-200">{{ log.notes }}</p>
                </div>
            </div>

            <div class="flex gap-3">
                <Link
                    v-if="log.fleet"
                    :href="route('fleet.show', log.fleet.id)"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-md font-medium text-white hover:bg-primary-700"
                >
                    <span class="material-icons text-lg">local_shipping</span>
                    Back to unit
                </Link>
                <Link
                    :href="route('fleet.maintenance.index')"
                    class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-md font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    All reports
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
