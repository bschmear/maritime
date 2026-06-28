<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\BoatMake\Models\BoatMake;
use App\Support\OpenAi\OpenAiModelResolver;
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

        $pdfText = $this->ensureUtf8($pdfText);

        $instructions = trim((string) ($aiInstructions ?? ''));
        if ($instructions === '') {
            $instructions = trim((string) ($brand->invoiceImportProfile?->ai_instructions ?? ''));
        }
        if ($instructions === '') {
            throw new RuntimeException('Invoice parsing instructions are required. Add instructions or let AI draft them from your uploaded PDF.');
        }

        $model = OpenAiModelResolver::forInvoicePdfText($pdfText);

        Log::info('InvoiceLineExtractionService: starting OpenAI extraction', [
            'boat_make_id' => $brand->id,
            'model' => $model,
            'pdf_text_length' => mb_strlen($pdfText),
            'catalog_assets' => count($catalog),
        ]);

        try {
            $userPayload = json_encode([
                'brand' => $brand->display_name,
                'pdf_text' => $pdfText,
                'catalog' => $catalog,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException $e) {
            throw new RuntimeException('Invoice text could not be encoded for AI processing. Try re-uploading the PDF.', 0, $e);
        }

        try {
            $response = OpenAI::chat()->create(OpenAiModelResolver::sanitizeChatPayload([
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
            ]));
        } catch (\Throwable $e) {
            report($e);
            Log::error('InvoiceLineExtractionService OpenAI call failed', [
                'boat_make_id' => $brand->id,
                'model' => $model,
                'pdf_text_length' => mb_strlen($pdfText),
                'catalog_assets' => count($catalog),
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

        $normalized = $this->normalize($decoded);

        $normalized['line_items'] = (new InvoiceLineItemReconciler)->reconcile(
            $normalized['line_items'],
            $normalized['invoice_lines'],
            $pdfText,
        );

        $normalized['line_items'] = $this->normalizeMisplacedSerials($normalized['line_items'], $pdfText);
        $normalized['line_items'] = $this->dedupeLineItems($normalized['line_items']);

        $beforeFilter = count($normalized['line_items']);
        $normalized['line_items'] = $this->filterRowsWithoutIdentifier($normalized['line_items']);
        $normalized['excluded_without_identifier'] = $beforeFilter - count($normalized['line_items']);

        return $normalized;
    }

    protected function systemPrompt(string $brandInstructions): string
    {
        $base = <<<'PROMPT'
You extract inventory unit rows from marine manufacturer invoice PDF text.

Rules:
- Return JSON only matching the schema.
- line_items: one entry per physical unit. When an invoice line shows quantity N, output exactly N rows in line_items for that line (expand quantity).
- Multiple line_items may share the same item_code, description, model, and unit_price — that is expected when quantity > 1.
- invoice_lines: aggregated invoice rows (qty, description, unit price, extension) for billing — do not expand quantity here.
- hin: hull ID from parenthetical lists on the invoice line; assign a distinct HIN to each expanded unit when the PDF lists multiple.
- serial_number: only when clearly labeled separately from HIN (e.g. "serial#..."). Do not put serial numbers in the hin field.
- Every line_items row MUST include hin or serial_number from the document. Never output unit rows without one of these identifiers.
- unit_price: per-unit dealer cost.
- invoice_date: YYYY-MM-DD when parseable.
- Ignore tax, freight, payment, subtotal/total summary rows.
- Deduplication applies only when the exact same HIN or the exact same serial_number would appear on more than one row (keep the first only). Do not collapse rows merely because they share model, item code, or price.

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

    /**
     * Move values the AI placed in hin when the PDF labels them as serial numbers.
     *
     * @param  list<array<string, mixed>>  $lineItems
     * @return list<array<string, mixed>>
     */
    protected function normalizeMisplacedSerials(array $lineItems, string $pdfText): array
    {
        if (! preg_match_all('/\bserial\s*#?\s*([A-Z0-9][A-Z0-9-]{3,})\b/i', $pdfText, $matches)) {
            return $lineItems;
        }

        $serialSet = [];
        foreach ($matches[1] as $serial) {
            $normalized = strtoupper(trim($serial));
            if ($normalized !== '') {
                $serialSet[$normalized] = true;
            }
        }

        if ($serialSet === []) {
            return $lineItems;
        }

        foreach ($lineItems as &$item) {
            $hin = strtoupper(trim((string) ($item['hin'] ?? '')));
            $serial = trim((string) ($item['serial_number'] ?? ''));
            if ($hin !== '' && $serial === '' && isset($serialSet[$hin])) {
                $item['serial_number'] = $item['hin'];
                $item['hin'] = null;
            }
        }
        unset($item);

        return $lineItems;
    }

    /**
     * Unit rows without a hull ID or serial cannot be imported and are omitted from review.
     *
     * @param  list<array<string, mixed>>  $lineItems
     * @return list<array<string, mixed>>
     */
    protected function filterRowsWithoutIdentifier(array $lineItems): array
    {
        $filtered = array_values(array_filter($lineItems, function (array $row): bool {
            $hin = trim((string) ($row['hin'] ?? ''));
            $serial = trim((string) ($row['serial_number'] ?? ''));

            return $hin !== '' || $serial !== '';
        }));

        foreach ($filtered as $index => &$row) {
            $row['row_index'] = $index;
        }
        unset($row);

        return $filtered;
    }

    protected function ensureUtf8(string $text): string
    {
        $normalized = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return is_string($normalized) ? $normalized : $text;
    }
}
