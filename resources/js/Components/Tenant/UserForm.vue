<script setup>
import RecordSelect from '@/Components/Tenant/RecordSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    formSchema: { type: Object, required: true },
    fieldsSchema: { type: Object, required: true },
    enumOptions: { type: Object, default: () => ({}) },
    mode: {
        type: String,
        required: true,
        validator: (v) => ['create', 'edit'].includes(v),
    },
    roles: { type: Array, default: () => [] },
    canAssignRole: { type: Boolean, default: false },
    avatarPreviewUrl: { type: String, default: null },
});

const emit = defineEmits(['saved', 'cancelled']);

const HERO_FIELD_KEYS = new Set(['avatar', 'first_name', 'last_name', 'position_title', 'display_name']);

const avatarFileInput = ref(null);
const avatarPreviewLocal = ref(null);

const sectionsToRender = computed(() =>
    Object.entries(props.formSchema?.form ?? {}).filter(([key, section]) => {
        if (key === 'access' && !props.canAssignRole) {
            return false;
        }

        return (section.fields ?? []).some((field) => !HERO_FIELD_KEYS.has(field.key));
    }),
);

const form = useForm({
    first_name: props.record?.first_name ?? '',
    last_name: props.record?.last_name ?? '',
    position_title: props.record?.position_title ?? '',
    email: props.record?.email ?? '',
    mobile_phone: props.record?.mobile_phone ?? '',
    office_phone: props.record?.office_phone ?? '',
    bio: props.record?.bio ?? '',
    avatar: props.record?.avatar ?? null,
    is_technician: Boolean(props.record?.is_technician),
    manager_user_id: props.record?.manager_user_id ?? props.record?.manager?.id ?? null,
    current_role: props.record?.current_role ?? props.record?.role?.id ?? null,
});

const fieldLabel = (key) => props.fieldsSchema[key]?.label ?? key;
const fieldHelp = (key) => props.fieldsSchema[key]?.help ?? null;
const getEnumOptions = (key) => props.enumOptions[key] ?? [];
const managerExcludeIds = computed(() => (props.record?.id ? [props.record.id] : []));

const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) {
        return;
    }
    form.avatar = file;
    if (avatarPreviewLocal.value) {
        URL.revokeObjectURL(avatarPreviewLocal.value);
    }
    avatarPreviewLocal.value = URL.createObjectURL(file);
};

const displayedAvatarSrc = computed(() => avatarPreviewLocal.value || props.avatarPreviewUrl);

const avatarInitials = computed(() => {
    const f = form.first_name?.charAt(0) ?? '';
    const l = form.last_name?.charAt(0) ?? '';
    return (f + l).toUpperCase() || '?';
});

const submit = () => {
    const url = props.mode === 'create'
        ? route('users.store')
        : route('users.update', props.record.id);

    const opts = { preserveScroll: true, onSuccess: () => emit('saved') };
    const submitOpts = form.avatar instanceof File ? { ...opts, forceFormData: true } : opts;

    if (props.mode === 'create') {
        form.post(url, submitOpts);
    } else {
        form.put(url, submitOpts);
    }
};
</script>

<template>
    <form class="w-full space-y-4" @submit.prevent="submit">
        <!-- Name, title, avatar -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="relative shrink-0 self-center sm:self-start">
                    <button
                        type="button"
                        class="group relative h-16 w-16 overflow-hidden rounded-full border border-gray-200 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                        @click="avatarFileInput.click()"
                    >
                        <img
                            v-if="displayedAvatarSrc"
                            :src="displayedAvatarSrc"
                            alt="Avatar"
                            class="h-full w-full object-cover"
                        />
                        <span
                            v-else
                            class="flex h-full w-full items-center justify-center text-lg font-semibold text-gray-400 dark:text-gray-500"
                        >
                            {{ avatarInitials }}
                        </span>
                        <span class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                            <span class="material-icons text-base text-white">photo_camera</span>
                        </span>
                    </button>
                    <input
                        ref="avatarFileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="sr-only"
                        @change="onAvatarChange"
                    />
                    <p v-if="form.errors.avatar" class="mt-1 text-center text-xs text-red-600">{{ form.errors.avatar }}</p>
                </div>

                <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="uf-first_name">
                            {{ fieldLabel('first_name') }}
                        </label>
                        <input
                            id="uf-first_name"
                            v-model="form.first_name"
                            type="text"
                            autocomplete="given-name"
                            class="input-style w-full dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="uf-last_name">
                            {{ fieldLabel('last_name') }}
                        </label>
                        <input
                            id="uf-last_name"
                            v-model="form.last_name"
                            type="text"
                            autocomplete="family-name"
                            class="input-style w-full dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="uf-position_title">
                            {{ fieldLabel('position_title') }}
                        </label>
                        <input
                            id="uf-position_title"
                            v-model="form.position_title"
                            type="text"
                            autocomplete="organization-title"
                            :placeholder="fieldHelp('position_title') || 'e.g. Sales Manager'"
                            class="input-style w-full dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="form.errors.position_title" class="mt-1 text-sm text-red-600">{{ form.errors.position_title }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <template v-for="([sectionKey, section], sectionIndex) in sectionsToRender" :key="sectionKey">
                <h3
                    v-if="section.label"
                    class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    :class="sectionIndex > 0 ? 'mt-6 border-t border-gray-200 pt-6 dark:border-gray-700' : ''"
                >
                    {{ section.label }}
                </h3>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <template v-for="field in (section.fields || [])" :key="field.key">
                        <template v-if="!HERO_FIELD_KEYS.has(field.key)">
                            <template v-if="field.key === 'current_role' && canAssignRole">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <select
                                        :id="`uf-${field.key}`"
                                        v-model="form.current_role"
                                        class="input-style w-full max-w-md dark:bg-gray-900 dark:text-white"
                                    >
                                        <option :value="null">— No role —</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.display_name }}</option>
                                    </select>
                                    <p v-if="fieldHelp(field.key)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</p>
                                    <p v-if="form.errors.current_role" class="mt-1 text-sm text-red-600">{{ form.errors.current_role }}</p>
                                </div>
                            </template>

                            <template v-else-if="field.key === 'email'">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <input
                                        :id="`uf-${field.key}`"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        class="input-style w-full dark:bg-gray-900 dark:text-white"
                                    />
                                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                                </div>
                            </template>

                            <template v-else-if="field.key === 'mobile_phone' || field.key === 'office_phone'">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <input
                                        :id="`uf-${field.key}`"
                                        v-model="form[field.key]"
                                        type="tel"
                                        class="input-style w-full dark:bg-gray-900 dark:text-white"
                                    />
                                    <p v-if="form.errors[field.key]" class="mt-1 text-sm text-red-600">{{ form.errors[field.key] }}</p>
                                </div>
                            </template>

                            <template v-else-if="field.key === 'bio'">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <textarea
                                        :id="`uf-${field.key}`"
                                        v-model="form.bio"
                                        rows="2"
                                        class="input-style w-full resize-y dark:bg-gray-900 dark:text-white"
                                    />
                                    <p v-if="form.errors.bio" class="mt-1 text-sm text-red-600">{{ form.errors.bio }}</p>
                                </div>
                            </template>

                            <template v-else-if="field.key === 'is_technician'">
                                <div class="md:col-span-2">
                                    <label
                                        :for="`uf-${field.key}`"
                                        class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            :id="`uf-${field.key}`"
                                            v-model="form.is_technician"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-primary-600"
                                        />
                                        <span>{{ fieldLabel(field.key) }}</span>
                                    </label>
                                    <p v-if="form.errors.is_technician" class="mt-1 text-sm text-red-600">{{ form.errors.is_technician }}</p>
                                </div>
                            </template>

                            <template v-else-if="field.key === 'manager_user_id'">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <RecordSelect
                                        :id="`uf-${field.key}`"
                                        :field="fieldsSchema.manager_user_id"
                                        v-model="form.manager_user_id"
                                        :enum-options="getEnumOptions('manager_user_id')"
                                        :record="record"
                                        field-key="manager_user_id"
                                        :exclude-ids="managerExcludeIds"
                                    />
                                    <p v-if="fieldHelp(field.key)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</p>
                                    <p v-if="form.errors.manager_user_id" class="mt-1 text-sm text-red-600">{{ form.errors.manager_user_id }}</p>
                                </div>
                            </template>
                        </template>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <button type="button" class="btn-secondary" @click="$emit('cancelled')">
                Cancel
            </button>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : mode === 'create' ? 'Create user' : 'Save changes' }}
            </button>
        </div>
    </form>
</template>
