<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\InboundEmail\InboundEmailAddressParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InboundEmailAddressParserTest extends TestCase
{
    #[DataProvider('extractProvider')]
    public function test_extract_parses_addresses(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, InboundEmailAddressParser::extract($input));
    }

    public static function extractProvider(): array
    {
        return [
            'plain address' => ['lead-abc123@inbound.helmful.com', 'lead-abc123@inbound.helmful.com'],
            'display name' => ['Dealer Desk <lead-abc123@inbound.helmful.com>', 'lead-abc123@inbound.helmful.com'],
            'uppercase normalized' => ['Lead-ABC123@Inbound.Helmful.com', 'lead-abc123@inbound.helmful.com'],
            'empty' => ['', null],
            'invalid' => ['not-an-email', null],
        ];
    }

    public function test_extract_first_uses_first_valid_recipient(): void
    {
        $value = 'Sales <sales@dealer.com>, lead-xyz789@inbound.helmful.com';

        $this->assertSame('lead-xyz789@inbound.helmful.com', InboundEmailAddressParser::extractFirst($value));
    }
}
