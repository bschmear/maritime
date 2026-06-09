<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Document\Models\Document;
use App\Domain\Role\Models\Role;
use App\Domain\User\Actions\CreateUser as CreateAction;
use App\Domain\User\Actions\DeleteUser as DeleteAction;
use App\Domain\User\Actions\UpdateUser as UpdateAction;
use App\Domain\User\Models\User as RecordModel;
use App\Enums\ServiceTicket\SignatureMethod;
use App\Http\Controllers\Concerns\HasImageSupport;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User as CentralUser;
use App\Services\TenantStaffResolver;
use App\Support\SignatureStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;

class UserController extends RecordController
{
    use HasImageSupport;

    protected $recordType = 'User';

    protected $table = null;

    public function __construct(Request $request)
    {

        parent::__construct(
            $request,
            'users',
            'User',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'User' // Domain name for schema lookup
        );
    }

    /**
     * True when the signed-in central user maps to a tenant staff row with the Administrator role.
     */
    protected function tenantStaffIsAdministrator(): bool
    {
        $central = auth()->user();
        $staff = TenantStaffResolver::tenantStaffForWebUser($central);
        if (! $staff) {
            return false;
        }
        $staff->loadMissing('role');

        return $staff->role && $staff->role->slug === 'admin';
    }

    /**
     * Central account team seats + whether this tenant staff email can log in to the workspace.
     *
     * @return array<string, mixed>|null
     */
    protected function workspaceTeamProps(?string $tenantStaffEmail): ?array
    {
        $account = request()->get('tenant_account');
        if (! $account instanceof Account) {
            return null;
        }

        $account->loadMissing(['subscription.plan', 'users', 'owner']);
        $currentPlan = $account->currentPlan();
        $seatUsage = $account->seatUsageForDisplay();

        $centralBase = rtrim((string) config('app.url'), '/');
        $accountShowUrl = $centralBase.'/accounts/'.$account->id;

        $webUser = auth()->user();
        $viewerIsAccountOwner = $webUser && (int) $account->owner_id === (int) $webUser->id;
        $viewerCanManageBillingSeats = $viewerIsAccountOwner || $this->tenantStaffIsAdministrator();

        $extraSeatMonthly = (float) (config('app.extra_seats.monthly_price') ?: 15.0);

        $staffInvite = null;
        if ($tenantStaffEmail !== null && trim($tenantStaffEmail) !== '') {
            $emailKey = strtolower(trim($tenantStaffEmail));
            $centralForEmail = CentralUser::query()->whereRaw('LOWER(email) = ?', [$emailKey])->first();
            $onAccount = $centralForEmail
                ? $account->users()->where('users.id', $centralForEmail->id)->exists()
                : false;

            $pendingInvitation = Invitation::query()
                ->where('account_id', $account->id)
                ->whereRaw('LOWER(email) = ?', [$emailKey])
                ->whereNull('accepted_at')
                ->whereNull('declined_at')
                ->exists();

            $staffInvite = [
                'email' => $emailKey,
                'has_central_user' => (bool) $centralForEmail,
                'on_account' => $onAccount,
                'pending_invitation' => $pendingInvitation,
            ];
        }

        return [
            'seat_usage' => $seatUsage,
            'billing_plan' => $currentPlan ? [
                'name' => $currentPlan->name,
                'seat_limit' => $currentPlan->seat_limit,
                'seat_extra' => $currentPlan->seat_extra,
            ] : null,
            'has_active_subscription' => $account->hasActiveSubscription(),
            'extra_seat_monthly_price' => $extraSeatMonthly,
            'account_id' => $account->id,
            'account_show_url' => $accountShowUrl,
            'viewer_is_account_owner' => $viewerIsAccountOwner,
            'viewer_can_manage_billing_seats' => $viewerCanManageBillingSeats,
            'staff_invite' => $staffInvite,
        ];
    }

    /**
     * Display a listing of users with relationships loaded.
     */
    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $schema = $this->getTableSchema() ?? [];
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        if (! $this->tenantStaffIsAdministrator()) {
            $schema = array_merge($schema, ['hide_create_button' => true]);
        }

        if (! in_array('id', $columns)) {
            $columns[] = 'id';
        }

        $query = $this->recordModel->select($columns)->with($this->getRelationshipsToLoad($fieldsSchema));

        // Apply search query (fuzzy search on display_name, case-insensitive)
        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower(trim($searchQuery)).'%']);
        }

        // Apply filters from query parameters
        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // Invalid filters, ignore
            }
        }

        // Order by display_name if the column exists, otherwise by created_at
        $tableName = $this->recordModel->getTable();
        $hasDisplayName = \Schema::connection($this->recordModel->getConnectionName())
            ->hasColumn($tableName, 'display_name');

        if ($hasDisplayName) {
            $query->orderByRaw('LOWER(display_name) ASC');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        // Return JSON for AJAX requests (needed for sublists to get schema)
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        // For now, always return Inertia response for navigation
        $pluralTitle = Str::plural($this->recordTitle);

        return inertia('Tenant/'.$this->domainName.'/Index', [
            'records' => $records,
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'recordType' => $this->recordType,
            'pluralTitle' => $pluralTitle,
            'recordTitle' => Str::singular($this->recordTitle),
            'canManageUsers' => $this->tenantStaffIsAdministrator(),
        ]);
    }

    /**
     * Show a specific user (uses {@see RecordController::show} so sublists and record relations load consistently).
     */
    public function show(Request $request, $id)
    {
        return parent::show($request, $id);
    }

    /**
     * @param  Model  $record
     */
    protected function showPageExtraProps($record): array
    {
        return [
            'canManageUsers' => $this->tenantStaffIsAdministrator(),
            'workspaceTeam' => $this->workspaceTeamProps($record->email),
            'canEditSignature' => $this->canEditUserSignature($record),
            'signature' => [
                'method' => $record->signature_method,
                'url' => $record->signature_url,
                'typed' => $record->typed_signature,
                'saved_at' => $record->signature_saved_at?->toIso8601String(),
            ],
        ];
    }

    protected function canEditUserSignature(RecordModel $user): bool
    {
        if ($this->tenantStaffIsAdministrator()) {
            return true;
        }

        $currentId = current_tenant_user_id();

        return $currentId !== null && $currentId === (int) $user->id;
    }

    public function updateSignature(Request $request, $id): RedirectResponse
    {
        $user = RecordModel::query()->findOrFail($id);

        if (! $this->canEditUserSignature($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string|max:50000',
        ]);

        $updates = [
            'signature_saved_at' => now(),
        ];

        if ($validated['signature_method'] === 'draw') {
            $path = SignatureStorage::storeDrawnImageForStaff($validated['signature_data'], (int) $user->id);
            if (! $path) {
                return back()->withErrors(['signature_data' => 'Could not save drawn signature. Please try again.']);
            }

            $updates['signature_method'] = SignatureMethod::Digital->value;
            $updates['signature_file'] = $path;
            $updates['typed_signature'] = null;
        } else {
            $typed = trim($validated['signature_data']);
            if ($typed === '') {
                return back()->withErrors(['signature_data' => 'Typed signature is required.']);
            }

            $updates['signature_method'] = SignatureMethod::DigitalTyped->value;
            $updates['typed_signature'] = $typed;
            $updates['signature_file'] = null;
        }

        $user->update($updates);

        return back()->with('success', 'Signature saved.');
    }

    public function destroySignature($id): RedirectResponse
    {
        $user = RecordModel::query()->findOrFail($id);

        if (! $this->canEditUserSignature($user)) {
            abort(403);
        }

        $user->update([
            'signature_method' => null,
            'signature_file' => null,
            'typed_signature' => null,
            'signature_saved_at' => null,
        ]);

        return back()->with('success', 'Signature removed.');
    }

    public function create(): InertiaResponse
    {
        if (! $this->tenantStaffIsAdministrator()) {
            abort(403, 'Only administrators can create users.');
        }

        return inertia('Tenant/User/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->getEnumOptions(),
            'roles' => Role::query()->orderBy('display_name')->get(['id', 'display_name', 'slug']),
            'canAssignRole' => true,
            'workspaceTeam' => $this->workspaceTeamProps(null),
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage): RedirectResponse
    {
        if (! $this->tenantStaffIsAdministrator()) {
            abort(403, 'Only administrators can create users.');
        }

        $data = $request->all();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                if ($request->hasFile($fieldKey)) {
                    $file = $request->file($fieldKey);
                    $meta = $fieldDef['meta'] ?? [];
                    $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                    $isPrivate = $meta['private'] ?? false;
                    $resizeWidth = $meta['max_width'] ?? null;
                    $crop = $meta['crop'] ?? false;

                    $result = $publicStorage->store(
                        file: $file,
                        directory: $directory,
                        resizeWidth: $resizeWidth,
                        existingFile: null,
                        crop: $crop,
                        deleteOld: false,
                        isPrivate: $isPrivate
                    );

                    $document = Document::create([
                        'display_name' => $result['display_name'],
                        'file' => $result['key'],
                        'file_extension' => $result['file_extension'],
                        'file_size' => $result['file_size'],
                        'created_by_id' => current_tenant_user_id(),
                        'updated_by_id' => current_tenant_user_id(),
                    ]);

                    $data[$fieldKey] = $document->id;
                }
            }
        }

        $result = ($this->createAction)($data);

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['email' => $result['message'] ?? 'Could not create user.'])->withInput();
        }

        return redirect()
            ->route('users.show', $result['record']->id)
            ->with('success', 'User created.')
            ->with(
                'info',
                'Add this person’s email under your Maritime account → Team members so they can sign in to this workspace.'
            );
    }

    public function edit($id): InertiaResponse
    {
        if (! $this->tenantStaffIsAdministrator()) {
            abort(403, 'Only administrators can edit users.');
        }

        $userId = $id instanceof RecordModel ? $id->id : $id;
        $record = RecordModel::with(['role', 'manager'])->findOrFail($userId);
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $avatarUrls = $this->getImageUrls($record, $fieldsSchema);

        return inertia('Tenant/User/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->getEnumOptions(),
            'roles' => Role::query()->orderBy('display_name')->get(['id', 'display_name', 'slug']),
            'canAssignRole' => true,
            'avatarPreviewUrl' => $avatarUrls['avatar'] ?? null,
            'workspaceTeam' => $this->workspaceTeamProps($record->email),
        ]);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage): RedirectResponse
    {
        if (! $this->tenantStaffIsAdministrator()) {
            abort(403, 'Only administrators can edit users.');
        }

        $userId = $id instanceof RecordModel ? $id->id : $id;

        $data = $request->all();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                if ($request->hasFile($fieldKey)) {
                    $file = $request->file($fieldKey);
                    $meta = $fieldDef['meta'] ?? [];
                    $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                    $isPrivate = $meta['private'] ?? false;
                    $resizeWidth = $meta['max_width'] ?? null;
                    $crop = $meta['crop'] ?? false;

                    $existing = RecordModel::find($userId)?->{$fieldKey};

                    $result = $publicStorage->store(
                        file: $file,
                        directory: $directory,
                        resizeWidth: $resizeWidth,
                        existingFile: null,
                        crop: $crop,
                        deleteOld: false,
                        isPrivate: $isPrivate
                    );

                    $document = Document::create([
                        'display_name' => $result['display_name'],
                        'file' => $result['key'],
                        'file_extension' => $result['file_extension'],
                        'file_size' => $result['file_size'],
                        'created_by_id' => current_tenant_user_id(),
                        'updated_by_id' => current_tenant_user_id(),
                    ]);

                    $data[$fieldKey] = $document->id;

                    if ($existing) {
                        $old = Document::find($existing);
                        if ($old) {
                            $publicStorage->delete($old->file);
                            $old->delete();
                        }
                    }
                }
            }
        }

        $result = ($this->updateAction)($userId, $data);

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['email' => $result['message'] ?? 'Could not update user.'])->withInput();
        }

        return redirect()
            ->route('users.show', $userId)
            ->with('success', 'User updated.');
    }
}
