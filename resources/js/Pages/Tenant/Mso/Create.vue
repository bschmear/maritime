<script setup>
import MsoBuilderToolbar from '@/Components/Tenant/Mso/MsoBuilderToolbar.vue';
import MsoFieldPalette from '@/Components/Tenant/Mso/MsoFieldPalette.vue';
import MsoPdfCanvas from '@/Components/Tenant/Mso/MsoPdfCanvas.vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import Breadcrumb from '@/Components/Tenant/Breadcrumb.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    transaction: { type: Object, required: true },
    lineItem: { type: Object, required: true },
    assetUnit: { type: Object, required: true },
    sourceDocument: { type: Object, default: null },
    msoRecord: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    fieldTypes: { type: Array, default: () => [] },
    hasSavedLayout: { type: Boolean, default: false },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const sourceDocument = ref(props.sourceDocument);
const fields = ref([...(props.msoRecord.fields || [])]);
const assignedUserId = ref(props.msoRecord.assigned_user_id ?? null);
const selectedFieldId = ref(null);
const saveLayout = ref(false);
const saving = ref(false);
const generating = ref(false);
const submitting = ref(false);
const uploading = ref(false);
const uploadInput = ref(null);

const pdfCanvasRef = ref(null);

const breadcrumbItems = computed(() => [
    { label: 'Home', href: route('dashboard') },
    { label: 'MSO', href: route('mso.index') },
    { label: `MSO Builder — ${props.transaction.display_name}` },
]);

const fieldTypeLabels = computed(() =>
    Object.fromEntries(props.fieldTypes.map((t) => [t.value, t.label])),
);

const selectedField = computed(() =>
    fields.value.find((field) => field.id === selectedFieldId.value) ?? null,
);

const canSubmit = computed(() => Boolean(sourceDocument.value?.preview_url));

function defaultValueForType(type) {
    const prefill = props.msoRecord.prefill || {};
    return prefill[type] ?? '';
}

function addField(type, placement = null) {
    const id = crypto.randomUUID();
    fields.value.push({
        id,
        type,
        page: placement?.page ?? 1,
        x: placement?.x ?? 0.1,
        y: placement?.y ?? 0.1,
        width: placement?.width ?? 0.25,
        height: placement?.height ?? 0.04,
        value: defaultValueForType(type),
        font_size: 10,
    });
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
        if (target.closest('[data-mso-sidebar-field]')) {
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
    const user = props.users.find((u) => u.id === assignedUserId.value);
    if (user) {
        prefill.user_name = user.display_name;
    }

    fields.value = fields.value.map((field) => {
        if (field.type === 'free_text') {
            return field;
        }
        return {
            ...field,
            value: field.value || prefill[field.type] || defaultValueForType(field.type),
        };
    });
}

watch(assignedUserId, () => refreshPrefillValues());

function builderPayload() {
    return {
        assigned_user_id: assignedUserId.value,
        fields: fields.value,
        save_layout: saveLayout.value,
    };
}

async function saveDraft() {
    saving.value = true;
    try {
        await axios.put(route('mso.records.builder', props.msoRecord.id), builderPayload());
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

        <div v-if="flash.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ flash.error }}
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="xl:col-span-8 space-y-4">
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
                />
            </div>

            <div class="xl:col-span-4 space-y-4">
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <MsoFieldPalette :field-types="fieldTypes" @add="addField" />
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

                    <div
                        v-if="selectedField"
                        data-mso-sidebar-field
                        class="space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Selected field</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                @click="removeField(selectedField.id)"
                            >
                                Delete
                            </button>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ fieldTypeLabels[selectedField.type] || selectedField.type }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Drag the left edge to move, the right edge or corner to resize.
                        </p>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Value</label>
                        <textarea
                            v-model="selectedField.value"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Font size</label>
                        <input
                            v-model.number="selectedField.font_size"
                            type="number"
                            min="6"
                            max="24"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="saveLayout" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span>Save layout for this document</span>
                    </label>
                    <p v-if="hasSavedLayout" class="text-xs text-gray-500 dark:text-gray-400">
                        A saved layout exists for this PDF and was applied when no fields were present yet.
                    </p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Deal reference</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-gray-500">Customer</dt><dd class="font-medium text-gray-900 dark:text-white">{{ transaction.customer_name || '—' }}</dd></div>
                        <div><dt class="text-gray-500">Phone</dt><dd>{{ transaction.customer_phone || '—' }}</dd></div>
                        <div><dt class="text-gray-500">Address</dt><dd class="whitespace-pre-line">{{ transaction.customer_address || '—' }}</dd></div>
                        <div><dt class="text-gray-500">Dealership</dt><dd>{{ transaction.subsidiary_name || '—' }}</dd></div>
                        <div><dt class="text-gray-500">Line item</dt><dd>{{ lineItem.name || '—' }}</dd></div>
                    </dl>
                </section>

                <MsoBuilderToolbar
                    :saving="saving"
                    :generating="generating"
                    :submitting="submitting"
                    :can-submit="canSubmit"
                    @save="saveDraft"
                    @generate="generatePdf"
                    @submit="submitMso"
                />
            </div>
        </div>
    </TenantLayout>
</template>
