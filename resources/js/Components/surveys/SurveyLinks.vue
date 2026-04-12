<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 p-6">
        <h3 class="mb-4 flex items-center gap-2 text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
            <span class="material-icons text-blue-600 dark:text-blue-400 text-[22px] leading-none">link</span>
            Survey Link
        </h3>
        <div class="space-y-4">
            <!-- User selection for links — public / team visibility -->
            <div v-if="visibility !== 'private' && users.length > 1">
                <label class="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="material-icons text-purple-600 dark:text-purple-400 text-[18px] leading-none">assignment_ind</span>
                    Select user for link
                </label>
                <select 
                    v-model="selectedUserId"
                    class="input-style"
                >
                    <option v-for="u in users" :key="u.id" :value="u.id">
                        {{ u.name }}{{ u.id === currentUserId ? ' (You)' : '' }}
                    </option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    The selected user will be associated with this link for recipients
                </p>
            </div>

            <!-- Direct Link -->
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Direct Link</label>
                <div class="flex">
                    <input 
                        type="text"
                        :value="directLinkUrl"
                        readonly
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-s-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-3 text-sm text-gray-900 bg-gray-200 border border-s-0 border-gray-300 rounded-e-lg hover:bg-gray-300 focus:ring-4 focus:outline-none focus:ring-gray-100 dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:text-gray-200 dark:border-gray-600"
                        title="Copy link"
                        @click="copyToClipboard(directLinkUrl, 'Link copied to clipboard!')"
                    >
                        <span class="material-icons text-[18px] leading-none text-gray-700 dark:text-gray-200">content_copy</span>
                    </button>
                </div>
            </div>

            <!-- Embed Code (dedicated /survey/embed URL — minimal UI for iframes) -->
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Embed Code (iframe)</label>

                <div class="relative">
                    <textarea
                        :value="embedCode"
                        readonly
                        rows="3"
                        class="block p-2.5 w-full text-xs text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 font-mono"
                    ></textarea>
                    <button
                        type="button"
                        class="absolute top-2 right-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 focus:ring-2 focus:outline-none focus:ring-gray-200 dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:text-gray-200 dark:border-gray-600"
                        @click="copyToClipboard(embedCode, 'Embed code copied to clipboard!')"
                    >
                        <span class="material-icons text-[16px] leading-none">content_copy</span>
                        Copy
                    </button>
                </div>
                <p class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="material-icons shrink-0 text-[16px] leading-none text-gray-400 dark:text-gray-500">info</span>
                    <span>Use this code to embed the survey in your website or emails</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SurveyLinks',
    props: {
        baseUrl: {
            type: String,
            required: true,
        },
        /** Absolute URL to {@see surveysPublicEmbed} (with `?id=`). Iframe src uses this + `aid`. */
        embedBaseUrl: {
            type: String,
            default: '',
        },
        users: {
            type: Array,
            required: true
        },
        currentUserId: {
            type: Number,
            required: true
        },
        currentUserName: {
            type: String,
            required: true
        },
        visibility: {
            type: String,
            default: 'public'
        }
    },
    data() {
        return {
            selectedUserId: this.currentUserId
        };
    },
    computed: {
        directLinkUrl() {
            const separator = this.baseUrl.includes('?') ? '&' : '?';
            return `${this.baseUrl}${separator}aid=${this.selectedUserId}`;
        },
        embedIframeSrc() {
            const base = (this.embedBaseUrl && String(this.embedBaseUrl).trim() !== '')
                ? this.embedBaseUrl
                : this.baseUrl;
            const separator = base.includes('?') ? '&' : '?';
            return `${base}${separator}aid=${this.selectedUserId}`;
        },
        embedCode() {
            return `<iframe src="${this.embedIframeSrc}" width="100%" height="600" frameborder="0" title="Survey"></iframe>`;
        },
    },
    methods: {
        copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                this.$root.createToast('success', message || 'Copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
                this.$root.createToast('error', 'Failed to copy to clipboard');
            });
        }
    }
};
</script>

