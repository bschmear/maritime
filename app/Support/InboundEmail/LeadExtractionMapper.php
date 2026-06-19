<?php

namespace App\Support\InboundEmail;

use App\Domain\User\Models\User;
use App\Enums\Entity\BudgetRange;
use App\Enums\Entity\ContactMethod;
use App\Enums\Entity\Priority;
use App\Enums\Entity\PurchaseTimeline;
use App\Enums\Entity\Source;
use App\Enums\Leads\Status;
use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;
use Carbon\Carbon;

class LeadExtractionMapper
{
    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    public function toLeadPayload(array $extracted, EmailRoute $route, AiEmailIngestion $ingestion, string $rawBody): array
    {
        $firstName = $this->string($extracted['first_name'] ?? null);
        $lastName = $this->string($extracted['last_name'] ?? null);

        if ($firstName === null && $lastName === null) {
            $legacyName = $this->string($extracted['name'] ?? null);
            if ($legacyName !== null) {
                $split = LeadNameSplitter::split($legacyName);
                $firstName = $split['first_name'];
                $lastName = $split['last_name'];
            }
        }

        $mobile = $this->string($extracted['mobile'] ?? null)
            ?? $this->string($extracted['phone'] ?? null);
        $phone = $this->string($extracted['phone'] ?? null);
        if ($phone !== null && $mobile !== null && $phone === $mobile) {
            $phone = null;
        }

        $notes = $this->string($extracted['notes'] ?? null) ?? '';
        $interestedModel = $this->string($extracted['interested_model'] ?? null);
        if ($interestedModel !== null && ! str_contains($notes, $interestedModel)) {
            $notes = trim("Interested in: {$interestedModel}\n\n".$notes);
        }

        if ($rawBody !== '' && ($notes === '' || ! str_contains($notes, $rawBody))) {
            $notes = trim($notes."\n\n--- Original email ---\n".$rawBody);
        }

        [$budgetMin, $budgetMax, $budgetRangeId] = $this->resolveBudgetFields($extracted);

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->string($extracted['email'] ?? null),
            'mobile' => $mobile,
            'phone' => $phone,
            'company' => $this->string($extracted['company'] ?? null),
            'position' => $this->string($extracted['position'] ?? null),
            'title' => $this->string($extracted['title'] ?? null),
            'website' => $this->normalizeWebsite($extracted['website'] ?? null),
            'linkedin' => $this->confidentSocialUrl($extracted['linkedin'] ?? null, ['linkedin.com']),
            'facebook' => $this->confidentSocialUrl($extracted['facebook'] ?? null, ['facebook.com', 'fb.com']),
            'address_line_1' => $this->string($extracted['address_line_1'] ?? null),
            'address_line_2' => $this->string($extracted['address_line_2'] ?? null),
            'city' => $this->string($extracted['city'] ?? null),
            'state' => $this->string($extracted['state'] ?? null),
            'postal_code' => $this->string($extracted['postal_code'] ?? null),
            'country' => $this->string($extracted['country'] ?? null),
            'notes' => $notes !== '' ? $notes : null,
            'preferred_contact_method' => $this->resolveContactMethod($extracted['preferred_contact_method'] ?? null),
            'purchase_timeline' => $this->resolvePurchaseTimeline($extracted['purchase_timeline'] ?? null),
            'has_trade_in' => $this->bool($extracted['has_trade_in'] ?? false),
            'status_id' => $this->resolveStatusId($extracted['status'] ?? null) ?? Status::Open->id(),
            'source_id' => $this->resolveSourceId($extracted['source'] ?? null) ?? Source::Other->id(),
            'priority_id' => $this->resolvePriorityId($extracted['priority'] ?? null),
            'budget_range' => $budgetRangeId,
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'next_followup_at' => $this->resolveDate($extracted['next_followup_at'] ?? null),
            'assigned_user_id' => $this->resolveAssignedUserId($extracted['assigned_user_name'] ?? null),
            'medium' => 'ai_inbox',
            'campaign' => $route->address,
            'source_details' => 'AI Inbox ingestion #'.$ingestion->id,
            'for_import' => true,
            'system_log_actor' => 'System User',
        ];

        return array_filter(
            $payload,
            fn (mixed $value) => $value !== null && $value !== ''
        );
    }

    protected function string(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }

    protected function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }

    protected function leadScore(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $score = (int) $value;

        return $score > 0 && $score <= 100 ? $score : null;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array{0: ?float, 1: ?float, 2: ?int}
     */
    protected function resolveBudgetFields(array $extracted): array
    {
        $budgetMin = $this->positiveNumber($extracted['budget_min'] ?? null);
        $budgetMax = $this->positiveNumber($extracted['budget_max'] ?? null);

        if ($budgetMin !== null && $budgetMax !== null && abs($budgetMin - $budgetMax) < 0.01) {
            $budgetMax = $budgetMin;
            $budgetMin = null;
        }

        $referenceAmount = $budgetMax ?? $budgetMin;
        $budgetRangeId = null;

        if ($referenceAmount !== null) {
            $budgetRangeId = BudgetRange::fromAmount($referenceAmount)->id();
        } else {
            $budgetRangeId = $this->resolveBudgetRangeId($extracted['budget_range'] ?? null);
        }

        return [$budgetMin, $budgetMax, $budgetRangeId];
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array{score: int, breakdown: list<array{component: string, value: int|float}>}
     */
    public function buildScoreData(array $extracted, ?float $budgetMin = null, ?float $budgetMax = null): array
    {
        $breakdown = [
            ['component' => 'ai_inbox_intake', 'value' => 25],
        ];

        if ($this->string($extracted['email'] ?? null) !== null) {
            $breakdown[] = ['component' => 'has_email', 'value' => 15];
        }
        if ($this->string($extracted['mobile'] ?? null) !== null || $this->string($extracted['phone'] ?? null) !== null) {
            $breakdown[] = ['component' => 'has_phone', 'value' => 15];
        }
        if ($this->string($extracted['first_name'] ?? null) !== null || $this->string($extracted['last_name'] ?? null) !== null) {
            $breakdown[] = ['component' => 'has_name', 'value' => 10];
        }
        if ($this->bool($extracted['has_trade_in'] ?? false)) {
            $breakdown[] = ['component' => 'has_trade_in', 'value' => 10];
        }
        if ($this->string($extracted['interested_model'] ?? null) !== null) {
            $breakdown[] = ['component' => 'boat_interest', 'value' => 10];
        }

        $referenceAmount = $budgetMax ?? $budgetMin;
        if ($referenceAmount !== null && $referenceAmount >= 50_000) {
            $breakdown[] = ['component' => 'budget_50k_plus', 'value' => 5];
        }
        if ($referenceAmount !== null && $referenceAmount >= 100_000) {
            $breakdown[] = ['component' => 'budget_100k_plus', 'value' => 5];
        }

        if ($this->resolvePurchaseTimeline($extracted['purchase_timeline'] ?? null) === PurchaseTimeline::Immediate->value) {
            $breakdown[] = ['component' => 'immediate_timeline', 'value' => 10];
        }

        $calculated = min(100, (int) array_sum(array_column($breakdown, 'value')));
        $aiScore = $this->leadScore($extracted['lead_score'] ?? null);

        return [
            'score' => $aiScore ?? $calculated,
            'breakdown' => $breakdown,
        ];
    }

    protected function resolveDate(mixed $value): ?string
    {
        $string = $this->string($value);
        if ($string === null) {
            return null;
        }

        try {
            return Carbon::parse($string)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeWebsite(mixed $value): ?string
    {
        $string = $this->string($value);
        if ($string === null) {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $string)) {
            $string = 'https://'.$string;
        }

        return filter_var($string, FILTER_VALIDATE_URL) ? $string : null;
    }

    protected function confidentSocialUrl(mixed $value, array $needles): ?string
    {
        $string = $this->string($value);
        if ($string === null) {
            return null;
        }

        $lower = strtolower($string);
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                if (! preg_match('/^https?:\/\//i', $string)) {
                    $string = 'https://'.$string;
                }

                return $string;
            }
        }

        return null;
    }

    protected function resolveEnumCase(string $enumClass, mixed $value): ?object
    {
        $string = strtolower($this->string($value) ?? '');
        if ($string === '') {
            return null;
        }

        foreach ($enumClass::cases() as $case) {
            if ($case->value === $string) {
                return $case;
            }
        }

        return null;
    }

    protected function resolveContactMethod(mixed $value): ?string
    {
        $case = $this->resolveEnumCase(ContactMethod::class, $value);

        return $case?->value;
    }

    protected function resolvePurchaseTimeline(mixed $value): ?string
    {
        $case = $this->resolveEnumCase(PurchaseTimeline::class, $value);

        return $case?->value;
    }

    protected function resolveStatusId(mixed $value): ?int
    {
        $case = $this->resolveEnumCase(Status::class, $value);

        return $case?->id();
    }

    protected function resolveSourceId(mixed $value): ?int
    {
        $case = $this->resolveEnumCase(Source::class, $value);

        return $case?->id();
    }

    protected function resolvePriorityId(mixed $value): ?int
    {
        $case = $this->resolveEnumCase(Priority::class, $value);

        return $case?->id();
    }

    protected function resolveBudgetRangeId(mixed $value): ?int
    {
        $case = $this->resolveEnumCase(BudgetRange::class, $value);

        return $case?->id();
    }

    protected function resolveAssignedUserId(mixed $value): ?int
    {
        $name = $this->string($value);
        if ($name === null) {
            return null;
        }

        $lower = strtolower($name);

        $user = User::query()
            ->whereRaw('LOWER(display_name) = ?', [$lower])
            ->first();

        if ($user === null) {
            $user = User::query()
                ->whereRaw('LOWER(display_name) LIKE ?', ['%'.$lower.'%'])
                ->first();
        }

        return $user?->id;
    }
}
