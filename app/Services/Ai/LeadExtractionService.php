<?php

namespace App\Services\Ai;

use App\Enums\Entity\BudgetRange;
use App\Enums\Entity\ContactMethod;
use App\Enums\Entity\Priority;
use App\Enums\Entity\PurchaseTimeline;
use App\Enums\Entity\Source;
use App\Enums\Leads\Status;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class LeadExtractionService
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $subject, string $emailBody): array
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $model = (string) config('inbound_email.ai_model', 'gpt-4o-mini');

        $userPayload = json_encode([
            'subject' => $subject,
            'body' => $emailBody,
        ], JSON_THROW_ON_ERROR);

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'lead_extraction',
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
            Log::error('LeadExtractionService OpenAI call failed', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('AI lead extraction failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new \RuntimeException('Empty response from AI lead extraction.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid AI lead extraction response shape.');
        }

        return $this->normalize($decoded);
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
You extract marine dealership sales lead details from inbound emails.

Rules:
- Return JSON only matching the schema.
- Focus on the prospective customer, not dealership staff or signatures unless they are the lead.
- Handle forwarded emails and dealer lead providers (Boat Trader, boats.com, manufacturer programs).
- Put uncertain or extra context in notes.
- Use empty strings for unknown text fields.
- Use 0 for unknown numeric fields (lead_score, budget_min, budget_max).
- Use false for has_trade_in when unknown.

Names:
- Prefer first_name and last_name when available; leave name empty if you split them.

Phone numbers:
- Put the primary customer number in mobile (cell/mobile numbers are most common).
- Put a second landline/office number in phone only when explicitly labeled separately.

Social / web (only when explicitly present in the email — do not guess):
- linkedin: full URL containing linkedin.com, else empty.
- facebook: full URL containing facebook.com or fb.com, else empty.
- website: customer or company website URL only when clearly stated.

Address: extract street/city/state/postal_code/country when present.

Budget:
- budget_range must match the dollar amount using these buckets:
{$this->budgetRangePrompt()}
- Listing price / boat price: set budget_max to that amount, budget_min to 0. Do NOT duplicate the same value in both min and max.
- Customer-stated budget range: set budget_min and budget_max separately when they give a range.
- budget_min / budget_max: 0 when unknown.

Lead score:
- lead_score: required integer 1-100 rating lead quality from contact completeness, purchase intent, urgency, trade-in mention, and specific boat interest. Never return 0.

Enums (use exact value or empty string):
- purchase_timeline: {$this->enumList(PurchaseTimeline::class)}
- preferred_contact_method: {$this->enumList(ContactMethod::class)}
- status: {$this->enumList(Status::class)} — use open for new inbound inquiries when unsure.
- source: {$this->enumList(Source::class)} — boat listing sites/ads → ad, website forms → website, manufacturer lead programs → manufacturer.
- priority: {$this->enumList(Priority::class)}

Other:
- has_trade_in: true only when trade-in is explicitly mentioned.
- next_followup_at: YYYY-MM-DD only when an explicit follow-up date is mentioned; else empty.
- assigned_user_name: sales rep name only if explicitly assigned in the email; else empty.
- interested_model: boat model/year of interest when stated.
PROMPT;
    }

    /**
     * @param  class-string  $enumClass
     */
    protected function enumList(string $enumClass): string
    {
        return implode(', ', array_map(fn ($case) => $case->value, $enumClass::cases()));
    }

    protected function budgetRangePrompt(): string
    {
        $lines = [];
        foreach (BudgetRange::aiReference() as $row) {
            $max = $row['max'] !== null ? '$'.number_format($row['max']) : 'and above';
            $lines[] = "  - {$row['value']}: {$row['label']}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'first_name', 'last_name', 'name', 'email', 'mobile', 'phone', 'company',
                'position', 'title', 'website', 'linkedin', 'facebook',
                'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
                'budget_min', 'budget_max', 'budget_range',
                'purchase_timeline', 'preferred_contact_method',
                'has_trade_in', 'status', 'source', 'priority',
                'lead_score', 'next_followup_at', 'assigned_user_name',
                'interested_model', 'notes',
            ],
            'properties' => [
                'first_name' => $string,
                'last_name' => $string,
                'name' => $string,
                'email' => $string,
                'mobile' => $string,
                'phone' => $string,
                'company' => $string,
                'position' => $string,
                'title' => $string,
                'website' => $string,
                'linkedin' => $string,
                'facebook' => $string,
                'address_line_1' => $string,
                'address_line_2' => $string,
                'city' => $string,
                'state' => $string,
                'postal_code' => $string,
                'country' => $string,
                'budget_min' => ['type' => 'number'],
                'budget_max' => ['type' => 'number'],
                'budget_range' => $string,
                'purchase_timeline' => $string,
                'preferred_contact_method' => $string,
                'has_trade_in' => ['type' => 'boolean'],
                'status' => $string,
                'source' => $string,
                'priority' => $string,
                'lead_score' => ['type' => 'integer'],
                'next_followup_at' => $string,
                'assigned_user_name' => $string,
                'interested_model' => $string,
                'notes' => $string,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    protected function normalize(array $decoded): array
    {
        $normalize = fn (mixed $value): ?string => ($s = trim((string) ($value ?? ''))) !== '' ? $s : null;

        return [
            'first_name' => $normalize($decoded['first_name'] ?? null),
            'last_name' => $normalize($decoded['last_name'] ?? null),
            'name' => $normalize($decoded['name'] ?? null),
            'email' => $normalize($decoded['email'] ?? null),
            'mobile' => $normalize($decoded['mobile'] ?? null),
            'phone' => $normalize($decoded['phone'] ?? null),
            'company' => $normalize($decoded['company'] ?? null),
            'position' => $normalize($decoded['position'] ?? null),
            'title' => $normalize($decoded['title'] ?? null),
            'website' => $normalize($decoded['website'] ?? null),
            'linkedin' => $normalize($decoded['linkedin'] ?? null),
            'facebook' => $normalize($decoded['facebook'] ?? null),
            'address_line_1' => $normalize($decoded['address_line_1'] ?? null),
            'address_line_2' => $normalize($decoded['address_line_2'] ?? null),
            'city' => $normalize($decoded['city'] ?? null),
            'state' => $normalize($decoded['state'] ?? null),
            'postal_code' => $normalize($decoded['postal_code'] ?? null),
            'country' => $normalize($decoded['country'] ?? null),
            'budget_min' => (float) ($decoded['budget_min'] ?? 0),
            'budget_max' => (float) ($decoded['budget_max'] ?? 0),
            'budget_range' => $normalize($decoded['budget_range'] ?? null),
            'purchase_timeline' => $normalize($decoded['purchase_timeline'] ?? null),
            'preferred_contact_method' => $normalize($decoded['preferred_contact_method'] ?? null),
            'has_trade_in' => (bool) ($decoded['has_trade_in'] ?? false),
            'status' => $normalize($decoded['status'] ?? null),
            'source' => $normalize($decoded['source'] ?? null),
            'priority' => $normalize($decoded['priority'] ?? null),
            'lead_score' => (int) ($decoded['lead_score'] ?? 0),
            'next_followup_at' => $normalize($decoded['next_followup_at'] ?? null),
            'assigned_user_name' => $normalize($decoded['assigned_user_name'] ?? null),
            'interested_model' => $normalize($decoded['interested_model'] ?? null),
            'notes' => $normalize($decoded['notes'] ?? null),
        ];
    }
}
