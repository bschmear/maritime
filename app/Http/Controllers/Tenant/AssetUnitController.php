<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\AssetLayoutFootprint;
use App\Domain\AssetUnit\Actions\CreateAssetUnit as CreateAction;
use App\Domain\AssetUnit\Actions\DeleteAssetUnit as DeleteAction;
use App\Domain\AssetUnit\Actions\UpdateAssetUnit as UpdateAction;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit as RecordModel;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Enums\RecordType;
use App\Enums\Timezone;
use App\Models\AccountSettings;
use App\Services\AssetOptionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Response as InertiaResponse;

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
            app(CreateAction::class),
            app(UpdateAction::class),
            new DeleteAction,
            $recordType->domainName()
        );
    }

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        return $this->unitsIndex($request);
    }

    /**
     * Global units list — dedicated entry point (Assets → Units tab) with through-filters on the parent Asset.
     */
    public function unitsIndex(Request $request): JsonResponse|InertiaResponse
    {
        return parent::index($request);
    }

    /**
     * Brand (make_id) is stored on the related Asset, not on asset_units.
     */
    protected function applyFilters($query, array $filters, $fieldsSchema)
    {
        $remaining = [];

        foreach ($filters as $filter) {
            if (! is_array($filter) || ($filter['field'] ?? '') !== 'make_id') {
                $remaining[] = $filter;

                continue;
            }

            $makeIds = $this->resolveMakeIdsFromFilter($filter);
            if ($makeIds === []) {
                continue;
            }

            $query->whereHas('asset', fn ($q) => $q->whereIn('make_id', $makeIds));
        }

        return parent::applyFilters($query, $remaining, $fieldsSchema);
    }

    /**
     * @return list<int>
     */
    private function resolveMakeIdsFromFilter(array $filter): array
    {
        $operator = $filter['operator'] ?? 'equals';
        $value = $filter['value'] ?? null;

        if ($operator === 'any_of' && is_array($value)) {
            $ids = array_map(fn ($v) => (int) $v, $value);

            return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        }

        if (($operator === 'equals' || $operator === 'any_of') && $value !== null && $value !== '') {
            $id = (int) $value;

            return $id > 0 ? [$id] : [];
        }

        return [];
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
            'documents.description',
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
        $account = AccountSettings::getCurrent();

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

        $hin = trim((string) $request->query('hin', ''));
        if ($hin !== '') {
            $prefill['hin'] = $hin;
        }

        $serialNumber = trim((string) $request->query('serial_number', ''));
        if ($serialNumber !== '') {
            $prefill['serial_number'] = $serialNumber;
        }

        $linkFinancingId = (int) $request->query('link_financing_id', 0) ?: null;

        $linkFinancing = null;
        if ($linkFinancingId) {
            $fin = \App\Domain\Financing\Models\Financing::query()
                ->with(['vendor:id,display_name'])
                ->select([
                    'id', 'sequence', 'serial_vin', 'model_year', 'model_number',
                    'supplier_name', 'lender_invoice_number', 'dealer_name', 'principal_amount',
                    'current_balance', 'financed_at', 'vendor_id',
                ])
                ->find($linkFinancingId);

            if ($fin) {
                $linkFinancing = [
                    'id' => $fin->id,
                    'display_name' => $fin->display_name,
                    'serial_vin' => $fin->serial_vin,
                    'model_year' => $fin->model_year,
                    'model_number' => $fin->model_number,
                    'supplier_name' => $fin->supplier_name,
                    'lender_invoice_number' => $fin->lender_invoice_number,
                    'dealer_name' => $fin->dealer_name,
                    'principal_amount' => $fin->principal_amount,
                    'current_balance' => $fin->current_balance,
                    'financed_at' => $fin->financed_at?->toDateString(),
                    'vendor_name' => $fin->vendor?->display_name,
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
            'linkFinancingId' => $linkFinancingId,
            'linkFinancing' => $linkFinancing,
            'returnUrl' => trim((string) $request->query('return_url', '')) ?: null,
        ]);
    }

    /**
     * @param  AssetUnit  $record
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

        $msoRecords = $this->serializeMsoRecordsForUnit($record);
        $financingContext = $this->buildFinancingContext($record);

        if (! $record->is_consignment) {
            return array_merge($base, $catalog, [
                'msoRecords' => $msoRecords,
                'financingContext' => $financingContext,
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
            'msoRecords' => $msoRecords,
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
            'financingContext' => $financingContext,
        ]);
    }

    /**
     * @param  AssetUnit  $record
     */
    protected function editPageExtraProps($record): array
    {
        return [
            'financingContext' => $this->buildFinancingContext($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFinancingContext(AssetUnit $record): array
    {
        $financing = $record->activeFinancing()
            ->with(['vendor:id,display_name'])
            ->first();

        $metrics = $financing?->metrics()->toArray();

        return [
            'financing' => $financing,
            'metrics' => $metrics,
            'create_url' => URL::route('financings.create', ['asset_unit_id' => $record->id]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeMsoRecordsForUnit(AssetUnit $record): array
    {
        return MsoRecord::query()
            ->where('asset_unit_id', $record->id)
            ->with(['transaction' => fn ($q) => $q->select(['id', 'sequence'])])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MsoRecord $mso) => [
                'id' => $mso->id,
                'display_name' => $mso->display_name,
                'status' => $mso->status?->value,
                'status_label' => $mso->status?->label(),
                'created_at' => $mso->created_at?->toIso8601String(),
                'submitted_at' => $mso->submitted_at?->toIso8601String(),
                'show_url' => route('mso.show', $mso->id),
                'transaction_label' => $mso->transaction
                    ? 'DL-'.($mso->transaction->sequence ?: $mso->transaction->id)
                    : null,
            ])
            ->values()
            ->all();
    }

    public function transferLocation(Request $request, AssetUnit $assetUnit): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $assetUnit->update(['location_id' => $validated['location_id']]);

        return response()->json([
            'success' => true,
            'location_id' => (int) $validated['location_id'],
        ]);
    }

    public function updateLayoutFootprint(Request $request, AssetUnit $assetUnit): JsonResponse
    {
        $validated = $request->validate([
            'length_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'width_ft' => ['required', 'numeric', 'min:0.01', 'max:500'],
        ]);

        $assetUnit->load(['asset', 'assetVariant']);

        $asset = $assetUnit->asset;
        if ($asset === null) {
            return response()->json(['message' => 'Unit has no linked asset.'], 422);
        }

        AssetLayoutFootprint::applyFootprintFeet(
            $asset,
            $assetUnit,
            (float) $validated['length_ft'],
            (float) $validated['width_ft'],
        );

        return response()->json([
            'success' => true,
            'length_ft' => (float) $validated['length_ft'],
            'width_ft' => (float) $validated['width_ft'],
        ]);
    }
}
