<?php

namespace App\Services\InboundEmail;

use App\Enums\InboundEmail\IngestionStatus;
use App\Jobs\ProcessInboundEmail;
use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;
use App\Support\InboundEmail\InboundEmailAddressParser;
use Illuminate\Support\Facades\Log;

class InboundEmailReceiver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function receive(array $payload): ?AiEmailIngestion
    {
        $toAddress = InboundEmailAddressParser::extractFirst(
            is_string($payload['to'] ?? null) ? $payload['to'] : null
        );

        if ($toAddress === null) {
            Log::warning('Inbound email: missing or invalid recipient address', [
                'to' => $payload['to'] ?? null,
            ]);

            return null;
        }

        $route = EmailRoute::query()
            ->active()
            ->where('address', $toAddress)
            ->first();

        if ($route === null) {
            Log::warning('Inbound email: no active route for address', [
                'address' => $toAddress,
            ]);

            return null;
        }

        $from = is_string($payload['from'] ?? null) ? $payload['from'] : null;
        $subject = is_string($payload['subject'] ?? null) ? $payload['subject'] : null;

        $ingestion = AiEmailIngestion::query()->create([
            'tenant_id' => $route->tenant_id,
            'email_route_id' => $route->id,
            'status' => IngestionStatus::Pending,
            'from_email' => $from,
            'to_email' => $toAddress,
            'subject' => $subject,
            'raw_payload' => $payload,
        ]);

        ProcessInboundEmail::dispatch($ingestion->id);

        return $ingestion;
    }
}
