<template>
  <div class="w-full max-w-xl">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Survey Information</h2>

    <div class="space-y-6">

      <!-- Survey Name -->
      <div>
        <label for="title" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
          Survey Name <span class="text-red-500">*</span>
        </label>
        <input
          id="title"
          v-model="localData.title"
          type="text"
          placeholder="e.g., Customer Satisfaction Survey"
          required
          class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
        />
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
          Give your survey a clear, descriptive name
        </p>
      </div>

      <!-- Survey Type -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
          Survey Type <span class="text-red-500">*</span>
        </label>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <button
            v-for="type in surveyTypes"
            :key="type.value"
            type="button"
            @click="localData.type = type.value"
            :class="[
              'flex flex-col items-center justify-center gap-1.5 p-4 rounded-xl border-2 transition-all text-sm font-medium',
              localData.type === type.value
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-blue-300 dark:hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-gray-600',
            ]"
          >
            <span class="material-icons text-xl" :style="{ color: localData.type === type.value ? undefined : type.color }">{{ type.icon }}</span>
            {{ type.name }}
          </button>
        </div>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
          Choose the type that best fits your survey purpose
        </p>
      </div>

      <!-- Assigned To -->
      <div>
        <label for="assigned_user" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
          Assigned To
        </label>
        <select
          id="assigned_user"
          v-model="localData.assigned_user_id"
          class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition"
        >
          <option :value="null">Select a team member (optional)</option>
          <option v-for="user in users" :key="user.id" :value="user.id">
            {{ user.name }}
          </option>
        </select>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
          Assign this survey to a specific team member
        </p>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
          Description
        </label>
        <textarea
          id="description"
          v-model="localData.description"
          rows="4"
          placeholder="Provide a brief description of the survey's purpose and what you hope to learn..."
          class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition resize-none"
        />
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
          This helps your team understand the survey's goals
        </p>
      </div>

      <!-- Public Description -->
      <div>
        <label for="public_description" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
          Public Description
        </label>
        <textarea
          id="public_description"
          v-model="localData.public_description"
          rows="4"
          placeholder="Provide a brief description of the survey for clients or recipients..."
          class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition resize-none"
        />
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
          This description will be shown to clients or recipients of the survey
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
      required: true,
    },
    users: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      surveyTypes: [
        { value: 'feedback', name: 'Feedback',  icon: 'chat',       color: '#3B82F6' },
        { value: 'lead',     name: 'Lead',       icon: 'person_add', color: '#10B981' },
        { value: 'followup', name: 'Follow Up',  icon: 'reply',      color: '#8B5CF6' },
      ],
    };
  },
  computed: {
    localData: {
      get() { return this.modelValue; },
      set(value) { this.$emit('update:modelValue', value); },
    },
  },
};
</script>