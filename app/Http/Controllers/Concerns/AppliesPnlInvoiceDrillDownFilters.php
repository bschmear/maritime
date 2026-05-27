<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Asset\Models\Asset;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesPnlInvoiceDrillDownFilters
{
    /**
     * Narrow the invoice index when opened from the P&L report (pnl_from, pnl_to, optional pnl_metric).
     *
     * @param  Builder<\App\Domain\Invoice\Models\Invoice>  $query
     */
    protected function applyPnlDrillDownFilters(Request $request, Builder $query): void
    {
        if (! $request->filled('pnl_from') || ! $request->filled('pnl_to')) {
            return;
        }

        $from = Carbon::parse($request->string('pnl_from'))->startOfDay();
        $to = Carbon::parse($request->string('pnl_to'))->endOfDay();
        $table = $this->recordModel->getTable();

        $subsidiaryId = $request->filled('subsidiary_id') ? $request->integer('subsidiary_id') : null;
        $locationId = $request->filled('location_id') ? $request->integer('location_id') : null;

        $metric = strtolower(trim($request->string('pnl_metric')->toString()));

        $dealershipWarranty = WarrantyCoverageType::Dealership->value;
        $manufacturerWarranty = WarrantyCoverageType::Manufacturer->value;

        $serviceMorph = static function (\Illuminate\Database\Eloquent\Builder $q): void {
            $q->where(function ($inner) {
                $inner->whereNull('itemable_type')
                    ->orWhereNotIn('itemable_type', [Asset::class, InventoryItem::class]);
            });
        };

        $warrantyDealershipLine = static function (\Illuminate\Database\Eloquent\Builder $q) use ($dealershipWarranty): void {
            $q->where(function ($inner) use ($dealershipWarranty) {
                $inner->where(function ($w) use ($dealershipWarranty) {
                    $w->where('is_warranty', true)
                        ->where('warranty_type', $dealershipWarranty);
                })->orWhere(function ($w) {
                    $w->where('is_warranty', true)
                        ->whereNull('warranty_type')
                        ->where('billable_to', 'internal');
                });
            });
        };

        $warrantyManufacturerLine = static function (\Illuminate\Database\Eloquent\Builder $q) use ($manufacturerWarranty): void {
            $q->where(function ($inner) use ($manufacturerWarranty) {
                $inner->where(function ($w) use ($manufacturerWarranty) {
                    $w->where('is_warranty', true)
                        ->where('warranty_type', $manufacturerWarranty);
                })->orWhere('billable_to', 'manufacturer');
            });
        };

        if (str_starts_with($metric, 'warranty_')) {
            $query->whereNotIn($table.'.status', ['draft', 'void'])
                ->whereRaw(
                    'COALESCE((SELECT wo.completed_at FROM work_orders wo WHERE wo.id = '.$table.'.work_order_id LIMIT 1), '.$table.'.created_at) BETWEEN ? AND ?',
                    [$from, $to]
                );
        } else {
            $query->whereNotIn($table.'.status', ['draft', 'void'])
                ->whereBetween($table.'.created_at', [$from, $to]);
        }

        $this->applyPnlSubsidiaryLocationToInvoiceEloquent($query, $subsidiaryId, $locationId, $table);

        match ($metric) {
            'income_boat' => $query->whereHas('items', function ($q) {
                $q->where('itemable_type', Asset::class)
                    ->where('billable_to', 'customer');
            }),
            'income_parts' => $query->whereHas('items', function ($q) {
                $q->where('itemable_type', InventoryItem::class)
                    ->where('billable_to', 'customer');
            }),
            'income_service' => $query->whereHas('items', function ($q) use ($serviceMorph) {
                $serviceMorph($q);
                $q->where('billable_to', 'customer');
            }),
            'income_service_wo' => $query->whereNotNull($table.'.work_order_id')
                ->whereHas('items', function ($q) use ($serviceMorph) {
                    $serviceMorph($q);
                    $q->where('billable_to', 'customer');
                }),
            'warranty_dealership_invoiced' => $query->whereHas('items', function ($q) use ($warrantyDealershipLine) {
                $warrantyDealershipLine($q);
            }),
            'warranty_manufacturer_invoiced' => $query->whereHas('items', function ($q) use ($warrantyManufacturerLine) {
                $warrantyManufacturerLine($q);
            }),
            'cogs_boat' => $query->whereHas('items', function ($q) {
                $q->where('itemable_type', Asset::class);
            }),
            'cogs_parts' => $query->whereHas('items', function ($q) {
                $q->where('itemable_type', InventoryItem::class);
            }),
            'cogs_service' => $query->whereHas('items', function ($q) use ($serviceMorph) {
                $serviceMorph($q);
            }),
            'cogs_service_wo' => $query->whereNotNull($table.'.work_order_id')
                ->whereHas('items', function ($q) use ($serviceMorph) {
                    $serviceMorph($q);
                }),
            'cogs_all', 'income_all' => null,
            default => null,
        };
    }

    /**
     * @param  Builder<\App\Domain\Invoice\Models\Invoice>  $query
     */
    private function applyPnlSubsidiaryLocationToInvoiceEloquent(Builder $query, ?int $subsidiaryId, ?int $locationId, string $table): void
    {
        if ($subsidiaryId !== null) {
            $query->where(function ($q) use ($subsidiaryId, $table) {
                $q->whereHas('transaction', fn ($t) => $t->where('subsidiary_id', $subsidiaryId))
                    ->orWhere(function ($inner) use ($subsidiaryId, $table) {
                        $inner->whereNull($table.'.transaction_id')
                            ->where($table.'.subsidiary_id', $subsidiaryId);
                    });
            });
        }

        if ($locationId !== null) {
            $query->where(function ($q) use ($locationId, $table) {
                $q->whereHas('transaction', fn ($t) => $t->where('location_id', $locationId))
                    ->orWhere(function ($inner) use ($locationId, $table) {
                        $inner->whereNull($table.'.transaction_id')
                            ->where($table.'.location_id', $locationId);
                    });
            });
        }
    }
}
