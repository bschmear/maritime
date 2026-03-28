<template>
  <div class="ai-analysis-button" v-cloak>
    <!-- AI Usage Stats Badge -->
    <div v-if="showUsageStats && aiUsage && !ontrial" class="mb-3 text-xs text-gray-600 dark:text-gray-400">
      <span class="font-semibold">AI Credits:</span>
      {{ aiUsage.monthly_remaining || aiUsage.free_credits_remaining }}/{{ aiUsage.monthly_limit || aiUsage.free_credits_total }} remaining this month
    </div>

    <!-- Analyze Button (Trial Users) -->
    <button
      v-if="!hasanalysis && ontrial"
      type="button"
      @click="showTrialModal = true"
      class="btn ai-btn">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
      </svg>
      <span>Analyze with AI</span>
      <span class="ml-2 px-2 py-0.5 text-xs bg-yellow-400 text-gray-900 rounded-full">Trial</span>
    </button>

    <!-- Analyze Button (Non-Trial Users) -->
    <button
      v-if="!hasanalysis && !ontrial"
      type="button"
      @click="analyzeResponse"
      :disabled="loading || !canUseAi"
      class="btn ai-btn">
      <svg v-if="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
      </svg>
      <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span v-if="!loading">Analyze with AI</span>
      <span v-else>Analyzing...</span>
    </button>

    <!-- View Analysis Button (when analysis exists) -->
    <button
      v-if="hasanalysis"
      type="button"
      @click="$root.analysisCollapsed = false;"
      class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 rounded-lg transition-all duration-200">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      Analyzed - See Results
    </button>

    <!-- Error Message -->
    <div v-if="error" class="mt-3 p-3 text-sm text-red-800 bg-red-100 border border-red-200 rounded-lg dark:bg-red-900 dark:text-red-200 dark:border-red-800">
      <div class="flex items-start">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
        </svg>
        <div>
          <span class="font-semibold">Error:</span> {{ error }}
        </div>
      </div>
    </div>

    <!-- Upgrade Notice -->
    <div v-if="!canUseAi && !loading && !ontrial" class="mt-3 p-4 text-sm text-blue-800 bg-blue-100 border border-blue-200 rounded-lg dark:bg-blue-900 dark:text-blue-200 dark:border-blue-800">
      <div class="flex items-start">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
        </svg>
        <div>
          <span class="font-semibold">AI Limit Reached</span>
          <p v-if="subscriptionlevel === 1" class="mt-1">You've reached your demo limit. AI analysis is only available on higher tier plans.</p>
          <p v-else class="mt-1">You've reached your monthly AI analysis limit. Upgrade your plan to unlock more AI-powered insights.</p>
          <a :href="upgradeurl" target="_blank" class="inline-block mt-2 text-blue-700 underline hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200">
            Upgrade Now →
          </a>
        </div>
      </div>
    </div>

    <!-- Trial Modal -->
    <div v-if="showTrialModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTrialModal = false"></div>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-r from-purple-100 to-blue-100 dark:from-purple-900 dark:to-blue-900 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                  Unlock AI Analysis
                </h3>
                <div class="mt-4 space-y-4">
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    AI Analysis is a powerful feature available with a paid subscription. Here's what you can do:
                  </p>

                  <ul class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                      </svg>
                      <span><strong>Summarize responses</strong> - Get instant AI-generated summaries of survey feedback</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                      </svg>
                      <span><strong>Generate suggested tasks</strong> - AI creates actionable follow-up tasks based on responses</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                      </svg>
                      <span><strong>Create follow-up messages</strong> - AI drafts personalized follow-up emails automatically</span>
                    </li>
                  </ul>

                  <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                      <strong>End your trial early</strong> by adding your payment card and subscribe now to unlock all AI features immediately!
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
            <a
              :href="upgradeurl"
              target="_blank"
              class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-base font-medium text-white hover:from-purple-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
              Subscribe Now
            </a>
            <button
              type="button"
              @click="showTrialModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AiAnalysisButton',
  props: {
    surveyresponseid: {
      type: Number,
      required: true
    },
    teamid: {
      type: Number,
      required: true
    },
    hasanalysis: {
      type: Boolean,
      default: false
    },
    ontrial: {
      type: Boolean,
      default: false
    },
    subscriptionlevel: {
      type: Number,
      default: 1
    },
    showUsageStats: {
      type: Boolean,
      default: true
    },
    upgradeurl: {
      type: String,
      default: '/settings/subscription'
    }
  },
  data() {
    return {
      loading: false,
      error: null,
      aiUsage: null,
      canUseAi: true,
      showTrialModal: false
    };
  },
  mounted() {
    this.loadAiUsage();
  },
  methods: {
    async loadAiUsage() {
      try {
        const response = await axios.get(`/ai/usage?team_id=${this.teamid}`);
        this.aiUsage = response.data;
        
        // Check if team can use AI
        const remaining = this.aiUsage.monthly_remaining || this.aiUsage.free_credits_remaining || 0;
        this.canUseAi = this.aiUsage.ai_access && remaining > 0;
      } catch (error) {
        console.error('Failed to load AI usage:', error);
      }
    },
    async analyzeResponse() {
      if (!this.canUseAi) {
        this.error = 'You have reached your AI analysis limit. Please upgrade your plan.';
        return;
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await axios.post(
          `/ai/analyze-survey?team=${this.teamid}`,
          {
            survey_response_id: this.surveyresponseid
          }
        );

        // Emit success event with analysis data
        this.$emit('analysiscomplete', response.data.analysis);
        
        // Reload AI usage stats
        await this.loadAiUsage();

      } catch (error) {
        console.error('AI analysis failed:', error);
        
        const errorMessage = error.response?.data?.message || 
                           error.response?.data?.error || 
                           'Failed to analyze survey. Please try again.';
        
        this.error = errorMessage;

        if (this.$root.showNotification) {
          this.$root.showNotification(errorMessage, 'error');
        }
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
[v-cloak] {
  display: none;
}

.ai-analysis-button {
  position: relative;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>

