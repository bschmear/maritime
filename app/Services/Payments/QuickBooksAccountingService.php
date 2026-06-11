<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Contact\Models\Contact;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use App\Enums\Integration\IntegrationType;
use App\Enums\Payments\Terms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuickBooksAccountingService
{
    public function __construct(
        protected QuickBooksOAuthService $oauth,
        protected QuickBooksTermsService $terms,
        protected QuickBooksTaxService $tax,
    ) {}

    public function integration(): ?Integration
    {
        return Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();
    }

    public function isConnected(): bool
    {
        $integration = $this->integration();

        return $integration !== null
            && $integration->access_token
            && $integration->refresh_token
            && $integration->external_id;
    }

    /**
     * @return array{success: bool, customer_id?: string, message?: string}
     */
    public function pushContact(Contact $contact, ?Invoice $invoice = null): array
    {
        $integration = $this->integration();
        if ($integration === null || ! $this->isConnected()) {
            return ['success' => false, 'message' => 'QuickBooks is not connected.'];
        }

        if ($contact->quickbooks_customer_id) {
            return ['success' => true, 'customer_id' => $contact->quickbooks_customer_id];
        }

        try {
            $customerId = $this->createCustomer($integration, $contact, $invoice);
            $contact->attachProcessorCustomerId('quickbooks', $customerId);

            return ['success' => true, 'customer_id' => $customerId];
        } catch (\Throwable $e) {
            Log::error('QuickBooks push contact failed', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, invoice_id?: string, invoice_url?: string, message?: string}
     */
    public function pushInvoice(Invoice $invoice): array
    {
        $integration = $this->integration();
        if ($integration === null || ! $this->isConnected()) {
            return ['success' => false, 'message' => 'QuickBooks is not connected.'];
        }

        if ($invoice->quickbooks_invoice_id) {
            return $this->updateInvoice($invoice);
        }

        $invoice->loadMissing(['contact', 'items.serviceItem', 'transaction:id,tax_rate,tax_jurisdiction,tax_jurisdiction_code']);

        $contact = $invoice->contact;
        if ($contact === null) {
            return ['success' => false, 'message' => 'Invoice has no contact.'];
        }

        if (! $contact->quickbooks_customer_id) {
            $contactResult = $this->pushContact($contact, $invoice);
            if (! ($contactResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $contactResult['message'] ?? 'Could not create QuickBooks customer for this contact.',
                ];
            }
            $contact->refresh();
        }

        try {
            $itemId = $this->resolveDefaultItemId($integration);
            $payload = $this->buildInvoicePayload(
                $integration,
                $invoice,
                (string) $contact->quickbooks_customer_id,
                $itemId,
            );
            $response = $this->postEntity($integration, 'invoice', $payload);
            $qboInvoice = $response['Invoice'] ?? null;

            if (! is_array($qboInvoice) || empty($qboInvoice['Id'])) {
                throw new RuntimeException('QuickBooks did not return an invoice id.');
            }

            $qboId = (string) $qboInvoice['Id'];
            $url = $this->resolveCustomerInvoiceUrl($integration, $qboId, $invoice->quickbooks_invoice_url);

            $invoice->update([
                'quickbooks_invoice_id' => $qboId,
                'quickbooks_invoice_url' => $url,
            ]);

            return [
                'success' => true,
                'invoice_id' => $qboId,
                'invoice_url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::error('QuickBooks push invoice failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentsForInvoice(Integration $integration, string $qboInvoiceId): array
    {
        $escapedId = str_replace("'", "\\'", $qboInvoiceId);
        $sql = "select * from Payment where Line.LinkedTxn.TxnId = '{$escapedId}'";
        $payload = $this->oauth->queryAccountingForIntegration($integration, $sql);

        if (! empty($payload['Fault'])) {
            throw new RuntimeException($this->faultMessage($payload['Fault']) ?: 'QuickBooks payment query failed.');
        }

        $payments = $payload['QueryResponse']['Payment'] ?? [];
        if ($payments === []) {
            return [];
        }
        if (! array_is_list($payments)) {
            return [$payments];
        }

        return $payments;
    }

    public function invoiceUrl(string $qboInvoiceId): string
    {
        $host = $this->oauth->isProduction()
            ? 'https://qbo.intuit.com'
            : 'https://sandbox.qbo.intuit.com';

        return "{$host}/app/invoice?txnId={$qboInvoiceId}";
    }

    /**
     * Customer-facing QuickBooks Online invoice URL (pay / view in QBO).
     */
    public function customerInvoiceUrlForInvoice(Invoice $invoice): ?string
    {
        $qboId = (string) ($invoice->quickbooks_invoice_id ?? '');
        if ($qboId === '') {
            return null;
        }

        $integration = $this->integration();
        if ($integration === null || ! $this->isConnected()) {
            return $invoice->quickbooks_invoice_url;
        }

        $url = $this->resolveCustomerInvoiceUrl(
            $integration,
            $qboId,
            $invoice->quickbooks_invoice_url,
        );

        if ($url !== null && $url !== $invoice->quickbooks_invoice_url) {
            $invoice->update(['quickbooks_invoice_url' => $url]);
        }

        return $url;
    }

    /**
     * Fetch the QBO online invoice link; fall back to a stored URL only when it is already customer-facing.
     */
    public function resolveCustomerInvoiceUrl(
        Integration $integration,
        string $qboInvoiceId,
        ?string $storedUrl = null,
    ): ?string {
        $this->oauth->refreshAccessTokenIfExpiredForIntegration($integration);
        if ($integration->exists) {
            $integration->refresh();
        }

        $publicUrl = $this->fetchPublicInvoiceLink($integration, $qboInvoiceId);
        if ($publicUrl !== null) {
            return $publicUrl;
        }

        if ($storedUrl !== null && $storedUrl !== '' && ! $this->isStaffInvoiceUrl($storedUrl)) {
            return $storedUrl;
        }

        return null;
    }

    protected function fetchPublicInvoiceLink(Integration $integration, string $qboInvoiceId): ?string
    {
        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return null;
        }

        // InvoiceLink is only returned when include=invoiceLink is set (requires minorversion ≥ 36).
        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->get(
                "{$this->oauth->accountingApiBaseUrl()}/v3/company/{$realmId}/invoice/{$qboInvoiceId}",
                [
                    'minorversion' => 70,
                    'include' => 'invoiceLink',
                ],
            );

        if ($response->failed()) {
            $json = $response->json();
            Log::warning('QuickBooks: failed to fetch public invoice link', QuickBooksHttpSupport::withIntuitTid($response, [
                'integration_id' => $integration->id,
                'qbo_invoice_id' => $qboInvoiceId,
                'status' => $response->status(),
                'fault' => is_array($json) ? $this->faultMessage($json['Fault'] ?? []) : null,
            ]));

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        if (! empty($json['Fault'])) {
            Log::warning('QuickBooks: invoice link request returned fault', QuickBooksHttpSupport::withIntuitTid($response, [
                'integration_id' => $integration->id,
                'qbo_invoice_id' => $qboInvoiceId,
                'fault' => $this->faultMessage($json['Fault']),
            ]));

            return null;
        }

        $link = $json['Invoice']['InvoiceLink'] ?? null;

        if (! is_string($link) || $link === '') {
            Log::info('QuickBooks: invoice has no InvoiceLink (online payments or BillEmail may be missing)', [
                'integration_id' => $integration->id,
                'qbo_invoice_id' => $qboInvoiceId,
            ]);

            return null;
        }

        return $link;
    }

    protected function isStaffInvoiceUrl(string $url): bool
    {
        return str_contains($url, 'qbo.intuit.com/app/invoice')
            || str_contains($url, 'sandbox.qbo.intuit.com/app/invoice');
    }

    /**
     * @return array{success: bool, invoice_id?: string, invoice_url?: string, message?: string}
     */
    public function updateInvoice(Invoice $invoice): array
    {
        $integration = $this->integration();
        if ($integration === null || ! $this->isConnected()) {
            return ['success' => false, 'message' => 'QuickBooks is not connected.'];
        }

        $qboId = (string) ($invoice->quickbooks_invoice_id ?? '');
        if ($qboId === '') {
            return $this->pushInvoice($invoice);
        }

        $invoice->loadMissing(['contact', 'items.serviceItem', 'transaction:id,tax_rate,tax_jurisdiction,tax_jurisdiction_code']);

        $contact = $invoice->contact;
        if ($contact === null) {
            return ['success' => false, 'message' => 'Invoice has no contact.'];
        }

        if (! $contact->quickbooks_customer_id) {
            $contactResult = $this->pushContact($contact, $invoice);
            if (! ($contactResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $contactResult['message'] ?? 'Could not create QuickBooks customer for this contact.',
                ];
            }
            $contact->refresh();
        }

        try {
            $remote = $this->fetchRemoteInvoice($integration, $qboId);
            if ($remote === null) {
                return ['success' => false, 'message' => 'Invoice not found in QuickBooks.'];
            }

            $itemId = $this->resolveDefaultItemId($integration);
            $payload = $this->buildInvoicePayload(
                $integration,
                $invoice,
                (string) $contact->quickbooks_customer_id,
                $itemId,
            );
            $payload['Id'] = $remote['Id'];
            $payload['SyncToken'] = $remote['SyncToken'];

            $this->postEntity($integration, 'invoice', $payload);

            $customerUrl = $this->resolveCustomerInvoiceUrl(
                $integration,
                $qboId,
                $invoice->quickbooks_invoice_url,
            );
            if ($customerUrl !== null && $customerUrl !== $invoice->quickbooks_invoice_url) {
                $invoice->update(['quickbooks_invoice_url' => $customerUrl]);
            }

            return [
                'success' => true,
                'invoice_id' => $qboId,
                'invoice_url' => $customerUrl ?? $invoice->quickbooks_invoice_url,
                'message' => 'Updated in QuickBooks.',
            ];
        } catch (\Throwable $e) {
            Log::error('QuickBooks invoice update failed', [
                'invoice_id' => $invoice->id,
                'qbo_invoice_id' => $qboId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  'auto'|'void'|'delete'  $operation
     * @return array{success: bool, message?: string, operation?: string}
     */
    public function removeRemoteInvoice(string $qboInvoiceId, string $operation = 'auto'): array
    {
        $integration = $this->integration();
        if ($integration === null || ! $this->isConnected()) {
            return ['success' => false, 'message' => 'QuickBooks is not connected.'];
        }

        $remote = $this->fetchRemoteInvoice($integration, $qboInvoiceId);
        if ($remote === null) {
            return ['success' => false, 'message' => 'Invoice not found in QuickBooks.'];
        }

        $operations = match ($operation) {
            'void' => ['void'],
            'delete' => ['delete', 'void'],
            default => ['delete', 'void'],
        };

        $lastError = 'QuickBooks could not remove this invoice.';
        $body = [
            'Id' => (string) $remote['Id'],
            'SyncToken' => (string) $remote['SyncToken'],
        ];

        foreach ($operations as $op) {
            try {
                $this->mutateEntity($integration, 'invoice', $body, $op);

                return [
                    'success' => true,
                    'operation' => $op,
                    'message' => $op === 'void'
                        ? 'Voided in QuickBooks.'
                        : 'Deleted in QuickBooks.',
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $refreshed = $this->fetchRemoteInvoice($integration, $qboInvoiceId);
                if ($refreshed !== null) {
                    $body['SyncToken'] = (string) $refreshed['SyncToken'];
                }
            }
        }

        return ['success' => false, 'message' => $lastError];
    }

    /**
     * @deprecated Use {@see removeRemoteInvoice()}
     *
     * @return array{success: bool, message?: string, voided?: bool}
     */
    public function deleteRemoteInvoice(string $qboInvoiceId): array
    {
        $result = $this->removeRemoteInvoice($qboInvoiceId, 'auto');

        if (($result['operation'] ?? '') === 'void') {
            $result['voided'] = true;
        }

        return $result;
    }

    /**
     * @return array{Id: string, SyncToken: string}|null
     */
    public function fetchRemoteInvoice(Integration $integration, string $qboInvoiceId): ?array
    {
        $this->oauth->refreshAccessTokenIfExpiredForIntegration($integration);
        $integration->refresh();

        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            throw new RuntimeException('QuickBooks realm id is missing — reconnect QuickBooks.');
        }

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->get(
                "{$this->oauth->accountingApiBaseUrl()}/v3/company/{$realmId}/invoice/{$qboInvoiceId}",
                ['minorversion' => 70],
            );

        if ($response->failed()) {
            Log::error('QuickBooks invoice GET failed', QuickBooksHttpSupport::withIntuitTid($response, [
                'invoice_id' => $qboInvoiceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]));

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        if (! empty($json['Fault'])) {
            throw new RuntimeException($this->faultMessage($json['Fault']) ?: 'QuickBooks invoice lookup failed.');
        }

        $invoice = $json['Invoice'] ?? null;
        if (! is_array($invoice) || empty($invoice['Id']) || ! isset($invoice['SyncToken'])) {
            return null;
        }

        return [
            'Id' => (string) $invoice['Id'],
            'SyncToken' => (string) $invoice['SyncToken'],
        ];
    }

    public function createCustomer(Integration $integration, Contact $contact, ?Invoice $invoice = null): string
    {
        $displayName = trim((string) ($contact->display_name ?: ''));
        if ($displayName === '') {
            $displayName = trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''));
        }
        if ($displayName === '') {
            $displayName = $contact->company ?: $contact->email ?: 'Customer '.$contact->id;
        }

        $payload = [
            'DisplayName' => $displayName,
        ];

        $first = trim((string) ($contact->first_name ?? ''));
        $last = trim((string) ($contact->last_name ?? ''));
        if ($first !== '') {
            $payload['GivenName'] = $first;
        }
        if ($last !== '') {
            $payload['FamilyName'] = $last;
        }
        if ($contact->company) {
            $payload['CompanyName'] = $contact->company;
        }
        if ($contact->email) {
            $payload['PrimaryEmailAddr'] = ['Address' => $contact->email];
        }
        $phone = $contact->phone ?: $contact->mobile;
        if ($phone) {
            $payload['PrimaryPhone'] = ['FreeFormNumber' => $phone];
        }

        $billAddr = $this->resolveBillAddr($contact, $invoice);
        if ($billAddr !== null) {
            $payload['BillAddr'] = $billAddr;
        }

        $response = $this->postEntity($integration, 'customer', $payload);
        $customer = $response['Customer'] ?? null;

        if (! is_array($customer) || empty($customer['Id'])) {
            throw new RuntimeException('QuickBooks did not return a customer id.');
        }

        return (string) $customer['Id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function postEntity(Integration $integration, string $entity, array $payload): array
    {
        return $this->mutateEntity($integration, $entity, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function mutateEntity(Integration $integration, string $entity, array $payload, ?string $operation = null): array
    {
        $this->oauth->refreshAccessTokenIfExpiredForIntegration($integration);
        $integration->refresh();

        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            throw new RuntimeException('QuickBooks realm id is missing — reconnect QuickBooks.');
        }

        $entity = strtolower($entity);
        $url = "{$this->oauth->accountingApiBaseUrl()}/v3/company/{$realmId}/{$entity}";
        $query = ['minorversion' => 70];
        if ($operation !== null && $operation !== '') {
            $query['operation'] = $operation;
        }
        $url .= '?'.http_build_query($query);

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('QuickBooks entity POST failed', QuickBooksHttpSupport::withIntuitTid($response, [
                'entity' => $entity,
                'operation' => $operation,
                'status' => $response->status(),
                'body' => $response->body(),
            ]));

            $message = $this->extractErrorMessage($response->json()) ?: 'QuickBooks request failed (HTTP '.$response->status().').';
            throw new RuntimeException($message);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('QuickBooks returned an invalid response.');
        }

        if (! empty($json['Fault'])) {
            Log::error('QuickBooks entity POST returned fault', QuickBooksHttpSupport::withIntuitTid($response, [
                'entity' => $entity,
                'operation' => $operation,
                'fault' => $this->faultMessage($json['Fault']),
            ]));

            throw new RuntimeException($this->faultMessage($json['Fault']) ?: 'QuickBooks returned a fault.');
        }

        return $json;
    }

    protected function fetchInvoiceSyncToken(Integration $integration, string $qboInvoiceId): ?string
    {
        $escapedId = str_replace("'", "\\'", $qboInvoiceId);
        $payload = $this->oauth->queryAccountingForIntegration(
            $integration,
            "select Id, SyncToken from Invoice where Id = '{$escapedId}'",
        );

        if (! empty($payload['Fault'])) {
            throw new RuntimeException($this->faultMessage($payload['Fault']) ?: 'QuickBooks invoice lookup failed.');
        }

        $invoices = $payload['QueryResponse']['Invoice'] ?? [];
        if ($invoices === []) {
            return null;
        }
        if (! array_is_list($invoices)) {
            $invoices = [$invoices];
        }

        $invoice = $invoices[0] ?? null;
        if (! is_array($invoice) || empty($invoice['SyncToken'])) {
            return null;
        }

        return (string) $invoice['SyncToken'];
    }

    public function resolveDefaultItemId(Integration $integration): string
    {
        $settings = QuickBooksSettings::from($integration);
        if ($settings->defaultItemId) {
            return $settings->defaultItemId;
        }

        $payload = $this->oauth->queryAccountingForIntegration(
            $integration,
            "select Id, Name from Item where Type = 'Service' maxresults 1",
        );
        $integration->refresh();

        if (! empty($payload['Fault'])) {
            throw new RuntimeException($this->faultMessage($payload['Fault']) ?: 'Could not query QuickBooks items.');
        }

        $items = $payload['QueryResponse']['Item'] ?? [];
        if ($items !== [] && ! array_is_list($items)) {
            $items = [$items];
        }

        $first = $items[0] ?? null;
        if (! is_array($first) || empty($first['Id'])) {
            throw new RuntimeException(
                'No Service item found in QuickBooks. Create at least one Service item in QuickBooks Online, then try again.',
            );
        }

        $itemId = (string) $first['Id'];
        $existing = is_array($integration->settings) ? $integration->settings : [];
        $integration->update([
            'settings' => array_merge($existing, ['default_item_id' => $itemId]),
        ]);

        return $itemId;
    }

    /**
     * @return array<string, string>|null
     */
    protected function resolveBillAddr(Contact $contact, ?Invoice $invoice): ?array
    {
        if ($invoice !== null && trim((string) ($invoice->billing_address_line1 ?? '')) !== '') {
            return $this->billAddrFromInvoice($invoice);
        }

        $contact->loadMissing('primaryAddress');
        $address = $contact->primaryAddress;
        if ($address === null) {
            return null;
        }

        $line1 = trim((string) ($address->address_line_1 ?? ''));
        if ($line1 === '') {
            return null;
        }

        $addr = ['Line1' => $line1];
        $line2 = trim((string) ($address->address_line_2 ?? ''));
        if ($line2 !== '') {
            $addr['Line2'] = $line2;
        }
        $city = trim((string) ($address->city ?? ''));
        if ($city !== '') {
            $addr['City'] = $city;
        }
        $state = trim((string) ($address->state ?? ''));
        if ($state !== '') {
            $addr['CountrySubDivisionCode'] = $state;
        }
        $postal = trim((string) ($address->postal_code ?? ''));
        if ($postal !== '') {
            $addr['PostalCode'] = $postal;
        }
        $country = trim((string) ($address->country ?? ''));
        if ($country !== '') {
            $addr['Country'] = $country;
        }

        return $addr;
    }

    /**
     * @return array<string, string>
     */
    protected function billAddrFromInvoice(Invoice $invoice): array
    {
        $addr = [
            'Line1' => trim((string) $invoice->billing_address_line1),
        ];
        $line2 = trim((string) ($invoice->billing_address_line2 ?? ''));
        if ($line2 !== '') {
            $addr['Line2'] = $line2;
        }
        $city = trim((string) ($invoice->billing_city ?? ''));
        if ($city !== '') {
            $addr['City'] = $city;
        }
        $state = trim((string) ($invoice->billing_state ?? ''));
        if ($state !== '') {
            $addr['CountrySubDivisionCode'] = $state;
        }
        $postal = trim((string) ($invoice->billing_postal ?? ''));
        if ($postal !== '') {
            $addr['PostalCode'] = $postal;
        }
        $country = trim((string) ($invoice->billing_country ?? ''));
        if ($country !== '') {
            $addr['Country'] = $country;
        }

        return $addr;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildInvoicePayload(
        Integration $integration,
        Invoice $invoice,
        string $customerId,
        string $itemId,
    ): array {
        $lines = [];
        $items = $invoice->items;

        if ($items->isEmpty()) {
            $amount = round((float) $invoice->total, 2);
            $lines[] = $this->salesLine($amount, 'Invoice', $itemId, 1);
        } else {
            foreach ($items->sortBy('position')->sortBy('id') as $item) {
                $qty = max(0.01, (float) ($item->quantity ?? 1));
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discount = (float) ($item->discount ?? 0);
                $amount = round(max(0, ($item->subtotal ?? null) !== null
                    ? (float) $item->subtotal
                    : max(0, ($qty * $unitPrice) - $discount)), 2);
                $description = trim((string) ($item->name ?? 'Line item'));
                if ($item->description) {
                    $description .= ' — '.$item->description;
                }
                $lineItemId = $this->resolveLineItemId($item, $itemId);
                $lines[] = $this->salesLine(
                    $amount,
                    $description,
                    $lineItemId,
                    $qty,
                    $this->resolveLineTaxableFlag($item),
                );
            }
        }

        $payload = [
            'CustomerRef' => ['value' => $customerId],
            'Line' => $lines,
        ];

        $paymentTerm = Terms::fromStored($invoice->payment_term);
        $salesTermRef = $this->terms->salesTermRefFor($integration, $paymentTerm);
        if ($salesTermRef !== null) {
            $payload['SalesTermRef'] = $salesTermRef;
        }

        if ($invoice->due_at) {
            $payload['DueDate'] = $invoice->due_at->format('Y-m-d');
        }

        $invoice->loadMissing('contact');

        $billEmail = $this->resolveInvoiceBillEmail($invoice);
        if ($billEmail !== null) {
            $payload['BillEmail'] = ['Address' => $billEmail];
        }

        // Required for QBO to generate InvoiceLink (customer pay / view URL).
        $payload['AllowOnlineCreditCardPayment'] = true;
        $payload['AllowOnlineACHPayment'] = true;
        $billAddr = $this->resolveBillAddr($invoice->contact, $invoice);
        if ($billAddr !== null) {
            $payload['BillAddr'] = $billAddr;
        }

        $docNumber = $invoice->sequence ? (string) $invoice->sequence : null;
        if ($docNumber) {
            $payload['DocNumber'] = $docNumber;
        }

        return $this->tax->enrichInvoicePayload($integration, $invoice, $payload, $lines);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function salesLine(
        float $amount,
        string $description,
        string $itemId,
        float $qty,
        ?bool $taxable = null,
    ): array {
        $line = [
            'Amount' => $amount,
            'DetailType' => 'SalesItemLineDetail',
            'Description' => mb_substr($description, 0, 4000),
            'SalesItemLineDetail' => [
                'ItemRef' => ['value' => $itemId],
                'Qty' => $qty,
                'UnitPrice' => $qty > 0 ? round($amount / $qty, 2) : $amount,
            ],
        ];

        if ($taxable !== null) {
            $line[QuickBooksTaxService::LINE_TAXABLE_FLAG] = $taxable;
        }

        return $line;
    }

    protected function resolveInvoiceBillEmail(Invoice $invoice): ?string
    {
        $candidates = [
            $invoice->customer_email,
            $invoice->contact?->email,
        ];

        foreach ($candidates as $email) {
            $email = is_string($email) ? trim($email) : '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    protected function resolveLineItemId(InvoiceItem $item, string $fallbackItemId): string
    {
        if ($item->relationLoaded('serviceItem')) {
            $qboId = $item->serviceItem?->quickbooks_item_id;
        } elseif ($item->service_item_id) {
            $qboId = $item->serviceItem()->value('quickbooks_item_id');
        } else {
            return $fallbackItemId;
        }

        if (is_string($qboId) && trim($qboId) !== '') {
            return trim($qboId);
        }

        return $fallbackItemId;
    }

    protected function resolveLineTaxableFlag(InvoiceItem $item): ?bool
    {
        if (! array_key_exists('taxable', $item->getAttributes())) {
            return null;
        }

        return filter_var($item->getAttributes()['taxable'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function extractErrorMessage(?array $json): ?string
    {
        if ($json === null || empty($json['Fault'])) {
            return null;
        }

        return $this->faultMessage($json['Fault']);
    }

    /**
     * @param  array<string, mixed>  $fault
     */
    protected function faultMessage(array $fault): string
    {
        $errors = $fault['Error'] ?? [];
        if (! is_array($errors)) {
            return '';
        }
        if ($errors !== [] && ! array_is_list($errors)) {
            $errors = [$errors];
        }
        $parts = [];
        foreach ($errors as $err) {
            if (is_array($err) && ! empty($err['Message'])) {
                $detail = (string) $err['Message'];
                if (! empty($err['Detail'])) {
                    $detail .= ' — '.(string) $err['Detail'];
                }
                $parts[] = $detail;
            }
        }

        return implode('; ', $parts);
    }
}
