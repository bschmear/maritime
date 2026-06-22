<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\BoatMake\Models\BoatMake;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class InvoiceInstructionsGeneratorService
{
    public function generate(string $pdfText, BoatMake $brand): string
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = (string) config('invoice_import.ai_model', 'gpt-4o-mini');

        $userPayload = json_encode([
            'brand' => $brand->display_name,
            'pdf_text' => $pdfText,
        ], JSON_THROW_ON_ERROR);

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'invoice_import_instructions',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userPayload],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InvoiceInstructionsGeneratorService OpenAI call failed', [
                'boat_make_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('AI could not draft invoice parsing instructions. Please write them yourself.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new RuntimeException('Empty response when drafting invoice instructions.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $instructions = trim((string) ($decoded['instructions'] ?? ''));

        if ($instructions === '') {
            throw new RuntimeException('AI returned empty invoice instructions. Please write them yourself.');
        }

        return $instructions;
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You write reusable parsing instructions for importing marine manufacturer invoice PDFs into inventory.

The instructions will be saved per dealer (tenant) and per brand, then reused on future imports.

From the sample invoice text provided, describe:
- Which header fields matter (invoice number, date, vendor cues)
- Line table columns and how to read them
- How to expand quantity into one row per physical unit
- Where HIN, serial number, model codes, descriptions, and unit cost appear
- Rows or sections to ignore (tax, freight, totals, footers)
- Deduplication: each HIN and serial number must map to at most one unit row — if the document repeats the same identifier, instruct the extractor to keep the first occurrence only
- Brand-specific quirks (identifier formats, item code patterns)

Write in plain English for a downstream extraction AI. Be specific to this invoice layout but avoid one-off values (use patterns, not single invoice numbers).

Return only the instructions text in the JSON field — no markdown fences.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['instructions'],
            'properties' => [
                'instructions' => ['type' => 'string'],
            ],
        ];
    }
}
