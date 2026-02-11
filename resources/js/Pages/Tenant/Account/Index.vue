<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    accountSections: {
        type: Array,
        required: true,
    },
    account: {
        type: Object,
        default: null,
    },
    timezones: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    logo: null,
    default_timezone: props.account?.timezone || 'America/Chicago',
    brand_color: props.account?.brand_color || '#3B82F6',
});

const logoPreview = ref(props.account?.logo_url || null);
const fileInput = ref(null);

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.logo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeLogo = () => {
    form.logo = null;
    logoPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('account.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Account Management" />

    <TenantLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Account Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your organization's users, roles, and permissions
                    </p>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Account Settings
                    </h3>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Account Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Account Logo
                            </label>

                            <!-- Logo Preview -->
                            <div class="mb-3">
                                <div v-if="logoPreview" class="relative">
                                    <div class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 p-4 flex items-center justify-center">
                                        <img :src="logoPreview" alt="Logo preview" class="max-h-24 w-auto object-contain" />
                                    </div>
                                   <!--  <button
                                        type="button"
                                        @click="removeLogo"
                                        class="absolute -top-2 -right-2 rounded-full bg-red-500 p-1.5 text-white hover:bg-red-600 transition-colors shadow-md"
                                    >
                                        <span class="material-icons text-base">close</span>
                                    </button> -->
                                </div>
                                <div v-else class="w-full h-24 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600">
                                    <span class="material-icons text-gray-400 dark:text-gray-500 text-3xl">
                                        business
                                    </span>
                                </div>
                            </div>

                            <!-- Hidden File Input -->
                            <input
                                ref="fileInput"
                                type="file"
                                @change="handleLogoChange"
                                accept="image/*"
                                class="hidden"
                            />

                            <!-- Upload Button -->
                            <button
                                type="button"
                                @click="fileInput.click()"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-800 transition-colors"
                            >
                                <span class="material-icons text-base">upload</span>
                                <span>{{ logoPreview ? 'Change Logo' : 'Upload Logo' }}</span>
                            </button>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                PNG, JPG, GIF up to 2MB
                            </p>
                            <p v-if="form.errors.logo" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.logo }}
                            </p>
                        </div>

                        <!-- Default Timezone -->
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Default Timezone
                            </label>
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-xl pointer-events-none">
                                    schedule
                                </span>
                                <select
                                    id="timezone"
                                    v-model="form.default_timezone"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                >
                                    <option value="">Select timezone</option>
                                    <option v-for="tz in timezones" :key="tz.id" :value="tz.id">
                                        {{ tz.name }}
                                    </option>
                                </select>
                            </div>
                            <p v-if="form.errors.default_timezone" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.default_timezone }}
                            </p>
                        </div>

                        <!-- Brand Color -->
                        <div>
                            <label for="brand_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Brand Color
                            </label>
                            <div class="flex items-center gap-3">
                                <!-- Circular Color Picker -->
                                <label class="relative block cursor-pointer group">
                                    <input
                                        id="brand_color"
                                        type="color"
                                        v-model="form.brand_color"
                                        class="sr-only"
                                    />
                                    <div
                                        class="h-12 w-12 rounded-full border-2 border-gray-300 dark:border-gray-600 shadow-sm group-hover:scale-110 transition-transform"
                                        :style="{ backgroundColor: form.brand_color }"
                                    ></div>
                                    <div class="absolute inset-0 rounded-full ring-2 ring-transparent group-hover:ring-blue-500 group-hover:ring-offset-2 dark:group-hover:ring-offset-gray-800 transition-all pointer-events-none"></div>
                                </label>

                                <!-- Hex Input -->
                                <input
                                    type="text"
                                    v-model="form.brand_color"
                                    class="flex-1 pl-3 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 font-mono text-sm"
                                    placeholder="#3B82F6"
                                    maxlength="7"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Click the circle to choose a color or enter a hex code
                            </p>
                            <p v-if="form.errors.brand_color" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.brand_color }}
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors dark:focus:ring-offset-gray-800"
                        >
                            <span class="material-icons text-sm">save</span>
                            <span>{{ form.processing ? 'Saving...' : 'Save Changes' }}</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link
                        v-for="section in accountSections"
                        :key="section.title"
                        :href="section.href"
                        class="group block rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Icon -->
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                                <span class="material-icons text-2xl text-gray-600 dark:text-gray-300">
                                    {{ section.icon }}
                                </span>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 overflow-hidden">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ section.title }}
                                    </h3>
                                    <!-- Arrow icon -->
                                    <span class="material-icons flex-shrink-0 text-gray-400 transition-transform group-hover:translate-x-1 dark:text-gray-500">
                                        chevron_right
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ section.description }}
                                </p>

                                <!-- Stats (if available) -->
                                <div v-if="section.stats" class="mt-3 flex items-center gap-1 text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">{{ section.stats.label }}:</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ section.stats.value }}</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
