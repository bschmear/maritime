<?php

namespace App\Http\Controllers\Tenant;

use App\Domains\Communication\Actions;
use App\Enums\Communication\Channel;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\NextActionType;
use App\Enums\Communication\Outcome;
use App\Enums\Communication\Priority;
use App\Enums\Communication\Status;
use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class CommunicationController extends Controller
{
    public function __construct(Request $request)
    {
        $this->route = Route::currentRouteName();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Initialize user/team filtering variables
        $viewingUserId = $user->id;
        $viewingTeam = false;
        $canFilterUser = false;

        // Get the filter user parameter
        $filterUser = $request->get('u');
        // Normalize filterUser
        if ($filterUser == null || $filterUser == '') {
            $filterUser = $user->id;
        }

        // Handle user filtering logic
        if ($filterUser) {
            if ($canFilterUser) {
                if ($filterUser != 't') {
                    // Filter by specific user
                    $viewingUserId = $filterUser;
                } else {
                    // View entire team
                    $viewingTeam = true;
                }
            } else {
                // Non-admin trying to view team - block it
                if ($filterUser === 't') {
                    abort(403, "Sorry, your user role isn't authorized to view the entire team.");
                }
                // If they're trying to view another user, ignore it and keep current user
            }
        }

        // Only check team membership if not viewing the entire team and user is not the current user
        if (! $viewingTeam && $viewingUserId != $user->id && ! $team->hasTeamUser($viewingUserId)) {
            abort(403, 'User is not on this team.');
        }

        // Get the actual user object for the filtered user (if different from current user)
        $viewingUserObject = $user; // Default to current user
        if (! $viewingTeam && $viewingUserId != $user->id) {
            $viewingUserObject = User::find($viewingUserId);
            if (! $viewingUserObject) {
                abort(404, 'User not found.');
            }
        }

        // Get dashboard data using the service with the correct user
        $communicationStatsService = new \App\Services\CommunicationStatsService;
        if ($viewingTeam) {
            // When viewing team, pass current user with viewingTeam=true to get team-wide stats
            $dashboardData = $communicationStatsService->getDashboardData($team, $user, $isAdmin, true);
        } else {
            // When viewing specific user, pass that user object with viewingTeam=false to get user-specific stats
            $dashboardData = $communicationStatsService->getDashboardData($team, $viewingUserObject, $isAdmin, false);
        }

        // Get filter parameters - now using ID-based filtering
        $paginateCount = $request->get('showing', 25);
        $filterOutcome = $request->get('o', null);
        $filterStatus = $request->input('s', null);
        $filterPriority = $request->get('p', null);
        $filterName = $request->get('n', null);
        $filterChannel = $request->get('c', null);
        $filterNextAction = $request->get('a', null);
        $recordType = $request->get('type', null);
        $showClosed = filter_var($request->get('show_closed', false), FILTER_VALIDATE_BOOLEAN);
        $needsAttention = filter_var($request->get('needs_attention', false), FILTER_VALIDATE_BOOLEAN);

        // Get sorting parameters
        $orderBy = $request->get('order', 'created_at');
        $orderDir = $request->get('dir', 'desc');
        if (empty($orderDir)) {
            $orderDir = 'desc';
        }

        // Build the base query using eager loading instead of joins
        $communicationsQuery = $team->activities()->withoutGlobalScope(TeamScope::class)
            ->with('communicable');

        // Apply user access restrictions based on filtering
        if ($viewingTeam) {
            // If viewing team and user is admin, don't add user restrictions
            if (! $isAdmin) {
                // This shouldn't happen due to earlier check, but as safety
                $communicationsQuery->where(function ($query) use ($user) {
                    $query->where('communications.user_id', $user->id)
                        ->orWhere('communications.assigned_to', $user->id);
                });
            }
            // If admin viewing team, show all team communications (no additional filter)
        } else {
            // Filter by specific user (could be current user or another user if admin)
            $communicationsQuery->where(function ($query) use ($viewingUserId) {
                $query->where('communications.user_id', $viewingUserId)
                    ->orWhere('communications.assigned_to', $viewingUserId);
            });
        }

        if (! $showClosed && empty($filterStatus)) {
            $communicationsQuery->whereIn('communications.status_id', [
                Status::Open->id(),
                Status::Waiting->id(),
            ]);
        }

        // Apply "Needs Attention" filter
        if ($needsAttention) {
            $threeDaysAgo = Carbon::now()->subDays(3);
            $today = Carbon::now();

            $communicationsQuery->where(function ($query) use ($threeDaysAgo, $today) {
                // Overdue follow-ups: next_action_at is in the past and status is not closed
                $query->where(function ($q) use ($today) {
                    $q->where('communications.next_action_at', '<', $today)
                        ->whereNotNull('communications.next_action_at')
                        ->whereNotIn('communications.status_id', [Status::Closed->id()]);
                })
                // OR waiting for response longer than 3 days
                    ->orWhere(function ($q) use ($threeDaysAgo) {
                        $q->where('communications.status_id', Status::Waiting->id())
                            ->where('communications.created_at', '<', $threeDaysAgo);
                    })
                // OR high priority open items
                    ->orWhere(function ($q) {
                        $q->where('communications.priority_id', Priority::High->id())
                            ->where('communications.status_id', Status::Open->id());
                    });
            });
        }

        // Apply filters - updated to use ID-based columns
        if (! empty($filterOutcome)) {
            $communicationsQuery->where('communications.outcome_id', $filterOutcome);
        }

        if (! empty($filterStatus)) {
            $communicationsQuery->where('communications.status_id', $filterStatus);
        }

        if (! empty($filterPriority)) {
            $communicationsQuery->where('communications.priority_id', $filterPriority);
        }

        if (! empty($filterChannel)) {
            $communicationsQuery->where('communications.channel_id', $filterChannel);
        }

        if (! empty($filterNextAction)) {
            $communicationsQuery->where('communications.next_action_type_id', $filterNextAction);
        }

        if (! empty($filterName)) {
            // Search in the subject field and load communicable relationships for name filtering
            $communicationsQuery->where(function ($query) use ($filterName) {
                $query->where('communications.subject', 'like', '%'.$filterName.'%')
                    ->orWhereHasMorph('communicable', ['App\\Models\\Lead', 'App\\Models\\Contact'], function ($q) use ($filterName) {
                        $q->where('title', 'like', '%'.$filterName.'%');
                    })
                    ->orWhereHasMorph('communicable', ['App\\Models\\Vendor'], function ($q) use ($filterName) {
                        $q->where('name', 'like', '%'.$filterName.'%');
                    });
            });
        }

        if (! empty($recordType)) {
            switch ($recordType) {
                case 'contact':
                    $communicableType = 'App\Models\Contact';
                    break;
                case 'lead':
                    $communicableType = 'App\Models\Lead';
                    break;
                case 'vendor':
                    $communicableType = 'App\Models\Vendor';
                    break;
                default:
                    break;
            }

            if (isset($communicableType)) {
                $communicationsQuery->where('communications.communicable_type', $communicableType);
            }
        }

        // Apply sorting - updated column names for ID-based fields
        $validSortColumns = [
            'created_at', 'updated_at', 'subject', 'status_id', 'priority_id',
            'outcome_id', 'channel_id', 'communication_type_id', 'next_action_type_id',
        ];

        switch ($orderBy) {
            case 'rt':
                $dbColumn = 'communicable_type';
                break;
            case 'type':
                $dbColumn = 'communication_type_id';
                break;
            case 'status':
                $dbColumn = 'status_id';
                break;
            case 'priority':
                $dbColumn = 'priority_id';
                break;
            case 'next_action':
                $dbColumn = 'next_action_type_id';
                break;
            case 'user':
                $dbColumn = 'user_id';
                break;
            case 'assigned':
                $dbColumn = 'assigned_to';
                break;
            case 'created_at':
                $dbColumn = 'created_at';
                break;
            case 'updated_at':
                $dbColumn = 'updated_at';
                break;
            default:
                $dbColumn = 'created_at';
                break;
        }

        if (in_array($dbColumn, $validSortColumns)) {
            $communicationsQuery->orderBy('communications.'.$dbColumn, $orderDir);
        } elseif ($orderBy === 'communicable_name') {
            $sortByCommunicableName = true;
            $sortDirection = $orderDir;
            $communicationsQuery->orderBy('communications.created_at', $orderDir); // Default sort for query
        } else {
            $communicationsQuery->orderBy($dbColumn, $orderDir);
        }

        // Get paginated results
        $items = $communicationsQuery->paginate($paginateCount);

        // Get team users for ownership display
        $usersArray = $team->getTeamUserNames();
        $TeamUsers = collect($usersArray)->mapWithKeys(function ($name, $id) {
            return [
                $id => (object) [
                    'id' => $id,
                    'name' => $name,
                ],
            ];
        })->all();

        // Add owner names and communicable names to communications
        $modifiedCommunications = $items->getCollection()->map(function ($communication) use ($TeamUsers) {
            // Add owner name if multiple team users
            if (count($TeamUsers) > 1) {
                $communication->owner_name = $TeamUsers[$communication->user_id]->name ?? 'Unknown';
                $communication->assigned = $TeamUsers[$communication->assigned_to]->name ?? 'Unknown';
            }
            // Add communicable name
            if ($communication->communicable) {
                if ($communication->communicable_type === 'App\\Models\\Lead' || $communication->communicable_type === 'App\\Models\\Contact') {
                    $communication->title = $communication->communicable->title ?? 'No Title';
                } elseif ($communication->communicable_type === 'App\\Models\\Vendor') {
                    $communication->title = $communication->communicable->name ?? 'No Name';
                } else {
                    $communication->title = 'Unknown Type';
                }
            } else {
                $communication->title = 'No Related Record';
            }

            return $communication;
        });

        // Handle sorting by communicable_name if requested
        if (isset($sortByCommunicableName) && $sortByCommunicableName) {
            $modifiedCommunications = $modifiedCommunications->sortBy('title', SORT_REGULAR, $sortDirection === 'desc');
        }

        $items->setCollection($modifiedCommunications);

        // Handle AJAX requests
        if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
            return response()->json([
                'items' => $items,
                'dashboardStats' => $dashboardData,
            ]);
        }

        // Prepare enum options using the static options() method
        $outcomeOptions = json_encode(Outcome::options());
        $statusOptions = json_encode(Status::options());
        $priorityOptions = json_encode(Priority::options());
        $channelOptions = json_encode(Channel::options());
        $nextActionTypeOptions = json_encode(NextActionType::options());
        $communicationTypeOptions = json_encode(CommunicationType::options());

        // Prepare breadcrumbs
        $breadcrumbs = [
            'current' => 'Activity',
            'links' => [
                (object) ['url' => route('dashOverview'), 'name' => 'Dashboard'],
            ],
        ];

        $typeOptions = [
            ['id' => 'contact', 'name' => 'Contact'],
            ['id' => 'lead', 'name' => 'Lead'],
            ['id' => 'vendor', 'name' => 'Vendor'],
        ];

        $typeOptions = json_encode($typeOptions);
        $breadcrumbs = json_encode($breadcrumbs);
        $items = json_encode($items);

        return view('crm.activity.index', compact([
            'filterName',
            'filterOutcome',
            'filterStatus',
            'filterPriority',
            'filterChannel',
            'filterNextAction',
            'recordType',
            'user',
            'items',
            'breadcrumbs',
            'TeamUsers',
            'outcomeOptions',
            'nextActionTypeOptions',
            'statusOptions',
            'priorityOptions',
            'channelOptions',
            'typeOptions',
            'communicationTypeOptions',
            'dashboardData',
            'showClosed',
            'needsAttention',
            'viewingUserId',
            'viewingTeam',
            'canFilterUser',
            'filterUser',
        ]));
    }

    public function recordItems(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = false;

        if (! $user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->user()->canEditTeam($team)) {
            $isAdmin = true;
        }

        $recordType = $request->get('type');
        $recordId = $request->get('id');

        if (! in_array($recordType, ['lead', 'contact', 'vendor'])) {
            abort(400, 'Invalid record type.');
        }

        switch ($recordType) {
            case 'lead':
                $modelClass = \App\Models\Lead::class;
                break;
            case 'contact':
                $modelClass = \App\Models\Contact::class;
                break;
            case 'vendor':
                $modelClass = \App\Models\Vendor::class;
                break;
        }

        $record = $modelClass::where('team_id', $team->id)
            ->findOrFail($recordId);

        $paginateCount = $request->get('showing', 25);

        $communicationsQuery = $record->communications()->orderBy('created_at', 'desc');

        if (! $isAdmin) {
            $communicationsQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $communications = $communicationsQuery->paginate($paginateCount);

        // Prepare enum options to send to client (label + color + id)
        $nextActionTypeOptions = array_map(fn (NextActionType $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], NextActionType::cases());

        $outcomeOptions = array_map(fn (Outcome $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], Outcome::cases());

        $statusOptions = array_map(fn (Status $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], Status::cases());

        $priorityOptions = array_map(fn (Priority $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], Priority::cases());

        $channelOptions = array_map(fn (Channel $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], Channel::cases());

        $communicationTypeOptions = array_map(fn (CommunicationType $type) => [
            'id' => $type->id(),
            'name' => $type->label(),
            'color' => $type->color(),
            'bgClass' => $type->bgClass(),
        ], CommunicationType::cases());

        return response()->json([
            'communications' => $communications,
            'next_action_types' => $nextActionTypeOptions,
            'outcome' => $outcomeOptions,
            'status' => $statusOptions,
            'priority' => $priorityOptions,
            'channel' => $channelOptions,
            'communication_types' => $communicationTypeOptions,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (! $user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        // Updated validation rules to use ID-based columns
        $validated = $request->validate([
            'communicable_type' => 'required|string|in:lead,contact,vendor',
            'communicable_id' => 'required|integer',
            'communication_type_id' => 'required|integer',
            'direction' => 'nullable|in:inbound,outbound',
            'subject' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_private' => 'boolean',
            'status_id' => 'required|integer',
            'next_action_type_id' => 'nullable|integer',
            'channel_id' => 'nullable|integer',
            'priority_id' => 'required|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'outcome_id' => 'nullable|integer',
            'next_action_at' => 'nullable|date',
            'date_contacted' => 'nullable|date',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $communication = Actions::create($user, $team, $validated);

            if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
                return response()->json([
                    'success' => true,
                    'message' => 'Communication created successfully.',
                    'record' => $communication,
                ], 201);
            }

            return redirect()->back()->with('success', 'Communication saved.');
        } catch (\Exception $e) {
            Log::error('Failed to create communication', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'team_id' => $team->id,
                'validated_data' => $validated,
            ]);

            if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create communication. Please try again.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to create communication. Please try again.');
        }
    }

    public function show(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        $request->validate([
            'id' => 'required|integer|exists:communications,id',
        ]);

        $communication = \App\Models\Communication::with(['user', 'communicable'])
            ->where('team_id', $team->id)
            ->findOrFail($request->id);

        if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
            return response()->json([
                'success' => true,
                'record' => $communication,
            ]);
        }

        // Fallback: return JSON or a view as needed
        return response()->json($communication);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Communication $communication)
    {
        //
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        // Updated validation rules to use ID-based columns
        $validated = $request->validate([
            'id' => 'required|integer|exists:communications,id',
            'type_id' => 'sometimes|integer',
            'direction' => 'nullable|in:inbound,outbound',
            'subject' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_private' => 'sometimes|boolean',
            'status_id' => 'sometimes|integer',
            'next_action_type_id' => 'nullable|integer',
            'channel_id' => 'nullable|integer',
            'priority_id' => 'sometimes|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'outcome_id' => 'nullable|integer',
            'next_action_at' => 'nullable|date',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $communication = Communication::findOrFail($validated['id']);

        if (! $user->hasAccessToCurrentTeam() || $communication->team_id !== $team->id) {
            abort(403, 'Unauthorized action.');
        }

        // Remove ID from validated data as it's not needed for update
        unset($validated['id']);

        try {
            $updatedCommunication = Actions::update($user, $team, $communication, $validated);

            if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
                return response()->json([
                    'success' => true,
                    'message' => 'Communication updated successfully.',
                    'record' => $updatedCommunication,
                ]);
            }

            return redirect()->back()->with('success', 'Communication updated.');
        } catch (\Exception $e) {
            Log::error('Failed to update communication', [
                'error' => $e->getMessage(),
                'communication_id' => $communication->id,
                'user_id' => $user->id,
                'team_id' => $team->id,
                'validated_data' => $validated,
            ]);

            if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update communication. Please try again.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update communication. Please try again.');
        }
    }

    public function updateajax(Request $request)
    {
        abort_unless($request->ajax(), 404);
        $user = auth()->user();

        $teamId = $user->current_team_id;
        $team = currentTeam();

        $id = $request->input('id');
        $field = $request->input('field');
        $value = $request->input('value');

        $record = Communication::where('id', $id)->first();
        $isAdmin = $request->user()->canEditTeam($team);

        if ($record->team_id == $teamId && ($record->user_id == $user->id || $record->assigned_to == $user->id || $isAdmin)) {
            $input[$field] = $value;
            $record->update($input);
            if ($request->ajax() && $request->header('X-App-Ajax') === 'true') {
                return response()->json(['success' => true, 'message' => 'Updated successfully']);

            } else {
                return response()->json(['success' => false, 'Sorry you do not have permission.']);
            }

            return back()->with('success', 'Activity Updated');
        } else {
            return response()->json(['error', 'Sorry you do not have permission.']);
        }
    }

    public function destroySelected(Request $request, Actions $actions)
    {
        return $actions->destroySelected($request);
    }

    public function destroy(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $id = $request->get('id');
        $communication = Communication::find($id);

        if (! $communication) {
            if (request()->ajax() && request()->header('X-App-Ajax') === 'true') {
                return response()->json([
                    'success' => false,
                    'message' => 'Communication not found.',
                ], 404);
            }

            return redirect()->back()->with('error', 'Communication not found.');
        }

        $result = Actions::delete($user, $team, $communication);

        if (request()->ajax() && request()->header('X-App-Ajax') === 'true') {
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['status_code']);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }
}
