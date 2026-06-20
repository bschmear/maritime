<?php

declare(strict_types=1);

namespace App\Support\ChartOfAccount;

/**
 * Universal QuickBooks-style chart of accounts for marine dealerships.
 *
 * Names and hierarchy follow common QBO industry templates (excluding
 * landscaping-specific income/expense lines). quickbooks_account_id is
 * intentionally omitted so imports can link seeded rows by fully_qualified_name.
 */
final class DefaultChartOfAccounts
{
    /**
     * @return list<array{fully_qualified_name: string, account_type: string, detail_type: string}>
     */
    public static function definitions(): array
    {
        $rows = [
            // Balance sheet — assets
            ['Checking', 'Bank', 'Checking'],
            ['Savings', 'Bank', 'Savings'],
            ['Undeposited Funds', 'Other Current Asset', 'UndepositedFunds'],
            ['Inventory Asset', 'Other Current Asset', 'Inventory'],
            ['Prepaid Expenses', 'Other Current Asset', 'PrepaidExpenses'],
            ['Uncategorized Asset', 'Other Current Asset', 'OtherCurrentAssets'],
            ['Accounts Receivable (A/R)', 'Accounts Receivable', 'AccountsReceivable'],
            ['Truck', 'Fixed Asset', 'Vehicles'],
            ['Truck:Original Cost', 'Fixed Asset', 'Vehicles'],
            ['Truck:Depreciation', 'Fixed Asset', 'AccumulatedDepreciation'],

            // Balance sheet — liabilities & equity
            ['Accounts Payable (A/P)', 'Accounts Payable', 'AccountsPayable'],
            ['Loan Payable', 'Other Current Liability', 'OtherCurrentLiabilities'],
            ['Notes Payable', 'Long Term Liability', 'OtherLongTermLiabilities'],
            ['Mastercard', 'Credit Card', 'CreditCard'],
            ['Visa', 'Credit Card', 'CreditCard'],
            ['Opening Balance Equity', 'Equity', 'OpeningBalanceEquity'],
            ['Retained Earnings', 'Equity', 'RetainedEarnings'],

            // Income
            ['Sales of Product Income', 'Income', 'SalesOfProductIncome'],
            ['Services', 'Income', 'ServiceFeeIncome'],
            ['Billable Expense Income', 'Income', 'ServiceFeeIncome'],
            ['Fees Billed', 'Income', 'ServiceFeeIncome'],
            ['Discounts given', 'Income', 'DiscountsRefundsGiven'],
            ['Refunds-Allowances', 'Income', 'DiscountsRefundsGiven'],
            ['Other Income', 'Income', 'OtherPrimaryIncome'],
            ['Uncategorized Income', 'Income', 'ServiceFeeIncome'],
            ['Unapplied Cash Payment Income', 'Income', 'UnappliedCashPaymentIncome'],
            ['Interest Earned', 'Other Income', 'InterestEarned'],
            ['Other Portfolio Income', 'Other Income', 'OtherMiscellaneousIncome'],

            // Cost of goods sold
            ['Cost of Goods Sold', 'Cost of Goods Sold', 'SuppliesMaterialsCogs'],

            // Operating expenses — top level
            ['Advertising', 'Expense', 'AdvertisingPromotional'],
            ['Bank Charges', 'Expense', 'BankCharges'],
            ['Automobile', 'Expense', 'Auto'],
            ['Automobile:Fuel', 'Expense', 'Auto'],
            ['Commissions & fees', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Dues & Subscriptions', 'Expense', 'DuesSubscriptions'],
            ['Equipment Rental', 'Expense', 'EquipmentRental'],
            ['Insurance', 'Expense', 'Insurance'],
            ['Insurance:Workers Compensation', 'Expense', 'Insurance'],
            ['Job Expenses', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Job Expenses:Cost of Labor', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Job Expenses:Cost of Labor:Installation', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Job Expenses:Cost of Labor:Maintenance and Repairs', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Job Expenses:Equipment Rental', 'Expense', 'EquipmentRental'],
            ['Job Expenses:Job Materials', 'Expense', 'SuppliesMaterials'],
            ['Job Expenses:Permits', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Legal & Professional Fees', 'Expense', 'LegalProfessionalFees'],
            ['Legal & Professional Fees:Accounting', 'Expense', 'LegalProfessionalFees'],
            ['Legal & Professional Fees:Bookkeeper', 'Expense', 'LegalProfessionalFees'],
            ['Legal & Professional Fees:Lawyer', 'Expense', 'LegalProfessionalFees'],
            ['Maintenance and Repair', 'Expense', 'RepairMaintenance'],
            ['Maintenance and Repair:Building Repairs', 'Expense', 'RepairMaintenance'],
            ['Maintenance and Repair:Computer Repairs', 'Expense', 'RepairMaintenance'],
            ['Maintenance and Repair:Equipment Repairs', 'Expense', 'RepairMaintenance'],
            ['Meals and Entertainment', 'Expense', 'EntertainmentMeals'],
            ['Office Expenses', 'Expense', 'OfficeGeneralAdministrativeExpenses'],
            ['Promotional', 'Expense', 'AdvertisingPromotional'],
            ['Purchases', 'Expense', 'SuppliesMaterials'],
            ['Rent or Lease', 'Expense', 'RentOrLeaseOfBuildings'],
            ['Stationery & Printing', 'Expense', 'OfficeGeneralAdministrativeExpenses'],
            ['Supplies', 'Expense', 'SuppliesMaterials'],
            ['Taxes & Licenses', 'Expense', 'TaxesPaid'],
            ['Travel', 'Expense', 'Travel'],
            ['Travel Meals', 'Expense', 'TravelMeals'],
            ['Utilities', 'Expense', 'Utilities'],
            ['Utilities:Gas and Electric', 'Expense', 'Utilities'],
            ['Utilities:Telephone', 'Expense', 'Utilities'],
            ['Disposal Fees', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Uncategorized Expense', 'Expense', 'OtherMiscellaneousServiceCost'],
            ['Unapplied Cash Bill Payment Expense', 'Expense', 'UnappliedCashBillPaymentExpense'],

            // Other expense
            ['Depreciation', 'Other Expense', 'Depreciation'],
            ['Miscellaneous', 'Other Expense', 'OtherMiscellaneousExpense'],
            ['Penalties & Settlements', 'Other Expense', 'PenaltiesSettlements'],
        ];

        return array_map(
            static fn (array $row): array => [
                'fully_qualified_name' => $row[0],
                'name' => self::shortName($row[0]),
                'account_type' => $row[1],
                'detail_type' => $row[2],
            ],
            $rows,
        );
    }

    public static function shortName(string $fullyQualifiedName): string
    {
        $parts = explode(':', $fullyQualifiedName);

        return trim((string) end($parts));
    }

    public static function parentFullyQualifiedName(string $fullyQualifiedName): ?string
    {
        if (! str_contains($fullyQualifiedName, ':')) {
            return null;
        }

        $parts = explode(':', $fullyQualifiedName);
        array_pop($parts);

        return implode(':', $parts);
    }

    /**
     * @return list<array{fully_qualified_name: string, name: string, account_type: string, detail_type: string}>
     */
    public static function definitionsByDepth(): array
    {
        $definitions = self::definitions();

        usort(
            $definitions,
            static fn (array $a, array $b): int =>
                substr_count($a['fully_qualified_name'], ':') <=> substr_count($b['fully_qualified_name'], ':'),
        );

        return $definitions;
    }
}
