<script setup>
import MsoBuilderToolbar from '@/Components/Tenant/Mso/MsoBuilderToolbar.vue';
import MsoFieldInspector from '@/Components/Tenant/Mso/MsoFieldInspector.vue';
import MsoFieldPalette from '@/Components/Tenant/Mso/MsoFieldPalette.vue';
import MsoPdfCanvas from '@/Components/Tenant/Mso/MsoPdfCanvas.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { formatMsoCustomerAddress, msoSignatureFieldPatch } from '@/Utils/msoAddressFormat';
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    transaction: { type: Object, required: true },
    lineItem: { type: Object, required: true },
    assetUnit: { type: Object, required: true },
    sourceDocument: { type: Object, default: null },
    msoRecord: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    fieldGroups: { type: Array, default: () => [] },
    layoutTemplates: { type: Array, default: () => [] },
    showTemplatePicker: { type: Boolean, default: false },
    appliedTemplate: { type: Object, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const sourceDocument = ref(props.sourceDocument);
const fields = ref([...(props.msoRecord.fields || [])]);
const assignedUserId = ref(props.msoRecord.assigned_user_id ?? null);
const selectedFieldId = ref(null);
const layoutTemplateName = ref('');
const activeTemplateId = ref(props.msoRecord.layout_template_id ?? props.appliedTemplate?.id ?? null);
const activeTemplateName = ref(props.appliedTemplate?.name ?? '');
const templatePickerDismissed = ref(false);
const selectedTemplateId = ref(props.layoutTemplates[0]?.id ?? null);
const saving = ref(false);
const savingTemplate = ref(false);
const generating = ref(false);
const submitting = ref(false);
const deleting = ref(false);
const uploading = ref(false);
const uploadInput = ref(null);
const pageSizes = ref({ ...(props.msoRecord.page_sizes || {}) });

const pdfCanvasRef = ref(null);
const appInstance = getCurrentInstance();

function toast(type, message) {
    appInstance?.appContext.config.globalProperties.$toast?.(type, message);
}

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'MSO', href: route('mso.index') },
    { label: `MSO Builder — ${props.transaction.display_name}` },
]);

const fieldTypeLabels = computed(() => {
    const labels = {};
    for (const group of props.fieldGroups) {
        for (const field of group.fields ?? []) {
            labels[field.value] = field.label;
        }
    }

    return labels;
});

const selectedField = computed(() =>
    fields.value.find((field) => field.id === selectedFieldId.value) ?? null,
);

const canSubmit = computed(() => Boolean(sourceDocument.value?.preview_url));

const canDelete = computed(() => props.msoRecord.status === 'draft');

const showTemplateModal = computed(
    () => props.showTemplatePicker && !templatePickerDismissed.value,
);

function defaultValueForType(type) {
    const prefill = props.msoRecord.prefill || {};
    return prefill[type] ?? '';
}

const fieldDefaultsByType = {
    user_signature: { width: 0.28, height: 0.08 },
    customer_address: { width: 0.34, height: 0.1, address_layout: 'multiline' },
    dealership_address: { width: 0.34, height: 0.1, address_layout: 'multiline' },
};

function assignedUser() {
    return props.users.find((user) => user.id === assignedUserId.value) ?? null;
}

function addressPartsForType(type) {
    const prefill = props.msoRecord.prefill || {};
    if (type === 'dealership_address') {
        return prefill.dealership_address_parts ?? {};
    }

    return prefill.customer_address_parts ?? {};
}

function addField(type, placement = null) {
    const id = crypto.randomUUID();
    const typeDefaults = fieldDefaultsByType[type] ?? {};
    const layout = typeDefaults.address_layout ?? 'multiline';
    const field = {
        id,
        type,
        page: placement?.page ?? 1,
        x: placement?.x ?? 0.1,
        y: placement?.y ?? 0.1,
        width: placement?.width ?? typeDefaults.width ?? 0.25,
        height: placement?.height ?? typeDefaults.height ?? 0.04,
        value: defaultValueForType(type),
        font_size: 10,
        font_bold: false,
    };

    if (type === 'customer_address' || type === 'dealership_address') {
        field.address_layout = layout;
        field.value = formatMsoCustomerAddress(addressPartsForType(type), layout);
    }

    if (type === 'user_signature') {
        Object.assign(field, msoSignatureFieldPatch(assignedUser()));
        if (!field.value) {
            field.value = defaultValueForType(type);
        }
    }

    if (type === 'user_position_title') {
        const user = assignedUser();
        field.value = user?.position_title || defaultValueForType(type);
    }

    fields.value.push(field);
    selectedFieldId.value = id;
}

function updateField(fieldId, patch) {
    const index = fields.value.findIndex((field) => field.id === fieldId);
    if (index === -1) {
        return;
    }
    fields.value[index] = { ...fields.value[index], ...patch };
}

function removeField(fieldId) {
    if (!fieldId) {
        return;
    }

    fields.value = fields.value.filter((field) => field.id !== fieldId);
    if (selectedFieldId.value === fieldId) {
        selectedFieldId.value = null;
    }
}

function onBuilderKeydown(event) {
    if (!selectedFieldId.value) {
        return;
    }

    if (event.key !== 'Delete' && event.key !== 'Backspace') {
        return;
    }

    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        if (target.classList.contains('field-input')) {
            return;
        }
        if (target.closest('[data-mso-field-inspector]')) {
            return;
        }
    }

    event.preventDefault();
    removeField(selectedFieldId.value);
}

onMounted(() => {
    window.addEventListener('keydown', onBuilderKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onBuilderKeydown);
});

function refreshPrefillValues() {
    const prefill = { ...(props.msoRecord.prefill || {}) };
    const user = assignedUser();
    if (user) {
        prefill.user_name = user.display_name;
        prefill.user_position_title = user.position_title ?? '';
    }

    fields.value = fields.value.map((field) => {
        if (field.type === 'free_text') {
            return field;
        }

        if (field.type === 'user_signature') {
            return {
                ...field,
                ...msoSignatureFieldPatch(user),
            };
        }

        if (field.type === 'user_position_title') {
            return {
                ...field,
                value: user?.position_title || prefill.user_position_title || '',
            };
        }

        return {
            ...field,
            value: field.value || prefill[field.type] || defaultValueForType(field.type),
        };
    });
}

function setAddressLayout(layout) {
    const field = selectedField.value;
    if (!field || !['customer_address', 'dealership_address'].includes(field.type)) {
        return;
    }

    updateField(field.id, {
        address_layout: layout,
        value: formatMsoCustomerAddress(addressPartsForType(field.type), layout),
    });
}

watch(assignedUserId, () => refreshPrefillValues());

function builderPayload(extra = {}) {
    return {
        assigned_user_id: assignedUserId.value,
        fields: fields.value,
        page_sizes: pageSizes.value,
        ...extra,
    };
}

function onPageSizes(sizes) {
    pageSizes.value = {
        ...pageSizes.value,
        ...sizes,
    };
}

function startBlank() {
    templatePickerDismissed.value = true;
}

function startWithTemplate() {
    if (!selectedTemplateId.value) {
        return;
    }

    router.visit(
        route('mso.create', {
            transaction_id: props.transaction.id,
            line_item_id: props.lineItem.id,
            layout_template_id: selectedTemplateId.value,
        }),
    );
}

function syncActiveTemplateFromProps() {
    const templateId = props.msoRecord.layout_template_id ?? props.appliedTemplate?.id ?? null;
    activeTemplateId.value = templateId;
    activeTemplateName.value = props.appliedTemplate?.name
        ?? props.layoutTemplates.find((template) => template.id === templateId)?.name
        ?? '';
    if (templateId) {
        layoutTemplateName.value = '';
    }
}

watch(
    () => [props.msoRecord.layout_template_id, props.appliedTemplate],
    () => syncActiveTemplateFromProps(),
    { immediate: true, deep: true },
);

async function updateCurrentTemplate() {
    if (!activeTemplateId.value) {
        return;
    }

    savingTemplate.value = true;
    try {
        await axios.put(
            route('mso.records.builder', props.msoRecord.id),
            builderPayload({ layout_template_id: activeTemplateId.value }),
        );
        toast('success', `Template "${activeTemplateName.value}" updated.`);
    } catch {
        toast('error', 'Unable to update template.');
    } finally {
        savingTemplate.value = false;
    }
}

async function saveLayoutTemplate() {
    const name = layoutTemplateName.value.trim();
    if (!name) {
        return;
    }

    savingTemplate.value = true;
    try {
        const { data } = await axios.put(
            route('mso.records.builder', props.msoRecord.id),
            builderPayload({ layout_template_name: name }),
        );
        if (data?.layoutTemplate) {
            activeTemplateId.value = data.layoutTemplate.id;
            activeTemplateName.value = data.layoutTemplate.name;
            layoutTemplateName.value = '';
        } else if (data?.msoRecord?.layout_template_id) {
            activeTemplateId.value = data.msoRecord.layout_template_id;
        }
        toast('success', `Template "${name}" saved.`);
    } catch {
        toast('error', 'Unable to save template.');
    } finally {
        savingTemplate.value = false;
    }
}

async function saveDraft() {
    saving.value = true;
    try {
        await axios.put(route('mso.records.builder', props.msoRecord.id), builderPayload());
        toast('success', 'Draft saved.');
    } catch {
        toast('error', 'Unable to save draft.');
    } finally {
        saving.value = false;
    }
}

async function generatePdf() {
    generating.value = true;
    try {
        const { data } = await axios.post(route('mso.records.generate-pdf', props.msoRecord.id), builderPayload());
        if (data?.outputDocument?.download_url) {
            window.open(data.outputDocument.download_url, '_blank');
        }
    } finally {
        generating.value = false;
    }
}

function submitMso() {
    submitting.value = true;
    router.post(route('mso.records.submit', props.msoRecord.id), builderPayload(), {
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function deleteMso() {
    if (!canDelete.value) {
        return;
    }

    if (!confirm('Delete this MSO draft? This cannot be undone.')) {
        return;
    }

    deleting.value = true;
    router.delete(route('mso.records.destroy', props.msoRecord.id), {
        onFinish: () => {
            deleting.value = false;
        },
    });
}

async function onUploadSelected(event) {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    uploading.value = true;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('display_name', file.name);

    try {
        const { data } = await axios.post(
            route('mso.records.source-document', props.msoRecord.id),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        if (data?.sourceDocument) {
            sourceDocument.value = data.sourceDocument;
        }
    } finally {
        uploading.value = false;
        if (uploadInput.value) {
            uploadInput.value.value = '';
        }
    }
}
</script>

<template>
    <Head :title="`MSO Builder — ${transaction.display_name}`" />

    <TenantLayout>
        <template #header>
            <div class="col-span-full">
                <Breadcrumb :items="breadcrumbItems" />
                <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">MSO Builder</h2>
                        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                            Place customer, deal, dealership, and signature fields on the original MSO PDF.
                        </p>
                    </div>
                    <Link
                        :href="route('mso.index')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    >
                        Back to MSO
                    </Link>
                </div>
            </div>
        </template>

        <div class="flex min-h-[55vh] items-center justify-center px-6 py-12 min-[1280px]:hidden">
            <div class="max-w-md rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <span class="material-icons mb-4 block text-5xl text-gray-400 dark:text-gray-500">desktop_windows</span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Large screen required</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Please view on a large screen to use the MSO builder.
                </p>
                <Link
                    :href="route('mso.index')"
                    class="mt-6 inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Back to MSO
                </Link>
            </div>
        </div>

        <div class="hidden min-[1280px]:block">
            <div v-if="flash.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
                {{ flash.error }}
            </div>

            <div class="grid gap-6 min-[1280px]:grid-cols-[minmax(0,1fr)_minmax(320px,400px)]">
            <div class="min-w-0 space-y-4">
                <div v-if="!sourceDocument" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Upload original MSO</h3>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                        Attach the manufacturer PDF for {{ assetUnit.display_name }}.
                    </p>
                    <div class="mt-3">
                        <input ref="uploadInput" type="file" accept="application/pdf,.pdf" class="hidden" @change="onUploadSelected" />
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                            :disabled="uploading"
                            @click="uploadInput?.click()"
                        >
                            {{ uploading ? 'Uploading…' : 'Upload PDF' }}
                        </button>
                    </div>
                </div>

                <div v-else class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Source: <span class="font-medium text-gray-900 dark:text-white">{{ sourceDocument.display_name }}</span>
                    </p>
                    <div>
                        <input ref="uploadInput" type="file" accept="application/pdf,.pdf" class="hidden" @change="onUploadSelected" />
                        <button
                            type="button"
                            class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                            @click="uploadInput?.click()"
                        >
                            Replace PDF
                        </button>
                    </div>
                </div>

                <MsoFieldInspector
                    :selected-field="selectedField"
                    :field-type-labels="fieldTypeLabels"
                    @set-address-layout="setAddressLayout"
                />

                <MsoPdfCanvas
                    ref="pdfCanvasRef"
                    :preview-url="sourceDocument?.preview_url"
                    :fields="fields"
                    :selected-field-id="selectedFieldId"
                    :field-type-labels="fieldTypeLabels"
                    @add-field="addField($event.type, $event)"
                    @select-field="selectedFieldId = $event"
                    @update-field="updateField"
                    @delete-field="removeField"
                    @page-sizes="onPageSizes"
                />
            </div>

            <div class="space-y-4">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <MsoFieldPalette :field-groups="fieldGroups" @add="addField" />
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assigned user</label>
                        <select
                            v-model="assignedUserId"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        >
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.display_name }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            MSO layout template
                        </p>

                        <template v-if="activeTemplateId">
                            <p class="text-xs text-primary-700 dark:text-primary-300">
                                Using template: <strong>{{ activeTemplateName }}</strong>
                            </p>
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-lg border border-primary-600 bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50 dark:border-primary-500 dark:bg-primary-500 dark:hover:bg-primary-600"
                                :disabled="savingTemplate"
                                @click="updateCurrentTemplate"
                            >
                                <svg
                                    v-if="savingTemplate"
                                    class="mr-2 h-4 w-4 animate-spin"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ savingTemplate ? 'Updating template…' : `Update "${activeTemplateName}"` }}
                            </button>

                            <div class="space-y-2 border-t border-gray-200 pt-3 dark:border-gray-700">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">Save as new template</p>
                                <input
                                    v-model="layoutTemplateName"
                                    type="text"
                                    maxlength="255"
                                    placeholder="New template name"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                />
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                    :disabled="!layoutTemplateName.trim() || savingTemplate"
                                    @click="saveLayoutTemplate"
                                >
                                    Save as new
                                </button>
                            </div>
                        </template>

                        <template v-else>
                            <input
                                v-model="layoutTemplateName"
                                type="text"
                                maxlength="255"
                                placeholder="Template name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                            />
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                :disabled="!layoutTemplateName.trim() || savingTemplate"
                                @click="saveLayoutTemplate"
                            >
                                <svg
                                    v-if="savingTemplate"
                                    class="mr-2 h-4 w-4 animate-spin"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ savingTemplate ? 'Saving template…' : 'Save template' }}
                            </button>
                        </template>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Saves field positions for reuse on future MSOs.
                        </p>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Deal reference</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-gray-500 dark:text-gray-400">Customer</dt><dd class="font-medium text-gray-900 dark:text-white">{{ transaction.customer_name || '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="text-gray-900 dark:text-white">{{ transaction.customer_phone || '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="whitespace-pre-line text-gray-900 dark:text-white">{{ transaction.customer_address || '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Dealership</dt><dd class="text-gray-900 dark:text-white">{{ transaction.subsidiary_name || '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Line item</dt><dd class="text-gray-900 dark:text-white">{{ lineItem.name || '—' }}</dd></div>
                    </dl>
                </section>

            </div>
            </div>
        </div>

        <MsoBuilderToolbar
            :saving="saving"
            :generating="generating"
            :submitting="submitting"
            :deleting="deleting"
            :can-submit="canSubmit"
            :can-delete="canDelete"
            @save="saveDraft"
            @generate="generatePdf"
            @submit="submitMso"
            @delete="deleteMso"
        />

        <Teleport to="body">
            <div
                v-if="showTemplateModal"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="mso-template-picker-title"
            >
                <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                    <h3 id="mso-template-picker-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                        Start MSO builder
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Would you like to start from a saved layout template or place fields manually?
                    </p>

                    <div v-if="layoutTemplates.length" class="mt-4 space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="mso-template-select">
                            Template
                        </label>
                        <select
                            id="mso-template-select"
                            v-model="selectedTemplateId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        >
                            <option v-for="template in layoutTemplates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="startBlank"
                        >
                            Start blank
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            :disabled="!selectedTemplateId"
                            @click="startWithTemplate"
                        >
                            Use template
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </TenantLayout>
</template>
