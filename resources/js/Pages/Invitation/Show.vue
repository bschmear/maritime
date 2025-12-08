<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    invitation: {
        type: Object,
        required: true,
    },
    account: {
        type: Object,
        required: true,
    },
    user: {
        type: Object,
        required: true,
    },
});

const isAccepting = ref(false);
const isDeclining = ref(false);
const acceptSuccess = ref(false);
const declineSuccess = ref(false);

const acceptInvitation = async () => {
    if (isAccepting.value || acceptSuccess.value) return;

    isAccepting.value = true;

    try {
        await axios.post(route('invitations.accept', props.invitation.token));

        // Mark as successful - this will update the UI
        acceptSuccess.value = true;

        // Controller will redirect, but show immediate success feedback
        alert('Invitation accepted successfully! Redirecting...');

        // Fallback redirect in case controller redirect doesn't work immediately
        setTimeout(() => {
            window.location.href = route('dashboard');
        }, 2000);

    } catch (error) {
        console.error('Error accepting invitation:', error);
        const errorMessage = error.response?.data?.errors?.stripe?.[0] ||
                           error.response?.data?.errors?.plan?.[0] ||
                           error.response?.data?.errors?.seats?.[0] ||
                           error.response?.data?.message ||
                           'Failed to accept invitation. Please try again.';
        alert(errorMessage);
        isAccepting.value = false;
    }
};

const declineInvitation = async () => {
    if (isDeclining.value || declineSuccess.value) return;

    if (!confirm('Are you sure you want to decline this invitation?')) {
        return;
    }

    isDeclining.value = true;

    try {
        await axios.post(route('invitations.decline', props.invitation.token));

        // Mark as successful - this will update the UI
        declineSuccess.value = true;

        // Controller will redirect, but show immediate success feedback
        alert('Invitation declined. Redirecting...');

        // Fallback redirect in case controller redirect doesn't work immediately
        setTimeout(() => {
            window.location.href = route('dashboard');
        }, 2000);

    } catch (error) {
        console.error('Error declining invitation:', error);
        alert('Failed to decline invitation. Please try again.');
        isDeclining.value = false;
    }
};
</script>

<template>
    <Head title="Account Invitation" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Account Invitation
            </h2>
        </template>

        <div class="py-12 bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Header -->
                        <div class="text-center mb-8">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
                                <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                You're Invited!
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ invitation.inviter?.name || 'Someone' }} has invited you to join <strong>{{ account.name }}</strong>
                            </p>
                        </div>

                        <!-- Invitation Details -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-8">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invitation Details</h4>

                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account:</dt>
                                    <dd class="text-sm text-gray-900 dark:text-white">{{ account.name }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Your Role:</dt>
                                    <dd class="text-sm text-gray-900 dark:text-white capitalize">{{ invitation.role }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invited by:</dt>
                                    <dd class="text-sm text-gray-900 dark:text-white">
                                        {{ invitation.inviter?.name || 'Unknown' }}
                                        <span v-if="invitation.inviter?.email" class="text-gray-500">({{ invitation.inviter.email }})</span>
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invited on:</dt>
                                    <dd class="text-sm text-gray-900 dark:text-white">
                                        {{ new Date(invitation.created_at).toLocaleDateString() }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Account Info -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">About this account</h4>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <p>Joining this account will give you access to manage and track marine sales opportunities. You'll be able to collaborate with the team and contribute to the organization's success.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button
                                @click="acceptInvitation"
                                :disabled="isAccepting || isDeclining || acceptSuccess || declineSuccess"
                                :class="[
                                    'flex-1 font-medium py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center',
                                    acceptSuccess
                                        ? 'bg-green-600 text-white cursor-not-allowed'
                                        : 'bg-blue-600 hover:bg-blue-700 text-white',
                                    (isAccepting || isDeclining || declineSuccess) ? 'opacity-50 cursor-not-allowed' : ''
                                ]"
                            >
                                <svg v-if="isAccepting" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else-if="acceptSuccess" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ acceptSuccess ? 'Accepted!' : (isAccepting ? 'Accepting...' : 'Accept Invitation') }}
                            </button>

                            <button
                                @click="declineInvitation"
                                :disabled="isAccepting || isDeclining || acceptSuccess || declineSuccess"
                                :class="[
                                    'flex-1 font-medium py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center',
                                    declineSuccess
                                        ? 'bg-red-600 text-white cursor-not-allowed'
                                        : 'bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200',
                                    (isAccepting || isDeclining || acceptSuccess) ? 'opacity-50 cursor-not-allowed' : ''
                                ]"
                            >
                                <svg v-if="isDeclining" class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-800 dark:text-gray-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else-if="declineSuccess" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                {{ declineSuccess ? 'Declined' : (isDeclining ? 'Declining...' : 'Decline') }}
                            </button>
                        </div>

                        <!-- Success Messages -->
                        <div v-if="acceptSuccess || declineSuccess" class="mt-6 p-4 rounded-lg" :class="acceptSuccess ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg :class="acceptSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="acceptSuccess" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p :class="acceptSuccess ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'" class="text-sm font-medium">
                                        {{ acceptSuccess ? 'Invitation accepted successfully!' : 'Invitation declined.' }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ acceptSuccess ? 'You are now a member of this account. Redirecting...' : 'You have declined this invitation. Redirecting...' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>