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
        required: true,
    },
    teamUsers: {
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
    canReassign.value && props.teamUsers.length > 1
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
                    class="inline-flex items-center text-sm px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Survey
                </a>
            </div>
        </div>

        <div v-if="!response" class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 text-center">
            <p class="text-gray-500 dark:text-gray-400">Response not found.</p>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="space-y-6">

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-user text-blue-600 dark:text-blue-500 mr-2"></i>
                        Respondent Info
                    </h3>
                    <ul class="text-sm space-y-3 text-gray-700 dark:text-gray-300">
                        <li><strong>Name:</strong> {{ respondentName ?? 'Anonymous' }}</li>
                        <li><strong>Email:</strong> {{ response.email ?? 'Anonymous' }}</li>
                        <li><strong>Submitted:</strong> {{ formatDate(response.submitted_at ?? response.created_at) }}</li>
                        <li><strong>IP Address:</strong> {{ response.ip_address ?? 'N/A' }}</li>
                        <li>
                            <strong>User Agent:</strong>
                            <span class="text-gray-500">{{ response.user_agent ?? 'N/A' }}</span>
                        </li>

                        {{-- Linked Owner --}}
                        <li v-if="response.owner_type && response.owner_id"
                            class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-600">
                            <strong>Linked to:</strong>
                            <a v-if="ownerConfig"
                                :href="route(ownerConfig.routeName, { id: response.owner_id })"
                                target="_blank"
                                class="inline-flex items-center ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                <i class="fas mr-1.5" :class="ownerConfig.icon"></i>
                                {{ response.owner_type }}
                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                            <span v-else class="ml-1">{{ response.owner_type }}</span>
                        </li>

                        {{-- Deal / Transaction --}}
                        <li v-if="response.deal">
                            <strong>Transaction:</strong>
                            <a :href="route('dashShowDeal', { id: response.deal.id })"
                                target="_blank"
                                class="inline-flex items-center ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                <i class="fas fa-home mr-1.5"></i>
                                {{ response.deal.title }}
                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-clipboard-check text-green-600 dark:text-green-500 mr-2"></i>
                        Summary
                    </h3>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-3">
                        <li>
                            <strong>Survey Type:</strong>
                            {{ survey.type.charAt(0).toUpperCase() + survey.type.slice(1) }}
                        </li>
                        <li class="flex items-center gap-2">
                            <strong>Survey Status:</strong>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="survey.status
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'">
                                <i class="fas mr-1.5" :class="survey.status ? 'fa-check-circle' : 'fa-clock'"></i>
                                {{ survey.status ? 'Active' : 'Draft' }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div v-if="showReassign"
                    class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-user-edit text-purple-600 dark:text-purple-500 mr-2"></i>
                        Assigned To
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <template v-if="response.assigned_to_user">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold mr-3">
                                    {{ response.assigned_to_user.name.charAt(0) }}
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ response.assigned_to_user.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ response.assigned_to_user.email }}</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="w-10 h-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unassigned</p>
                            </template>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reassign response to:
                            </label>
                            <select
                                class="input-style"
                                @change="reassignResponse(response.id, $event.target.value)">
                                <option value="">-- Select Team Member --</option>
                                <option
                                    v-for="member in teamUsers.filter(m => m.id !== (response.assigned_to ?? 0))"
                                    :key="member.id"
                                    :value="member.id">
                                    {{ member.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-blue-50 dark:from-gray-800 dark:to-gray-900 border border-purple-200 dark:border-purple-900 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-user-plus text-blue-600 dark:text-blue-500 mr-2"></i>
                        Actions
                    </h3>
                    <button
                        @click.prevent="convertToLead(response.id)"
                        class="inline-flex items-center justify-center btn btn-blue w-full">
                        <i class="fas fa-user-plus mr-2"></i>
                        Convert to Lead
                    </button>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Create a new lead from this survey response
                    </p>
                </div>

                <div v-else-if="survey.type === 'lead' && response.converted"
                    class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
                        <span class="text-sm font-medium text-green-700 dark:text-green-300">
                            Already Converted to Lead
                        </span>
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

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-list-alt text-purple-600 dark:text-purple-500 mr-2"></i>
                            Answers
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <template v-if="survey.questions?.length > 0">
                            <div v-for="(question, index) in survey.questions" :key="question.id"
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-1">
                                    {{ index + 1 }}. {{ question.label }}
                                    <span v-if="question.required" class="text-red-500">*</span>
                                </h4>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <span v-if="formatAnswer(getAnswerForQuestion(question.id))">
                                        {{ formatAnswer(getAnswerForQuestion(question.id)) }}
                                    </span>
                                    <span v-else class="italic text-gray-400">No response</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-gray-500 dark:text-gray-400 text-center py-6">
                            No questions found for this survey.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
