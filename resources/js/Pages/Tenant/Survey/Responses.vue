<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserInitialsAvatar from '@/Components/Tenant/UserInitialsAvatar.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

function userDisplayName(user) {
    if (!user) return '';
    if (user.name != null && String(user.name).trim() !== '') {
        return String(user.name).trim();
    }
    return (
        (user.display_name && String(user.display_name).trim())
        || [user.first_name, user.last_name].filter(Boolean).join(' ').trim()
        || user.email
        || ''
    );
}

const props = defineProps({
    responses: {
        type: Object,
        required: true,
    },
    survey: {
        type: Object,
        default: null,
    },
    users: {
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
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">forum</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ responses.total.toLocaleString() }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses</p>
                </div>
            </div>
 
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 dark:text-purple-300 bg-purple-100 dark:bg-purple-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">label</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ getTypeConfig(survey.type).label }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Survey Type</p>
                </div>
            </div>
 
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg flex-shrink-0"
                    :class="survey.status
                        ? 'text-green-600 dark:text-green-300 bg-green-100 dark:bg-green-900'
                        : 'text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700'">
                    <span class="material-icons">check_circle</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ survey.status ? 'Active' : 'Draft' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                </div>
            </div>
 
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex-shrink-0">
                    <UserInitialsAvatar :name="userDisplayName(survey.user) || 'Unknown'" />
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ userDisplayName(survey.user) || 'Unknown' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                </div>
            </div>
        </div>
 
        
        <div class="relative bg-white dark:bg-gray-800 sm:rounded-lg overflow-hidden grow flex flex-col shadow-md">
            <div class="px-4">
                <div class="border-b border-gray-200 dark:border-gray-700 space-y-4">
 
                    
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white">{{ pageTitle }}</h5>
 
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 mt-3 sm:mt-0">
                            <template v-if="survey">
                                <a :href="route('surveysShow', { id: survey.uuid })"
                                    class="btn btn-outline sm flex items-center justify-center gap-2">
                                    <span class="material-icons text-base leading-none">poll</span>
                                    <span>View Survey</span>
                                </a>
                                <a :href="route('surveyResponses')"
                                    class="btn btn-outline sm flex items-center justify-center gap-2">
                                    <span class="material-icons text-base leading-none">list</span>
                                    <span>All Responses</span>
                                </a>
                            </template>
                            <template v-else>
                                <a :href="route('surveysIndex')"
                                    class="btn btn-outline sm flex items-center justify-center gap-2">
                                    <span class="material-icons text-base leading-none">arrow_back</span>
                                    <span>Back to Surveys</span>
                                </a>
                            </template>
                        </div>
                    </div>
 
                    
                    <div v-if="isAdmin" class="flex items-center gap-3 pb-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            Filter by Team Member:
                        </label>
                        <select
                            :value="filterUser"
                            @change="updateFilterUser($event.target.value)"
                            class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full sm:w-64 p-2.5">
                            <option value="all">All Team Members</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ userDisplayName(u) }}</option>
                        </select>
                    </div>
                </div>
            </div>
 
            
            <div class="overflow-x-auto grow flex flex-col">
 
                <div v-if="responses.data.length === 0"
                    class="bg-gray-50 dark:bg-gray-900 flex items-center justify-center grow">
                    <div class="text-center flex flex-col items-center p-8">
                        <span class="material-icons text-5xl text-gray-400 dark:text-gray-500 mb-4">inbox</span>
                        <h3 class="mb-3 text-lg font-bold text-gray-900 dark:text-white">No responses yet</h3>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ survey ? "This survey hasn't received any responses yet." : "No survey responses have been submitted yet." }}
                        </p>
                        <a v-if="survey" :href="route('surveysShow', { id: survey.uuid })" class="btn btn-primary inline-flex items-center gap-2">
                            <span class="material-icons text-base leading-none">visibility</span>
                            View Survey
                        </a>
                    </div>
                </div>
 
                <table v-else class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                    <thead class="text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
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
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="response in responses.data" :key="response.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
 
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'date') ?? 'N/A' }}
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(response.submitted_at, currentUser.timezone, 'time') ?? '' }}
                                    </span>
                                </div>
                            </td>
 
                            <td v-if="!survey" class="px-4 py-3">
                                <a :href="route('surveyResponsesByUuid', { id: response.survey.uuid })"
                                    class="font-medium text-blue-600 dark:text-blue-400 hover:underline underline-offset-2">
                                    {{ response.survey.title }}
                                </a>
                            </td>
 
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-medium"
                                    :class="getTypeConfig(response.survey.type).classes">
                                    {{ getTypeConfig(response.survey.type).label }}
                                </span>
                            </td>
 
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <template v-if="respondentName(response)">
                                        <UserInitialsAvatar :name="respondentName(response)" small />
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
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                    {{ response.email }}
                                </a>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic">No email</span>
                            </td>

                            <td class="px-4 py-3">
                                <template v-if="response.owner_type && response.owner_id">
                                    <a v-if="ownerConfig(response.owner_type)"
                                        :href="route(ownerConfig(response.owner_type).routeName, { id: response.owner_id })"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                        <span class="material-icons text-base leading-none">{{ ownerConfig(response.owner_type).icon }}</span>
                                        {{ response.owner_type }}
                                    </a>
                                    <span v-else class="text-gray-500 dark:text-gray-400">{{ response.owner_type }}</span>
                                </template>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic">Public</span>
                            </td>
 
                            
                            <td class="px-4 py-3">
                                <div v-if="response.assigned_to_user" class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                        {{ response.assigned_to_user.name.charAt(0) }}
                                    </div>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ response.assigned_to_user.name }}</span>
                                </div>
                                <span v-else class="text-gray-500 dark:text-gray-400 italic">Unassigned</span>
                            </td>
 
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a :href="route('surveyResponseShow', { sid: response.survey.uuid, rid: response.id })"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                        title="View Response">
                                        <span class="material-icons text-xl leading-none">visibility</span>
                                    </a>
 
                                    <button
                                        v-if="response.survey.type === 'lead' && !response.converted && response.email"
                                        @click="convertToLead(response.id)"
                                        class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 transition-colors"
                                        title="Convert to Lead">
                                        <span class="material-icons text-xl leading-none">person_add</span>
                                    </button>
 
                                    <span v-else-if="response.converted"
                                        class="text-green-600 dark:text-green-400"
                                        title="Already Converted">
                                        <span class="material-icons text-xl leading-none">check_circle</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
 
            
            <div v-if="responses.data.length > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
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
                                class="px-3 py-1 text-sm rounded border transition-colors"
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