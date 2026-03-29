<template>
  <div class="w-full">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Delivery & Settings</h2>

    <div class="space-y-8 max-w-3xl">

      <!-- ── Automation Trigger ── -->
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="material-icons text-[20px] text-purple-500">bolt</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Automation Trigger</h3>
        </div>

        <!-- Confirmation note -->
        <div class="flex items-start gap-2 mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
          <span class="material-icons text-[16px] text-blue-500 mt-0.5 shrink-0">info</span>
          <p class="text-sm text-blue-700 dark:text-blue-300">
            Surveys are <strong>never sent automatically.</strong> When a trigger condition is met, you'll be asked to confirm before the survey is sent.
          </p>
        </div>

        <div class="space-y-3">
          <label
            v-for="trigger in automationTriggers"
            :key="trigger.value"
            class="flex items-start p-4 border-2 rounded-xl cursor-pointer transition-all"
            :class="localData.automation_trigger === trigger.value
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
              : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
          >
            <input
              v-model="localData.automation_trigger"
              type="radio"
              :value="trigger.value"
              class="mt-1 accent-blue-600 shrink-0"
            />
            <div class="ml-3 flex-1">
              <div class="flex items-center gap-2">
                <span class="material-icons text-[18px]" :style="{ color: trigger.color }">{{ trigger.icon }}</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ trigger.name }}</span>
              </div>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ trigger.description }}</p>
            </div>
          </label>
        </div>

        <!-- Trigger config panel -->
        <div v-if="localData.automation_trigger !== 'manual'"
          class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700">

          <!-- after_transaction / on_lead_conversion: send timing -->
          <div v-if="localData.automation_trigger === 'after_transaction' || localData.automation_trigger === 'on_lead_conversion'"
            class="space-y-3">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              <span class="material-icons text-[16px] text-gray-400">schedule</span>
              When to Notify
            </p>

            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all"
              :class="localData.automation_config.send_type === 'immediate'
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
                : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-white dark:hover:bg-gray-800'"
            >
              <input v-model="localData.automation_config.send_type" type="radio" value="immediate"
                class="mt-1 accent-blue-600 shrink-0" />
              <div class="ml-3">
                <span class="font-medium text-gray-900 dark:text-white">Immediately</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  You'll be prompted as soon as the trigger condition is met
                </p>
              </div>
            </label>

            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all"
              :class="localData.automation_config.send_type === 'days'
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
                : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-600 hover:bg-white dark:hover:bg-gray-800'"
            >
              <input v-model="localData.automation_config.send_type" type="radio" value="days"
                class="mt-1 accent-blue-600 shrink-0" />
              <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-white">After X Days</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Wait a number of days before prompting you to send
                </p>
                <div v-if="localData.automation_config.send_type === 'days'" class="mt-3 flex items-center gap-3">
                  <span class="text-sm text-gray-700 dark:text-gray-300">Send after</span>
                  <input
                    v-model="localData.automation_config.days"
                    type="number" min="1"
                    class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">days</span>
                </div>
              </div>
            </label>
          </div>

          <!-- after_days -->
          <div v-if="localData.automation_trigger === 'after_days'" class="space-y-3">
            <div class="flex items-center gap-3">
              <span class="text-sm text-gray-700 dark:text-gray-300">Prompt after</span>
              <input
                v-model="localData.automation_config.days"
                type="number" min="1"
                class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-900 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
              />
              <span class="text-sm text-gray-700 dark:text-gray-300">days from transaction creation</span>
            </div>
            <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
              <span class="material-icons text-[13px]">info</span>
              You'll receive a prompt to confirm sending after the specified number of days.
            </p>
          </div>
        </div>
      </div>

      <!-- ── Completion Settings ── -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <span class="material-icons text-[20px] text-red-400">favorite</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Completion Settings</h3>
        </div>

        <div class="space-y-4">
          <div>
            <label for="thank_you_message" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              Thank You Message
            </label>
            <textarea
              id="thank_you_message"
              v-model="localData.thank_you_message"
              rows="3"
              placeholder="Thank you for completing this survey!"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition resize-none"
            />
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
              Shown to respondents after they submit the survey
            </p>
          </div>

          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              id="enable_redirect"
              v-model="enableRedirect"
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 accent-blue-600"
            />
            <span class="text-sm text-gray-700 dark:text-gray-300">Redirect to a custom URL after submission</span>
          </label>

          <div v-if="enableRedirect">
            <label for="redirect_url" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              Redirect URL
            </label>
            <input
              id="redirect_url"
              v-model="localData.redirect_url"
              type="url"
              placeholder="https://example.com/thank-you"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
            />
          </div>
        </div>
      </div>

      <!-- ── Privacy Settings ── -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <span class="material-icons text-[20px] text-green-500">shield</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Privacy Settings</h3>
        </div>

        <div class="space-y-4">
          <label class="flex items-start gap-3 cursor-pointer select-none">
            <input
              v-model="localData.privacy_settings.anonymous"
              type="checkbox"
              class="w-4 h-4 mt-0.5 rounded border-gray-300 dark:border-gray-600 accent-blue-600 shrink-0"
            />
            <div>
              <span class="font-medium text-gray-900 dark:text-white">Anonymous Responses</span>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Don't collect any identifying information from respondents
              </p>
            </div>
          </label>

          <label class="flex items-start gap-3 cursor-pointer select-none"
            :class="localData.privacy_settings.anonymous ? 'opacity-50 cursor-not-allowed' : ''">
            <input
              v-model="localData.privacy_settings.require_identity"
              type="checkbox"
              :disabled="localData.privacy_settings.anonymous"
              class="w-4 h-4 mt-0.5 rounded border-gray-300 dark:border-gray-600 accent-blue-600 shrink-0"
            />
            <div>
              <span class="font-medium" :class="localData.privacy_settings.anonymous ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white'">
                Require Name and Email
              </span>
              <p class="text-sm mt-0.5" :class="localData.privacy_settings.anonymous ? 'text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400'">
                Respondents must provide their name and email to submit
              </p>
            </div>
          </label>

          <label class="flex items-start gap-3 cursor-pointer select-none">
            <input
              v-model="localData.privacy_settings.one_response_per_user"
              type="checkbox"
              class="w-4 h-4 mt-0.5 rounded border-gray-300 dark:border-gray-600 accent-blue-600 shrink-0"
            />
            <div>
              <span class="font-medium text-gray-900 dark:text-white">One Response Per User</span>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Limit each email address to one response
              </p>
            </div>
          </label>

          <label class="flex items-start gap-3 cursor-pointer select-none">
            <input
              v-model="localData.privacy_settings.show_results"
              type="checkbox"
              class="w-4 h-4 mt-0.5 rounded border-gray-300 dark:border-gray-600 accent-blue-600 shrink-0"
            />
            <div>
              <span class="font-medium text-gray-900 dark:text-white">Show Results After Submission</span>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Allow respondents to see aggregated results after completing the survey
              </p>
            </div>
          </label>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'DeliverySettings',

  props: {
    modelValue: { type: Object, required: true },
    team:         { type: Object, default: null },
    subscription: { type: Object, default: null },
  },

  data() {
    return {
      enableRedirect: !!this.modelValue.redirect_url,

      automationTriggers: [
        {
          value: 'manual',
          name: 'Manual Send',
          description: 'You choose exactly when to send this survey — no automatic prompts.',
          icon: 'touch_app',
          color: '#6B7280',
        },
        {
          value: 'after_transaction',
          name: 'After Transaction Closes',
          description: 'Prompts you to send when a transaction is marked as closed.',
          icon: 'handshake',
          color: '#10B981',
        },
        {
          value: 'after_days',
          name: 'After X Days',
          description: 'Prompts you to send after a set number of days from transaction creation.',
          icon: 'calendar_today',
          color: '#F59E0B',
        },
        {
          value: 'on_lead_conversion',
          name: 'On Lead Conversion',
          description: 'Prompts you to send when a lead is converted to a client.',
          icon: 'person_add',
          color: '#8B5CF6',
        },
      ],
    };
  },

  computed: {
    localData() {
      const data = this.modelValue;
      if (!data.automation_config) {
        data.automation_config = { send_type: 'immediate', days: 7 };
      }
      if (['after_transaction', 'on_lead_conversion'].includes(data.automation_trigger) && !data.automation_config.send_type) {
        data.automation_config.send_type = 'immediate';
      }
      if (data.automation_config.send_type === 'days' && !data.automation_config.days) {
        data.automation_config.days = 7;
      }
      if (!data.privacy_settings) {
        data.privacy_settings = {
          anonymous: false,
          require_identity: false,
          one_response_per_user: false,
          show_results: false,
        };
      }
      return data;
    },
  },

  watch: {
    enableRedirect(val) {
      if (!val) this.localData.redirect_url = '';
    },
    'localData.privacy_settings.anonymous'(val) {
      if (val) this.localData.privacy_settings.require_identity = false;
    },
  },
};
</script>