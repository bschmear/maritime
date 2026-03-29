<template>
  <div class="flex items-center gap-2" v-cloak>

    <!-- Share button -->
    <button
      v-if="statusBoolean"
      type="button"
      @click="$root.copyLink('survey-link', 'Link copied to clipboard!')"
      class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors"
    >
      <span class="material-icons text-[16px]">share</span>
      Share
    </button>

    <!-- Kebab menu -->
    <div class="relative">
      <button
        @click.prevent="showForm = !showForm"
        type="button"
        class="p-2.5 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 transition-colors"
        aria-label="More actions"
      >
        <span class="material-icons text-[20px]">more_vert</span>
      </button>

      <!-- Dropdown -->
      <div
        v-show="showForm"
        @click.away="showForm = false"
        class="absolute right-0 z-10 mt-2 w-52 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg overflow-hidden"
      >
        <!-- Clone -->
        <form :action="surveysclone" method="POST" class="w-full">
          <input type="hidden" name="_token" :value="csrfToken" />
          <button
            type="submit"
            class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors text-left"
          >
            <span class="material-icons text-[18px] text-gray-400">content_copy</span>
            Clone Survey
          </button>
        </form>

        <div class="border-t border-gray-100 dark:border-gray-600">
          <!-- Deactivate -->
          <button
            v-if="statusBoolean"
            type="button"
            @click="toggleStatus(false)"
            class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors text-left"
          >
            <span class="material-icons text-[18px] text-amber-500">pause_circle</span>
            Deactivate
          </button>
          <!-- Activate -->
          <button
            v-else
            type="button"
            @click="toggleStatus(true)"
            class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors text-left"
          >
            <span class="material-icons text-[18px] text-green-500">play_circle</span>
            Activate
          </button>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-600">
          <!-- Delete -->
          <button
            type="button"
            @click="toggleDelete"
            class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left"
          >
            <span class="material-icons text-[18px]">delete_outline</span>
            Delete Survey
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete confirmation backdrop -->
  <div
    v-if="$root.confirmDelete"
    @click="$root.confirmDelete = false"
    class="fixed inset-0 z-40 bg-gray-900/60 dark:bg-gray-900/80"
  />

  <!-- Delete confirmation modal -->
  <div
    v-show="$root.confirmDelete"
    :aria-hidden="!$root.confirmDelete"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    v-cloak
  >
    <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 text-center">

      <!-- Close button -->
      <button
        type="button"
        @click="$root.confirmDelete = false"
        class="absolute top-3 right-3 p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
        aria-label="Close"
      >
        <span class="material-icons text-[20px]">close</span>
      </button>

      <!-- Icon -->
      <span class="material-icons text-5xl text-gray-300 dark:text-gray-500 block mb-3">delete_outline</span>

      <p class="text-gray-600 dark:text-gray-300 mb-6">
        Are you sure you want to delete this survey? This cannot be undone.
      </p>

      <div class="flex items-center justify-center gap-3">
        <button
          @click="$root.confirmDelete = false"
          type="button"
          class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
        >
          No, cancel
        </button>
        <button
          type="button"
          @click="deleteSurvey"
          class="px-5 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg focus:outline-none focus:ring-4 focus:ring-red-300 dark:focus:ring-red-800 transition-colors"
        >
          Yes, delete
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SurveyActions',

  props: ['status', 'uuid', 'deleteroute', 'surveysindex', 'surveysupdate', 'surveysclone'],

  data() {
    return {
      showForm: false,
      csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
  },

  computed: {
    statusBoolean() {
      return this.status === true || this.status === 1 || this.status === '1';
    },
  },

  methods: {
    toggleDelete() {
      this.showForm = false;
      this.$root.confirmDelete = !this.$root.confirmDelete;
    },

    async deleteSurvey() {
      const response = await axios.delete(this.deleteroute);
      if (response.status === 200) {
        window.location.href = this.surveysindex;
      } else {
        alert(response.message);
      }
    },

    async toggleStatus(status) {
      this.showForm = false;
      try {
        await axios.put(this.surveysupdate, { status });
        this.showToast('success', `Survey ${status ? 'activated' : 'deactivated'} successfully`);
        setTimeout(() => window.location.reload(), 1500);
      } catch (error) {
        this.showToast('error', 'Error updating survey status');
        console.error(error);
      }
    },

    showToast(type, message) {
      this.$root.createToast(type, message || 'Action completed successfully.');
    },
  },
};
</script>