<?php

namespace App\Jobs;

use App\Domain\InboundEmail\InboundEmailActionFactory;
use App\Enums\InboundEmail\IngestionStatus;
use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $ingestionId
    ) {}

    public function handle(InboundEmailActionFactory $actionFactory): void
    {
        $ingestion = AiEmailIngestion::query()->find($this->ingestionId);
        if ($ingestion === null) {
            Log::warning('ProcessInboundEmail: ingestion not found', [
                'ingestion_id' => $this->ingestionId,
            ]);

            return;
        }

        if ($ingestion->status !== IngestionStatus::Pending) {
            return;
        }

        $ingestion->markProcessing();

        $route = $ingestion->email_route_id
            ? EmailRoute::query()->find($ingestion->email_route_id)
            : null;

        if ($route === null) {
            $ingestion->markFailed('Email route not found for ingestion.');
            Log::error('ProcessInboundEmail: email route missing', [
                'ingestion_id' => $ingestion->id,
            ]);

            return;
        }

        $tenant = Tenant::query()->find($route->tenant_id);
        if ($tenant === null) {
            $ingestion->markFailed('Tenant not found for email route.');
            Log::error('ProcessInboundEmail: tenant initialization failed', [
                'ingestion_id' => $ingestion->id,
                'tenant_id' => $route->tenant_id,
            ]);

            return;
        }

        try {
            Tenancy::initialize($tenant);

            $result = $actionFactory->make($route)->execute($ingestion, $route);

            $ingestion->markCompleted($result);
        } catch (Throwable $e) {
            $ingestion->markFailed($e->getMessage());
            Log::error('ProcessInboundEmail failed', [
                'ingestion_id' => $ingestion->id,
                'tenant_id' => $route->tenant_id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            Tenancy::end();
        }
    }
}
