<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountConsignmentController extends Controller
{
    public function index(): Response
    {
        ConsignmentPolicy::ensureDefaultsExist();
        AccountSettings::ensureConsignmentDefaults();

        $account = AccountSettings::getCurrent();
        $policies = ConsignmentPolicy::query()->ordered()->get();

        return Inertia::render('Tenant/Account/ConsignmentConfiguration', [
            'account' => $account,
            'policies' => $policies,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'consignment_fee_percent' => 'required|numeric|min:0|max:100',
            'consignment_terms' => 'nullable|string|max:20000',
        ]);

        $account = AccountSettings::getCurrent();
        $account->consignment_fee_percent = $validated['consignment_fee_percent'];
        $account->consignment_terms = $validated['consignment_terms'] ?? null;
        $account->save();

        return back()->with('success', 'Consignment settings saved.');
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:20000',
            'is_active' => 'sometimes|boolean',
        ]);

        $maxOrder = (int) ConsignmentPolicy::query()->max('sort_order');

        ConsignmentPolicy::create([
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $maxOrder + 1,
        ]);

        return back();
    }

    public function updatePolicy(Request $request, ConsignmentPolicy $consignmentPolicy): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:20000',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = ['body' => $validated['body']];
        if ($request->exists('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $consignmentPolicy->update($data);

        return back()->with('success', 'Policy updated.');
    }

    public function destroyPolicy(ConsignmentPolicy $consignmentPolicy): RedirectResponse
    {
        $consignmentPolicy->delete();

        return back()->with('success', 'Policy removed.');
    }

    public function reorderPolicies(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:consignment_policies,id',
        ]);

        $ids = array_values($validated['ids']);
        $existingSorted = ConsignmentPolicy::query()->pluck('id')->sort()->values()->all();
        $incomingSorted = collect($ids)->sort()->values()->all();

        abort_unless(
            count($incomingSorted) === count($existingSorted) && $incomingSorted === $existingSorted,
            422,
            'Reorder payload must include every policy exactly once.',
        );

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                ConsignmentPolicy::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return back()->with('success', 'Policy order updated.');
    }
}
