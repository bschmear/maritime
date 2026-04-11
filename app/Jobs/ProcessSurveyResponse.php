<?php

namespace App\Jobs;

use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyResponse;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for post-survey notifications (email, communications, tasks).
 * Extend this job when wiring maritime notifications.
 */
class ProcessSurveyResponse
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public SurveyResponse $response,
    ) {}

    public function handle(): void
    {
        Log::debug('ProcessSurveyResponse: stub', [
            'survey_id' => $this->survey->id,
            'response_id' => $this->response->id,
        ]);
    }
}
