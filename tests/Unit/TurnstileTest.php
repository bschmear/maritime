<?php

namespace Tests\Unit;

use App\Support\Turnstile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    #[Test]
    public function verify_passes_when_not_configured(): void
    {
        config([
            'services.turnstile.site_key' => '',
            'services.turnstile.secret_key' => '',
        ]);

        $this->assertFalse(Turnstile::isConfigured());
        $this->assertTrue(Turnstile::verify(null));
        $this->assertTrue(Turnstile::verify(''));
    }

    #[Test]
    public function verify_calls_siteverify_when_configured(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(Turnstile::verify('token-abc', '203.0.113.1'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === 'secret-key'
                && $request['response'] === 'token-abc'
                && $request['remoteip'] === '203.0.113.1';
        });
    }

    #[Test]
    public function verify_returns_false_when_siteverify_rejects_token(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->assertFalse(Turnstile::verify('bad-token'));
    }
}
