<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Entity\ContactType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactTypeValidationTest extends TestCase
{
    #[Test]
    public function accepts_numeric_option_id_from_forms(): void
    {
        $this->assertSame(ContactType::Person, ContactType::tryFromStored(1));
        $this->assertSame('person', ContactType::toStoredValue(1));
    }

    #[Test]
    public function accepts_string_backing_value(): void
    {
        $this->assertSame(ContactType::Company, ContactType::tryFromStored('company'));
        $this->assertSame('company', ContactType::toStoredValue('company'));
    }
}
