<template>
  <div class="survey-info-step w-full max-w-xl margin-auto">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Survey Information</h2>
    
    <div class="space-y-6 max-w-2xl">
      <!-- Survey Name -->
      <div>
        <label for="title" class="input-label">
          Survey Name <span class="text-red-500">*</span>
        </label>
        <input
          id="title"
          v-model="localData.title"
          type="text"
          class="input-style"
          placeholder="e.g., Customer Satisfaction Survey"
          required
        />
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Give your survey a clear, descriptive name
        </p>
      </div>

      <!-- Survey Type -->
      <div>
        <label for="type" class="input-label">
          Survey Type <span class="text-red-500">*</span>
        </label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <button
            v-for="type in surveyTypes"
            :key="type.value"
            type="button"
            @click="localData.type = type.value"
            class="flex flex-col items-center justify-center p-4 border-2 rounded-lg transition-all hover:border-blue-500"
            :class="localData.type === type.value 
              ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500' 
              : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
          >
            <i :class="type.icon" class="text-2xl mb-2" :style="{ color: type.color }"></i>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ type.name }}</span>
          </button>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Choose the type that best fits your survey purpose
        </p>
      </div>

      <!-- Associated Agent/Team -->
      <div>
        <label for="assigned_user" class="input-label">
          Assigned To
        </label>
        <select
          id="assigned_user"
          v-model="localData.assigned_user_id"
          class="input-style"
        >
          <option :value="null">Select a team member (optional)</option>
          <option v-for="user in users" :key="user.id" :value="user.id">
            {{ user.name }}
          </option>
        </select>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Assign this survey to a specific team member
        </p>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="input-label">
          Description
        </label>
        <textarea
          id="description"
          v-model="localData.description"
          rows="4"
          class="input-style"
          placeholder="Provide a brief description of the survey's purpose and what you hope to learn..."
        ></textarea>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          This helps your team understand the survey's goals
        </p>
      </div>

<div>
  <label for="public_description" class="input-label">
    Public Description
  </label>
  <textarea
    id="public_description"
    v-model="localData.public_description"
    rows="4"
    class="input-style"
    placeholder="Provide a brief description of the survey for clients or recipients..."
  ></textarea>
  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
    This description will be shown to clients or recipients of the survey
  </p>
</div>


<!-- Visibility -->
<div>
  <label class="input-label">
    Visibility
  </label>
  <div class="flex space-x-4">
    <label class="flex items-center cursor-pointer">
      <input
        v-model="localData.visibility"
        type="radio"
        value="private"
        class="radio-input text-blue-600"
      />
      <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
        <i class="fas fa-lock mr-1"></i> Private
      </span>
    </label>
    <label class="flex items-center cursor-pointer">
      <input
        v-model="localData.visibility"
        type="radio"
        value="public"
        class="radio-input text-green-600"
      />
      <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
        <i class="fas fa-users mr-1"></i> Team Access
      </span>
    </label>
  </div>
  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
    <strong>Private:</strong> only you can view and use this survey.<br>
    <strong>Team Access:</strong> all members of your team can view and use this survey.
  </p>
</div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'SurveyInfoStep',
  props: {
    modelValue: {
      type: Object,
      required: true
    },
    users: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      surveyTypes: [
        { 
          value: 'feedback', 
          name: 'Feedback', 
          icon: 'fas fa-comments',
          color: '#3B82F6' 
        },
        { 
          value: 'lead', 
          name: 'Lead', 
          icon: 'fas fa-user-plus',
          color: '#10B981' 
        },
        { 
          value: 'followup', 
          name: 'Follow Up', 
          icon: 'fas fa-reply',
          color: '#8B5CF6' 
        }
      ]
    };
  },
  computed: {
    localData: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      }
    }
  }
};
</script>

