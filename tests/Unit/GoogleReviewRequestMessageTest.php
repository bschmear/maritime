<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Subsidiary\Support\GoogleReviewRequestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleReviewRequestMessageTest extends TestCase
{
    public function test_default_without_subsidiary(): void
    {
        $this->assertSame(
            'We appreciate your business. We\'d appreciate it if you could leave us a Google review.',
            GoogleReviewRequestMessage::default(),
        );
    }

    public function test_default_with_subsidiary(): void
    {
        $this->assertSame(
            'We appreciate your business with Downtown Marina. We\'d appreciate it if you could leave us a Google review.',
            GoogleReviewRequestMessage::default('Downtown Marina'),
        );
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(mixed $input, ?string $subsidiary, string $expected): void
    {
        $this->assertSame($expected, GoogleReviewRequestMessage::normalize($input, $subsidiary));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string, 2: string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'empty uses default' => ['', null, GoogleReviewRequestMessage::default()],
            'whitespace uses default' => ['   ', 'East Bay', GoogleReviewRequestMessage::default('East Bay')],
            'custom message preserved' => [
                'Thanks for choosing us! Please leave a review when you can.',
                'East Bay',
                'Thanks for choosing us! Please leave a review when you can.',
            ],
        ];
    }
}
