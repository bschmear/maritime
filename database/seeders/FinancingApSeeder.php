<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Bill\Models\Bill;
use App\Domain\BillPayment\Models\BillPayment;
use App\Domain\BillPayment\Models\BillPaymentLine;
use App\Domain\Financing\Models\Financing;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Bill\Status as BillStatus;
use App\Enums\BillPayment\PayType;
use App\Enums\Entity\VendorType;
use App\Enums\Financing\BillType as FinancingBillType;
use App\Enums\Financing\Status as FinancingStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Sample financing, bills, and bill payments for new tenants.
 *
 * Re-running removes rows tagged with {@see SEED_MARKER} and inserts fresh demo data.
 */
class FinancingApSeeder extends Seeder
{
    public const SEED_MARKER = 'SEED:financing-ap';

    private const LENDER_VENDOR_CODE = 'SEED-LENDER-NPCF';

    private const FINANCING_INVOICE = '21375';

    private const BILL_DOC_INTEREST = 'SEED-BILL-INTEREST';

    private const BILL_DOC_VENDOR = 'SEED-BILL-VENDOR';

    private const PAYMENT_DOC = 'SEED-BPAY-001';

    public function run(): void
    {
        if (! Schema::hasTable('financings') || ! Schema::hasTable('bills') || ! Schema::hasTable('billpayments')) {
            $this->command?->warn('Financing/AP tables missing — run tenant migrations first.');

            return;
        }

        $this->purgeSeededRows();

        $lender = $this->ensureLenderVendor();
        $assetUnit = AssetUnit::query()->orderBy('id')->first();

        $financing = null;
        if ($assetUnit !== null) {
            $financing = $this->seedFinancing($lender, $assetUnit);
        } else {
            $this->command?->warn('No asset units found — skipping financing record (bills still seeded).');
        }

        $interestBill = $this->seedInterestBill($lender, $financing);
        $vendorBill = $this->seedVendorBill($lender);
        $this->seedBillPayment($lender, $interestBill);

        $this->command?->info('Financing/AP demo data seeded.');
    }

    private function purgeSeededRows(): void
    {
        $seededUnitIds = Financing::query()
            ->where(function ($q) {
                $q->where('notes', 'like', '%'.self::SEED_MARKER.'%')
                    ->orWhere('lender_invoice_number', self::FINANCING_INVOICE);
            })
            ->pluck('asset_unit_id')
            ->filter()
            ->all();

        $billIds = Bill::withTrashed()
            ->where(function ($q) {
                $q->where('private_note', 'like', '%'.self::SEED_MARKER.'%')
                    ->orWhereIn('doc_number', [
                        self::BILL_DOC_INTEREST,
                        self::BILL_DOC_VENDOR,
                        self::FINANCING_INVOICE,
                    ]);
            })
            ->pluck('id');

        if ($billIds->isNotEmpty()) {
            BillPaymentLine::query()->whereIn('bill_id', $billIds)->delete();
        }

        BillPayment::withTrashed()
            ->where(function ($q) {
                $q->where('private_note', 'like', '%'.self::SEED_MARKER.'%')
                    ->orWhere('doc_number', self::PAYMENT_DOC);
            })
            ->forceDelete();

        Bill::withTrashed()
            ->where(function ($q) {
                $q->where('private_note', 'like', '%'.self::SEED_MARKER.'%')
                    ->orWhereIn('doc_number', [
                        self::BILL_DOC_INTEREST,
                        self::BILL_DOC_VENDOR,
                        self::FINANCING_INVOICE,
                    ]);
            })
            ->forceDelete();

        Financing::query()
            ->where(function ($q) {
                $q->where('notes', 'like', '%'.self::SEED_MARKER.'%')
                    ->orWhere('lender_invoice_number', self::FINANCING_INVOICE);
            })
            ->delete();

        if ($seededUnitIds !== []) {
            AssetUnit::query()
                ->whereIn('id', $seededUnitIds)
                ->update(['is_financed' => false]);
        }
    }

    private function ensureLenderVendor(): Vendor
    {
        return Vendor::query()->updateOrCreate(
            ['vendor_code' => self::LENDER_VENDOR_CODE],
            [
                'display_name' => 'Northpoint Commercial Finance',
                'vendor_type' => VendorType::Lender->id(),
                'qbo_active' => true,
                'notes' => self::SEED_MARKER,
            ],
        );
    }

    private function seedFinancing(Vendor $lender, AssetUnit $assetUnit): Financing
    {
        $financing = Financing::query()->updateOrCreate(
            [
                'asset_unit_id' => $assetUnit->id,
                'lender_invoice_number' => self::FINANCING_INVOICE,
            ],
            [
                'vendor_id' => $lender->id,
                'dealer_name' => 'Lauderdale Inflatables',
                'dealer_cin' => '52151',
                'status' => FinancingStatus::Active,
                'principal_amount' => 4586.56,
                'current_balance' => 4242.58,
                'annual_interest_rate' => 6.5,
                'loan_term_months' => 60,
                'financed_at' => Carbon::parse('2025-05-21'),
                'interest_start_date' => Carbon::parse('2025-05-21'),
                'next_payment_date' => Carbon::now()->addDays(14),
                'monthly_payment_amount' => 125.00,
                'lender_status' => 'In-Stock',
                'aging_days' => 394,
                'supplier_name' => 'AB Inflatables USA',
                'supplier_cin' => '36914',
                'lender_invoice_number' => self::FINANCING_INVOICE,
                'model_number' => '10AL GY',
                'serial_vin' => 'XMO51210H324',
                'notes' => self::SEED_MARKER.' Sample financing.',
            ],
        );

        $assetUnit->update(['is_financed' => true]);

        return $financing;
    }

    private function seedInterestBill(Vendor $lender, ?Financing $financing): Bill
    {
        return Bill::query()->updateOrCreate(
            ['doc_number' => self::BILL_DOC_INTEREST],
            [
                'vendor_id' => $lender->id,
                'financing_id' => $financing?->id,
                'financing_bill_type' => FinancingBillType::Interest->value,
                'txn_date' => Carbon::now()->subDays(30),
                'due_date' => Carbon::now()->addDays(7),
                'total_amt' => 125.00,
                'balance' => 125.00,
                'currency_code' => 'USD',
                'status' => BillStatus::Open->value,
                'private_note' => self::SEED_MARKER.' Monthly financing interest.',
            ],
        );
    }

    private function seedVendorBill(Vendor $lender): Bill
    {
        return Bill::query()->updateOrCreate(
            ['doc_number' => self::BILL_DOC_VENDOR],
            [
                'vendor_id' => $lender->id,
                'txn_date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->addDays(20),
                'total_amt' => 850.00,
                'balance' => 850.00,
                'currency_code' => 'USD',
                'status' => BillStatus::Open->value,
                'private_note' => self::SEED_MARKER.' General vendor AP bill.',
            ],
        );
    }

    private function seedBillPayment(Vendor $lender, Bill $bill): BillPayment
    {
        $payment = BillPayment::query()->updateOrCreate(
            ['doc_number' => self::PAYMENT_DOC],
            [
                'vendor_id' => $lender->id,
                'txn_date' => Carbon::now()->subDays(5),
                'total_amt' => 50.00,
                'pay_type' => PayType::Check->value,
                'currency_code' => 'USD',
                'private_note' => self::SEED_MARKER.' Partial interest payment.',
            ],
        );

        BillPaymentLine::query()->updateOrCreate(
            [
                'bill_payment_id' => $payment->id,
                'bill_id' => $bill->id,
            ],
            [
                'amount' => 50.00,
                'position' => 1,
            ],
        );

        $bill->applyPayment(50.00);

        return $payment;
    }
}
