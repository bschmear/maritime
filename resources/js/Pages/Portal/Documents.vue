<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    documents: { type: Object, required: true },
    pendingDocumentRequests: { type: Array, default: () => [] },
    activeTab: { type: String, default: 'documents' },
});

const page = usePage();
const tab = ref(props.activeTab);
const uploadingId = ref(null);

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const fileIcon = (ext) => {
    const e = (ext || '').toLowerCase();
    if (e === 'pdf') return 'picture_as_pdf';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(e)) return 'image';
    if (['xls', 'xlsx', 'csv'].includes(e)) return 'table_chart';
    return 'description';
};

const formatSize = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return isNaN(d.getTime()) ? val : d.toLocaleDateString();
};

const setTab = (name) => {
    tab.value = name;
    router.get(route('portal.documents'), { tab: name }, { preserveScroll: true, replace: true });
};

const onFileChosen = (request, event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }

    uploadingId.value = request.id;
    router.post(
        route('portal.document-requests.fulfill', request.id),
        { file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploadingId.value = null;
            },
        },
    );
};
</script>

<template>
    <ClientPortalLayout title="Documents">
        <Head title="Documents - Customer Portal" />

        <div v-if="flashSuccess" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ flashError }}
        </div>

        <div class="border-b border-gray-200 mb-4">
            <nav class="flex gap-6">
                <button
                    type="button"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors"
                    :class="tab === 'documents'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="setTab('documents')"
                >
                    Documents
                </button>
                <button
                    type="button"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2"
                    :class="tab === 'requests'
                        ? 'border-primary-600 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="setTab('requests')"
                >
                    Document requests
                    <span
                        v-if="pendingDocumentRequests.length"
                        class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs bg-primary-100 text-primary-700"
                    >
                        {{ pendingDocumentRequests.length }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Documents tab -->
        <div v-show="tab === 'documents'" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Shared documents</h2>
                <p class="text-xs text-gray-500 mt-1">Files your dealer has shared with you.</p>
            </div>

            <div v-if="documents?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase">Name</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase">Type</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase">Size</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase">Date</th>
                            <th class="px-5 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="material-icons text-lg text-gray-400">{{ fileIcon(doc.file_extension) }}</span>
                                    <span class="font-medium text-gray-900">{{ doc.display_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 uppercase text-xs">{{ doc.file_extension || '—' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ formatSize(doc.file_size) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ formatDate(doc.created_at) }}</td>
                            <td class="px-5 py-3 text-right">
                                <a
                                    :href="doc.download_url"
                                    class="text-primary-600 hover:underline text-sm font-medium"
                                >
                                    Download
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">folder_open</span>
                <p class="text-sm text-gray-500">No shared documents yet.</p>
            </div>
        </div>

        <!-- Document requests tab -->
        <div v-show="tab === 'requests'" class="space-y-4">
            <div v-if="!pendingDocumentRequests.length" class="bg-white rounded-xl border border-gray-200 px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">assignment</span>
                <p class="text-sm text-gray-500">You have no open document requests.</p>
            </div>

            <div
                v-for="req in pendingDocumentRequests"
                :key="req.id"
                class="bg-white rounded-xl border border-gray-200 p-5"
            >
                <h3 class="font-semibold text-gray-900">{{ req.title }}</h3>
                <p v-if="req.description" class="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{{ req.description }}</p>
                <p v-if="req.sent_at" class="text-xs text-gray-400 mt-2">Requested {{ formatDate(req.sent_at) }}</p>

                <div class="mt-4">
                    <label
                        class="flex flex-col items-center justify-center w-full max-w-md aspect-[2/1] border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 bg-gray-50 transition-colors"
                        :class="{ 'opacity-60 pointer-events-none': uploadingId === req.id }"
                    >
                        <span v-if="uploadingId === req.id" class="material-icons text-3xl text-primary-600 animate-spin">sync</span>
                        <template v-else>
                            <span class="material-icons text-3xl text-gray-400">cloud_upload</span>
                            <span class="text-sm text-gray-600 mt-2">Click to upload file</span>
                        </template>
                        <input
                            type="file"
                            class="hidden"
                            accept="image/*,.pdf,.doc,.docx,.txt,.csv,.xlsx"
                            @change="(e) => onFileChosen(req, e)"
                        />
                    </label>
                </div>
            </div>
        </div>
    </ClientPortalLayout>
</template>
