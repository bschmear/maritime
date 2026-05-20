<script setup>
import TenantLayout from '@/Layouts/TenantLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance, watch } from 'vue';

const inertiaApp = getCurrentInstance();

function showToast(type, message) {
    if (!message) {
        return;
    }
    const root = inertiaApp?.appContext?.app?._instance?.proxy;
    if (typeof root?.createToast === 'function') {
        root.createToast(type, String(message));
    }
}

const props = defineProps({
    account: { type: Object, required: true },
    smsPreferences: { type: Object, required: true },
    smsNotificationTypes: { type: Array, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const isSandboxMode = computed(() => !!props.account?.sandbox_mode);

const form = useForm({
    sms_enabled: !!props.account?.sms_enabled,
    preferences: { ...props.smsPreferences },
});

watch(
    () => [props.account, props.smsPreferences],
    () => {
        if (!props.account) {
            return;
        }
        form.sms_enabled = !!props.account.sms_enabled;
        form.preferences = { ...props.smsPreferences };
    },
    { deep: true },
);

watch(flashSuccess, (msg) => {
    if (msg) {
        showToast('success', msg);
    }
});

const perTypeDisabled = computed(() => !form.sms_enabled);

const save = () => {
    form.patch(route('account.notifications.sms.update'), { preserveScroll: true });
};

function prefError(value) {
    return form.errors[`preferences.${value}`] ?? null;
}
</script>

<template>
    <Head title="Text notifications" />

    <TenantLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('account.index')"
                        class="mb-2 inline-flex items-center gap-1 text-md font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        <span class="material-icons text-lg leading-none">arrow_back</span>
                        Account
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Text notifications</h2>
                    <p class="mt-1 text-md text-gray-500 dark:text-gray-400">
                        Transactional SMS only: one-way alerts from your account. This is not chat, inbound messaging, or marketing.
                    </p>
                </div>
            </div>
        </template>

        <div v-if="flashSuccess" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-md text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
            {{ flashSuccess }}
        </div>

        <div
            v-if="isSandboxMode"
            class="mb-6 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-md text-amber-950 shadow-sm dark:border-amber-700/80 dark:bg-amber-950/40 dark:text-amber-100"
            role="status"
        >
            <span class="material-icons shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true">science</span>
            <p class="min-w-0 leading-snug">
                <strong class="font-semibold">Sandbox mode is on.</strong>
                Customer emails and text notifications are sent to you (the signed-in user), not real customers, until you turn sandbox off under Account → General Account Settings.
            </p>
        </div>

        <div class="mx-auto max-w-2xl">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:p-8">
                <div class="mb-8 flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/50">
                        <span class="material-icons text-[22px] leading-none text-primary-600 dark:text-primary-400">sms</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">SMS preferences</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Turn SMS on for the tenant, then choose categories. Sandbox mode (test routing for emails and SMS) is under General Account Settings on the main Account page.
                        </p>
                    </div>
                </div>

                <form class="space-y-8" @submit.prevent="save">
                    <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950">
                        <div>
                            <label for="sms_master" class="text-md font-semibold text-gray-900 dark:text-white">Text notifications</label>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Master switch for all SMS from this account.</p>
                            <p v-if="form.errors.sms_enabled" class="mt-1 text-sm text-red-600">{{ form.errors.sms_enabled }}</p>
                        </div>
                        <button
                            id="sms_master"
                            type="button"
                            role="switch"
                            :aria-checked="form.sms_enabled"
                            class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                            :class="form.sms_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'"
                            @click="form.sms_enabled = !form.sms_enabled"
                        >
                            <span
                                class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition"
                                :class="form.sms_enabled ? 'translate-x-5' : 'translate-x-0'"
                            />
                        </button>
                    </div>

                    <div>
                        <h4 class="mb-1 text-md font-semibold text-gray-900 dark:text-white">Categories</h4>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            These apply only when text notifications are on. Sending must still be implemented per feature.
                        </p>

                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <li
                                v-for="item in smsNotificationTypes"
                                :key="item.value"
                                class="flex items-start justify-between gap-4 px-4 py-4 sm:px-5"
                                :class="{ 'opacity-50': perTypeDisabled }"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ item.label }}</p>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                    <p v-if="prefError(item.value)" class="mt-1 text-sm text-red-600">{{ prefError(item.value) }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="!!form.preferences[item.value]"
                                    :disabled="perTypeDisabled"
                                    class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:focus:ring-offset-gray-900"
                                    :class="form.preferences[item.value] ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'"
                                    @click="form.preferences[item.value] = !form.preferences[item.value]"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition"
                                        :class="form.preferences[item.value] ? 'translate-x-5' : 'translate-x-0'"
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-md font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50 sm:w-auto"
                    >
                        <span class="material-icons text-lg leading-none">save</span>
                        {{ form.processing ? 'Saving…' : 'Save settings' }}
                    </button>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
