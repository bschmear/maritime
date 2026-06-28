<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OpenAi\OpenAiModelResolver;
use App\Support\OpenAi\OpenAiRequestType;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OpenAiModelResolverTest extends TestCase
{
    public function test_resolves_configured_models_by_request_type(): void
    {
        Config::set('openai_models.boat_specs', 'gpt-boat');
        Config::set('openai_models.document_extract', 'gpt-doc');
        Config::set('openai_models.messy_ocr', 'gpt-full');

        $this->assertSame('gpt-boat', OpenAiModelResolver::resolve(OpenAiRequestType::BoatSpecs));
        $this->assertSame('gpt-doc', OpenAiModelResolver::resolve(OpenAiRequestType::DocumentExtract));
        $this->assertSame('gpt-full', OpenAiModelResolver::resolve(OpenAiRequestType::MessyOcr));
    }

    public function test_invoice_pdf_text_uses_messy_ocr_when_text_is_too_short(): void
    {
        Config::set('openai_models.invoice_min_text_length', 80);
        Config::set('openai_models.document_extract', 'gpt-doc');
        Config::set('openai_models.messy_ocr', 'gpt-full');

        $short = str_repeat('x', 40);
        $long = str_repeat('y', 120);

        $this->assertSame('gpt-full', OpenAiModelResolver::forInvoicePdfText($short));
        $this->assertSame('gpt-doc', OpenAiModelResolver::forInvoicePdfText($long));
    }

    public function test_sanitize_chat_payload_strips_temperature_for_gpt_5_models(): void
    {
        $payload = OpenAiModelResolver::sanitizeChatPayload([
            'model' => 'gpt-5-mini',
            'temperature' => 0,
            'messages' => [],
        ]);

        $this->assertArrayNotHasKey('temperature', $payload);

        $kept = OpenAiModelResolver::sanitizeChatPayload([
            'model' => 'gpt-4o-mini',
            'temperature' => 0,
            'messages' => [],
        ]);

        $this->assertSame(0, $kept['temperature']);
    }
}
