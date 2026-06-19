<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

final class QuickBooksChartOfAccountsMapper
{
    /**
     * Map a QuickBooks Online Account entity row to a chart_of_accounts payload.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function mapAccountRow(array $row): array
    {
        $qboId = self::extractQboId($row);
        $fullyQualifiedName = QuickBooksRowMapper::normalizeString($row['FullyQualifiedName'] ?? null);
        $name = QuickBooksRowMapper::normalizeString($row['Name'] ?? null);

        if ($name === '' && $fullyQualifiedName !== '') {
            $name = self::shortNameFromFullyQualified($fullyQualifiedName);
        }

        return [
            'name' => $name,
            'quickbooks_account_id' => $qboId,
            'fully_qualified_name' => $fullyQualifiedName !== '' ? $fullyQualifiedName : $name,
            'account_type' => self::normalizeAccountType($row),
            'detail_type' => self::normalizeDetailType($row),
            'active' => ! (array_key_exists('Active', $row) && $row['Active'] === false),
            'parent_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function extractQboId(array $row): string
    {
        $id = $row['Id'] ?? null;
        if ($id !== null && $id !== '') {
            return (string) $id;
        }

        return QuickBooksRowMapper::refValue($row['Id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function parentQboId(array $row): string
    {
        return QuickBooksRowMapper::refValue($row['ParentRef'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function normalizeAccountType(array $row): ?string
    {
        $type = QuickBooksRowMapper::normalizeString($row['AccountType'] ?? null);

        return $type !== '' ? $type : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function normalizeDetailType(array $row): ?string
    {
        $detail = QuickBooksRowMapper::normalizeString($row['AccountSubType'] ?? null);

        return $detail !== '' ? $detail : null;
    }

    private static function shortNameFromFullyQualified(string $fullyQualifiedName): string
    {
        $parts = explode(':', $fullyQualifiedName);

        return trim((string) end($parts));
    }
}
