<template>
  <div class="w-full min-h-[500px]">

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Build Your Questions</h2>
      <button
        @click="togglePreviewMode"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
      >
        <span class="material-icons text-[18px]">{{ previewMode ? 'edit' : 'visibility' }}</span>
        {{ previewMode ? 'Edit Mode' : 'Preview Mode' }}
      </button>
    </div>

    <!-- ── PREVIEW MODE ── -->
    <div v-if="previewMode" class="space-y-4">
      <div
        v-for="(question, index) in localData.questions"
        :key="question.id"
        class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6"
      >
        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-3">
          {{ index + 1 }}. {{ question.label || 'Untitled question' }}
          <span v-if="question.required" class="text-red-500 ml-0.5">*</span>
        </label>

        <!-- Conditional logic badge -->
        <div v-if="question.conditional_logic" class="flex items-start gap-1.5 text-xs text-blue-600 dark:text-blue-400 mb-3">
          <span class="material-icons text-[14px] mt-0.5">account_tree</span>
          <div>
            <span class="font-semibold">Conditional: </span>
            <span v-for="(rule, rIndex) in getConditionalRules(question)" :key="rIndex">
              <span v-if="rIndex > 0" class="font-semibold"> OR </span>
              Q{{ (rule.question ?? -1) + 1 }} = "{{ rule.equals }}"
            </span>
          </div>
        </div>

        <!-- text -->
        <input v-if="question.type === 'text'" type="text" disabled placeholder="Your answer…"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-400 dark:text-gray-500 cursor-not-allowed" />

        <!-- textarea -->
        <textarea v-else-if="question.type === 'textarea'" disabled rows="3" placeholder="Your answer…"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-400 dark:text-gray-500 cursor-not-allowed resize-none" />

        <!-- multiple choice -->
        <div v-else-if="question.type === 'multiple_choice'" class="space-y-2">
          <label v-for="option in question.options" :key="option" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="radio" disabled class="accent-blue-600" />
            {{ option }}
          </label>
        </div>

        <!-- rating -->
        <div v-else-if="question.type === 'rating'" class="flex gap-2">
          <button
            v-for="n in (question.config?.max || 5)" :key="n" disabled
            class="w-10 h-10 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 cursor-not-allowed"
          >{{ n }}</button>
        </div>

        <!-- dropdown -->
        <select v-else-if="question.type === 'dropdown'" disabled
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-400 dark:text-gray-500 cursor-not-allowed">
          <option>Select an option…</option>
          <option v-for="option in question.options" :key="option">{{ option }}</option>
        </select>

        <!-- nps -->
        <div v-else-if="question.type === 'nps'">
          <div class="flex flex-wrap gap-1">
            <button v-for="n in 11" :key="n" disabled
              class="w-10 h-10 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 cursor-not-allowed"
            >{{ n - 1 }}</button>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-400 dark:text-gray-500">
            <span>Not likely</span>
            <span>Extremely likely</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── EDIT MODE ── -->
    <div v-else>

      <!-- Question list (sortable target) -->
      <div ref="listRef" class="space-y-3">
        <div
          v-for="(question, index) in localData.questions"
          :key="question.id"
          :data-id="question.id"
          class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:border-blue-300 dark:hover:border-blue-600 transition-colors"
        >
          <div class="flex items-start gap-3">

            <!-- Drag handle -->
            <button type="button" class="drag-handle mt-1 cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0">
              <span class="material-icons text-[20px]">drag_indicator</span>
            </button>

            <!-- Question body -->
            <div class="flex-1 min-w-0">

              <!-- Question header row -->
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">
                  Question {{ index + 1 }}
                </span>
                <div class="flex items-center gap-1">
                  <button @click="duplicateQuestion(index)" type="button" title="Duplicate"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                    <span class="material-icons text-[18px]">content_copy</span>
                  </button>
                  <button @click="deleteQuestion(index)" type="button" title="Delete"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <span class="material-icons text-[18px]">delete_outline</span>
                  </button>
                </div>
              </div>

              <!-- Type selector -->
              <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 mb-3">
                <span class="inline-flex items-center px-3 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 border-r border-gray-300 dark:border-gray-600 whitespace-nowrap shrink-0">
                  Question Type
                </span>
                <select
                  v-model="question.type"
                  @change="onQuestionTypeChange(question)"
                  class="flex-1 px-3 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                >
                  <option value="text">Text (Short Answer)</option>
                  <option value="textarea">Textarea (Long Answer)</option>
                  <option value="multiple_choice">Multiple Choice</option>
                  <option value="rating">Rating</option>
                  <option value="dropdown">Dropdown</option>
                  <option value="nps">NPS (Net Promoter Score)</option>
                </select>
              </div>

              <!-- Question label -->
              <input
                v-model="question.label"
                type="text"
                placeholder="Enter your question…"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition mb-3"
              />

              <!-- Options (multiple choice / dropdown) -->
              <div v-if="question.type === 'multiple_choice' || question.type === 'dropdown'" class="mb-3">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Options</p>
                <div class="space-y-2">
                  <div v-for="(option, optIndex) in question.options" :key="optIndex" class="flex items-center gap-2">
                    <input
                      v-model="question.options[optIndex]"
                      type="text"
                      placeholder="Enter option…"
                      class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
                    />
                    <button @click="removeOption(question, optIndex)" type="button"
                      class="p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                      <span class="material-icons text-[18px]">close</span>
                    </button>
                  </div>
                  <button @click="addOption(question)" type="button"
                    class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium transition-colors">
                    <span class="material-icons text-[16px]">add</span>
                    Add Option
                  </button>
                </div>
              </div>

              <!-- Rating config -->
              <div v-if="question.type === 'rating'" class="mb-3">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Max Rating</label>
                <select v-model="question.config.max"
                  class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition">
                  <option :value="5">5 Stars</option>
                  <option :value="10">10 Points</option>
                </select>
              </div>

              <!-- Footer row -->
              <div class="flex flex-wrap items-center gap-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    v-model="question.required"
                    type="checkbox"
                    class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Required</span>
                </label>

                <button @click="toggleConditionalLogic(question)" type="button"
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
                  <span class="material-icons text-[16px]">account_tree</span>
                  {{ question.conditionalLogic ? 'Remove' : 'Add' }} Conditional Logic
                </button>
              </div>

              <!-- Conditional logic editor -->
              <div v-if="question.conditionalLogic"
                class="mt-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  <span class="material-icons text-[16px] text-blue-500">info</span>
                  Show this question only if:
                </p>

                <div class="space-y-2">
                  <div
                    v-for="(rule, ruleIndex) in getConditionalRules(question)"
                    :key="ruleIndex"
                    class="flex items-center gap-2"
                  >
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300 w-6 shrink-0">
                      {{ ruleIndex > 0 ? 'OR' : '' }}
                    </span>

                    <select
                      v-model="rule.question"
                      @change="updateConditionalLogic(question)"
                      class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
                    >
                      <option value="">Select a question…</option>
                      <option
                        v-for="(q, qIndex) in localData.questions.slice(0, index)"
                        :key="q.id"
                        :value="qIndex"
                      >
                        Q{{ qIndex + 1 }}: {{ q.label || 'Untitled' }}
                      </option>
                    </select>

                    <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0">equals</span>

                    <input
                      v-model="rule.equals"
                      @input="updateConditionalLogic(question)"
                      type="text"
                      placeholder="Value…"
                      class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
                    />

                    <button
                      v-if="getConditionalRules(question).length > 1"
                      @click="removeConditionalRule(question, ruleIndex)"
                      type="button"
                      class="p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0"
                    >
                      <span class="material-icons text-[18px]">close</span>
                    </button>
                    <span v-else class="w-8 shrink-0" />
                  </div>
                </div>

                <button @click="addConditionalRule(question)" type="button"
                  class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors">
                  <span class="material-icons text-[16px]">add</span>
                  Add OR Condition
                </button>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                  <span class="material-icons text-[13px]">lightbulb</span>
                  Values are case-sensitive. Multiple conditions use OR logic.
                </p>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="localData.questions.length === 0"
        class="text-center py-16 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
      >
        <span class="material-icons text-5xl text-gray-300 dark:text-gray-600 block mb-3">help_outline</span>
        <p class="text-gray-500 dark:text-gray-400 mb-5">No questions added yet</p>
        <button @click="addQuestion" type="button"
          class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
          <span class="material-icons text-[18px]">add</span>
          Add Your First Question
        </button>
      </div>

      <!-- Add question button -->
      <div v-else class="flex justify-center mt-6">
        <button @click="addQuestion" type="button"
          class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
          <span class="material-icons text-[18px]">add</span>
          Add Question
        </button>
      </div>

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
      required: true,
    },
  },

  data() {
    return {
      previewMode: false,
      questionIdCounter: 1,
      sortableInstance: null,
    };
  },

  computed: {
    localData() {
      if (!this.modelValue.questions) {
        this.modelValue.questions = [];
      }
      this.modelValue.questions.forEach((question) => {
        if (question.conditional_logic && typeof question.conditional_logic === 'object') {
          question.conditionalLogic = true;
        } else if (!Object.prototype.hasOwnProperty.call(question, 'conditionalLogic')) {
          question.conditionalLogic = false;
        }
      });
      return this.modelValue;
    },
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
        conditional_logic: null,
      };
      this.localData.questions.push(newQuestion);
      this.$nextTick(() => this.initSortable());
    },

    duplicateQuestion(index) {
      const original = this.localData.questions[index];
      const dupe = {
        ...JSON.parse(JSON.stringify(original)),
        id: `q_${Date.now()}_${this.questionIdCounter++}`,
        label: `${original.label} (Copy)`,
      };
      this.localData.questions.splice(index + 1, 0, dupe);
      this.reorderQuestions();
      this.$nextTick(() => this.initSortable());
    },

    deleteQuestion(index) {
      if (confirm('Delete this question?')) {
        this.localData.questions.splice(index, 1);
        this.reorderQuestions();
        this.$nextTick(() => this.initSortable());
      }
    },

    addOption(question) {
      if (!question.options) question.options = [];
      question.options.push('');
    },

    removeOption(question, optIndex) {
      question.options.splice(optIndex, 1);
    },

    reorderQuestions() {
      this.localData.questions.forEach((q, i) => { q.order = i; });
    },

    onQuestionTypeChange(question) {
      if ((question.type === 'multiple_choice' || question.type === 'dropdown') && !question.options?.length) {
        question.options = ['Option 1', 'Option 2', 'Option 3'];
      }
      if (question.type === 'rating' && !question.config) {
        question.config = { max: 5 };
      }
    },

    getConditionalRules(question) {
      if (!question.conditional_logic) return [{ question: '', equals: '' }];
      if (question.conditional_logic.rules?.length) return question.conditional_logic.rules;
      if (question.conditional_logic.equals_any?.length) {
        return question.conditional_logic.equals_any.map((value) => ({
          question: question.conditional_logic.show_if_question ?? '',
          equals: value,
        }));
      }
      if (question.conditional_logic.show_if_question !== undefined || question.conditional_logic.equals) {
        return [{ question: question.conditional_logic.show_if_question ?? '', equals: question.conditional_logic.equals ?? '' }];
      }
      return [{ question: '', equals: '' }];
    },

    toggleConditionalLogic(question) {
      question.conditionalLogic = !question.conditionalLogic;
      question.conditional_logic = question.conditionalLogic
        ? { rules: [{ question: '', equals: '' }] }
        : null;
    },

    updateConditionalLogic(question) {
      question.conditional_logic = { rules: this.getConditionalRules(question) };
    },

    addConditionalRule(question) {
      if (!question.conditional_logic) question.conditional_logic = { rules: [] };
      if (!question.conditional_logic.rules) question.conditional_logic.rules = [];
      question.conditional_logic.rules.push({ question: '', equals: '' });
    },

    removeConditionalRule(question, ruleIndex) {
      if (question.conditional_logic?.rules) {
        question.conditional_logic.rules.splice(ruleIndex, 1);
        if (!question.conditional_logic.rules.length) {
          question.conditional_logic.rules = [{ question: '', equals: '' }];
        }
      }
    },

    togglePreviewMode() {
      this.previewMode = !this.previewMode;
      if (!this.previewMode) {
        this.$nextTick(() => this.initSortable());
      }
    },

    initSortable() {
      const el = this.$refs.listRef;
      if (!el || this.previewMode) return;

      if (this.sortableInstance) {
        this.sortableInstance.destroy();
        this.sortableInstance = null;
      }

      this.sortableInstance = Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'opacity-40',
        dragClass: 'shadow-xl',
        onEnd: ({ oldIndex, newIndex }) => {
          if (oldIndex === newIndex) return;
          const moved = this.localData.questions.splice(oldIndex, 1)[0];
          this.localData.questions.splice(newIndex, 0, moved);
          this.reorderQuestions();
        },
      });
    },
  },

  mounted() {
    this.$nextTick(() => this.initSortable());
  },

  beforeUnmount() {
    this.sortableInstance?.destroy();
  },
};
</script>