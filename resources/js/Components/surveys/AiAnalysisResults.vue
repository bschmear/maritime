<template>
  <div v-if="analysis" class="ai-analysis-results bg-white dark:bg-gray-800 rounded-lg shadow-lg" v-cloak>
    <!-- Header -->
    <div class=" p-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3 flex-1">
          <div>
            <div class="flex space-x-3 items-center">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                  <i class="fas fa-bolt w-6 h-6 mr-2 text-purple-600"></i>
                  AI Analysis Results
                </h3>
                  <div v-if="analysis.confidence" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <i class="fas fa-check-circle w-4 h-4 mr-1"></i>
                    {{ Math.round(analysis.confidence * 100) }}% Confidence
                  </div>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Generated {{ formatDate(analysis.created_at) }}
            </p>
          </div>
        </div>
          <button
            @click="toggleCollapse"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
            :title="$root.analysisCollapsed ? 'Expand' : 'Collapse'">
            <i class="fas fa-chevron-down w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': !$root.analysisCollapsed }"></i>
          </button>
      </div>
    </div>

    <!-- Collapsible Content -->
    <div v-show="!$root.analysisCollapsed" class="border-t border-gray-200 dark:border-gray-700">
      <!-- Lead Survey Results -->
      <div v-if="isLeadSurvey" class="p-6 space-y-6">
      <!-- Lead Score -->
      <div class="bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900 dark:to-blue-900 p-4 rounded-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Lead Score</p>
            <p class="text-4xl font-bold text-purple-600 dark:text-purple-400 mt-1">
              {{ analysis.analysis_result.lead_score }}<span class="text-2xl text-gray-500">/100</span>
            </p>
          </div>
          <div class="text-right">
            <span :class="getScoreClass(analysis.analysis_result.lead_score)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
              {{ getScoreLabel(analysis.analysis_result.lead_score) }}
            </span>
          </div>
        </div>
        <p v-if="analysis.analysis_result.score_reasoning" class="mt-3 text-sm text-gray-700 dark:text-gray-300">
          {{ analysis.analysis_result.score_reasoning }}
        </p>
      </div>

      <!-- Suggested Tasks -->
      <div v-if="analysis.analysis_result.suggested_tasks && analysis.analysis_result.suggested_tasks.length">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Suggested Tasks</h4>
        <ul class="space-y-2">
          <li v-for="(task, index) in analysis.analysis_result.suggested_tasks" :key="index" class="flex items-start p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <svg class="w-5 h-5 mr-3 mt-0.5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ task }}</span>
          </li>
        </ul>
      </div>

      <!-- Follow-up Message -->
      <div v-if="analysis.analysis_result.follow_up_message">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Suggested Follow-up Message</h4>
          
          <!-- Scheduled State -->
          <div v-if="analysis.has_scheduled_followup" class="flex items-center px-3 py-1.5 text-sm font-medium text-green-700 bg-green-50 dark:bg-green-900 dark:text-green-200 rounded-lg border border-green-200 dark:border-green-700">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Follow-up Scheduled
          </div>

          <!-- Schedule Button (only if linked to a record) -->
          <button
            v-else-if="canScheduleFollowUp"
            @click="openScheduleFollowUpModal"
            class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Schedule Follow-up
          </button>

          <!-- Disabled state with tooltip -->
          <div v-else class="relative group">
            <button
              disabled
              class="px-3 py-1.5 text-sm font-medium text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg cursor-not-allowed">
              <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
              Schedule Follow-up
            </button>
          </div>
        </div>
        
        <!-- Warning message if cannot schedule -->
        <div v-if="!canScheduleFollowUp && !analysis.has_scheduled_followup" class="mb-3 p-3 bg-amber-50 dark:bg-amber-900 rounded-lg border border-amber-200 dark:border-amber-700">
          <div class="flex items-start">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="text-sm text-amber-800 dark:text-amber-200">
              <p class="font-medium mb-1">Cannot Schedule Follow-up</p>
              <p>Follow-up emails can only be sent to existing Leads, Contacts, or Vendors. Please convert this response to a Lead first, then you can schedule a follow-up.</p>
            </div>
          </div>
        </div>

        <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg border-l-4 border-blue-600">
          <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ replacePlaceholders(unescapeNewlines(analysis.analysis_result.follow_up_message)) }}</p>
        </div>
        <p v-if="analysis.analysis_result.recommended_send_time" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Recommended send time: {{ formatDate(analysis.analysis_result.recommended_send_time) }}
        </p>
      </div>

      <!-- Manual Schedule Follow-up for responses without AI message -->
      <div v-else class="mt-4">
        <!-- Scheduled State -->
        <div v-if="analysis.has_scheduled_followup" class="w-full px-4 py-3 text-sm font-medium text-green-700 bg-green-50 dark:bg-green-900 dark:text-green-200 rounded-lg border border-green-200 dark:border-green-700 flex items-center justify-center">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          Follow-up Email Scheduled
        </div>

        <!-- Schedule Button (only if linked to a record) -->
        <button
          v-else-if="canScheduleFollowUp"
          @click="openScheduleFollowUpModal"
          class="w-full px-4 py-3 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 rounded-lg border border-blue-200 dark:border-blue-700 transition-colors">
          <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
          Schedule Manual Follow-up Email
        </button>

        <!-- Disabled state with warning -->
        <div v-else>
          <button
            disabled
            class="w-full px-4 py-3 text-sm font-medium text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg cursor-not-allowed border border-gray-300 dark:border-gray-600">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            Schedule Manual Follow-up Email
          </button>
          <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900 rounded-lg border border-amber-200 dark:border-amber-700">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
              <div class="text-sm text-amber-800 dark:text-amber-200">
                <p class="font-medium mb-1">Cannot Schedule Follow-up</p>
                <p>Follow-up emails require a linked record (Lead, Contact, or Vendor). Please convert this response first.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Follow-up Survey Results -->
    <div v-if="isFollowUpSurvey" class="p-6 space-y-6">
      <!-- Satisfaction Score -->
      <div class="bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900 dark:to-blue-900 p-4 rounded-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Satisfaction Score</p>
            <p class="text-4xl font-bold text-green-600 dark:text-green-400 mt-1">
              {{ analysis.analysis_result.satisfaction_score }}<span class="text-2xl text-gray-500">/10</span>
            </p>
          </div>
          <div class="text-right">
            <span :class="getSatisfactionClass(analysis.analysis_result.satisfaction_score)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
              {{ getSatisfactionLabel(analysis.analysis_result.satisfaction_score) }}
            </span>
          </div>
        </div>
        <p v-if="analysis.analysis_result.key_sentiment" class="mt-3 text-sm text-gray-700 dark:text-gray-300">
          <span class="font-semibold">Sentiment:</span> {{ analysis.analysis_result.key_sentiment }}
        </p>
      </div>

      <!-- Suggested Response -->
      <div v-if="analysis.analysis_result.suggested_response">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Suggested Response</h4>
        <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg border-l-4 border-blue-600">
          <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ analysis.analysis_result.suggested_response }}</p>
        </div>
        <p v-if="analysis.analysis_result.next_contact_timing" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Next contact: {{ analysis.analysis_result.next_contact_timing }}
        </p>
      </div>

      <!-- Follow-up Tasks -->
      <div v-if="analysis.analysis_result.follow_up_tasks && analysis.analysis_result.follow_up_tasks.length">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Follow-up Tasks</h4>
        <div class="space-y-3">
          <div v-for="(task, index) in analysis.analysis_result.follow_up_tasks" :key="index" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <div class="flex items-start justify-between mb-2">
              <h5 class="font-semibold text-gray-900 dark:text-white">{{ task.task_name }}</h5>
              <span :class="getPriorityClass(task.priority)" class="px-2 py-1 rounded text-xs font-medium">
                {{ task.priority }}
              </span>
            </div>
            <p v-if="task.notes" class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ task.notes }}</p>
            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 space-x-4">
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Due: {{ formatDate(task.due_date) }}
              </span>
              <span v-if="task.reminder" class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                Reminder: {{ formatDate(task.reminder) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Options -->
    <div class="border-t border-gray-200 dark:border-gray-700 p-6  rounded-b-lg">
      <div class="space-y-4">
        <!-- Show current record info if attached to a record -->
        <div v-if="analysis.owner_record" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900 rounded-lg border border-blue-200 dark:border-blue-700">
          <p class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-1">
            Linked to {{ analysis.owner_record.type.charAt(0).toUpperCase() + analysis.owner_record.type.slice(1) }}:
          </p>
          <p class="text-sm text-blue-700 dark:text-blue-300">
            {{ analysis.owner_record.name }}
            <span v-if="analysis.owner_record.email" class="text-xs text-blue-600 dark:text-blue-400 ml-2">
              ({{ analysis.owner_record.email }})
            </span>
          </p>
        </div>

        <!-- Options for responses linked to existing Lead -->
        <div v-if="analysis.owner_record_type === 'lead'" class="space-y-3">
          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose an action:</h4>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_tasks_only"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ recordResponse?.tasks_applied ? 'Tasks Already Applied' : 'Apply Tasks to Lead' }}
              </span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ recordResponse?.tasks_applied ? 'Tasks have been created for this lead' : 'Create suggested tasks for this lead' }}
              </p>
            </div>
          </label>

          <label v-if="isLeadSurvey" class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_score_to_lead"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Apply Score to Lead</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Update lead's score based on survey analysis</p>
            </div>
          </label>
        </div>

        <!-- Options for responses linked to existing Contact -->
        <div v-else-if="analysis.owner_record_type === 'contact'" class="space-y-3">
          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose an action:</h4>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_tasks_only"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ recordResponse?.tasks_applied ? 'Tasks Already Applied' : 'Apply Tasks to Contact' }}
              </span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ recordResponse?.tasks_applied ? 'Tasks have been created for this contact' : 'Create suggested tasks for this contact' }}
              </p>
            </div>
          </label>

          <label v-if="isLeadSurvey" class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_score_to_contact"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Apply Score to Contact</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Update contact's score based on survey analysis</p>
            </div>
          </label>

          <label v-if="isLeadSurvey" class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="convert_to_lead"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Convert Contact to Lead</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Convert this contact into a lead with AI score</p>
            </div>
          </label>
        </div>

        <!-- Options for responses linked to existing Vendor -->
        <div v-else-if="analysis.owner_record_type === 'vendor'" class="space-y-3">
          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose an action:</h4>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_tasks_only"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ recordResponse?.tasks_applied ? 'Tasks Already Applied' : 'Apply Tasks to Vendor' }}
              </span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ recordResponse?.tasks_applied ? 'Tasks have been created for this vendor' : 'Create suggested tasks for this vendor' }}
              </p>
            </div>
          </label>
        </div>

        <!-- Options for Lead Survey (not yet linked to any record) -->
        <div v-else-if="isLeadSurvey && !analysis.survey_response?.converted" class="space-y-3">
          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose an action:</h4>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="convert_to_lead"
              class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Convert to Lead</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Create a new lead with score card</p>
            </div>
          </label>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="convert_to_lead_and_apply_tasks"
              class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Convert to Lead & Apply Tasks</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Create lead with score card and add suggested tasks</p>
            </div>
          </label>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_tasks_only"
              class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ recordResponse?.tasks_applied ? 'Tasks Already Applied' : 'Only Apply Tasks' }}
              </span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ recordResponse?.tasks_applied ? 'Tasks have been created' : 'Create tasks without converting to lead' }}
              </p>
            </div>
          </label>
        </div>

        <!-- Options for Follow-up Survey or generic case -->
        <div v-else class="space-y-3">
          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Choose an action:</h4>

          <label class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_tasks_only"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ recordResponse?.tasks_applied ? 'Tasks Already Applied' : 'Apply Tasks' }}
              </span>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ recordResponse?.tasks_applied ? 'Follow-up tasks have been created' : 'Create suggested follow-up tasks' }}
              </p>
            </div>
          </label>

          <label v-if="isFollowUpSurvey" class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
              type="radio"
              v-model="selectedAction"
              value="apply_response"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
            />
            <div class="ml-3">
              <span class="text-sm font-medium text-gray-900 dark:text-white">Apply Response</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">Create communication from suggested response</p>
            </div>
          </label>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
            Close
          </button>

          <button
            @click="applySelectedAction"
            :disabled="!selectedAction || applying"
            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="!applying">Submit</span>
            <span v-else>Processing...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Duplicate Lead Dialog -->
    <div v-if="showDuplicateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
        <div class="flex items-start mb-4">
          <div class="flex-shrink-0">
            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              Duplicate Lead Found
            </h3>
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
              <p class="mb-3">A lead with this email already exists:</p>
              <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded border border-gray-200 dark:border-gray-600">
                <p class="font-medium text-gray-900 dark:text-white">{{ existingLead?.name }}</p>
                <p class="text-gray-600 dark:text-gray-400">{{ existingLead?.email }}</p>
              </div>
              <p class="mt-3">Would you like to update the existing lead or create a new one?</p>
            </div>
          </div>
        </div>
        
        <div class="flex flex-col space-y-2 mt-6">
          <button
            @click="handleUpdateExisting"
            :disabled="applying"
            class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="!applying">Update Existing Lead</span>
            <span v-else>Updating...</span>
          </button>
          
          <button
            @click="handleCreateNew"
            :disabled="applying"
            class="w-full px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="!applying">Create New Lead</span>
            <span v-else>Creating...</span>
          </button>
          
          <button
            @click="handleCancelDuplicate"
            :disabled="applying"
            class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Schedule Follow-up Email Modal -->
    <div v-if="showScheduleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="closeScheduleModal">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-6 z-10">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
              Schedule Follow-up Email
            </h3>
            <button
              @click="closeScheduleModal"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <div class="p-6 space-y-4">
          <!-- Error Message -->
          <div v-if="scheduleErrorMessage" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div class="flex-1">
                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                  {{ scheduleErrorMessage }}
                </p>
                <!-- Display individual field errors if available -->
                <ul v-if="Object.keys(scheduleErrors).length > 0" class="mt-2 text-sm text-red-700 dark:text-red-400 list-disc list-inside">
                  <li v-for="(errors, field) in scheduleErrors" :key="field">
                    <span class="font-medium capitalize">{{ field.replace('_', ' ') }}:</span>
                    {{ Array.isArray(errors) ? errors.join(', ') : errors }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Subject
            </label>
            <input
              v-model="followUpForm.subject"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter email subject"
            />
          </div>

          <!-- Message -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Message
            </label>
            <textarea
              v-model="followUpForm.message"
              rows="8"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter your follow-up message"
            ></textarea>
          </div>

          <!-- Send Date and Time -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Send Date
              </label>
              <input
                v-model="followUpForm.sendDate"
                type="date"
                :min="minDate"
                :class="[
                  'w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent',
                  scheduleErrors.send_date 
                    ? 'border-red-300 dark:border-red-600 focus:ring-red-500' 
                    : 'border-gray-300 dark:border-gray-600 focus:ring-blue-500'
                ]"
              />
              <p v-if="scheduleErrors.send_date" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ Array.isArray(scheduleErrors.send_date) ? scheduleErrors.send_date[0] : scheduleErrors.send_date }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Send Time
              </label>
              <input
                v-model="followUpForm.sendTime"
                type="time"
                :class="[
                  'w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent',
                  scheduleErrors.send_time 
                    ? 'border-red-300 dark:border-red-600 focus:ring-red-500' 
                    : 'border-gray-300 dark:border-gray-600 focus:ring-blue-500'
                ]"
              />
              <p v-if="scheduleErrors.send_time" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ Array.isArray(scheduleErrors.send_time) ? scheduleErrors.send_time[0] : scheduleErrors.send_time }}
              </p>
            </div>
          </div>

          <!-- Timezone Notice -->
          <div class="p-3 bg-blue-50 dark:bg-blue-900 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-sm text-blue-700 dark:text-blue-300">
                The email will be scheduled in your local timezone. The system will automatically handle timezone conversions.
              </p>
            </div>
          </div>

          <!-- Recipient Info (if available) -->
          <div v-if="analysis.survey_response" class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recipient:</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ getRecipientName() }}
              <span v-if="analysis.survey_response.email" class="text-gray-500">
                ({{ analysis.survey_response.email }})
              </span>
            </p>
          </div>
        </div>

        <div class="sticky bottom-0 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 p-6 flex justify-end space-x-3">
          <button
            @click="closeScheduleModal"
            :disabled="scheduling"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Cancel
          </button>
          <button
            @click="scheduleFollowUp"
            :disabled="scheduling || !isFormValid"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="!scheduling">Schedule Email</span>
            <span v-else>Scheduling...</span>
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AiAnalysisResults',
  props: {
    analysis: {
      type: Object,
      required: true
    },
    response: {
      type: Object,
      required: true
    },
    teamid: {
      type: Number,
      required: true
    },
    currentusername: {
      type: String,
      required: false,
      default: ''
    },
    initiallyCollapsed: {
      type: [Boolean, String],
      default: true
    }
  },
  data() {
    return {
      applying: false,
      selectedAction: null,
      recordResponse: null,
      showDuplicateDialog: false,
      existingLead: null,
      pendingAction: null,
      showScheduleModal: false,
      scheduling: false,
      scheduleErrors: {},
      scheduleErrorMessage: '',
      // isCollapsed: this.initiallyCollapsed === true || this.initiallyCollapsed === 'true',
      followUpForm: {
        subject: '',
        message: '',
        sendDate: '',
        sendTime: ''
      }
    };
  },
    mounted() {
        this.$root.analysisCollapsed = false;
        this.recordResponse = this.response;
    },
  computed: {
    isLeadSurvey() {
      return this.analysis.survey_type === 'lead';
    },
    isFollowUpSurvey() {
      return this.analysis.survey_type === 'follow_up';
    },
    minDate() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },
    isFormValid() {
      return this.followUpForm.subject.trim() !== '' &&
             this.followUpForm.message.trim() !== '' &&
             this.followUpForm.sendDate !== '' &&
             this.followUpForm.sendTime !== '';
    },
    canScheduleFollowUp() {
      // Follow-up emails can only be sent to existing leads, contacts, or vendors
      // Check if the survey response is linked to any of these record types
      return this.analysis.owner_record_type && 
             ['lead', 'contact', 'vendor'].includes(this.analysis.owner_record_type);
    }
  },
  methods: {
    toggleCollapse() {
      this.$root.analysisCollapsed = !this.$root.analysisCollapsed;
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    getScoreClass(score) {
      if (score >= 80) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
      if (score >= 60) return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
      if (score >= 40) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    },
    getScoreLabel(score) {
      if (score >= 80) return 'Hot Lead';
      if (score >= 60) return 'Warm Lead';
      if (score >= 40) return 'Cool Lead';
      return 'Cold Lead';
    },
    getSatisfactionClass(score) {
      if (score >= 8) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
      if (score >= 6) return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
      if (score >= 4) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    },
    getSatisfactionLabel(score) {
      if (score >= 8) return 'Very Satisfied';
      if (score >= 6) return 'Satisfied';
      if (score >= 4) return 'Neutral';
      return 'Unsatisfied';
    },
    getPriorityClass(priority) {
      const p = (priority || 'medium').toLowerCase();
      if (p === 'high') return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
      if (p === 'medium') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    },
    async applySelectedAction(forceCreate = false, updateExisting = false) {
      if (!this.selectedAction) {
        return;
      }

      this.applying = true;

      try {
        const payload = {
          analysis_id: this.analysis.id,
          action: this.selectedAction
        };

        if (forceCreate) {
          payload.force_create = true;
        }
        if (updateExisting) {
          payload.update_existing = true;
        }

        const response = await axios.post(
          `/ai/apply-suggestions?team=${this.teamid}`,
          payload
        );

        // Update the survey_response with the latest data from the backend
        if (response.data.survey_response) {
          this.analysis.survey_response = response.data.survey_response;
          this.recordResponse.tasks_applied = response.data.survey_response.tasks_applied;
          // this.response?.tasks_applied =
        }

        const message = response.data.result.updated_existing 
          ? 'Existing lead updated successfully!' 
          : 'Suggestions applied successfully!';

        // Show success notification
        if (this.$root.showNotification) {
          this.$root.showNotification(message, 'success');
        }

        // Close dialog and modal after applying
        this.showDuplicateDialog = false;

        // Reset selected action to show the updated state
        this.selectedAction = null;
      } catch (error) {
        console.error('Failed to apply suggestions:', error);
        
        // Handle duplicate detection
        if (error.response?.status === 409 && error.response?.data?.error === 'duplicate_found') {
          this.existingLead = error.response.data.existing_lead;
          this.pendingAction = this.selectedAction;
          this.showDuplicateDialog = true;
          this.applying = false;
          return;
        }

        const errorMessage = error.response?.data?.message || 'Failed to apply suggestions';
        
        if (this.$root.showNotification) {
          this.$root.showNotification(errorMessage, 'error');
        }
      } finally {
        if (!this.showDuplicateDialog) {
          this.applying = false;
        }
      }
    },
    openScheduleFollowUpModal() {
      // Prevent scheduling if not linked to a record
      if (!this.canScheduleFollowUp) {
        if (this.$root.showNotification) {
          this.$root.showNotification(
            'Follow-up emails can only be sent to existing Leads, Contacts, or Vendors. Please convert this response first.',
            'error'
          );
        }
        return;
      }

      // Pre-fill form with AI-generated content if available
      if (this.analysis.analysis_result.follow_up_message) {
        // Process the message: unescape newlines and replace placeholders
        const processedMessage = this.replacePlaceholders(
          this.unescapeNewlines(this.analysis.analysis_result.follow_up_message)
        );
        this.followUpForm.message = processedMessage;

        this.followUpForm.subject = `Follow-up: ${this.analysis.survey_response?.survey?.title || 'Your Survey Response'}`;
        
        // Pre-fill date/time from AI recommendation
        if (this.analysis.analysis_result.recommended_send_time) {
          const recommendedTime = new Date(this.analysis.analysis_result.recommended_send_time);
          this.followUpForm.sendDate = recommendedTime.toISOString().split('T')[0];
          this.followUpForm.sendTime = recommendedTime.toTimeString().slice(0, 5);
        } else {
          // Default to tomorrow at 10:00 AM
          const tomorrow = new Date();
          tomorrow.setDate(tomorrow.getDate() + 1);
          this.followUpForm.sendDate = tomorrow.toISOString().split('T')[0];
          this.followUpForm.sendTime = '10:00';
        }
      } else {
        // Manual entry - set defaults
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        this.followUpForm.sendDate = tomorrow.toISOString().split('T')[0];
        this.followUpForm.sendTime = '10:00';
        this.followUpForm.subject = `Follow-up: ${this.analysis.survey_response?.survey?.title || 'Your Survey Response'}`;
        this.followUpForm.message = '';
      }
      
      this.showScheduleModal = true;
    },
    closeScheduleModal() {
      this.showScheduleModal = false;
      this.scheduleErrors = {};
      this.scheduleErrorMessage = '';
      this.followUpForm = {
        subject: '',
        message: '',
        sendDate: '',
        sendTime: ''
      };
    },
    unescapeNewlines(text) {
      return text.replace(/\\n/g, '\n');
    },
    replacePlaceholders(text) {
      // Replace AI-generated placeholders with user information
      // Note: The backend will also handle this, but we pre-fill for user preview/editing
      if (!text) return text;
      
      // Use the prop passed from parent component
      const userName = this.currentusername || this.$root.user?.name || 'Your Name';
      const userEmail = this.$root.user?.email || '';
      const userPhone = this.$root.user?.phone || '';
      
      let replaced = text;
      replaced = replaced.replace(/\[Your Name\]/g, userName);
      replaced = replaced.replace(/\[Your Email\]/g, userEmail);
      replaced = replaced.replace(/\[Your Phone\]/g, userPhone);
      
      return replaced;
    },
    getRecipientName() {
      if (!this.analysis.survey_response) return 'Unknown';
      const firstName = this.analysis.survey_response.first_name || '';
      const lastName = this.analysis.survey_response.last_name || '';
      return `${firstName} ${lastName}`.trim() || 'Unknown';
    },
    async scheduleFollowUp() {
      if (!this.isFormValid) {
        return;
      }

      // Clear previous errors
      this.scheduleErrors = {};
      this.scheduleErrorMessage = '';
      this.scheduling = true;

      try {
        const payload = {
          survey_response_id: this.analysis.survey_response_id,
          subject: this.followUpForm.subject,
          message: this.followUpForm.message,
          send_date: this.followUpForm.sendDate,
          send_time: this.followUpForm.sendTime
        };

        const response = await axios.post(
          `/ai/schedule-follow-up?team=${this.teamid}`,
          payload
        );

        if (this.$root.showNotification) {
          const scheduledTime = new Date(response.data.scheduled_at).toLocaleString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          });
          this.$root.showNotification(
            `Follow-up email scheduled for ${scheduledTime} (${response.data.timezone || 'your timezone'})`,
            'success'
          );
        }

        // Update the analysis to mark follow-up as scheduled
        this.analysis.has_scheduled_followup = true;

        this.closeScheduleModal();
      } catch (error) {
        console.error('Failed to schedule follow-up email:', error);
        
        // Handle validation errors
        if (error.response?.status === 422 || error.response?.status === 400) {
          this.scheduleErrors = error.response.data.errors || {};
          this.scheduleErrorMessage = error.response.data.message || 'Validation failed';
        } else {
          this.scheduleErrorMessage = error.response?.data?.message || 'Failed to schedule follow-up email';
        }
        
        // Also show notification
        if (this.$root.showNotification) {
          this.$root.showNotification(this.scheduleErrorMessage, 'error');
        }
      } finally {
        this.scheduling = false;
      }
    },
    handleUpdateExisting() {
      this.showDuplicateDialog = false;
      this.selectedAction = this.pendingAction;
      this.applySelectedAction(false, true);
    },
    handleCreateNew() {
      this.showDuplicateDialog = false;
      this.selectedAction = this.pendingAction;
      this.applySelectedAction(true, false);
    },
    handleCancelDuplicate() {
      this.showDuplicateDialog = false;
      this.applying = false;
      this.existingLead = null;
      this.pendingAction = null;
    }
  }
};
</script>

