<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import SurveyAdminControls from '@/Components/surveys/SurveyAdminControls.vue';
import { formatPhoneNumber } from '@/Utils/formatPhoneNumber';

const props = defineProps({
    survey: { type: Object, required: true },
    recipientData: { type: Object, default: null },
    submitUrl: { type: String, required: true },
    surveyColor: { type: String, default: '#14c2ad' },
    /** App default brand hex (matches Survey::getEffectiveColor default path). */
    defaultSurveyBrand: { type: String, default: '#14c2ad' },
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
const showDuplicateModal = ref(false);
const showResults = ref(false);
const userAnswers = ref([]);
const aggregateResults = ref([]);
const startTime = ref(null);
const localSurveyColor = ref(props.surveyColor);

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

const adminInitialColorScheme = computed(() => {
    const s = props.survey?.color_scheme;
    if (s === 'team' && !props.account?.brand_color) {
        return 'default';
    }
    return s === 'custom' || s === 'team' || s === 'default' ? s : 'default';
});

const adminInitialCustomColor = computed(
    () => props.survey?.custom_color || props.defaultSurveyBrand,
);

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
    document.querySelectorAll('[data-survey-progress-bar]').forEach((el) => {
        el.style.width = `${p}%`;
    });
    document.querySelectorAll('[data-survey-progress-text]').forEach((el) => {
        el.textContent = `${p}%`;
    });
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

const recipientGreetingName = computed(() => {
    const r = props.recipientData;
    if (!r) return '';
    if (r.name && String(r.name).trim()) return String(r.name).trim();
    return [r.first_name, r.last_name].filter(Boolean).join(' ').trim();
});

const estimatedMinutes = computed(() => props.survey.estimated_time ?? 5);

const heroGradientStyle = computed(() => {
    const c = effectiveColor.value;
    return {
        background: `linear-gradient(to bottom right, ${c}, color-mix(in srgb, ${c} 72%, black))`,
    };
});

const agentInitial = computed(() => {
    const n = props.agent?.display_name || props.agent?.email || 'A';
    const ch = String(n).trim().charAt(0);
    return ch ? ch.toUpperCase() : 'A';
});

const surveyMarkInitial = computed(() => {
    const t = props.survey?.title || 'S';
    const ch = String(t).trim().charAt(0);
    return ch ? ch.toUpperCase() : 'S';
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
        const msg = data?.message || '';
        if (msg.toLowerCase().includes('already submitted')) {
            showDuplicateModal.value = true;
        } else if (msg) {
            submitError.value = msg;
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

function onAdminColorPreview(hex) {
    if (hex && typeof hex === 'string') {
        localSurveyColor.value = hex;
    }
}
</script>

<template>
    <Head>
        <title>{{ survey.title }}</title>
        <meta v-if="pageDescription" name="description" :content="pageDescription" />
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicSurveyLayout :embed="embed">
        <SurveyAdminControls
            v-if="canEdit && adminLinks"
            :survey-id="survey.uuid"
            :edit-url="adminLinks.edit"
            :analytics-url="adminLinks.analytics"
            :update-route="adminLinks.updateColor"
            :can-customize-colors="true"
            :initial-color-scheme="adminInitialColorScheme"
            :initial-custom-color="adminInitialCustomColor"
            :default-color="defaultSurveyBrand"
            :team-color="account?.brand_color || null"
            :current-color="surveyColor"
            :embed="embed"
            @preview="onAdminColorPreview"
        />

        <!-- Marketing hero (full page only) -->
        <div v-if="!embed" class="relative text-white" :style="heroGradientStyle">
            <header class="relative z-10">
                <nav class="border-b border-white/10">
                    <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between gap-3 px-4 py-4 md:px-6">
                        <div class="flex items-center gap-3">
                            <div
                                v-if="account?.logo_url"
                                class="timeline-logo rounded-lg bg-white p-2 shadow-lg"
                            >
                                <img :src="account.logo_url" alt="" class="max-h-8 w-auto object-contain" />
                            </div>
                            <div
                                v-else
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20 text-lg font-bold shadow-md backdrop-blur-sm"
                            >
                                {{ surveyMarkInitial }}
                            </div>
                        </div>
                        <div v-if="agent?.phone && agentPhoneDisplay" class="hidden text-right sm:block">
                            <a
                                :href="`tel:${String(agent.phone).replace(/\D/g, '')}`"
                                class="text-white transition-opacity hover:opacity-90"
                            >
                                <div class="text-xs opacity-90">Contact</div>
                                <div class="text-base font-semibold">{{ agentPhoneDisplay }}</div>
                            </a>
                        </div>
                    </div>
                </nav>
            </header>

            <div class="mx-auto max-w-4xl px-4 py-12 text-center md:py-16">
                <div class="mb-6">
                    <span
                        class="inline-flex items-center rounded-full border border-white/20 bg-blue-500/20 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                    >
                        <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"
                            />
                            <path
                                fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Survey
                    </span>
                </div>
                <h1 class="mb-4 text-4xl font-bold leading-tight md:text-5xl">
                    {{ survey.title }}
                </h1>
                <p
                    v-if="pageDescription"
                    class="mx-auto max-w-2xl text-lg leading-relaxed text-blue-100 md:text-xl"
                >
                    {{ pageDescription }}
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-6 text-sm text-blue-100">
                    <div class="flex items-center">
                        <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Takes ~{{ estimatedMinutes }} minutes
                    </div>
                    <div class="flex items-center">
                        <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fill-rule="evenodd"
                                d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Your responses are confidential
                    </div>
                </div>
            </div>

            <div class="relative">
                <svg
                    class="h-12 w-full md:h-16"
                    viewBox="0 0 1440 48"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M0 48H1440V0C1440 0 1140 48 720 48C300 48 0 0 0 0V48Z"
                        class="fill-gray-50 dark:fill-gray-900"
                    />
                </svg>
            </div>
        </div>

        <!-- Embed: slim header -->
        <header v-if="embed" class="mb-4 border-b border-gray-200 pb-3 dark:border-gray-700">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">{{ survey.title }}</h1>
        </header>

        <!-- Main shell -->
        <div :class="embed ? '' : '-mt-px bg-gray-50 dark:bg-gray-900'">
            <div :class="embed ? '' : 'mx-auto max-w-7xl px-4 py-8 md:py-12'">
                <div
                    :class="
                        embed
                            ? ''
                            : 'grid grid-cols-1 gap-8 lg:grid-cols-3 lg:gap-6'
                    "
                >
                    <!-- Sidebar (full page, before submit) -->
                    <aside
                        v-if="!embed && !submitted"
                        class="order-2 space-y-6 lg:sticky lg:top-8 lg:col-span-1 lg:order-1 lg:self-start"
                    >
                        <div
                            v-if="agent?.display_name"
                            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="relative p-6 pb-16 text-center" :style="heroGradientStyle">
                                <h3 class="text-lg font-semibold text-white">Your contact</h3>
                                <p class="text-sm text-white/90">Available to help you</p>
                            </div>
                            <div class="relative -mt-12 mb-4 flex justify-center">
                                <div class="relative">
                                    <img
                                        v-if="agentAvatarSrc"
                                        :src="agentAvatarSrc"
                                        :alt="agent.display_name"
                                        class="h-24 w-24 rounded-full border-4 border-white object-cover shadow-xl dark:border-gray-800"
                                    />
                                    <div
                                        v-else
                                        class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-blue-500 to-indigo-600 text-3xl font-bold text-white shadow-xl dark:border-gray-800"
                                    >
                                        {{ agentInitial }}
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 pb-6 text-center">
                                <h4 class="mb-1 text-xl font-bold text-gray-900 dark:text-white">
                                    {{ agent.display_name }}
                                </h4>
                                <div class="mt-4 space-y-3 text-left">
                                    <a
                                        v-if="agent.phone && agentPhoneDisplay"
                                        :href="`tel:${String(agent.phone).replace(/\D/g, '')}`"
                                        class="group flex items-center rounded-lg bg-gray-50 p-3 transition-colors hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                                    >
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30"
                                        >
                                            <span class="material-icons text-blue-600 dark:text-blue-400">phone</span>
                                        </div>
                                        <div class="ml-3 min-w-0 flex-1">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Phone</p>
                                            <p class="break-all text-sm font-medium text-gray-900 dark:text-white">
                                                {{ agentPhoneDisplay }}
                                            </p>
                                        </div>
                                    </a>
                                    <a
                                        v-if="agent.email"
                                        :href="`mailto:${agent.email}`"
                                        class="group flex items-center rounded-lg bg-gray-50 p-3 transition-colors hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                                    >
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30"
                                        >
                                            <span class="material-icons text-green-600 dark:text-green-400">email</span>
                                        </div>
                                        <div class="ml-3 min-w-0 flex-1">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Email</p>
                                            <p class="break-all text-sm font-medium text-gray-900 dark:text-white">
                                                {{ agent.email }}
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20"
                        >
                            <div class="flex items-start">
                                <span class="material-icons flex-shrink-0 text-blue-600 dark:text-blue-400">help</span>
                                <div class="ml-3">
                                    <h4 class="mb-1 text-sm font-semibold text-blue-900 dark:text-blue-300">
                                        Need help?
                                    </h4>
                                    <p class="text-sm text-blue-800 dark:text-blue-400">
                                        If you have questions about this survey, use the contact information
                                        {{ agent?.display_name ? 'above' : 'from your invitation' }}.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="hidden rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:block"
                        >
                            <h4 class="mb-3 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span class="material-icons mr-2 text-blue-600 text-[20px]">check_circle</span>
                                Survey progress
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Completion</span>
                                    <span
                                        data-survey-progress-text
                                        class="font-medium text-gray-900 dark:text-white"
                                    >{{ progress }}%</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        data-survey-progress-bar
                                        class="h-2.5 rounded-full transition-all duration-300"
                                        :style="{ width: `${progress}%`, backgroundColor: effectiveColor }"
                                    />
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Form column -->
                    <div
                        id="SurveyForm"
                        class="w-full"
                        :class="
                            embed
                                ? ''
                                : submitted
                                  ? 'order-1 mx-auto max-w-2xl lg:col-span-3 lg:order-2'
                                  : 'order-1 lg:col-span-2 lg:order-2'
                        "
                    >
                        <div
                            v-if="!embed && recipientGreetingName && !submitted"
                            class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                        >
                            <p class="text-sm text-blue-800 dark:text-blue-300">
                                <span class="material-icons mr-1 align-middle text-base text-blue-600 dark:text-blue-400">
                                    person
                                </span>
                                Hello,
                                <strong>{{ recipientGreetingName }}</strong>
                                ! We have pre-filled your information to save you time.
                            </p>
                        </div>

                        <div v-if="!embed && !submitted" class="mb-6 lg:hidden">
                            <div class="mb-2 flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                <span>Progress</span>
                                <span
                                    data-survey-progress-text
                                    class="font-medium text-gray-900 dark:text-white"
                                >{{ progress }}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    data-survey-progress-bar
                                    class="h-2 rounded-full transition-all duration-300"
                                    :style="{ width: `${progress}%`, backgroundColor: effectiveColor }"
                                />
                            </div>
                        </div>

            <form
                v-if="!submitted"
                class="space-y-8 rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-800 md:p-8"
                :class="embed ? 'rounded-lg shadow-md' : ''"
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

            <div
                v-else
                class="rounded-xl border border-gray-200 bg-white p-8 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
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
                </div>
            </div>
        </div>
        <!-- Already-submitted modal -->
        <Transition name="modal-fade">
            <div
                v-if="showDuplicateModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="dup-modal-title"
                @click.self="showDuplicateModal = false"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showDuplicateModal = false" />

                <!-- Panel -->
                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
                    <!-- Colour bar -->
                    <div
                        class="h-1.5 w-full rounded-t-2xl"
                        :style="{ backgroundColor: effectiveColor }"
                    />

                    <div class="px-8 pb-8 pt-6">
                        <!-- Icon -->
                        <div class="mb-5 flex justify-center">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-full"
                                :style="{ backgroundColor: effectiveColor + '20' }"
                            >
                                <svg
                                    class="h-7 w-7"
                                    :style="{ color: effectiveColor }"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <h2
                            id="dup-modal-title"
                            class="mb-2 text-center text-xl font-bold text-gray-900 dark:text-white"
                        >
                            Already submitted
                        </h2>

                        <p class="mb-1 text-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ survey.title }}</span>
                        </p>

                        <p class="mt-3 text-center text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                            It looks like a response has already been recorded for this survey.
                            If you believe this is a mistake, please reach out to the sender.
                        </p>

                        <div class="mt-7 flex justify-center">
                            <button
                                type="button"
                                class="rounded-lg px-6 py-2.5 text-sm font-semibold text-white shadow transition-opacity hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                :style="{ backgroundColor: effectiveColor }"
                                @click="showDuplicateModal = false"
                            >
                                Got it
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
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

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
    transform: scale(0.96);
}
</style>
