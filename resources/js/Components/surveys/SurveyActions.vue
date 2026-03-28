<template>
  <div class="flex items-center gap-2" v-cloak>
    <button
      v-if="statusBoolean"
      type="button"
      @click="$root.copyLink('survey-link', 'Link copied to clipboard!')"
      class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
      <i class="fas fa-share-alt mr-2"></i>
      Share
    </button>

    <div class="relative">
      <button
        @click.prevent="showForm = !showForm"
        type="button"
        class="p-2.5 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700">
        <i class="fas fa-ellipsis-v"></i>
      </button>

      <div
        v-show="showForm"
        @click.away="showForm = false"
        class="absolute right-0 z-10 mt-2 w-56 bg-white divide-y divide-gray-100 rounded-lg shadow dark:bg-gray-700 dark:divide-gray-600">
        <ul class="py-2 text-sm text-gray-700 dark:text-gray-200">
          <li>
            <form :action="surveysclone" method="POST" class="w-full">
              <input type="hidden" name="_token" :value="csrfToken">
              <button type="submit" class="flex items-center w-full px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white text-left">
                <i class="fas fa-clone w-4 mr-2"></i>
                Clone Survey
              </button>
            </form>
          </li>
          <li>
            <button
              v-if="statusBoolean"
              type="button"
              @click="toggleStatus(false)"
              class="flex items-center w-full px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white text-left">
              <i class="fas fa-pause w-4 mr-2"></i>
              Deactivate
            </button>
            <button
              v-else
              type="button"
              @click="toggleStatus(true)"
              class="flex items-center w-full px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white text-left">
              <i class="fas fa-play w-4 mr-2"></i>
              Activate
            </button>
          </li>
        </ul>
        <div class="py-2">
          <button
            type="button"
            @click="toggleDelete"
            class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-red-500 dark:hover:text-red-400 text-left">
            <i class="fas fa-trash-alt w-4 mr-2"></i>
            Delete Survey
          </button>
        </div>
      </div>
    </div>
  </div>

<div v-if="$root.confirmDelete" @click="$root.confirmDelete = false" class="bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40"></div>
<div v-show="$root.confirmDelete" tabindex="-1" :aria-hidden="!$root.confirmDelete" class="modal-style flex items-center justify-center" v-cloak>

    <div class="relative p-4 w-full max-w-md h-full md:h-auto">
        <div class="relative p-4 text-center bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
            <button type="button"
                    class="text-gray-400 absolute top-2.5 right-2.5 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                    @click="$root.confirmDelete = false">
                <i class="fas fa-times"></i>
            </button>
            <svg class="text-gray-400 dark:text-gray-500 w-11 h-11 mb-3.5 mx-auto" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <p class="mb-4 text-gray-500 dark:text-gray-300">Are you sure you want to delete this survey?</p>
            <div class="flex justify-center items-center space-x-4">
                <button @click="$root.confirmDelete = false" type="button" class="btn-outline sm">
                    No, cancel
                </button>
                <button type="button" @click="deleteSurvey" class="red-button sm">
                    Yes, I'm sure
                </button>
            </div>
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
      csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    };
  },

  computed: {
    statusBoolean() {
      return this.status === true || this.status === 1 || this.status === '1';
    }
  },

  methods: {
    toggleDelete() {
      // Close dropdown
      this.showForm = false;
      this.$root.confirmDelete = !this.$root.confirmDelete;
    },
    async deleteSurvey() {
        const response = await axios.delete(this.deleteroute);

        if (response.status === 200) {
            window.location.href = this.surveysindex;
        }  else {
            alert(response.message)
        }
    },
    async toggleStatus(status) {
      // Close dropdown
      this.showForm = false;

      try {
        const response = await axios.put(this.surveysupdate, {
          status: status
        });

        this.showToast('success', `Survey ${status ? 'activated' : 'deactivated'} successfully`);

        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } catch (error) {
        this.showToast('error', 'Error updating survey status');
        console.error(error);
      }
    },

    showToast(type, message) {
      this.$root.createToast(type, message || 'Action completed successfully.');
    }
  }
};
</script>
