<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps documents in sync across a contact and its CRM lead / customer profile rows.
 */
final class ContactDocumentLinker
{
    /**
     * @return class-string<Model>
     */
    public static function modelClassForDomain(string $domain): string
    {
        return match ($domain) {
            'Contact' => Contact::class,
            'Customer' => Customer::class,
            'Lead' => Lead::class,
            default => "App\\Domain\\{$domain}\\Models\\{$domain}",
        };
    }

    public static function resolveContact(?string $documentableType, ?int $documentableId): ?Contact
    {
        if (! $documentableType || ! $documentableId) {
            return null;
        }

        if ($documentableType === Contact::class) {
            return Contact::query()->find($documentableId);
        }

        if ($documentableType === Customer::class) {
            $customer = Customer::query()->find($documentableId);

            return $customer?->contact_id
                ? Contact::query()->find($customer->contact_id)
                : null;
        }

        if ($documentableType === Lead::class) {
            $lead = Lead::query()->find($documentableId);

            return $lead?->contact_id
                ? Contact::query()->find($lead->contact_id)
                : null;
        }

        return null;
    }

    public static function resolveContactFromDomain(string $domain, int $id): ?Contact
    {
        return self::resolveContact(self::modelClassForDomain($domain), $id);
    }

    /**
     * @return list<array{0: class-string<Model>, 1: int}>
     */
    public static function documentableTargets(Contact $contact): array
    {
        $targets = [[Contact::class, (int) $contact->id]];

        $customerId = Customer::query()
            ->where('contact_id', $contact->id)
            ->value('id');
        if ($customerId) {
            $targets[] = [Customer::class, (int) $customerId];
        }

        $leadIds = Lead::query()
            ->where('contact_id', $contact->id)
            ->pluck('id');
        foreach ($leadIds as $leadId) {
            $targets[] = [Lead::class, (int) $leadId];
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public static function syncAttach(Document $document, Contact $contact, array $pivot = []): void
    {
        $pivot = array_merge(['visible_to_customer' => false], $pivot);

        foreach (self::documentableTargets($contact) as [$class, $id]) {
            /** @var Model|null $model */
            $model = $class::query()->find($id);
            if ($model && method_exists($model, 'attachDocument') && ! $model->hasDocument($document)) {
                $model->attachDocument($document, $pivot);
            }
        }
    }

    public static function attachToCustomerOnly(Document $document, Customer $customer, bool $visibleToCustomer = true): void
    {
        self::setPivotVisibility($customer, $document, $visibleToCustomer);
    }

    public static function documentInContactCluster(Contact $contact, Document $document): bool
    {
        return self::forContact($contact)->contains(fn (Document $d) => (int) $d->id === (int) $document->id);
    }

    /**
     * Set portal visibility for a document across the contact cluster (customer pivot is authoritative for the portal).
     */
    public static function setClusterVisibility(Document $document, Model $viewingRecord, bool $visibleToCustomer): void
    {
        $contact = self::resolveContactFromRecord($viewingRecord);
        if (! $contact || ! self::documentInContactCluster($contact, $document)) {
            return;
        }

        $pivot = [
            'sort_order' => 0,
            'visible_to_customer' => $visibleToCustomer,
        ];

        $hasVisibilityColumn = Schema::hasColumn('documentables', 'visible_to_customer');

        foreach (self::documentableTargets($contact) as [$class, $id]) {
            /** @var Model|null $model */
            $model = $class::query()->find($id);
            if (! $model || ! method_exists($model, 'attachDocument')) {
                continue;
            }

            $shouldSync = self::documentableRowExists($document->id, $class, $id)
                || $class === Customer::class
                || self::isViewingTarget($viewingRecord, $class, $id);

            if (! $shouldSync) {
                continue;
            }

            if ($hasVisibilityColumn && self::updateDocumentableVisibility($document->id, $class, $id, $visibleToCustomer)) {
                continue;
            }

            if ($model->hasDocument($document)) {
                $model->updateDocumentPivot($document, $pivot);
            } else {
                $model->attachDocument($document, $pivot);
            }
        }
    }

    private static function isViewingTarget(Model $viewingRecord, string $class, int $id): bool
    {
        return $viewingRecord instanceof $class && (int) $viewingRecord->getKey() === $id;
    }

    private static function documentableRowExists(int $documentId, string $class, int $documentableId): bool
    {
        return DB::table('documentables')
            ->where('document_id', $documentId)
            ->where('documentable_type', $class)
            ->where('documentable_id', $documentableId)
            ->exists();
    }

    private static function updateDocumentableVisibility(int $documentId, string $class, int $documentableId, bool $visible): bool
    {
        return DB::table('documentables')
            ->where('document_id', $documentId)
            ->where('documentable_type', $class)
            ->where('documentable_id', $documentableId)
            ->update([
                'visible_to_customer' => $visible,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    private static function setPivotVisibility(Customer $customer, Document $document, bool $visibleToCustomer, array $pivot = []): void
    {
        $pivot = array_merge([
            'sort_order' => 0,
            'visible_to_customer' => $visibleToCustomer,
        ], $pivot);

        if ($customer->hasDocument($document)) {
            $customer->updateDocumentPivot($document, $pivot);
        } else {
            $customer->documents()->attach($document->id, $pivot);
        }
    }

    public static function syncDetach(Document $document, Contact $contact): void
    {
        foreach (self::documentableTargets($contact) as [$class, $id]) {
            /** @var Model|null $model */
            $model = $class::query()->find($id);
            if ($model && method_exists($model, 'detachDocument') && $model->hasDocument($document)) {
                $model->detachDocument($document);
            }
        }
    }

    /**
     * @return Collection<int, Document>
     */
    public static function forContact(Contact $contact): Collection
    {
        $pairs = collect(self::documentableTargets($contact));

        if ($pairs->isEmpty()) {
            return collect();
        }

        return Document::query()
            ->where(function (Builder $query) use ($pairs) {
                foreach ($pairs as [$class, $id]) {
                    $query->orWhereExists(function ($sub) use ($class, $id) {
                        $sub->selectRaw('1')
                            ->from('documentables')
                            ->whereColumn('documentables.document_id', 'documents.id')
                            ->where('documentables.documentable_type', $class)
                            ->where('documentables.documentable_id', $id);
                    });
                }
            })
            ->orderByDesc('documents.created_at')
            ->get()
            ->unique('id')
            ->values();
    }

    public static function hydrateDocumentsRelationIfApplicable(Model $record): void
    {
        $contact = self::resolveContactFromRecord($record);
        if ($contact) {
            self::hydrateClusterDocumentsOn($record, $contact);

            return;
        }

        if (method_exists($record, 'documents')) {
            $documents = $record->documents()
                ->orderByDesc('documents.created_at')
                ->get()
                ->map(function (Document $document) {
                    $row = $document->toArray();
                    $row['visible_to_customer'] = self::pivotBool($document->pivot->visible_to_customer ?? false);
                    $row['visible_to_vendor'] = self::pivotBool($document->pivot->visible_to_vendor ?? false);

                    return $row;
                });

            $record->setRelation('documents', $documents);
        }
    }

    public static function resolveContactFromRecord(Model $record): ?Contact
    {
        if ($record instanceof Contact) {
            return $record;
        }

        if ($record instanceof Customer && $record->contact_id) {
            return Contact::query()->find($record->contact_id);
        }

        if ($record instanceof Lead && $record->contact_id) {
            return Contact::query()->find($record->contact_id);
        }

        return null;
    }

    /**
     * Union of documents on contact + customer profile + lead profiles, with pivot-aware visibility for the current view.
     */
    private static function hydrateClusterDocumentsOn(Model $record, Contact $contact): void
    {
        $visibilityByDocument = self::clusterPivotVisibilityByDocument($contact);

        $documents = self::forContact($contact)->map(function (Document $document) use ($visibilityByDocument, $record, $contact) {
            $row = $document->toArray();
            $row['visible_to_customer'] = self::visibleToCustomerForView(
                $visibilityByDocument[(int) $document->id] ?? [],
                $record,
                $contact,
            ) || self::isFulfilledPortalDocument($contact, (int) $document->id);

            return $row;
        });

        $record->setRelation('documents', $documents);
    }

    /**
     * @return array<int, array<class-string<Model>, bool>>
     */
    private static function clusterPivotVisibilityByDocument(Contact $contact): array
    {
        $byDocument = [];
        $targets = self::documentableTargets($contact);

        if ($targets === [] || ! Schema::hasColumn('documentables', 'visible_to_customer')) {
            return $byDocument;
        }

        $rows = DB::table('documentables')
            ->where(function ($query) use ($targets) {
                foreach ($targets as [$class, $id]) {
                    $query->orWhere(function ($inner) use ($class, $id) {
                        $inner->where('documentable_type', $class)
                            ->where('documentable_id', $id);
                    });
                }
            })
            ->get(['document_id', 'documentable_type', 'visible_to_customer']);

        foreach ($rows as $row) {
            $byDocument[(int) $row->document_id][(string) $row->documentable_type] = self::pivotBool($row->visible_to_customer);
        }

        return $byDocument;
    }

    private static function isFulfilledPortalDocument(Contact $contact, int $documentId): bool
    {
        return DocumentRequest::query()
            ->where('contact_id', $contact->id)
            ->where('fulfilled_document_id', $documentId)
            ->where('status', DocumentRequestStatus::Fulfilled)
            ->exists();
    }

    /**
     * Staff UI badge: true when any cluster pivot marks the document customer-visible.
     *
     * @param  array<class-string<Model>, bool>  $flags
     */
    private static function visibleToCustomerForView(array $flags, Model $record, Contact $contact): bool
    {
        if (self::contactHasCustomerProfile($contact)) {
            if (array_key_exists(Customer::class, $flags) && self::pivotBool($flags[Customer::class])) {
                return true;
            }
        } elseif ($record instanceof Customer && array_key_exists(Customer::class, $flags)) {
            return self::pivotBool($flags[Customer::class]);
        }

        if ($record instanceof Lead && array_key_exists(Lead::class, $flags)) {
            return self::pivotBool($flags[Lead::class]);
        }

        foreach ($flags as $visible) {
            if (self::pivotBool($visible)) {
                return true;
            }
        }

        return false;
    }

    private static function contactHasCustomerProfile(Contact $contact): bool
    {
        return Customer::query()->where('contact_id', $contact->id)->exists();
    }

    private static function pivotBool(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if ($value === false || $value === 0 || $value === null) {
            return false;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 't', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    public static function applyExcludeAttachedToFilter(Builder $query, string $parentDomain, int $parentId): void
    {
        $contact = self::resolveContactFromDomain($parentDomain, $parentId);
        if (! $contact) {
            $modelClass = self::modelClassForDomain($parentDomain);
            $query->whereNotExists(function ($sub) use ($modelClass, $parentId) {
                $sub->selectRaw('1')
                    ->from('documentables')
                    ->whereColumn('documentables.document_id', 'documents.id')
                    ->where('documentables.documentable_type', $modelClass)
                    ->where('documentables.documentable_id', $parentId);
            });

            return;
        }

        $pairs = collect(self::documentableTargets($contact));
        $query->whereNotExists(function ($outer) use ($pairs) {
            $outer->selectRaw('1')
                ->from('documentables')
                ->whereColumn('documentables.document_id', 'documents.id')
                ->where(function ($inner) use ($pairs) {
                    foreach ($pairs as [$class, $id]) {
                        $inner->orWhere(function ($clause) use ($class, $id) {
                            $clause->where('documentables.documentable_type', $class)
                                ->where('documentables.documentable_id', $id);
                        });
                    }
                });
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function documentsPayloadForDomain(string $parentDomain, int $parentId): array
    {
        $modelClass = self::modelClassForDomain($parentDomain);
        /** @var Model|null $model */
        $model = $modelClass::query()->find($parentId);
        if (! $model) {
            return [];
        }

        self::hydrateDocumentsRelationIfApplicable($model);

        $documents = $model->getRelation('documents');

        if ($documents instanceof Collection) {
            return self::normalizeDocumentsPayload($documents->values()->all());
        }

        return self::normalizeDocumentsPayload(is_array($documents) ? array_values($documents) : []);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    private static function normalizeDocumentsPayload(array $documents): array
    {
        return array_values(array_map(function ($doc) {
            if (! is_array($doc)) {
                return $doc;
            }

            $doc['visible_to_customer'] = self::pivotBool($doc['visible_to_customer'] ?? false);

            return $doc;
        }, $documents));
    }
}
