<template>
  <div v-if="usage" class="ai-usage-stats bg-white dark:bg-gray-800 rounded-lg shadow p-6" v-cloak>
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
      <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
      </svg>
      AI Usage Statistics
    </h3>

    <!-- Tier Badge -->
    <div class="mb-4">
      <span :class="getTierClass(usage.tier)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
        {{ usage.tier_name }} Plan
      </span>
    </div>

    <!-- Usage Bars -->
    <div class="space-y-4">
      <!-- Monthly Usage -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Monthly AI Analyses</span>
          <span class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ usage.monthly_used }} / {{ usage.monthly_limit }}
          </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
          <div 
            :class="getProgressClass(usage.monthly_used, usage.monthly_limit)"
            class="h-2.5 rounded-full transition-all duration-300"
            :style="{ width: getPercentage(usage.monthly_used, usage.monthly_limit) + '%' }">
          </div>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          {{ usage.monthly_remaining }} analyses remaining this month
        </p>
      </div>

      <!-- Free Credits (Trial Only) -->
      <div v-if="usage.tier === 1 && usage.free_credits_total > 0">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Free Credits</span>
          <span class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ usage.free_credits_remaining }} / {{ usage.free_credits_total }}
          </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
          <div 
            :class="getProgressClass(usage.free_credits_used, usage.free_credits_total)"
            class="h-2.5 rounded-full transition-all duration-300"
            :style="{ width: getPercentage(usage.free_credits_remaining, usage.free_credits_total) + '%' }">
          </div>
        </div>
      </div>

      <!-- Total Usage -->
      <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Analyses</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ usage.total_used }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Tokens Used (Monthly)</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ formatNumber(usage.tokens_used_monthly) }}</p>
          </div>
        </div>
      </div>

      <!-- Last Reset -->
      <div v-if="usage.last_reset_at" class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Last reset: {{ formatDate(usage.last_reset_at) }}
        </p>
      </div>

      <!-- Upgrade CTA -->
      <div v-if="!usage.ai_access || usage.monthly_remaining === 0" class="pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="p-4 bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900 dark:to-blue-900 rounded-lg">
          <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
            {{ usage.tier === 1 ? 'Upgrade to Pro' : 'Need More AI Analyses?' }}
          </p>
          <p class="text-xs text-gray-600 dark:text-gray-300 mb-3">
            {{ getUpgradeMessage() }}
          </p>
          <a 
            :href="upgradeUrl"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg transition-all duration-200">
            Upgrade Plan
            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AiUsageStats',
  props: {
    teamId: {
      type: Number,
      required: true
    },
    upgradeUrl: {
      type: String,
      default: '/settings/subscription'
    },
    autoRefresh: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      usage: null,
      loading: false,
      refreshInterval: null
    };
  },
  mounted() {
    this.loadUsage();
    
    if (this.autoRefresh) {
      this.refreshInterval = setInterval(() => {
        this.loadUsage();
      }, 60000); // Refresh every minute
    }
  },
  beforeUnmount() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  },
  methods: {
    async loadUsage() {
      this.loading = true;
      
      try {
        const response = await axios.get(`/ai/usage?team_id=${this.teamId}`);
        this.usage = response.data;
      } catch (error) {
        console.error('Failed to load AI usage:', error);
      } finally {
        this.loading = false;
      }
    },
    getPercentage(used, total) {
      if (total === 0) return 0;
      return Math.min(100, Math.round((used / total) * 100));
    },
    getProgressClass(used, total) {
      const percentage = this.getPercentage(used, total);
      
      if (percentage >= 90) return 'bg-red-600';
      if (percentage >= 75) return 'bg-yellow-500';
      return 'bg-gradient-to-r from-purple-600 to-blue-600';
    },
    getTierClass(tier) {
      if (tier === 3) return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
      if (tier === 2) return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    },
    formatNumber(num) {
      if (!num) return '0';
      return num.toLocaleString();
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    },
    getUpgradeMessage() {
      if (this.usage.tier === 1) {
        return 'Unlock 25 AI analyses per month and advanced features with Pro.';
      } else if (this.usage.tier === 2) {
        return 'Upgrade to Elite for 1000+ AI analyses per month and unlimited access.';
      }
      return 'Maximize your AI-powered insights.';
    },
    refresh() {
      this.loadUsage();
    }
  }
};
</script>

<style scoped>
[v-cloak] {
  display: none;
}

.ai-usage-stats {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>

