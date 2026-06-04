<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AddOn\Models\AddOn;
use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Customer\Models\Customer;
use App\Domain\FeatureRequest\Models\FeatureRequestInvite;
use App\Domain\Opportunity\Actions\CreateOpportunity as CreateAction;
use App\Domain\Opportunity\Actions\DeleteOpportunity as DeleteAction;
use App\Domain\Opportunity\Actions\EnsureOpportunityAssetAddonFromCatalog;
use App\Domain\Opportunity\Actions\UpdateOpportunity as UpdateAction;
use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use App\Domain\Opportunity\Models\OpportunityFeatureRequest;
use App\Domain\Opportunity\Services\ApplyFeatureRequestAssetOptionSelections;
use App\Domain\Qualification\Models\Qualification;
use App\Domain\Qualification\Support\ResolveQualificationAsset;
use App\Enums\Entity\BudgetRange;
use App\Enums\Entity\PurchaseTimeline;
use App\Enums\Opportunity\Stage;
use App\Enums\Opportunity\Status;
use App\Enums\Timezone;
use App\Mail\OpportunityFeatureRequestInvite;
use App\Models\AccountSettings;
use App\Services\AssetOptionResolver;
use App\Services\Mail\TenantMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpportunityController extends RecordController
{
    protected $recordType = 'Opportunity';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'opportunities',
            'Opportunity',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    /**
     * Opportunity {@see RecordModel::getDisplayNameAttribute()} is not a DB column, so the default
     * list search never hits it. Match opportunity number, salesperson, or customer contact.
     */
    protected function applyCustomSearch($query, string $rawSearch): bool
    {
        $trimmed = trim($rawSearch);
        $normalized = preg_replace('/^OPP-/i', '', $trimmed);
        $like = '%'.strtolower($normalized).'%';
        $fullLike = '%'.strtolower($trimmed).'%';
        $t = $query->getModel()->getTable();

        $query->where(function ($q) use ($normalized, $like, $fullLike, $t) {
            $q->whereRaw("CAST({$t}.sequence AS TEXT) LIKE ?", [$like])
                ->orWhereRaw("CAST({$t}.id AS TEXT) LIKE ?", [$like])
                ->orWhereRaw("LOWER(CONCAT('opp-', CAST({$t}.sequence AS TEXT))) LIKE ?", [$fullLike]);

            if ($normalized !== '' && ctype_digit($normalized)) {
                $q->orWhere("{$t}.sequence", '=', (int) $normalized);
            }

            $q->orWhereHas('salesperson', function ($sq) use ($like) {
                $sq->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw(
                        'LOWER(TRIM(CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')))) LIKE ?',
                        [$like]
                    )
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
            });

            $q->orWhereHas('customer', function ($cq) use ($like) {
                $cq->whereHas('contact', function ($contactQ) use ($like) {
                    $contactQ->whereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw(
                            'LOWER(TRIM(CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')))) LIKE ?',
                            [$like]
                        )
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
                });
            });
        });

        return true;
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];

        if ($request->query('from') === 'qualification' && $request->query('id')) {
            $qualification = Qualification::with([
                'lead.converted_customer',
                'desired_brand:id,display_name',
            ])->find($request->query('id'));

            if ($qualification) {
                $tenantUser = current_tenant_profile();
                $makeId = $qualification->getRawOriginal('desired_brand');
                $brandRelation = $qualification->relationLoaded('desired_brand')
                    ? $qualification->getRelation('desired_brand')
                    : null;
                $brandName = $brandRelation instanceof BoatMake
                    ? $brandRelation->display_name
                    : ($makeId ? BoatMake::query()->whereKey($makeId)->value('display_name') : null);

                $initialData = [
                    'qualification_id' => $qualification->id,
                    'qualification' => ['id' => $qualification->id, 'display_name' => $qualification->display_name],
                    'user_id' => $tenantUser?->id,
                    'user' => $tenantUser ? ['id' => $tenantUser->id, 'display_name' => $tenantUser->display_name ?? ''] : null,
                    'qualification_prefill' => [
                        'desired_brand_id' => $makeId,
                        'desired_brand_name' => $brandName,
                        'desired_model' => $qualification->desired_model,
                    ],
                ];

                if ($qualification->lead) {
                    if ($qualification->lead->contact_id) {
                        $initialData['contact_id'] = $qualification->lead->contact_id;
                    }

                    if ($qualification->lead->converted_customer_id) {
                        $customer = $qualification->lead->converted_customer;
                        $initialData['customer_id'] = $qualification->lead->converted_customer_id;
                        $initialData['customer'] = ['id' => $customer->id, 'display_name' => $customer->display_name];
                        if ($customer->contact_id) {
                            $initialData['contact_id'] = $customer->contact_id;
                        }
                    }
                }

                $initialData['needs_engine'] = (bool) $qualification->needs_engine;
                $initialData['needs_trailer'] = (bool) $qualification->needs_trailer;
                $initialData['requires_delivery'] = (bool) $qualification->requires_delivery;
                $initialData['delivery_location'] = $qualification->delivery_location;
                $initialData['delivery_state'] = $qualification->delivery_state;
                $initialData['delivery_country'] = $qualification->delivery_country;

                $resolvedAsset = app(ResolveQualificationAsset::class)($qualification);
                if ($resolvedAsset) {
                    $initialData['assets'] = [[
                        'id' => $resolvedAsset->id,
                        'display_name' => $resolvedAsset->display_name,
                        'year' => $resolvedAsset->year,
                        'make' => $resolvedAsset->make ? [
                            'display_name' => $resolvedAsset->make->display_name,
                        ] : null,
                        'default_price' => $resolvedAsset->default_price,
                        'default_cost' => $resolvedAsset->default_cost,
                        'has_variants' => $resolvedAsset->has_variants,
                    ]];
                }
            }
        }

        return inertia('Tenant/Opportunity/Create', [
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
        ]);
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (isset($relationships[$relName])) {
                    continue;
                }

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relName] = function ($q) {
                        $q->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => fn ($q2) => $q2->select(['id', 'display_name'])]);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    // handled below
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relName] = Customer::eagerWithContactSelect();
                } else {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        // Load full qualification with product requirements + nested desired_brand
        $relationships['qualification'] = fn ($q) => $q->select('*')
            ->with(['desired_brand' => fn ($q2) => $q2->select(['id', 'display_name'])]);

        // Load inventory items (Parts & Accessories) and assets with pivot data
        $relationships['inventoryItems'] = fn ($q) => $q->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes');
        $relationships['assets'] = fn ($q) => $q->with('make:id,display_name')
            ->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id', 'asset_unit_id');

        $relationships['featureRequests'] = fn ($q) => $q
            ->select([
                'id',
                'opportunity_id',
                'asset_opportunity_id',
                'include_addons',
                'asset_display_name',
                'variant_label',
                'asset_option_selections',
                'addon_selections',
                'addon_staff_decisions',
                'signer_name',
                'submitted_at',
            ])
            ->orderByDesc('submitted_at')
            ->limit(100);

        $record = $this->recordModel->with($relationships)->findOrFail($id);
        RecordModel::hydratePivotAssetVariants($record->assets);
        app(ApplyFeatureRequestAssetOptionSelections::class)->reconcileOpportunity($record);
        RecordModel::attachLineItemSnapshotsForJson($record);

        $optionResolver = app(AssetOptionResolver::class);
        foreach ($record->featureRequests as $featureRequest) {
            $featureRequest->setAttribute(
                'asset_option_selections_display',
                $this->buildFeatureRequestAssetOptionDisplayRows($featureRequest, $optionResolver)
            );
        }

        $catalogAddons = AddOn::query()
            ->orderBy('name')
            ->get(['id', 'name', 'default_price'])
            ->values()
            ->all();

        return inertia('Tenant/Opportunity/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'qualificationEnumOptions' => [
                'budget_range' => BudgetRange::options(),
                'purchase_timeline' => PurchaseTimeline::options(),
            ],
            'catalogAddons' => $catalogAddons,
        ]);
    }

    /**
     * Email a signed Feature Request Form link for one asset line on the opportunity.
     */
    public function sendFeatureRequestInvite(Request $request, $opportunity): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'asset_opportunity_id' => ['required', 'integer'],
            'include_addons' => ['required', 'boolean'],
            'catalog_addon_ids' => ['nullable', 'array'],
            'catalog_addon_ids.*' => ['integer', 'exists:addons,id'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = RecordModel::with('customer')->findOrFail($opportunity);

        $this->assertOpportunityAllowsFeatureRequestInvite($record);

        if (! $record->customer?->email) {
            return back()->withErrors(['error' => 'This opportunity has no customer email address.']);
        }

        $customerNote = isset($validated['customer_note']) ? trim((string) $validated['customer_note']) : '';
        $customerNote = $customerNote !== '' ? $customerNote : null;

        try {
            $ctx = $this->buildFeatureRequestInviteContext($record, [
                'asset_opportunity_id' => $validated['asset_opportunity_id'],
                'include_addons' => $validated['include_addons'],
                'catalog_addon_ids' => $validated['catalog_addon_ids'] ?? [],
            ]);
            $this->dispatchFeatureRequestInviteEmail($record, $ctx, $customerNote);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Failed to send opportunity feature request invite', [
                'opportunity_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        return back()->with('success', 'Feature Request Form link sent to '.$record->customer->email.'.');
    }

    /**
     * Email signed Feature Request Form links for multiple asset lines (one message per line).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendFeatureRequestInvites(Request $request, $opportunity)
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.asset_opportunity_id' => ['required', 'integer'],
            'lines.*.include_addons' => ['required', 'boolean'],
            'lines.*.catalog_addon_ids' => ['nullable', 'array'],
            'lines.*.catalog_addon_ids.*' => ['integer', 'exists:addons,id'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = RecordModel::with('customer')->findOrFail($opportunity);

        $this->assertOpportunityAllowsFeatureRequestInvite($record);

        if (! $record->customer?->email) {
            return back()->withErrors(['error' => 'This opportunity has no customer email address.']);
        }

        $customerNote = isset($validated['customer_note']) ? trim((string) $validated['customer_note']) : '';
        $customerNote = $customerNote !== '' ? $customerNote : null;

        $contexts = [];
        foreach ($validated['lines'] as $idx => $line) {
            try {
                $contexts[] = $this->buildFeatureRequestInviteContext($record, $line);
            } catch (ValidationException $e) {
                $prefixed = [];
                foreach ($e->errors() as $field => $messages) {
                    $prefixed['lines.'.$idx.'.'.$field] = $messages;
                }

                throw ValidationException::withMessages($prefixed);
            }
        }

        $customerEmail = $record->customer->email;

        try {
            foreach ($contexts as $ctx) {
                $this->dispatchFeatureRequestInviteEmail($record, $ctx, $customerNote);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send opportunity feature request invites', [
                'opportunity_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send one or more emails. Please try again.']);
        }

        $n = count($contexts);

        return back()->with(
            'success',
            $n === 1
                ? 'Feature Request Form link sent to '.$customerEmail.'.'
                : $n.' Feature Request Form links sent to '.$customerEmail.'.'
        );
    }

    /**
     * @param  array{asset_opportunity_id: int, include_addons: bool, catalog_addon_ids?: array<int>}  $line
     * @return array{pivot: object, include_addons: bool, addon_ids_json: ?string, asset_label: string}
     */
    private function buildFeatureRequestInviteContext(RecordModel $record, array $line): array
    {
        $pivot = DB::table('asset_opportunity')
            ->where('id', $line['asset_opportunity_id'])
            ->where('opportunity_id', $record->id)
            ->first();

        if ($pivot === null) {
            throw ValidationException::withMessages([
                'asset_opportunity_id' => 'That asset line does not belong to this opportunity.',
            ]);
        }

        $includeAddons = $line['include_addons'];
        $addonIdsPayload = null;

        if ($includeAddons) {
            $catalogIds = array_values(array_unique(array_map('intval', $line['catalog_addon_ids'] ?? [])));
            if ($catalogIds === []) {
                throw ValidationException::withMessages([
                    'catalog_addon_ids' => 'Select at least one add-on, or turn off Include add-ons.',
                ]);
            }

            foreach ($catalogIds as $addonId) {
                if (! AddOn::query()->whereKey($addonId)->exists()) {
                    throw ValidationException::withMessages([
                        'catalog_addon_ids' => 'One or more add-ons are invalid.',
                    ]);
                }
            }

            // Catalog `addons.id` values — pivot rows are created only after staff approves the customer's submission.
            $addonIdsPayload = json_encode($catalogIds);
        }

        $asset = Asset::query()->find((int) $pivot->asset_id);
        $assetLabel = $asset?->display_name ?? $asset?->name ?? 'Asset';

        return [
            'pivot' => $pivot,
            'include_addons' => $includeAddons,
            'addon_ids_json' => $addonIdsPayload !== null ? $addonIdsPayload : null,
            'asset_label' => $assetLabel,
        ];
    }

    /**
     * Approve or deny a customer-requested catalog add-on from a feature request submission.
     * Approving creates/updates the {@see \App\Domain\Opportunity\Models\OpportunityAssetAddon} line.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reviewFeatureRequestAddon(Request $request, $opportunity, $featureRequest)
    {
        $validated = $request->validate([
            'catalog_addon_id' => ['required', 'integer', 'exists:addons,id'],
            'decision' => ['required', 'string', 'in:approved,denied'],
        ]);

        $record = RecordModel::findOrFail($opportunity);

        $fr = OpportunityFeatureRequest::query()
            ->where('opportunity_id', $record->id)
            ->whereKey($featureRequest)
            ->firstOrFail();

        $addonSelections = $fr->addon_selections ?? [];
        $qty = 1;
        $found = false;

        foreach ($addonSelections as $row) {
            $cid = isset($row['catalog_addon_id']) ? (int) $row['catalog_addon_id'] : null;
            if ($cid === (int) $validated['catalog_addon_id']) {
                $found = true;
                $qty = max(1, (int) ($row['quantity'] ?? 1));

                break;
            }
        }

        if (! $found) {
            throw ValidationException::withMessages([
                'catalog_addon_id' => 'That add-on is not part of this feature request submission.',
            ]);
        }

        $decisions = $fr->addon_staff_decisions ?? [];
        $decisions[(string) $validated['catalog_addon_id']] = $validated['decision'];
        $fr->update(['addon_staff_decisions' => $decisions]);

        if ($validated['decision'] === 'approved') {
            app(EnsureOpportunityAssetAddonFromCatalog::class)(
                (int) $fr->asset_opportunity_id,
                (int) $validated['catalog_addon_id'],
                $qty
            );
        }

        return back()->with(
            'success',
            $validated['decision'] === 'approved'
                ? 'Add-on approved and added to the opportunity line.'
                : 'Add-on request recorded as declined.'
        );
    }

    /**
     * Feature request invites are only allowed in the early pipeline (Open + New).
     */
    private function assertOpportunityAllowsFeatureRequestInvite(RecordModel $record): void
    {
        $status = (int) ($record->status ?? 0);
        $stage = (int) ($record->stage ?? 0);

        if ($status !== Status::Open->id() || $stage !== Stage::New->id()) {
            throw ValidationException::withMessages([
                'error' => 'Feature request forms can only be sent when status is Open and stage is New.',
            ]);
        }
    }

    /**
     * @param  array{pivot: object, include_addons: bool, addon_ids_json: ?string, asset_label: string}  $ctx
     */
    private function dispatchFeatureRequestInviteEmail(RecordModel $record, array $ctx, ?string $customerNote = null): void
    {
        $pivot = $ctx['pivot'];
        $includeAddons = $ctx['include_addons'];

        DB::table('asset_opportunity')
            ->where('id', $pivot->id)
            ->update([
                'feature_request_completed_at' => null,
                'feature_request_addon_ids' => $ctx['addon_ids_json'],
            ]);

        $addonCatalogIds = $ctx['addon_ids_json'] !== null && $ctx['addon_ids_json'] !== ''
            ? json_decode($ctx['addon_ids_json'], true)
            : null;

        $invite = FeatureRequestInvite::query()->create([
            'uuid' => (string) Str::uuid(),
            'source' => 'opportunity',
            'opportunity_id' => $record->id,
            'asset_opportunity_id' => $pivot->id,
            'include_addons' => $includeAddons,
            'addon_catalog_ids' => is_array($addonCatalogIds) ? $addonCatalogIds : null,
        ]);

        $url = URL::temporarySignedRoute(
            'featurerequest.show',
            now()->addDays(30),
            ['invite' => $invite->uuid]
        );

        $account = AccountSettings::getCurrent();
        $mailable = new OpportunityFeatureRequestInvite(
            $record,
            $account,
            $url,
            $ctx['asset_label'],
            $includeAddons,
            $customerNote,
        );

        $customerEmail = $record->customer->email;
        $tenantMail = app(TenantMailService::class);
        $actor = Auth::user();

        if (! $tenantMail->canSend($customerEmail, $mailable, $actor)) {
            throw ValidationException::withMessages([
                'email' => [$tenantMail->validationErrorMessage($mailable)],
            ]);
        }

        $tenantMail->send($customerEmail, $mailable, $actor);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (isset($relationships[$relName])) {
                    continue;
                }

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relName] = function ($q) {
                        $q->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => fn ($q2) => $q2->select(['id', 'display_name'])]);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'sequence']);
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relName] = Customer::eagerWithContactSelect();
                } else {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        // Load inventory items (Parts & Accessories) and assets with pivot data
        $relationships['inventoryItems'] = fn ($q) => $q->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes');
        $relationships['assets'] = fn ($q) => $q->with('make:id,display_name')
            ->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id', 'asset_unit_id');

        $record = $this->recordModel->with($relationships)->findOrFail($id);
        RecordModel::hydratePivotAssetVariants($record->assets);
        app(ApplyFeatureRequestAssetOptionSelections::class)->reconcileOpportunity($record);
        RecordModel::attachLineItemSnapshotsForJson($record);

        return inertia('Tenant/Opportunity/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => [],
        ]);
    }

    /**
     * Resolve stored option ids to labels for the opportunity show “Feature requests” table.
     *
     * @return list<array{option_name: string, value_label: string, price: ?float}>
     */
    private function buildFeatureRequestAssetOptionDisplayRows(
        OpportunityFeatureRequest $featureRequest,
        AssetOptionResolver $resolver
    ): array {
        $selections = $featureRequest->asset_option_selections;
        if (! is_array($selections) || $selections === []) {
            return [];
        }

        $pivotId = (int) ($featureRequest->asset_opportunity_id ?? 0);
        if ($pivotId <= 0) {
            return $this->fallbackFeatureRequestSelectionLabels($selections);
        }

        $pivot = DB::table('asset_opportunity')->where('id', $pivotId)->first();
        if ($pivot === null) {
            return $this->fallbackFeatureRequestSelectionLabels($selections);
        }

        $asset = Asset::query()->find((int) $pivot->asset_id);
        if ($asset === null) {
            return $this->fallbackFeatureRequestSelectionLabels($selections);
        }

        $variantId = ! empty($pivot->asset_variant_id) ? (int) $pivot->asset_variant_id : null;
        $variant = $variantId
            ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
            : null;

        $resolved = $resolver->resolve($asset, $variant)->keyBy('option_id');

        $out = [];
        foreach ($selections as $sel) {
            if (! is_array($sel)) {
                continue;
            }
            $oid = (int) ($sel['option_id'] ?? 0);
            $vid = (int) ($sel['option_value_id'] ?? 0);
            $opt = $resolved->get($oid);
            if ($opt === null) {
                $out[] = [
                    'option_name' => $oid > 0 ? 'Option #'.$oid : 'Option',
                    'value_label' => $vid > 0 ? 'Value #'.$vid : '—',
                    'price' => null,
                ];

                continue;
            }

            $valueMeta = collect($opt['values'] ?? [])->firstWhere(
                fn (array $v) => (int) ($v['id'] ?? 0) === $vid
            );

            $out[] = [
                'option_name' => (string) ($opt['name'] ?? 'Option'),
                'value_label' => is_array($valueMeta)
                    ? (string) ($valueMeta['label'] ?? ($vid > 0 ? 'Value #'.$vid : '—'))
                    : ($vid > 0 ? 'Value #'.$vid : '—'),
                'price' => is_array($valueMeta) && array_key_exists('price', $valueMeta)
                    ? (float) $valueMeta['price']
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $selections
     * @return list<array{option_name: string, value_label: string, price: ?float}>
     */
    private function fallbackFeatureRequestSelectionLabels(array $selections): array
    {
        $out = [];
        foreach ($selections as $sel) {
            if (! is_array($sel)) {
                continue;
            }
            $oid = (int) ($sel['option_id'] ?? 0);
            $vid = (int) ($sel['option_value_id'] ?? 0);
            $out[] = [
                'option_name' => $oid > 0 ? 'Option #'.$oid : 'Option',
                'value_label' => $vid > 0 ? 'Value #'.$vid : '—',
                'price' => null,
            ];
        }

        return $out;
    }
}
