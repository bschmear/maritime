<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Invoice\Actions\CreateInvoice as CreateAction;
use App\Domain\Invoice\Actions\DeleteInvoice as DeleteAction;
use App\Domain\Invoice\Actions\UpdateInvoice as UpdateAction;
use App\Domain\Invoice\Models\Invoice as RecordModel;
use Illuminate\Http\Request;

class InvoiceController extends RecordController
{
    protected $recordType = 'Invoice';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'invoices',
            'Invoice',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    public function index(Request $request)
    {
        return inertia('Tenant/Invoice/Index');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function show(Request $request, $id)
    {
        return inertia('Tenant/Invoice/Show');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    public function create()
    {
        return inertia('Tenant/Invoice/Create');
    }

    /**
     * Show a specific user with role relationship loaded.
     */
    // public function edit()
    // {
    //     return inertia('Tenant/Invoice/Edit');
    // }

    // public function store(Request $request)
    // {

    // }

    // public function update(Request $request, int $contract)
    // {
    //     $settings = AccountSettings::getCurrent();
    //     $existing = Contract::query()
    //         ->where('account_settings_id', $settings->id)
    //         ->findOrFail($contract);

    //     if ($this->isLocked($existing)) {
    //         return redirect()->route('contracts.show', $contract)
    //             ->with('error', 'This contract cannot be updated because it has been signed.');
    //     }

    //     $result = (new UpdateContract)($contract, $request->all());

    //     if (! $result['success'] || empty($result['record'])) {
    //         return back()
    //             ->withErrors(['message' => $result['message'] ?? 'Could not update contract.'])
    //             ->withInput();
    //     }

    //     return redirect()->route('contracts.show', $contract);
    // }

    public function sendToCustomer(int $invoice)
    {
        // $settings = AccountSettings::getCurrent();
        // $record = Contract::query()
        //     ->where('account_settings_id', $settings->id)
        //     ->with(['customer', 'transaction'])
        //     ->findOrFail($contract);

        // $customerEmail = $record->customer?->email
        //     ?? $record->transaction?->customer_email;

        // if (! $customerEmail) {
        //     return back()->with('error', 'No customer email found for this contract.');
        // }

        // $record->update(['status' => ContractStatus::PendingApproval->value]);

        // $reviewUrl = route('contracts.review', $record->uuid);
        // Mail::to($customerEmail)->send(new ContractReviewRequest($record, $settings, $reviewUrl));

        // return back()->with('success', 'Contract sent to ' . $customerEmail);
    }

    // public function destroy(int $invoice)
    // {
    // $settings = AccountSettings::getCurrent();
    // Contract::query()
    //     ->where('account_settings_id', $settings->id)
    //     ->findOrFail($contract);

    // $result = (new DeleteContract)($contract);

    // if (! $result['success']) {
    //     return redirect()->route('contracts.index')
    //         ->with('error', $result['message'] ?? 'Could not delete contract.');
    // }

    // return redirect()->route('contracts.index')
    //     ->with('success', $result['message'] ?? 'Contract deleted.');
    // }

}
