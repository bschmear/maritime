<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import UserInitialsAvatar from '@/Components/Tenant/UserInitialsAvatar.vue';
import SurveyStatusToggle from '@/Components/surveys/SurveyStatusToggle.vue';
import SurveyActions from '@/Components/surveys/SurveyActions.vue';
import SurveyLinks from '@/Components/surveys/SurveyLinks.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    survey: { type: Object, required: true },
    weeklyResponses: { type: Number, default: 0 },
    completionRate: { type: Number, default: 0 },
    avgRating: { type: [Number, String], default: null },
    users: { type: Array, default: () => [] },
    currentUser: { type: Object, required: true },
});

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Surveys', href: route('surveysIndex') },
    { label: props.survey.title },
]);

const hideQuestions = ref(false);

const typeColorClass = computed(() => {
    const map = {
        feedback: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        lead:     'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        followup: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    };
    return map[props.survey.type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
});

const questionTypeIcon = (type) => {
    const map = {
        text:            'text_fields',
        multiple_choice: 'list',
        rating:          'star',
        dropdown:        'arrow_drop_down_circle',
        nps:             'trending_up',
    };
    return map[type] ?? 'help_outline';
};

const deliveryIcon = (method) => {
    const map = { email: 'email', sms: 'sms' };
    return map[method] ?? 'code';
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

        <!-- Survey Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h1 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ survey.title }}
                            </h1>

                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-sm font-medium"
                                :class="survey.status
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'">
                                <span class="material-icons text-base leading-none">
                                    {{ survey.status ? 'check_circle' : 'schedule' }}
                                </span>
                                {{ survey.status ? 'Active' : 'Draft' }}
                            </span>

                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium"
                                :class="typeColorClass">
                                {{ survey.type.charAt(0).toUpperCase() + survey.type.slice(1) }}
                            </span>
                        </div>

                        <p v-if="survey.description" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ survey.description }}
                        </p>

                        <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="material-icons text-base leading-none">person</span>
                                {{ survey.user?.name ?? 'Unknown' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="material-icons text-base leading-none">calendar_today</span>
                                {{ new Date(survey.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="material-icons text-base leading-none">forum</span>
                                {{ survey.responses_count ?? survey.responses?.length ?? 0 }} responses
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a :href="route('surveysEdit', { id: survey.uuid })"
                            class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <span class="material-icons text-base leading-none">edit</span>
                            Edit
                        </a>

                        <SurveyStatusToggle
                            :status="survey.status"
                            :updateroute="route('surveysUpdate', { id: survey.uuid })"
                        />

                        <SurveyActions
                            :status="survey.status ? 1 : 0"
                            :deleteroute="route('surveysDestroy', { id: survey.uuid })"
                            :surveysindex="route('surveysIndex')"
                            :surveysupdate="route('surveysUpdate', { id: survey.uuid })"
                            :surveysclone="route('surveysClone', { id: survey.uuid })"
                            :uuid="survey.uuid"
                            :public-url="survey.public_url ?? ''"
                            :share-agent-id="currentUser.id"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">forum</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">
                        {{ (survey.responses_count ?? survey.responses?.length ?? 0).toLocaleString() }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Responses</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-green-600 dark:text-green-300 bg-green-100 dark:bg-green-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">calendar_view_week</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ weeklyResponses }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This Week</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-purple-600 dark:text-purple-300 bg-purple-100 dark:bg-purple-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">task_alt</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ completionRate }}%</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Completion Rate</p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="flex items-center justify-center w-12 h-12 text-yellow-600 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex-shrink-0">
                    <span class="material-icons">star</span>
                </div>
                <div class="flex-1 ms-4">
                    <h3 class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">{{ avgRating ?? 'N/A' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg. Rating</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Questions -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="sm:flex space-y-2 sm:space-y-0 items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="material-icons text-blue-600 dark:text-blue-400">help_outline</span>
                                Questions ({{ sortedQuestions.length }})
                            </h2>
                            <div class="flex items-center gap-3 divide-x divide-gray-300 dark:divide-gray-600">
                                <a :href="route('surveysEdit', { id: survey.uuid })"
                                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                    Edit Questions
                                </a>
                                <button @click="hideQuestions = !hideQuestions"
                                    class="pl-3 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-white transition-colors">
                                    <span class="material-icons text-base leading-none">
                                        {{ hideQuestions ? 'visibility_off' : 'visibility' }}
                                    </span>
                                    {{ hideQuestions ? 'Show Questions' : 'Hide Questions' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6" v-show="!hideQuestions">
                        <div v-if="sortedQuestions.length > 0" class="space-y-4">
                            <div v-for="(question, index) in sortedQuestions" :key="question.id"
                                class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-semibold text-blue-800 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/50 rounded-full flex-shrink-0 mt-0.5">
                                        {{ index + 1 }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="font-medium text-gray-900 dark:text-white">{{ question.label }}</h3>
                                            <span v-if="question.required" class="text-red-500">*</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded">
                                                <span class="material-icons text-base leading-none">{{ questionTypeIcon(question.type) }}</span>
                                                {{ question.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
                                            </span>
                                            <span v-if="question.options?.length > 0" class="text-sm text-gray-500 dark:text-gray-400">
                                                <span class="font-medium">Options:</span>
                                                {{ question.options.slice(0, 3).join(', ') }}
                                                <span v-if="question.options.length > 3" class="text-gray-400 dark:text-gray-500">
                                                    +{{ question.options.length - 3 }} more
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                            <span class="material-icons text-5xl mb-3">help_outline</span>
                            <p class="text-sm">No questions added yet</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Responses -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="material-icons text-green-600 dark:text-green-400">bar_chart</span>
                                Recent Responses
                            </h2>
                            <a v-if="(survey.responses_count ?? survey.responses?.length ?? 0) > 0"
                                :href="route('surveyResponsesByUuid', { id: survey.uuid })"
                                class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                View All
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <div v-if="recentResponses.length > 0" class="space-y-3">
                            <a v-for="response in recentResponses" :key="response.id"
                                :href="route('surveyResponseShow', { sid: survey.uuid, rid: response.id })"
                                class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 bg-gray-200 dark:bg-gray-600 rounded-full flex-shrink-0">
                                        <span class="material-icons text-gray-600 dark:text-gray-300">person</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ response.email ?? 'Anonymous' }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                            {{ response.created_at_human ?? response.created_at }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center p-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg flex-shrink-0">
                                    <span class="material-icons text-base text-gray-700 dark:text-gray-300 leading-none">visibility</span>
                                </div>
                            </a>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                            <span class="material-icons text-5xl mb-3">inbox</span>
                            <p class="text-sm">No responses yet</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">

                <SurveyLinks
                    v-if="survey.status && survey.public_url"
                    :base-url="survey.public_url"
                    :embed-base-url="survey.embed_url || ''"
                    :users="users"
                    :current-user-id="currentUser.id"
                    :current-user-name="currentUser.display_name || currentUser.name || currentUser.email || ''"
                    :visibility="survey.visibility ?? 'public'"
                />

                <!-- Delivery & Automation -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-green-600 dark:text-green-400">send</span>
                        Delivery & Automation
                    </h3>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Delivery Method</span>
                            <span class="inline-flex items-center gap-1.5 font-medium text-gray-900 dark:text-white">
                                <span class="material-icons text-base leading-none">{{ deliveryIcon(survey.delivery_method) }}</span>
                                {{ (survey.delivery_method ?? 'email').charAt(0).toUpperCase() + (survey.delivery_method ?? 'email').slice(1) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-sm border-t border-gray-100 dark:border-gray-700">
                            <span class="text-gray-500 dark:text-gray-400">Automation</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ (survey.automation_trigger ?? 'manual').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
                            </span>
                        </div>
                        <div v-if="survey.automation_config?.days && survey.automation_trigger !== 'manual'"
                            class="flex items-center justify-between py-2 text-sm border-t border-gray-100 dark:border-gray-700">
                            <span class="text-gray-500 dark:text-gray-400">Send After</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ survey.automation_config.days }} days</span>
                        </div>
                    </div>
                </div>

                <!-- Privacy Settings -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-purple-600 dark:text-purple-400">shield</span>
                        Privacy Settings
                    </h3>
                    <ul class="space-y-3">
                        <li v-for="(label, key) in {
                            anonymous: 'Anonymous Responses',
                            require_email: 'Require Email',
                            one_response_per_user: 'One Response Per User',
                            show_results: 'Show Results',
                        }" :key="key" class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ label }}</span>
                            <span class="material-icons text-xl leading-none"
                                :class="privacy[key]
                                    ? 'text-green-500 dark:text-green-400'
                                    : 'text-gray-300 dark:text-gray-600'">
                                {{ privacy[key] ? 'check_circle' : 'cancel' }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Completion Settings -->
                <div v-if="survey.thank_you_message || survey.redirect_url"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                    <h3 class="mb-4 text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons text-red-500 dark:text-red-400">favorite</span>
                        Completion Settings
                    </h3>
                    <div class="space-y-4">
                        <div v-if="survey.thank_you_message">
                            <label class="block mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Thank You Message</label>
                            <p class="text-sm text-gray-900 dark:text-white">{{ survey.thank_you_message }}</p>
                        </div>
                        <div v-if="survey.redirect_url">
                            <label class="block mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Redirect URL</label>
                            <a :href="survey.redirect_url" target="_blank"
                                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline break-all">
                                {{ survey.redirect_url }}
                                <span class="material-icons text-base leading-none flex-shrink-0">open_in_new</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </TenantLayout>
</template>