<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Financing\Models\Financing;
use App\Domain\User\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckFinancingThresholds extends Command
{
    protected $signature = 'financing:check-thresholds
                            {--all-tenants : Run inside each tenant database}
                            {--tenants=* : Tenant id(s) when using --all-tenants}';

    protected $description = 'Notify users when financed units exceed days-in-inventory and interest-cost thresholds.';

    public function handle(NotificationService $notifications): int
    {
        if ($this->option('all-tenants')) {
            $tenantIds = array_values(array_filter((array) $this->option('tenants')));
            $forTenants = $tenantIds !== [] ? $tenantIds : null;
            $failed = false;

            tenancy()->runForMultiple($forTenants, function () use ($notifications, &$failed): void {
                $label = tenancy()->tenant?->getTenantKey() ?? '?';
                $this->line("--- Tenant {$label} ---");
                if ($this->checkTenant($notifications) === self::FAILURE) {
                    $failed = true;
                }
            });

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        return $this->checkTenant($notifications);
    }

    private function checkTenant(NotificationService $notifications): int
    {
        $notified = 0;

        Financing::query()
            ->active()
            ->with(['assetUnit', 'vendor'])
            ->chunkById(100, function ($financings) use ($notifications, &$notified): void {
                foreach ($financings as $financing) {
                    $metrics = $financing->metrics();

                    if (! $metrics->isAtRisk()) {
                        continue;
                    }

                    if ($this->wasRecentlyNotified($financing)) {
                        continue;
                    }

                    $userIds = User::query()->pluck('id')->all();
                    foreach ($userIds as $userId) {
                        $notifications->notifyFinancingAtRisk($financing, $metrics->toArray(), (int) $userId);
                    }

                    $financing->update(['alert_notified_at' => now()]);
                    $notified++;
                }
            });

        $this->info("Financing threshold alerts sent for {$notified} record(s).");

        return self::SUCCESS;
    }

    private function wasRecentlyNotified(Financing $financing): bool
    {
        if ($financing->alert_notified_at === null) {
            return false;
        }

        return $financing->alert_notified_at->greaterThan(Carbon::now()->subDays(7));
    }
}
