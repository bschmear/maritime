<script setup>
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

const avatarFileInput = ref(null);
const avatarPreviewLocal = ref(null);

const sectionsToRender = computed(() =>
    Object.entries(props.formSchema?.form ?? {}).filter(([key]) => {
        if (key === 'access' && !props.canAssignRole) return false;
        return true;
    })
);

const form = useForm({
    first_name: props.record?.first_name ?? '',
    last_name: props.record?.last_name ?? '',
    email: props.record?.email ?? '',
    mobile_phone: props.record?.mobile_phone ?? '',
    office_phone: props.record?.office_phone ?? '',
    bio: props.record?.bio ?? '',
    avatar: props.record?.avatar ?? null,
    is_technician: Boolean(props.record?.is_technician),
    current_role: props.record?.current_role ?? props.record?.role?.id ?? null,
});

const fieldLabel = (key) => props.fieldsSchema[key]?.label ?? key;
const fieldHelp  = (key) => props.fieldsSchema[key]?.help ?? null;

const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.avatar = file;
    if (avatarPreviewLocal.value) URL.revokeObjectURL(avatarPreviewLocal.value);
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

    props.mode === 'create' ? form.post(url, submitOpts) : form.put(url, submitOpts);
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">

        <!-- Avatar + name hero row -->
        <div class="flex flex-col items-center gap-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-start">

            <!-- Avatar -->
            <div class="relative shrink-0">
                <button
                    type="button"
                    class="group relative h-24 w-24 overflow-hidden rounded-full border-2 border-gray-200 bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700"
                    @click="avatarFileInput.click()"
                >
                    <!-- Image or initials -->
                    <img
                        v-if="displayedAvatarSrc"
                        :src="displayedAvatarSrc"
                        alt="Avatar"
                        class="h-full w-full object-cover"
                    />
                    <span
                        v-else
                        class="flex h-full w-full items-center justify-center text-2xl font-semibold text-gray-400 dark:text-gray-500"
                    >
                        {{ avatarInitials }}
                    </span>

                    <!-- Hover overlay -->
                    <span class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                        <span class="material-icons text-lg text-white">photo_camera</span>
                        <span class="text-[10px] font-medium text-white">Change</span>
                    </span>
                </button>

                <input
                    ref="avatarFileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    @change="onAvatarChange"
                />

                <p v-if="form.errors.avatar" class="mt-1.5 text-center text-xs text-red-600">{{ form.errors.avatar }}</p>
            </div>

            <!-- Name fields -->
            <div class="flex-1 space-y-4 self-center">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="uf-first_name">
                            First name
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
                            Last name
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
                </div>

                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Click the avatar to upload a new photo (JPEG, PNG, GIF or WebP).
                </p>
            </div>
        </div>

        <!-- Dynamic sections (skip avatar/name fields already rendered above) -->
        <template v-for="[sectionKey, section] in sectionsToRender" :key="sectionKey">
            <div
                v-if="(section.fields || []).some(f => !['avatar','first_name','last_name','display_name'].includes(f.key))"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-5 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {{ section.label || sectionKey }}
                </h3>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <template v-for="field in (section.fields || [])" :key="field.key">

                        <!-- Skip fields already in the hero block -->
                        <template v-if="!['avatar','first_name','last_name','display_name'].includes(field.key)">

                            <!-- Role selector -->
                            <template v-if="field.key === 'current_role' && canAssignRole">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <select
                                        :id="`uf-${field.key}`"
                                        v-model="form.current_role"
                                        class="input-style w-full max-w-sm dark:bg-gray-900 dark:text-white"
                                    >
                                        <option :value="null">— No role —</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.display_name }}</option>
                                    </select>
                                    <p v-if="fieldHelp(field.key)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</p>
                                    <p v-if="form.errors.current_role" class="mt-1 text-sm text-red-600">{{ form.errors.current_role }}</p>
                                </div>
                            </template>

                            <!-- Email -->
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
                                    <p v-if="fieldHelp(field.key)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</p>
                                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                                </div>
                            </template>

                            <!-- Phone fields -->
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

                            <!-- Bio -->
                            <template v-else-if="field.key === 'bio'">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="`uf-${field.key}`">
                                        {{ fieldLabel(field.key) }}
                                    </label>
                                    <textarea
                                        :id="`uf-${field.key}`"
                                        v-model="form.bio"
                                        rows="4"
                                        class="input-style w-full resize-y dark:bg-gray-900 dark:text-white"
                                    />
                                    <p v-if="fieldHelp(field.key)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</p>
                                    <p v-if="form.errors.bio" class="mt-1 text-sm text-red-600">{{ form.errors.bio }}</p>
                                </div>
                            </template>

                            <!-- Technician toggle -->
                            <template v-else-if="field.key === 'is_technician'">
                                <div class="md:col-span-2">
                                    <label
                                        :for="`uf-${field.key}`"
                                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700/50"
                                    >
                                        <input
                                            :id="`uf-${field.key}`"
                                            v-model="form.is_technician"
                                            type="checkbox"
                                            class="mt-0.5 rounded border-gray-300 text-primary-600"
                                        />
                                        <div>
                                            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ fieldLabel(field.key) }}</span>
                                            <span v-if="fieldHelp(field.key)" class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ fieldHelp(field.key) }}</span>
                                        </div>
                                    </label>
                                    <p v-if="form.errors.is_technician" class="mt-1 text-sm text-red-600">{{ form.errors.is_technician }}</p>
                                </div>
                            </template>

                        </template>
                    </template>
                </div>
            </div>
        </template>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
            <button type="button" class="btn-secondary" @click="$emit('cancelled')">
                Cancel
            </button>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : mode === 'create' ? 'Create user' : 'Save changes' }}
            </button>
        </div>
    </form>
</template>