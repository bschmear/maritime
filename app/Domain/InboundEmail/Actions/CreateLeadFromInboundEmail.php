<?php

namespace App\Domain\InboundEmail\Actions;

use App\Contracts\InboundEmail\InboundEmailAction;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Models\Lead;
use App\Domain\Score\Actions\CreateScore;
use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;
use App\Services\Ai\LeadExtractionService;
use App\Support\InboundEmail\InboundEmailBodyExtractor;
use App\Support\InboundEmail\LeadExtractionMapper;
use App\Support\OpenAi\OpenAiModelResolver;
use App\Support\OpenAi\OpenAiRequestType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateLeadFromInboundEmail implements InboundEmailAction
{
    public function __construct(
        protected LeadExtractionService $leadExtractionService,
        protected LeadExtractionMapper $leadExtractionMapper,
        protected CreateLead $createLead,
        protected CreateScore $createScore,
    ) {}

    public function execute(AiEmailIngestion $ingestion, EmailRoute $route): array
    {
        $payload = $ingestion->raw_payload ?? [];
        $subject = trim((string) ($ingestion->subject ?? $payload['subject'] ?? ''));
        $body = InboundEmailBodyExtractor::extract($payload);

        $extracted = $this->leadExtractionService->extract($subject, $body);
        $leadData = $this->leadExtractionMapper->toLeadPayload($extracted, $route, $ingestion, $body);
        $scoreData = $this->leadExtractionMapper->buildScoreData(
            $extracted,
            $leadData['budget_min'] ?? null,
            $leadData['budget_max'] ?? null,
        );

        return DB::transaction(function () use ($leadData, $scoreData, $extracted, $ingestion, $route) {
            $leadResult = ($this->createLead)($leadData);

            if (! ($leadResult['success'] ?? false) || $leadResult['record'] === null) {
                throw new RuntimeException($leadResult['message'] ?? 'Failed to create lead from inbound email.');
            }

            /** @var Lead $lead */
            $lead = $leadResult['record'];

            $scoreResult = ($this->createScore)([
                'scorable_type' => Lead::class,
                'scorable_id' => $lead->getKey(),
                'user_id' => $lead->assigned_user_id,
                'score_type' => 'behavior',
                'score_value' => $scoreData['score'],
                'meta' => [
                    'breakdown' => $scoreData['breakdown'],
                    'reason' => 'AI Inbox email lead extraction',
                    'stage' => 'lead_intake',
                    'model_version' => OpenAiModelResolver::resolve(OpenAiRequestType::DocumentExtract),
                    'auto_generated' => true,
                    'event_id' => $ingestion->id,
                ],
                'notes' => Str::limit('AI Inbox: '.$route->address, 250, ''),
            ]);

            if (! ($scoreResult['success'] ?? false) || $scoreResult['record'] === null) {
                throw new RuntimeException($scoreResult['message'] ?? 'Failed to create lead score from inbound email.');
            }

            return [
                'action' => 'create_lead',
                'lead_id' => $lead->id,
                'score_id' => $scoreResult['record']->id,
                'score' => $scoreData['score'],
                'extracted' => $extracted,
            ];
        });
    }
}
