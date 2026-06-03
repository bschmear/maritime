<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\InvoiceLineItem;
use Stripe\StripeClient;

class AccountBillingController extends Controller
{
    public function invoices(Account $account): Response
    {
        $this->authorizeAccountAccess($account);

        $owner = $account->owner;
        $cashierSub = $owner?->cashierSubscriptionForAccount($account);
        $invoices = [];

        if ($owner?->hasStripeId()) {
            try {
                $invoices = $owner->invoicesIncludingPending(['limit' => 48])
                    ->filter(function (Invoice $invoice) use ($cashierSub) {
                        if (! $cashierSub?->stripe_id) {
                            return true;
                        }

                        return $invoice->subscriptionId() === $cashierSub->stripe_id;
                    })
                    ->map(fn (Invoice $invoice) => $this->serializeInvoiceSummary($invoice))
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('Failed to load subscription invoices', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Account/Invoices/Index', [
            'account' => $this->serializeAccount($account),
            'invoices' => $invoices,
            'can_manage_billing' => $this->userIsOwner($account),
        ]);
    }

    public function showInvoice(Account $account, string $invoice): Response
    {
        $this->authorizeAccountAccess($account);

        $owner = $account->owner;
        if (! $owner?->hasStripeId()) {
            abort(404);
        }

        try {
            $invoiceModel = $owner->findInvoiceOrFail($invoice);
        } catch (\Throwable) {
            abort(404);
        }

        $cashierSub = $owner->cashierSubscriptionForAccount($account);
        if ($cashierSub?->stripe_id && $invoiceModel->subscriptionId() !== $cashierSub->stripe_id) {
            abort(403, 'This invoice does not belong to this account subscription.');
        }

        return Inertia::render('Account/Invoices/Show', [
            'account' => $this->serializeAccount($account),
            'invoice' => $this->serializeInvoiceDetail($invoiceModel),
            'can_manage_billing' => $this->userIsOwner($account),
        ]);
    }

    public function setupIntent(Account $account): JsonResponse
    {
        $this->authorizeAccountOwner($account);

        $owner = $account->owner;
        $owner->createOrGetStripeCustomer();
        $intent = $owner->createSetupIntent();

        return response()->json([
            'client_secret' => $intent->client_secret,
            'stripe_key' => config('cashier.key'),
        ]);
    }

    public function updatePaymentMethod(Request $request, Account $account): RedirectResponse
    {
        $this->authorizeAccountOwner($account);

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $owner = $account->owner;
        $owner->createOrGetStripeCustomer();

        try {
            $owner->updateDefaultPaymentMethod($validated['payment_method']);

            $cashierSub = $owner->cashierSubscriptionForAccount($account);
            if ($cashierSub?->stripe_id) {
                $stripe = new StripeClient(config('cashier.secret'));
                $stripe->subscriptions->update($cashierSub->stripe_id, [
                    'default_payment_method' => $validated['payment_method'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update default payment method', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'payment_method' => 'Could not update your payment method. Please check your card details and try again.',
            ]);
        }

        return redirect()->back()->with('success', 'Payment method updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAccount(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'is_owner' => $this->userIsOwner($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->asStripeInvoice()->id,
            'number' => $invoice->number,
            'status' => $invoice->asStripeInvoice()->status,
            'date' => $invoice->date()?->toIso8601String(),
            'total' => $invoice->total(),
            'invoice_pdf' => $invoice->invoice_pdf,
            'hosted_invoice_url' => $invoice->asStripeInvoice()->hosted_invoice_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoiceDetail(Invoice $invoice): array
    {
        $stripeInvoice = $invoice->asStripeInvoice();

        return [
            'id' => $stripeInvoice->id,
            'number' => $invoice->number,
            'status' => $stripeInvoice->status,
            'date' => $invoice->date()?->toIso8601String(),
            'due_date' => $invoice->dueDate()?->toIso8601String(),
            'total' => $invoice->total(),
            'subtotal' => $invoice->subtotal(),
            'tax' => $invoice->tax(),
            'invoice_pdf' => $invoice->invoice_pdf,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
            'lines' => collect($invoice->invoiceLineItems())->map(function (InvoiceLineItem $line) {
                return [
                    'description' => $line->asStripeInvoiceLineItem()->description ?? 'Line item',
                    'quantity' => $line->asStripeInvoiceLineItem()->quantity,
                    'amount' => $line->total(),
                ];
            })->values()->all(),
        ];
    }

    private function authorizeAccountAccess(Account $account): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $hasAccess = $account->users()->where('users.id', $user->id)->exists()
            || $account->owner_id === $user->id;

        if (! $hasAccess) {
            abort(403, 'You do not have access to this account.');
        }
    }

    private function authorizeAccountOwner(Account $account): void
    {
        if (! $this->userIsOwner($account)) {
            abort(403, 'Only account owners can manage billing.');
        }
    }

    private function userIsOwner(Account $account): bool
    {
        return $account->owner_id === Auth::id();
    }
}
