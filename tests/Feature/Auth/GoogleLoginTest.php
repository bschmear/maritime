<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Google\GoogleLoginService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.login_redirect_uri' => 'https://oauth.example.test/auth/google/callback',
            'app.url' => 'https://maritime.test',
            'app.domain' => 'maritime.test',
        ]);
    }

    public function test_google_redirect_stores_handoff_and_redirects_to_google(): void
    {
        $response = $this->get('https://maritime.test/'.route('google.login.redirect', [], false));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
        $this->assertSame(1, DB::table('google_login_handoffs')->count());
    }

    public function test_google_callback_completes_login_on_return_origin_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'google_id' => null,
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-user-123',
                'email' => 'staff@example.com',
                'given_name' => 'Staff',
                'family_name' => 'Member',
                'verified_email' => true,
            ]),
        ]);

        $handoffId = (string) Str::uuid();

        DB::table('google_login_handoffs')->insert([
            'id' => $handoffId,
            'return_origin' => 'https://maritime.test',
            'invitation_token' => null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $callbackResponse = $this->get('https://oauth.example.test/'.route('google.login.callback', [
            'code' => 'auth-code',
            'state' => $handoffId,
        ], false));

        $callbackResponse->assertRedirect('https://maritime.test/auth/google/complete/'.$handoffId);

        $completeResponse = $this->get('https://maritime.test/'.route('google.login.complete', ['handoff' => $handoffId], false));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-user-123', $user->fresh()->google_id);
        $completeResponse->assertStatus(409);
        $completeResponse->assertHeader('X-Inertia-Location', route('dashboard', absolute: true));
        $this->assertDatabaseMissing('google_login_handoffs', ['id' => $handoffId]);
    }

    public function test_google_callback_logs_in_existing_user_by_google_id(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'google_id' => 'google-user-123',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-user-123',
                'email' => 'staff@example.com',
                'verified_email' => true,
            ]),
        ]);

        $handoffId = (string) Str::uuid();

        DB::table('google_login_handoffs')->insert([
            'id' => $handoffId,
            'return_origin' => 'https://maritime.test',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('https://oauth.example.test/'.route('google.login.callback', [
            'code' => 'auth-code',
            'state' => $handoffId,
        ], false));

        $this->get('https://maritime.test/'.route('google.login.complete', ['handoff' => $handoffId], false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_google_callback_creates_user_when_no_match_exists(): void
    {
        Event::fake([Registered::class]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-user-new',
                'email' => 'newuser@example.com',
                'given_name' => 'New',
                'family_name' => 'User',
                'verified_email' => true,
            ]),
        ]);

        $handoffId = (string) Str::uuid();

        DB::table('google_login_handoffs')->insert([
            'id' => $handoffId,
            'return_origin' => 'https://maritime.test',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('https://oauth.example.test/'.route('google.login.callback', [
            'code' => 'auth-code',
            'state' => $handoffId,
        ], false));

        $this->get('https://maritime.test/'.route('google.login.complete', ['handoff' => $handoffId], false));

        $user = User::query()->where('email', 'newuser@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('google-user-new', $user->google_id);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(Registered::class);
    }

    public function test_google_login_service_links_existing_email_without_duplicate_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $service = app(GoogleLoginService::class);

        $resolved = $service->resolveUser([
            'id' => 'google-abc',
            'email' => 'existing@example.com',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'verified' => true,
        ]);

        $this->assertTrue($resolved->is($existing));
        $this->assertSame('google-abc', $resolved->google_id);
        $this->assertNotNull($resolved->email_verified_at);
        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
    }
}
