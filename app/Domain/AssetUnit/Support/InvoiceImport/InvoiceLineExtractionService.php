<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\BoatMake\Models\BoatMake;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class InvoiceLineExtractionService
{
    /**
     * @param  list<array<string, mixed>>  $catalog
     * @return array{
     *   invoice_number: ?string,
     *   invoice_date: ?string,
     *   line_items: list<array<string, mixed>>,
     *   invoice_lines: list<array<string, mixed>>
     * }
     */
    public function extract(string $pdfText, BoatMake $brand, ?string $aiInstructions = null, array $catalog = []): array
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $instructions = trim((string) ($aiInstructions ?? ''));
        if ($instructions === '') {
            $instructions = trim((string) ($brand->invoiceImportProfile?->ai_instructions ?? ''));
        }
        if ($instructions === '') {
            throw new RuntimeException('Invoice parsing instructions are required. Add instructions or let AI draft them from your uploaded PDF.');
        }

        $model = (string) config('invoice_import.ai_model', 'gpt-4o-mini');

        $userPayload = json_encode([
            'brand' => $brand->display_name,
            'pdf_text' => $pdfText,
            'catalog' => $catalog,
        ], JSON_THROW_ON_ERROR);

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'invoice_unit_extraction',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($instructions)],
                    ['role' => 'user', 'content' => $userPayload],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InvoiceLineExtractionService OpenAI call failed', [
                'boat_make_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('AI invoice extraction failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new RuntimeException('Empty response from AI invoice extraction.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid AI invoice extraction response shape.');
        }

        return $this->normalize($decoded);
    }

    protected function systemPrompt(string $brandInstructions): string
    {
        $base = <<<'PROMPT'
You extract inventory unit rows from marine manufacturer invoice PDF text.

Rules:
- Return JSON only matching the schema.
- line_items: one entry per physical unit (expand quantity).
- invoice_lines: aggregated invoice rows (qty, description, unit price, extension) for billing — do not expand quantity here.
- hin: hull ID when present in parentheses on invoice lines.
- serial_number: only when clearly labeled separately from HIN.
- unit_price: per-unit dealer cost.
- invoice_date: YYYY-MM-DD when parseable.
- Ignore tax, freight, payment, subtotal/total summary rows.
- Do not duplicate units: each physical unit must appear once in line_items.
- If the same HIN or serial number appears more than once in the document, include only the first occurrence and omit later duplicates.
- When quantity expansion would reuse the same HIN for multiple rows, output one row for that HIN only.

Catalog matching:
- The user message includes catalog: this brand's assets (models) and variants with asset_id and asset_variant_id.
- For each line_items row, set asset_id and asset_variant_id to the best catalog match using item_code, description, extracted_model, and extracted_variant.
- When an asset has_variants is true, asset_variant_id is required whenever asset_id is set.
- When has_variants is false, asset_variant_id must be null.
- Use null for asset_id when no reasonable catalog match exists.
- mapping_confidence: 0.0–1.0 for catalog match certainty.

Brand-specific instructions:
PROMPT;

        return $base."\n".$brandInstructions;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInt = ['type' => ['integer', 'null']];
        $unitItem = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'source_line_index', 'item_code', 'description', 'extracted_model',
                'extracted_variant', 'unit_price', 'hin', 'serial_number',
                'asset_id', 'asset_variant_id', 'mapping_confidence',
            ],
            'properties' => [
                'source_line_index' => ['type' => 'integer'],
                'item_code' => $nullableString,
                'description' => $nullableString,
                'extracted_model' => $nullableString,
                'extracted_variant' => $nullableString,
                'unit_price' => ['type' => 'number'],
                'hin' => $nullableString,
                'serial_number' => $nullableString,
                'asset_id' => $nullableInt,
                'asset_variant_id' => $nullableInt,
                'mapping_confidence' => ['type' => 'number'],
            ],
        ];

        $invoiceLine = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['source_line_index', 'item_code', 'description', 'quantity', 'unit_price', 'extension'],
            'properties' => [
                'source_line_index' => ['type' => 'integer'],
                'item_code' => $nullableString,
                'description' => $nullableString,
                'quantity' => ['type' => 'number'],
                'unit_price' => ['type' => 'number'],
                'extension' => ['type' => 'number'],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['invoice_number', 'invoice_date', 'line_items', 'invoice_lines'],
            'properties' => [
                'invoice_number' => $nullableString,
                'invoice_date' => $nullableString,
                'line_items' => [
                    'type' => 'array',
                    'items' => $unitItem,
                ],
                'invoice_lines' => [
                    'type' => 'array',
                    'items' => $invoiceLine,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{
     *   invoice_number: ?string,
     *   invoice_date: ?string,
     *   line_items: list<array<string, mixed>>,
     *   invoice_lines: list<array<string, mixed>>
     * }
     */
    protected function normalize(array $decoded): array
    {
        $normalize = fn (mixed $value): ?string => ($s = trim((string) ($value ?? ''))) !== '' ? $s : null;

        $lineItems = [];
        foreach ((array) ($decoded['line_items'] ?? []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $lineItems[] = [
                'row_index' => $index,
                'source_line_index' => (int) ($row['source_line_index'] ?? 0),
                'item_code' => $normalize($row['item_code'] ?? null),
                'description' => $normalize($row['description'] ?? null),
                'extracted_model' => $normalize($row['extracted_model'] ?? null),
                'extracted_variant' => $normalize($row['extracted_variant'] ?? null),
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                'hin' => $normalize($row['hin'] ?? null),
                'serial_number' => $normalize($row['serial_number'] ?? null),
                'asset_id' => isset($row['asset_id']) && $row['asset_id'] !== null ? (int) $row['asset_id'] : null,
                'asset_variant_id' => isset($row['asset_variant_id']) && $row['asset_variant_id'] !== null
                    ? (int) $row['asset_variant_id']
                    : null,
                'mapping_confidence' => round((float) ($row['mapping_confidence'] ?? 0), 2),
            ];
        }

        $invoiceLines = [];
        foreach ((array) ($decoded['invoice_lines'] ?? []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $invoiceLines[] = [
                'source_line_index' => (int) ($row['source_line_index'] ?? $index),
                'item_code' => $normalize($row['item_code'] ?? null),
                'description' => $normalize($row['description'] ?? null),
                'quantity' => round((float) ($row['quantity'] ?? 0), 2),
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                'extension' => round((float) ($row['extension'] ?? 0), 2),
            ];
        }

        return [
            'invoice_number' => $normalize($decoded['invoice_number'] ?? null),
            'invoice_date' => $normalize($decoded['invoice_date'] ?? null),
            'line_items' => $this->dedupeLineItems($lineItems),
            'invoice_lines' => $invoiceLines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @return list<array<string, mixed>>
     */
    protected function dedupeLineItems(array $lineItems): array
    {
        $seenHins = [];
        $seenSerials = [];
        $deduped = [];

        foreach ($lineItems as $item) {
            $hin = strtoupper(trim((string) ($item['hin'] ?? '')));
            $serial = strtoupper(trim((string) ($item['serial_number'] ?? '')));

            if ($hin !== '') {
                if (isset($seenHins[$hin])) {
                    continue;
                }
                $seenHins[$hin] = true;
            } elseif ($serial !== '') {
                if (isset($seenSerials[$serial])) {
                    continue;
                }
                $seenSerials[$serial] = true;
            }

            $deduped[] = $item;
        }

        foreach ($deduped as $index => &$item) {
            $item['row_index'] = $index;
        }
        unset($item);

        return $deduped;
    }
}
