<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    survey: {
        type: Object,
        required: true,
    },
    weeklyResponses: {
        type: Number,
        default: 0,
    },
    completionRate: {
        type: Number,
        default: 0,
    },
    avgRating: {
        type: [Number, String],
        default: null,
    },
    teamUsers: {
        type: Array,
        default: () => [],
    },
    currentUser: {
        type: Object,
        required: true,
    },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys', href: route('surveysIndex') },
    { label: props.survey.title },
]);

const hideQuestions = ref(false);

const typeColorClass = computed(() => {
    const map = {
        feedback: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        lead: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        followup: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
    return map[props.survey.type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
});

const questionTypeIcon = (type) => {
    const map = {
        text: 'fa-font',
        multiple_choice: 'fa-list-ul',
        rating: 'fa-star',
        dropdown: 'fa-caret-square-down',
        nps: 'fa-chart-line',
    };
    return map[type] ?? 'fa-question';
};

const deliveryIcon = (method) => {
    const map = { email: 'fa-envelope', sms: 'fa-sms' };
    return map[method] ?? 'fa-code';
};

const privacy = computed(() => props.survey.privacy_settings ?? {});

const sortedQuestions = computed(() =>
    [...(props.survey.questions ?? [])].sort((a, b) => a.order - b.order)
);

const recentResponses = computed(() =>
    (props.survey.responses ?? []).slice(0, 5)
);
</script>

<template>
    <Head :title="survey.title" />
    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
            </div>
        </template>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h1 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ survey.title }}
                            </h1>

                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="survey.status
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'">
                                <i class="fas mr-1.5" :class="survey.status ? 'fa-check-circle' : 'fa-clock'"></i>
                                {{ survey.status ? 'Active' : 'Draft' }}
                            </span>

                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="typeColorClass">
                                {{ survey.type.charAt(0).toUpperCase() + survey.type.slice(1) }}
                            </span>
                        </div>

                        <p v-if="survey.description" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ survey.description }}
                        </p>

                        <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center">
                                <i class="fas fa-user w-4 mr-1.5"></i>
                                {{ survey.user?.name ?? 'Unknown' }}
                            </span>
                            <span class="inline-flex items-center">
                                <i class="fas fa-calendar w-4 mr-1.5"></i>
                                {{ new Date(survey.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                            </span>
                            <span class="inline-flex items-center">
                                <i class="fas fa-comments w-4 mr-1.5"></i>
                                {{ survey.responses_count ?? survey.responses?.length ?? 0 }} responses
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a :href="route('surveysEdit', { id: survey.uuid })"
                            class="text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700">
                            <i class="fas fa-edit mr-2"></i>Edit
                        </a>

                        <surveystatustoggle
                            :status="survey.status"
                            :updateroute="route('surveysUpdate', { id: survey.uuid })"
                        />

                        <surveyactions
                            :status="survey.status ? 1 : 0"
                            :deleteroute="route('surveysDestroy', { id: survey.uuid })"
                            :surveysindex="route('surveysIndex')"
                            :surveysupdate="route('surveysUpdate', { id: survey.uuid })"
                            :surveysclone="route('surveysClone', { id: survey.uuid })"
                            :uuid="survey.uuid"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 bg-blue-100 rounded-lg dark:bg-blue-900 dark:text-blue-300 flex-shrink-0">
                    <i class="fas fa-comments text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">
                        {{ (survey.responses_count ?? survey.responses?.length ?? 0).toLocaleString() }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-green-600 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-300 flex-shrink-0">
                    <i class="fas fa-calendar-week text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ weeklyResponses }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This Week</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 bg-purple-100 rounded-lg dark:bg-purple-900 dark:text-purple-300 flex-shrink-0">
                    <i class="fas fa-clipboard-check text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ completionRate }}%</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Completion Rate</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-center w-12 h-12 text-yellow-600 bg-yellow-100 rounded-lg dark:bg-yellow-900 dark:text-yellow-300 flex-shrink-0">
                    <i class="fas fa-star text-xl"></i>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ avgRating ?? 'N/A' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg. Rating</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="sm:flex space-y-2 sm:space-y-0 items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-question-circle text-blue-600 dark:text-blue-500 mr-2"></i>
                                Questions ({{ sortedQuestions.length }})
                            </h2>
                            <div class="flex space-x-2 divide-x divide-gray-300 dark:divide-gray-600">
                                <a :href="route('surveysEdit', { id: survey.uuid })"
                                    class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">
                                    Edit Questions
                                </a>
                                <button @click="hideQuestions = !hideQuestions"
                                    class="pl-2 text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-500 dark:hover:text-white">
                                    <i class="fas mr-1" :class="hideQuestions ? 'fa-eye-slash' : 'fa-eye'"></i>
                                    {{ hideQuestions ? 'Show Questions' : 'Hide Questions' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6" v-show="!hideQuestions">
                        <div v-if="sortedQuestions.length > 0" class="space-y-4">
                            <div v-for="(question, index) in sortedQuestions" :key="question.id"
                                class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300">
                                                {{ index + 1 }}
                                            </span>
                                            <h3 class="font-medium text-gray-900 dark:text-white">{{ question.label }}</h3>
                                            <span v-if="question.required" class="text-red-500 text-sm">*</span>
                                        </div>
                                        <div class="ml-8">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                                                <i class="fas mr-1" :class="questionTypeIcon(question.type)"></i>
                                                {{ question.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
                                            </span>
                                            <div v-if="question.options?.length > 0" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="font-medium">Options:</span>
                                                {{ question.options.slice(0, 3).join(', ') }}
                                                <span v-if="question.options.length > 3" class="text-gray-400">
                                                    +{{ question.options.length - 3 }} more
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-question-circle text-5xl mb-3"></i>
                            <p class="text-sm">No questions added yet</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-chart-bar text-green-600 dark:text-green-500 mr-2"></i>
                                Recent Responses
                            </h2>
                            <a v-if="(survey.responses_count ?? survey.responses?.length ?? 0) > 0"
                                :href="route('surveyResponsesByUuid', { id: survey.uuid })"
                                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">
                                View All
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="recentResponses.length > 0" class="space-y-3">
                            <a v-for="response in recentResponses" :key="response.id"
                                :href="route('surveyResponseShow', { sid: survey.uuid, rid: response.id })"
                                class="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-10 h-10 bg-gray-200 rounded-full dark:bg-gray-600 flex-shrink-0">
                                        <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate dark:text-white">
                                            {{ response.email ?? 'Anonymous' }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ response.created_at_human ?? response.created_at }}
                                        </p>
                                    </div>
                                </div>
                                <div class="inline-flex items-center p-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-lg dark:bg-gray-800 dark:text-white dark:border-gray-600">
                                    <i class="fas fa-eye"></i>
                                </div>
                            </a>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-5xl mb-3"></i>
                            <p class="text-sm">No responses yet</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">

                <survey-links
                    v-if="survey.status"
                    :base-url="survey.public_url"
                    :team-users="teamUsers"
                    :current-user-id="currentUser.id"
                    :current-user-name="currentUser.name"
                    :visibility="survey.visibility ?? 'public'"
                />

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-paper-plane text-green-600 dark:text-green-500 mr-2"></i>
                        Delivery & Automation
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Delivery Method</span>
                            <span class="inline-flex items-center font-medium text-gray-900 dark:text-white">
                                <i class="fas mr-1.5" :class="deliveryIcon(survey.delivery_method)"></i>
                                {{ (survey.delivery_method ?? 'email').charAt(0).toUpperCase() + (survey.delivery_method ?? 'email').slice(1) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-sm border-t border-gray-200 dark:border-gray-700">
                            <span class="text-gray-500 dark:text-gray-400">Automation</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ (survey.automation_trigger ?? 'manual').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
                            </span>
                        </div>
                        <div v-if="survey.automation_config?.days && survey.automation_trigger !== 'manual'"
                            class="flex items-center justify-between py-2 text-sm border-t border-gray-200 dark:border-gray-700">
                            <span class="text-gray-500 dark:text-gray-400">Send After</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ survey.automation_config.days }} days</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-shield-alt text-purple-600 dark:text-purple-500 mr-2"></i>
                        Privacy Settings
                    </h3>
                    <ul class="space-y-3 text-sm">
                        <li v-for="(label, key) in {
                            anonymous: 'Anonymous Responses',
                            require_email: 'Require Email',
                            one_response_per_user: 'One Response Per User',
                            show_results: 'Show Results',
                        }" :key="key" class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ label }}</span>
                            <i class="fas"
                                :class="privacy[key]
                                    ? 'fa-check-circle text-green-500 dark:text-green-400'
                                    : 'fa-times-circle text-gray-300 dark:text-gray-600'">
                            </i>
                        </li>
                    </ul>
                </div>

                <div v-if="survey.thank_you_message || survey.redirect_url"
                    class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-heart text-red-600 dark:text-red-500 mr-2"></i>
                        Completion Settings
                    </h3>
                    <div class="space-y-4">
                        <div v-if="survey.thank_you_message">
                            <label class="block mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Thank You Message</label>
                            <p class="text-sm text-gray-900 dark:text-white">{{ survey.thank_you_message }}</p>
                        </div>
                        <div v-if="survey.redirect_url">
                            <label class="block mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Redirect URL</label>
                            <a :href="survey.redirect_url" target="_blank"
                                class="inline-flex items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-500 break-all">
                                {{ survey.redirect_url }}
                                <i class="fas fa-external-link-alt ml-1.5 text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </TenantLayout>
</template>
