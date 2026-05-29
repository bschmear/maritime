<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Integration\Models\Integration;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\InvoiceItem\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuickBooksTaxService
{
    /** Internal flag on QBO line payloads — stripped before POST. */
    public const LINE_TAXABLE_FLAG = '_maritime_taxable';

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
        $invoice->loadMissing(['items', 'transaction:id,tax_rate']);

        $taxTotal = round((float) $invoice->tax_total, 2);
        $invoiceHasTax = $taxTotal >= 0.01;
        $context = $this->resolveTaxContext($invoice, $taxTotal);

        if ($this->usesAutomatedSalesTax($integration)) {
            $payload = $this->applyAutomatedSalesTax($invoice, $payload, $lines, $invoiceHasTax, $taxTotal, $context);
        } else {
            $payload = $this->applyManualTax($integration, $invoice, $payload, $lines, $taxTotal, $invoiceHasTax, $context);
        }

        $payload['Line'] = $this->stripInternalLineKeys($lines);

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
            fn () => $this->fetchIsAutomatedSalesTax($integration),
        );
    }

    protected function fetchIsAutomatedSalesTax(Integration $integration): bool
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
            Log::warning('QuickBooks preferences fetch failed', QuickBooksHttpSupport::withIntuitTid($response, [
                'integration_id' => $integration->id,
                'status' => $response->status(),
            ]));

            return false;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return false;
        }

        // US companies always require TAX/NON on line items — treat as AST regardless of PartnerTaxEnabled.
        if ($this->preferencesCountryIsUs($json)) {
            return true;
        }

        return (bool) ($json['Preferences']['TaxPrefs']['PartnerTaxEnabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $preferencesJson
     */
    protected function preferencesCountryIsUs(array $preferencesJson): bool
    {
        $nameValues = $preferencesJson['Preferences']['OtherPrefs']['NameValue'] ?? [];
        foreach ((array) $nameValues as $nv) {
            if (! is_array($nv)) {
                continue;
            }
            if (($nv['Name'] ?? '') === 'CountryCode' && strtoupper((string) ($nv['Value'] ?? '')) === 'US') {
                return true;
            }
        }

        return false;
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
        $items = $this->orderedInvoiceItems($invoice);
        $effectiveRate = (float) ($context['effective_rate'] ?? 0);
        $anyTaxable = false;
        $itemIndex = 0;

        foreach ($lines as $index => $line) {
            if (($line['DetailType'] ?? '') !== 'SalesItemLineDetail') {
                continue;
            }

            $item = $items[$itemIndex] ?? null;
            $itemIndex++;

            $taxable = $this->lineIsTaxable($line, $item, $effectiveRate, $invoiceHasTax);
            $anyTaxable = $anyTaxable || $taxable;
            $lines[$index] = $this->withLineTaxCode($line, $taxable ? self::AST_TAXABLE : self::AST_NON_TAXABLE);
        }

        if ($anyTaxable) {
            $txnTaxDetail = [
                'TxnTaxCodeRef' => ['value' => self::AST_TAXABLE],
            ];
            if ($taxTotal >= 0.01) {
                $txnTaxDetail['TotalTax'] = $taxTotal;
            }
            $payload['TxnTaxDetail'] = $txnTaxDetail;
            $payload['ApplyTaxAfterDiscount'] = false;
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
        $taxRates = $this->cachedTaxRates($integration);

        // US QBO companies with no TaxCodes are on AST — use TAX/NON line refs.
        if ($taxCodes === [] && $taxRates !== []) {
            return $this->applyAutomatedSalesTax($invoice, $payload, $lines, $invoiceHasTax, $taxTotal, $context);
        }

        $exemptCodeId = $this->resolveExemptTaxCodeId($taxCodes);
        $effectiveRate = (float) ($context['effective_rate'] ?? 0);

        if (! $invoiceHasTax) {
            if ($exemptCodeId !== null) {
                foreach ($lines as $index => $line) {
                    $lines[$index] = $this->withLineTaxCode($line, $exemptCodeId);
                }
            }

            return $payload;
        }

        $taxableSubtotal = $this->taxableSubtotal($invoice, $effectiveRate);
        $taxPercent = $effectiveRate;
        if ($taxPercent <= 0 && $taxableSubtotal > 0) {
            $taxPercent = round(($taxTotal / $taxableSubtotal) * 100, 4);
        }

        $taxRate = $taxPercent > 0 ? $this->findBestTaxRate($taxRates, $taxPercent) : null;

        $taxCode = $taxRate !== null
            ? $this->findTaxCodeForRate($taxCodes, (string) $taxRate['Id'])
            : null;

        if ($taxCode === null && $taxPercent > 0) {
            $taxCode = $this->findTaxCodeByPercent($taxCodes, $taxRates, $taxPercent);
        }

        $taxCode ??= $this->resolveDefaultTaxableTaxCode($taxCodes);

        if ($taxRate === null && $taxCode !== null) {
            $taxRate = $this->resolveTaxRateFromTaxCode($taxCode, $taxRates);
        }

        $taxCodeId = $taxCode !== null ? (string) $taxCode['Id'] : null;

        if ($taxCodeId === null) {
            Log::warning('QuickBooks manual tax: no matching TaxCode; invoice pushed without tax detail', [
                'integration_id' => $integration->id,
                'invoice_id' => $invoice->id,
                'tax_total' => $taxTotal,
                'tax_percent' => $taxPercent,
            ]);

            return $payload;
        }

        $taxRateId = $taxRate !== null ? (string) $taxRate['Id'] : null;
        $rateValue = $taxRate !== null ? (float) ($taxRate['RateValue'] ?? $taxPercent) : $taxPercent;

        $items = $this->orderedInvoiceItems($invoice);
        $itemIndex = 0;
        foreach ($lines as $index => $line) {
            if (($line['DetailType'] ?? '') !== 'SalesItemLineDetail') {
                continue;
            }

            $item = $items[$itemIndex] ?? null;
            $itemIndex++;

            $codeId = $this->lineIsTaxable($line, $item, $effectiveRate, true)
                ? $taxCodeId
                : ($exemptCodeId ?? $taxCodeId);
            $lines[$index] = $this->withLineTaxCode($line, $codeId);
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
        $payload['ApplyTaxAfterDiscount'] = false;
        $payload['TxnTaxDetail'] = $txnTaxDetail;

        return $payload;
    }

    /**
     * @return array{effective_rate: float}
     */
    protected function resolveTaxContext(Invoice $invoice, float $taxTotal): array
    {
        $transaction = $invoice->transaction;

        $explicitRate = $this->maxStoredTaxRate($invoice);
        if ($explicitRate <= 0 && $transaction !== null) {
            $explicitRate = (float) ($transaction->tax_rate ?? 0);
        }

        $taxableSubtotal = $this->taxableSubtotal($invoice, $explicitRate);

        // Fallback: if taxableSubtotal is 0, use invoice subtotal directly
        if ($taxableSubtotal <= 0) {
            $taxableSubtotal = round((float) $invoice->subtotal, 2);
        }

        $inferredRate = $taxableSubtotal > 0 && $taxTotal >= 0.01
            ? round(($taxTotal / $taxableSubtotal) * 100, 4)
            : 0.0;

        return [
            'effective_rate' => $explicitRate > 0 ? $explicitRate : $inferredRate,
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

    /**
     * @param  array<string, mixed>  $line
     */
    protected function lineIsTaxable(array $line, ?InvoiceItem $item, float $effectiveTaxRate, bool $invoiceHasTax): bool
    {
        if ($item !== null && $this->itemIsExplicitlyTaxable($item)) {
            return true;
        }

        if (array_key_exists(self::LINE_TAXABLE_FLAG, $line) && (bool) $line[self::LINE_TAXABLE_FLAG]) {
            return true;
        }

        if ($item !== null && $this->itemIsExplicitlyNonTaxable($item)) {
            return false;
        }

        if ($item !== null) {
            if ((float) ($item->tax_amount ?? 0) >= 0.01) {
                return true;
            }

            if ((float) ($item->tax_rate ?? 0) > 0.0001) {
                return true;
            }
        }

        if ($item === null) {
            return $invoiceHasTax;
        }

        return $effectiveTaxRate > 0.0001 || $invoiceHasTax;
    }

    protected function itemIsExplicitlyTaxable(InvoiceItem $item): bool
    {
        $attrs = $item->getAttributes();

        if (! array_key_exists('taxable', $attrs)) {
            return false;
        }

        if ($attrs['taxable'] === null) {
            return false;
        }

        return filter_var($attrs['taxable'], FILTER_VALIDATE_BOOLEAN);
    }

    protected function itemIsExplicitlyNonTaxable(InvoiceItem $item): bool
    {
        $attrs = $item->getAttributes();

        if (! array_key_exists('taxable', $attrs)) {
            return false;
        }

        // null means unset — not explicitly non-taxable
        if ($attrs['taxable'] === null) {
            return false;
        }

        return ! filter_var($attrs['taxable'], FILTER_VALIDATE_BOOLEAN);
    }

    protected function orderedInvoiceItems(Invoice $invoice): Collection
    {
        return $invoice->items
            ->sortBy('position')
            ->sortBy('id')
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function stripInternalLineKeys(array $lines): array
    {
        return array_map(function (array $line) {
            unset($line[self::LINE_TAXABLE_FLAG]);

            return $line;
        }, $lines);
    }

    protected function taxableSubtotal(Invoice $invoice, float $effectiveTaxRate): float
    {
        $items = $this->orderedInvoiceItems($invoice);
        if ($items->isEmpty()) {
            $subtotal = round((float) $invoice->subtotal, 2);

            return ((float) $invoice->tax_total) >= 0.01 ? $subtotal : 0.0;
        }

        $sum = 0.0;
        foreach ($items as $item) {
            if (! $this->lineIsTaxable([], $item, $effectiveTaxRate, true)) {
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
     * Find the TaxRate whose RateValue is closest to $targetPercent.
     *
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
     * Find the TaxCode whose linked TaxRate is closest to $targetPercent.
     * Only returns a match within a 0.25% tolerance.
     *
     * @param  list<array<string, mixed>>  $codes
     * @param  list<array<string, mixed>>  $rates
     * @return array<string, mixed>|null
     */
    protected function findTaxCodeByPercent(array $codes, array $rates, float $targetPercent): ?array
    {
        if ($targetPercent <= 0) {
            return null;
        }

        $bestCode = null;
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($codes as $code) {
            if (! is_array($code) || empty($code['Id']) || $this->isExemptTaxCodeName((string) ($code['Name'] ?? ''))) {
                continue;
            }

            $linkedRate = $this->resolveTaxRateFromTaxCode($code, $rates);
            if ($linkedRate === null) {
                continue;
            }

            $value = (float) ($linkedRate['RateValue'] ?? -1);
            if ($value < 0) {
                continue;
            }

            $diff = abs($value - $targetPercent);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestCode = $code;
            }
        }

        return $bestDiff <= 0.25 ? $bestCode : null;
    }

    /**
     * Find the TaxCode that references the given TaxRate ID in its SalesTaxRateList.
     *
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
     * Resolve the first TaxRate referenced within a TaxCode's SalesTaxRateList.
     *
     * @param  array<string, mixed>  $code
     * @param  list<array<string, mixed>>  $rates
     * @return array<string, mixed>|null
     */
    protected function resolveTaxRateFromTaxCode(array $code, array $rates): ?array
    {
        $details = $code['SalesTaxRateList']['TaxRateDetail'] ?? [];
        if ($details === []) {
            return null;
        }
        if (! array_is_list($details)) {
            $details = [$details];
        }

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $rateId = $detail['TaxRateRef']['value'] ?? $detail['TaxRateRef'] ?? null;
            if ($rateId === null || $rateId === '') {
                continue;
            }
            foreach ($rates as $rate) {
                if (is_array($rate) && isset($rate['Id']) && (string) $rate['Id'] === (string) $rateId) {
                    return $rate;
                }
            }
        }

        return null;
    }

    /**
     * Return the first active TaxCode that has rate details and is not an exempt code.
     *
     * @param  list<array<string, mixed>>  $codes
     * @return array<string, mixed>|null
     */
    protected function resolveDefaultTaxableTaxCode(array $codes): ?array
    {
        foreach ($codes as $code) {
            if (! is_array($code) || empty($code['Id'])) {
                continue;
            }
            if ($this->isExemptTaxCodeName((string) ($code['Name'] ?? ''))) {
                continue;
            }
            $details = $code['SalesTaxRateList']['TaxRateDetail'] ?? [];
            if ($details === []) {
                continue;
            }

            return $code;
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
            if ($this->isExemptTaxCodeName((string) ($code['Name'] ?? ''))) {
                return (string) $code['Id'];
            }
        }

        return null;
    }

    protected function isExemptTaxCodeName(string $name): bool
    {
        $name = strtolower(trim($name));

        return in_array($name, ['non', 'non-taxable', 'non taxable', 'exempt', 'out of scope', 'not applicable'], true)
            || str_starts_with($name, 'non')
            || str_contains($name, 'exempt');
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
