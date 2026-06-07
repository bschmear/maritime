<?php

declare(strict_types=1);

namespace App\Domain\AccountSetup\Services;

use App\Domain\AccountSetup\Models\AccountSetupStep;
use App\Domain\AccountSetup\Models\AccountSetupStepProgress;
use App\Enums\AccountSetupStepStatus;
use App\Models\AccountSettings;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AccountSetupService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function stepsForUser(): Collection
    {
        $this->ensureProgressRows();

        return AccountSetupStep::query()
            ->where('is_active', true)
            ->with('progress')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (AccountSetupStep $step) => $this->userCanSeeStep($step))
            ->values()
            ->map(fn (AccountSetupStep $step) => $this->serializeStep($step));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextStep(): ?array
    {
        return $this->stepsForUser()
            ->first(fn (array $step) => $step['status'] === AccountSetupStepStatus::Pending->value);
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $steps = $this->stepsForUser();

        $completed = $steps->where('status', AccountSetupStepStatus::Completed->value)->count();
        $skipped = $steps->where('status', AccountSetupStepStatus::Skipped->value)->count();
        $pending = $steps->where('status', AccountSetupStepStatus::Pending->value)->count();
        $total = $steps->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'skipped' => $skipped,
            'pending' => $pending,
            'resolved' => $completed + $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetPayload(): array
    {
        $settings = AccountSettings::getCurrent();

        if (! $settings->onboarding_complete || $settings->account_setup_complete) {
            return [
                'show_widget' => false,
            ];
        }

        if (Auth::guard('web')->user() === null) {
            return [
                'show_widget' => false,
            ];
        }

        $summary = $this->summary();
        $pendingSteps = $this->stepsForUser()
            ->filter(fn (array $step) => $step['status'] === AccountSetupStepStatus::Pending->value)
            ->values()
            ->all();

        return [
            'show_widget' => $summary['pending'] > 0,
            'next_step' => $pendingSteps[0] ?? null,
            'pending_steps' => $pendingSteps,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boardPayload(): array
    {
        $settings = AccountSettings::getCurrent();
        $steps = $this->stepsForUser();
        $grouped = $steps
            ->groupBy('feature_area')
            ->map(fn (Collection $group, string $area) => [
                'area' => $area,
                'label' => $this->featureAreaLabel($area),
                'steps' => $group->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'account_setup_complete' => (bool) $settings->account_setup_complete,
            'summary' => $this->summary(),
            'groups' => $grouped,
        ];
    }

    public function markStep(string $key, AccountSetupStepStatus $status): void
    {
        if (! in_array($status, [AccountSetupStepStatus::Completed, AccountSetupStepStatus::Skipped], true)) {
            return;
        }

        $step = AccountSetupStep::query()->where('key', $key)->where('is_active', true)->firstOrFail();

        if (! $this->userCanSeeStep($step)) {
            abort(403);
        }

        AccountSetupStepProgress::query()->updateOrCreate(
            ['account_setup_step_id' => $step->id],
            [
                'status' => $status,
                'resolved_at' => now(),
                'resolved_by_id' => current_tenant_user_id(),
            ],
        );

        $this->syncAccountSetupComplete();
    }

    public function syncAccountSetupComplete(): void
    {
        $settings = AccountSettings::getCurrent();
        $this->ensureProgressRows();

        $activeStepIds = AccountSetupStep::query()
            ->where('is_active', true)
            ->pluck('id');

        if ($activeStepIds->isEmpty()) {
            $settings->account_setup_complete = true;
            $settings->save();

            return;
        }

        $pendingCount = AccountSetupStepProgress::query()
            ->whereIn('account_setup_step_id', $activeStepIds)
            ->where('status', AccountSetupStepStatus::Pending)
            ->count();

        $settings->account_setup_complete = $pendingCount === 0;
        $settings->save();
    }

    public function ensureProgressRows(): void
    {
        $steps = AccountSetupStep::query()->where('is_active', true)->get(['id']);

        foreach ($steps as $step) {
            AccountSetupStepProgress::query()->firstOrCreate(
                ['account_setup_step_id' => $step->id],
                ['status' => AccountSetupStepStatus::Pending],
            );
        }
    }

    public function resolveStepUrl(AccountSetupStep $step): string
    {
        $params = $step->route_params ?? [];
        $query = [];

        if (isset($params['tab'])) {
            $query['tab'] = $params['tab'];
            unset($params['tab']);
        }

        $url = Route::has($step->route_name)
            ? route($step->route_name, $params)
            : '/';

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeStep(AccountSetupStep $step): array
    {
        $status = $step->progress?->status ?? AccountSetupStepStatus::Pending;

        return [
            'id' => $step->id,
            'key' => $step->key,
            'title' => $step->title,
            'description' => $step->description,
            'feature_area' => $step->feature_area,
            'icon' => $step->icon,
            'url' => $this->resolveStepUrl($step),
            'status' => $status->value,
            'sort_order' => $step->sort_order,
        ];
    }

    protected function userCanSeeStep(AccountSetupStep $step): bool
    {
        if ($step->permission === null || $step->permission === '') {
            return true;
        }

        if (Auth::guard('web')->user() === null) {
            return false;
        }

        return app(CurrentTenantProfile::class)->hasPermission($step->permission);
    }

    protected function featureAreaLabel(string $area): string
    {
        return match ($area) {
            'account' => 'Account & settings',
            'team' => 'Team & access',
            'inventory' => 'Inventory & catalog',
            'operations' => 'Operations',
            default => ucfirst(str_replace('_', ' ', $area)),
        };
    }
}
