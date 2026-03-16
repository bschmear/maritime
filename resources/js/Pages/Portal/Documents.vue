<script setup>
import ClientPortalLayout from '@/Layouts/ClientPortalLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    documents: Object,
});

const fileIcon = (type) => {
    if (!type) return 'description';
    if (type.includes('pdf')) return 'picture_as_pdf';
    if (type.includes('image')) return 'image';
    if (type.includes('spreadsheet') || type.includes('excel') || type.includes('csv')) return 'table_chart';
    return 'description';
};
</script>

<template>
    <ClientPortalLayout title="Documents">
        <Head title="Documents - Customer Portal" />

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Your Documents</h2>
            </div>

            <!-- Table -->
            <div v-if="documents?.data?.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Name</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Type</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Size</th>
                            <th class="px-5 py-3 font-medium text-gray-500 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="material-icons text-lg text-gray-400">{{ fileIcon(doc.mime_type) }}</span>
                                    <span class="font-medium text-gray-900">{{ doc.name || doc.original_name || `Document #${doc.id}` }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ doc.mime_type || '-' }}</td>
                            <td class="px-5 py-3 text-gray-500">
                                {{ doc.size ? `${(doc.size / 1024).toFixed(1)} KB` : '-' }}
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ doc.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-else class="px-5 py-16 text-center">
                <span class="material-icons text-4xl text-gray-300 mb-3">folder_open</span>
                <p class="text-sm text-gray-500">No documents found.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="documents?.links?.length > 3" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in documents.links" :key="link.label">
                <component
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url"
                    class="px-3 py-1.5 text-xs rounded-lg border transition-colors no-underline"
                    :class="link.active ? 'bg-primary-600 text-white border-primary-600' : link.url ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' : 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'"
                    v-html="link.label"
                />
            </template>
        </div>
    </ClientPortalLayout>
</template>
