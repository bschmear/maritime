<?php

namespace App\Http\Controllers\Tenant\Surveys;

use App\Http\Controllers\Controller;
use App\Models\Survey\Survey;
use App\Models\Survey\SurveyResponse;
use App\Models\Survey\SurveyResponseAnswer;
use App\Enums\Surveys\Status;
use App\Enums\Surveys\Type;
use App\Models\Lead;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        // Get current user and team
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        // Get filters
        $filterType = $request->get('type');
        $filterStatus = $request->get('status', 'active');
        $filterName = $request->get('n');
        $filterUser = $request->get('u');

        // Build the base query
        $query = Survey::query()
            ->withCount('responses')
            ->with('user');

        // Apply filters
        if ($filterType) {
            $query->where('type', $filterType);
        }

        if ($filterStatus) {
            if ($filterStatus == 'active') {
                $query->where('status', true);
            } else {
                $query->where('status', false);
            }
        }

        if ($filterName) {
            $query->where('title', 'like', '%' . $filterName . '%');
        }

        if ($filterUser) {
            $query->where('user_id', $filterUser);
        }

        // Get sorting parameters
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        $query->orderBy($sortBy, $sortDir);

        // Paginate results - 25 per page
        $perPage = $request->get('per_page', 25);
        $surveys = $query->paginate($perPage);

        // If this is an AJAX request for table data, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data' => $surveys->items(),
                'total' => $surveys->total(),
                'per_page' => $surveys->perPage(),
                'current_page' => $surveys->currentPage(),
                'last_page' => $surveys->lastPage(),
            ]);
        }

        // Build breadcrumbs
        $breadcrumbs = json_encode([
            'current' => 'Surveys',
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
            ],
        ]);

        // Replace surveyTypes
        $surveyTypes = collect(Type::options());

        // Replace surveyStatuses - Only Active/Inactive (true/false)
        $surveyStatuses = collect([
            [
                'id' => 'active',
                'value' => 'active',
                'name' => 'Active',
                'color' => 'green',
                'bgClass' => 'bg-green-200 dark:text-white dark:bg-green-900',
            ],
            [
                'id' => 'inactive',
                'value' => 'inactive',
                'name' => 'Inactive',
                'color' => 'red',
                'bgClass' => 'bg-red-200 dark:text-white dark:bg-red-900',
            ]
        ]);

        // Get team users
        $TeamUsers = $team->getTeamUserNames(true);

        // Calculate stats
        $totalResponsesThisMonth = DB::table('survey_responses')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Average satisfaction score from rating questions
        $ratingQuestionIds = DB::table('survey_questions')
            ->where('team_id', auth()->user()->currentTeam->id)
            ->where('type', 'rating')
            ->pluck('id');

        $avgSatisfaction = 0;
        if ($ratingQuestionIds->isNotEmpty()) {
            $avgSatisfaction = DB::table('survey_response_answers')
                ->whereIn('survey_question_id', $ratingQuestionIds)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->avg('answer') ?? 0;
        }

        // Top performing agent (most surveys created)
        $topAgent = DB::table('surveys')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->first();

        $topAgentName = 'N/A';
        if ($topAgent) {
            $agentUser = \App\Models\User::find($topAgent->user_id);
            $topAgentName = $agentUser ? $agentUser->name : 'N/A';
        }

        // Conversion rate for lead surveys
        // Get the count of responses to lead surveys
        $leadSurveyResponses = DB::table('survey_responses')
            ->join('surveys', 'survey_responses.survey_id', '=', 'surveys.id')
            ->where('surveys.type', 'lead')
            ->count();

        $convertedLeads = DB::table('survey_responses')
            ->where('converted', true)
            ->count();
        // dd($convertedLeads);
        $conversionRate = $leadSurveyResponses > 0 ? round(($convertedLeads / $leadSurveyResponses) * 100, 1) : 0;
// dd($surveys);
        return view('crm.surveys.index', compact(
            'breadcrumbs',
            'surveys',
            'filterType',
            'filterStatus',
            'filterName',
            'surveyTypes',
            'surveyStatuses',
            'TeamUsers',
            'totalResponsesThisMonth',
            'avgSatisfaction',
            'topAgentName',
            'conversionRate',
            'user',
            'team'
        ));
    }

    public function create()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);
        $TeamUsers = $team->getTeamUserNames(true);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        $breadcrumbs = [
            'current' => 'Create Survey',
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
                (object)['url' => route('surveysIndex'), 'name' => 'Surveys']
            ]
        ];
        $breadcrumbs = json_encode($breadcrumbs);

        return view('crm.surveys.create', compact('breadcrumbs', 'user', 'team', 'isAdmin', 'TeamUsers'));
    }

    public function getTemplates()
    {
        $user = auth()->user();

        if (!$user->hasAccessToCurrentTeam()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
// Cache::forget('survey_templates');
        $templates = Cache::remember('survey_templates', now()->addDay(), function () {
            $templatesPath = resource_path('survey-templates');
            $templates = [];

            if (file_exists($templatesPath)) {
                $files = glob($templatesPath . '/*.json');

                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    $template = json_decode($content, true);

                    if ($template) {
                        $templates[] = $template;
                    }
                }
            }

            return $templates;
        });

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        // $isAdmin = $user->canEditTeam($team);
        // $TeamUsers = $team->getTeamUserNames(true);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public_description' => 'nullable|string',
            'visibility' => 'nullable|in:private,public',
            'type' => 'nullable|in:feedback,lead,followup,custom',
            'status' => 'nullable|boolean',
            'assigned_user_id' => 'nullable|exists:users,id',
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
            'color_scheme' => 'nullable|in:default,custom,team',
            'custom_color' => 'nullable|string|max:20',
        ]);

        // Create the survey
        $survey = Survey::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'public_description' => $validated['public_description'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'type' => $validated['type'] ?? 'custom',
            'status' => $validated['status'] ?? false,
            'user_id' => $validated['assigned_user_id'] ?? auth()->id(),
            'team_id' => $team->id,
            'delivery_method' => $validated['delivery_method'] ?? 'email',
            'automation_trigger' => $validated['automation_trigger'] ?? 'manual',
            'automation_config' => $validated['automation_config'] ?? null,
            'thank_you_message' => $validated['thank_you_message'] ?? null,
            'redirect_url' => $validated['redirect_url'] ?? null,
            'privacy_settings' => $validated['privacy_settings'] ?? null,
            'color_scheme' => $validated['color_scheme'] ?? 'default',
            'custom_color' => $validated['custom_color'] ?? null,
        ]);

        // Create questions if provided
        if (!empty($validated['questions'])) {
            foreach ($validated['questions'] as $questionData) {
                $survey->questions()->create([
                    'team_id' => $team->id,
                    'type' => $questionData['type'],
                    'label' => $questionData['label'],
                    'required' => $questionData['required'] ?? false,
                    'order' => $questionData['order'] ?? 0,
                    'options' => $questionData['options'] ?? null,
                    'config' => $questionData['config'] ?? null,
                    'conditional_logic' => $questionData['conditional_logic'] ?? null,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json($survey->load('questions'), 201);
        }
        
        return redirect()->route('surveysShow', ['id' => $survey->uuid])->with('success', 'Survey created successfully.');
    }

    public function show(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Get team users for agent selection
        $TeamUsersNames = $team->getTeamUserNames();
        $TeamUsers = collect($TeamUsersNames)->map(function ($name, $id) {
            return [
                'id' => $id,
                'name' => $name,
            ];
        })->values()->all();

        $getId = $request->get('id');
        abort_unless($getId, 404);

        $survey = Survey::firstWhere('uuid', $getId);
        abort_unless($survey, 404);

        // Load relationships
        $survey->load(['questions' => function($query) {
            $query->orderBy('order');
        }, 'followups', 'responses' => function($query) {
            $query->latest();
        }, 'user']);
        
        // Calculate statistics
        $weeklyResponses = $survey->responses()
            ->where('created_at', '>=', now()->subWeek())
            ->count();
            
        $completionRate = $survey->responses()->count() > 0 
            ? round(($survey->responses()->whereNotNull('created_at')->count() / $survey->responses()->count()) * 100)
            : 0;
            
        // Calculate average rating from rating questions
        $avgRating = null;
        $ratingQuestions = $survey->questions()->where('type', 'rating')->pluck('id');
        if ($ratingQuestions->count() > 0) {
            $avgRating = DB::table('survey_response_answers')
                ->whereIn('survey_question_id', $ratingQuestions)
                ->whereNotNull('answer')
                ->avg('answer');
            $avgRating = $avgRating ? number_format($avgRating, 1) : null;
        }
        
        $breadcrumbs = [
            'current' => $survey->title,
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
                (object)['url' => route('surveysIndex'), 'name' => 'Surveys']
            ]
        ];
        $breadcrumbs = json_encode($breadcrumbs);
        
        
        return view('crm.surveys.show', compact(
            'survey', 
            'breadcrumbs', 
            'user', 
            'team',
            'TeamUsers',
            'weeklyResponses',
            'completionRate',
            'avgRating'
        ));
    }

    public function responses(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        // Get team users for filter dropdown
        $TeamUsersNames = $team->getTeamUserNames();
        $TeamUsers = collect($TeamUsersNames)->map(function ($name, $id) {
            return [
                'id' => $id,
                'name' => $name,
            ];
        })->values()->all();

        // Check if filtering by survey UUID
        $surveyUuid = $request->get('id');
        $survey = null;

        // Get filter value - default to current user
        $filterUser = $request->get('filteruser', $user->id);
        
        // Non-admins can only see their own responses
        if (!$isAdmin) {
            $filterUser = $user->id;
        }

        // Build the base query
        $query = SurveyResponse::query()
            ->with(['survey', 'owner', 'assignedTo'])
            ->whereHas('survey', function($q) use ($team, $user, $isAdmin) {
                $q->where('team_id', $team->id);

                // Apply visibility filters
                if (!$isAdmin) {
                    $q->where(function($query) use ($user) {
                        $query->where(function($q) use ($user) {
                            // Private surveys by the current user
                            $q->where('visibility', 'private')
                              ->where('user_id', $user->id);
                        })->orWhere(function($q) {
                            $q->where('visibility', 'public');
                        });
                    });
                }
            });

        // Filter by assigned agent
        if ($filterUser !== 'all') {
            $query->where('assigned_to', $filterUser);
        }

        if ($surveyUuid) {
            $survey = Survey::where('uuid', $surveyUuid)
                ->where('team_id', $team->id)
                ->firstOrFail();

            $query->where('survey_id', $survey->id);
        }

        // Order by most recent first and paginate
        $responses = $query->orderBy('submitted_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        // Build breadcrumbs
        $breadcrumbs = [
            'current' => $survey ? "Responses: {$survey->title}" : 'All Survey Responses',
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
                (object)['url' => route('surveysIndex'), 'name' => 'Surveys']
            ]
        ];

        if ($survey) {
            $breadcrumbs['links'][] = (object)['url' => route('surveyResponses'), 'name' => 'All Responses'];
        }

        $breadcrumbs = json_encode($breadcrumbs);

        return view('crm.surveys.responses', compact(
            'responses',
            'survey',
            'breadcrumbs',
            'user',
            'team',
            'isAdmin',
            'TeamUsers',
            'filterUser'
        ));
    }

    public function showResponse(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        $surveyId = $request->get('sid');
        $responseId = $request->get('rid');
        abort_unless($responseId, 404);

        $response = SurveyResponse::with(['scheduledFollowupEmail' => function($query) {
            // Only load if it's still scheduled and not sent
            $query->whereNotNull('scheduled_at')
                  ->where('scheduled_at', '>', now())
                  ->whereNull('sent_at');
        }, 'latestAiAnalysis'])->findOrFail($responseId);

        $survey = $response->survey;

        if($survey->uuid != $surveyId) {
            abort(403, 'Unauthorized action.');
        }

        // Load relationships
        $survey->load(['questions' => function($query) {
            $query->orderBy('order');
        }, 'followups', 'user']);

        // Get team users for reassignment
        $TeamUsersNames = $team->getTeamUserNames();
        $TeamUsers = collect($TeamUsersNames)->mapWithKeys(function ($name, $id) {
            return [
                $id => (object)[
                    'id' => $id,
                    'name' => $name,
                ],
            ];
        })->all();

        $breadcrumbs = [
            'current' => $survey->title,
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
                (object)['url' => route('surveysIndex'), 'name' => 'Surveys']
            ]
        ];
        $breadcrumbs = json_encode($breadcrumbs);

        // Enrich AI analysis if it exists
        if ($response->latestAiAnalysis) {
            $analysis = $response->latestAiAnalysis;
            // dd($analysis);
            // Add owner record type for follow-up scheduling
            if ($response->owner_type && $response->owner_id) {
                $ownerType = class_basename($response->owner_type);
                $analysis->owner_record_type = strtolower($ownerType);
            } else {
                $analysis->owner_record_type = null;
            }
            
            // Check if follow-up email is already scheduled
            if ($response->scheduled_followup_email_id) {
                $scheduledFollowUp = \App\Models\EmailSent::where('id', $response->scheduled_followup_email_id)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now())
                    ->whereNull('sent_at')
                    ->first();
                
                $analysis->has_scheduled_followup = $scheduledFollowUp !== null;
            } else {
                $analysis->has_scheduled_followup = false;
            }
        }

        // Get subscription information
        $subscription = $team->cachedActiveSubscription();
        $onTrial = $subscription ? $subscription->onTrial() : false;
        $subscriptionLevel = $subscription ? $subscription->level : 1;
// dd($response);
        return view('crm.surveys.response', compact(
            'response',
            'survey',
            'breadcrumbs',
            'user',
            'isAdmin',
            'team',
            'TeamUsers',
            'subscription',
            'onTrial',
            'subscriptionLevel'
        ));
    }

    public function convertResponseToLead(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (!$user->hasAccessToCurrentTeam()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'response_id' => 'required|integer|exists:survey_responses,id',
            'check_duplicate' => 'required|boolean',
            'link_to_existing' => 'sometimes|boolean',
            'existing_lead_id' => 'sometimes|nullable|integer|exists:leads,id',
        ]);

        $response = \App\Models\Survey\SurveyResponse::with('survey')->findOrFail($validated['response_id']);

        // Verify survey belongs to team
        if ($response->survey->team_id !== $team->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        // Ensure the response has an email
        if (!$response->email) {
            return response()->json([
                'success' => false,
                'message' => 'Survey response must have an email address to convert to lead.'
            ], 422);
        }

        // Check for duplicate lead
        if ($validated['check_duplicate']) {
            $existingLead = Lead::where('team_id', $team->id)
                ->where('email', $response->email)
                ->first();

            if ($existingLead) {
                return response()->json([
                    'success' => true,
                    'duplicate_found' => true,
                    'existing_lead' => [
                        'id' => $existingLead->id,
                        'first_name' => $existingLead->first_name,
                        'last_name' => $existingLead->last_name,
                        'email' => $existingLead->email,
                        'created_at' => $existingLead->created_at,
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'duplicate_found' => false
            ]);
        }

        // Process conversion
        try {
            $lead = null;
            $message = '';

            if ($validated['link_to_existing'] ?? false) {
                // Link to existing lead
                $lead = Lead::where('team_id', $team->id)
                    ->findOrFail($validated['existing_lead_id']);

                // Update survey response to link to existing lead
                $response->update([
                    'owner_type' => Lead::class,
                    'owner_id' => $lead->id,
                    'converted' => true,
                ]);

                $message = 'Survey response linked to existing lead successfully!';
            } else {
                // Create new lead
                $lead = Lead::create([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'first_name' => $response->first_name,
                    'last_name' => $response->last_name,
                    'email' => $response->email,
                    'source_id' => 13,
                    'status_id' => 1,
                ]);
                
                // Create a note for the lead
                $note = Note::create([
                    'note' => 'Created from survey response: ' . $response->survey->title,
                    'team_id' => $team->id,
                    'created_by' => $user->id,
                ]);
                
                // Attach the note to the lead
                $lead->note_id = $note->id;
                $lead->save();

                // Update survey response to link to new lead
                $response->update([
                    'owner_type' => Lead::class,
                    'owner_id' => $lead->id,
                    'converted' => true,
                ]);

                $message = 'Lead created successfully from survey response!';
            }

            // Log communication
            \App\Domains\Communication\Actions::create($user, $team, [
                'communicable_type' => 'lead',
                'communicable_id' => $lead->id,
                'communication_type_id' => 5, // Survey response type
                'direction' => 'inbound',
                'channel_id' => 6, // Email channel
                'subject' => 'Survey response: ' . $response->survey->title,
                'notes' => 'Survey: ' . $response->survey->title . "\n\n" . $this->formatSurveyAnswers($response),
                'is_private' => false,
                'status_id' => 3, // Closed
                'priority_id' => 2, // Medium
                'assigned_to' => $user->id,
                'date_contacted' => $response->submitted_at ?? now()->format('Y-m-d H:i:s'),
                'survey_response_id' => $response->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'lead_id' => $lead->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to convert survey response to lead: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert survey response. Please try again.'
            ], 500);
        }
    }

    /**
     * Format survey answers for communication notes
     */
    private function formatSurveyAnswers($response)
    {
        $answers = $response->answers()->with('question')->get();
        $formatted = [];

        foreach ($answers as $answer) {
            $question = $answer->question->label ?? 'Question';
            $answerText = is_array($answer->answer) ? implode(', ', $answer->answer) : $answer->answer;
            $formatted[] = "Q: {$question}\nA: {$answerText}";
        }

        return implode("\n\n", $formatted);
    }


    public function edit(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);
        $TeamUsers = $team->getTeamUserNames(true);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        $uuid = $request->get('id');
        abort_unless($uuid, 404);

        $survey = Survey::with([
            'questions' => fn($q) => $q->orderBy('order'),
            'followups',
            'responses.answers',
            'user'
        ])->firstWhere('uuid', $uuid);

        abort_unless($survey, 404);

        if ($survey->team_id !== $team->id) {
            return redirect()
                ->route('surveysIndex')
                ->with('alert', 'You do not have permission to update this survey.');
        }

        $weeklyResponses = $survey->responses()
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $totalResponses = $survey->responses()->count();
        $completedResponses = $survey->responses()->whereNotNull('submitted_at')->count();
        $completionRate = $totalResponses > 0
            ? round(($completedResponses / $totalResponses) * 100)
            : 0;

        $avgRating = null;
        $ratingQuestionIds = $survey->questions()
            ->where('type', 'rating')
            ->pluck('id');

        if ($ratingQuestionIds->isNotEmpty()) {
            $avgRating = SurveyResponseAnswer::whereIn('survey_question_id', $ratingQuestionIds)
                ->whereNotNull('answer')
                ->avg('answer');

            $avgRating = $avgRating ? number_format($avgRating, 1) : null;
        }

        $breadcrumbs = json_encode([
            'current' => 'Edit',
            'links' => [
                (object)['url' => route('dashOverview'), 'name' => 'Dashboard'],
                (object)['url' => route('surveysIndex'), 'name' => 'Surveys'],
                (object)['url' => route('surveysShow', ['id' => $survey->uuid]), 'name' => $survey->title]
            ]
        ]);

        return view('crm.surveys.edit', compact(
            'survey',
            'breadcrumbs',
            'user',
            'team',
            'TeamUsers',
            'weeklyResponses',
            'completionRate',
            'avgRating'
        ));
    }


    public function update(Request $request)
    {

        $user = auth()->user();
        $team = $user->currentTeam;
        $isAdmin = $user->canEditTeam($team);
        // $TeamUsers = $team->getTeamUserNames(true);

        if (!$user->hasAccessToCurrentTeam()) {
            abort(403, 'Unauthorized action.');
        }

        $getId = $request->get('id');
        abort_unless($getId, 404);

        $survey = Survey::firstWhere('uuid', $getId);

        abort_unless($survey, 404);

        if ($survey->team_id != $team->id) {
            return redirect()->route('surveysIndex')->with('alert', 'You do not have permission to update this survey.');
        }

        // Validate survey fields
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'public_description' => 'nullable|string',
            'visibility' => 'nullable|in:private,public',
            'type' => 'nullable|in:feedback,lead,followup,custom',
            'status' => 'nullable|boolean',
            'assigned_user_id' => 'nullable|exists:users,id',
            'questions' => 'nullable|array',
            'questions.*.id' => 'nullable', // Can be numeric ID or temp string ID
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
            'color_scheme' => 'nullable|in:default,custom,team',
            'custom_color' => 'nullable|string|max:20',
        ]);

        // Update survey basic fields
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

        // Handle questions
        if (isset($validated['questions'])) {
            $existingQuestionIds = [];

            foreach ($validated['questions'] as $questionData) {
                $questionId = $questionData['id'] ?? null;

                // Check if it's an existing question (numeric ID) or new question (string ID like "q_123_1")
                $isExistingQuestion = $questionId && is_numeric($questionId);

                if ($isExistingQuestion) {
                    // Update existing question
                    $question = $survey->questions()->find($questionId);
                    if ($question) {
                        $question->update([
                            'type' => $questionData['type'],
                            'label' => $questionData['label'],
                            'required' => $questionData['required'] ?? false,
                            'order' => $questionData['order'] ?? 0,
                            'options' => $questionData['options'] ?? null,
                            'config' => $questionData['config'] ?? null,
                            'conditional_logic' => $questionData['conditional_logic'] ?? null,
                        ]);
                        $existingQuestionIds[] = $questionId;
                    }
                } else {
                    // Create new question (ID is either null or a temp string ID)
                    $newQuestion = $survey->questions()->create([
                        'team_id' => $team->id,
                        'type' => $questionData['type'],
                        'label' => $questionData['label'],
                        'required' => $questionData['required'] ?? false,
                        'order' => $questionData['order'] ?? 0,
                        'options' => $questionData['options'] ?? null,
                        'config' => $questionData['config'] ?? null,
                        'conditional_logic' => $questionData['conditional_logic'] ?? null,
                    ]);
                    $existingQuestionIds[] = $newQuestion->id;
                }
            }

            // Delete questions that are no longer in the array
            $survey->questions()->whereNotIn('id', $existingQuestionIds)->delete();
        }

        if ($request->expectsJson()) {
            return response()->json($survey->load('questions'));
        }

        return redirect()->route('surveysShow', ['id' => $survey->uuid])
            ->with('success', 'Survey updated successfully.');
    }

    public function destroy(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $uuid = $request->get('id');
        abort_unless($uuid, 404);
        $survey = Survey::where('uuid', $uuid)->firstOrFail();

        if ($survey->team_id !== $team->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to delete this survey.'
                ], 403);
            }
            return redirect()->route('surveysIndex')
                ->with('alert', 'You do not have permission to delete this survey.');
        }

        $isAdmin = $user->canEditTeam($team);

        if (! $isAdmin && $survey->user_id !== $user->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to delete this survey.'
                ], 403);
            }
            return redirect()->route('surveysIndex')
                ->with('alert', 'You do not have permission to delete this survey.');
        }

        $survey->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Survey deleted successfully.'
            ], 200);
        }

        return redirect()->route('surveysIndex')
            ->with('success', 'Survey deleted successfully.');
    }
    
    public function clone(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        $getId = $request->get('id');
        abort_unless($getId, 404);
        $survey = Survey::firstWhere('uuid', $getId);
        abort_unless($survey, 404);
        if ( $survey->team_id != $team->id ) {
            return redirect()->route('surveysIndex')->with('alert', 'You do not have permission to clone this survey.');
        }
        $newSurvey = $survey->replicate();
        $newSurvey->title = $survey->title . ' (Copy)';
        $newSurvey->uuid = null;
        $newSurvey->status = false;

        $newSurvey->user_id = auth()->id();
        $newSurvey->save();
        
        // Clone questions
        foreach ($survey->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->survey_id = $newSurvey->id;
            $newQuestion->save();
        }
        
        if (request()->expectsJson()) {
            return response()->json($newSurvey->load('questions'), 201);
        }
        
        return redirect()->route('surveysEdit', ['id' => $newSurvey->uuid])->with('success', 'Survey cloned successfully.');
    }

    public function sendToDeal(Request $request)
    {
        try {
            $user = auth()->user();
            $team = $user->currentTeam;

            if (!$user->hasAccessToCurrentTeam()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'survey_id' => 'required|exists:surveys,id',
                'deal_id' => 'required|exists:deals,id',
                'send_option' => 'required|in:immediate,scheduled,cancel',
                'days' => 'nullable|integer|min:1',
                'selected_recipients' => 'required|array',
                'selected_recipients.*.id' => 'required|integer',
                'selected_recipients.*.type' => 'required|in:contact,lead,vendor',
                'send_as_agent_id' => 'nullable|integer', // null means "team"
            ]);

            $survey = Survey::findOrFail($validated['survey_id']);
            $deal = \App\Models\Deal::findOrFail($validated['deal_id']);

            // Verify permissions
            if ($survey->team_id !== $team->id || $deal->team_id !== $team->id) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            // Verify survey access (private surveys must be owned by user, or public surveys)
            if ($survey->visibility === 'private' && $survey->user_id !== $user->id) {
                return response()->json(['error' => 'You do not have access to this survey'], 403);
            }

            if ($validated['send_option'] === 'cancel') {
                return response()->json([
                    'success' => true,
                    'message' => 'Survey sending cancelled'
                ]);
            }

            if (empty($validated['selected_recipients'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recipients selected'
                ], 400);
            }

            // Determine which agent to use for the survey URL
            $sendAsAgentId = $validated['send_as_agent_id'] ?? $user->id;

            // Verify the agent belongs to the team
            $sendAsAgent = \App\Models\User::find($sendAsAgentId);
            if (!$sendAsAgent || !$sendAsAgent->teams()->where('teams.id', $team->id)->exists()) {
                $sendAsAgentId = $user->id; // Fallback to current user
            }

            $sentCount = 0;
            $scheduledAt = null;

            if ($validated['send_option'] === 'scheduled') {
                $days = $validated['days'] ?? 7;
                $scheduledAt = now()->addDays($days);
            }

            foreach ($validated['selected_recipients'] as $recipientData) {
                $recipientId = $recipientData['id'];
                $recipientType = $recipientData['type'];

                // Get the recipient model based on type
                $recipient = null;
                $recipientModelClass = null;

                switch ($recipientType) {
                    case 'contact':
                        $recipient = \App\Models\Contact::find($recipientId);
                        $recipientModelClass = \App\Models\Contact::class;
                        break;
                    case 'lead':
                        $recipient = Lead::find($recipientId);
                        $recipientModelClass = Lead::class;
                        break;
                    case 'vendor':
                        $recipient = \App\Models\Vendor::find($recipientId);
                        $recipientModelClass = \App\Models\Vendor::class;
                        break;
                }

                if (!$recipient || empty($recipient->email)) {
                    continue;
                }

                // Send survey email
                try {
                    $surveyUrl = $survey->getPublicUrl($sendAsAgentId) . '&type=' . $recipientType . '&rid=' . $recipientId . '&did=' . $deal->id;

                    // Create unique hash for tracking
                    do {
                        $hash = \Illuminate\Support\Str::random(24);
                    } while (\App\Models\EmailSent::where('hash', $hash)->exists());

                    // Create EmailSent record immediately (for pending emails)
                    // Use hash as job_id since it's passed to the job and searchable in Redis
                    $emailSentData = [
                        'hash' => $hash,
                        'job_id' => $hash,
                        'opens' => 0,
                        'clicks' => 0,
                        'user_id' => $user->id,
                        'team_id' => $team->id,
                        'recipient_email' => $recipient->email,
                        'recipient_name' => $recipient->first_name . ' ' . ($recipient->last_name ?? ''),
                        'recipient_type' => $recipientModelClass,
                        'recipient_id' => $recipientId,
                        'subject' => 'Survey: ' . $survey->title,
                        'message' => 'Survey invitation: ' . $surveyUrl,
                        'log_communication' => true,
                    ];

                    if ($scheduledAt) {
                        // For scheduled emails, set scheduled_at and leave sent_at null
                        $scheduledDate = \Carbon\Carbon::parse($scheduledAt)
                            ->timezone($user->timezone ?? 'America/Chicago')
                            ->setTime(9, 0, 0);

                        $emailSentData['scheduled_at'] = $scheduledDate;

                        $emailObject = \App\Models\EmailSent::create($emailSentData);

                        // Dispatch the job with the hash
                        \App\Jobs\SendSurveyEmail::dispatch(
                            $survey,
                            $recipient,
                            $surveyUrl,
                            $user,
                            $team,
                            $recipientModelClass,
                            $recipientId,
                            $survey->title,
                            $hash  // Pass the hash so job doesn't create duplicate
                        )->delay($scheduledDate);
                    } else {
                        // For immediate emails, set sent_at
                        $emailSentData['sent_at'] = now();

                        $emailObject = \App\Models\EmailSent::create($emailSentData);

                        // Queue the email to send immediately with the hash
                        \App\Jobs\SendSurveyEmail::dispatch(
                            $survey,
                            $recipient,
                            $surveyUrl,
                            $user,
                            $team,
                            $recipientModelClass,
                            $recipientId,
                            $survey->title,
                            $hash  // Pass the hash so job doesn't create duplicate
                        );
                    }

                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send survey to ' . $recipientType . ': ' . $recipientId . ' - ' . $e->getMessage());
                }
            }

            $message = $scheduledAt
                ? "Survey scheduled to be sent to {$sentCount} recipient(s) in {$days} day(s)"
                : "Survey queued to be sent to {$sentCount} recipient(s)";

            return response()->json([
                'success' => true,
                'message' => $message,
                'sent_count' => $sentCount
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function sendToContact(Request $request)
    {
        try {
            $user = auth()->user();
            $team = $user->currentTeam;

            if (!$user->hasAccessToCurrentTeam()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'survey_id' => 'required|exists:surveys,id',
                'contact_id' => 'required|exists:contacts,id',
                'send_option' => 'required|in:immediate,scheduled,cancel',
                'days' => 'nullable|integer|min:1',
                'selected_recipients' => 'required|array',
                'selected_recipients.*.id' => 'required|integer',
                'selected_recipients.*.type' => 'required|in:contact,lead,vendor',
                'send_as_agent_id' => 'nullable|integer', // null means "team"
            ]);

            $survey = Survey::findOrFail($validated['survey_id']);
            $contact = \App\Models\Contact::findOrFail($validated['contact_id']);

            // Verify permissions
            if ($survey->team_id !== $team->id || $contact->team_id !== $team->id) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            // Verify survey access (private surveys must be owned by user, or public surveys)
            if ($survey->visibility === 'private' && $survey->user_id !== $user->id) {
                return response()->json(['error' => 'You do not have access to this survey'], 403);
            }

            if ($validated['send_option'] === 'cancel') {
                return response()->json([
                    'success' => true,
                    'message' => 'Survey sending cancelled'
                ]);
            }

            if (empty($validated['selected_recipients'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recipients selected'
                ], 400);
            }

            // Determine which agent to use for the survey URL
            $sendAsAgentId = $validated['send_as_agent_id'] ?? $user->id;

            // Verify the agent belongs to the team
            $sendAsAgent = \App\Models\User::find($sendAsAgentId);
            if (!$sendAsAgent || !$sendAsAgent->teams()->where('teams.id', $team->id)->exists()) {
                $sendAsAgentId = $user->id; // Fallback to current user
            }

            $sentCount = 0;
            $scheduledAt = null;

            if ($validated['send_option'] === 'scheduled') {
                $days = $validated['days'] ?? 7;
                $scheduledAt = now()->addDays($days);
            }

            foreach ($validated['selected_recipients'] as $recipientData) {
                $recipientId = $recipientData['id'];
                $recipientType = $recipientData['type'];

                // Get the recipient model based on type
                $recipient = null;
                $recipientModelClass = null;

                switch ($recipientType) {
                    case 'contact':
                        $recipient = \App\Models\Contact::find($recipientId);
                        $recipientModelClass = \App\Models\Contact::class;
                        break;
                    case 'lead':
                        $recipient = Lead::find($recipientId);
                        $recipientModelClass = Lead::class;
                        break;
                    case 'vendor':
                        $recipient = \App\Models\Vendor::find($recipientId);
                        $recipientModelClass = \App\Models\Vendor::class;
                        break;
                }

                if (!$recipient || empty($recipient->email)) {
                    continue;
                }

                // Send survey email
                try {
                    $surveyUrl = $survey->getPublicUrl($sendAsAgentId) . '&type=' . $recipientType . '&rid=' . $recipientId . '&cid=' . $contact->id;

                    // Create unique hash for tracking
                    do {
                        $hash = \Illuminate\Support\Str::random(24);
                    } while (\App\Models\EmailSent::where('hash', $hash)->exists());

                    // Create EmailSent record immediately (for pending emails)
                    // Use hash as job_id since it's passed to the job and searchable in Redis
                    $emailSentData = [
                        'hash' => $hash,
                        'job_id' => $hash,
                        'opens' => 0,
                        'clicks' => 0,
                        'user_id' => $user->id,
                        'team_id' => $team->id,
                        'recipient_email' => $recipient->email,
                        'recipient_name' => $recipient->first_name . ' ' . ($recipient->last_name ?? ''),
                        'recipient_type' => $recipientModelClass,
                        'recipient_id' => $recipientId,
                        'subject' => 'Survey: ' . $survey->title,
                        'message' => 'Survey invitation: ' . $surveyUrl,
                        'log_communication' => true,
                    ];

                    if ($scheduledAt) {
                        // For scheduled emails, set scheduled_at and leave sent_at null
                        $scheduledDate = \Carbon\Carbon::parse($scheduledAt)
                            ->timezone($user->timezone ?? 'America/Chicago')
                            ->setTime(9, 0, 0);

                        $emailSentData['scheduled_at'] = $scheduledDate;

                        $emailObject = \App\Models\EmailSent::create($emailSentData);

                        // Dispatch the job with the hash
                        \App\Jobs\SendSurveyEmail::dispatch(
                            $survey,
                            $recipient,
                            $surveyUrl,
                            $user,
                            $team,
                            $recipientModelClass,
                            $recipientId,
                            $survey->title,
                            $hash  // Pass the hash so job doesn't create duplicate
                        )->delay($scheduledDate);
                    } else {
                        // For immediate emails, set sent_at
                        $emailSentData['sent_at'] = now();

                        $emailObject = \App\Models\EmailSent::create($emailSentData);

                        // Queue the email to send immediately with the hash
                        \App\Jobs\SendSurveyEmail::dispatch(
                            $survey,
                            $recipient,
                            $surveyUrl,
                            $user,
                            $team,
                            $recipientModelClass,
                            $recipientId,
                            $survey->title,
                            $hash  // Pass the hash so job doesn't create duplicate
                        );
                    }

                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send survey to ' . $recipientType . ': ' . $recipientId . ' - ' . $e->getMessage());
                }
            }

            $message = $scheduledAt
                ? "Survey scheduled to be sent to {$sentCount} recipient(s) in {$days} day(s)"
                : "Survey queued to be sent to {$sentCount} recipient(s)";

            return response()->json([
                'success' => true,
                'message' => $message,
                'sent_count' => $sentCount
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function getActiveSurveys(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (!$user->hasAccessToCurrentTeam()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get active surveys based on record type preference (optional filtering)
        $query = Survey::where('team_id', $team->id)
            ->where('status', true)
            ->where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    // Private surveys by the current user
                    $q->where('visibility', 'private')
                      ->where('user_id', $user->id);
                })->orWhere(function($q) {
                    // Public surveys (shared with team)
                    $q->where('visibility', 'public');
                });
            })
            ->select('id', 'uuid', 'title', 'description', 'type', 'automation_config', 'user_id', 'visibility')
            ->orderBy('created_at', 'desc');

        $surveys = $query->get()->map(function($survey) use ($user) {
            $config = $survey->automation_config ?? [];

            return [
                'id' => $survey->id,
                'uuid' => $survey->uuid,
                'title' => $survey->title,
                'description' => $survey->description,
                'type' => $survey->type,
                'automation_config' => $config,
                'is_owner' => $survey->user_id === $user->id,
                'visibility' => $survey->visibility
            ];
        });

        // Get team users for "Send as" dropdown
        $TeamUsersNames = $team->getTeamUserNames();
        $TeamUsers = collect($TeamUsersNames)->map(function ($name, $id) {
            return [
                'id' => $id,
                'name' => $name,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'surveys' => $surveys,
            'team_users' => $TeamUsers,
            'current_user_id' => $user->id,
            'team_owner_id' => $team->user_id
        ]);
    }

    public function sendToRecord(Request $request)
    {
        try {
            $user = auth()->user();
            $team = $user->currentTeam;

            if (!$user->hasAccessToCurrentTeam()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'survey_id' => 'required|exists:surveys,id',
                'record_type' => 'required|in:contact,lead,vendor',
                'record_id' => 'required|integer',
                'send_option' => 'required|in:immediate,scheduled',
                'days' => 'nullable|integer|min:1',
                'send_as_agent_id' => 'nullable|integer', // null means "team"
            ]);

            $survey = Survey::findOrFail($validated['survey_id']);

            // Verify permissions
            if ($survey->team_id !== $team->id) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            // Verify survey access (private surveys must be owned by user, or public surveys)
            if ($survey->visibility === 'private' && $survey->user_id !== $user->id) {
                return response()->json(['error' => 'You do not have access to this survey'], 403);
            }

            // Get the recipient model based on type
            $recipient = null;
            $recipientModelClass = null;
            $recipientType = $validated['record_type'];
            $recipientId = $validated['record_id'];

            switch ($recipientType) {
                case 'contact':
                    $recipient = \App\Models\Contact::where('team_id', $team->id)->find($recipientId);
                    $recipientModelClass = \App\Models\Contact::class;
                    break;
                case 'lead':
                    $recipient = Lead::where('team_id', $team->id)->find($recipientId);
                    $recipientModelClass = Lead::class;
                    break;
                case 'vendor':
                    $recipient = \App\Models\Vendor::where('team_id', $team->id)->find($recipientId);
                    $recipientModelClass = \App\Models\Vendor::class;
                    break;
            }

            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient not found or does not belong to your team'
                ], 404);
            }

            if (empty($recipient->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient does not have an email address'
                ], 400);
            }

            // Determine which agent to use for the survey URL
            $sendAsAgentId = $validated['send_as_agent_id'] ?? $user->id;

            // Verify the agent belongs to the team
            $sendAsAgent = \App\Models\User::find($sendAsAgentId);
            if (!$sendAsAgent || !$sendAsAgent->teams()->where('teams.id', $team->id)->exists()) {
                $sendAsAgentId = $user->id; // Fallback to current user
            }

            // Send survey email
            $scheduledAt = null;
            if ($validated['send_option'] === 'scheduled') {
                $days = $validated['days'] ?? 7;
                $scheduledAt = now()->addDays($days);
            }

            // Build survey URL with agent ID
            $surveyUrl = $survey->getPublicUrl($sendAsAgentId) . '&type=' . $recipientType . '&rid=' . $recipientId;

            try {
                // Create unique hash for tracking
                do {
                    $hash = \Illuminate\Support\Str::random(24);
                } while (\App\Models\EmailSent::where('hash', $hash)->exists());

                // Create EmailSent record immediately (for pending emails)
                // Use hash as job_id since it's passed to the job and searchable in Redis
                $emailSentData = [
                    'hash' => $hash,
                    'job_id' => $hash,
                    'opens' => 0,
                    'clicks' => 0,
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'recipient_email' => $recipient->email,
                    'recipient_name' => $recipient->first_name . ' ' . ($recipient->last_name ?? ''),
                    'recipient_type' => $recipientModelClass,
                    'recipient_id' => $recipientId,
                    'subject' => 'Survey: ' . $survey->title,
                    'message' => 'Survey invitation: ' . $surveyUrl,
                    'log_communication' => true,
                ];

                if ($scheduledAt) {
                    // For scheduled emails, set scheduled_at and leave sent_at null
                    $scheduledDate = \Carbon\Carbon::parse($scheduledAt)
                        ->timezone($user->timezone ?? 'America/Chicago')
                        ->setTime(9, 0, 0);

                    $emailSentData['scheduled_at'] = $scheduledDate;

                    $emailObject = \App\Models\EmailSent::create($emailSentData);

                    // Dispatch the job with the hash
                    \App\Jobs\SendSurveyEmail::dispatch(
                        $survey,
                        $recipient,
                        $surveyUrl,
                        $user,
                        $team,
                        $recipientModelClass,
                        $recipientId,
                        $survey->title,
                        $hash  // Pass the hash so job doesn't create duplicate
                    )->delay($scheduledDate);

                    $message = "Survey scheduled to be sent to {$recipient->first_name} {$recipient->last_name} in {$days} day(s)";
                } else {
                    // For immediate emails, set sent_at
                    $emailSentData['sent_at'] = now();

                    $emailObject = \App\Models\EmailSent::create($emailSentData);

                    // Queue the email to send immediately with the hash
                    \App\Jobs\SendSurveyEmail::dispatch(
                        $survey,
                        $recipient,
                        $surveyUrl,
                        $user,
                        $team,
                        $recipientModelClass,
                        $recipientId,
                        $survey->title,
                        $hash  // Pass the hash so job doesn't create duplicate
                    );

                    $message = "Survey queued to be sent to {$recipient->first_name} {$recipient->last_name}!";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send survey to ' . $recipientType . ': ' . $recipientId . ' - ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send survey. Please try again.'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ], 500);
        }
    }

    public function reassignResponse(Request $request)
    {
        try {
            $user = auth()->user();
            $team = $user->currentTeam;
            $isAdmin = $user->canEditTeam($team);

            if (!$user->hasAccessToCurrentTeam()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'response_id' => 'required|integer',
                'assigned_to' => 'required|exists:users,id',
            ]);

            $response = SurveyResponse::with('survey')->findOrFail($validated['response_id']);

            // Verify the response belongs to the team
            if ($response->survey->team_id !== $team->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Response not found or access denied.'
                ], 403);
            }

            // Check permissions: admins can reassign any response, agents can only reassign their own
            if (!$isAdmin && $response->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to reassign this response.'
                ], 403);
            }

            // Verify the new assignee belongs to the same team
            $newAssignee = \App\Models\User::find($validated['assigned_to']);
            if (!$newAssignee || !$newAssignee->teams()->where('teams.id', $team->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid assignee selected.'
                ], 400);
            }

            // Update the assignment
            $response->update([
                'assigned_to' => $validated['assigned_to']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Response reassigned to {$newAssignee->name} successfully!",
                'assigned_to' => [
                    'id' => $newAssignee->id,
                    'name' => $newAssignee->name
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to reassign survey response: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign response. Please try again.'
            ], 500);
        }
    }

}
