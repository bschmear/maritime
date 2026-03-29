<template>
  <div class="survey-creator">
    <!-- Template Selector Modal (Show first on create) -->
    <div
      v-if="showTemplateSelector"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50 dark:bg-opacity-80"
      :class="{ 'backdrop-blur-sm': !isEditing && !surveyStarted }"
    >
      <div class="relative w-full max-w-6xl max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow dark:bg-gray-800">
        <!-- Modal header -->
        <div class="sticky top-0 flex items-center justify-between p-4 border-b rounded-t bg-white dark:bg-gray-800 dark:border-gray-700 z-10">
          <div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
              <i class="fas fa-file-alt mr-2 text-blue-600 dark:text-blue-400"></i>
              Choose a Survey Template
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Start with a pre-built template or create from scratch</p>
          </div>
          <button
            v-if="surveyStarted"
            @click="showTemplateSelector = false"
            type="button"
            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>

        <!-- Modal body -->
        <div class="p-6">
          <div v-if="loadingTemplates" class="flex justify-center items-center py-12">
            <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
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
                    class="inline-block p-4 border-b-2 rounded-t-lg"
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
                    class="inline-block p-4 border-b-2 rounded-t-lg"
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
                  <i class="fas fa-plus text-2xl"></i>
                </div>
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Start from Scratch</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 flex-grow">Create a completely custom survey with your own questions</p>
                <div class="mt-4 text-blue-600 dark:text-blue-400 font-medium text-sm group-hover:underline">
                  Create Custom Survey <i class="fas fa-arrow-right ml-1"></i>
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
                    <i :class="template.icon" class="text-2xl fas"></i>
                  </div>
                  <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded dark:bg-blue-900 dark:text-blue-300">
                    {{ template.questions?.length || 0 }} questions
                  </span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-2">{{ template.name }}</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 flex-grow line-clamp-2">{{ template.description }}</p>
                <div class="mt-4 text-blue-600 dark:text-blue-400 font-medium text-sm group-hover:underline">
                  Use This Template <i class="fas fa-arrow-right ml-1"></i>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div v-if="surveyStarted" class="flex items-center justify-end p-4 border-t border-gray-200 rounded-b dark:border-gray-700">
          <button
            @click="showTemplateSelector = false"
            type="button"
            class="text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 font-medium rounded-lg text-sm px-5 py-2.5"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Main Survey Creator Content (only show after template selection) -->
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
                class="flex items-center justify-center w-10 h-10 rounded-full lg:h-12 lg:w-12 shrink-0 transition-colors"
                :class="getStepClass(step.number)"
              >
                <i v-if="step.number < currentStep" class="fas fa-check"></i>
                <span v-else>{{ step.number }}</span>
              </span>
              <span
                class="mt-2 text-xs font-medium text-center"
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
                :class="currentStep > step.number ? 'bg-blue-600 dark:bg-blue-500 w-full' : 'bg-gray-200 dark:bg-gray-700 w-0'"
              ></div>
            </div>
          </li>
        </ol>
      </div>

      <!-- Step Content -->
      <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg shadow-sm p-6 flex justify-center">
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
            class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Previous
          </button>

          <!-- Choose Template button (only on step 1 and 2) -->
          <button
            v-if="!isEditing && (currentStep === 1 || currentStep === 2)"
            @click="showTemplateSelector = true"
            type="button"
            class="text-gray-600 bg-gray-50 border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:border-gray-600 dark:focus:ring-gray-700"
          >
            <i class="fas fa-file-alt mr-2"></i>
            Choose Template
          </button>
        </div>

        <div class="flex flex-wrap gap-3 items-center">
          <!-- Next Button (Not on last step) -->
          <button
            v-if="currentStep < steps.length"
            @click="nextStep"
            type="button"
            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
          >
            Next
            <i class="fas fa-arrow-right ml-2"></i>
          </button>

          <!-- CREATE MODE: Save as Draft and Publish buttons (only on last step) -->
          <template v-if="!isEditing && currentStep === steps.length">
            <button
              @click="handleSaveDraft"
              type="button"
              class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700"
              :disabled="saving"
            >
              <i class="fas fa-save mr-2"></i>
              Save as Draft
            </button>
            
            <button
              @click="publishSurvey"
              type="button"
              class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-green-600 dark:hover:bg-green-700 focus:outline-none dark:focus:ring-green-800"
              :disabled="saving"
            >
              <i class="fas fa-rocket mr-2"></i>
              Save and Publish
            </button>
          </template>

          <!-- EDIT MODE: Save Updates button (visible on any step) -->
          <button
            v-if="isEditing"
            @click="saveSurvey"
            type="button"
            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
            :disabled="saving"
          >
            <i class="fas fa-save mr-2"></i>
            Save Updates
          </button>
        </div>
      </div>
    </div>

    <!-- Draft Confirmation Modal -->
    <div
      v-if="showDraftConfirmation"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50 dark:bg-opacity-80"
      @click.self="showDraftConfirmation = false"
    >
      <div class="relative w-full max-w-md bg-white rounded-lg shadow dark:bg-gray-700">
        <button
          @click="showDraftConfirmation = false"
          type="button"
          class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
        >
          <i class="fas fa-times"></i>
        </button>
        <div class="p-6 text-center">
          <i class="fas fa-exclamation-triangle mx-auto mb-4 text-yellow-500 text-5xl dark:text-yellow-400"></i>
          <h3 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">This Survey is Currently Live</h3>
          <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            Saving as draft will disable this survey and make it unavailable to respondents.
            Are you sure you want to continue?
          </p>
          <div class="flex gap-3 justify-center">
            <button
              @click="confirmSaveDraft"
              type="button"
              class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:outline-none focus:ring-yellow-300 dark:focus:ring-yellow-800 font-medium rounded-lg text-sm px-5 py-2.5"
            >
              <i class="fas fa-save mr-2"></i>
              Save as Draft
            </button>
            <button
              @click="saveAndKeepLive"
              type="button"
              class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 dark:focus:ring-green-800 font-medium rounded-lg text-sm px-5 py-2.5"
            >
              <i class="fas fa-check mr-2"></i>
              Save and Keep Live
            </button>
            <button
              @click="showDraftConfirmation = false"
              type="button"
              class="text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 font-medium rounded-lg text-sm px-5 py-2.5"
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
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50 dark:bg-opacity-80"
      @click.self="showPreview = false"
    >
      <div class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow dark:bg-gray-800">
        <!-- Modal header -->
        <div class="sticky top-0 flex items-center justify-between p-4 border-b rounded-t bg-white dark:bg-gray-800 dark:border-gray-700 z-10">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-eye mr-2 text-blue-600 dark:text-blue-400"></i>
            Survey Preview
          </h3>
          <button
            @click="showPreview = false"
            type="button"
            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
          >
            <i class="fas fa-times"></i>
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
              class="p-4 border border-gray-200 rounded-lg dark:border-gray-700"
            >
              <label class="block mb-3 text-sm font-medium text-gray-900 dark:text-white">
                <span class="inline-flex items-center justify-center w-6 h-6 mr-2 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300">
                  {{ index + 1 }}
                </span>
                {{ question.label }}
                <span v-if="question.required" class="text-red-600 dark:text-red-500">*</span>
              </label>

              <!-- Text Input -->
              <div v-if="question.type === 'text'">
                <input
                  type="text"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                  placeholder="Your answer..."
                  disabled
                >
              </div>

              <!-- Multiple Choice -->
              <div v-else-if="question.type === 'multiple_choice'" class="space-y-2">
                <div v-for="(option, optIndex) in question.options" :key="optIndex" class="flex items-center">
                  <input
                    :id="`preview_${question.id}_${optIndex}`"
                    type="radio"
                    :name="`preview_${question.id}`"
                    disabled
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                  >
                  <label
                    :for="`preview_${question.id}_${optIndex}`"
                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300"
                  >
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
                    class="w-10 h-10 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700"
                    disabled
                  >
                    {{ n }}
                  </button>
                </div>
              </div>

              <!-- Dropdown -->
              <div v-else-if="question.type === 'dropdown'">
                <select
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                  disabled
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
                    class="w-10 h-10 text-xs font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700"
                    :class="{
                      'border-red-500 dark:border-red-500': n <= 7,
                      'border-yellow-500 dark:border-yellow-500': n > 7 && n <= 9,
                      'border-green-500 dark:border-green-500': n > 9
                    }"
                    disabled
                  >
                    {{ n - 1 }}
                  </button>
                </div>
                <div class="flex justify-between mt-3 text-xs text-gray-500 dark:text-gray-400">
                  <span><i class="fas fa-frown mr-1"></i>Not likely</span>
                  <span>Extremely likely<i class="fas fa-smile ml-1"></i></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div class="flex items-center p-4 border-t border-gray-200 rounded-b dark:border-gray-700">
          <button
            @click="showPreview = false"
            type="button"
            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
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

export default {
  name: 'SurveyCreator',
  components: {
    SurveyInfoStep,
    QuestionBuilder,
    DeliverySettings,
  },
  props: {
    users: {
      type: Array,
      default: () => []
    },
    team: {
      type: Object,
      default: null
    },
    subscription: {
      type: Object,
      default: null
    },
    initialData: {
      type: Object,
      default: () => ({})
    },
    isEditing: {
      type: Boolean,
      default: false
    },
    surveyId: {
      type: [Number, String],
      default: null
    }
  },
  data() {
    // Merge initialData and ensure status is numeric for radio buttons
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
        show_results: false
      },
      ...this.initialData
    };

    // Convert status to numeric for radio buttons (1 = active, 0 = inactive)
    if (this.isEditing) {
      mergedData.status = mergedData.status ? 1 : 0;
    }

    return {
      currentStep: 1,
      saving: false,
      showPreview: false,
      showDraftConfirmation: false,
      showTemplateSelector: !this.isEditing, // Show on create, hide on edit
      surveyStarted: this.isEditing, // Start immediately if editing
      loadingTemplates: false,
      templates: [],
      selectedCategory: 'all',
      steps: [
        { number: 1, title: 'Survey Info', component: 'SurveyInfoStep' },
        { number: 2, title: 'Questions', component: 'QuestionBuilder' },
        { number: 3, title: 'Delivery', component: 'DeliverySettings' },
      ],
      surveyData: mergedData
    };
  },
  computed: {
    currentStepComponent() {
      return this.steps[this.currentStep - 1].component;
    },
    templateCategories() {
      const categories = new Set();
      this.templates.forEach(template => {
        if (template.category) {
          categories.add(template.category);
        }
      });
      return Array.from(categories).sort();
    },
    filteredTemplates() {
      if (this.selectedCategory === 'all') {
        return this.templates;
      }
      return this.templates.filter(template => template.category === this.selectedCategory);
    }
  },
  mounted() {
    this.loadTemplates();
  },
  methods: {
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
      // Apply template to surveyData
      this.surveyData.title = template.name;
      this.surveyData.description = template.description;
      this.surveyData.public_description = template.public_description;
      this.surveyData.type = template.type || 'custom';

      // Map template questions to survey format
      if (template.questions && template.questions.length > 0) {
        this.surveyData.questions = template.questions.map((q, index) => ({
          id: `template_${Date.now()}_${index}`,
          type: q.type,
          label: q.label,
          required: q.required || false,
          order: q.order || index,
          options: q.options || null,
          config: q.config || null,
          conditional_logic: q.conditional_logic || null
        }));
      }

      // Close modal and show main creator
      this.showTemplateSelector = false;
      this.surveyStarted = true;
      this.currentStep = 1;

      // Show success message
      alert(`Template "${template.name}" has been loaded! You can customize it as needed.`);
    },
    startFromScratch() {
      // Close modal and show main creator with empty form
      this.showTemplateSelector = false;
      this.surveyStarted = true;
      this.currentStep = 1;
    },
    getStepClass(stepNumber) {
      if (stepNumber < this.currentStep) {
        return 'bg-blue-600 text-white';
      } else if (stepNumber === this.currentStep) {
        return 'bg-blue-600 text-white ring-4 ring-blue-200 dark:ring-blue-800';
      } else {
        return 'bg-gray-300 text-gray-600 dark:bg-gray-600 dark:text-gray-300';
      }
    },
    nextStep() {
      if (this.validateCurrentStep()) {
        if (this.currentStep < this.steps.length) {
          this.currentStep++;
        }
      }
    },
    previousStep() {
      if (this.currentStep > 1) {
        this.currentStep--;
      }
    },
    validateCurrentStep() {
      // Step 1 validation
      if (this.currentStep === 1) {
        if (!this.surveyData.title || this.surveyData.title.trim() === '') {
          alert('Please enter a survey title');
          return false;
        }
        if (!this.surveyData.type) {
          alert('Please select a survey type');
          return false;
        }
      }

      // Step 2 validation
      if (this.currentStep === 2) {
        if (this.surveyData.questions.length === 0) {
          alert('Please add at least one question');
          return false;
        }

        // Check if all questions have labels
        const invalidQuestions = this.surveyData.questions.filter(q => !q.label || q.label.trim() === '');
        if (invalidQuestions.length > 0) {
          alert('All questions must have a label');
          return false;
        }
      }

      return true;
    },
    handleSaveDraft() {
      // Check if survey is currently live and editing
      if (this.isEditing && this.initialData.status === true) {
        // Show confirmation modal
        this.showDraftConfirmation = true;
      } else {
        // Just save as draft
        this.saveDraft(false);
      }
    },

    apiErrorMessage(error) {
      if (error.response?.data) {
        if (error.response.data.errors) {
          return Object.values(error.response.data.errors).flat().join('\n');
        }
        if (error.response.data.message) return error.response.data.message;
      }
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
          response = await axios.put(route('surveysUpdate', { id: this.surveyId }), {
            ...this.surveyData,
            status: keepLive,
          });
        } else {
          response = await axios.post(route('surveysStore'), {
            ...this.surveyData,
            status: false,
          });
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

    confirmSaveDraft() {
      this.saveDraft(false);
    },

    saveAndKeepLive() {
      this.saveDraft(true);
    },

    async saveSurvey() {
      if (!this.validateCurrentStep()) {
        return;
      }

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
      if (!this.validateCurrentStep()) {
        return;
      }

      this.saving = true;
      try {
        const url    = this.isEditing ? route('surveysUpdate', { id: this.surveyId }) : route('surveysStore');
        const method = this.isEditing ? 'put' : 'post';

        const response = await axios[method](url, {
          ...this.surveyData,
          status: true,
        });

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
    }

  }
};
</script>

<style scoped>
.survey-creator {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
