<?php

declare(strict_types=1);

namespace App\Domain\Document\Support;

use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorInstance;
use Illuminate\Support\Collection;

final class PortalDocuments
{
    /**
     * @return LengthAwarePaginator<int, Document>
     */
    public static function paginateForCustomerProfile(
        ?Customer $customerProfile,
        Request $request,
        ?int $contactId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        if (! $customerProfile) {
            return new PaginatorInstance([], 0, $perPage, 1, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]);
        }

        return self::portalVisibleQuery($customerProfile, $contactId)
            ->orderByDesc('documents.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function countForCustomerProfile(?Customer $customerProfile, ?int $contactId = null): int
    {
        if (! $customerProfile) {
            return 0;
        }

        return (int) self::portalVisibleQuery($customerProfile, $contactId)->count();
    }

    public static function customerCanDownload(Customer $customerProfile, Document $document, ?int $contactId = null): bool
    {
        return self::portalVisibleQuery($customerProfile, $contactId)
            ->where('documents.id', $document->id)
            ->exists();
    }

    /**
     * @return Collection<int, Document>
     */
    public static function vendorVisibleOnWarrantyClaim(WarrantyClaim $claim): Collection
    {
        return $claim->documents()
            ->wherePivot('visible_to_vendor', true)
            ->orderByDesc('documents.created_at')
            ->get();
    }

    public static function vendorCanDownloadFromWarrantyClaim(WarrantyClaim $claim, Document $document): bool
    {
        return $claim->documents()
            ->where('documents.id', $document->id)
            ->wherePivot('visible_to_vendor', true)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapForVendorWarrantyClaim(WarrantyClaim $claim): array
    {
        return self::vendorVisibleOnWarrantyClaim($claim)->map(function (Document $document) use ($claim) {
            return [
                'id' => $document->id,
                'display_name' => $document->display_name,
                'file_extension' => $document->file_extension,
                'file_size' => $document->file_size,
                'created_at' => $document->created_at?->toIso8601String(),
                'download_url' => route('vendor.portal.warranty-claims.documents.download', [
                    'warranty_claim' => $claim->id,
                    'document' => $document->id,
                ]),
            ];
        })->all();
    }

    /**
     * Documents shared with the customer (pivot flag) plus uploads that fulfilled their requests.
     */
    private static function portalVisibleQuery(Customer $customerProfile, ?int $contactId): Builder
    {
        $fulfilledDocumentIds = $contactId
            ? DocumentRequest::query()
                ->where('contact_id', $contactId)
                ->where('status', DocumentRequestStatus::Fulfilled)
                ->whereNotNull('fulfilled_document_id')
                ->pluck('fulfilled_document_id')
            : collect();

        return Document::query()->where(function (Builder $query) use ($customerProfile, $fulfilledDocumentIds) {
            $query->whereExists(function ($sub) use ($customerProfile) {
                $sub->selectRaw('1')
                    ->from('documentables')
                    ->whereColumn('documentables.document_id', 'documents.id')
                    ->where('documentables.documentable_type', Customer::class)
                    ->where('documentables.documentable_id', $customerProfile->id)
                    ->whereRaw('documentables.visible_to_customer IS TRUE');
            });

            if ($fulfilledDocumentIds->isNotEmpty()) {
                $query->orWhereIn('documents.id', $fulfilledDocumentIds);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapForPortal(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(function (Document $document) {
            return [
                'id' => $document->id,
                'display_name' => $document->display_name,
                'file_extension' => $document->file_extension,
                'file_size' => $document->file_size,
                'created_at' => $document->created_at?->toIso8601String(),
                'download_url' => route('portal.documents.download', $document->id),
            ];
        })->all();
    }
}
