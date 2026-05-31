<?php

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Support\ContactDocumentLinker;
use PHPUnit\Framework\TestCase;

class ContactDocumentLinkerTest extends TestCase
{
    public function test_model_class_for_domain_maps_crm_profiles(): void
    {
        $this->assertSame(Contact::class, ContactDocumentLinker::modelClassForDomain('Contact'));
        $this->assertSame(Customer::class, ContactDocumentLinker::modelClassForDomain('Customer'));
        $this->assertSame(Lead::class, ContactDocumentLinker::modelClassForDomain('Lead'));
    }
}
