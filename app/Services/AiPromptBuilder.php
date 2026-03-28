<?php

namespace App\Services;

use App\Models\Survey\SurveyResponse;

class AiPromptBuilder
{
    /**
     * Build a prompt for lead survey analysis.
     */
    public function buildLeadSurveyPrompt(SurveyResponse $surveyResponse, ?string $userTimezone = null): string
    {
        $survey = $surveyResponse->survey;
        $answers = $surveyResponse->answers()->with('question')->get();
        $timezone = $userTimezone ?? 'UTC';

        $promptParts = [
            "You are an AI assistant analyzing a lead survey response for a real estate CRM system.",
            "",
            "Survey Information:",
            "- Survey Title: {$survey->title}",
            "- Survey Description: {$survey->description}",
            "- Response Date: {$surveyResponse->submitted_at}",
            "",
            "Agent Information:",
            "- Agent Timezone: {$timezone}",
            "",
            "Respondent Information:",
        ];

        // Add respondent details if available
        if ($surveyResponse->first_name || $surveyResponse->last_name) {
            $promptParts[] = "- Name: {$surveyResponse->first_name} {$surveyResponse->last_name}";
        }
        if ($surveyResponse->email) {
            $promptParts[] = "- Email: {$surveyResponse->email}";
        }

        $promptParts[] = "";
        $promptParts[] = "Survey Responses:";

        // Add question-answer pairs
        foreach ($answers as $answer) {
            $question = $answer->question;
            $promptParts[] = "";
            $promptParts[] = "Q: {$question->question}";
            
            // Format answer with context based on question type
            $formattedAnswer = $this->formatAnswer($answer, $question);
            $promptParts[] = "A: {$formattedAnswer}";
            
            // Include meta information if available
            if ($answer->meta && is_array($answer->meta)) {
                $promptParts[] = "  (Additional context: " . json_encode($answer->meta) . ")";
            }
        }

        $promptParts[] = "";
        $promptParts[] = "Based on this lead survey response, please analyze the quality and potential of this lead.";
        $promptParts[] = "Provide:";
        $promptParts[] = "1. A lead_score (1-100) assessing how qualified this lead is";
        $promptParts[] = "2. score_reasoning explaining your assessment";
        $promptParts[] = "3. suggested_tasks - specific actionable tasks the agent should complete";
        $promptParts[] = "4. follow_up_message - a personalized message to send to this lead";
        $promptParts[] = "5. recommended_send_time - optimal time to send the follow-up (ISO 8601 format with timezone)";
        $promptParts[] = "   - IMPORTANT: Use the agent's timezone ({$timezone}) for scheduling";
        $promptParts[] = "   - Schedule between 9 AM and 5 PM in the agent's timezone";
        $promptParts[] = "   - For urgent leads: within 1-2 business days at 10 AM";
        $promptParts[] = "   - For warm leads: within 3-5 business days at 2 PM";
        $promptParts[] = "   - For cold leads: within 1 week at 10 AM";
        $promptParts[] = "   - Avoid early mornings (before 9 AM) and late evenings (after 6 PM)";
        $promptParts[] = "   - Example format: 2025-11-25T10:00:00-05:00 (for EST timezone)";
        $promptParts[] = "";
        $promptParts[] = "Consider factors like urgency, budget indicators, timeline, motivation, and engagement level.";

        return implode("\n", $promptParts);
    }

    /**
     * Build a prompt for follow-up survey analysis.
     */
    public function buildFollowUpSurveyPrompt(SurveyResponse $surveyResponse, ?string $userTimezone = null): string
    {
        $survey = $surveyResponse->survey;
        $answers = $surveyResponse->answers()->with('question')->get();
        $timezone = $userTimezone ?? 'UTC';

        $promptParts = [
            "You are an AI assistant analyzing a follow-up survey response for a real estate CRM system.",
            "",
            "Survey Information:",
            "- Survey Title: {$survey->title}",
            "- Survey Description: {$survey->description}",
            "- Response Date: {$surveyResponse->submitted_at}",
            "",
            "Agent Information:",
            "- Agent Timezone: {$timezone}",
        ];

        // Add rating if available
        if ($surveyResponse->rating) {
            $promptParts[] = "- Overall Rating: {$surveyResponse->rating}/10";
        }

        $promptParts[] = "";
        $promptParts[] = "Respondent Information:";

        // Add respondent details if available
        if ($surveyResponse->first_name || $surveyResponse->last_name) {
            $promptParts[] = "- Name: {$surveyResponse->first_name} {$surveyResponse->last_name}";
        }
        if ($surveyResponse->email) {
            $promptParts[] = "- Email: {$surveyResponse->email}";
        }

        $promptParts[] = "";
        $promptParts[] = "Survey Responses:";

        // Add question-answer pairs
        foreach ($answers as $answer) {
            $question = $answer->question;
            $promptParts[] = "";
            $promptParts[] = "Q: {$question->question}";
            
            // Format answer with context based on question type
            $formattedAnswer = $this->formatAnswer($answer, $question);
            $promptParts[] = "A: {$formattedAnswer}";
            
            // Include meta information if available
            if ($answer->meta && is_array($answer->meta)) {
                $promptParts[] = "  (Additional context: " . json_encode($answer->meta) . ")";
            }
        }

        $promptParts[] = "";
        $promptParts[] = "Based on this follow-up survey response, please analyze customer satisfaction and engagement.";
        $promptParts[] = "Provide:";
        $promptParts[] = "1. A satisfaction_score (1-10) indicating overall customer satisfaction";
        $promptParts[] = "2. key_sentiment - a brief summary of the customer's sentiment";
        $promptParts[] = "3. suggested_response - a personalized response message";
        $promptParts[] = "4. next_contact_timing - recommended timing for next follow-up (e.g., '3 days', '1 week')";
        $promptParts[] = "5. follow_up_tasks - specific tasks with:";
        $promptParts[] = "   - task_name: Clear task description";
        $promptParts[] = "   - due_date: ISO 8601 timestamp with timezone between 9 AM - 5 PM in agent's timezone";
        $promptParts[] = "   - reminder: ISO 8601 timestamp with timezone (usually 1 day before due_date at 9 AM)";
        $promptParts[] = "   - priority: 'low', 'medium', or 'high'";
        $promptParts[] = "   - notes: Additional context or instructions";
        $promptParts[] = "";
        $promptParts[] = "IMPORTANT: Use the agent's timezone ({$timezone}) for ALL timestamps.";
        $promptParts[] = "All timestamps should be scheduled during business hours (9 AM - 5 PM) in the agent's timezone.";
        $promptParts[] = "Avoid scheduling before 9 AM or after 6 PM to respect business hours.";
        $promptParts[] = "Example format: 2025-11-25T10:00:00-05:00 (for EST timezone)";
        $promptParts[] = "";
        $promptParts[] = "Consider factors like satisfaction level, concerns raised, opportunities for upselling, and relationship strength.";

        return implode("\n", $promptParts);
    }

    /**
     * Build prompt based on survey type.
     */
    public function buildPrompt(SurveyResponse $surveyResponse, ?string $userTimezone = null): string
    {
        $surveyType = $surveyResponse->survey->type ?? 'lead';

        return match($surveyType) {
            'follow_up' => $this->buildFollowUpSurveyPrompt($surveyResponse, $userTimezone),
            default => $this->buildLeadSurveyPrompt($surveyResponse, $userTimezone),
        };
    }

    /**
     * Get the schema type based on survey type.
     */
    public function getSchemaType(SurveyResponse $surveyResponse): string
    {
        $surveyType = $surveyResponse->survey->type ?? 'lead';

        return match($surveyType) {
            'follow_up' => 'survey_follow_up',
            default => 'survey_lead',
        };
    }

    /**
     * Format answer with context based on question type.
     */
    protected function formatAnswer($answer, $question): string
    {
        $answerText = $answer->answer;
        $questionType = $question->type ?? 'text';
        $config = $question->config ?? [];

        // For rating questions, include the scale context
        if ($questionType === 'rating' || $questionType === 'star_rating') {
            $max = $config['max'] ?? $config['stars'] ?? 5; // Default to 5 if not specified
            if ($max == 5) {
                return "{$answerText} out of 5 stars";
            } else {
                return "{$answerText} out of {$max} points";
            }
        }

        // For NPS questions (Net Promoter Score), include the scale context
        if ($questionType === 'nps') {
            return "{$answerText} out of 10 (Net Promoter Score)";
        }

        // For scale questions, include the range
        if ($questionType === 'scale' || $questionType === 'slider') {
            $min = $config['min'] ?? 1;
            $max = $config['max'] ?? 10;
            return "{$answerText} (on a scale of {$min} to {$max})";
        }

        // For yes/no questions, ensure clarity
        if ($questionType === 'yes_no' || $questionType === 'boolean') {
            return strtolower($answerText) === 'true' || strtolower($answerText) === 'yes' || $answerText === '1' 
                ? 'Yes' 
                : 'No';
        }

        // For multiple choice, if options are available, keep as is
        // For text, textarea, date, etc., return as is
        return $answerText;
    }
}

