<template>
  <button
    @click="handleToggleStatus"
    type="button"
    :class="[
      status ? 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-300 dark:bg-yellow-600 dark:hover:bg-yellow-700 dark:focus:ring-yellow-800' : 'bg-green-600 hover:bg-green-700 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800',
      small ? 'px-2.5 py-1.5 text-xs' : 'px-5 py-2.5 text-sm',
      'text-white focus:ring-4 font-medium rounded-lg focus:outline-none'
    ]"
    :disabled="loading"
    :title="small ? (status ? 'Deactivate Survey' : 'Publish Survey') : ''"
  >
    <i v-if="loading" class="fas fa-spinner fa-spin"></i>
    <i v-else :class="['fas', status ? 'fa-pause-circle' : 'fa-rocket', !small ? 'mr-2' : '']"></i>
    <span v-if="!small">
      <span v-if="loading">
        {{ status ? 'Deactivating...' : 'Publishing...' }}
      </span>
      <span v-else>
        {{ status ? 'Deactivate' : 'Publish' }}
      </span>
    </span>
  </button>
</template>

<script>
export default {
  name: 'SurveyStatusToggle',
  props: {
    small: {
      type: Boolean,
      required: false
    },
    status: {
      type: Boolean,
      required: true
    },
    updateroute: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      loading: false
    };
  },
  methods: {
    async handleToggleStatus() {
      const newStatus = !this.status;
      const action = newStatus ? 'publish' : 'deactivate';
      const confirmMessage = newStatus
        ? 'Are you sure you want to publish this survey? It will become available to respondents.'
        : 'Are you sure you want to deactivate this survey? It will no longer be available to respondents.';

      if (!confirm(confirmMessage)) {
        return;
      }

      this.loading = true;

      try {
        const response = await axios.put(this.updateroute, {
          status: newStatus
        });

        if (response.data) {
          alert(newStatus ? 'Survey published successfully!' : 'Survey deactivated successfully!');
          window.location.reload();
        }
      } catch (error) {
        console.error('Error:', error);
        let errorMessage = 'An error occurred while updating the survey status.';

        if (error.response && error.response.data) {
          if (error.response.data.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            errorMessage = errors.join('\n');
          } else if (error.response.data.message) {
            errorMessage = error.response.data.message;
          }
        }

        alert('Error: ' + errorMessage);
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

