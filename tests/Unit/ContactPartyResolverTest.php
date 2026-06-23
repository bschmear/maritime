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
        Schema::dropIfExists('subsidiaries');
        Schema::dropIfExists('contacts');

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('subsidiaries', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('subsidiary_id');
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
        Schema::dropIfExists('subsidiaries');
        Schema::dropIfExists('contacts');

        parent::tearDown();
    }

    public function test_ensure_customer_profile_creates_row_for_contact(): void
    {
        $subsidiaryId = \Illuminate\Support\Facades\DB::table('subsidiaries')->insertGetId([
            'display_name' => 'Main',
            'inactive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contact = Contact::query()->create(['display_name' => 'Pat Lee']);

        $customer = ContactPartyResolver::ensureCustomerProfile($contact, $subsidiaryId);

        $this->assertSame($contact->id, $customer->contact_id);
        $this->assertSame($subsidiaryId, $customer->subsidiary_id);
        $this->assertDatabaseHas('customer_profiles', [
            'id' => $customer->id,
            'contact_id' => $contact->id,
            'subsidiary_id' => $subsidiaryId,
        ]);
    }

    public function test_party_labels_reflect_lead_and_customer_profiles(): void
    {
        \Illuminate\Support\Facades\DB::table('subsidiaries')->insert([
            'id' => 1,
            'display_name' => 'Main',
            'inactive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contact = Contact::query()->create(['display_name' => 'Alex Kim']);
        Lead::query()->create(['contact_id' => $contact->id]);
        Customer::query()->create([
            'contact_id' => $contact->id,
            'subsidiary_id' => 1,
            'account_status' => 'active',
        ]);

        $this->assertSame(['Contact', 'Lead', 'Customer'], ContactPartyResolver::partyLabelsForContact($contact));
    }
}
