<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Invoice\Actions\CreateInvoice as CreateAction;
use App\Domain\Invoice\Actions\DeleteInvoice as DeleteAction;
use App\Domain\Invoice\Actions\UpdateInvoice as UpdateAction;
use App\Domain\Invoice\Models\Invoice as RecordModel;
use App\Domain\Transaction\Models\Transaction;
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

    public function show(Request $request, $id)
    {
        return parent::show($request, $id);
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
            $transaction = Transaction::query()
                ->with([
                    'contract' => fn ($q) => $q->select(['id', 'sequence']),
                    'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
                    'subsidiary',
                    'location',
                    'items' => fn ($q) => $q
                        ->with([
                            'addons',
                            'estimateLineItem' => fn ($q2) => $q2
                                ->select(['id', 'asset_variant_id'])
                                ->with([
                                    'assetVariant' => fn ($q3) => $q3->select(['id', 'display_name', 'name']),
                                ]),
                        ])
                        ->orderBy('position')
                        ->orderBy('id'),
                ])
                ->find((int) $transactionId);

            if ($transaction) {
                $customer = $transaction->customer;
                $contact = $customer?->contact;

                $initialData['transaction_id'] = $transaction->id;
                $initialData['contact_id'] = $customer?->contact_id
                    ?? ($req->query('contact_id') ? (int) $req->query('contact_id') : null);
                $initialData['currency'] = $transaction->currency ?? 'USD';

                if ($transaction->contract) {
                    $initialData['contract_id'] = $transaction->contract->id;
                    $initialData['contract'] = [
                        'id' => $transaction->contract->id,
                        'display_name' => $transaction->contract->display_name,
                    ];
                }

                $initialData['transaction'] = [
                    'id' => $transaction->id,
                    'display_name' => $transaction->display_name,
                ];

                if ($contact) {
                    $initialData['contact'] = [
                        'id' => $contact->id,
                        'display_name' => $contact->display_name,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'mobile' => $contact->mobile,
                    ];
                }

                $initialData['customer_name'] = $transaction->customer_name
                    ?? $customer?->display_name
                    ?? $contact?->display_name
                    ?? '';
                $initialData['customer_email'] = $transaction->customer_email
                    ?? $customer?->email
                    ?? $contact?->email
                    ?? '';
                $initialData['customer_phone'] = $transaction->customer_phone
                    ?? $customer?->phone
                    ?? $contact?->phone
                    ?? $contact?->mobile
                    ?? '';

                // Billing: chosen from contact addresses in the UI (same as estimates), not the deal snapshot.

                $initialData['items'] = $transaction->items->map(function ($item) {
                    $eli = $item->estimateLineItem;

                    return [
                        'transaction_item_id' => $item->id,
                        'name' => $item->name ?? '',
                        'description' => $item->description ?? '',
                        'quantity' => (float) ($item->quantity ?? 1),
                        'unit_price' => (float) ($item->unit_price ?? 0),
                        'discount' => (float) ($item->discount ?? 0),
                        'taxable' => (bool) ($item->taxable ?? false),
                        'tax_rate' => (float) ($item->tax_rate ?? 0),
                        'position' => $item->position ?? 0,
                        'addons' => $item->addons->map(fn ($a) => [
                            'id' => $a->id,
                            'name' => $a->name,
                            'price' => (float) ($a->price ?? 0),
                            'quantity' => (int) ($a->quantity ?? 1),
                            'taxable' => (bool) ($a->taxable ?? true),
                            'tax_rate' => $a->tax_rate !== null ? (float) $a->tax_rate : null,
                            'notes' => $a->notes,
                        ])->values()->all(),
                        'estimate_line_item' => $eli ? [
                            'id' => $eli->id,
                            'asset_variant_id' => $eli->asset_variant_id,
                            'asset_variant' => $eli->relationLoaded('assetVariant') && $eli->assetVariant ? [
                                'id' => $eli->assetVariant->id,
                                'display_name' => $eli->assetVariant->display_name,
                                'name' => $eli->assetVariant->name,
                            ] : null,
                        ] : null,
                    ];
                })->values()->all();

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
