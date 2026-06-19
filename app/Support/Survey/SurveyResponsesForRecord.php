<?php

namespace App\Support\Survey;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Survey\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SurveyResponsesForRecord
{
    public static function hydrate(Model $record, string $recordType): void
    {
        $responses = match ($recordType) {
            'contact' => $record instanceof Contact ? self::forContact($record) : collect(),
            'lead' => $record instanceof Lead ? self::forLead($record) : collect(),
            'customer' => $record instanceof Customer ? self::forCustomer($record) : collect(),
            default => collect(),
        };

        $record->setRelation('surveyResponses', $responses);
    }

    public static function forContact(Contact $contact): Collection
    {
        return self::forContactId((int) $contact->id);
    }

    public static function forLead(Lead $lead): Collection
    {
        return self::baseQuery()
            ->where(function (Builder $query) use ($lead): void {
                self::applyMorphCondition($query, 'owner', Lead::class, (int) $lead->id);

                if ($lead->contact_id) {
                    $query->orWhere(function (Builder $nested) use ($lead): void {
                        self::applyMorphCondition($nested, 'sourceable', Contact::class, (int) $lead->contact_id);
                    });
                }
            })
            ->get();
    }

    public static function forCustomer(Customer $customer): Collection
    {
        if (! $customer->contact_id) {
            return collect();
        }

        return self::forContactId((int) $customer->contact_id);
    }

    public static function forContactId(int $contactId): Collection
    {
        return self::baseQuery()
            ->where(function (Builder $query) use ($contactId): void {
                self::applyMorphCondition($query, 'sourceable', Contact::class, $contactId);
                $query->orWhere(function (Builder $nested) use ($contactId): void {
                    self::applyMorphCondition($nested, 'owner', Contact::class, $contactId);
                });
            })
            ->get();
    }

    /**
     * @return Builder<SurveyResponse>
     */
    protected static function baseQuery(): Builder
    {
        return SurveyResponse::query()
            ->whereNotNull('submitted_at')
            ->with(['survey:id,uuid,title,type'])
            ->orderByDesc('submitted_at');
    }

    protected static function applyMorphCondition(Builder $query, string $column, string $type, int $id): void
    {
        $query->where("{$column}_type", $type)->where("{$column}_id", $id);
    }
}
