<template>
  <div class="survey-creator">
    <!-- Template Selector Modal -->
    <div
      v-if="showTemplateSelector"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 dark:bg-black/70"
      :class="{ 'backdrop-blur-sm': !isEditing && !surveyStarted }"
    >
      <div class="relative w-full max-w-6xl max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow dark:bg-gray-800">
        <!-- Modal header -->
        <div class="sticky top-0 flex items-center justify-between p-4 border-b rounded-t bg-white dark:bg-gray-800 dark:border-gray-700 z-10">
          <div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <span class="material-icons text-blue-600 dark:text-blue-400">description</span>
              Choose a Survey Template
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Start with a pre-built template or create from scratch</p>
          </div>
          <button
            v-if="surveyStarted"
            @click="showTemplateSelector = false"
            type="button"
            class="text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white rounded-lg w-8 h-8 inline-flex justify-center items-center transition-colors"
          >
            <span class="material-icons text-xl">close</span>
          </button>
        </div>

        <!-- Modal body -->
        <div class="p-6">
          <div v-if="loadingTemplates" class="flex justify-center items-center py-12">
            <span class="material-icons text-5xl text-gray-400 animate-spin">progress_activity</span>
          </div>

          <div v-else>
            <!-- Category Tabs -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
              <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                  <button
                    @click="selectedCategory = 'all'"
                    :class="selectedCategory === 'all'
                      ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500'
                      : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="inline-block p-4 border-b-2 rounded-t-lg transition-colors"
                  >
                    All Templates
                  </button>
                </li>
                <li v-for="category in templateCategories" :key="category" class="mr-2">
                  <button
                    @click="selectedCategory = category"
                    :class="selectedCategory === category
                      ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500'
                      : 'text-gray-500 border-transparent hover:text-gray-600 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="inline-block p-4 border-b-2 rounded-t-lg transition-colors"
                  >
                    {{ category }}
                  </button>
                </li>
              </ul>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <!-- Start from Scratch Card -->
              <button
                @click="startFromScratch"
                class="flex flex-col p-6 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border-2 border-blue-300 dark:border-blue-600 rounded-lg hover:shadow-lg transition-all text-left group"
              >
                <div class="flex items-center justify-center w-12 h-12 mb-4 bg-blue-600 text-white rounded-lg">
                  <span class="material-icons text-2xl">add</span>
                </div>
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Start from Scratch</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 flex-grow">Create a completely custom survey with your own questions</p>
                <div class="mt-4 text-blue-600 dark:text-blue-400 font-medium text-sm group-hover:underline flex items-center gap-1">
                  Create Custom Survey
                  <span class="material-icons text-base leading-none">arrow_forward</span>
                </div>
              </button>

              <!-- Template Cards -->
              <button
                v-for="template in filteredTemplates"
                :key="template.id"
                @click="selectTemplate(template)"
                class="flex flex-col p-6 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-lg transition-all text-left group"
              >
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center justify-center w-12 h-12 bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded-lg">
                    <span class="material-icons text-2xl">{{ templateIcon(template.icon) }}</span>
                  </div>
                  <span class="inline-flex items-center px-2 py-1 text-sm font-medium text-blue-800 bg-blue-100 rounded dark:bg-blue-900 dark:text-blue-300">
                    {{ template.questions?.length || 0 }} questions
                  </span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-2">{{ template.name }}</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 flex-grow line-clamp-2">{{ template.description }}</p>
                <div class="mt-4 text-blue-600 dark:text-blue-400 font-medium text-sm group-hover:underline flex items-center gap-1">
                  Use This Template
                  <span class="material-icons text-base leading-none">arrow_forward</span>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div v-if="surveyStarted" class="flex items-center justify-end p-4 border-t border-gray-200 dark:border-gray-700 rounded-b">
          <button
            @click="showTemplateSelector = false"
            type="button"
            class="text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Main Survey Creator -->
    <div v-if="surveyStarted">
      <!-- Step Progress Indicator -->
      <div class="mb-8">
        <ol class="flex items-center w-full max-w-3xl mx-auto">
          <li
            v-for="(step, index) in steps"
            :key="step.number"
            class="flex items-center"
            :class="index < steps.length - 1 ? 'w-full' : ''"
          >
            <div class="flex flex-col items-center" :class="index < steps.length - 1 ? 'w-full' : ''">
              <span
                class="flex items-center justify-center w-10 h-10 lg:h-12 lg:w-12 rounded-full shrink-0 transition-colors"
                :class="getStepClass(step.number)"
              >
                <span v-if="step.number < currentStep" class="material-icons text-xl">check</span>
                <span v-else>{{ step.number }}</span>
              </span>
              <span
                class="mt-2 text-sm font-medium text-center"
                :class="currentStep >= step.number ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'"
              >
                {{ step.title }}
              </span>
            </div>
            <div
              v-if="index < steps.length - 1"
              class="flex w-full bg-gray-200 h-0.5 dark:bg-gray-700 mx-4"
            >
              <div
                class="h-0.5 transition-all"
                :class="currentStep > step.number ? 'bg-blue-600 dark:bg-blue-500 w-full' : 'w-0'"
              ></div>
            </div>
          </li>
        </ol>
      </div>

      <!-- Step Content -->
      <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6 flex justify-center">
        <component
          :is="currentStepComponent"
          v-model="surveyData"
          :users="users"
          :team="team"
          :subscription="subscription"
          @next="nextStep"
          @previous="previousStep"
        />
      </div>

      <!-- Navigation Buttons -->
      <div class="flex justify-between items-center mt-6">
        <div class="flex gap-3">
          <button
            v-if="currentStep > 1"
            @click="previousStep"
            type="button"
            class="inline-flex items-center gap-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
          >
            <span class="material-icons text-base leading-none">arrow_back</span>
            Previous
          </button>

          <button
            v-if="!isEditing && (currentStep === 1 || currentStep === 2)"
            @click="showTemplateSelector = true"
            type="button"
            class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
          >
            <span class="material-icons text-base leading-none">description</span>
            Choose Template
          </button>
        </div>

        <div class="flex flex-wrap gap-3 items-center">
          <button
            v-if="currentStep < steps.length"
            @click="nextStep"
            type="button"
            class="inline-flex items-center gap-2 text-white bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
          >
            Next
            <span class="material-icons text-base leading-none">arrow_forward</span>
          </button>

          <!-- CREATE MODE: last step actions -->
          <template v-if="!isEditing && currentStep === steps.length">
            <button
              @click="handleSaveDraft"
              type="button"
              :disabled="saving"
              class="inline-flex items-center gap-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors disabled:opacity-50"
            >
              <span class="material-icons text-base leading-none">save</span>
              Save as Draft
            </button>

            <button
              @click="publishSurvey"
              type="button"
              :disabled="saving"
              class="inline-flex items-center gap-2 text-white bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors disabled:opacity-50"
            >
              <span class="material-icons text-base leading-none">rocket_launch</span>
              Save and Publish
            </button>
          </template>

          <!-- EDIT MODE: visible on any step -->
          <button
            v-if="isEditing"
            @click="saveSurvey"
            type="button"
            :disabled="saving"
            class="inline-flex items-center gap-2 text-white bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors disabled:opacity-50"
          >
            <span class="material-icons text-base leading-none">save</span>
            Save Updates
          </button>
        </div>
      </div>
    </div>

    <!-- Draft Confirmation Modal -->
    <div
      v-if="showDraftConfirmation"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 dark:bg-black/70"
      @click.self="showDraftConfirmation = false"
    >
      <div class="relative w-full max-w-md bg-white dark:bg-gray-700 rounded-lg shadow">
        <button
          @click="showDraftConfirmation = false"
          type="button"
          class="absolute top-3 right-3 text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-gray-900 dark:hover:text-white rounded-lg w-8 h-8 inline-flex justify-center items-center transition-colors"
        >
          <span class="material-icons text-xl">close</span>
        </button>
        <div class="p-6 text-center">
          <span class="material-icons text-5xl text-yellow-500 dark:text-yellow-400 mb-4 block">warning</span>
          <h3 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">This Survey is Currently Live</h3>
          <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            Saving as draft will disable this survey and make it unavailable to respondents.
            Are you sure you want to continue?
          </p>
          <div class="flex gap-3 justify-center flex-wrap">
            <button
              @click="confirmSaveDraft"
              type="button"
              class="inline-flex items-center gap-2 text-white bg-yellow-600 hover:bg-yellow-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
            >
              <span class="material-icons text-base leading-none">save</span>
              Save as Draft
            </button>
            <button
              @click="saveAndKeepLive"
              type="button"
              class="inline-flex items-center gap-2 text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
            >
              <span class="material-icons text-base leading-none">check</span>
              Save and Keep Live
            </button>
            <button
              @click="showDraftConfirmation = false"
              type="button"
              class="text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div
      v-if="showPreview"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 dark:bg-black/70"
      @click.self="showPreview = false"
    >
      <div class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow">
        <!-- Modal header -->
        <div class="sticky top-0 flex items-center justify-between p-4 border-b rounded-t bg-white dark:bg-gray-800 dark:border-gray-700 z-10">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="material-icons text-blue-600 dark:text-blue-400">visibility</span>
            Survey Preview
          </h3>
          <button
            @click="showPreview = false"
            type="button"
            class="text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-gray-900 dark:hover:text-white rounded-lg w-8 h-8 inline-flex justify-center items-center transition-colors"
          >
            <span class="material-icons text-xl">close</span>
          </button>
        </div>

        <!-- Modal body -->
        <div class="p-6 space-y-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ surveyData.title }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ surveyData.description }}</p>
          </div>

          <div class="space-y-6">
            <div
              v-for="(question, index) in surveyData.questions"
              :key="question.id"
              class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
            >
              <label class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-900 dark:text-white">
                <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-semibold text-blue-800 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/50 rounded-full flex-shrink-0">
                  {{ index + 1 }}
                </span>
                {{ question.label }}
                <span v-if="question.required" class="text-red-600 dark:text-red-400">*</span>
              </label>

              <!-- Text Input -->
              <div v-if="question.type === 'text'">
                <input
                  type="text"
                  class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg block w-full p-2.5"
                  placeholder="Your answer..."
                  disabled
                >
              </div>

              <!-- Textarea -->
              <div v-else-if="question.type === 'textarea'">
                <textarea
                  class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg block w-full p-2.5"
                  :rows="question.config?.rows || 4"
                  :placeholder="question.config?.placeholder || 'Your answer...'"
                  disabled
                ></textarea>
              </div>

              <!-- Multiple Choice -->
              <div v-else-if="question.type === 'multiple_choice'" class="space-y-2">
                <div v-for="(option, optIndex) in question.options" :key="optIndex" class="flex items-center gap-2">
                  <input
                    :id="`preview_${question.id}_${optIndex}`"
                    type="radio"
                    :name="`preview_${question.id}`"
                    disabled
                    class="w-4 h-4 text-blue-600 bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600"
                  >
                  <label :for="`preview_${question.id}_${optIndex}`" class="text-sm text-gray-900 dark:text-gray-300">
                    {{ option }}
                  </label>
                </div>
              </div>

              <!-- Rating -->
              <div v-else-if="question.type === 'rating'">
                <div class="flex gap-2">
                  <button
                    v-for="n in (question.config?.max || 5)"
                    :key="n"
                    type="button"
                    disabled
                    class="w-10 h-10 text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg"
                  >
                    {{ n }}
                  </button>
                </div>
              </div>

              <!-- Dropdown -->
              <div v-else-if="question.type === 'dropdown'">
                <select
                  disabled
                  class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg block w-full p-2.5"
                >
                  <option selected>Select an option...</option>
                  <option v-for="(option, optIndex) in question.options" :key="optIndex">{{ option }}</option>
                </select>
              </div>

              <!-- NPS -->
              <div v-else-if="question.type === 'nps'">
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="n in 11"
                    :key="n"
                    type="button"
                    disabled
                    class="w-10 h-10 text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-800 border rounded-lg"
                    :class="{
                      'border-red-400 dark:border-red-500': n <= 7,
                      'border-yellow-400 dark:border-yellow-500': n > 7 && n <= 9,
                      'border-green-400 dark:border-green-500': n > 9
                    }"
                  >
                    {{ n - 1 }}
                  </button>
                </div>
                <div class="flex justify-between mt-3 text-sm text-gray-500 dark:text-gray-400">
                  <span class="flex items-center gap-1">
                    <span class="material-icons text-base leading-none">sentiment_dissatisfied</span>
                    Not likely
                  </span>
                  <span class="flex items-center gap-1">
                    Extremely likely
                    <span class="material-icons text-base leading-none">sentiment_satisfied</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div class="flex items-center p-4 border-t border-gray-200 dark:border-gray-700 rounded-b">
          <button
            @click="showPreview = false"
            type="button"
            class="text-white bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 transition-colors"
          >
            Close Preview
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import SurveyInfoStep from './SurveyInfoStep.vue';
import QuestionBuilder from './QuestionBuilder.vue';
import DeliverySettings from './DeliverySettings.vue';

// Map legacy FA icon names to Material icons
const FA_TO_MATERIAL = {
  'fa-anchor':           'anchor',
  'fa-exchange-alt':     'swap_horiz',
  'fa-ship':             'directions_boat',
  'fa-wrench':           'build',
  'fa-redo':             'refresh',
  'fa-user-check':       'how_to_reg',
  'fa-calendar-check':   'event_available',
  'fa-money-check':      'payment',
  'fa-handshake':        'handshake',
  'fa-hand-holding-usd': 'monetization_on',
  'fa-home':             'home',
  'fa-door-open':        'door_front',
  'fa-poll':             'poll',
  'fa-calculator':       'calculate',
  'fa-user-plus':        'person_add',
  'fa-building':         'business',
  'fa-chart-line':       'trending_up',
  'fa-file-alt':         'description',
  'fa-plus':             'add',
};

export default {
  name: 'SurveyCreator',
  components: { SurveyInfoStep, QuestionBuilder, DeliverySettings },
  props: {
    users:       { type: Array,          default: () => [] },
    team:        { type: Object,         default: null },
    subscription:{ type: Object,         default: null },
    initialData: { type: Object,         default: () => ({}) },
    isEditing:   { type: Boolean,        default: false },
    surveyId:    { type: [Number, String], default: null },
  },
  data() {
    const mergedData = {
      title: '',
      type: 'feedback',
      status: false,
      assigned_user_id: null,
      description: '',
      public_description: '',
      visibility: 'public',
      questions: [],
      delivery_method: 'email',
      automation_trigger: 'manual',
      automation_config: { days: 7 },
      thank_you_message: 'Thank you for completing this survey!',
      redirect_url: '',
      privacy_settings: {
        anonymous: false,
        require_email: false,
        one_response_per_user: false,
        show_results: false,
      },
      ...this.initialData,
    };

    if (this.isEditing) {
      mergedData.status = mergedData.status ? 1 : 0;
    }

    return {
      currentStep: 1,
      saving: false,
      showPreview: false,
      showDraftConfirmation: false,
      showTemplateSelector: !this.isEditing,
      surveyStarted: this.isEditing,
      loadingTemplates: false,
      templates: [],
      selectedCategory: 'all',
      steps: [
        { number: 1, title: 'Survey Info',  component: 'SurveyInfoStep' },
        { number: 2, title: 'Questions',    component: 'QuestionBuilder' },
        { number: 3, title: 'Delivery',     component: 'DeliverySettings' },
      ],
      surveyData: mergedData,
    };
  },
  computed: {
    currentStepComponent() {
      return this.steps[this.currentStep - 1].component;
    },
    templateCategories() {
      const categories = new Set();
      this.templates.forEach(t => { if (t.category) categories.add(t.category); });
      return Array.from(categories).sort();
    },
    filteredTemplates() {
      if (this.selectedCategory === 'all') return this.templates;
      return this.templates.filter(t => t.category === this.selectedCategory);
    },
  },
  mounted() {
    this.loadTemplates();
  },
  methods: {
    templateIcon(faIcon) {
      return FA_TO_MATERIAL[faIcon] ?? 'description';
    },

    async loadTemplates() {
      this.loadingTemplates = true;
      try {
        const response = await axios.get(route('surveyTemplates'));
        this.templates = response.data || [];
      } catch (error) {
        console.error('Error loading templates:', error);
        alert('Failed to load survey templates');
      } finally {
        this.loadingTemplates = false;
      }
    },

    selectTemplate(template) {
      this.surveyData.title              = template.name;
      this.surveyData.description        = template.description;
      this.surveyData.public_description = template.public_description;
      this.surveyData.type               = template.type || 'custom';

      if (template.questions?.length > 0) {
        this.surveyData.questions = template.questions.map((q, index) => ({
          id:                `template_${Date.now()}_${index}`,
          type:              q.type,
          label:             q.label,
          required:          q.required || false,
          order:             q.order || index,
          options:           q.options || null,
          config:            q.config || null,
          conditional_logic: q.conditional_logic || null,
        }));
      }

      this.showTemplateSelector = false;
      this.surveyStarted        = true;
      this.currentStep          = 1;

      alert(`Template "${template.name}" has been loaded! You can customize it as needed.`);
    },

    startFromScratch() {
      this.showTemplateSelector = false;
      this.surveyStarted        = true;
      this.currentStep          = 1;
    },

    getStepClass(stepNumber) {
      if (stepNumber < this.currentStep)  return 'bg-blue-600 text-white';
      if (stepNumber === this.currentStep) return 'bg-blue-600 text-white ring-4 ring-blue-200 dark:ring-blue-800';
      return 'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-300';
    },

    nextStep() {
      if (this.validateCurrentStep() && this.currentStep < this.steps.length) {
        this.currentStep++;
      }
    },

    previousStep() {
      if (this.currentStep > 1) this.currentStep--;
    },

    validateCurrentStep() {
      if (this.currentStep === 1) {
        if (!this.surveyData.title?.trim()) { alert('Please enter a survey title'); return false; }
        if (!this.surveyData.type)          { alert('Please select a survey type'); return false; }
      }
      if (this.currentStep === 2) {
        if (this.surveyData.questions.length === 0) { alert('Please add at least one question'); return false; }
        if (this.surveyData.questions.some(q => !q.label?.trim())) { alert('All questions must have a label'); return false; }
      }
      return true;
    },

    handleSaveDraft() {
      if (this.isEditing && this.initialData.status === true) {
        this.showDraftConfirmation = true;
      } else {
        this.saveDraft(false);
      }
    },

    apiErrorMessage(error) {
      if (error.response?.data?.errors)  return Object.values(error.response.data.errors).flat().join('\n');
      if (error.response?.data?.message) return error.response.data.message;
      return error.message ?? 'Unknown error';
    },

    navigateToShow(uuid) {
      window.location.href = route('surveysShow', { id: uuid });
    },

    async saveDraft(keepLive = false) {
      this.saving = true;
      try {
        let response;
        if (this.isEditing && this.surveyId) {
          response = await axios.put(route('surveysUpdate', { id: this.surveyId }), { ...this.surveyData, status: keepLive });
        } else {
          response = await axios.post(route('surveysStore'), { ...this.surveyData, status: false });
        }
        if (response.data) {
          alert(keepLive ? 'Survey saved successfully!' : 'Draft saved successfully!');
          this.navigateToShow(response.data.uuid || this.surveyId);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error saving draft:\n' + this.apiErrorMessage(error));
      } finally {
        this.saving = false;
        this.showDraftConfirmation = false;
      }
    },

    confirmSaveDraft()  { this.saveDraft(false); },
    saveAndKeepLive()   { this.saveDraft(true); },

    async saveSurvey() {
      if (!this.validateCurrentStep()) return;
      this.saving = true;
      try {
        const response = await axios.put(route('surveysUpdate', { id: this.surveyId }), {
          ...this.surveyData,
          status: Boolean(this.surveyData.status),
        });
        if (response.data) {
          alert('Survey saved successfully!');
          this.navigateToShow(response.data.uuid || this.surveyId);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error saving survey:\n' + this.apiErrorMessage(error));
      } finally {
        this.saving = false;
      }
    },

    async publishSurvey() {
      if (!this.validateCurrentStep()) return;
      this.saving = true;
      try {
        const url    = this.isEditing ? route('surveysUpdate', { id: this.surveyId }) : route('surveysStore');
        const method = this.isEditing ? 'put' : 'post';
        const response = await axios[method](url, { ...this.surveyData, status: true });
        if (response.data) {
          alert('Survey published successfully!');
          this.navigateToShow(response.data.uuid || this.surveyId);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error publishing survey:\n' + this.apiErrorMessage(error));
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.survey-creator {
  max-width: 1200px;
  margin: 0 auto;
}
</style>