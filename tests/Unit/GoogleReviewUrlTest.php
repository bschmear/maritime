<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Subsidiary\Support\GoogleReviewUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleReviewUrlTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, GoogleReviewUrl::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'null stays null' => [null, null],
            'empty string becomes null' => ['', null],
            'whitespace becomes null' => ['   ', null],
            'adds https scheme' => [
                'g.page/r/CQu90eLhS4cAEAE/review',
                'https://g.page/r/CQu90eLhS4cAEAE/review',
            ],
            'preserves https' => [
                'https://g.page/r/CQu90eLhS4cAEAE/review',
                'https://g.page/r/CQu90eLhS4cAEAE/review',
            ],
            'preserves http' => [
                'http://g.page/r/example/review',
                'http://g.page/r/example/review',
            ],
            'trims whitespace' => [
                '  https://g.page/r/example/review  ',
                'https://g.page/r/example/review',
            ],
        ];
    }
}
