<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Vendor\Models\Vendor;

final class QuickBooksVendorResolver
{
    /**
     * Resolve a Maritime vendor id from a QuickBooks VendorRef and optional full Vendor entity row.
     *
     * @param  array<string, mixed>|null  $vendorRef
     * @param  array<string, mixed>|null  $qboVendorRow
     */
    public static function resolveLocalVendorId(?array $vendorRef, ?array $qboVendorRow = null): ?int
    {
        if ($qboVendorRow !== null) {
            $fromRow = self::resolveLocalVendorIdFromQboVendorRow($qboVendorRow);
            if ($fromRow !== null) {
                return $fromRow;
            }
        }

        $qboId = self::quickbooksVendorIdFromRef($vendorRef);
        if ($qboId !== null) {
            $byQboId = Vendor::query()->where('quickbooks_id', $qboId)->value('id');
            if ($byQboId !== null) {
                return (int) $byQboId;
            }
        }

        $refName = QuickBooksRowMapper::refName($vendorRef);
        if ($refName === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($refName));

        $byDisplayName = Vendor::query()
            ->whereRaw('LOWER(TRIM(display_name)) = ?', [$normalized])
            ->orderBy('id')
            ->value('id');

        if ($byDisplayName !== null) {
            return (int) $byDisplayName;
        }

        $byCompanyName = Vendor::query()
            ->whereNotNull('company_name')
            ->whereRaw('LOWER(TRIM(company_name)) = ?', [$normalized])
            ->orderBy('id')
            ->value('id');

        return $byCompanyName !== null ? (int) $byCompanyName : null;
    }

    /**
     * @param  array<string, mixed>  $row  QuickBooks Vendor entity
     */
    public static function resolveLocalVendorIdFromQboVendorRow(array $row): ?int
    {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        if ($qboId !== '') {
            $byQboId = Vendor::query()->where('quickbooks_id', $qboId)->value('id');
            if ($byQboId !== null) {
                return (int) $byQboId;
            }
        }

        foreach (['DisplayName', 'CompanyName', 'PrintOnCheckName'] as $nameKey) {
            $name = QuickBooksRowMapper::normalizeString($row[$nameKey] ?? null);
            if ($name === '') {
                continue;
            }

            $normalized = mb_strtolower(trim($name));

            $byDisplayName = Vendor::query()
                ->whereRaw('LOWER(TRIM(display_name)) = ?', [$normalized])
                ->orderBy('id')
                ->value('id');

            if ($byDisplayName !== null) {
                return (int) $byDisplayName;
            }

            $byCompanyName = Vendor::query()
                ->whereNotNull('company_name')
                ->whereRaw('LOWER(TRIM(company_name)) = ?', [$normalized])
                ->orderBy('id')
                ->value('id');

            if ($byCompanyName !== null) {
                return (int) $byCompanyName;
            }
        }

        $email = QuickBooksRowMapper::normalizeEmail($row['PrimaryEmailAddr']['Address'] ?? null);
        if ($email !== null) {
            $byEmail = self::resolveLocalVendorIdByEmail($email);
            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        return null;
    }

    public static function resolveLocalVendorIdByEmail(string $email): ?int
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        $byVendorEmail = Vendor::query()
            ->where(function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(TRIM(secondary_email)) = ?', [$normalized])
                    ->orWhereRaw('LOWER(TRIM(contact_email)) = ?', [$normalized]);
            })
            ->orderBy('id')
            ->value('id');

        if ($byVendorEmail !== null) {
            return (int) $byVendorEmail;
        }

        $byPrimaryContact = Vendor::query()
            ->whereHas('primaryContact', function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(TRIM(email)) = ?', [$normalized]);
            })
            ->orderBy('id')
            ->value('id');

        if ($byPrimaryContact !== null) {
            return (int) $byPrimaryContact;
        }

        $byLinkedContact = Vendor::query()
            ->whereHas('linkedContacts', function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(TRIM(email)) = ?', [$normalized]);
            })
            ->orderBy('id')
            ->value('id');

        return $byLinkedContact !== null ? (int) $byLinkedContact : null;
    }

    public static function backfillQuickbooksIdOnVendor(int $vendorId, string $qboVendorId): void
    {
        if ($qboVendorId === '') {
            return;
        }

        Vendor::query()
            ->whereKey($vendorId)
            ->where(function ($query): void {
                $query->whereNull('quickbooks_id')->orWhere('quickbooks_id', '');
            })
            ->update(['quickbooks_id' => $qboVendorId]);
    }

    /**
     * @param  array<string, mixed>|null  $vendorRef
     */
    public static function quickbooksVendorIdFromRef(?array $vendorRef): ?string
    {
        if ($vendorRef === null) {
            return null;
        }

        $qboId = QuickBooksRowMapper::refValue($vendorRef);

        return $qboId !== '' ? $qboId : null;
    }
}
