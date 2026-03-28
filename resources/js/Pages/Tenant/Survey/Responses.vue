<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    responses: {
        type: Object,
        required: true,
    },
    survey: {
        type: Object,
        default: null,
    },
    teamUsers: {
        type: Array,
        default: () => [],
    },
    filterUser: {
        type: [String, Number],
        default: 'all',
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    currentUser: {
        type: Object,
        required: true,
    },
});

const pageTitle = computed(() =>
    props.survey ? `Responses: ${props.survey.title}` : 'All Survey Responses'
);

const breadcrumbItems = computed(() => {
    const items = [
        { label: 'Home', href: route('dashboard') },
        { label: 'Surveys', href: route('surveysIndex') },
    ];
    if (props.survey) {
        items.push({ label: props.survey.title, href: route('surveysShow', { id: props.survey.uuid }) });
        items.push({ label: 'Responses' });
    } else {
        items.push({ label: 'All Responses' });
    }
    return items;
});

const typeConfig = {
    lead:     { label: 'Lead',      classes: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' },
    feedback: { label: 'Feedback',  classes: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' },
    followup: { label: 'Follow Up', classes: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' },
};

const getTypeConfig = (type) =>
    typeConfig[type] ?? { label: 'Custom', classes: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300' };

const ownerConfig = (ownerType) => {
    const map = {
        Contact: { icon: 'fa-user',      routeName: 'dashShowContact' },
        Lead:    { icon: 'fa-user-plus',  routeName: 'dashShowLead' },
        Vendor:  { icon: 'fa-building',   routeName: 'dashShowVendor' },
    };
    return map[ownerType] ?? null;
};

const formatDate = (dateStr, timezone, format) => {
    if (!dateStr) return null;
    const tz = timezone ?? 'America/Chicago';
    return new Date(dateStr).toLocaleString('en-US', {
        timeZone: tz,
        ...(format === 'date'
            ? { month: 'short', day: 'numeric', year: 'numeric' }
            : { hour: 'numeric', minute: '2-digit' }),
    });
};

const respondentName = (r) => {
    const name = [r.first_name, r.last_name].filter(Boolean).join(' ');
    return name || null;
};

const updateFilterUser = (userId) => {
    router.get(
        route(props.survey ? 'surveyResponsesByUuid' : 'surveyResponses'),
        {
            ...(props.survey ? { id: props.survey.uuid } : {}),
            ...(userId !== 'all' ? { filteruser: userId } : {}),
        },
        { preserveState: true }
    );
};

const convertToLead = (responseId, convertRoute) => {
    router.post(convertRoute, { response_id: responseId });
};

const paginate = (url) => {
    if (url) router.get(url, {}, { preserveState: true });
};
</script>

<template>
    <Head :title="pageTitle" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div v-if="survey" class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300 flex-shrink-0">
                    <i class="fas fa-comments text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ responses.total.toLocaleString() }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300 flex-shrink-0">
                    <i class="fas fa-tag text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ getTypeConfig(survey.type).label }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Survey Type</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg flex-shrink-0"
                    :class="survey.status
                        ? 'text-green-600 bg-green-100 dark:bg-green-900 dark:text-green-300'
                        : 'text-gray-600 bg-gray-100 dark:bg-gray-900 dark:text-gray-300'">
                    <i class="fas fa-circle-check text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ survey.status ? 'Active' : 'Draft' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="avatar-wrap flex-shrink-0">
                    <avatar :name="survey.user?.name ?? 'Unknown'" />
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ survey.user?.name ?? 'Unknown' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                </div>
            </div>
        </div>

        <div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden grow flex flex-col shadow-md">
            <div class="px-4">
                <div class="border-b dark:border-gray-700 space-y-4">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b dark:border-gray-700">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white">{{ pageTitle }}</h5>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 gap-2 mt-3 sm:mt-0">
                            <template v-if="survey">
                                <a :href="route('surveysShow', { id: survey.uuid })"
                                    class="btn btn-outline sm flex items-center justify-center space-x-2">
                                    <i class="fas fa-poll-h"></i>
                                    <span>View Survey</span>
                                </a>
                                <a :href="route('surveyResponses')"
                                    class="btn btn-outline sm flex items-center justify-center space-x-2">
                                    <i class="fas fa-list"></i>
                                    <span>All Responses</span>
                                </a>
                            </template>
                            <template v-else>
                                <a :href="route('surveysIndex')"
                                    class="btn btn-outline sm flex items-center justify-center space-x-2">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Surveys</span>
                                </a>
                            </template>
                        </div>
                    </div>

                    <div v-if="isAdmin" class="flex flex-col sm:flex-row sm:items-center pt-4 pb-4">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Filter by Team Member:
                            </label>
                            <select
                                :value="filterUser"
                                @change="updateFilterUser($event.target.value)"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full sm:w-64 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="all">All Team Members</option>
                                <option v-for="u in teamUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>

            <div class="overflow-x-auto grow flex flex-col">

                <div v-if="responses.data.length === 0"
                    class="text-gray-700 bg-gray-50 dark:bg-gray-900 dark:text-gray-400 flex items-center justify-center grow">
                    <div class="text-center flex flex-col items-center p-8">
                        <i class="fas fa-inbox text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                        <h3 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">No responses yet</h3>
                        <p class="mb-4 text-gray-500 dark:text-gray-400">
                            {{ survey ? "This survey hasn't received any responses yet." : "No survey responses have been submitted yet." }}
                        </p>
                        <a v-if="survey" :href="route('surveysShow', { id: survey.uuid })" class="btn btn-primary inline-flex items-center">
                            <i class="fas fa-eye mr-2"></i>
                            View Survey
                        </a>
                    </div>
                </div>

                <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Submitted</th>
                            <th v-if="!survey" class="px-4 py-3 whitespace-nowrap">Survey</th>
                            <th class="px-4 py-3 whitespace-nowrap">Type</th>
                            <th class="px-4 py-3 whitespace-nowrap">Respondent</th>
                            <th class="px-4 py-3 whitespace-nowrap">Email</th>
                            <th class="px-4 py-3 whitespace-nowrap">Linked To</th>
                            <th class="px-4 py-3 whitespace-nowrap">Assigned To</th>
                            <th class="px-4 py-3 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="response in responses.data" :key="response.id"
                            class="border-b dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">

                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'date') ?? 'N/A' }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'time') ?? '' }}
                                    </span>
                                </div>
                            </td>

                            <td v-if="!survey" class="px-4 py-3">
                                <a :href="route('surveyResponsesByUuid', { id: response.survey.uuid })"
                                    class="font-medium text-blue-600 hover:underline underline-offset-2 dark:text-blue-400">
                                    {{ response.survey.title }}
                                </a>
                            </td>

                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="getTypeConfig(response.survey.type).classes">
                                    {{ getTypeConfig(response.survey.type).label }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <template v-if="respondentName(response)">
                                        <div class="avatar-wrap small mr-2">
                                            <avatar :name="respondentName(response)" />
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ respondentName(response) }}
                                        </span>
                                    </template>
                                    <span v-else class="text-gray-500 dark:text-gray-400 italic">Anonymous</span>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <a v-if="response.email"
                                    :href="`mailto:${response.email}`"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ response.email }}
                                </a>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic">No email</span>
                            </td>

                            <td class="px-4 py-3">
                                <template v-if="response.owner_type && response.owner_id">
                                    <template v-if="ownerConfig(response.owner_type)">
                                        <a :href="route(ownerConfig(response.owner_type).routeName, { id: response.owner_id })"
                                            target="_blank"
                                            class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            <i class="fas mr-1.5" :class="ownerConfig(response.owner_type).icon"></i>
                                            {{ response.owner_type }}
                                        </a>
                                    </template>
                                    <span v-else class="text-gray-500 dark:text-gray-400">{{ response.owner_type }}</span>
                                </template>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic">Public</span>
                            </td>

                            <td class="px-4 py-3">
                                <div v-if="response.assigned_to_user" class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-semibold mr-2">
                                        {{ response.assigned_to_user.name.charAt(0) }}
                                    </div>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ response.assigned_to_user.name }}</span>
                                </div>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic text-sm">Unassigned</span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-3">
                                    <a :href="route('surveyResponseShow', { sid: response.survey.uuid, rid: response.id })"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        title="View Response">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <button
                                        v-if="response.survey.type === 'lead' && !response.converted && response.email"
                                        @click="convertToLead(response.id, route('surveyResponseConvertToLead'))"
                                        class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300"
                                        title="Convert to Lead">
                                        <i class="fas fa-user-plus"></i>
                                    </button>

                                    <span
                                        v-else-if="response.converted"
                                        class="text-green-600 dark:text-green-400"
                                        title="Already Converted">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="responses.data.length > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row items-center justify-between">
                    <span class="text-sm text-gray-700 dark:text-gray-400 mb-3 sm:mb-0">
                        Showing
                        <span class="font-semibold text-gray-900 dark:text-white">{{ responses.from }}</span>
                        to
                        <span class="font-semibold text-gray-900 dark:text-white">{{ responses.to }}</span>
                        of
                        <span class="font-semibold text-gray-900 dark:text-white">{{ responses.total }}</span>
                        responses
                    </span>
                    <div class="flex gap-1">
                        <template v-for="link in responses.links" :key="link.label">
                            <button
                                v-if="link.url"
                                class="px-3 py-1 text-sm rounded border"
                                :class="link.active
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                @click="paginate(link.url)"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
