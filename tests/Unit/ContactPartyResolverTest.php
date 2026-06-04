<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Support\ContactPartyResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactPartyResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('lead_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('contacts');

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('account_status')->default('active');
            $table->timestamps();
        });

        Schema::create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lead_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('contacts');

        parent::tearDown();
    }

    public function test_ensure_customer_profile_creates_row_for_contact(): void
    {
        $contact = Contact::query()->create(['display_name' => 'Pat Lee']);

        $customer = ContactPartyResolver::ensureCustomerProfile($contact);

        $this->assertSame($contact->id, $customer->contact_id);
        $this->assertDatabaseHas('customer_profiles', ['id' => $customer->id, 'contact_id' => $contact->id]);
    }

    public function test_party_labels_reflect_lead_and_customer_profiles(): void
    {
        $contact = Contact::query()->create(['display_name' => 'Alex Kim']);
        Lead::query()->create(['contact_id' => $contact->id]);
        Customer::query()->create(['contact_id' => $contact->id, 'account_status' => 'active']);

        $this->assertSame(['Contact', 'Lead', 'Customer'], ContactPartyResolver::partyLabelsForContact($contact));
    }
}
