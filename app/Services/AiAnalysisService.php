<?php

namespace App\Services;

use App\Models\AiSurveyAnalysis;
use App\Models\AiUsage;
use App\Models\Survey\SurveyResponse;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisService
{
    protected AiPromptBuilder $promptBuilder;

    public function __construct(AiPromptBuilder $promptBuilder)
    {
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Analyze a survey response using GPT-5 Full.
     *
     * @param Team $team
     * @param SurveyResponse $surveyResponse
     * @param int|null $userId
     * @return AiSurveyAnalysis
     * @throws \Exception
     */
    public function analyzeSurvey(Team $team, SurveyResponse $surveyResponse, ?int $userId = null): AiSurveyAnalysis
    {
        // Check if team can use AI
        if (!$this->canTeamUseAi($team)) {
            throw new \Exception('Your subscription does not include AI analysis features.');
        }

        // Check AI quota
        if (!$this->hasAiQuota($team)) {
            throw new \Exception('Monthly AI analysis limit reached. Please upgrade your plan.');
        }

        // Get user timezone if userId is provided
        $userTimezone = null;
        if ($userId) {
            $user = \App\Models\User::find($userId);
            $userTimezone = $user?->timezone ?? 'UTC';
        }

        // Build the prompt
        $prompt = $this->promptBuilder->buildPrompt($surveyResponse, $userTimezone);
        $schemaType = $this->promptBuilder->getSchemaType($surveyResponse);
        $schema = config("ai_schemas.{$schemaType}");

        if (!$schema) {
            throw new \Exception("Invalid schema type: {$schemaType}");
        }

        // Prepare survey type for storage
        $surveyType = str_replace('survey_', '', $schemaType); // 'survey_lead' -> 'lead'

        // Call OpenAI API
        try {
            $analysisResult = $this->callOpenAi($prompt, $schema, $schemaType);
        } catch (\Exception $e) {
            Log::error('OpenAI API call failed', [
                'error' => $e->getMessage(),
                'team_id' => $team->id,
                'survey_response_id' => $surveyResponse->id,
            ]);
            throw new \Exception('AI analysis failed. Please try again later.');
        }

        // Prepare input data
        $inputData = [
            'survey_id' => $surveyResponse->survey_id,
            'survey_type' => $surveyResponse->survey->type,
            'survey_title' => $surveyResponse->survey->title,
            'submitted_at' => $surveyResponse->submitted_at?->toIso8601String(),
            'answers' => $surveyResponse->answers->map(function ($answer) {
                return [
                    'question' => $answer->question->question ?? 'N/A',
                    'answer' => $answer->answer,
                    'meta' => $answer->meta,
                ];
            })->toArray(),
        ];

        // Save analysis result
        $analysis = AiSurveyAnalysis::create([
            'survey_response_id' => $surveyResponse->id,
            'team_id' => $team->id,
            'user_id' => $userId,
            'survey_type' => $surveyType,
            'input_data' => $inputData,
            'analysis_result' => $analysisResult,
            'confidence' => $this->calculateConfidence($analysisResult),
        ]);

        // Update AI usage
        $this->incrementAiUsage($team, $analysisResult['usage']['total_tokens'] ?? 0);

        return $analysis;
    }

    /**
     * Call OpenAI API with structured output.
     */
    protected function callOpenAi(string $prompt, array $schema, string $schemaName): array
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (!$apiKey) {
            throw new \Exception('OpenAI API key not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o', // Using GPT-4o as GPT-5 is not yet released
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert real estate CRM analyst. Analyze survey responses and provide structured insights to help agents better serve their clients.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
            'temperature' => 0.7,
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $data = $response->json();

        // Extract the JSON content
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new \Exception('Invalid response from OpenAI API.');
        }

        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
        }

        // Add usage information
        $result['usage'] = $data['usage'] ?? [];

        return $result;
    }

    /**
     * Check if team can use AI based on subscription tier.
     */
    protected function canTeamUseAi(Team $team): bool
    {
        $subscription = $team->cachedActiveSubscription();

        if (!$subscription) {
            return false;
        }

        $tier = $subscription->level ?? 1;
        $aiConfig = config("global.subscriptionOptions.{$tier}.ai");

        // Tier 1 (Trial) can use free credits
        if ($tier == 1) {
            $aiUsage = $team->aiUsage ?? AiUsage::firstOrCreate(['team_id' => $team->id]);
            return $aiUsage->hasFreeCredits();
        }

        // Tier 2+ check ai_access flag
        return $aiConfig['ai_access'] ?? false;
    }

    /**
     * Check if team has AI quota available.
     */
    protected function hasAiQuota(Team $team): bool
    {
        $subscription = $team->cachedActiveSubscription();

        if (!$subscription) {
            return false;
        }

        $tier = $subscription->level ?? 1;
        $aiConfig = config("global.subscriptionOptions.{$tier}.ai");

        $aiUsage = $team->aiUsage ?? AiUsage::firstOrCreate(['team_id' => $team->id]);

        // Check free credits for Trial tier
        if ($tier == 1) {
            return $aiUsage->hasFreeCredits();
        }

        // Check monthly limit for other tiers
        $monthlyLimit = $aiConfig['monthly_ai_limit'] ?? 0;
        $currentUsage = $aiUsage->survey_analysis_monthly ?? 0;

        return $currentUsage < $monthlyLimit;
    }

    /**
     * Increment AI usage counters.
     */
    protected function incrementAiUsage(Team $team, int $tokensUsed = 0): void
    {
        $aiUsage = $team->aiUsage ?? AiUsage::firstOrCreate(['team_id' => $team->id]);

        $subscription = $team->cachedActiveSubscription();
        $tier = $subscription->level ?? 1;

        // For Trial tier, consume free credit
        if ($tier == 1 && $aiUsage->hasFreeCredits()) {
            $aiUsage->consumeFreeCredit();
        }

        // Increment survey analysis counters
        $aiUsage->incrementUsage('survey_analysis', $tokensUsed);
    }

    /**
     * Calculate confidence score based on analysis result.
     */
    protected function calculateConfidence(array $result): float
    {
        // Simple heuristic: presence of key fields increases confidence
        $confidence = 0.5; // Base confidence

        // Check for required fields
        if (isset($result['lead_score']) || isset($result['satisfaction_score'])) {
            $confidence += 0.2;
        }

        if (isset($result['suggested_tasks']) && count($result['suggested_tasks']) > 0) {
            $confidence += 0.15;
        }

        if (isset($result['follow_up_tasks']) && count($result['follow_up_tasks']) > 0) {
            $confidence += 0.15;
        }

        if (isset($result['score_reasoning']) || isset($result['key_sentiment'])) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Get AI usage statistics for a team.
     */
    public function getTeamAiUsage(Team $team): array
    {
        $aiUsage = $team->aiUsage;
        $subscription = $team->cachedActiveSubscription();
        $tier = $subscription->level ?? 1;
        $aiConfig = config("global.subscriptionOptions.{$tier}.ai");

        if (!$aiUsage) {
            $aiUsage = AiUsage::firstOrCreate(['team_id' => $team->id]);
        }

        $monthlyLimit = $aiConfig['monthly_ai_limit'] ?? 0;
        $freeCredits = $aiConfig['ai_free_analysis'] ?? 0;

        return [
            'tier' => $tier,
            'tier_name' => $aiConfig['name'] ?? 'Unknown',
            'ai_access' => $aiConfig['ai_access'] ?? false,
            'monthly_limit' => $monthlyLimit,
            'monthly_used' => $aiUsage->survey_analysis_monthly ?? 0,
            'monthly_remaining' => max(0, $monthlyLimit - ($aiUsage->survey_analysis_monthly ?? 0)),
            'total_used' => $aiUsage->survey_analysis_total ?? 0,
            'free_credits_total' => $freeCredits,
            'free_credits_used' => $aiUsage->free_credits_used ?? 0,
            'free_credits_remaining' => max(0, $freeCredits - ($aiUsage->free_credits_used ?? 0)),
            'tokens_used_monthly' => $aiUsage->tokens_used_monthly ?? 0,
            'tokens_used_total' => $aiUsage->tokens_used_total ?? 0,
            'last_analysis_at' => $aiUsage->last_analysis_at?->toIso8601String(),
            'last_reset_at' => $aiUsage->last_reset_at?->toIso8601String(),
        ];
    }
}
