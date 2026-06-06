<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appName = computed(() => page.props.app.name);

const props = defineProps({
    /** Payload from UserController (null if no tenant account on request). */
    workspaceTeam: {
        type: Object,
        default: null,
    },
    /** When true, show a short hint that the profile must still be invited on the central account. */
    isCreatePage: {
        type: Boolean,
        default: false,
    },
});

const su = computed(() => props.workspaceTeam?.seat_usage ?? null);

const staff = computed(() => props.workspaceTeam?.staff_invite ?? null);

const plan = computed(() => props.workspaceTeam?.billing_plan ?? null);

const showUnlimitedCopy = computed(
    () => plan.value && plan.value.seat_extra != null && Number(plan.value.seat_extra) > 0,
);

const needsLoginHelp = computed(() => {
    const s = staff.value;
    if (!s) {
        return false;
    }
    if (s.pending_invitation) {
        return true;
    }
    if (!s.on_account) {
        return true;
    }
    return false;
});

const showSeatUsage = computed(() => Boolean(props.workspaceTeam?.viewer_can_manage_billing_seats && su.value));

const showBanner = computed(() => {
    if (!props.workspaceTeam) {
        return false;
    }
    if (props.isCreatePage && !staff.value) {
        return true;
    }
    if (showSeatUsage.value) {
        return true;
    }
    if (staff.value && (needsLoginHelp.value || staff.value.on_account)) {
        return true;
    }

    return false;
});
</script>

<template>
    <div v-if="showBanner" class="space-y-4">
        <div
            v-if="isCreatePage && !staff"
            class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-100"
        >
            <p class="font-medium">Before they can log in</p>
            <p class="mt-1">
                Creating a user here adds them to your tenant directory only. Use the same email under your {{ appName }} account
                <strong>Team members</strong>
                (invite) so they receive access to this workspace.
            </p>
        </div>
        <!-- Seat usage (account owner or tenant admin only) -->
        <div
            v-if="showSeatUsage"
            class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <div class="min-w-0 flex-1 text-sm text-blue-900 dark:text-blue-100">
                    <p class="font-semibold">Workspace team seats</p>
                    <p class="mt-1 text-blue-800 dark:text-blue-200">
                        Your {{ appName }} account is using
                        <strong>{{ su.current_users }}</strong>
                        of
                        <strong>{{ su.seat_limit }}</strong>
                        included seat{{ su.seat_limit === 1 ? '' : 's' }}.
                        <span v-if="su.over_limit > 0" class="block mt-1">
                            {{ su.over_limit }} seat{{ su.over_limit === 1 ? '' : 's' }} beyond the included amount add about
                            <strong>${{ Number(workspaceTeam.extra_seat_monthly_price || 15).toFixed(2) }}</strong>
                            /month each (see your account for exact billing).
                        </span>
                    </p>
                    <div
                        v-if="showUnlimitedCopy"
                        class="mt-3 rounded-lg border border-blue-200 bg-white/80 p-3 text-xs text-blue-800 dark:border-blue-700 dark:bg-gray-900/40 dark:text-blue-200"
                    >
                        <p class="font-medium">Unlimited team growth</p>
                        <p class="mt-1">
                            You can invite more team members. Additional users beyond your {{ plan.seat_limit }} included seats are charged
                            ${{ plan.seat_extra }}/month each.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login / invite (per tenant staff email) -->
        <div v-if="staff && needsLoginHelp" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div class="min-w-0 flex-1 text-sm text-amber-950 dark:text-amber-100">
                    <template v-if="staff.pending_invitation">
                        <p class="font-semibold">Invitation pending</p>
                        <p class="mt-1">
                            An invitation has already been sent to <strong>{{ staff.email }}</strong>. They must accept it before they can
                            sign in to this workspace.
                        </p>
                    </template>
                    <template v-else>
                        <p class="font-semibold">Not on your {{ appName }} workspace yet</p>
                        <p class="mt-1">
                            This person is saved in your tenant directory, but <strong>{{ staff.email }}</strong> is not yet a team member
                            on your {{ appName }} account — so they cannot log in to this site until invited.
                        </p>
                    </template>

                    <div v-if="workspaceTeam.viewer_is_account_owner" class="mt-4 flex flex-wrap items-center gap-3">
                        <a
                            :href="workspaceTeam.account_show_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800 dark:bg-amber-600 dark:hover:bg-amber-500"
                        >
                            Open team &amp; invitations
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                        <span class="text-xs text-amber-800 dark:text-amber-200">Use “Invite team member” with this email.</span>
                    </div>
                    <p v-else class="mt-3 text-xs text-amber-900/90 dark:text-amber-200/90">
                        Only the {{ appName }} account owner can send invitations. Ask the owner to add
                        <strong>{{ staff.email }}</strong>
                        under Account → Team members.
                    </p>
                </div>
            </div>
        </div>

        <div
            v-else-if="staff && staff.on_account"
            class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-800 dark:bg-green-900/20 dark:text-green-100"
        >
            <p class="font-medium">Workspace access</p>
            <p class="mt-1">This email is on your {{ appName }} account, so they can sign in to this workspace (subject to their password and invitation state).</p>
        </div>
    </div>
</template>
