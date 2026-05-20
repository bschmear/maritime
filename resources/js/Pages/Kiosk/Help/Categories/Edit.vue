<script setup>
import KioskLayout from '@/Layouts/KioskLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ category: Object, parents: Array });

const form = useForm({
    name: props.category.name,
    description: props.category.description || '',
    parent_id: props.category.parent_id || '',
    sort_order: props.category.sort_order || 0,
    active: props.category.active ?? true,
});
</script>

<template>
    <Head title="Edit Category" />
    <KioskLayout>
        <template #header><h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Category</h1></template>
        <form class="max-w-xl space-y-4" @submit.prevent="form.put(route('kiosk.help-categories.update', category.id))">
            <div>
                <InputLabel value="Name" />
                <TextInput v-model="form.name" class="mt-1 w-full dark:bg-gray-800 dark:text-white" required />
            </div>
            <div>
                <InputLabel value="Description" />
                <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <InputLabel value="Parent" />
                <select v-model="form.parent_id" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">None</option>
                    <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <label class="flex items-center gap-2"><Checkbox v-model:checked="form.active" /> Active</label>
            <div class="flex gap-3">
                <button type="submit" class="gradient-btn rounded-lg px-4 py-2 text-sm">Update</button>
                <Link :href="route('kiosk.help-categories.index')" class="text-sm text-gray-500">Cancel</Link>
            </div>
        </form>
    </KioskLayout>
</template>
