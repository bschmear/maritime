<template>
  <button
    type="button"
    class="inline-flex items-center justify-center gap-1.5 text-white focus:outline-none focus:ring-4 font-medium rounded-lg disabled:opacity-60 disabled:cursor-not-allowed"
    :class="[
      status
        ? 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-300 dark:bg-yellow-600 dark:hover:bg-yellow-700 dark:focus:ring-yellow-800'
        : 'bg-green-600 hover:bg-green-700 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800',
      small ? 'px-2 py-1.5 text-xs' : 'px-5 py-2.5 text-sm',
    ]"
    :disabled="loading"
    :title="status ? 'Deactivate survey' : 'Publish survey'"
    @click="handleToggleStatus"
  >
    <span
      v-if="loading"
      class="material-icons text-[18px] leading-none animate-spin"
    >sync</span>
    <span
      v-else
      class="material-icons leading-none shrink-0"
      :class="small ? 'text-[16px]' : 'text-[20px] mr-0.5'"
    >{{ status ? 'pause_circle' : 'publish' }}</span>

    <span v-if="small && !loading" class="font-semibold leading-none whitespace-nowrap">
      {{ status ? 'Pause' : 'Publish' }}
    </span>

    <span v-if="!small">
      <span v-if="loading">{{ status ? 'Deactivating…' : 'Publishing…' }}</span>
      <span v-else>{{ status ? 'Deactivate' : 'Publish' }}</span>
    </span>
  </button>
</template>

<script>
export default {
  name: 'SurveyStatusToggle',
  props: {
    small: {
      type: Boolean,
      default: false,
    },
    status: {
      type: Boolean,
      required: true,
    },
    updateroute: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
    };
  },
  methods: {
    async handleToggleStatus() {
      const newStatus = !this.status;
      const confirmMessage = newStatus
        ? 'Are you sure you want to publish this survey? It will become available to respondents.'
        : 'Are you sure you want to deactivate this survey? It will no longer be available to respondents.';

      if (!confirm(confirmMessage)) {
        return;
      }

      this.loading = true;

      try {
        const response = await axios.put(this.updateroute, {
          status: newStatus,
        });

        if (response.data) {
          const msg = newStatus
            ? 'Survey published successfully.'
            : 'Survey deactivated successfully.';
          if (typeof this.$root.createToast === 'function') {
            this.$root.createToast('success', msg);
          } else {
            alert(msg);
          }
          window.location.reload();
        }
      } catch (error) {
        console.error('Error:', error);
        let errorMessage = 'An error occurred while updating the survey status.';

        if (error.response?.data) {
          if (error.response.data.errors) {
            errorMessage = Object.values(error.response.data.errors).flat().join('\n');
          } else if (error.response.data.message) {
            errorMessage = error.response.data.message;
          }
        }

        if (typeof this.$root.createToast === 'function') {
          this.$root.createToast('error', errorMessage);
        } else {
          alert('Error: ' + errorMessage);
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
