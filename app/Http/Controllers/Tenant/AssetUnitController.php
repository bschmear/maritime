<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Actions\CreateAssetUnit as CreateAction;
use App\Domain\AssetUnit\Actions\DeleteAssetUnit as DeleteAction;
use App\Domain\AssetUnit\Actions\UpdateAssetUnit as UpdateAction;
use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use App\Enums\RecordType;
use App\Enums\Timezone;
use App\Services\AssetOptionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AssetUnitController extends RecordController
{
    public function __construct(Request $request)
    {
        $recordType = RecordType::AssetUnit;
        parent::__construct(
            $request,
            $recordType->plural(),
            $recordType->title(),
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $recordType->domainName()
        );
    }

    /**
     * Include catalog flags on the parent asset so the UI can hide options when variants exist.
     */
    protected function appendShowRelationships(array &$relationships): void
    {
        $relationships['asset'] = function ($query) {
            $query->select(['id', 'display_name', 'has_variants', 'make_id', 'year', 'model'])
                ->with([
                    'make' => fn ($q) => $q->select(['id', 'display_name']),
                ]);
        };
        $relationships['assetVariant'] = fn ($q) => $q->select(['id', 'display_name', 'name', 'asset_id']);
        $relationships['customer'] = fn ($q) => $q->select(['id', 'contact_id'])
            ->with(['contact' => fn ($cq) => $cq->select(['id', 'display_name', 'first_name', 'last_name'])]);
        $relationships['documents'] = fn ($q) => $q->select([
            'documents.id',
            'documents.display_name',
            'documents.file',
            'documents.file_extension',
            'documents.file_size',
            'documents.created_at',
        ]);
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $prefill = [];
        $assetId = (int) $request->query('asset_id', 0);
        if ($assetId > 0) {
            $asset = Asset::query()
                ->select(['id', 'display_name', 'has_variants'])
                ->find($assetId);
            if ($asset) {
                $prefill = [
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                ];
            }
        }

        return inertia('Tenant/AssetUnit/Create', [
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'prefill' => $prefill,
        ]);
    }

    /**
     * @param  \App\Domain\AssetUnit\Models\AssetUnit  $record
     */
    protected function showPageExtraProps($record): array
    {
        $base = parent::showPageExtraProps($record);

        $asset = Asset::query()
            ->select(['id', 'display_name', 'has_variants', 'make_id', 'year', 'model'])
            ->with([
                'make' => fn ($q) => $q->select(['id', 'display_name']),
            ])
            ->find($record->asset_id);

        if ($asset === null) {
            $catalog = [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        } elseif (filter_var($asset->getAttribute('has_variants'), FILTER_VALIDATE_BOOLEAN)) {
            $catalog = [
                'catalogResolvedOptions' => null,
                'catalogContext' => null,
            ];
        } else {
            $resolved = app(AssetOptionResolver::class)->resolve($asset, null);
            $catalog = [
                'catalogResolvedOptions' => $resolved->values()->all(),
                'catalogContext' => [
                    'asset_id' => $asset->id,
                    'variant_id' => null,
                    'make_id' => $asset->make_id,
                    'has_variants' => false,
                    'show_variant_scope' => false,
                ],
            ];
        }

        if (! $record->is_consignment) {
            $record->loadMissing(['customer.contact']);
            $ownerName = $record->customer?->contact?->display_name;

            return array_merge($base, $catalog, [
                'consignmentAgreementContext' => [
                    'not_marked_consignment' => true,
                    'needs_agreement' => true,
                    'create_url' => URL::route('consignmentagreements.create', ['asset_unit_id' => $record->id]),
                    'mark_consignment_preview' => [
                        'owner_name' => $ownerName,
                        'can_mark' => filled($ownerName) && $record->customer?->contact_id,
                    ],
                ],
            ]);
        }

        $hasSigned = $record->consignmentAgreements()->signed()->exists();
        $draft = $record->consignmentAgreements()->unsigned()->latest('id')->first();
        $reviewUrl = $draft ? URL::route('consignment-agreements.review', ['uuid' => $draft->uuid]) : null;
        $latestSigned = $hasSigned
            ? $record->consignmentAgreements()->signed()->latest('id')->first()
            : null;
        $signedReviewUrl = $latestSigned
            ? URL::route('consignment-agreements.review', ['uuid' => $latestSigned->uuid])
            : null;

        return array_merge($base, $catalog, [
            'consignmentAgreementContext' => [
                'needs_agreement' => ! $hasSigned,
                'has_signed_agreement' => $hasSigned,
                'review_url' => $reviewUrl,
                'signed_review_url' => $signedReviewUrl,
                'create_url' => URL::route('consignmentagreements.create', ['asset_unit_id' => $record->id]),
                'draft_edit_url' => $draft ? URL::route('consignmentagreements.edit', $draft->id) : null,
                'signed_record_url' => $latestSigned ? URL::route('consignmentagreements.show', $latestSigned->id) : null,
                'draft' => $draft ? $draft->only([
                    'id',
                    'uuid',
                    'agreement_date',
                    'signed_at',
                ]) : null,
            ],
        ]);
    }
}
