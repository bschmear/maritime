<?php

namespace App\Services\Tax;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class TaxRateAiService
{
    /**
     * @param  array{line1?: string, city?: string, state?: string, postal_code?: string, country?: string}  $address
     * @return array{
     *     state_code: string,
     *     postal_code: string,
     *     city: ?string,
     *     county_name: ?string,
     *     jurisdiction_code: string,
     *     jurisdiction_label: string,
     *     state_rate_percent: float,
     *     local_rate_percent: float,
     *     total_rate_percent: float
     * }
     */
    public function fetch(array $address): array
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $payload = [
            'line1' => trim((string) ($address['line1'] ?? '')),
            'city' => trim((string) ($address['city'] ?? '')),
            'state' => trim((string) ($address['state'] ?? '')),
            'postal_code' => trim((string) ($address['postal_code'] ?? '')),
            'country' => trim((string) ($address['country'] ?? 'US')),
        ];

        $model = (string) config('tax.ai_model', 'gpt-4o-mini');

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'sales_tax_rate_lookup',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TaxRateAiService OpenAI call failed', [
                'address' => $payload,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('AI tax rate lookup failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new \RuntimeException('Empty response from AI tax rate lookup.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid AI tax rate response shape.');
        }

        return $this->normalizeAiResult($decoded, $payload);
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a US sales tax research assistant for a marine dealership CRM.

Given a billing address, return the current combined sales tax rate that should be charged on taxable goods/services delivered to that address.

Rules:
- Return rates as percentages (e.g. 7.0 means 7%).
- total_rate_percent is the full combined rate to charge (state + county + city + district when applicable).
- state_rate_percent is the state portion only.
- local_rate_percent is the combined local portion (county/city/district) such that state_rate_percent + local_rate_percent ~= total_rate_percent.
- county_name should be the county for the ZIP when known.
- jurisdiction_code is typically the two-letter state code.
- jurisdiction_label is a concise human-readable label such as "Naples, FL, 34112 (Collier County)".
- Use current US sales tax law and published state/local rates.
- If the location has no sales tax, return 0 for all rate fields.
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
            'required' => [
                'state_code',
                'postal_code',
                'city',
                'county_name',
                'jurisdiction_code',
                'jurisdiction_label',
                'state_rate_percent',
                'local_rate_percent',
                'total_rate_percent',
            ],
            'properties' => [
                'state_code' => ['type' => 'string'],
                'postal_code' => ['type' => 'string'],
                'city' => ['type' => ['string', 'null']],
                'county_name' => ['type' => ['string', 'null']],
                'jurisdiction_code' => ['type' => 'string'],
                'jurisdiction_label' => ['type' => 'string'],
                'state_rate_percent' => ['type' => 'number'],
                'local_rate_percent' => ['type' => 'number'],
                'total_rate_percent' => ['type' => 'number'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array{line1: string, city: string, state: string, postal_code: string, country: string}  $address
     * @return array{
     *     state_code: string,
     *     postal_code: string,
     *     city: ?string,
     *     county_name: ?string,
     *     jurisdiction_code: string,
     *     jurisdiction_label: string,
     *     state_rate_percent: float,
     *     local_rate_percent: float,
     *     total_rate_percent: float
     * }
     */
    protected function normalizeAiResult(array $decoded, array $address): array
    {
        $stateCode = strtoupper(trim((string) ($decoded['state_code'] ?? $address['state'])));
        $postalCode = $this->normalizePostalCode((string) ($decoded['postal_code'] ?? $address['postal_code']));

        if ($stateCode === '' || $postalCode === null) {
            throw new \RuntimeException('AI tax rate response is missing state or postal code.');
        }

        $total = max(0.0, (float) ($decoded['total_rate_percent'] ?? 0));
        $stateRate = max(0.0, (float) ($decoded['state_rate_percent'] ?? 0));
        $localRate = max(0.0, (float) ($decoded['local_rate_percent'] ?? max(0.0, $total - $stateRate)));

        if ($total <= 0.0 && ($stateRate + $localRate) > 0.0) {
            $total = $stateRate + $localRate;
        }

        $city = trim((string) ($decoded['city'] ?? $address['city']));
        $county = trim((string) ($decoded['county_name'] ?? ''));

        return [
            'state_code' => $stateCode,
            'postal_code' => $postalCode,
            'city' => $city !== '' ? $city : null,
            'county_name' => $county !== '' ? $county : null,
            'jurisdiction_code' => strtoupper(trim((string) ($decoded['jurisdiction_code'] ?? $stateCode))),
            'jurisdiction_label' => trim((string) ($decoded['jurisdiction_label'] ?? '')),
            'state_rate_percent' => round($stateRate, 4),
            'local_rate_percent' => round($localRate, 4),
            'total_rate_percent' => round($total, 4),
        ];
    }

    protected function normalizePostalCode(string $postalCode): ?string
    {
        $digits = preg_replace('/\D/', '', $postalCode) ?? '';

        return strlen($digits) >= 5 ? substr($digits, 0, 5) : null;
    }
}
