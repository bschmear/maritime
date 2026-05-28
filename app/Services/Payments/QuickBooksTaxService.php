<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Integration\Models\Integration;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuickBooksTaxService
{
    private const AST_TAXABLE = 'TAX';

    private const AST_NON_TAXABLE = 'NON';

    public function __construct(
        protected QuickBooksOAuthService $oauth,
    ) {}

    /**
     * Apply QBO tax fields to an invoice create payload (AST or manual per company preferences).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public function enrichInvoicePayload(Integration $integration, Invoice $invoice, array $payload, array $lines): array
    {
        $invoice->loadMissing(['items', 'transaction:id,tax_rate,tax_jurisdiction']);

        $taxTotal = round((float) $invoice->tax_total, 2);
        $invoiceHasTax = $taxTotal >= 0.01;
        $context = $this->resolveTaxContext($invoice, $taxTotal);

        if ($this->usesAutomatedSalesTax($integration)) {
            $payload = $this->applyAutomatedSalesTax($invoice, $payload, $lines, $invoiceHasTax, $taxTotal, $context);
        } else {
            $payload = $this->applyManualTax($integration, $invoice, $payload, $lines, $taxTotal, $invoiceHasTax, $context);
        }

        $payload['Line'] = $lines;

        return $payload;
    }

    public function usesAutomatedSalesTax(Integration $integration): bool
    {
        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return false;
        }

        return Cache::remember(
            $this->astCacheKey($integration->id, $realmId),
            now()->addDay(),
            fn () => $this->fetchPartnerTaxEnabled($integration),
        );
    }

    public function forgetCachedTaxData(Integration $integration): void
    {
        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return;
        }

        Cache::forget($this->astCacheKey($integration->id, $realmId));
        Cache::forget($this->taxRatesCacheKey($integration->id, $realmId));
        Cache::forget($this->taxCodesCacheKey($integration->id, $realmId));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function applyAutomatedSalesTax(
        Invoice $invoice,
        array $payload,
        array &$lines,
        bool $invoiceHasTax,
        float $taxTotal,
        array $context,
    ): array {
        $items = $invoice->items;
        $effectiveRate = (float) ($context['effective_rate'] ?? 0);
        $anyTaxable = false;

        if ($items->isNotEmpty()) {
            foreach ($lines as $index => $line) {
                $item = $items[$index] ?? null;
                $taxable = $this->lineIsTaxable($item, $effectiveRate, $invoiceHasTax);
                $anyTaxable = $anyTaxable || $taxable;
                $lines[$index] = $this->withLineTaxCode($line, $taxable ? self::AST_TAXABLE : self::AST_NON_TAXABLE);
            }
        } else {
            $anyTaxable = $invoiceHasTax;
            foreach ($lines as $index => $line) {
                $lines[$index] = $this->withLineTaxCode(
                    $line,
                    $invoiceHasTax ? self::AST_TAXABLE : self::AST_NON_TAXABLE,
                );
            }
        }

        if ($anyTaxable) {
            $txnTaxDetail = [
                'TxnTaxCodeRef' => ['value' => self::AST_TAXABLE],
            ];
            if ($taxTotal >= 0.01) {
                $txnTaxDetail['TotalTax'] = $taxTotal;
            }
            $payload['TxnTaxDetail'] = $txnTaxDetail;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function applyManualTax(
        Integration $integration,
        Invoice $invoice,
        array $payload,
        array &$lines,
        float $taxTotal,
        bool $invoiceHasTax,
        array $context,
    ): array {
        $taxCodes = $this->cachedTaxCodes($integration);
        $exemptCodeId = $this->resolveExemptTaxCodeId($taxCodes);
        $effectiveRate = (float) ($context['effective_rate'] ?? 0);
        $jurisdiction = (string) ($context['jurisdiction'] ?? '');

        if (! $invoiceHasTax) {
            if ($exemptCodeId !== null) {
                foreach ($lines as $index => $line) {
                    $lines[$index] = $this->withLineTaxCode($line, $exemptCodeId);
                }
            }

            return $payload;
        }

        $taxableSubtotal = $this->taxableSubtotal($invoice, $effectiveRate);
        $taxPercent = (float) ($context['effective_rate'] ?? 0);
        if ($taxPercent <= 0 && $taxableSubtotal > 0) {
            $taxPercent = round(($taxTotal / $taxableSubtotal) * 100, 4);
        }

        $taxRates = $this->cachedTaxRates($integration);
        $taxRate = $this->findTaxRateForInvoice($taxRates, $taxPercent, $jurisdiction, $taxCodes);
        $taxCode = $taxRate !== null
            ? $this->findTaxCodeForRate($taxCodes, (string) $taxRate['Id'])
            : null;

        if ($taxCode === null) {
            $taxCode = $this->findTaxCodeByJurisdiction($taxCodes, $jurisdiction);
        }

        if ($taxCode === null) {
            $taxCode = $this->findTaxCodeByName($taxCodes, $this->jurisdictionTaxCodeNames($jurisdiction));
        }

        if ($taxCode === null) {
            Log::warning('QuickBooks manual tax: no matching TaxCode/TaxRate; invoice pushed without tax detail', [
                'integration_id' => $integration->id,
                'invoice_id' => $invoice->id,
                'tax_total' => $taxTotal,
                'tax_percent' => $taxPercent,
                'jurisdiction' => $jurisdiction,
            ]);

            return $payload;
        }

        $taxCodeId = (string) $taxCode['Id'];
        $taxRateId = $taxRate !== null ? (string) $taxRate['Id'] : null;
        $rateValue = $taxRate !== null ? (float) ($taxRate['RateValue'] ?? $taxPercent) : $taxPercent;

        $items = $invoice->items;
        if ($items->isNotEmpty()) {
            foreach ($lines as $index => $line) {
                $item = $items[$index] ?? null;
                $codeId = $this->lineIsTaxable($item, $effectiveRate, true)
                    ? $taxCodeId
                    : ($exemptCodeId ?? $taxCodeId);
                $lines[$index] = $this->withLineTaxCode($line, $codeId);
            }
        } else {
            foreach ($lines as $index => $line) {
                $lines[$index] = $this->withLineTaxCode($line, $taxCodeId);
            }
        }

        $txnTaxDetail = [
            'TxnTaxCodeRef' => ['value' => $taxCodeId],
            'TotalTax' => $taxTotal,
        ];

        if ($taxRateId !== null && $taxableSubtotal > 0) {
            $txnTaxDetail['TaxLine'] = [[
                'Amount' => $taxTotal,
                'DetailType' => 'TaxLineDetail',
                'TaxLineDetail' => [
                    'TaxRateRef' => ['value' => $taxRateId],
                    'PercentBased' => true,
                    'TaxPercent' => $rateValue,
                    'NetAmountTaxable' => $taxableSubtotal,
                ],
            ]];
        }

        $payload['GlobalTaxCalculation'] = 'TaxExcluded';
        $payload['TxnTaxDetail'] = $txnTaxDetail;

        return $payload;
    }

    /**
     * @return array{jurisdiction: string, effective_rate: float}
     */
    protected function resolveTaxContext(Invoice $invoice, float $taxTotal): array
    {
        $transaction = $invoice->transaction;
        $jurisdiction = trim((string) (
            $invoice->tax_jurisdiction
            ?? $transaction?->tax_jurisdiction
            ?? $invoice->billing_state
            ?? ''
        ));

        $explicitRate = $this->maxStoredTaxRate($invoice);
        if ($explicitRate <= 0 && $transaction !== null) {
            $explicitRate = (float) ($transaction->tax_rate ?? 0);
        }

        $taxableSubtotal = $this->taxableSubtotal($invoice, $explicitRate);
        $inferredRate = $taxableSubtotal > 0 && $taxTotal >= 0.01
            ? round(($taxTotal / $taxableSubtotal) * 100, 4)
            : 0.0;

        $effectiveRate = $explicitRate > 0 ? $explicitRate : $inferredRate;

        return [
            'jurisdiction' => $jurisdiction,
            'effective_rate' => $effectiveRate,
        ];
    }

    protected function maxStoredTaxRate(Invoice $invoice): float
    {
        $max = (float) ($invoice->tax_rate ?? 0);
        foreach ($invoice->items as $item) {
            $max = max($max, (float) ($item->tax_rate ?? 0));
        }

        return $max;
    }

    protected function lineIsTaxable(?InvoiceItem $item, float $effectiveTaxRate, bool $invoiceHasTax): bool
    {
        if ($item === null) {
            return $invoiceHasTax;
        }

        if (! (bool) ($item->taxable ?? false)) {
            return false;
        }

        if ((float) ($item->tax_amount ?? 0) >= 0.01) {
            return true;
        }

        if ((float) ($item->tax_rate ?? 0) > 0.0001) {
            return true;
        }

        return $effectiveTaxRate > 0.0001 || $invoiceHasTax;
    }

    protected function taxableSubtotal(Invoice $invoice, float $effectiveTaxRate): float
    {
        $items = $invoice->items;
        if ($items->isEmpty()) {
            $subtotal = round((float) $invoice->subtotal, 2);

            return ((float) $invoice->tax_total) >= 0.01 ? $subtotal : 0.0;
        }

        $sum = 0.0;
        foreach ($items as $item) {
            if (! $this->lineIsTaxable($item, $effectiveTaxRate, true)) {
                continue;
            }
            $qty = max(0.01, (float) ($item->quantity ?? 1));
            $lineSub = $item->subtotal !== null
                ? (float) $item->subtotal
                : max(0, ($qty * (float) $item->unit_price) - (float) ($item->discount ?? 0));
            $sum += $lineSub;
        }

        return round($sum, 2);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    protected function withLineTaxCode(array $line, string $taxCodeValue): array
    {
        if (! isset($line['SalesItemLineDetail']) || ! is_array($line['SalesItemLineDetail'])) {
            return $line;
        }

        $line['SalesItemLineDetail']['TaxCodeRef'] = ['value' => $taxCodeValue];

        return $line;
    }

    protected function fetchPartnerTaxEnabled(Integration $integration): bool
    {
        $this->oauth->refreshAccessTokenIfExpiredForIntegration($integration);
        if ($integration->exists) {
            $integration->refresh();
        }

        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            return false;
        }

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->get(
                "{$this->oauth->accountingApiBaseUrl()}/v3/company/{$realmId}/preferences",
                ['minorversion' => 70],
            );

        if ($response->failed()) {
            Log::warning('QuickBooks preferences fetch failed', [
                'integration_id' => $integration->id,
                'status' => $response->status(),
            ]);

            return false;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return false;
        }

        return (bool) ($json['Preferences']['TaxPrefs']['PartnerTaxEnabled'] ?? false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function cachedTaxRates(Integration $integration): array
    {
        $realmId = (string) $integration->external_id;

        return Cache::remember(
            $this->taxRatesCacheKey($integration->id, $realmId),
            now()->addDay(),
            fn () => $this->queryEntities($integration, 'select Id, Name, RateValue from TaxRate where Active = true'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function cachedTaxCodes(Integration $integration): array
    {
        $realmId = (string) $integration->external_id;

        return Cache::remember(
            $this->taxCodesCacheKey($integration->id, $realmId),
            now()->addDay(),
            fn () => $this->queryEntities($integration, 'select Id, Name, SalesTaxRateList from TaxCode where Active = true'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function queryEntities(Integration $integration, string $sql): array
    {
        $payload = $this->oauth->queryAccountingForIntegration($integration, $sql);

        if (! empty($payload['Fault'])) {
            throw new RuntimeException(
                $this->faultMessage($payload['Fault']) ?: 'QuickBooks tax query failed.',
            );
        }

        $entity = null;
        if (str_contains(strtolower($sql), 'taxrate')) {
            $entity = $payload['QueryResponse']['TaxRate'] ?? [];
        } elseif (str_contains(strtolower($sql), 'taxcode')) {
            $entity = $payload['QueryResponse']['TaxCode'] ?? [];
        }

        if ($entity === [] || $entity === null) {
            return [];
        }

        if (! array_is_list($entity)) {
            return [$entity];
        }

        return $entity;
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     * @param  list<array<string, mixed>>  $codes
     * @return array<string, mixed>|null
     */
    protected function findTaxRateForInvoice(
        array $rates,
        float $targetPercent,
        string $jurisdiction,
        array $codes,
    ): ?array {
        $byJurisdiction = $this->findTaxRateByJurisdiction($rates, $codes, $jurisdiction);
        if ($byJurisdiction !== null) {
            return $byJurisdiction;
        }

        return $this->findBestTaxRate($rates, $targetPercent);
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     * @param  list<array<string, mixed>>  $codes
     * @return array<string, mixed>|null
     */
    protected function findTaxRateByJurisdiction(array $rates, array $codes, string $jurisdiction): ?array
    {
        if ($jurisdiction === '') {
            return null;
        }

        $code = $this->findTaxCodeByJurisdiction($codes, $jurisdiction);
        if ($code === null) {
            return $this->findTaxRateByName($rates, $this->jurisdictionTaxCodeNames($jurisdiction));
        }

        $details = $code['SalesTaxRateList']['TaxRateDetail'] ?? [];
        if ($details === []) {
            return null;
        }
        if (! array_is_list($details)) {
            $details = [$details];
        }

        $rateId = null;
        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $ref = $detail['TaxRateRef']['value'] ?? $detail['TaxRateRef'] ?? null;
            if ($ref !== null && $ref !== '') {
                $rateId = (string) $ref;
                break;
            }
        }

        if ($rateId === null) {
            return null;
        }

        foreach ($rates as $rate) {
            if (is_array($rate) && isset($rate['Id']) && (string) $rate['Id'] === $rateId) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     * @param  list<string>  $names
     * @return array<string, mixed>|null
     */
    protected function findTaxRateByName(array $rates, array $names): ?array
    {
        foreach ($rates as $rate) {
            if (! is_array($rate) || empty($rate['Name'])) {
                continue;
            }
            $normalized = strtolower(trim((string) $rate['Name']));
            foreach ($names as $name) {
                $needle = strtolower(trim($name));
                if ($needle === '' || $needle === 'tax' || $needle === 'sales tax' || $needle === 'state tax') {
                    continue;
                }
                if ($normalized === $needle || str_contains($normalized, $needle)) {
                    return $rate;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $codes
     * @return array<string, mixed>|null
     */
    protected function findTaxCodeByJurisdiction(array $codes, string $jurisdiction): ?array
    {
        return $this->findTaxCodeByName($codes, $this->jurisdictionTaxCodeNames($jurisdiction));
    }

    /**
     * @return list<string>
     */
    protected function jurisdictionTaxCodeNames(string $jurisdiction): array
    {
        $jurisdiction = trim($jurisdiction);
        $names = ['tax', 'sales tax', 'state tax'];
        if ($jurisdiction === '') {
            return $names;
        }

        $names[] = $jurisdiction;

        if (str_contains($jurisdiction, ',')) {
            foreach (explode(',', $jurisdiction) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $names[] = $part;
                }
            }
        }

        if (preg_match('/\b([A-Za-z]{2})\b/', $jurisdiction, $matches) === 1) {
            $names[] = strtoupper($matches[1]);
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     * @return array<string, mixed>|null
     */
    protected function findBestTaxRate(array $rates, float $targetPercent): ?array
    {
        $best = null;
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($rates as $rate) {
            if (! is_array($rate) || ! isset($rate['Id'])) {
                continue;
            }
            $value = (float) ($rate['RateValue'] ?? -1);
            if ($value < 0) {
                continue;
            }
            $diff = abs($value - $targetPercent);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $rate;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $codes
     * @return array<string, mixed>|null
     */
    protected function findTaxCodeForRate(array $codes, string $rateId): ?array
    {
        foreach ($codes as $code) {
            if (! is_array($code) || empty($code['Id'])) {
                continue;
            }

            $details = $code['SalesTaxRateList']['TaxRateDetail'] ?? [];
            if ($details === []) {
                continue;
            }
            if (! array_is_list($details)) {
                $details = [$details];
            }

            foreach ($details as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $ref = $detail['TaxRateRef']['value'] ?? $detail['TaxRateRef'] ?? null;
                if ((string) $ref === $rateId) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $codes
     * @param  list<string>  $names
     * @return array<string, mixed>|null
     */
    protected function findTaxCodeByName(array $codes, array $names): ?array
    {
        foreach ($codes as $code) {
            if (! is_array($code) || empty($code['Name'])) {
                continue;
            }
            $normalized = strtolower(trim((string) $code['Name']));
            foreach ($names as $name) {
                if ($normalized === strtolower($name)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $codes
     */
    protected function resolveExemptTaxCodeId(array $codes): ?string
    {
        foreach ($codes as $code) {
            if (! is_array($code) || empty($code['Id'])) {
                continue;
            }
            $name = strtolower(trim((string) ($code['Name'] ?? '')));
            if (in_array($name, ['non', 'non-taxable', 'non taxable', 'exempt', 'out of scope', 'not applicable'], true)) {
                return (string) $code['Id'];
            }
            if (str_starts_with($name, 'non') || str_contains($name, 'exempt')) {
                return (string) $code['Id'];
            }
        }

        return null;
    }

    protected function astCacheKey(int $integrationId, string $realmId): string
    {
        return "qbo_ast_{$integrationId}_{$realmId}";
    }

    protected function taxRatesCacheKey(int $integrationId, string $realmId): string
    {
        return "qbo_tax_rates_{$integrationId}_{$realmId}";
    }

    protected function taxCodesCacheKey(int $integrationId, string $realmId): string
    {
        return "qbo_tax_codes_{$integrationId}_{$realmId}";
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
                $parts[] = (string) $err['Message'];
            }
        }

        return implode('; ', $parts);
    }
}
