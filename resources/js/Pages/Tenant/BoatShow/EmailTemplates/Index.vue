<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    template: { type: Object, required: true },
    availableVariables: { type: Object, default: () => ({}) },
});

const selectedAssetListMergeTag = '{{ selected_asset_list }}';

const form = useForm({
    email_subject: props.template.email_subject,
    email_message: props.template.email_message,
    is_active: !!props.template.is_active,
});

const testForm = useForm({
    email: '',
    subject: '',
    message: '',
});

const showTestForm = ref(false);
const editorComponent = ref(null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'Boat Shows', href: route('boat-shows.index') },
    { label: 'Follow-up emails', href: null },
]);

const saveTemplate = () => {
    form.put(route('boat-show-email-templates.update', props.template.id), {
        preserveScroll: true,
    });
};

const openTest = () => {
    testForm.email = '';
    testForm.subject = form.email_subject;
    testForm.message = form.email_message;
    testForm.clearErrors();
    showTestForm.value = true;
};

const cancelTestEmail = () => {
    showTestForm.value = false;
    testForm.reset();
};

const sendTestEmail = () => {
    testForm.subject = form.email_subject;
    testForm.message = form.email_message;
    testForm.post(route('boat-show-email-templates.send-test'), {
        preserveScroll: true,
        onSuccess: () => {
            showTestForm.value = false;
            testForm.reset();
        },
    });
};

const insertVariable = (variable) => {
    try {
        if (!editorComponent.value?.editor) return;
        const ed = editorComponent.value.editor;
        const { from } = ed.state.selection;
        ed.chain().focus().insertContentAt(from, ` ${variable} `).run();
    } catch (e) {
        console.error(e);
    }
};
</script>

<template>
    <Head title="Boat show follow-up emails" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Follow-up email template</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            One shared template for delayed emails after boat show leads. Use
                            <code class="rounded bg-gray-100 px-1 text-xs dark:bg-gray-800">{{ selectedAssetListMergeTag }}</code>
                            for the inventory the visitor selected. Timing and recipients are set per event on the event edit page.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="openTest"
                    >
                        <span class="material-icons text-[18px]">mail_outline</span>
                        Send test
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto w-full max-w-7xl space-y-6 p-4">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                {{ $page.props.flash.error }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <div class="space-y-6 lg:col-span-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Message</h2>
                        <form class="space-y-6" @submit.prevent="saveTemplate">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                                <input
                                    v-model="form.email_subject"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="form.errors.email_subject" class="mt-1 text-sm text-red-600">{{ form.errors.email_subject }}</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                                Active
                            </label>
                            <div>
                                <TipTapEditor
                                    ref="editorComponent"
                                    v-model="form.email_message"
                                    label="Message (HTML)"
                                    id="email_message"
                                    :error="form.errors.email_message"
                                />
                            </div>
                            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <button type="submit" class="btn btn-primary sm" :disabled="form.processing">
                                    {{ form.processing ? 'Saving…' : 'Save' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="lg:col-span-1">
                    <div class="sticky top-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Variables</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Click to insert at cursor</p>
                        <div class="mt-3 max-h-[60vh] space-y-2 overflow-y-auto">
                            <button
                                v-for="(label, variable) in availableVariables"
                                :key="variable"
                                type="button"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs hover:border-primary-300 dark:border-gray-600 dark:bg-gray-900"
                                @click="insertVariable(variable)"
                            >
                                <div class="break-all font-mono text-primary-600 dark:text-primary-400">{{ variable }}</div>
                                <div class="mt-0.5 text-gray-600 dark:text-gray-400">{{ label }}</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showTestForm" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Send test email</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Uses the subject and message currently in the form (including unsaved edits).</p>
                <form class="mt-4 space-y-4" @submit.prevent="sendTestEmail">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient</label>
                        <input
                            v-model="testForm.email"
                            type="email"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="testForm.errors.email" class="mt-1 text-sm text-red-600">{{ testForm.errors.email }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-outline sm" @click="cancelTestEmail">Cancel</button>
                        <button type="submit" class="btn btn-primary sm" :disabled="testForm.processing">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
