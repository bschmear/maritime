<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    specGroups: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    key: '',
    label: '',
    group_id: '',
    type: 'text',
    unit: '',
    unit_imperial: '',
    unit_metric: '',
    use_metric: false,
    options: [],
    is_filterable: false,
    is_visible: true,
    is_required: false,
    show_on_table: false,
    position: 0,
    asset_types: [],
});

const showUnitFields = ref(false);
const showOptionsFields = ref(false);

const typeOptions = [
    { value: 'text', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'select', label: 'Select/Dropdown' },
    { value: 'boolean', label: 'Yes/No (Boolean)' },
];

/** AssetType enum: Boat=1, Engine=2, Trailer=3, Other=4 */
const assetTypeOptions = [
    { value: 1, label: 'Boat' },
    { value: 3, label: 'Trailer' },
    { value: 2, label: 'Engine' },
    { value: 4, label: 'Other' },
];

const addOption = () => {
    form.options.push({ value: '', label: '' });
};

const removeOption = (index) => {
    form.options.splice(index, 1);
};

const handleTypeChange = () => {
    showOptionsFields.value = form.type === 'select';
    if (showOptionsFields.value && form.options.length === 0) {
        addOption();
    }
};

const generateKey = () => {
    if (form.label) {
        form.key = form.label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }
};

const submit = () => {
    form.post(route('asset-specs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
};
</script>

<template>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div
            @click="emit('close')"
            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"
        ></div>

        <!-- Modal -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                <!-- Header -->
                <div class="border-b border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Create New Spec
                    </h3>
                    <button
                        @click="emit('close')"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Label -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.label"
                            @blur="generateKey"
                            type="text"
                            placeholder="e.g., Overall Length"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                            required
                        />
                        <p v-if="form.errors.label" class="mt-1 text-sm text-red-600">{{ form.errors.label }}</p>
                    </div>

                    <!-- Key -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Key <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.key"
                            type="text"
                            placeholder="e.g., overall_length"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400 font-mono text-sm"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Unique identifier (auto-generated from label)
                        </p>
                        <p v-if="form.errors.key" class="mt-1 text-sm text-red-600">{{ form.errors.key }}</p>
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.type"
                            @change="handleTypeChange"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                            required
                        >
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                    </div>

                    <!-- Group -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Group
                        </label>
                        <select
                            v-model="form.group_id"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                        >
                            <option value="">None</option>
                            <option v-for="g in specGroups" :key="g.id" :value="g.id">
                                {{ g.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Optional section for organizing specs
                        </p>
                    </div>

                    <!-- Unit (for number type) -->
                    <div v-if="form.type === 'number'">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Unit
                            </label>
                            <button
                                type="button"
                                @click="showUnitFields = !showUnitFields"
                                class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                {{ showUnitFields ? 'Hide' : 'Show' }} imperial/metric
                            </button>
                        </div>
                        <input
                            v-model="form.unit"
                            type="text"
                            placeholder="e.g., ft, lb, hp"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                        />

                        <!-- Imperial/Metric Units -->
                        <div v-if="showUnitFields" class="mt-3 space-y-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Imperial Unit
                                </label>
                                <input
                                    v-model="form.unit_imperial"
                                    type="text"
                                    placeholder="e.g., ft, lb"
                                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Metric Unit
                                </label>
                                <input
                                    v-model="form.unit_metric"
                                    type="text"
                                    placeholder="e.g., m, kg"
                                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Options (for select type) -->
                    <div v-if="showOptionsFields">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Options <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <div v-for="(option, index) in form.options" :key="index" class="flex gap-2">
                                <input
                                    v-model="option.value"
                                    type="text"
                                    placeholder="Value (e.g., S)"
                                    class="block w-1/3 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    required
                                />
                                <input
                                    v-model="option.label"
                                    type="text"
                                    placeholder="Label (e.g., Short)"
                                    class="block flex-1 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500 dark:focus:ring-primary-400"
                                    required
                                />
                                <button
                                    type="button"
                                    @click="removeOption(index)"
                                    class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                >
                                    <span class="material-icons text-[20px]">delete</span>
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addOption"
                            class="mt-2 inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
                        >
                            <span class="material-icons text-[16px]">add</span>
                            Add option
                        </button>
                    </div>

                    <!-- Asset Types -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Asset Types
                        </label>
                        <div class="space-y-2">
                            <label
                                v-for="option in assetTypeOptions"
                                :key="option.value"
                                class="flex items-center"
                            >
                                <input
                                    v-model="form.asset_types"
                                    :value="option.value"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    {{ option.label }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Toggles -->
                    <div class="space-y-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <label class="flex items-center">
                            <input
                                v-model="form.is_required"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Required field
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.is_filterable"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Marketplace filterable
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.is_visible"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Always visible in UI
                            </span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.show_on_table"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-400"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Show on asset Variants table (when this spec applies to selected asset types)
                            </span>
                        </label>
                    </div>
                </form>

                <!-- Footer -->
                <div class="border-t border-gray-100 dark:border-gray-700 px-6 py-4 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Spec' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>