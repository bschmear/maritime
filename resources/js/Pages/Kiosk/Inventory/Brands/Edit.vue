<script setup>
import KioskImageUploadField from '@/Components/Kiosk/KioskImageUploadField.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import KioskLayout from '@/Layouts/KioskLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    brand: Object,
    boatTypes: { type: Array, default: () => [] },
    hullTypes: { type: Array, default: () => [] },
    hullMaterials: { type: Array, default: () => [] },
});

const logoUrl = ref(props.brand.logo_url || '');

const form = useForm({
    display_name: props.brand.display_name || '',
    slug: props.brand.slug || '',
    active: props.brand.active ?? true,
    boat_type_id: props.brand.boat_type_id || '',
    hull_type_id: props.brand.hull_type_id || '',
    hull_material_id: props.brand.hull_material_id || '',
});

const submit = () => {
    form.put(route('kiosk.inventory-brands.update', props.brand.id));
};

const removeLogoFromServer = () => {
    if (!props.brand.logo_url) {
        logoUrl.value = '';
        return;
    }

    router.delete(route('kiosk.inventory-brands.remove-logo', props.brand.id), {
        preserveScroll: true,
        onSuccess: () => {
            logoUrl.value = '';
        },
    });
};
</script>

<template>
    <Head :title="`Edit ${brand.display_name}`" />

    <KioskLayout>
        <template #header>
            <div class="flex items-center gap-x-3">
                <Link
                    :href="route('kiosk.inventory-brands.index')"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                >
                    ←
                </Link>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Brand</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid max-w-2xl grid-cols-1 gap-6">
                    <div>
                        <KioskImageUploadField
                            v-model="logoUrl"
                            :existing-url="brand.logo_url"
                            :upload-url="route('kiosk.inventory-brands.upload-logo', brand.id)"
                            label="Brand logo"
                            help="Uploads immediately to the inventory catalog CDN."
                        />
                        <button
                            v-if="brand.logo_url"
                            type="button"
                            class="mt-3 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                            @click="removeLogoFromServer"
                        >
                            Delete logo from catalog
                        </button>
                    </div>
                    <div>
                        <InputLabel for="display_name" value="Display name" />
                        <TextInput id="display_name" v-model="form.display_name" class="mt-2 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.display_name" />
                    </div>
                    <div>
                        <InputLabel for="slug" value="Slug" />
                        <TextInput id="slug" v-model="form.slug" class="mt-2 block w-full font-mono" required />
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
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ brand.catalog_assets_count || 0 }} catalog model(s) linked to this brand.
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                    <Link :href="route('kiosk.inventory-brands.index')" class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                        Cancel
                    </Link>
                    <button type="submit" class="gradient-btn rounded-lg px-4 py-2.5 text-sm" :disabled="form.processing">
                        Save changes
                    </button>
                </div>
            </div>
        </form>
    </KioskLayout>
</template>
