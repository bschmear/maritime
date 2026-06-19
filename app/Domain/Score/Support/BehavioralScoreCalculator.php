<?php

declare(strict_types=1);

namespace App\Domain\Score\Support;

use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\Communication\Models\Communication;
use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Enums\Entity\PurchaseTimeline;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class BehavioralScoreCalculator
{
    /**
     * @return array{
     *     score: float,
     *     breakdown: list<array{component: string, name: string, value: int|float}>,
     *     meta: array<string, mixed>
     * }
     */
    public function calculate(Model $entity): array
    {
        if ($entity instanceof Lead) {
            return $this->calculateForLead($entity);
        }

        if ($entity instanceof Contact) {
            return $this->calculateForContact($entity);
        }

        throw new InvalidArgumentException('Unsupported scorable type for behavioral scoring.');
    }

    /**
     * @return array{
     *     score: float,
     *     breakdown: list<array{component: string, name: string, value: int|float}>,
     *     meta: array<string, mixed>
     * }
     */
    private function calculateForLead(Lead $lead): array
    {
        $breakdown = [];

        if ($this->filled($lead->email)) {
            $this->push($breakdown, 'has_email', 'Has email', 10);
        }

        if ($this->filled($lead->phone) || $this->filled($lead->mobile)) {
            $this->push($breakdown, 'has_phone', 'Has phone', 10);
        }

        if ($this->filled($lead->first_name) || $this->filled($lead->last_name)) {
            $this->push($breakdown, 'has_name', 'Has name', 5);
        }

        if ($this->filled($lead->address_line_1) || ($this->filled($lead->city) && $this->filled($lead->state))) {
            $this->push($breakdown, 'has_address', 'Has address', 5);
        }

        if ($this->filled($lead->company)) {
            $this->push($breakdown, 'has_company', 'Has company', 3);
        }

        if ($this->filled($lead->interested_model)) {
            $this->push($breakdown, 'boat_interest', 'Interested model specified', 8);
        }

        if ($lead->has_trade_in) {
            $this->push($breakdown, 'has_trade_in', 'Has trade-in', 5);
        }

        $referenceBudget = $lead->budget_max ?? $lead->budget_min;
        if ($referenceBudget !== null && (float) $referenceBudget >= 50_000) {
            $this->push($breakdown, 'budget_50k_plus', 'Budget $50k+', 5);
        }
        if ($referenceBudget !== null && (float) $referenceBudget >= 100_000) {
            $this->push($breakdown, 'budget_100k_plus', 'Budget $100k+', 5);
        }

        $this->pushPurchaseTimelinePoints($breakdown, $lead->purchase_timeline);

        if ($lead->marketing_opt_in) {
            $this->push($breakdown, 'marketing_opt_in', 'Marketing opt-in', 3);
        }

        if ($lead->is_qualified) {
            $this->push($breakdown, 'is_qualified', 'Marked qualified', 5);
        }

        if ($lead->assigned_user_id) {
            $this->push($breakdown, 'assigned_rep', 'Assigned to rep', 2);
        }

        if ($lead->last_contacted_at && Carbon::parse($lead->last_contacted_at)->greaterThanOrEqualTo(now()->subDays(30))) {
            $this->push($breakdown, 'recent_contact', 'Contacted in last 30 days', 5);
        }

        if ($lead->next_followup_at && Carbon::parse($lead->next_followup_at)->greaterThanOrEqualTo(now()->startOfDay())) {
            $this->push($breakdown, 'scheduled_followup', 'Follow-up scheduled', 3);
        }

        $communicationsCount = $this->communicationCountForLead($lead);
        if ($communicationsCount >= 1) {
            $this->push($breakdown, 'communications_1', 'Has communications', 5);
        }
        if ($communicationsCount >= 3) {
            $this->push($breakdown, 'communications_3', '3+ communications', 5);
        }

        if ($lead->qualitifications()->exists()) {
            $this->push($breakdown, 'qualifications', 'Qualification on file', 8);
        }

        if ($lead->estimates()->exists()) {
            $this->push($breakdown, 'estimates', 'Has estimates', 10);
        }

        if ($lead->tasks()->where('completed', false)->exists()) {
            $this->push($breakdown, 'open_tasks', 'Open tasks', 3);
        }

        if ($lead->resolveLinkedCustomerProfile() !== null) {
            $this->push($breakdown, 'linked_customer', 'Linked customer profile', 15);
        }

        if (BoatShowLead::query()
            ->where('leadable_type', Lead::class)
            ->where('leadable_id', $lead->getKey())
            ->exists()) {
            $this->push($breakdown, 'boat_show_lead', 'Boat show capture', 5);
        }

        return $this->result($breakdown, 'lead_pipeline');
    }

    /**
     * @return array{
     *     score: float,
     *     breakdown: list<array{component: string, name: string, value: int|float}>,
     *     meta: array<string, mixed>
     * }
     */
    private function calculateForContact(Contact $contact): array
    {
        $breakdown = [];

        if ($this->filled($contact->email)) {
            $this->push($breakdown, 'has_email', 'Has email', 10);
        }

        if ($this->filled($contact->phone) || $this->filled($contact->mobile)) {
            $this->push($breakdown, 'has_phone', 'Has phone', 10);
        }

        if ($this->filled($contact->first_name) || $this->filled($contact->last_name)) {
            $this->push($breakdown, 'has_name', 'Has name', 5);
        }

        $primary = $contact->relationLoaded('primaryAddress')
            ? $contact->primaryAddress
            : $contact->primaryAddress()->first();

        if ($primary && ($this->filled($primary->address_line_1) || ($this->filled($primary->city) && $this->filled($primary->state)))) {
            $this->push($breakdown, 'has_address', 'Has address', 5);
        }

        if ($this->filled($contact->company)) {
            $this->push($breakdown, 'has_company', 'Has company', 3);
        }

        if ($contact->leads()->exists()) {
            $this->push($breakdown, 'has_lead', 'Lead profile', 15);
        }

        if ($contact->customer()->exists()) {
            $this->push($breakdown, 'has_customer', 'Customer profile', 15);
        }

        $communicationsCount = $contact->communications()->count();
        if ($communicationsCount >= 1) {
            $this->push($breakdown, 'communications_1', 'Has communications', 5);
        }
        if ($communicationsCount >= 3) {
            $this->push($breakdown, 'communications_3', '3+ communications', 5);
        }

        if ($contact->estimates()->exists()) {
            $this->push($breakdown, 'estimates', 'Has estimates', 10);
        }

        if ($contact->invoices()->exists()) {
            $this->push($breakdown, 'invoices', 'Has invoices', 10);
        }

        if ($contact->vendors()->exists()) {
            $this->push($breakdown, 'has_vendor', 'Vendor profile', 5);
        }

        return $this->result($breakdown, 'contact_crm');
    }

    /**
     * @param  list<array{component: string, name: string, value: int|float}>  $breakdown
     */
    private function push(array &$breakdown, string $component, string $name, int|float $value): void
    {
        if ($value <= 0) {
            return;
        }

        $breakdown[] = [
            'component' => $component,
            'name' => $name,
            'value' => $value,
        ];
    }

    /**
     * @param  list<array{component: string, name: string, value: int|float}>  $breakdown
     */
    private function pushPurchaseTimelinePoints(array &$breakdown, mixed $timeline): void
    {
        $timeline = is_string($timeline) ? trim($timeline) : null;
        if ($timeline === null || $timeline === '') {
            return;
        }

        if ($timeline === PurchaseTimeline::Immediate->value) {
            $this->push($breakdown, 'immediate_timeline', 'Immediate purchase timeline', 8);

            return;
        }

        if ($timeline === PurchaseTimeline::ZeroToThreeMonths->value) {
            $this->push($breakdown, 'short_timeline', '0–3 month timeline', 5);

            return;
        }

        if ($timeline === PurchaseTimeline::ThreeToSixMonths->value) {
            $this->push($breakdown, 'mid_timeline', '3–6 month timeline', 3);
        }
    }

    private function communicationCountForLead(Lead $lead): int
    {
        $count = $lead->communications()->count();

        if ($lead->contact_id) {
            $count += Communication::query()
                ->where('communicable_type', Contact::class)
                ->where('communicable_id', $lead->contact_id)
                ->count();
        }

        return $count;
    }

    /**
     * @param  list<array{component: string, name: string, value: int|float}>  $breakdown
     * @return array{
     *     score: float,
     *     breakdown: list<array{component: string, name: string, value: int|float}>,
     *     meta: array<string, mixed>
     * }
     */
    private function result(array $breakdown, string $stage): array
    {
        $total = 0.0;

        foreach ($breakdown as $component) {
            $total += (float) $component['value'];
        }

        $score = round(min($total, 100), 2);

        return [
            'score' => $score,
            'breakdown' => $breakdown,
            'meta' => [
                'breakdown' => $breakdown,
                'reason' => 'Automatically calculated from profile completeness and CRM activity.',
                'stage' => $stage,
                'model_version' => '1.0',
                'auto_generated' => true,
                'confidence' => $this->confidenceFromBreakdown(count($breakdown)),
                'event_id' => null,
            ],
        ];
    }

    private function confidenceFromBreakdown(int $componentCount): float
    {
        return round(min(0.95, 0.45 + ($componentCount * 0.04)), 2);
    }

    private function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== '';
    }
}
