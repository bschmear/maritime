<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    survey: {
        type: Object,
        required: true,
    },
    teamUsers: {
        type: Array,
        default: () => [],
    },
    team: {
        type: Object,
        required: true,
    },
    subscription: {
        type: Object,
        default: null,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys', href: route('surveysIndex') },
    { label: props.survey.title, href: route('surveysShow', { id: props.survey.uuid }) },
    { label: 'Edit' },
]);
</script>

<template>
    <Head :title="`Edit Survey - ${survey.title}`" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="container mx-auto px-4 py-6">
            <survey-creator
                :users="teamUsers"
                :team="team"
                :subscription="subscription"
                :initial-data="survey"
                :is-editing="true"
                :survey-id="survey.uuid"
            />
        </div>
    </TenantLayout>
</template>
