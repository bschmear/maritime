<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

defineProps({
    boatTypes: { type: Array, default: () => [] },
    hullTypes: { type: Array, default: () => [] },
    hullMaterials: { type: Array, default: () => [] },
});

const form = useForm({
    display_name: '',
    slug: '',
    active: true,
    boat_type_id: '',
    hull_type_id: '',
    hull_material_id: '',
});

const slugManuallyEdited = { value: false };

watch(
    () => form.display_name,
    (value) => {
        if (!slugManuallyEdited.value) {
            form.slug = slugify(value);
        }
    },
);

function onSlugInput() {
    slugManuallyEdited.value = true;
}

function slugify(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const submit = () => {
    form.post(route('kiosk.inventory-brands.store'));
};
</script>

<template>
    <Head title="New Inventory Brand" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.inventory-brands.index')"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    ←
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">New Brand</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid max-w-2xl grid-cols-1 gap-6">
                    <div>
                        <InputLabel for="display_name" value="Display name" />
                        <TextInput id="display_name" v-model="form.display_name" class="mt-2 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.display_name" />
                    </div>
                    <div>
                        <InputLabel for="slug" value="Slug" />
                        <TextInput id="slug" v-model="form.slug" class="mt-2 block w-full font-mono" required @input="onSlugInput" />
                        <InputError class="mt-2" :message="form.errors.slug" />
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            Active
                        </label>
                    </div>
                    <div>
                        <InputLabel for="boat_type_id" value="Boat type" />
                        <select
                            id="boat_type_id"
                            v-model="form.boat_type_id"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">None</option>
                            <option v-for="type in boatTypes" :key="type.id" :value="type.id">{{ type.display_name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="hull_type_id" value="Hull type" />
                        <select
                            id="hull_type_id"
                            v-model="form.hull_type_id"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">None</option>
                            <option v-for="type in hullTypes" :key="type.id" :value="type.id">{{ type.display_name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="hull_material_id" value="Hull material" />
                        <select
                            id="hull_material_id"
                            v-model="form.hull_material_id"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">None</option>
                            <option v-for="material in hullMaterials" :key="material.id" :value="material.id">{{ material.display_name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                    <Link :href="route('kiosk.inventory-brands.index')" class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                        Cancel
                    </Link>
                    <button type="submit" class="gradient-btn rounded-lg px-4 py-2.5 text-sm" :disabled="form.processing">
                        Create brand
                    </button>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>
