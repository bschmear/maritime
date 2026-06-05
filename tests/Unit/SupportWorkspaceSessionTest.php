<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use App\Support\SupportWorkspaceSession;
use Illuminate\Http\Request;
use Tests\TestCase;

class SupportWorkspaceSessionTest extends TestCase
{
    private function requestWithSession(): Request
    {
        $session = $this->app['session.store'];
        if (! $session->isStarted()) {
            $session->start();
        }

        $request = Request::create('/');
        $request->setLaravelSession($session);

        return $request;
    }

    private function supportUser(): User
    {
        $user = new User(['is_support' => true]);
        $user->id = 1;

        return $user;
    }

    private function account(bool $allowSupportAccess): Account
    {
        $account = new Account([
            'name' => 'Test Workspace',
            'tenant_id' => 'tenant-1',
            'allow_support_access' => $allowSupportAccess,
        ]);
        $account->id = 10;

        return $account;
    }

    public function test_allows_support_access_when_session_account_and_user_match(): void
    {
        $user = $this->supportUser();
        $account = $this->account(true);
        $request = $this->requestWithSession();
        $request->session()->put(SupportWorkspaceSession::SESSION_KEY, [
            'account_id' => $account->id,
            'tenant_id' => $account->tenant_id,
            'account_name' => $account->name,
            'user_id' => $user->id,
            'started_at' => now()->toIso8601String(),
        ]);

        $this->assertTrue(SupportWorkspaceSession::allows($request, $account, $user));
    }

    public function test_denies_support_access_when_customer_has_not_enabled_it(): void
    {
        $user = $this->supportUser();
        $account = $this->account(false);
        $request = $this->requestWithSession();
        $request->session()->put(SupportWorkspaceSession::SESSION_KEY, [
            'account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $this->assertFalse(SupportWorkspaceSession::allows($request, $account, $user));
    }

    public function test_denies_support_access_for_non_support_users(): void
    {
        $user = new User(['is_support' => false]);
        $user->id = 2;
        $account = $this->account(true);
        $request = $this->requestWithSession();
        $request->session()->put(SupportWorkspaceSession::SESSION_KEY, [
            'account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $this->assertFalse(SupportWorkspaceSession::allows($request, $account, $user));
    }
}
