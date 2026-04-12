<?php

namespace App\Http\Controllers\Tenant\Surveys;

use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Domain\Survey\Models\Survey;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSurveyResponse;
use App\Models\AccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PublicSurveyController extends Controller
{
    public function index(): never
    {
        abort(404);
    }

    public function show(Request $request): Response
    {
        return Inertia::render('Tenant/Public/SurveyIntake', $this->buildIntakeProps($request, embed: false));
    }

    /**
     * Minimal iframe-friendly HTML (no Vue/Inertia). See resources/views/surveys/embed.blade.php.
     */
    public function embed(Request $request): View
    {
        $uuid = $request->query('id');
        abort_unless($uuid, 404);

        $survey = Survey::query()
            ->where('uuid', $uuid)
            ->where('status', true)
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->firstOrFail();

        if ($survey->visibility !== 'public') {
            if (! $this->userCanManageSurvey($survey)) {
                abort(404);
            }
        }

        $aid = $request->query('aid');
        $agentId = null;
        if ($aid !== null && $aid !== '') {
            $aidInt = filter_var($aid, FILTER_VALIDATE_INT);
            if ($aidInt !== false && $aidInt >= 1 && User::query()->whereKey($aidInt)->exists()) {
                $agentId = $aidInt;
            }
        }

        return view('surveys.embed', [
            'survey' => $survey,
            'surveyColor' => $survey->getEffectiveColor(),
            'submitUrl' => route('surveysPublicSubmit', absolute: true),
            'agentId' => $agentId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIntakeProps(Request $request, bool $embed): array
    {
        $uuid = $request->query('id');
        abort_unless($uuid, 404);

        $survey = Survey::query()
            ->with(['questions' => fn ($q) => $q->orderBy('order')])
            ->where('uuid', $uuid)
            ->where('status', true)
            ->firstOrFail();

        $canEdit = $this->userCanManageSurvey($survey);

        if ($survey->visibility !== 'public' && ! $canEdit) {
            abort(404);
        }

        $recipientData = $this->resolveRecipientData($request);
        $agent = $this->resolveAgent($survey, $request->query('aid'));
        $account = AccountSettings::getCurrent();

        return [
            'survey' => $survey,
            'recipientData' => $recipientData,
            'agent' => $this->serializeAgent($agent),
            'surveyColor' => $survey->getEffectiveColor(),
            'defaultSurveyBrand' => config('app.app_brand', '#2663eb'),
            'embed' => $embed,
            'canEdit' => $canEdit,
            'account' => [
                'logo_url' => $account->logo_url,
                'brand_color' => $account->brand_color ?? null,
            ],
            'submitUrl' => route('surveysPublicSubmit'),
            'adminLinks' => $canEdit ? [
                'edit' => route('surveysEdit', ['id' => $survey->uuid]),
                'analytics' => route('surveysShow', ['id' => $survey->uuid]),
                'updateColor' => route('surveysPublicEdit'),
            ] : null,
        ];
    }

    protected function userCanManageSurvey(Survey $survey): bool
    {
        $profile = current_tenant_profile();
        if (! $profile) {
            return false;
        }
        if ((int) $profile->id === (int) $survey->user_id) {
            return true;
        }
        $slug = current_tenant_role_slug();

        return in_array($slug, ['admin', 'owner'], true);
    }

    protected function resolveAgent(Survey $survey, mixed $agentId): User
    {
        $creator = $survey->user;
        if (! $agentId) {
            return $creator;
        }
        $aid = filter_var($agentId, FILTER_VALIDATE_INT);
        if ($aid === false || $aid < 1) {
            return $creator;
        }
        $requested = User::query()->find($aid);

        return $requested instanceof User ? $requested : $creator;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveRecipientData(Request $request): ?array
    {
        $recipientType = $request->query('type');
        $recipientId = $request->query('rid');
        $agentId = $request->query('aid');

        if ($recipientType && $recipientId !== null && $recipientId !== '') {
            $rid = filter_var($recipientId, FILTER_VALIDATE_INT);
            if ($rid === false || $rid < 1) {
                return $agentId ? ['agent_id' => (int) $agentId] : null;
            }

            $recipient = match ($recipientType) {
                'contact' => Contact::query()->find($rid),
                'lead' => Lead::query()->find($rid),
                'vendor' => Vendor::query()->with('primaryContact')->find($rid),
                default => null,
            };

            if (! $recipient) {
                return $agentId ? ['agent_id' => (int) $agentId] : null;
            }

            $name = '';
            $first = '';
            $last = '';
            $email = '';

            if ($recipient instanceof Contact) {
                $first = (string) ($recipient->first_name ?? '');
                $last = (string) ($recipient->last_name ?? '');
                $name = trim($recipient->display_name ?? '') ?: trim($first.' '.$last);
                $email = (string) ($recipient->email ?? '');
            } elseif ($recipient instanceof Lead) {
                $first = (string) ($recipient->first_name ?? '');
                $last = (string) ($recipient->last_name ?? '');
                $name = trim($recipient->display_name ?? '') ?: trim($first.' '.$last);
                $email = (string) ($recipient->email ?? '');
            } elseif ($recipient instanceof Vendor) {
                $pc = $recipient->primaryContact;
                $first = $pc ? (string) ($pc->first_name ?? '') : '';
                $last = $pc ? (string) ($pc->last_name ?? '') : '';
                $name = trim((string) ($recipient->company ?? '')) ?: trim($first.' '.$last);
                $email = $pc ? (string) ($pc->email ?? '') : '';
            }

            return [
                'name' => $name,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'type' => $recipientType,
                'id' => $rid,
                'agent_id' => $agentId ? (int) $agentId : null,
            ];
        }

        if ($agentId) {
            return ['agent_id' => (int) $agentId];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeAgent(User $user): array
    {
        $phone = $user->office_phone ?: $user->mobile_phone;

        return [
            'id' => $user->id,
            'display_name' => $user->display_name ?: $user->full_name,
            'email' => $user->email,
            'office_phone' => $user->office_phone,
            'mobile_phone' => $user->mobile_phone,
            'phone' => $phone,
            'avatar' => $user->avatar,
        ];
    }

    public function submit(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $startTime = $request->input('start_time');
        if ($startTime) {
            $elapsed = (microtime(true) * 1000) - (float) $startTime;
            if ($elapsed < 2000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission too fast. Possible bot detected.',
                ], 429);
            }
        }

        try {
            $uuid = $request->input('id');
            $survey = Survey::query()
                ->where('uuid', $uuid)
                ->where('status', true)
                ->with(['questions'])
                ->firstOrFail();

            if ($survey->visibility !== 'public') {
                if (! $this->userCanManageSurvey($survey)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This survey is not available.',
                    ], 404);
                }
            }

            $validated = $request->validate([
                'answers' => 'required|array',
                'email' => 'nullable|email',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'type' => 'nullable|in:contact,lead,vendor',
                'rid' => 'nullable|integer',
                'aid' => 'nullable|integer',
            ]);

            $answers = $validated['answers'];
            $this->validateRequiredAnswers($survey, $answers);

            $privacySettings = $survey->privacy_settings ?? [];
            if (! empty($privacySettings['one_response_per_user']) && ! empty($validated['email'])) {
                $existingResponse = $survey->responses()
                    ->where('email', $validated['email'])
                    ->first();

                if ($existingResponse) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already submitted a response to this survey.',
                    ], 422);
                }
            }

            [$ownerType, $ownerId] = $this->resolveOwnerFromPayload($validated);

            $assignedTo = $survey->user_id;
            if (! empty($validated['aid'])) {
                $aid = (int) $validated['aid'];
                if (User::query()->whereKey($aid)->exists()) {
                    $assignedTo = $aid;
                }
            }

            $matchedContact = ! empty($validated['email'])
                ? Contact::findByEmailCaseInsensitive($validated['email'])
                : null;

            $surveyResponse = $survey->responses()->create([
                'email' => $validated['email'] ?? null,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'assigned_to' => $assignedTo,
                'sourceable_type' => $matchedContact ? Contact::class : null,
                'sourceable_id' => $matchedContact?->id,
                'submitted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            foreach ($answers as $questionId => $answer) {
                $qid = (int) $questionId;
                if (! $survey->questions->contains('id', $qid)) {
                    continue;
                }
                $value = is_array($answer) ? json_encode($answer) : (string) $answer;
                $surveyResponse->answers()->create([
                    'survey_question_id' => $qid,
                    'answer' => $value,
                ]);
            }

            ProcessSurveyResponse::dispatch($survey->fresh(), $surveyResponse->fresh());

            $responseData = [
                'success' => true,
                'message' => $survey->thank_you_message ?? 'Thank you for your response!',
                'redirect_url' => $survey->redirect_url,
                'response_id' => $surveyResponse->id,
            ];

            if (! empty($privacySettings['show_results'])) {
                $responseData['show_results'] = true;
                $responseData['user_answers'] = $surveyResponse->answers()
                    ->with('question')
                    ->get()
                    ->map(fn ($answer) => [
                        'question' => $answer->question->label ?? 'N/A',
                        'answer' => $answer->answer,
                        'question_type' => $answer->question->type ?? 'text',
                    ]);
                $responseData['aggregate_results'] = $this->getAggregateResults($survey);
            }

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json($responseData);
            }

            if ($survey->redirect_url) {
                return redirect()->away($survey->redirect_url);
            }

            return redirect()->back()->with('success', $survey->thank_you_message ?? 'Thank you for your response!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Survey submission failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while submitting your response. Please try again.',
                ], 500);
            }

            return redirect()->back()->withErrors([
                'error' => 'An error occurred while submitting your response. Please try again.',
            ])->withInput();
        }
    }

    protected function isAnswerEmpty(mixed $val): bool
    {
        if ($val === null) {
            return true;
        }
        if (is_array($val)) {
            return $val === [];
        }
        if ($val === '') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    protected function validateRequiredAnswers(Survey $survey, array $answers): void
    {
        $sorted = $survey->questions->sortBy('order')->values();
        $errors = [];
        foreach ($sorted as $question) {
            if (! $question->required) {
                continue;
            }
            if (! $this->isSurveyQuestionVisible($question, $sorted, $answers)) {
                continue;
            }
            $key = (string) $question->id;
            $hasKey = array_key_exists($key, $answers) || array_key_exists($question->id, $answers);
            if (! $hasKey) {
                $errors['answers.'.$key] = 'This field is required.';

                continue;
            }
            $val = array_key_exists($key, $answers) ? $answers[$key] : $answers[$question->id];
            if ($this->isAnswerEmpty($val)) {
                $errors['answers.'.$key] = 'This field is required.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Matches {@see \resources\js\Pages\Tenant\Public\SurveyIntake.vue} conditional visibility (sorted question index).
     *
     * @param  Collection<int, \App\Domain\Survey\Models\SurveyQuestion>  $sortedQuestions
     * @param  array<string|int, mixed>  $answers
     */
    protected function isSurveyQuestionVisible(object $question, Collection $sortedQuestions, array $answers): bool
    {
        $logic = $question->conditional_logic;
        if ($logic === null || $logic === [] || $logic === '') {
            return true;
        }
        if (! is_array($logic)) {
            return true;
        }

        /** @var list<object> $qs */
        $qs = $sortedQuestions->all();

        if (! empty($logic['rules']) && is_array($logic['rules'])) {
            foreach ($logic['rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                $idx = $rule['question'] ?? null;
                if ($idx === null || ! isset($qs[$idx])) {
                    continue;
                }
                $targetQuestion = $qs[$idx];
                $tid = (string) $targetQuestion->id;
                $targetAnswer = $answers[$tid] ?? $answers[$targetQuestion->id] ?? null;
                if ($targetAnswer == ($rule['equals'] ?? null)) {
                    return true;
                }
            }

            return false;
        }

        $showIdx = $logic['show_if_question'] ?? null;
        $targetQuestion = ($showIdx !== null && isset($qs[$showIdx])) ? $qs[$showIdx] : null;
        if (! $targetQuestion) {
            return true;
        }
        $tid = (string) $targetQuestion->id;
        $targetAnswer = $answers[$tid] ?? $answers[$targetQuestion->id] ?? null;

        if (array_key_exists('equals', $logic)) {
            return $targetAnswer == $logic['equals'];
        }
        if (! empty($logic['equals_any']) && is_array($logic['equals_any'])) {
            return in_array($targetAnswer, $logic['equals_any'], false);
        }

        return true;
    }

    /**
     * @param  array{type?: string, rid?: int}  $validated
     * @return array{0: ?string, 1: ?int}
     */
    protected function resolveOwnerFromPayload(array $validated): array
    {
        if (empty($validated['type']) || empty($validated['rid'])) {
            return [null, null];
        }

        $recipientType = $validated['type'];
        $recipientId = (int) $validated['rid'];

        $modelClass = match ($recipientType) {
            'contact' => Contact::class,
            'lead' => Lead::class,
            'vendor' => Vendor::class,
            default => null,
        };

        if ($modelClass === null) {
            return [null, null];
        }

        $recipient = $modelClass::query()->find($recipientId);

        if (! $recipient) {
            return [null, null];
        }

        return [$modelClass, $recipientId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getAggregateResults(Survey $survey): array
    {
        $questions = $survey->questions()->get();
        $results = [];

        foreach ($questions as $question) {
            $questionData = [
                'question' => $question->label,
                'type' => $question->type,
                'total_responses' => 0,
            ];

            $answers = $question->answers()->get();
            $questionData['total_responses'] = $answers->count();

            if (in_array($question->type, ['multiple_choice', 'dropdown'], true)) {
                $distribution = [];
                foreach ($answers as $answer) {
                    $value = (string) $answer->answer;
                    $distribution[$value] = ($distribution[$value] ?? 0) + 1;
                }

                $questionData['distribution'] = collect($distribution)->map(function ($count) use ($questionData) {
                    return [
                        'count' => $count,
                        'percentage' => $questionData['total_responses'] > 0
                            ? round(($count / $questionData['total_responses']) * 100, 1)
                            : 0,
                    ];
                })->toArray();
            } elseif ($question->type === 'rating') {
                $numeric = $answers->map(fn ($a) => (float) $a->answer)->filter(fn ($v) => $v > 0 || (string) $v === '0');
                $questionData['average'] = $numeric->isNotEmpty()
                    ? round($numeric->avg(), 2)
                    : 0;
            } elseif ($question->type === 'nps') {
                $scores = $answers->map(fn ($a) => (int) $a->answer);
                $promoters = $scores->filter(fn ($s) => $s >= 9)->count();
                $detractors = $scores->filter(fn ($s) => $s <= 6)->count();
                $questionData['nps_score'] = $questionData['total_responses'] > 0
                    ? (int) round((($promoters - $detractors) / $questionData['total_responses']) * 100)
                    : 0;
            }

            $results[] = $questionData;
        }

        return $results;
    }

    public function edit(Request $request): JsonResponse
    {
        $uuid = $request->input('survey_id') ?? $request->query('id');

        if (! $uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Survey ID is required.',
            ], 400);
        }

        $survey = Survey::query()->where('uuid', $uuid)->firstOrFail();

        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to edit this survey.',
            ], 401);
        }

        if (! $this->userCanManageSurvey($survey)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this survey.',
            ], 403);
        }

        $validated = $request->validate([
            'color_scheme' => 'nullable|in:default,custom,team',
            'custom_color' => 'nullable|string|max:20',
        ]);

        // Color customization: allowed for survey managers in this app (no separate billing gate yet).
        $updateData = [];
        if (array_key_exists('color_scheme', $validated)) {
            $updateData['color_scheme'] = $validated['color_scheme'];
        }
        if (array_key_exists('custom_color', $validated)) {
            $updateData['custom_color'] = $validated['custom_color'];
        }

        if ($updateData !== []) {
            $survey->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Survey updated successfully.',
            'data' => [
                'color_scheme' => $survey->color_scheme,
                'custom_color' => $survey->custom_color,
                'effective_color' => $survey->getEffectiveColor(),
            ],
        ]);
    }
}
