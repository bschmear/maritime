<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Integration\Models\Integration;
use App\Domain\ServiceItem\Actions\CreateServiceItem;
use App\Domain\ServiceItem\Actions\UpdateServiceItem;
use App\Domain\ServiceItem\Models\ServiceItem;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Enums\ServiceItem\BillingType;
use App\Services\Payments\QuickBooksOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PullServiceItemsFromQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantUserProfileId,
        public int $defaultBillingType = BillingType::Flat->value,
    ) {}

    public function handle(
        QuickBooksOAuthService $oauth,
        CreateServiceItem $createServiceItem,
        UpdateServiceItem $updateServiceItem,
    ): void {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            return;
        }

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Syncing,
            'sync_error_message' => null,
        ]);

        try {
            $start = 1;
            $pageSize = 100;

            do {
                $sql = "select * from Item where Type = 'Service' STARTPOSITION {$start} MAXRESULTS {$pageSize}";
                $payload = $oauth->queryAccountingForIntegration($integration, $sql);
                $integration->refresh();

                if (! empty($payload['Fault'])) {
                    $msg = $this->faultMessage($payload['Fault']);
                    throw new \RuntimeException($msg ?: 'QuickBooks returned a fault.');
                }

                $queryResponse = $payload['QueryResponse'] ?? [];
                $items = $queryResponse['Item'] ?? [];
                if ($items !== [] && ! array_is_list($items)) {
                    $items = [$items];
                }

                foreach ($items as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $this->importOneItem($row, $createServiceItem, $updateServiceItem);
                }

                $count = count($items);

                $start += $pageSize;
            } while ($count === $pageSize);

            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Success,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $integration->refresh();
            $integration->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'sync_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importOneItem(
        array $row,
        CreateServiceItem $createServiceItem,
        UpdateServiceItem $updateServiceItem,
    ): void {
        if (array_key_exists('Active', $row) && $row['Active'] === false) {
            return;
        }

        $type = $this->normalizeNamePart($row['Type'] ?? null);
        if ($type !== '' && strcasecmp($type, 'Service') !== 0) {
            return;
        }

        $qboId = isset($row['Id']) ? (string) $row['Id'] : '';
        if ($qboId === '') {
            return;
        }

        $name = $this->normalizeNamePart($row['Name'] ?? null);
        if ($name === '') {
            return;
        }

        $payload = $this->mapRowToPayload($row, $qboId, $name);

        $existing = ServiceItem::query()->where('quickbooks_item_id', $qboId)->first();

        if ($existing !== null) {
            $updateServiceItem((int) $existing->id, $payload);

            return;
        }

        $createServiceItem(array_merge($payload, ['for_import' => true]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRowToPayload(array $row, string $qboId, string $name): array
    {
        $description = $this->normalizeNamePart($row['Description'] ?? null);
        $sku = $this->normalizeNamePart($row['Sku'] ?? null);
        $purchaseDesc = $this->normalizeNamePart($row['PurchaseDesc'] ?? null);
        $unitPrice = $this->parseMoney($row['UnitPrice'] ?? null);
        $purchaseCost = $this->parseMoney($row['PurchaseCost'] ?? null);
        $taxable = $this->parseBool($row['Taxable'] ?? false);
        $attributes = $this->buildQboAttributes($row);

        $notes = $purchaseDesc !== '' ? 'Purchase: '.$purchaseDesc : null;

        return [
            'display_name' => $name,
            'code' => $sku !== '' ? $sku : null,
            'description' => $description !== '' ? $description : null,
            'billing_type' => $this->defaultBillingType,
            'default_rate' => $unitPrice,
            'default_cost' => $purchaseCost,
            'default_hours' => 1,
            'taxable' => $taxable,
            'billable' => true,
            'warranty_eligible' => false,
            'inactive' => false,
            'notes' => $notes,
            'quickbooks_item_id' => $qboId,
            'attributes' => $attributes,
        ];
    }

    private function normalizeNamePart(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, (float) $value);
    }

    private function parseBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function buildQboAttributes(array $row): ?array
    {
        $quickbooks = [];

        $incomeName = $this->refName($row['IncomeAccountRef'] ?? null);
        $incomeId = $this->refValue($row['IncomeAccountRef'] ?? null);
        if ($incomeName !== '') {
            $quickbooks['income_account'] = $incomeName;
        }
        if ($incomeId !== '') {
            $quickbooks['income_account_id'] = $incomeId;
        }

        $expenseName = $this->refName($row['ExpenseAccountRef'] ?? null);
        $expenseId = $this->refValue($row['ExpenseAccountRef'] ?? null);
        if ($expenseName !== '') {
            $quickbooks['expense_account'] = $expenseName;
        }
        if ($expenseId !== '') {
            $quickbooks['expense_account_id'] = $expenseId;
        }

        return $quickbooks !== [] ? ['quickbooks' => $quickbooks] : null;
    }

    /**
     * @param  array<string, mixed>|null  $ref
     */
    private function refName(?array $ref): string
    {
        if ($ref === null) {
            return '';
        }

        return $this->normalizeNamePart($ref['name'] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $ref
     */
    private function refValue(?array $ref): string
    {
        if ($ref === null) {
            return '';
        }

        $value = $ref['value'] ?? null;
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $fault
     */
    private function faultMessage(array $fault): string
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
