<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatPhoneNumberTest extends TestCase
{
    public function test_formats_ten_digit_us_number(): void
    {
        $this->assertSame('(555) 123-4567', format_phone_number('5551234567'));
        $this->assertSame('(555) 123-4567', format_phone_number('555-123-4567'));
        $this->assertSame('(555) 123-4567', format_phone_number('(555) 123-4567'));
    }

    public function test_strips_leading_country_code(): void
    {
        $this->assertSame('(555) 123-4567', format_phone_number('15551234567'));
        $this->assertSame('(555) 123-4567', format_phone_number('+1 (555) 123-4567'));
    }

    public function test_returns_empty_for_blank_values(): void
    {
        $this->assertSame('', format_phone_number(null));
        $this->assertSame('', format_phone_number(''));
        $this->assertSame('', format_phone_number('   '));
    }
}
