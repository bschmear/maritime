<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    accounts: {
        type: Array,
        default: () => [],
    },
    pending_invitations: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showModal = ref(false);
const modalType = ref('success'); // 'success' or 'error'
const modalTitle = ref('');
const modalMessage = ref('');
const confirmAction = ref(null);
const isConfirmModal = ref(false);
const isProcessing = ref(false);

const getTenantUrl = (domain) => {
    if (!domain) return null;
    const protocol = window.location.protocol;
    return `${protocol}//${domain}`;
};

// Show confirmation modal
const showConfirmModal = (title, message, onConfirm) => {
    modalTitle.value = title;
    modalMessage.value = message;
    confirmAction.value = onConfirm;
    isConfirmModal.value = true;
    showModal.value = true;
};

// Show result modal
const showResultModal = (type, title, message, shouldReload = false) => {
    modalType.value = type;
    modalTitle.value = title;
    modalMessage.value = message;
    isConfirmModal.value = false;
    showModal.value = true;

    if (shouldReload) {
        setTimeout(() => {
            router.reload({ only: ['accounts', 'pending_invitations'] });
            closeModal();
        }, 2000);
    }
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    confirmAction.value = null;
    isProcessing.value = false;
};

// Handle confirmation
const handleConfirm = () => {
    if (confirmAction.value && !isProcessing.value) {
        isProcessing.value = true;
        confirmAction.value();
    }
};

// Handle invitation acceptance
const acceptInvitation = (invitation) => {
    showConfirmModal(
        'Accept Invitation',
        `Are you sure you want to accept the invitation to join ${invitation.account.name} as a ${invitation.role}?`,
        () => {
            router.post(route('invitations.accept', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Invitation Accepted!',
                        `Welcome to ${invitation.account.name}! You have successfully joined the account.`,
                        true
                    );
                },
                onError: (errors) => {
                    console.error('Error accepting invitation:', errors);
                    const errorMessage = errors?.stripe?.[0] ||
                                       errors?.plan?.[0] ||
                                       errors?.seats?.[0] ||
                                       errors?.accept?.[0] ||
                                       'Failed to accept invitation. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    // Don't set isProcessing to false here, let the success/error handlers do it
                }
            });
        }
    );
};

// Handle invitation decline
const declineInvitation = (invitation) => {
    showConfirmModal(
        'Decline Invitation',
        `Are you sure you want to decline the invitation to join ${invitation.account.name}?`,
        () => {
            router.post(route('invitations.decline', invitation.token), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    showResultModal(
                        'success',
                        'Invitation Declined',
                        'The invitation has been declined.',
                        true
                    );
                },
                onError: (errors) => {
                    console.error('Error declining invitation:', errors);
                    const errorMessage = errors?.decline?.[0] || 'Failed to decline invitation. Please try again.';
                    showResultModal('error', 'Error', errorMessage);
                    isProcessing.value = false;
                },
                onFinish: () => {
                    // Don't set isProcessing to false here, let the success/error handlers do it
                }
            });
        }
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-12 bg-gray-50 dark:bg-gray-900 grow">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Welcome Banner -->
                <div class="mb-8 overflow-hidden bg-gradient-to-r from-primary-500 to-secondary-500 rounded-2xl shadow-lg">
                    <div class="p-8 sm:p-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold text-white mb-2">
                                    Welcome back! 👋
                                </h3>
                                <p class="text-secondary-100 text-sm sm:text-base">
                                    Manage your accounts and access your tenant applications
                                </p>
                            </div>
                            <div class="hidden sm:block">
                                <svg class="w-24 h-24 text-white opacity-20" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Invitations Section -->
                <div v-if="pending_invitations.length > 0" class="mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-blue-200 dark:border-blue-800">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Account Invitations
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        You have {{ pending_invitations.length }} pending invitation{{ pending_invitations.length === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                    {{ pending_invitations.length }} pending
                                </span>
                            </div>

                            <div class="space-y-4">
                                <div
                                    v-for="invitation in pending_invitations"
                                    :key="invitation.id"
                                    class="border border-blue-200 dark:border-blue-700 rounded-lg p-4 bg-blue-50 dark:bg-blue-900/20"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    Invited to {{ invitation.account.name }}
                                                </h4>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <span class="font-medium">Role:</span> {{ invitation.role }}
                                                    </p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <span class="font-medium">Invited by:</span>
                                                        {{ invitation.inviter?.name || 'Unknown' }}
                                                        <span v-if="invitation.inviter?.email">
                                                            ({{ invitation.inviter.email }})
                                                        </span>
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <span class="font-medium">Account Owner:</span> {{ invitation.account.owner }}
                                                    </p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <span class="font-medium">Invited:</span>
                                                        {{ new Date(invitation.created_at).toLocaleDateString() }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex space-x-3 ml-4">
                                            <button
                                                @click="acceptInvitation(invitation)"
                                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                            >
                                                Accept
                                            </button>
                                            <button
                                                @click="declineInvitation(invitation)"
                                                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                            >
                                                Decline
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounts Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Your Accounts
                        </h3>
                        <Link
                            href="/pricing"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            Create New Account
                        </Link>
                    </div>

                    <div v-if="accounts.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                            <p class="mb-4">You don't have any accounts yet.</p>
                            <Link
                                href="/pricing"
                                class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700"
                            >
                                Get Started
                            </Link>
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow"
                        >
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                            {{ account.name }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" v-if="account.is_owner">
                                                Owner
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:text-blue-200 dark:bg-blue-900 dark:bg-blue-200" v-if="!account.is_owner">
                                                {{ account.user_role }}
                                            </span>
                                            <span>•</span>
                                            <span>{{ account.users_count }} {{ account.users_count === 1 ? 'user' : 'users' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <!-- Tenant Link -->
                                    <div v-if="account.domain" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tenant URL</p>
                                            <p class="text-sm font-mono text-gray-900 dark:text-white truncate">{{ account.domain }}</p>
                                        </div>
                                        <a
                                            :href="getTenantUrl(account.domain)"
                                            target="_blank"
                                            class="ml-3 inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                                        >
                                            Open
                                            <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    </div>

                                    <!-- Owner Actions -->
                                    <div v-if="account.is_owner" class="space-y-2">
                                        <Link
                                            :href="route('accounts.show', account.id)"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        >
                                            Manage Account
                                        </Link>

                                        <button
                                            @click="cancelAccount(account)"
                                            class="w-full px-3 py-2 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-800 rounded-md hover:bg-red-100 dark:hover:bg-red-900/40 text-center"
                                        >
                                            Cancel Subscription
                                        </button>
                                    </div>

                                    <!-- Member View -->
                                    <div v-else class="text-xs text-gray-500 dark:text-gray-400">
                                        You are a {{ account.user_role }} of this account
                                    </div>
                                </div>

                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Created {{ new Date(account.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flowbite Modal -->
        <div v-if="showModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full bg-gray-900 bg-opacity-50">
            <div class="relative w-full max-w-md max-h-full">
                <!-- Modal content -->
                <div class="relative bg-white rounded-lg shadow dark:bg-gray-800">
                    <!-- Modal header -->
                    <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600"
                         :class="isConfirmModal ? 'bg-gray-50 dark:bg-gray-700' : modalType === 'success' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                        <div class="flex items-center space-x-3">
                            <!-- Success Icon -->
                            <div v-if="!isConfirmModal && modalType === 'success'" class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <!-- Error Icon -->
                            <div v-else-if="!isConfirmModal && modalType === 'error'" class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <!-- Confirm Icon -->
                            <div v-else class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ modalTitle }}
                            </h3>
                        </div>
                        <button
                            @click="closeModal"
                            type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        >
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                            </svg>
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>
                    <!-- Modal body -->
                    <div class="p-4 md:p-5 space-y-4">
                        <p class="text-base leading-relaxed text-gray-700 dark:text-gray-300">
                            {{ modalMessage }}
                        </p>
                    </div>
                    <!-- Modal footer -->
                    <div class="flex items-center justify-end p-4 md:p-5 space-x-3 border-t border-gray-200 rounded-b dark:border-gray-600">
                        <button
                            v-if="isConfirmModal"
                            @click="closeModal"
                            type="button"
                            class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            v-if="isConfirmModal"
                            @click="handleConfirm"
                            :disabled="isProcessing"
                            type="button"
                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center"
                        >
                            <svg v-if="isProcessing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isProcessing ? 'Processing...' : 'Confirm' }}
                        </button>
                        <button
                            v-else
                            @click="closeModal"
                            type="button"
                            class="text-white focus:ring-4 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                            :class="modalType === 'success' 
                                ? 'bg-green-700 hover:bg-green-800 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800' 
                                : 'bg-red-700 hover:bg-red-800 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800'"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
