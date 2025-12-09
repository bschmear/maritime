<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Tenant;
use App\Mail\AccountInvitation;
use App\Domain\User\Models\User as TenantUserModel;
use App\Domain\Role\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Inertia\Inertia;
use Exception;

class InvitationController extends Controller
{
    /**
     * Show the invitation page.
     */
    public function show(Request $request, string $token)
    {
        $invitation = Invitation::with(['account.owner', 'inviter'])
            ->where('token', $token)
            ->first();

        if (!$invitation) {
            abort(404, 'Invitation not found or has expired.');
        }

        if (!$invitation->isPending()) {
            return Inertia::render('Invitation/Expired', [
                'invitation' => $invitation,
                'accepted' => $invitation->isAccepted(),
                'declined' => $invitation->isDeclined(),
            ]);
        }

        $user = Auth::user();

        // If user is not logged in, redirect to login with invitation token
        if (!$user) {
            return redirect()->route('login', ['invitation' => $token]);
        }

        // Check if user email matches invitation email
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            Auth::logout();
            return redirect()->route('login', ['invitation' => $token])
                ->withErrors(['email' => 'Please log in with the email address that was invited.']);
        }

        // Check if user is already a member of the account
        if ($invitation->account->users()->where('users.id', $user->id)->exists()) {
            return Inertia::render('Invitation/AlreadyMember', [
                'invitation' => $invitation,
                'account' => $invitation->account,
            ]);
        }

        return Inertia::render('Invitation/Show', [
            'invitation' => $invitation,
            'account' => $invitation->account,
            'user' => $user,
        ]);
    }

    /**
     * Accept the invitation.
     */
public function accept(Request $request, string $token)
{
    $invitation = Invitation::with(['account.tenant', 'account.subscription.plan'])
        ->where('token', $token)
        ->first();

    if (!$invitation || !$invitation->isPending()) {
        return redirect()->back()->withErrors(['invitation' => 'Invitation not found or no longer valid.']);
    }

    $user = Auth::user();

    if (!$user || strtolower($user->email) !== strtolower($invitation->email)) {
        return redirect()->route('login', ['invitation' => $token])
            ->withErrors(['auth' => 'Please log in with the invited email address.']);
    }

    // Already a member?
    if ($invitation->account->users()->where('users.id', $user->id)->exists()) {
        return redirect()->back()->withErrors(['membership' => 'You are already a member of this account.']);
    }

    try {
        // Accept the invitation in your system
        $invitation->accept($user);

        // Copy user to tenant database
        if ($invitation->account->tenant) {
            $this->copyUserToTenant($user, $invitation->account->tenant, $invitation->role);
        } else {
            Log::error('No tenant found for account during invitation acceptance', [
                'account_id' => $invitation->account->id,
                'invitation_id' => $invitation->id,
            ]);
        }

        $subscription = $invitation->account->subscription;

        if ($subscription && $subscription->isActive()) {

            $account = $invitation->account;
            $owner = $account->owner;
            $cashierSub = $owner->subscription('default');

            if ($cashierSub && $cashierSub->active()) {

                // Stripe client
                $stripe = new \Stripe\StripeClient(config('cashier.secret'));

                // Count total users
                $totalUsers = $account->users()->count();

                $includedSeats = $subscription->plan->seat_limit;
                $extraSeatCount = max(0, $totalUsers - $includedSeats);

                // Determine correct extra-seat price ID
                $extraSeatPriceId = $subscription->billing_cycle === 'yearly'
                    ? config('app.extra_seats.yearly_price_id')
                    : config('app.extra_seats.monthly_price_id');

                // Fetch Stripe subscription
                $stripeSub = $cashierSub->asStripeSubscription();

                // Find existing extra-seat line item
                $extraSeatItem = collect($stripeSub->items->data)
                    ->firstWhere('price.id', $extraSeatPriceId);

                if ($extraSeatCount > 0) {

                    if ($extraSeatItem) {

                        // ✔ Update quantity of existing add-on
                        $stripe->subscriptions->update(
                            $stripeSub->id,
                            [
                                'items' => [
                                    [
                                        'id' => $extraSeatItem->id,
                                        'quantity' => (int) $extraSeatCount,
                                    ],
                                ],
                                'proration_behavior' => 'create_prorations',
                            ]
                        );

                    } else {

                        // ✔ Add new extra seat price item
                        $stripe->subscriptions->update(
                            $stripeSub->id,
                            [
                                'items' => [
                                    [
                                        'price' => $extraSeatPriceId,
                                        'quantity' => (int) $extraSeatCount,
                                    ],
                                ],
                                'proration_behavior' => 'create_prorations',
                            ]
                        );
                    }

                    Log::info('Updated extra seat quantities', [
                        'account_id' => $account->id,
                        'subscription_id' => $stripeSub->id,
                        'extra_seat_price' => $extraSeatPriceId,
                        'extra_seat_qty' => $extraSeatCount,
                    ]);

                } else {
                    // NO extra seats needed — remove if exists
                    if ($extraSeatItem) {
                        $stripe->subscriptions->update(
                            $stripeSub->id,
                            [
                                'items' => [
                                    [
                                        'id' => $extraSeatItem->id,
                                        'deleted' => true,
                                    ],
                                ],
                                'proration_behavior' => 'create_prorations',
                            ]
                        );

                        Log::info('Removed extra seat line item (no longer needed)', [
                            'account_id' => $account->id,
                            'subscription_id' => $stripeSub->id,
                        ]);
                    }
                }

                // Update internal subscription quantity to reflect total users
                $subscription->update([
                    'quantity' => $totalUsers
                ]);
            }
        }

        // Notify account owner
        try {
            $invitation->account->owner
                ->notify(new \App\Notifications\UserJoinedAccount($user, $invitation->account, $invitation->role));
        } catch (\Exception $e) {
            Log::error('Failed to send owner notification', [
                'account_id' => $invitation->account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $invitation->delete();
        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->account->name}! You have successfully joined the account as a {$invitation->role}.");

    } catch (\Exception $e) {
        Log::error('Failed to accept invitation', [
            'invitation_id' => $invitation->id,
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()->withErrors(['accept' => 'Failed to accept invitation. Please try again.']);
    }
}

    
    /**
     * Decline the invitation.
     */
    public function decline(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isPending()) {
            return redirect()->back()->withErrors(['invitation' => 'Invitation not found or no longer valid.']);
        }

        $user = Auth::user();

        if (!$user || strtolower($user->email) !== strtolower($invitation->email)) {
            return redirect()->back()->withErrors(['auth' => 'Please log in with the invited email address.']);
        }

        try {
            $invitation->decline();

            return redirect()->route('dashboard')
                ->with('info', 'Invitation declined.');

        } catch (\Exception $e) {
            \Log::error('Failed to decline invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['decline' => 'Failed to decline invitation. Please try again.']);
        }
    }

    /**
     * Resend an invitation email.
     */
    public function resend(Request $request, Invitation $invitation)
    {
        // Check if user owns the account
        if (!$this->userOwnsAccount($invitation->account)) {
            abort(403, 'You do not have permission to manage this invitation.');
        }

        if (!$invitation->isPending()) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has already been processed.']);
        }

        try {
            // Send the invitation email again
            Mail::to($invitation->email)->send(new AccountInvitation($invitation, $invitation->account, Auth::user()));

            return redirect()->back()->with('success', 'Invitation resent successfully!');
        } catch (\Exception $e) {
            \Log::error('Failed to resend invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['email' => 'Failed to resend invitation. Please try again.']);
        }
    }

    /**
     * Delete an invitation.
     */
    public function destroy(Request $request, Invitation $invitation)
    {
        // Check if user owns the account
        if (!$this->userOwnsAccount($invitation->account)) {
            abort(403, 'You do not have permission to manage this invitation.');
        }

        try {
            $invitation->delete();

            return response()->json(['message' => 'Invitation deleted successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to delete invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to delete invitation.'], 500);
        }
    }

    /**
     * Check if the authenticated user owns the account.
     */
    private function userOwnsAccount(Account $account): bool
    {
        return $account->owner_id === Auth::id();
    }

    /**
     * Copy the authenticated user to the tenant's users table.
     */
    private function copyUserToTenant(\App\Models\User $user, \App\Models\Tenant $tenant, string $invitationRole): void
    {
        try {

            // Check if tenant exists
            if (!$tenant) {
                throw new Exception('Tenant not found');
            }

            // Check if tenant has domains
            if ($tenant->domains()->count() === 0) {
                throw new Exception('Tenant has no domains configured');
            }

            // Verify tenant schema exists
            $schemaName = 'tenant' . $tenant->id;
            $centralConnection = DB::connection('pgsql');
            $schemaExists = $centralConnection->select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schemaName]
            );

            if (empty($schemaExists)) {
                Log::error('Tenant schema does not exist', [
                    'tenant_id' => $tenant->id,
                    'schema_name' => $schemaName,
                ]);
                throw new Exception("Tenant schema {$schemaName} does not exist");
            }

            // Switch to tenant context
            tenancy()->initialize($tenant);

            Log::info('Tenancy initialized', [
                'tenant_id' => $tenant->id,
                'current_database' => config('database.connections.pgsql.database'),
                'tenant_database' => tenancy()->getTenantDatabaseName(),
                'tenancy_active' => tenancy()->isActive(),
            ]);

            // Ensure tenant database is set up
            try {
                // Check if we can connect to tenant database
                DB::connection()->getPdo();
                Log::info('Tenant database connection successful');
            } catch (Exception $e) {
                Log::error('Tenant database connection failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('Cannot connect to tenant database');
            }

            // Verify we're in the right tenant context
            $currentConnection = DB::getDefaultConnection();
            Log::info('Database connection check', [
                'default_connection' => $currentConnection,
                'pgsql_database' => config("database.connections.{$currentConnection}.database"),
            ]);

            // Check if users table exists in tenant schema
            try {
                $tableExists = DB::select("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users')", [$schemaName]);
                Log::info('Users table check', [
                    'schema' => $schemaName,
                    'table_exists' => !empty($tableExists) && $tableExists[0]->exists,
                ]);

                if (empty($tableExists) || !$tableExists[0]->exists) {
                    throw new Exception("Users table does not exist in tenant schema {$schemaName}");
                }

            } catch (Exception $e) {
                Log::error('Failed to check users table', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Check if user already exists in tenant
            $existingTenantUser = TenantUserModel::where('email', $user->email)->first();
            if ($existingTenantUser) {
                Log::info('User already exists in tenant database', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'existing_tenant_user_id' => $existingTenantUser->id,
                ]);
                return; // User already exists, skip creation
            }

            // Map invitation role to tenant role (needs to be done in tenant context)
            $tenantRoleId = $this->getTenantRoleId($invitationRole);

            Log::info('Creating tenant user', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'email' => $user->email,
                'role_id' => $tenantRoleId,
            ]);

            // Create tenant user using raw SQL to ensure it works
            try {
                DB::insert(
                    'INSERT INTO users (display_name, first_name, last_name, email, current_role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        trim($user->first_name . ' ' . $user->last_name),
                        $user->first_name,
                        $user->last_name,
                        $user->email,
                        $tenantRoleId,
                    ]
                );

                Log::info('Tenant user created with raw SQL', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'email' => $user->email,
                ]);

            } catch (Exception $e) {
                Log::error('Raw SQL insert failed, trying Eloquent', [
                    'error' => $e->getMessage(),
                ]);

                // Fallback to Eloquent
                $tenantUser = TenantUserModel::create([
                    'display_name' => trim($user->first_name . ' ' . $user->last_name),
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'current_role' => $tenantRoleId,
                ]);

                Log::info('Tenant user created with Eloquent fallback', [
                    'user_id' => $user->id,
                    'tenant_user_id' => $tenantUser->id,
                ]);
            }

            Log::info('User copied to tenant via invitation', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $tenantUser->id,
                'email' => $user->email,
                'invitation_role' => $invitationRole,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to copy user to tenant via invitation', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw exception - invitation acceptance should continue
        } finally {
            // Reset tenancy context
            tenancy()->end();
            Log::info('Tenancy ended');
        }
    }

    /**
     * Map invitation role to tenant role ID.
     * Note: This method should be called within the tenant context.
     */
    private function getTenantRoleId(string $invitationRole): ?int
    {
        try {
            // Map common role names to tenant role slugs
            $roleMapping = [
                'admin' => 'admin',
                'manager' => 'manager',
                'member' => 'user',
                'user' => 'user',
                'editor' => 'user',
                'viewer' => 'user',
            ];

            $tenantRoleSlug = $roleMapping[strtolower($invitationRole)] ?? 'user';

            $role = Role::where('slug', $tenantRoleSlug)->first();

            Log::info('Mapped invitation role to tenant role', [
                'invitation_role' => $invitationRole,
                'tenant_role_slug' => $tenantRoleSlug,
                'tenant_role_id' => $role?->id,
            ]);

            return $role?->id;
        } catch (Exception $e) {
            Log::error('Failed to get tenant role ID for invitation', [
                'invitation_role' => $invitationRole,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
