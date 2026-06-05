<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

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
    users: {
        type: Array,
        default: () => [],
    },
    paymentTermOptions: {
        type: Array,
        default: () => [],
    },
    show_account_intro_modal: {
        type: Boolean,
        default: false,
    },
    allow_support_access: {
        type: Boolean,
        default: false,
    },
    app_name: {
        type: String,
        default: 'Support',
    },
});

const defaultContractTermsFallback = 'This agreement outlines the terms and conditions of the sale, including product details, payment obligations, and delivery expectations.';
const defaultPaymentTermsFallback = 'Payment is due as specified in the contract. Please remit promptly.';
const defaultDeliveryTermsFallback = 'Delivery will be scheduled according to contract terms. Customer will be notified in advance.';

function timeInputValue(value) {
    if (!value) return '08:00';
    const s = String(value);
    return s.length >= 5 ? s.slice(0, 5) : '08:00';
}

/** Which account settings form tab is visible (single form, all fields still submit together). */
const settingsTab = ref('general');
const settingsTabs = [
    { id: 'general', label: 'General account', title: 'General Account Settings', icon: 'settings' },
    { id: 'scheduling', label: 'Scheduling', title: 'Scheduling board defaults', icon: 'calendar_view_week' },
    { id: 'service_ticket', label: 'Service tickets', title: 'Service Ticket & Work Order Settings', icon: 'assignment' },
    { id: 'transactions', label: 'Transactions', title: 'Default transaction settings', icon: 'receipt_long' },
];

const form = useForm({
    logo: null,
    default_timezone: props.account?.timezone || 'America/Chicago',
    brand_color: props.account?.brand_color || '#3B82F6',
    estimate_threshold_percent: parseInt(props.account?.estimate_threshold_percent, 10) || 20,
    service_ticket_ack_text: props.account?.service_ticket_ack_text || 'I acknowledge that all charges for services, labor, parts, materials, and applicable fees are due upon release of the property. I understand that I will be notified when work is complete. Storage fees may apply if the property is not picked up within 14 days from notification. Unclaimed property after 60 days may be subject to sale or disposal as permitted by law. I have reviewed and approved the estimate or scope of work and accept the associated charges.',
    service_ticket_signed_notify_user_id: props.account?.service_ticket_signed_notify_user_id || (props.users?.length > 0 ? props.users[0].id : null),
    default_contract_terms: props.account?.default_contract_terms ?? defaultContractTermsFallback,
    default_payment_term: props.account?.default_payment_term ?? 'due_on_receipt',
    default_payment_terms: props.account?.default_payment_terms ?? defaultPaymentTermsFallback,
    default_delivery_terms: props.account?.default_delivery_terms ?? defaultDeliveryTermsFallback,
    workday_hours: parseInt(props.account?.workday_hours, 10) || 6,
    start_time: timeInputValue(props.account?.start_time),
    allow_overlap: !!props.account?.allow_overlap,
    sandbox_mode: !!props.account?.sandbox_mode,
    allow_support_access: !!props.allow_support_access,
});

const logoPreview = ref(props.account?.logo_url || null);
const fileInput = ref(null);
const characterCount = ref(form.service_ticket_ack_text.length);

const showAccountIntro = ref(!!props.show_account_intro_modal);

watch(
    () => props.show_account_intro_modal,
    (v) => {
        showAccountIntro.value = !!v;
    }
);

function dismissAccountIntro() {
    router.post(
        route('account.overview.dismiss'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showAccountIntro.value = false;
            },
        }
    );
}

// Computed property for selected notification user
const selectedNotificationUser = computed(() => {
    if (!form.service_ticket_signed_notify_user_id) return null;
    return props.users.find(user => user.id === form.service_ticket_signed_notify_user_id);
});

// Check if the first user is selected (default behavior)
const isFirstUserSelected = computed(() => {
    return props.users.length > 0 && form.service_ticket_signed_notify_user_id === props.users[0].id;
});

const selectedPaymentTermDescription = computed(() => {
    const opt = props.paymentTermOptions.find(
        (o) => o.value === form.default_payment_term || String(o.id) === String(form.default_payment_term),
    );
    return opt?.description ?? '';
});

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

const updateCharacterCount = () => {
    characterCount.value = form.service_ticket_ack_text.length;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        estimate_threshold_percent: parseInt(data.estimate_threshold_percent, 10),
        workday_hours: parseInt(data.workday_hours, 10),
    })).post(route('account.update'), {
        preserveScroll: true,
    });
};

/** After validation errors, show the tab that contains the first failing field. */
watch(
    () => form.errors,
    (errs) => {
        const keys = Object.keys(errs).filter((k) => errs[k]);
        if (keys.length === 0) {
            return;
        }
        const generalKeys = ['logo', 'default_timezone', 'brand_color', 'sandbox_mode', 'allow_support_access'];
        const schedulingKeys = ['workday_hours', 'start_time', 'allow_overlap'];
        const serviceKeys = [
            'estimate_threshold_percent',
            'service_ticket_ack_text',
            'service_ticket_signed_notify_user_id',
        ];
        const transactionKeys = [
            'default_payment_term',
            'default_contract_terms',
            'default_payment_terms',
            'default_delivery_terms',
        ];
        if (keys.some((k) => generalKeys.includes(k))) {
            settingsTab.value = 'general';
        } else if (keys.some((k) => schedulingKeys.includes(k))) {
            settingsTab.value = 'scheduling';
        } else if (keys.some((k) => serviceKeys.includes(k))) {
            settingsTab.value = 'service_ticket';
        } else if (keys.some((k) => transactionKeys.includes(k))) {
            settingsTab.value = 'transactions';
        }
    },
    { deep: true },
);
</script>

<template>
    <Head title="Account Management" />

    <TenantLayout>
        <!-- One-time intro after onboarding -->
        <div
            v-if="showAccountIntro"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="account-intro-title"
        >
            <div class="max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <h2 id="account-intro-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                    Account hub
                </h2>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    This is where you configure general workspace settings, manage users and roles, subsidiaries, locations,
                    payments (including Stripe), consignment policies, and text notifications. Use the cards below to jump into
                    each area.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="dismissAccountIntro"
                    >
                        Got it
                    </button>
                </div>
            </div>
        </div>

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
            <!-- Main Content -->
            <div class="lg:col-span-4">
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

            <!-- Settings Form -->
            <div class="lg:col-span-4">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form @submit.prevent="submit">
                        <div class="border-b border-gray-200 dark:border-gray-700">
                            <nav
                                class="-mb-px flex gap-0.5 overflow-x-auto px-4 sm:px-6"
                                aria-label="Account settings"
                                role="tablist"
                            >
                                <button
                                    v-for="tab in settingsTabs"
                                    :key="tab.id"
                                    type="button"
                                    role="tab"
                                    :title="tab.title"
                                    :aria-selected="settingsTab === tab.id"
                                    class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium transition-colors sm:px-4"
                                    :class="settingsTab === tab.id
                                        ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
                                    @click="settingsTab = tab.id"
                                >
                                    <span class="material-icons text-lg leading-none sm:text-xl" aria-hidden="true">{{ tab.icon }}</span>
                                    {{ tab.label }}
                                </button>
                            </nav>
                        </div>

                        <div
                            v-show="settingsTab === 'general'"
                            role="tabpanel"
                            class="p-6"
                        >
                            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                                Branding, timezone, and sandbox testing.
                            </p>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
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
                                        <!--     <button
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
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Default timezone for all users
                                    </p>
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
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Click circle or enter hex code
                                    </p>
                                    <p v-if="form.errors.brand_color" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.brand_color }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50/90 p-4 dark:border-amber-800/60 dark:bg-amber-950/25">
                                <label class="flex cursor-pointer items-start gap-3 select-none">
                                    <input
                                        v-model="form.sandbox_mode"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-600 dark:bg-gray-800"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-amber-950 dark:text-amber-100">Sandbox mode</span>
                                        <span class="mt-1 block text-sm text-amber-900/90 dark:text-amber-200/90">
                                            While sandbox mode is on, customer emails and text notifications go to you (the signed-in user) instead of real customers, so you can test safely. Turn this off before going live.
                                        </span>
                                    </span>
                                </label>
                                <p v-if="form.errors.sandbox_mode" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                    {{ form.errors.sandbox_mode }}
                                </p>
                            </div>

                            <div class="mt-6 rounded-lg border border-sky-200 bg-sky-50/90 p-4 dark:border-sky-800/60 dark:bg-sky-950/25">
                                <label class="flex cursor-pointer items-start gap-3 select-none">
                                    <input
                                        v-model="form.allow_support_access"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300 text-sky-600 focus:ring-sky-500 dark:border-sky-600 dark:bg-gray-800"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-sky-950 dark:text-sky-100">
                                            Allow {{ app_name }} support to access this workspace
                                        </span>
                                        <span class="mt-1 block text-sm text-sky-900/90 dark:text-sky-200/90">
                                            When enabled, authorized {{ app_name }} support staff can sign in to help configure, set up, or debug your account. You can turn this off at any time.
                                        </span>
                                    </span>
                                </label>
                                <p v-if="form.errors.allow_support_access" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                    {{ form.errors.allow_support_access }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-show="settingsTab === 'scheduling'"
                            role="tabpanel"
                            class="p-6"
                        >
                            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                                Workday length, start hour, and default overlap on the service yard schedule board.
                            </p>
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <div>
                                    <label for="workday_hours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Workday hours
                                    </label>
                                    <select
                                        id="workday_hours"
                                        v-model.number="form.workday_hours"
                                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                        <option v-for="h in [4, 5, 6, 7, 8, 9, 10]" :key="h" :value="h">
                                            {{ h }} hrs
                                        </option>
                                    </select>
                                    <p v-if="form.errors.workday_hours" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.workday_hours }}
                                    </p>
                                </div>
                                <div>
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Workday start time
                                    </label>
                                    <input
                                        id="start_time"
                                        v-model="form.start_time"
                                        type="time"
                                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Hour aligns with the schedule toolbar (6:00–9:00 AM presets).
                                    </p>
                                    <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.start_time }}
                                    </p>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <label class="flex items-center gap-3 cursor-pointer select-none">
                                        <input
                                            v-model="form.allow_overlap"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Allow overlapping assignments by default
                                        </span>
                                    </label>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Staff can still change overlap on the board for their session.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="settingsTab === 'service_ticket'"
                            role="tabpanel"
                            class="p-6"
                        >
                            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                                Estimate threshold, customer acknowledgment copy, and who gets notified when tickets are signed.
                            </p>

                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                                <!-- Estimate Threshold -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                                    <label for="estimate_threshold" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Estimate Variance Threshold
                                    </label>

                                    <div class="flex items-center gap-4">
                                        <div class="relative flex-1 max-w-[150px]">
                                            <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-xl pointer-events-none">
                                                percent
                                            </span>
                                            <input
                                                id="estimate_threshold"
                                                type="number"
                                                v-model.number="form.estimate_threshold_percent"
                                                min="0"
                                                max="100"
                                                step="1"
                                                class="block w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm font-medium">
                                                %
                                            </span>
                                        </div>

                                        <div class="flex-1">
                                            <input
                                                type="range"
                                                v-model.number="form.estimate_threshold_percent"
                                                min="0"
                                                max="100"
                                                step="5"
                                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                        <p class="text-sm text-blue-800 dark:text-blue-300">
                                            <span class="material-icons text-base align-middle mr-1">info</span>
                                            <strong>Customer will see:</strong> "Our estimate may vary by {{ form.estimate_threshold_percent }}%. If the final cost exceeds this threshold, customer verification will be required before proceeding."
                                        </p>
                                    </div>

                                    <p v-if="form.errors.estimate_threshold_percent" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.estimate_threshold_percent }}
                                    </p>
                                </div>

                                <!-- Service Ticket Acknowledgment Text -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                                    <label for="service_ticket_ack" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Service Ticket Acknowledgment Text
                                    </label>

                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        This text will appear on service tickets for customers to acknowledge your policies and terms.
                                    </p>

                                    <textarea
                                        id="service_ticket_ack"
                                        v-model="form.service_ticket_ack_text"
                                        @input="updateCharacterCount"
                                        rows="6"
                                        maxlength="1000"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 resize-y"
                                        placeholder="Enter the acknowledgment text that customers must agree to..."
                                    ></textarea>

                                    <div class="flex items-center justify-between mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Use [COMPANY NAME] as a placeholder for your company name
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ characterCount }} / 1000 characters
                                        </p>
                                    </div>

                                    <!-- Preview Box -->
                             <!--        <div class="mt-4 p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg">
                                        <div class="flex items-start gap-2 mb-2">
                                            <span class="material-icons text-gray-400 dark:text-gray-500 text-sm mt-0.5">
                                                visibility
                                            </span>
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                                Preview
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">
                                            {{ form.service_ticket_ack_text || 'Your acknowledgment text will appear here...' }}
                                        </p>
                                    </div> -->

                                    <p v-if="form.errors.service_ticket_ack_text" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.service_ticket_ack_text }}
                                    </p>
                                </div>

                                <!-- Service Ticket Signed Notification -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                                    <label for="service_ticket_signed_notify_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Service Ticket Signed Notification
                                    </label>

                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Select who should receive notifications when service tickets are signed or approved by customers. The first user is selected by default. If no users are available, notifications will be sent to the account owner.
                                    </p>

                                    <select
                                        id="service_ticket_signed_notify_user_id"
                                        v-model="form.service_ticket_signed_notify_user_id"
                                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    >
                                        <option v-if="users.length > 0" :value="users[0].id">
                                            {{ users[0].name }} ({{ users[0].email }}) - Default
                                        </option>
                                        <option v-for="user in users.slice(1)" :key="user.id" :value="user.id">
                                            {{ user.name }} ({{ user.email }})
                                        </option>
                                        <option value="">Account Owner (if no users available)</option>
                                    </select>

                                    <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                        <p class="text-sm text-amber-800 dark:text-amber-300">
                                            <span class="material-icons text-base align-middle mr-1">notifications</span>
                                            <strong>Notification Recipients:</strong>
                                            <span v-if="selectedNotificationUser">
                                                {{ selectedNotificationUser.name }} ({{ selectedNotificationUser.email }})
                                                <em v-if="isFirstUserSelected" class="text-xs">(Default)</em>
                                            </span>
                                            <span v-else-if="users.length === 0">Account Owner</span>
                                            <span v-else>{{ users[0].name }} ({{ users[0].email }}) - Default</span>
                                        </p>
                                    </div>

                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        Transactional SMS to customers is separate from these in-app alerts. Configure it on the
                                        <Link :href="route('account.notifications.sms.index')" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">text notifications</Link>
                                        page.
                                    </p>

                                    <p v-if="form.errors.service_ticket_signed_notify_user_id" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.service_ticket_signed_notify_user_id }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="settingsTab === 'transactions'"
                            role="tabpanel"
                            class="p-6"
                        >
                            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                                Defaults for new contracts and deals. Consignment fee and policy bullets are on the
                                <Link :href="route('account.consignment.index')" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">consignment configuration</Link>
                                page.
                            </p>

                            <!-- Default payment term (enum) -->
                            <div class="mb-6 max-w-xl">
                                <label for="default_payment_term" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Default payment term
                                </label>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Standard payment schedule (e.g. Net 30) applied when creating contracts or transactions.
                                </p>
                                <select
                                    id="default_payment_term"
                                    v-model="form.default_payment_term"
                                    class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option
                                        v-for="opt in paymentTermOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="selectedPaymentTermDescription"
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ selectedPaymentTermDescription }}
                                </p>
                                <p v-if="form.errors.default_payment_term" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                    {{ form.errors.default_payment_term }}
                                </p>
                            </div>

                            <!-- Default contract terms (body) -->
                            <div class="mb-6">
                                <label for="default_contract_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Default contract terms
                                </label>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    General agreement language pre-filled on new contracts (sale terms, obligations, expectations).
                                </p>
                                <textarea
                                    id="default_contract_terms"
                                    v-model="form.default_contract_terms"
                                    rows="5"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white resize-y"
                                    placeholder="This agreement outlines the terms and conditions..."
                                />
                                <p v-if="form.errors.default_contract_terms" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                    {{ form.errors.default_contract_terms }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <div>
                                    <label for="default_payment_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Default payment terms (text)
                                    </label>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Pre-filled on new contracts when no other terms are supplied.
                                    </p>
                                    <textarea
                                        id="default_payment_terms"
                                        v-model="form.default_payment_terms"
                                        rows="6"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 resize-y"
                                        placeholder="Payment is due as specified in the contract..."
                                    />
                                    <p v-if="form.errors.default_payment_terms" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.default_payment_terms }}
                                    </p>
                                </div>
                                <div>
                                    <label for="default_delivery_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Default delivery terms
                                    </label>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Pre-filled on new contracts and related records when created from a deal flow.
                                    </p>
                                    <textarea
                                        id="default_delivery_terms"
                                        v-model="form.default_delivery_terms"
                                        rows="6"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 resize-y"
                                        placeholder="Delivery will be scheduled according to contract terms..."
                                    />
                                    <p v-if="form.errors.default_delivery_terms" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                        {{ form.errors.default_delivery_terms }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 rounded-b-lg">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Changes apply to account defaults for future service tickets, work orders, and transactions
                                </p>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors dark:focus:ring-offset-gray-800 font-medium"
                                >
                                    <span class="material-icons text-sm">{{ form.processing ? 'hourglass_empty' : 'save' }}</span>
                                    <span>{{ form.processing ? 'Saving...' : 'Save Changes' }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
