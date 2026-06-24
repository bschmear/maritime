<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Integration\Support\WordPressIntegrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWordPressIntegrationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $integration = WordPressIntegrationSettings::integration();
        if ($integration === null) {
            return response()->json(['message' => 'WordPress integration is not configured.'], 404);
        }

        $settings = $integration->settings ?? [];
        $hash = $settings['helmful_api_key_hash'] ?? null;
        if (! is_string($hash) || $hash === '') {
            return response()->json(['message' => 'Helmful API key is not configured.'], 401);
        }

        $provided = $this->extractBearerToken($request);
        if ($provided === null || ! WordPressIntegrationSettings::verifyApiKey($provided, $hash)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
