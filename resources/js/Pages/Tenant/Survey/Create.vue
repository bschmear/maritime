<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    team: {
        type: Object,
        default: null,
    },
    subscription: {
        type: Object,
        default: null,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys', href: route('surveysIndex') },
    { label: 'Create Survey' },
]);
</script>

<template>
    <Head title="Create Survey" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="container mx-auto px-4 py-6">
            <survey-creator
                :users="users"
                :team="team"
                :subscription="subscription"
                :initial-data="{}"
                :is-editing="false"
            />
        </div>
    </TenantLayout>
</template>
