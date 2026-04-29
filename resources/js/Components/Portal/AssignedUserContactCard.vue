<script setup>
defineProps({
    assignedUser: {
        type: Object,
        required: true,
    },
});

const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    const cleaned = String(phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    if (match) {
        return `(${match[1]}) ${match[2]}-${match[3]}`;
    }
    return phone;
};
</script>

<template>
    <div
        class="rounded-xl border border-primary-200 bg-primary-50 px-5 py-4 dark:border-primary-900/40 dark:bg-primary-950/50"
    >
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Questions?</h3>
        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
            Please contact your assigned representative<span v-if="assignedUser.name">, {{ assignedUser.name }}</span>.
        </p>
        <ul class="mt-3 space-y-2 text-sm">
            <li v-if="assignedUser.phone" class="flex items-start gap-2 text-gray-800 dark:text-gray-200">
                <span class="material-icons mt-0.5 flex-shrink-0 text-base text-primary-600 dark:text-primary-400">phone</span>
                <a
                    :href="`tel:${String(assignedUser.phone).replace(/\s/g, '')}`"
                    class="font-medium text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 dark:text-primary-300"
                >
                    {{ formatPhoneDisplay(assignedUser.phone) }}
                </a>
            </li>
            <li v-if="assignedUser.email" class="flex items-start gap-2 text-gray-800 dark:text-gray-200">
                <span class="material-icons mt-0.5 flex-shrink-0 text-base text-primary-600 dark:text-primary-400">email</span>
                <a
                    :href="`mailto:${assignedUser.email}`"
                    class="break-all font-medium text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 dark:text-primary-300"
                >
                    {{ assignedUser.email }}
                </a>
            </li>
        </ul>
    </div>
</template>
