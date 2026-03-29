<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    survey: {
        type: Object,
        required: true,
    },
    response: {
        type: Object,
        default: null,
    },
    team: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
    currentUser: {
        type: Object,
        required: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    onTrial: {
        type: Boolean,
        default: false,
    },
    subscriptionLevel: {
        type: Number,
        default: 0,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys', href: route('surveysIndex') },
    { label: props.survey.title, href: route('surveysShow', { id: props.survey.uuid }) },
    { label: 'Response' },
]);

const showAiAnalysis = ref(!!props.response?.latest_ai_analysis);
const aiAnalysis = ref(props.response?.latest_ai_analysis ?? null);

const canReassign = computed(() =>
    props.isAdmin || props.response?.assigned_to === props.currentUser.id
);

const showReassign = computed(() =>
    canReassign.value && props.users.length > 1
);

const respondentName = computed(() => {
    if (!props.response) return null;
    const name = [props.response.first_name, props.response.last_name].filter(Boolean).join(' ');
    return name || null;
});

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-US', {
        timeZone: props.currentUser.timezone ?? 'America/Chicago',
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
};

const ownerConfig = computed(() => {
    if (!props.response?.owner_type) return null;
    const map = {
        Contact: { icon: 'fa-user',      routeName: 'dashShowContact' },
        Lead:    { icon: 'fa-user-plus',  routeName: 'dashShowLead' },
        Vendor:  { icon: 'fa-briefcase',  routeName: 'dashShowVendor' },
    };
    return map[props.response.owner_type] ?? null;
});

const getAnswerForQuestion = (questionId) => {
    return props.response?.answers?.find(a => a.survey_question_id === questionId) ?? null;
};

const formatAnswer = (answer) => {
    if (!answer?.answer) return null;
    if (Array.isArray(answer.answer)) return answer.answer.join(', ');
    return answer.answer;
};

const reassignResponse = (responseId, userId) => {
    if (!userId) return;
    router.patch(route('surveyResponseReassign', { id: responseId }), { assigned_to: userId });
};

const convertToLead = (responseId) => {
    router.post(route('surveyResponseConvertToLead'), { response_id: responseId });
};

const onAnalysisComplete = (analysis) => {
    aiAnalysis.value = analysis;
    showAiAnalysis.value = true;
};

const onSuggestionsApplied = () => {
    router.reload();
};
</script>

<template>
    <Head :title="`Survey Response - ${survey.title}`" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>
 
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-1">{{ survey.title }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Response Details</p>
                </div>
                <a :href="route('surveysShow', { id: survey.uuid })"
                    class="inline-flex items-center gap-2 text-sm px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <span class="material-icons text-base leading-none">arrow_back</span>
                    Back to Survey
                </a>
            </div>
        </div>
 
        <div v-if="!response" class="p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">Response not found.</p>
        </div>
 
        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
 
            <div class="space-y-6">
 
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-blue-600 dark:text-blue-400">person</span>
                        Respondent Info
                    </h3>
                    <ul class="text-sm space-y-3 text-gray-700 dark:text-gray-300">
                        <li><strong class="text-gray-900 dark:text-white">Name:</strong> {{ respondentName ?? 'Anonymous' }}</li>
                        <li><strong class="text-gray-900 dark:text-white">Email:</strong> {{ response.email ?? 'Anonymous' }}</li>
                        <li><strong class="text-gray-900 dark:text-white">Submitted:</strong> {{ formatDate(response.submitted_at ?? response.created_at) }}</li>
                        <li><strong class="text-gray-900 dark:text-white">IP Address:</strong> {{ response.ip_address ?? 'N/A' }}</li>
                        <li>
                            <strong class="text-gray-900 dark:text-white">User Agent:</strong>
                            <span class="text-gray-500 dark:text-gray-400 ml-1">{{ response.user_agent ?? 'N/A' }}</span>
                        </li>
 
                        <li v-if="response.owner_type && response.owner_id"
                            class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                            <strong class="text-gray-900 dark:text-white">Linked to:</strong>
                            <a v-if="ownerConfig"
                                :href="route(ownerConfig.routeName, { id: response.owner_id })"
                                target="_blank"
                                class="inline-flex items-center gap-1 ml-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                <span class="material-icons text-base leading-none">{{ ownerConfig.icon }}</span>
                                {{ response.owner_type }}
                                <span class="material-icons text-sm leading-none">open_in_new</span>
                            </a>
                            <span v-else class="ml-1">{{ response.owner_type }}</span>
                        </li>
 
                        <li v-if="response.deal">
                            <strong class="text-gray-900 dark:text-white">Transaction:</strong>
                            <a :href="route('dashShowDeal', { id: response.deal.id })"
                                target="_blank"
                                class="inline-flex items-center gap-1 ml-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                <span class="material-icons text-base leading-none">home</span>
                                {{ response.deal.title }}
                                <span class="material-icons text-sm leading-none">open_in_new</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-green-600 dark:text-green-400">task_alt</span>
                        Summary
                    </h3>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-3">
                        <li>
                            <strong class="text-gray-900 dark:text-white">Survey Type:</strong>
                            <span class="ml-1">{{ survey.type.charAt(0).toUpperCase() + survey.type.slice(1) }}</span>
                        </li>
                        <li class="flex items-center gap-2 flex-wrap">
                            <strong class="text-gray-900 dark:text-white">Survey Status:</strong>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-sm font-medium"
                                :class="survey.status
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'">
                                <span class="material-icons text-base leading-none">
                                    {{ survey.status ? 'check_circle' : 'schedule' }}
                                </span>
                                {{ survey.status ? 'Active' : 'Draft' }}
                            </span>
                        </li>
                    </ul>
                </div>
 
                <div v-if="showReassign" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-purple-600 dark:text-purple-400">manage_accounts</span>
                        Assigned To
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <template v-if="response.assigned_to_user">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold mr-3 flex-shrink-0">
                                    {{ response.assigned_to_user.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ response.assigned_to_user.name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ response.assigned_to_user.email }}</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center mr-3 flex-shrink-0">
                                    <span class="material-icons text-gray-500 dark:text-gray-400">person</span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Unassigned</p>
                            </template>
                        </div>
 
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reassign response to:
                            </label>
                            <select class="input-style" @change="reassignResponse(response.id, $event.target.value)">
                                <option value="">-- Select Team Member --</option>
                                <option
                                    v-for="member in users.filter(m => m.id !== (response.assigned_to ?? 0))"
                                    :key="member.id"
                                    :value="member.id">
                                    {{ member.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
 
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 dark:from-gray-800 dark:to-gray-900 border border-purple-200 dark:border-purple-800 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        AI Analysis
                    </h3>
                    <aianalysisbutton
                        :surveyresponseid="response.id"
                        :teamid="team.id"
                        :hasanalysis="!!response.latest_ai_analysis"
                        :ontrial="onTrial"
                        :subscriptionlevel="subscriptionLevel"
                        :upgradeurl="`${$page.props.appUrl}/settings/subscriptions`"
                        @analysiscomplete="onAnalysisComplete"
                        @showanalysis="showAiAnalysis = true"
                    />
                </div>
 
                <div v-if="survey.type === 'lead' && !response.converted && response.email"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-blue-600 dark:text-blue-400">person_add</span>
                        Actions
                    </h3>
                    <button
                        @click.prevent="convertToLead(response.id)"
                        class="inline-flex items-center justify-center gap-2 btn btn-blue w-full">
                        <span class="material-icons text-base leading-none">person_add</span>
                        Convert to Lead
                    </button>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Create a new lead from this survey response
                    </p>
                </div>
 
                <div v-else-if="survey.type === 'lead' && response.converted"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-center gap-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                        <span class="text-sm font-medium text-green-700 dark:text-green-300">Already Converted to Lead</span>
                    </div>
                </div>
 
                <surveyfollowupcard
                    :survey-response-id="response.id"
                    :team-id="team.id"
                    :scheduled-followup="response.scheduled_followup_email"
                />
            </div>
 
        
            <div class="lg:col-span-2 space-y-6">
 
                <aianalysisresults
                    v-if="showAiAnalysis && aiAnalysis"
                    :analysis="aiAnalysis"
                    :teamid="team.id"
                    :currentusername="currentUser.name"
                    :initially-collapsed="!response.latest_ai_analysis"
                    :response="response"
                    @close="showAiAnalysis = false"
                    @suggestionsapplied="onSuggestionsApplied"
                />
 
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="material-icons text-purple-600 dark:text-purple-400">format_list_bulleted</span>
                            Answers
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <template v-if="survey.questions?.length > 0">
                            <div v-for="(question, index) in survey.questions" :key="question.id"
                                class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">
                                    {{ index + 1 }}. {{ question.label }}
                                    <span v-if="question.required" class="text-red-500 ml-0.5">*</span>
                                </h4>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <span v-if="formatAnswer(getAnswerForQuestion(question.id))">
                                        {{ formatAnswer(getAnswerForQuestion(question.id)) }}
                                    </span>
                                    <span v-else class="italic text-gray-400 dark:text-gray-500">No response</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">
                            No questions found for this survey.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>