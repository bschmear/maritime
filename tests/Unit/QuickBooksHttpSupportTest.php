<?php

namespace Tests\Unit;

use App\Services\Payments\QuickBooksHttpSupport;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksHttpSupportTest extends TestCase
{
    #[Test]
    public function extracts_intuit_tid_from_intuit_tid_header(): void
    {
        $response = $this->responseWithHeaders(['intuit_tid' => 'tid-abc-123']);

        $this->assertSame('tid-abc-123', QuickBooksHttpSupport::intuitTid($response));
    }

    #[Test]
    public function extracts_intuit_tid_from_intuit_tid_hyphenated_header(): void
    {
        $response = $this->responseWithHeaders(['intuit-tid' => 'tid-hyphen']);

        $this->assertSame('tid-hyphen', QuickBooksHttpSupport::intuitTid($response));
    }

    #[Test]
    public function with_intuit_tid_merges_into_log_context(): void
    {
        $response = $this->responseWithHeaders(['Intuit-Tid' => 'support-me']);

        $context = QuickBooksHttpSupport::withIntuitTid($response, ['status' => 400]);

        $this->assertSame(400, $context['status']);
        $this->assertSame('support-me', $context['intuit_tid']);
    }

    #[Test]
    public function returns_null_when_header_missing(): void
    {
        $response = $this->responseWithHeaders([]);

        $this->assertNull(QuickBooksHttpSupport::intuitTid($response));
        $this->assertSame(['foo' => 'bar'], QuickBooksHttpSupport::withIntuitTid($response, ['foo' => 'bar']));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function responseWithHeaders(array $headers): Response
    {
        Http::fake([
            'https://example.test/*' => Http::response('{}', 200, $headers),
        ]);

        return Http::get('https://example.test/qbo');
    }
}
