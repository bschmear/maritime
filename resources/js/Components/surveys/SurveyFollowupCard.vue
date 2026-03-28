<template>
  <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
      <i class="fas fa-calendar-plus text-indigo-600 dark:text-indigo-500 mr-2"></i>
      Follow-up Email
    </h3>

    <!-- Scheduled State -->
    <div v-if="scheduledEmail && scheduledEmail.id || this.follupScheduled" class="space-y-3">
      <div class="flex items-center justify-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
        <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
        <span class="text-sm font-medium text-green-700 dark:text-green-300">
          Follow-up Scheduled
        </span>
      </div>

      <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg">
        <p><strong>Subject:</strong> {{ scheduledEmail.subject }}</p>
        <p><strong>Scheduled for:</strong> {{ formatScheduledDate(scheduledEmail.scheduled_at) }}</p>
      </div>

      <button
        @click="cancelFollowup"
        :disabled="canceling"
        v-if="scheduledEmail && scheduledEmail.id"
        class="inline-flex items-center justify-center btn btn-warning w-full"
      >
        <i class="fas fa-times-circle mr-2"></i>
        <span v-if="canceling">Canceling...</span>
        <span v-else>Cancel Follow-up</span>
      </button>
    </div>

    <!-- Not Scheduled State -->
    <div v-else>
      <button
        @click="showModal = true"
        class="inline-flex items-center justify-center btn btn-primary w-full"
      >
        <i class="fas fa-calendar-plus mr-2"></i>
        Schedule Follow-up
      </button>
      <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
        Send a follow-up email at a scheduled date and time
      </p>
    </div>

    <!-- Schedule Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="background-color: rgba(0, 0, 0, 0.5);">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                <i class="fas fa-calendar-plus mr-2 text-indigo-600 dark:text-indigo-500"></i>
                Schedule Follow-up Email
              </h3>
              <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <div class="space-y-4">
              <div>
                <label for="followup-subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Subject <span class="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  id="followup-subject"
                  v-model="form.subject"
                  placeholder="Follow-up on your survey response"
                  class="input-style"
                  required
                />
              </div>

              <div>
                <label for="followup-message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Message <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="followup-message"
                  v-model="form.message"
                  rows="6"
                  placeholder="Enter your follow-up message..."
                  class="input-style"
                  required
                ></textarea>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="followup-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Send Date <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="date"
                    id="followup-date"
                    v-model="form.send_date"
                    :min="minDate"
                    class="input-style"
                    required
                  />
                </div>

                <div>
                  <label for="followup-time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Send Time <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="time"
                    id="followup-time"
                    v-model="form.send_time"
                    class="input-style"
                    required
                  />
                </div>
              </div>

              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                <div class="flex">
                  <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-2"></i>
                  <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-medium">Your Local Timezone</p>
                    <p class="text-xs mt-1">Email will be scheduled in your local timezone and sent at the specified date and time.</p>
                  </div>
                </div>
              </div>

              <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                <p class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button
              @click="closeModal"
              type="button"
              :disabled="submitting"
              class="btn btn-outline w-full sm:w-auto"
            >
              Cancel
            </button>
            <button
              @click="submit"
              type="button"
              :disabled="submitting || !isFormValid"
              class="btn btn-primary w-full sm:w-auto"
            >
              <i class="fas fa-calendar-check mr-2"></i>
              <span v-if="submitting">Scheduling...</span>
              <span v-else>Schedule Email</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SurveyFollowupCard',
  props: {
    surveyResponseId: {
      type: Number,
      required: true
    },
    teamId: {
      type: Number,
      required: true
    },
    scheduledFollowup: {
      type: [Object, String, null],
      default: null
    }
  },
  data() {
    return {
      showModal: false,
      scheduledEmail: null,
      follupScheduled: false,
      submitting: false,
      canceling: false,
      error: null,
      form: {
        subject: 'Follow-up on your survey response',
        message: '',
        send_date: '',
        send_time: '09:00'
      }
    };
  },
  computed: {
    minDate() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },
    isFormValid() {
      return this.form.subject.trim() !== '' &&
             this.form.message.trim() !== '' &&
             this.form.send_date !== '' &&
             this.form.send_time !== '';
    }
  },
  mounted() {
    // Parse scheduled followup if it's a string
    if (typeof this.scheduledFollowup === 'string' && this.scheduledFollowup) {
      try {
        this.scheduledEmail = JSON.parse(this.scheduledFollowup);
      } catch (e) {
        this.scheduledEmail = null;
      }
    } else {
      this.scheduledEmail = this.scheduledFollowup;
    }
  },
  methods: {
    formatScheduledDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    },
    closeModal() {
      this.showModal = false;
      this.error = null;
      this.form = {
        subject: 'Follow-up on your survey response',
        message: '',
        send_date: '',
        send_time: '09:00'
      };
    },
    async submit() {
      if (!this.isFormValid) {
        this.error = 'Please fill in all required fields.';
        return;
      }

      this.submitting = true;
      this.error = null;

      try {
        const response = await axios.post(
          `/ai/schedule-follow-up?team=${this.teamId}`,
          {
            survey_response_id: this.surveyResponseId,
            subject: this.form.subject,
            message: this.form.message,
            send_date: this.form.send_date,
            send_time: this.form.send_time
          }
        );
        // console.log(response);
        if (response.data && response.data.message) {
          this.$root.createToast('success', response.data.message || 'Follow-up email scheduled successfully!');
          this.scheduledEmail = {
            id: response.data.email_id,
            subject: this.form.subject,
            scheduled_at: response.data.scheduled_at
          };
          this.follupScheduled = true;
          
          this.closeModal();
        }
      } catch (error) {
        // console.error('Error scheduling follow-up:', error);
        this.error = error.response?.data?.message || 'Failed to schedule follow-up email. Please try again.';
      } finally {
        this.submitting = false;
      }
    },
    async cancelFollowup() {
      if (!confirm('Are you sure you want to cancel this scheduled follow-up email?')) {
        return;
      }

      this.canceling = true;

      try {
        const response = await axios.delete(
          `/ai/cancel-scheduled-email?team=${this.teamId}`,
          {
            data: {
              email_id: this.scheduledEmail.id
            }
          }
        );
        if (response.data && response.data.success) {
          this.$root.createToast('success', response.data.message || 'Follow-up email canceled successfully.');
          this.scheduledEmail = null;
          this.follupScheduled = false;
        }
      } catch (error) {
        console.error('Error canceling follow-up:', error);
        alert(error.response?.data?.message || 'Failed to cancel follow-up email. Please try again.');
      } finally {
        this.canceling = false;
      }
    }
  }
};
</script>

