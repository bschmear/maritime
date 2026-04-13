<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Invoice\Actions\BuildInvoicePrefillFromTransaction;
use App\Domain\Invoice\Actions\CreateInvoice as CreateAction;
use App\Domain\Invoice\Actions\DeleteInvoice as DeleteAction;
use App\Domain\Invoice\Actions\UpdateInvoice as UpdateAction;
use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Transaction\Models\Transaction;
use App\Mail\InvoiceViewRequest;
use App\Models\AccountSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
        return parent::index($request);
    }

    protected function appendShowRelationships(array &$relationships): void
    {
        foreach (RecordModel::documentEagerLoads() as $key => $callback) {
            $relationships[$key] = $callback;
        }
    }

    public function show(Request $request, $id)
    {
        return parent::show($request, $id);
    }

    public function prefillFromTransaction($transaction)
    {
        $id = $transaction instanceof Transaction ? $transaction->getKey() : (int) $transaction;
        $model = Transaction::query()->findOrFail($id);

        return response()->json((new BuildInvoicePrefillFromTransaction)($model));
    }

    public function create()
    {
        $req = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];
        $transactionData = null;

        if ($transactionId = $req->query('transaction_id')) {
            $transaction = Transaction::query()->find((int) $transactionId);

            if ($transaction) {
                $initialData = array_merge($initialData, (new BuildInvoicePrefillFromTransaction)($transaction));

                if ($req->query('contact_id')) {
                    $initialData['contact_id'] = (int) $req->query('contact_id');
                }

                $transaction->loadMissing(['subsidiary', 'location', 'contract']);

                if ($transaction->contract) {
                    $initialData['contract'] = [
                        'id' => $transaction->contract->id,
                        'display_name' => $transaction->contract->display_name,
                    ];
                }

                $transactionData = [
                    'id' => $transaction->id,
                    'display_name' => $transaction->display_name,
                    'subsidiary' => $transaction->subsidiary
                        ? ['id' => $transaction->subsidiary->id, 'display_name' => $transaction->subsidiary->display_name]
                        : null,
                    'location' => $transaction->location
                        ? ['id' => $transaction->location->id, 'display_name' => $transaction->location->display_name]
                        : null,
                ];
            }
        } elseif ($cid = $req->query('contact_id')) {
            $initialData['contact_id'] = (int) $cid;
            $contactRow = Contact::query()
                ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile'])
                ->find((int) $cid);
            if ($contactRow) {
                $initialData['contact'] = [
                    'id' => $contactRow->id,
                    'display_name' => $contactRow->display_name,
                    'first_name' => $contactRow->first_name,
                    'last_name' => $contactRow->last_name,
                    'email' => $contactRow->email,
                    'phone' => $contactRow->phone,
                    'mobile' => $contactRow->mobile,
                ];
            }
        } elseif ($custId = $req->query('customer_id')) {
            $c = Customer::query()->select(['id', 'contact_id'])->find((int) $custId);
            if ($c?->contact_id) {
                $initialData['contact_id'] = $c->contact_id;
                $contactRow = Contact::query()
                    ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone', 'mobile'])
                    ->find($c->contact_id);
                if ($contactRow) {
                    $initialData['contact'] = [
                        'id' => $contactRow->id,
                        'display_name' => $contactRow->display_name,
                        'first_name' => $contactRow->first_name,
                        'last_name' => $contactRow->last_name,
                        'email' => $contactRow->email,
                        'phone' => $contactRow->phone,
                        'mobile' => $contactRow->mobile,
                    ];
                }
            }
        }

        return inertia('Tenant/Invoice/Create', [
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => \App\Enums\Timezone::options(),
            'initialData' => $initialData,
            'transaction' => $transactionData,
        ]);
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

    public function sendToCustomer(Request $request, int $invoice)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $settings = AccountSettings::getCurrent();
        $record = RecordModel::query()
            ->with(['contact' => fn ($q) => $q->select(['id', 'email', 'display_name'])])
            ->findOrFail($invoice);

        $to = $validated['email'] ?? $record->customer_email ?? $record->contact?->email;
        if (! $to) {
            return back()->with('error', 'No customer email found for this invoice.');
        }

        $viewUrl = route('invoices.view', $record->uuid);
        $record->markAsSent();
        Mail::to($to)->send(new InvoiceViewRequest($record->fresh(), $settings, $viewUrl));

        return back()->with('success', 'Invoice link sent to '.$to);
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
