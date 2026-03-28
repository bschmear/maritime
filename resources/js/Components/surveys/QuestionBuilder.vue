<template>
  <div class="question-builder w-full min-h-[500px]">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Build Your Questions</h2>
      <button
        @click="togglePreviewMode"
        type="button"
        class="btn btn-outline sm"
      >
        <i :class="previewMode ? 'fas fa-edit' : 'fas fa-eye'" class="mr-2"></i>
        {{ previewMode ? 'Edit Mode' : 'Preview Mode' }}
      </button>
    </div>

    <!-- Preview Mode -->
    <div v-if="previewMode" class="space-y-4">
      <div 
        v-for="(question, index) in localData.questions" 
        :key="question.id"
        class="bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 rounded-lg p-6"
      >
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
            {{ index + 1 }}. {{ question.label }}
            <span v-if="question.required" class="text-red-500">*</span>
          </label>
          <div v-if="question.conditional_logic" class="text-xs text-blue-600 dark:text-blue-400 mt-2">
            <p class="flex items-center mb-1">
              <i class="fas fa-code-branch mr-1"></i>
              <span class="font-semibold">Conditional Logic:</span>
            </p>
            <div class="ml-5 space-y-1">
              <p v-for="(rule, rIndex) in getConditionalRules(question)" :key="rIndex">
                <span v-if="rIndex > 0" class="font-semibold mr-1">OR</span>
                Question {{ (rule.question ?? -1) + 1 }} = "{{ rule.equals }}"
              </p>
            </div>
          </div>
        </div>
        
        <div v-if="question.type === 'text'">
          <input type="text" class="input-style w-full" placeholder="Your answer..." disabled>
        </div>
        <div v-else-if="question.type === 'textarea'">
          <textarea class="input-style w-full" rows="4" placeholder="Your answer..." disabled></textarea>
        </div>
        <div v-else-if="question.type === 'multiple_choice'">
          <div v-for="option in question.options" :key="option" class="flex items-center mb-2">
            <input type="radio" :name="`preview_${question.id}`" disabled class="form-radio mr-2">
            <span class="text-gray-700 dark:text-gray-300">{{ option }}</span>
          </div>
        </div>
        <div v-else-if="question.type === 'rating'">
          <div class="flex space-x-2">
            <button 
              v-for="n in (question.config?.max || 5)" 
              :key="n" 
              class="w-10 h-10 border dark:border-gray-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700" 
              disabled
            >
              {{ n }}
            </button>
          </div>
        </div>
        <div v-else-if="question.type === 'dropdown'">
          <select class="input-style w-full" disabled>
            <option>Select an option...</option>
            <option v-for="option in question.options" :key="option">{{ option }}</option>
          </select>
        </div>
        <div v-else-if="question.type === 'nps'">
          <div class="flex space-x-1">
            <button 
              v-for="n in 11" 
              :key="n" 
              class="w-10 h-10 border dark:border-gray-600 rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-700" 
              disabled
            >
              {{ n - 1 }}
            </button>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
            <span>Not likely</span>
            <span>Extremely likely</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Mode -->
    <div v-else>
      <!-- Question List -->
      <div id="questions-list" class="space-y-4">
        <div 
          v-for="(question, index) in localData.questions" 
          :key="question.id"
          :data-id="question.id"
          class="question-item bg-white dark:bg-gray-800 border-2 dark:border-gray-700 rounded-lg p-4 hover:border-blue-300 dark:hover:border-blue-600 transition-colors"
        >
            <div class="flex items-start space-x-3">
              <!-- Drag Handle -->
              <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mt-2">
                <i class="fas fa-grip-vertical"></i>
              </div>

              <!-- Question Content -->
              <div class="flex-1">
                <!-- Question Header -->
                <div class="flex items-center justify-between mb-3">
                  <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Question {{ index + 1 }}
                  </span>
                  <div class="flex items-center space-x-2">
                    <!-- Duplicate -->
                    <button
                      @click="duplicateQuestion(index)"
                      type="button"
                      class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                      title="Duplicate"
                    >
                      <i class="fas fa-copy"></i>
                    </button>
                    <!-- Delete -->
                    <button
                      @click="deleteQuestion(index)"
                      type="button"
                      class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete"
                    >
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </div>

                <!-- Question Type Selector -->
                <div class="mb-3">
                <div class="flex">
                  <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border rounded-e-0 border-gray-300 border-e-0 rounded-s-md dark:bg-gray-600 dark:text-gray-400 dark:border-gray-600">
                   <!--  <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 13a8.949 8.949 0 0 1-4.951-1.488A3.987 3.987 0 0 1 9 13h2a3.987 3.987 0 0 1 3.951 3.512A8.949 8.949 0 0 1 10 18Z"/>
                    </svg> -->
                    Question Type
                  </span>

                  <select
                    v-model="question.type"
                    class="input-style w-full flex-1 !rounded-none !rounded-e-lg"
                    @change="onQuestionTypeChange(question)"
                  >
                    <option value="text">Text (Short Answer)</option>
                    <option value="textarea">Textarea (Long Answer)</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="rating">Rating</option>
                    <option value="dropdown">Dropdown</option>
                    <option value="nps">NPS (Net Promoter Score)</option>
                  </select>
                </div>


                </div>

                <!-- Question Label -->
                <div class="mb-3">
                  <input
                    v-model="question.label"
                    type="text"
                    class="input-style w-full"
                    placeholder="Enter your question..."
                  />
                </div>

                <!-- Options for Multiple Choice / Dropdown -->
                <div v-if="question.type === 'multiple_choice' || question.type === 'dropdown'" class="mb-3">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Options
                  </label>
                  <div class="space-y-2">
                    <div 
                      v-for="(option, optIndex) in question.options" 
                      :key="optIndex"
                      class="flex items-center space-x-2"
                    >
                      <input
                        v-model="question.options[optIndex]"
                        type="text"
                        class="input-style flex-1"
                        placeholder="Enter option..."
                      />
                      <button
                        @click="removeOption(question, optIndex)"
                        type="button"
                        class="text-red-500 hover:text-red-700"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                    <button
                      @click="addOption(question)"
                      type="button"
                      class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400"
                    >
                      <i class="fas fa-plus mr-1"></i> Add Option
                    </button>
                  </div>
                </div>

                <!-- Rating Config -->
                <div v-if="question.type === 'rating'" class="mb-3">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Max Rating
                  </label>
                  <select v-model="question.config.max" class="input-style ">
                    <option :value="5">5 Stars</option>
                    <option :value="10">10 Points</option>
                  </select>
                </div>

                <!-- Settings -->
                <div class="flex items-center space-x-4 pt-3 border-t dark:border-gray-700">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="question.required"
                      type="checkbox"
                      class="form-checkbox text-blue-600 rounded"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Required</span>
                  </label>

                  <!-- Conditional Logic Toggle -->
                  <button
                    @click="toggleConditionalLogic(question)"
                    type="button"
                    class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400"
                  >
                    <i class="fas fa-code-branch mr-1"></i>
                    {{ question.conditionalLogic ? 'Remove' : 'Add' }} Conditional Logic
                  </button>
                </div>

                <!-- Conditional Logic Editor -->
                <div v-if="question.conditionalLogic" class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                  <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Show this question only if:
                  </p>
                  
                  <!-- Condition Rules -->
                  <div class="space-y-2">
                    <div 
                      v-for="(rule, ruleIndex) in getConditionalRules(question)" 
                      :key="ruleIndex"
                      class="flex items-center space-x-2"
                    >
                      <!-- OR label for subsequent conditions -->
                      <span v-if="ruleIndex > 0" class="text-xs font-semibold text-blue-700 dark:text-blue-300 w-8">OR</span>
                      <span v-else class="w-8"></span>
                      
                      <select 
                        v-model="rule.question"
                        @change="updateConditionalLogic(question)"
                        class="input-style flex-1"
                      >
                        <option value="">Select a question...</option>
                        <option 
                          v-for="(q, qIndex) in localData.questions.slice(0, index)" 
                          :key="q.id"
                          :value="qIndex"
                        >
                          Question {{ qIndex + 1 }}: {{ q.label || 'Untitled' }}
                        </option>
                      </select>
                      
                      <span class="text-sm text-gray-700 dark:text-gray-300">equals</span>
                      
                      <input 
                        v-model="rule.equals"
                        @input="updateConditionalLogic(question)"
                        type="text" 
                        class="form-input text-sm flex-1" 
                        placeholder="Value..."
                      >
                      
                      <!-- Remove button -->
                      <button
                        v-if="getConditionalRules(question).length > 1"
                        @click="removeConditionalRule(question, ruleIndex)"
                        type="button"
                        class="text-red-600 hover:text-red-700 dark:text-red-400 p-2"
                        title="Remove condition"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                      <span v-else class="w-8"></span>
                    </div>
                  </div>
                  
                  <!-- Add Condition Button -->
                  <button
                    @click="addConditionalRule(question)"
                    type="button"
                    class="mt-3 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 flex items-center"
                  >
                    <i class="fas fa-plus mr-1"></i>
                    Add OR Condition
                  </button>
                  
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                    <i class="fas fa-lightbulb mr-1"></i>
                    Values must match exactly (case-sensitive). Multiple conditions use OR logic.
                  </p>
                </div>
              </div>
            </div>
          </div>
      </div>
    </div>

    <!-- Empty State -->
    <div 
      v-if="localData.questions.length === 0" 
      class="text-center py-12 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700"
    >
      <i class="fas fa-question-circle text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
      <p class="text-gray-600 dark:text-gray-400 mb-4">No questions added yet</p>
      <button @click="addQuestion" type="button" class="btn btn-primary">
        <i class="fas fa-plus mr-2"></i> Add Your First Question
      </button>
    </div>

    <!-- Add Question Button -->
    <div v-else class="flex justify-center mt-6">
      <button
        @click="addQuestion"
        type="button"
        class="btn btn-primary"
      >
        <i class="fas fa-plus mr-2"></i> Add Question
      </button>
    </div>
  </div>

</template>

<script>
import Sortable from 'sortablejs';

export default {
  name: 'QuestionBuilder',
  props: {
    modelValue: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      previewMode: false,
      questionIdCounter: 1,
      sortableInstance: null
    };
  },
  computed: {
    localData() {
      // Ensure questions array exists
      if (!this.modelValue.questions) {
        this.modelValue.questions = [];
      }
      
      // Normalize conditional_logic to conditionalLogic for each question
      this.modelValue.questions.forEach(question => {
        // If conditional_logic exists and is not null/empty, enable the UI flag
        if (question.conditional_logic && typeof question.conditional_logic === 'object') {
          question.conditionalLogic = true;
        } else if (!question.hasOwnProperty('conditionalLogic')) {
          question.conditionalLogic = false;
        }
      });
      
      return this.modelValue;
    }
  },
  methods: {
    addQuestion() {
      const newQuestion = {
        id: `q_${Date.now()}_${this.questionIdCounter++}`,
        type: 'text',
        label: '',
        required: false,
        order: this.localData.questions.length,
        options: [],
        config: { max: 5 },
        conditionalLogic: false,
        conditional_logic: null
      };
      this.localData.questions.push(newQuestion);
    },
    duplicateQuestion(index) {
      const originalQuestion = this.localData.questions[index];
      const duplicatedQuestion = {
        ...JSON.parse(JSON.stringify(originalQuestion)),
        id: `q_${Date.now()}_${this.questionIdCounter++}`,
        label: `${originalQuestion.label} (Copy)`
      };
      this.localData.questions.splice(index + 1, 0, duplicatedQuestion);
      this.reorderQuestions();
    },
    deleteQuestion(index) {
      if (confirm('Are you sure you want to delete this question?')) {
        this.localData.questions.splice(index, 1);
        this.reorderQuestions();
      }
    },
    addOption(question) {
      if (!question.options) {
        question.options = [];
      }
      question.options.push('');
    },
    removeOption(question, optIndex) {
      question.options.splice(optIndex, 1);
    },
    reorderQuestions() {
      this.localData.questions.forEach((question, index) => {
        question.order = index;
      });
    },
    onQuestionTypeChange(question) {
      // Initialize options for multiple choice/dropdown
      if ((question.type === 'multiple_choice' || question.type === 'dropdown') && !question.options?.length) {
        question.options = ['Option 1', 'Option 2', 'Option 3'];
      }
      // Initialize config for rating
      if (question.type === 'rating' && !question.config) {
        question.config = { max: 5 };
      }
    },
    getConditionalRules(question) {
      if (!question.conditional_logic) {
        return [{ question: '', equals: '' }];
      }
      
      // Handle new format with rules array
      if (question.conditional_logic.rules && Array.isArray(question.conditional_logic.rules)) {
        return question.conditional_logic.rules;
      }
      
      // Handle old format with equals_any
      if (question.conditional_logic.equals_any && Array.isArray(question.conditional_logic.equals_any)) {
        return question.conditional_logic.equals_any.map(value => ({
          question: question.conditional_logic.show_if_question ?? '',
          equals: value
        }));
      }
      
      // Handle old single-condition format
      if (question.conditional_logic.show_if_question !== undefined || question.conditional_logic.equals) {
        return [{
          question: question.conditional_logic.show_if_question ?? '',
          equals: question.conditional_logic.equals ?? ''
        }];
      }
      
      // Default
      return [{ question: '', equals: '' }];
    },
    toggleConditionalLogic(question) {
      question.conditionalLogic = !question.conditionalLogic;
      
      if (question.conditionalLogic) {
        // Initialize conditional logic with rules array
        question.conditional_logic = {
          rules: [
            { question: '', equals: '' }
          ]
        };
      } else {
        // Clear conditional logic when disabling
        question.conditional_logic = null;
      }
    },
    updateConditionalLogic(question) {
      // Sync the rules array format
      const rules = this.getConditionalRules(question);
      question.conditional_logic = {
        rules: rules
      };
    },
    addConditionalRule(question) {
      if (!question.conditional_logic) {
        question.conditional_logic = { rules: [] };
      }
      if (!question.conditional_logic.rules) {
        question.conditional_logic.rules = [];
      }
      question.conditional_logic.rules.push({ question: '', equals: '' });
    },
    removeConditionalRule(question, ruleIndex) {
      if (question.conditional_logic && question.conditional_logic.rules) {
        question.conditional_logic.rules.splice(ruleIndex, 1);
        if (question.conditional_logic.rules.length === 0) {
          question.conditional_logic.rules = [{ question: '', equals: '' }];
        }
      }
    },
    togglePreviewMode() {
      this.previewMode = !this.previewMode;
      
      // Reinitialize sortable when switching back to edit mode
      if (!this.previewMode) {
        this.$nextTick(() => {
          this.initSortable();
        });
      }
    },
    initSortable() {
      const el = document.getElementById('questions-list');
      if (!el || this.previewMode) return;
      
      // Destroy existing instance if any
      if (this.sortableInstance) {
        this.sortableInstance.destroy();
      }
      
      // Create new sortable instance
      this.sortableInstance = Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'opacity-50',
        dragClass: 'opacity-75',
        onEnd: (evt) => {
          const { oldIndex, newIndex } = evt;
          
          if (oldIndex !== newIndex) {
            // Move the question in the array
            const movedQuestion = this.localData.questions.splice(oldIndex, 1)[0];
            this.localData.questions.splice(newIndex, 0, movedQuestion);
            
            // Reorder questions
            this.reorderQuestions();
          }
        }
      });
    }
  },
  mounted() {
    // Initialize Sortable
    this.$nextTick(() => {
      this.initSortable();
    });
  },
  beforeUnmount() {
    // Destroy sortable instance
    if (this.sortableInstance) {
      this.sortableInstance.destroy();
    }
  },
  updated() {
    // Reinitialize sortable when questions change
    if (!this.previewMode && this.localData.questions.length > 0) {
      this.$nextTick(() => {
        this.initSortable();
      });
    }
  }
};
</script>

<style scoped>
.drag-handle {
  cursor: grab;
}

.drag-handle:active {
  cursor: grabbing;
}
</style>

