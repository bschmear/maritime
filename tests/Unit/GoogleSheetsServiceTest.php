<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleSheetsService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class GoogleSheetsServiceTest extends TestCase
{
    #[Test]
    public function normalize_sheet_values_reindexes_sparse_rows_and_stringifies_cells(): void
    {
        $service = new GoogleSheetsService(app(GoogleOAuthService::class));
        $method = new ReflectionMethod(GoogleSheetsService::class, 'normalizeSheetValues');
        $method->setAccessible(true);

        /** @var list<list<string>> $normalized */
        $normalized = $method->invoke($service, [
            [
                0 => '1',
                1 => 'Serial',
                10 => null,
                11 => null,
                12 => 'Location',
            ],
            ['Header', 42, false, null],
        ]);

        $this->assertSame([
            ['1', 'Serial', '', '', 'Location'],
            ['Header', '42', 'FALSE', ''],
        ], $normalized);
    }

    #[Test]
    public function default_sheet_id_for_rename_prefers_sheet1_or_single_sheet(): void
    {
        $service = new GoogleSheetsService(app(GoogleOAuthService::class));
        $method = new ReflectionMethod(GoogleSheetsService::class, 'defaultSheetIdForRename');
        $method->setAccessible(true);

        $this->assertSame(7, $method->invoke($service, ['Sheet1' => 7]));
        $this->assertSame(9, $method->invoke($service, ['Inventory' => 3, 'Sheet1' => 9]));
        $this->assertSame(4, $method->invoke($service, ['Only Tab' => 4]));
        $this->assertNull($method->invoke($service, ['Inventory' => 3, 'Other' => 5]));
    }
}
