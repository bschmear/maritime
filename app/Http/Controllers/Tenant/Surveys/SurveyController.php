<?php

namespace App\Http\Controllers\Tenant\Surveys;

use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyResponse;
use App\Domain\User\Models\User;
use App\Enums\Surveys\Status;
use App\Enums\Surveys\Type;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    /**
     * Users for filters, reassignment, and link pickers (tenant context is already active).
     *
     * @see AccountController::index() for the same shape
     */
    private function usersForSelect(): array
    {
        return User::query()
            ->select('id', 'display_name', 'email')
            ->orderBy('display_name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->display_name ?: $user->email,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    /**
     * Tenant DB row id for the currently authenticated user (matched by email).
     */
    private function tenantUserId(): ?int
    {
        return current_tenant_profile()?->id;
    }

    // -------------------------------------------------------------------------
    // Inertia page actions
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $filterType = $request->get('type', '');
        $filterStatus = $request->get('status', 'active');
        $filterName = $request->get('n', '');
        $filterUser = $request->get('user');
        $sortCol = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');

        $allowed = ['title', 'type', 'status', 'created_at'];
        $sortCol = in_array($sortCol, $allowed, true) ? $sortCol : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Survey::query()->withCount('responses')->with('user');

        if ($filterType) {
            $query->where('type', $filterType);
        }

        if ($filterStatus === Status::Active->value) {
            $query->where('status', true);
        } elseif ($filterStatus === Status::Draft->value) {
            $query->where('status', false);
        } elseif ($filterStatus === Status::Archived->value) {
            // No archived column on surveys yet; keep filter consistent with UI until schema supports it.
            $query->whereRaw('1 = 0');
        }

        if ($filterName) {
            $query->where('title', 'like', '%'.$filterName.'%');
        }

        if ($filterUser) {
            $query->where('user_id', $filterUser);
        }

        $surveys = $query->orderBy($sortCol, $sortDir)->paginate(25)->withQueryString();

        // Stats
        $totalResponsesThisMonth = DB::table('survey_responses')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $ratingQuestionIds = DB::table('survey_questions')->where('type', 'rating')->pluck('id');

        $avgSatisfaction = $ratingQuestionIds->isNotEmpty()
            ? round(DB::table('survey_response_answers')
                ->whereIn('survey_question_id', $ratingQuestionIds)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->avg('answer') ?? 0, 1)
            : 0;

        $topUserRow = DB::table('surveys')
            ->select('user_id', DB::raw('count(*) as surveys_created'))
            ->groupBy('user_id')
            ->orderByDesc('surveys_created')
            ->first();

        $topUsers = null;
        if ($topUserRow) {
            $u = User::query()->find($topUserRow->user_id);
            $topUsers = [
                'name' => $u ? ($u->display_name ?: $u->email) : 'Unknown',
                'surveys_created' => (int) $topUserRow->surveys_created,
            ];
        }

        $leadSurveyResponses = DB::table('survey_responses')
            ->join('surveys', 'survey_responses.survey_id', '=', 'surveys.id')
            ->where('surveys.type', 'lead')
            ->count();

        $convertedLeads = DB::table('survey_responses')->where('converted', true)->count();
        $conversionRate = $leadSurveyResponses > 0
            ? round(($convertedLeads / $leadSurveyResponses) * 100, 1)
            : 0;

        return Inertia::render('Tenant/Survey/Index', [
            'surveys' => $surveys,
            'totalResponsesThisMonth' => $totalResponsesThisMonth,
            'avgSatisfaction' => $avgSatisfaction,
            'topUsers' => $topUsers,
            'conversionRate' => $conversionRate,
            'filterName' => $filterName,
            'filterType' => $filterType,
            'filterStatus' => $filterStatus,
            'surveyTypes' => collect(Type::options())->values(),
            'surveyStatuses' => collect(Status::options())->values(),
            'users' => $this->usersForSelect(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Survey/Create', [
            'users' => $this->usersForSelect(),
            'team' => null,
            'subscription' => null,
        ]);
    }

    public function show(Request $request): Response
    {
        $getId = $request->get('id');
        abort_unless($getId, 404);

        $survey = Survey::firstWhere('uuid', $getId);
        abort_if(! $survey, 404);

        $survey->load([
            'questions' => fn ($q) => $q->orderBy('order'),
            'followups',
            'responses' => fn ($q) => $q->latest()->limit(5),
            'user',
        ])->loadCount('responses');

        $weeklyResponses = $survey->responses()
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $totalResponses = $survey->responses_count;
        $completionRate = $totalResponses > 0
            ? round(($survey->responses()->whereNotNull('submitted_at')->count() / $totalResponses) * 100)
            : 0;

        $avgRating = null;
        $ratingQIds = $survey->questions()->where('type', 'rating')->pluck('id');
        if ($ratingQIds->isNotEmpty()) {
            $raw = DB::table('survey_response_answers')
                ->whereIn('survey_question_id', $ratingQIds)
                ->whereNotNull('answer')
                ->avg('answer');
            $avgRating = $raw ? number_format($raw, 1) : null;
        }

        return Inertia::render('Tenant/Survey/Show', [
            'survey' => $survey,
            'weeklyResponses' => $weeklyResponses,
            'completionRate' => $completionRate,
            'avgRating' => $avgRating,
            'users' => $this->usersForSelect(),
            'currentUser' => auth()->user(),
        ]);
    }

    public function responses(Request $request): Response
    {
        $surveyUuid = $request->get('id');
        $filterUser = $request->get('filteruser', 'all');
        $survey = null;

        $query = SurveyResponse::query()->with(['survey', 'owner', 'assignedTo']);

        if ($surveyUuid) {
            $survey = Survey::where('uuid', $surveyUuid)->firstOrFail();
            $query->where('survey_id', $survey->id);
        }

        if ($filterUser !== 'all') {
            $query->where('assigned_to', $filterUser);
        }

        $responses = $query
            ->orderBy('submitted_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tenant/Survey/Responses', [
            'responses' => $responses,
            'survey' => $survey,
            'users' => $this->usersForSelect(),
            'filterUser' => $filterUser,
            'isAdmin' => false,
            'currentUser' => auth()->user(),
        ]);
    }

    public function showResponse(Request $request): Response
    {
        $surveyId = $request->get('sid');
        $responseId = $request->get('rid');
        abort_unless($responseId, 404);

        $response = SurveyResponse::with(['answers', 'assignedTo'])->findOrFail($responseId);
        $survey = $response->survey;

        if ($survey->uuid != $surveyId) {
            abort(403);
        }

        $survey->load([
            'questions' => fn ($q) => $q->orderBy('order'),
            'followups',
            'user',
        ]);

        return Inertia::render('Tenant/Survey/Response', [
            'survey' => $survey,
            'response' => $response,
            'users' => $this->usersForSelect(),
            'currentUser' => auth()->user(),
            'isAdmin' => false,
            'team' => null,
            'onTrial' => false,
            'subscriptionLevel' => 0,
        ]);
    }

    public function edit(Request $request): Response
    {
        $uuid = $request->get('id');
        abort_unless($uuid, 404);

        $survey = Survey::with([
            'questions' => fn ($q) => $q->orderBy('order'),
            'followups',
            'user',
        ])->firstWhere('uuid', $uuid);

        abort_if(! $survey, 404);

        return Inertia::render('Tenant/Survey/Edit', [
            'survey' => $survey,
            'users' => $this->usersForSelect(),
            'team' => null,
            'subscription' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // JSON / axios actions (called by SurveyCreator.vue)
    // -------------------------------------------------------------------------

    public function getTemplates(): JsonResponse
    {
        // $templates = Cache::remember('survey_templates', now()->addDay(), function () {
            $path = resource_path('survey-templates');
            // dd($path);
            $templates = [];

            if (file_exists($path)) {
                foreach (glob($path.'/*.json') as $file) {
                    $tpl = json_decode(file_get_contents($file), true);
                    if ($tpl) {
                        $templates[] = $tpl;
                    }
                }
            }

            // return $templates;
        // });

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public_description' => 'nullable|string',
            'visibility' => 'nullable|in:private,public',
            'type' => 'nullable|in:feedback,lead,followup,custom',
            'status' => 'nullable|boolean',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'questions' => 'nullable|array',
            'questions.*.type' => 'nullable|required_with:questions|string',
            'questions.*.label' => 'nullable|required_with:questions|string',
            'questions.*.required' => 'nullable|boolean',
            'questions.*.order' => 'nullable|integer',
            'questions.*.options' => 'nullable|array',
            'questions.*.config' => 'nullable|array',
            'questions.*.conditional_logic' => 'nullable|array',
            'delivery_method' => 'nullable|string',
            'automation_trigger' => 'nullable|string',
            'automation_config' => 'nullable|array',
            'thank_you_message' => 'nullable|string',
            'redirect_url' => 'nullable|url',
            'privacy_settings' => 'nullable|array',
            'color_scheme' => 'nullable|in:default,custom',
            'custom_color' => 'nullable|string|max:20',
        ]);

        $tenantUserId = $this->tenantUserId() ?? ($validated['assigned_user_id'] ?? null);

        $survey = Survey::create([
            'user_id' => $tenantUserId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'public_description' => $validated['public_description'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'type' => $validated['type'] ?? 'custom',
            'status' => $validated['status'] ?? false,
            'delivery_method' => $validated['delivery_method'] ?? 'email',
            'automation_trigger' => $validated['automation_trigger'] ?? 'manual',
            'automation_config' => $validated['automation_config'] ?? null,
            'thank_you_message' => $validated['thank_you_message'] ?? null,
            'redirect_url' => $validated['redirect_url'] ?? null,
            'privacy_settings' => $validated['privacy_settings'] ?? null,
            'color_scheme' => $validated['color_scheme'] ?? 'default',
            'custom_color' => $validated['custom_color'] ?? null,
        ]);

        if (! empty($validated['questions'])) {
            foreach ($validated['questions'] as $q) {
                $survey->questions()->create([
                    'type' => $q['type'],
                    'label' => $q['label'],
                    'required' => $q['required'] ?? false,
                    'order' => $q['order'] ?? 0,
                    'options' => $q['options'] ?? null,
                    'config' => $q['config'] ?? null,
                    'conditional_logic' => $q['conditional_logic'] ?? null,
                ]);
            }
        }

        return response()->json($survey->load('questions'), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $getId = $request->get('id');
        abort_unless($getId, 404);

        $survey = Survey::firstWhere('uuid', $getId);
        abort_if(! $survey, 404);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'public_description' => 'nullable|string',
            'visibility' => 'nullable|in:private,public',
            'type' => 'nullable|in:feedback,lead,followup,custom',
            'status' => 'nullable|boolean',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'questions' => 'nullable|array',
            'questions.*.id' => 'nullable',
            'questions.*.type' => 'nullable|required_with:questions|string',
            'questions.*.label' => 'nullable|required_with:questions|string',
            'questions.*.required' => 'nullable|boolean',
            'questions.*.order' => 'nullable|integer',
            'questions.*.options' => 'nullable|array',
            'questions.*.config' => 'nullable|array',
            'questions.*.conditional_logic' => 'nullable|array',
            'delivery_method' => 'nullable|string',
            'automation_trigger' => 'nullable|string',
            'automation_config' => 'nullable|array',
            'thank_you_message' => 'nullable|string',
            'redirect_url' => 'nullable|url',
            'privacy_settings' => 'nullable|array',
            'color_scheme' => 'nullable|in:default,custom',
            'custom_color' => 'nullable|string|max:20',
        ]);

        $survey->update([
            'title' => $validated['title'] ?? $survey->title,
            'description' => $validated['description'] ?? $survey->description,
            'public_description' => $validated['public_description'] ?? $survey->public_description,
            'visibility' => $validated['visibility'] ?? $survey->visibility,
            'type' => $validated['type'] ?? $survey->type,
            'status' => $validated['status'] ?? $survey->status,
            'user_id' => $validated['assigned_user_id'] ?? $survey->user_id,
            'delivery_method' => $validated['delivery_method'] ?? $survey->delivery_method,
            'automation_trigger' => $validated['automation_trigger'] ?? $survey->automation_trigger,
            'automation_config' => $validated['automation_config'] ?? $survey->automation_config,
            'thank_you_message' => $validated['thank_you_message'] ?? $survey->thank_you_message,
            'redirect_url' => $validated['redirect_url'] ?? $survey->redirect_url,
            'privacy_settings' => $validated['privacy_settings'] ?? $survey->privacy_settings,
            'color_scheme' => $validated['color_scheme'] ?? $survey->color_scheme,
            'custom_color' => $validated['custom_color'] ?? $survey->custom_color,
        ]);

        if (isset($validated['questions'])) {
            $keptIds = [];

            foreach ($validated['questions'] as $q) {
                $qId = $q['id'] ?? null;
                $isNumeric = $qId && is_numeric($qId);

                if ($isNumeric) {
                    $existing = $survey->questions()->find($qId);
                    if ($existing) {
                        $existing->update([
                            'type' => $q['type'],
                            'label' => $q['label'],
                            'required' => $q['required'] ?? false,
                            'order' => $q['order'] ?? 0,
                            'options' => $q['options'] ?? null,
                            'config' => $q['config'] ?? null,
                            'conditional_logic' => $q['conditional_logic'] ?? null,
                        ]);
                        $keptIds[] = (int) $qId;
                    }
                } else {
                    $newQ = $survey->questions()->create([
                        'type' => $q['type'],
                        'label' => $q['label'],
                        'required' => $q['required'] ?? false,
                        'order' => $q['order'] ?? 0,
                        'options' => $q['options'] ?? null,
                        'config' => $q['config'] ?? null,
                        'conditional_logic' => $q['conditional_logic'] ?? null,
                    ]);
                    $keptIds[] = $newQ->id;
                }
            }

            $survey->questions()->whereNotIn('id', $keptIds)->delete();
        }

        return response()->json($survey->load('questions'));
    }

    public function destroy(Request $request): mixed
    {
        $uuid = $request->get('id');
        abort_unless($uuid, 404);

        $survey = Survey::where('uuid', $uuid)->firstOrFail();
        $survey->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Survey deleted successfully.']);
        }

        return redirect()->route('surveysIndex')->with('success', 'Survey deleted successfully.');
    }

    public function deleteSelected(Request $request): mixed
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:surveys,id',
        ]);

        Survey::whereIn('id', $validated['ids'])->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Surveys deleted successfully.']);
        }

        return redirect()->route('surveysIndex')->with('success', 'Surveys deleted successfully.');
    }

    public function clone(Request $request): mixed
    {
        $getId = $request->get('id');
        abort_unless($getId, 404);

        $survey = Survey::firstWhere('uuid', $getId);
        abort_if(! $survey, 404);

        $tenantUserId = $this->tenantUserId() ?? $survey->user_id;

        $clone = $survey->replicate();
        $clone->title = $survey->title.' (Copy)';
        $clone->uuid = null;
        $clone->status = false;
        $clone->user_id = $tenantUserId;
        $clone->save();

        foreach ($survey->questions as $question) {
            $newQ = $question->replicate();
            $newQ->survey_id = $clone->id;
            $newQ->save();
        }

        if ($request->expectsJson()) {
            return response()->json($clone->load('questions'), 201);
        }

        return redirect()->route('surveysEdit', ['id' => $clone->uuid])->with('success', 'Survey cloned successfully.');
    }

    public function getActiveSurveys(Request $request): JsonResponse
    {
        $tenantUserId = $this->tenantUserId();

        $surveys = Survey::where('status', true)
            ->where(function ($q) use ($tenantUserId) {
                $q->where('visibility', 'public')
                    ->orWhere(function ($q) use ($tenantUserId) {
                        $q->where('visibility', 'private')->where('user_id', $tenantUserId);
                    });
            })
            ->select('id', 'uuid', 'title', 'description', 'type', 'automation_config', 'user_id', 'visibility')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'title' => $s->title,
                'description' => $s->description,
                'type' => $s->type,
                'automation_config' => $s->automation_config ?? [],
                'is_owner' => $s->user_id === $tenantUserId,
                'visibility' => $s->visibility,
            ]);

        return response()->json([
            'success' => true,
            'surveys' => $surveys,
            'users' => $this->usersForSelect(),
            'current_user_id' => $tenantUserId,
        ]);
    }

    public function reassignResponse(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:survey_responses,id',
                'assigned_to' => 'required|integer|exists:users,id',
            ]);

            $response = SurveyResponse::findOrFail($validated['id']);
            $response->update(['assigned_to' => $validated['assigned_to']]);

            $assignee = User::query()->find($validated['assigned_to']);

            return response()->json([
                'success' => true,
                'message' => 'Response reassigned successfully.',
                'assigned_to' => $assignee ? [
                    'id' => $assignee->id,
                    'name' => $assignee->display_name
                        ?: trim(($assignee->first_name ?? '').' '.($assignee->last_name ?? ''))
                        ?: $assignee->email,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reassign survey response: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign response. Please try again.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // CRM-only stubs — not yet implemented for this project
    // -------------------------------------------------------------------------

    public function convertResponseToLead(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function sendToDeal(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function sendToContact(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function sendToRecord(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
