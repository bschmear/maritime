<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\AccountSetup\Services\AccountSetupService;
use App\Enums\AccountSetupStepStatus;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountSetupController extends Controller
{
    public function __construct(
        private readonly AccountSetupService $accountSetup,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $settings = AccountSettings::getCurrent();

        if (! $settings->onboarding_complete) {
            return redirect()->route('dashboard');
        }

        if ($settings->account_setup_complete) {
            return redirect()->route('account.index');
        }

        return Inertia::render('Tenant/Account/Setup', $this->accountSetup->boardPayload());
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AccountSetupStepStatus::Completed->value,
                AccountSetupStepStatus::Skipped->value,
            ])],
        ]);

        $this->accountSetup->markStep(
            $key,
            AccountSetupStepStatus::from($validated['status']),
        );

        return back();
    }
}
