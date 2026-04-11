<template>
  <div class="flex items-center gap-2" v-cloak>
    <!-- Share: public link popover -->
    <div v-if="statusBoolean" class="relative">
      <button
        type="button"
        @click.stop="toggleShare"
        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 transition-colors"
      >
        <span class="material-icons text-[16px]">share</span>
        Share
      </button>

      <div
        v-show="showShare"
        v-cloak
        class="absolute right-0 z-20 mt-2 w-[min(100vw-2rem,22rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-600 dark:bg-gray-800"
        @click.stop
      >
        <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white">Public survey link</p>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
          Anyone with this link can open the survey on your workspace URL. The survey UUID is included in the address.
        </p>
        <div class="flex gap-0">
          <input
            :id="shareInputId"
            type="text"
            readonly
            :value="shareableUrl"
            class="block min-w-0 flex-1 rounded-s-lg border border-gray-300 bg-gray-50 p-2.5 text-xs text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:text-sm"
          />
          <button
            type="button"
            class="inline-flex shrink-0 items-center rounded-e-lg border border-s-0 border-gray-300 bg-gray-200 px-3 text-sm text-gray-900 hover:bg-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-100 dark:hover:bg-gray-500"
            @click="copyShareLink"
          >
            <span class="material-icons text-[18px]">content_copy</span>
          </button>
        </div>
        <p v-if="!shareableUrl" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
          Missing survey identifier — reload the page or contact support.
        </p>
      </div>
    </div>

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

  props: {
    status: [Boolean, Number, String],
    uuid: { type: String, default: '' },
    publicUrl: { type: String, default: '' },
    /** When set, appends ?aid= or &aid= so responses attribute to this user (same as Survey Links). */
    shareAgentId: { type: [Number, String], default: null },
    deleteroute: String,
    surveysindex: String,
    surveysupdate: String,
    surveysclone: String,
  },

  data() {
    return {
      showForm: false,
      showShare: false,
      shareInputId: `survey-share-url-${Math.random().toString(36).slice(2, 9)}`,
      csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
  },

  computed: {
    statusBoolean() {
      return this.status === true || this.status === 1 || this.status === '1';
    },
    basePublicUrl() {
      if (this.publicUrl) {
        return this.publicUrl;
      }
      if (this.uuid && typeof route === 'function') {
        return route('surveysPublicShow', { id: this.uuid });
      }
      return '';
    },
    shareableUrl() {
      let url = this.basePublicUrl;
      if (!url || this.shareAgentId === null || this.shareAgentId === '') {
        return url;
      }
      const aid = String(this.shareAgentId).trim();
      if (!aid) {
        return url;
      }
      const sep = url.includes('?') ? '&' : '?';
      return `${url}${sep}aid=${encodeURIComponent(aid)}`;
    },
  },

  mounted() {
    document.addEventListener('click', this.closeShareOnOutsideClick);
  },

  beforeUnmount() {
    document.removeEventListener('click', this.closeShareOnOutsideClick);
  },

  methods: {
    toggleShare() {
      this.showForm = false;
      this.showShare = !this.showShare;
    },
    closeShareOnOutsideClick() {
      this.showShare = false;
    },
    copyShareLink() {
      const text = this.shareableUrl;
      if (!text) {
        this.showToast('error', 'No public link available.');
        return;
      }
      navigator.clipboard
        .writeText(text)
        .then(() => {
          this.showToast('success', 'Public link copied to clipboard!');
          this.showShare = false;
        })
        .catch(() => {
          const el = document.getElementById(this.shareInputId);
          if (el) {
            el.select();
            el.setSelectionRange(0, 99999);
            try {
              document.execCommand('copy');
              this.showToast('success', 'Public link copied to clipboard!');
              this.showShare = false;
            } catch (e) {
              this.showToast('error', 'Could not copy — select the link and copy manually.');
            }
          } else {
            this.showToast('error', 'Could not copy to clipboard.');
          }
        });
    },
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