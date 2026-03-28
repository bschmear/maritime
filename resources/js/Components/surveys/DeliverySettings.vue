<template>
  <div class="delivery-settings">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Delivery & Settings</h2>

    <div class="space-y-8 max-w-3xl">
      <!-- Delivery Method -->
<!--       <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          <i class="fas fa-paper-plane mr-2 text-blue-500"></i>
          Delivery Method
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label
            v-for="method in deliveryMethods"
            :key="method.value"
            class="relative flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
            :class="localData.delivery_method === method.value 
              ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
              : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
          >
            <input
              v-model="localData.delivery_method"
              type="radio"
              :value="method.value"
              class="sr-only"
            />
            <div class="flex items-center justify-center mb-2">
              <i :class="method.icon" class="text-3xl" :style="{ color: method.color }"></i>
            </div>
            <span class="text-center font-medium text-gray-900 dark:text-white">{{ method.name }}</span>
            <span class="text-center text-xs text-gray-500 dark:text-gray-400 mt-1">{{ method.description }}</span>
            <div 
              v-if="localData.delivery_method === method.value"
              class="absolute top-2 right-2 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center"
            >
              <i class="fas fa-check text-white text-xs"></i>
            </div>
          </label>
        </div>

        <div v-if="localData.delivery_method === 'embedded'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border dark:border-gray-700">
          <h4 class="font-medium text-gray-900 dark:text-white mb-3">Embed Code</h4>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                <i class="fas fa-link mr-1"></i> Direct Link
              </label>
              <div class="flex items-center space-x-2">
                <input
                  type="text"
                  :value="getEmbedUrl()"
                  readonly
                  class="form-input flex-1 bg-gray-100 dark:bg-gray-800"
                />
                <button
                  @click="copyToClipboard(getEmbedUrl())"
                  type="button"
                  class="btn btn-outline sm"
                >
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                <i class="fas fa-code mr-1"></i> iFrame Code
              </label>
              <div class="flex items-center space-x-2">
                <textarea
                  :value="getIframeCode()"
                  readonly
                  rows="3"
                  class="form-textarea flex-1 font-mono text-xs bg-gray-100 dark:bg-gray-800"
                ></textarea>
                <button
                  @click="copyToClipboard(getIframeCode())"
                  type="button"
                  class="btn btn-outline sm"
                >
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div> -->

      <!-- Automation Trigger -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          <i class="fas fa-robot mr-2 text-purple-500"></i>
          Automation Trigger
        </h3>
        <div class="space-y-3">
          <label
            v-for="trigger in automationTriggers"
            :key="trigger.value"
            class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
            :class="localData.automation_trigger === trigger.value 
              ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
              : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
          >
            <input
              v-model="localData.automation_trigger"
              type="radio"
              :value="trigger.value"
              class="form-radio text-blue-600 mt-1"
            />
            <div class="ml-3 flex-1">
              <div class="flex items-center">
                <i :class="trigger.icon" class="mr-2" :style="{ color: trigger.color }"></i>
                <span class="font-medium text-gray-900 dark:text-white">{{ trigger.name }}</span>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ trigger.description }}</p>
            </div>
          </label>
        </div>

        <!-- Custom Trigger Settings -->
        <div v-if="localData.automation_trigger !== 'manual'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border dark:border-gray-700">
          <!-- After Transaction Closes Settings -->
          <div v-if="localData.automation_trigger === 'after_transaction'" class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              <i class="fas fa-clock mr-1"></i>
              When to Send
            </h4>
            
            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.automation_config.send_type === 'immediate' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.automation_config.send_type"
                type="radio"
                value="immediate"
                class="form-radio text-blue-600 mt-1"
              />
              <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-white">Send Immediately</span>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                  Survey will be sent as soon as the transaction closes
                </p>
              </div>
            </label>

            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.automation_config.send_type === 'days' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.automation_config.send_type"
                type="radio"
                value="days"
                class="form-radio text-blue-600 mt-1"
              />
              <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-white">Send After X Days</span>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                  Wait a specific number of days after the transaction closes before sending
                </p>
                
                <div v-if="localData.automation_config.send_type === 'days'" class="mt-3 flex items-center space-x-3">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Send after</label>
                  <input
                    v-model="localData.automation_config.days"
                    type="number"
                    min="1"
                    class="form-input w-24"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">days</span>
                </div>
              </div>
            </label>
          </div>

          <!-- On Lead Conversion Settings -->
          <div v-if="localData.automation_trigger === 'on_lead_conversion'" class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              <i class="fas fa-clock mr-1"></i>
              When to Send
            </h4>
            
            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.automation_config.send_type === 'immediate' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.automation_config.send_type"
                type="radio"
                value="immediate"
                class="form-radio text-blue-600 mt-1"
              />
              <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-white">Send Immediately</span>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                  Survey will be sent as soon as the lead is converted
                </p>
              </div>
            </label>

            <label
              class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.automation_config.send_type === 'days' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.automation_config.send_type"
                type="radio"
                value="days"
                class="form-radio text-blue-600 mt-1"
              />
              <div class="ml-3 flex-1">
                <span class="font-medium text-gray-900 dark:text-white">Send After X Days</span>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                  Wait a specific number of days after the lead is converted before sending
                </p>
                
                <div v-if="localData.automation_config.send_type === 'days'" class="mt-3 flex items-center space-x-3">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Send after</label>
                  <input
                    v-model="localData.automation_config.days"
                    type="number"
                    min="1"
                    class="form-input w-24"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">days</span>
                </div>
              </div>
            </label>
          </div>

          <!-- After X Days Settings -->
          <div v-if="localData.automation_trigger === 'after_days'" class="space-y-2">
            <div class="flex items-center space-x-3">
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Send after</label>
              <input
                v-model="localData.automation_config.days"
                type="number"
                min="1"
                class="form-input w-24"
              />
              <span class="text-sm text-gray-700 dark:text-gray-300">days</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 italic">
              <i class="fas fa-info-circle mr-1"></i>
              Survey will be sent automatically after the specified number of days from when the transaction is created.
            </p>
          </div>
        </div>
      </div>

      <!-- Thank You Message -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          <i class="fas fa-heart mr-2 text-red-500"></i>
          Completion Settings
        </h3>
        <div class="space-y-4">
          <div>
            <label for="thank_you_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Thank You Message
            </label>
            <textarea
              id="thank_you_message"
              v-model="localData.thank_you_message"
              rows="3"
              class="form-textarea w-full"
              placeholder="Thank you for completing this survey!"
            ></textarea>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              This message will be shown after the survey is submitted
            </p>
          </div>

          <div class="flex items-center">
            <input
              id="enable_redirect"
              v-model="enableRedirect"
              type="checkbox"
              class="form-checkbox text-blue-600 rounded"
            />
            <label for="enable_redirect" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
              Redirect to custom URL after submission
            </label>
          </div>

          <div v-if="enableRedirect">
            <label for="redirect_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Redirect URL
            </label>
            <input
              id="redirect_url"
              v-model="localData.redirect_url"
              type="url"
              class="form-input w-full"
              placeholder="https://example.com/thank-you"
            />
          </div>
        </div>
      </div>

      <!-- Survey Styling (Premium Feature) -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          <i class="fas fa-palette mr-2 text-pink-500"></i>
          Survey Styling
          <span v-if="!canCustomizeColors" class="ml-2 text-xs inline-flex items-center px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
            <i class="fas fa-lock mr-1"></i>
            Premium Feature
          </span>
        </h3>
        
        <div v-if="canCustomizeColors" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Default Color -->
            <label
              class="relative flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.color_scheme === 'default' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.color_scheme"
                type="radio"
                value="default"
                class="sr-only"
              />
              <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-gray-900 dark:text-white">Default</span>
                <div class="w-8 h-8 rounded-full border-2 border-gray-300" style="background-color: #0d9488"></div>
              </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Default brand color</span>
              <div 
                v-if="localData.color_scheme === 'default'"
                class="absolute top-2 right-2 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center"
              >
                <i class="fas fa-check text-white text-xs"></i>
              </div>
            </label>

            <!-- Team Color -->
            <label
              class="relative flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.color_scheme === 'team' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.color_scheme"
                type="radio"
                value="team"
                class="sr-only"
              />
              <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-gray-900 dark:text-white">Team Color</span>
                <div class="w-8 h-8 rounded-full border-2 border-gray-300" :style="{ backgroundColor: teamColor || '#0d9488' }"></div>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400">Use your team's brand color</span>
              <div 
                v-if="localData.color_scheme === 'team'"
                class="absolute top-2 right-2 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center"
              >
                <i class="fas fa-check text-white text-xs"></i>
              </div>
            </label>

            <!-- Custom Color -->
            <label
              class="relative flex flex-col p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500"
              :class="localData.color_scheme === 'custom' 
                ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              <input
                v-model="localData.color_scheme"
                type="radio"
                value="custom"
                class="sr-only"
              />
              <div class="flex items-center justify-between mb-2">
                <span class="font-medium text-gray-900 dark:text-white">Custom</span>
                <div class="w-8 h-8 rounded-full border-2 border-gray-300" :style="{ backgroundColor: localData.custom_color || '#0d9488' }"></div>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400">Choose your own color</span>
              <div 
                v-if="localData.color_scheme === 'custom'"
                class="absolute top-2 right-2 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center"
              >
                <i class="fas fa-check text-white text-xs"></i>
              </div>
            </label>
          </div>

          <!-- Custom Color Picker -->
          <div v-if="localData.color_scheme === 'custom'" class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border dark:border-gray-700">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Choose Color
            </label>
            <div class="flex items-center space-x-3">
              <input
                v-model="localData.custom_color"
                type="color"
                class="h-10 w-20 rounded cursor-pointer"
              />
              <input
                v-model="localData.custom_color"
                type="text"
                placeholder="#0d9488"
                class="form-input flex-1"
              />
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
              This color will be used for buttons, highlights, and other accent elements in your survey
            </p>
          </div>
        </div>

        <!-- Upgrade Message -->
        <div v-else class="p-4 bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border-2 border-yellow-300 dark:border-yellow-600 rounded-lg">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <i class="fas fa-crown text-2xl text-yellow-600 dark:text-yellow-400"></i>
            </div>
            <div class="ml-3 flex-1">
              <h4 class="text-sm font-medium text-gray-900 dark:text-white">Upgrade to Customize Survey Styling</h4>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Premium members can customize survey colors to match their brand. Upgrade your subscription to unlock this feature.
              </p>
              <button
                type="button"
                class="mt-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-yellow-900 bg-yellow-100 hover:bg-yellow-200 dark:bg-yellow-800 dark:text-yellow-100 dark:hover:bg-yellow-700"
              >
                <i class="fas fa-arrow-up mr-2"></i>
                Upgrade Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Privacy Settings -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          <i class="fas fa-shield-alt mr-2 text-green-500"></i>
          Privacy Settings
        </h3>
        <div class="space-y-4">
          <label class="flex items-start cursor-pointer">
            <input
              v-model="localData.privacy_settings.anonymous"
              type="checkbox"
              class="form-checkbox text-blue-600 rounded mt-1"
            />
            <div class="ml-3">
              <span class="font-medium text-gray-900 dark:text-white">Anonymous Responses</span>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Don't collect any identifying information from respondents
              </p>
            </div>
          </label>

            <label class="flex items-start cursor-pointer">
              <input
                v-model="localData.privacy_settings.require_identity"
                type="checkbox"
                class="form-checkbox text-blue-600 rounded mt-1"
                :disabled="localData.privacy_settings.anonymous"
              />
              <div class="ml-3">
                <span
                  class="font-medium"
                  :class="localData.privacy_settings.anonymous ? 'text-gray-400' : 'text-gray-900 dark:text-white'"
                >
                  Require Name and Email
                </span>
                <p
                  class="text-sm mt-1"
                  :class="localData.privacy_settings.anonymous ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400'"
                >
                  Respondents must provide their email address, first name, and last name to submit the survey
                </p>
              </div>
            </label>

          <label class="flex items-start cursor-pointer">
            <input
              v-model="localData.privacy_settings.one_response_per_user"
              type="checkbox"
              class="form-checkbox text-blue-600 rounded mt-1"
            />
            <div class="ml-3">
              <span class="font-medium text-gray-900 dark:text-white">
                One Response Per User
              </span>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Limit each email address to one response
              </p>
            </div>
          </label>

          <label class="flex items-start cursor-pointer">
            <input
              v-model="localData.privacy_settings.show_results"
              type="checkbox"
              class="form-checkbox text-blue-600 rounded mt-1"
            />
            <div class="ml-3">
              <span class="font-medium text-gray-900 dark:text-white">
                Show Results After Submission
              </span>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
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
    modelValue: {
      type: Object,
      required: true
    },
    team: {
      type: Object,
      default: null
    },
    subscription: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      enableRedirect: !!this.modelValue.redirect_url,
      deliveryMethods: [
        {
          value: 'email',
          name: 'Email',
          description: 'Send via email',
          icon: 'fas fa-envelope',
          color: '#3B82F6'
        },
        // {
        //   value: 'sms',
        //   name: 'SMS',
        //   description: 'Send via text message',
        //   icon: 'fas fa-sms',
        //   color: '#10B981'
        // },
        {
          value: 'embedded',
          name: 'Embedded',
          description: 'Link or iframe',
          icon: 'fas fa-code',
          color: '#8B5CF6'
        }
      ],
      automationTriggers: [
        {
          value: 'manual',
          name: 'Manual Send',
          description: 'Manually choose when to send this survey',
          icon: 'fas fa-hand-pointer',
          color: '#6B7280'
        },
        {
          value: 'after_transaction',
          name: 'After Transaction Closes',
          description: 'Automatically send when a transaction is marked as closed',
          icon: 'fas fa-home',
          color: '#10B981'
        },
        {
          value: 'after_days',
          name: 'After X Days',
          description: 'Send automatically after a specified number of days from transaction creation',
          icon: 'fas fa-calendar-alt',
          color: '#F59E0B'
        },
        {
          value: 'on_lead_conversion',
          name: 'On Lead Conversion',
          description: 'Send when a lead is converted to a client',
          icon: 'fas fa-user-check',
          color: '#8B5CF6'
        }
      ]
    };
  },
  computed: {
    localData() {
      // Ensure defaults are set
      const data = this.modelValue;
      if (!data.automation_config) {
        data.automation_config = { 
          send_type: 'immediate',
          days: 7 
        };
      }
      // Set defaults for after_transaction trigger
      if (data.automation_trigger === 'after_transaction' && !data.automation_config.send_type) {
        data.automation_config.send_type = 'immediate';
      }
      // Set defaults for on_lead_conversion trigger
      if (data.automation_trigger === 'on_lead_conversion' && !data.automation_config.send_type) {
        data.automation_config.send_type = 'immediate';
      }
      // Ensure days is set if send_type is 'days'
      if (data.automation_config.send_type === 'days' && !data.automation_config.days) {
        data.automation_config.days = 7;
      }
      if (!data.privacy_settings) {
        data.privacy_settings = {
          anonymous: false,
          require_email: false,
          one_response_per_user: false,
          show_results: false
        };
      }
      if (!data.color_scheme) {
        data.color_scheme = 'default';
      }
      if (!data.custom_color) {
        data.custom_color = '#0d9488';
      }
      return data;
    },
    canCustomizeColors() {
      // Check if subscription level >= 2
      return this.subscription && this.subscription.level >= 2;
    },
    teamColor() {
      return this.team && this.team.team_color ? this.team.team_color : '#0d9488';
    }
  },
  watch: {
    enableRedirect(newValue) {
      if (!newValue) {
        this.localData.redirect_url = '';
      }
    },
    'localData.privacy_settings.anonymous'(newValue) {
      if (newValue) {
        this.localData.privacy_settings.require_email = false;
      }
    }
  },
  methods: {
    getEmbedUrl() {
      // This would be the actual survey URL once created
      return `${window.location.origin}/surveys/[survey-id]`;
    },
    getIframeCode() {
      const url = this.getEmbedUrl();
      return `<iframe src="${url}" width="100%" height="600" frameborder="0"></iframe>`;
    },
    copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
      }).catch(err => {
        console.error('Failed to copy:', err);
      });
    }
  }
};
</script>

