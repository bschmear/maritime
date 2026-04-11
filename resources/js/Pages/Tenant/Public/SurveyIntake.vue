<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';

const props = defineProps({
    survey: { type: Object, required: true },
    recipientData: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    surveyColor: { type: String, default: '#2563eb' },
    embed: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    adminLinks: { type: Object, default: null },
    account: {
        type: Object,
        default: () => ({ logo_url: null, brand_color: null }),
    },
    agent: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const answers = ref({});
const firstName = ref(props.recipientData?.first_name ?? '');
const lastName = ref(props.recipientData?.last_name ?? '');
const email = ref(props.recipientData?.email ?? '');
const submitted = ref(false);
const submitting = ref(false);
const submissionMessage = ref('');
const submitError = ref('');
const showResults = ref(false);
const userAnswers = ref([]);
const aggregateResults = ref([]);
const startTime = ref(null);
const localSurveyColor = ref(props.surveyColor);
const colorSaving = ref(false);
const colorError = ref('');

onMounted(() => {
    startTime.value = Date.now();
});

watch(
    () => props.surveyColor,
    (c) => {
        localSurveyColor.value = c;
    },
);

const queryAid = computed(() => {
    const url = page.url || '';
    const q = url.includes('?') ? url.split('?')[1] : '';
    return new URLSearchParams(q).get('aid');
});

const effectiveColor = computed(() => localSurveyColor.value || props.surveyColor);

const pageDescription = computed(
    () => props.survey.public_description || props.survey.description || '',
);

const sortedQuestions = computed(() => {
    const qs = [...(props.survey.questions || [])].sort((a, b) => a.order - b.order);

    return qs.filter((question) => {
        if (!question.conditional_logic) {
            return true;
        }

        const logic = question.conditional_logic;

        if (logic.rules && Array.isArray(logic.rules)) {
            return logic.rules.some((rule) => {
                const targetQuestion = qs[rule.question];
                if (!targetQuestion) return false;
                const targetAnswer = answers.value[targetQuestion.id];
                return targetAnswer === rule.equals;
            });
        }

        const targetQuestion = qs[logic.show_if_question];
        if (!targetQuestion) {
            return true;
        }

        const targetAnswer = answers.value[targetQuestion.id];

        if (logic.equals) {
            return targetAnswer === logic.equals;
        }
        if (logic.equals_any && Array.isArray(logic.equals_any)) {
            return logic.equals_any.includes(targetAnswer);
        }

        return true;
    });
});

const progress = computed(() => {
    const total = sortedQuestions.value.length;
    if (total === 0) return 0;
    const answered = Object.keys(answers.value).filter((key) => {
        const answer = answers.value[key];
        if (answer === null || answer === undefined || answer === '') {
            return false;
        }
        if (Array.isArray(answer) && answer.length === 0) {
            return false;
        }
        return true;
    }).length;
    return Math.round((answered / total) * 100);
});

watch(progress, (p) => {
    const bar = document.getElementById('progress-bar');
    const text = document.getElementById('progress-text');
    if (bar) bar.style.width = `${p}%`;
    if (text) text.textContent = `${p}%`;
});

function setRating(questionId, rating) {
    answers.value = { ...answers.value, [questionId]: rating };
}

function setNps(questionId, score) {
    answers.value = { ...answers.value, [questionId]: score };
}

function npsButtonClass(questionId, score) {
    const selected = answers.value[questionId] === score;

    if (selected) {
        if (score >= 9) {
            return 'bg-green-600 text-white border-green-600 dark:bg-green-500 dark:border-green-500';
        }
        if (score >= 7) {
            return 'bg-yellow-500 text-white border-yellow-500 dark:bg-yellow-400 dark:border-yellow-400';
        }
        return 'bg-red-600 text-white border-red-600 dark:bg-red-500 dark:border-red-500';
    }

    return 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600';
}

function formatAnswerDisplay(row) {
    if (row.question_type === 'rating' || row.question_type === 'nps') {
        return row.answer;
    }
    return row.answer;
}

const agentPhoneDisplay = computed(() => formatPhoneNumber(props.agent?.phone || ''));

const agentAvatarSrc = computed(() => {
    const a = props.agent?.avatar;
    if (!a) return null;
    if (typeof a === 'string' && (a.startsWith('http://') || a.startsWith('https://') || a.startsWith('/'))) {
        return a;
    }
    return null;
});

async function submitSurvey() {
    submitting.value = true;
    submitError.value = '';

    try {
        const payload = {
            id: props.survey.uuid,
            answers: answers.value,
            email: email.value,
            first_name: firstName.value,
            last_name: lastName.value,
            start_time: startTime.value,
        };

        if (props.recipientData?.type && props.recipientData?.id) {
            payload.type = props.recipientData.type;
            payload.rid = props.recipientData.id;
        }

        const aid = props.recipientData?.agent_id ?? queryAid.value;
        if (aid) {
            payload.aid = Number(aid);
        }

        const response = await axios.post(props.submitUrl, payload, {
            headers: { Accept: 'application/json' },
        });

        if (response.data.success) {
            submitted.value = true;
            submissionMessage.value = response.data.message || '';

            if (response.data.show_results) {
                showResults.value = true;
                userAnswers.value = response.data.user_answers || [];
                aggregateResults.value = response.data.aggregate_results || [];
            }

            if (props.survey.redirect_url && !showResults.value) {
                setTimeout(() => {
                    window.location.href = props.survey.redirect_url;
                }, 2000);
            }
        } else {
            submitError.value = response.data.message || 'Something went wrong. Please try again.';
        }
    } catch (e) {
        console.error(e);
        const data = e.response?.data;
        if (data?.message) {
            submitError.value = data.message;
        } else if (data?.errors) {
            const first = Object.values(data.errors).flat()[0];
            submitError.value = first || 'Please check your answers and try again.';
        } else {
            submitError.value = 'Error submitting survey. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

async function saveSurveyColor(customColor) {
    if (!props.adminLinks?.updateColor || !props.canEdit) return;
    colorError.value = '';
    colorSaving.value = true;
    try {
        const response = await axios.put(
            props.adminLinks.updateColor,
            {
                survey_id: props.survey.uuid,
                color_scheme: 'custom',
                custom_color: customColor,
            },
            { headers: { Accept: 'application/json' } },
        );
        if (response.data.success && response.data.data?.effective_color) {
            localSurveyColor.value = response.data.data.effective_color;
        }
    } catch (e) {
        const msg = e.response?.data?.message;
        colorError.value = msg || 'Could not update color.';
    } finally {
        colorSaving.value = false;
    }
}
</script>

<template>
    <Head>
        <title>{{ survey.title }}</title>
        <meta v-if="pageDescription" name="description" :content="pageDescription" />
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicSurveyLayout :survey-color="effectiveColor" :embed="embed">
        <!-- Admin strip -->
        <div
            v-if="canEdit && adminLinks"
            class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/40"
        >
            <p class="mb-3 font-medium text-amber-900 dark:text-amber-100">Survey admin</p>
            <div class="flex flex-wrap items-center gap-3">
                <a
                    :href="adminLinks.edit"
                    class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-amber-900 shadow-sm ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-gray-900 dark:text-amber-50 dark:ring-amber-800"
                >
                    Edit survey
                </a>
                <a
                    :href="adminLinks.analytics"
                    class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-amber-900 shadow-sm ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-gray-900 dark:text-amber-50 dark:ring-amber-800"
                >
                    Analytics
                </a>
                <label class="inline-flex items-center gap-2 text-amber-900 dark:text-amber-100">
                    <span class="whitespace-nowrap">Accent</span>
                    <input
                        type="color"
                        :value="localSurveyColor"
                        class="h-8 w-14 cursor-pointer rounded border border-amber-300 bg-white p-0.5"
                        @change="saveSurveyColor($event.target.value)"
                    />
                    <span v-if="colorSaving" class="text-xs text-amber-800 dark:text-amber-200">Saving…</span>
                </label>
            </div>
            <p v-if="colorError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ colorError }}</p>
        </div>

        <!-- Progress (full page only) -->
        <div v-if="!embed && !submitted" class="mb-8">
            <div class="mb-2 flex justify-between text-sm text-gray-600 dark:text-gray-400">
                <span>Progress</span>
                <span id="progress-text">{{ progress }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    id="progress-bar"
                    class="h-full rounded-full transition-all duration-300"
                    :style="{ width: `${progress}%`, backgroundColor: effectiveColor }"
                />
            </div>
        </div>

        <!-- Hero / header -->
        <header v-if="!embed" class="mb-8 text-center">
            <img
                v-if="account?.logo_url"
                :src="account.logo_url"
                alt=""
                class="mx-auto mb-4 h-12 w-auto object-contain"
            />
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                {{ survey.title }}
            </h1>
            <p
                v-if="pageDescription"
                class="mx-auto mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-300"
            >
                {{ pageDescription }}
            </p>
        </header>
        <header v-else class="mb-4 border-b border-gray-200 pb-3 dark:border-gray-700">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">{{ survey.title }}</h1>
        </header>

        <!-- Agent card (full only) -->
        <div
            v-if="!embed && agent?.display_name"
            class="mb-8 flex flex-col items-center gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-start"
        >
            <div
                v-if="agentAvatarSrc"
                class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-full ring-2 ring-gray-100 dark:ring-gray-600"
            >
                <img :src="agentAvatarSrc" alt="" class="h-full w-full object-cover" />
            </div>
            <div class="text-center sm:text-left">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Your contact</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ agent.display_name }}</p>
                <p v-if="agentPhoneDisplay" class="mt-1">
                    <a
                        :href="`tel:${String(agent.phone).replace(/\D/g, '')}`"
                        class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {{ agentPhoneDisplay }}
                    </a>
                </p>
                <p v-if="agent.email" class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                    {{ agent.email }}
                </p>
            </div>
        </div>

        <div id="SurveyForm" class="mx-auto max-w-2xl">
            <form
                v-if="!submitted"
                class="space-y-8 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800 sm:p-8"
                @submit.prevent="submitSurvey"
            >
                <p
                    v-if="submitError"
                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200"
                    role="alert"
                >
                    {{ submitError }}
                </p>

                <div v-for="(question, visibleIndex) in sortedQuestions" :key="question.id" class="space-y-3">
                    <label class="block text-base font-semibold text-gray-900 dark:text-white">
                        {{ visibleIndex + 1 }}. {{ question.label }}
                        <span v-if="question.required" class="text-red-600 dark:text-red-500">*</span>
                    </label>

                    <input
                        v-if="question.type === 'text'"
                        v-model="answers[question.id]"
                        :required="question.required"
                        type="text"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-500"
                        placeholder="Your answer..."
                    />

                    <textarea
                        v-else-if="question.type === 'textarea'"
                        v-model="answers[question.id]"
                        :required="question.required"
                        :rows="question.config?.rows || 4"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-500"
                        placeholder="Your answer..."
                    />

                    <div v-else-if="question.type === 'multiple_choice'" class="space-y-3">
                        <div v-for="option in question.options" :key="option" class="flex items-center">
                            <input
                                :id="'radio_' + question.id + '_' + option"
                                v-model="answers[question.id]"
                                type="radio"
                                :name="'question_' + question.id"
                                :value="option"
                                :required="question.required"
                                class="h-4 w-4 border-gray-300 bg-gray-100 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-blue-600"
                            />
                            <label
                                :for="'radio_' + question.id + '_' + option"
                                class="ml-3 cursor-pointer text-sm font-medium text-gray-900 dark:text-gray-300"
                            >
                                {{ option }}
                            </label>
                        </div>
                    </div>

                    <select
                        v-else-if="question.type === 'dropdown'"
                        v-model="answers[question.id]"
                        :required="question.required"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-500"
                    >
                        <option value="">Select an option...</option>
                        <option v-for="option in question.options" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>

                    <div v-else-if="question.type === 'rating'" class="flex flex-wrap items-center gap-1">
                        <button
                            v-for="i in question.config?.max || 5"
                            :key="i"
                            type="button"
                            class="rounded focus:outline-none"
                            :class="{
                                'ring-2 ring-amber-400 ring-offset-1 dark:ring-amber-500':
                                    question.required && !answers[question.id],
                            }"
                            @click="setRating(question.id, i)"
                        >
                            <svg
                                class="h-8 w-8 transition-colors"
                                :class="
                                    i <= (answers[question.id] || 0)
                                        ? 'text-yellow-400'
                                        : 'text-gray-300 dark:text-gray-600'
                                "
                                fill="currentColor"
                                viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                />
                            </svg>
                        </button>
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                            {{
                                answers[question.id]
                                    ? answers[question.id] + ' / ' + (question.config?.max || 5)
                                    : 'Not rated'
                            }}
                        </span>
                    </div>

                    <div v-else-if="question.type === 'nps'" class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="i in 11"
                                :key="i"
                                type="button"
                                class="h-10 w-10 flex-shrink-0 rounded-lg border text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :class="npsButtonClass(question.id, i - 1)"
                                @click="setNps(question.id, i - 1)"
                            >
                                {{ i - 1 }}
                            </button>
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span>Not at all likely</span>
                            <span>Extremely likely</span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!survey.privacy_settings?.anonymous"
                    class="grid grid-cols-1 gap-3 border-t border-gray-200 pt-4 dark:border-gray-700 md:grid-cols-2 md:gap-6 lg:pt-8"
                >
                    <div>
                        <label for="first_name" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            First Name
                            <span v-if="survey.privacy_settings?.require_email" class="text-red-600 dark:text-red-500"
                                >*</span
                            >
                        </label>
                        <input
                            id="first_name"
                            v-model="firstName"
                            type="text"
                            :required="survey.privacy_settings?.require_email"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-500"
                            placeholder="First Name"
                        />
                    </div>
                    <div>
                        <label for="last_name" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Last Name
                            <span v-if="survey.privacy_settings?.require_email" class="text-red-600 dark:text-red-500"
                                >*</span
                            >
                        </label>
                        <input
                            id="last_name"
                            v-model="lastName"
                            type="text"
                            :required="survey.privacy_settings?.require_email"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-500"
                            placeholder="Last Name"
                        />
                    </div>
                </div>

                <div v-if="!survey.privacy_settings?.anonymous">
                    <label for="email" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                        Email Address
                        <span v-if="survey.privacy_settings?.require_email" class="text-red-600 dark:text-red-500"
                            >*</span
                        >
                    </label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        :required="survey.privacy_settings?.require_email"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-500"
                        placeholder="your.email@example.com"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="w-full rounded-lg px-5 py-3 text-center text-sm font-medium text-white transition-colors focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50"
                    :style="{ backgroundColor: effectiveColor }"
                >
                    <span v-if="!submitting">Submit Survey</span>
                    <span v-else class="flex items-center justify-center">
                        <svg
                            class="-ml-1 mr-3 h-5 w-5 animate-spin text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Submitting...
                    </span>
                </button>
            </form>

            <div v-else class="rounded-lg bg-white p-8 shadow-sm dark:bg-gray-800">
                <div class="mb-6 text-center">
                    <div
                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
                    >
                        <svg
                            class="h-6 w-6 text-green-600 dark:text-green-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">Survey Submitted Successfully!</h3>
                    <p class="mb-6 text-gray-600 dark:text-gray-300">
                        {{ submissionMessage || survey.thank_you_message || 'Thank you for completing the survey!' }}
                    </p>
                </div>

                <div v-if="showResults && userAnswers.length > 0" class="mb-6">
                    <h4 class="mb-4 flex items-center text-md font-semibold text-gray-900 dark:text-white">
                        Your responses
                    </h4>
                    <div class="space-y-4">
                        <div
                            v-for="(row, index) in userAnswers"
                            :key="index"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ row.question }}</p>
                            <p class="text-base text-gray-900 dark:text-white">{{ formatAnswerDisplay(row) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="showResults && aggregateResults.length > 0" class="mb-6">
                    <h4 class="mb-4 text-md font-semibold text-gray-900 dark:text-white">Survey results</h4>
                    <div class="space-y-6">
                        <div
                            v-for="(result, index) in aggregateResults"
                            :key="index"
                            class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ result.question }}</p>
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ result.total_responses }} total responses
                            </p>

                            <div v-if="result.distribution" class="space-y-2">
                                <div v-for="(data, option) in result.distribution" :key="option" class="flex items-center">
                                    <div class="flex-1">
                                        <div class="mb-1 flex justify-between">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ option }}</span>
                                            <span class="text-xs font-medium text-gray-900 dark:text-white">
                                                {{ data.percentage }}%
                                            </span>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div
                                                class="h-2 rounded-full"
                                                :style="{ width: data.percentage + '%', backgroundColor: effectiveColor }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="result.average !== undefined" class="mt-3 text-center">
                                <p class="text-2xl font-bold" :style="{ color: effectiveColor }">{{ result.average }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Average rating</p>
                            </div>

                            <div v-if="result.nps_score !== undefined" class="mt-3 text-center">
                                <p class="text-2xl font-bold" :style="{ color: effectiveColor }">{{ result.nps_score }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">NPS score</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a
                        v-if="survey.redirect_url"
                        :href="survey.redirect_url"
                        class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                    >
                        Continue
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3"
                            />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </PublicSurveyLayout>
</template>

<style scoped>
@keyframes pulse-soft {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}
</style>
