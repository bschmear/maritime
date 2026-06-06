<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Support\ContactDeletionGuard;
use App\Domain\Customer\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactDeletionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('transactions');
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

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('contacts');

        parent::tearDown();
    }

    public function test_allows_deleting_contact_with_no_references(): void
    {
        $contact = Contact::query()->create(['display_name' => 'Pat Lee']);
        $guard = new ContactDeletionGuard;

        $this->assertNull($guard->messageFor($contact));
        $this->assertFalse($guard->isBlocked($contact));
    }

    public function test_blocks_deleting_contact_with_customer_transaction(): void
    {
        $contact = Contact::query()->create(['display_name' => 'Deal Buyer']);
        $customer = Customer::query()->create([
            'contact_id' => $contact->id,
            'account_status' => 'active',
        ]);

        DB::table('transactions')->insert([
            'customer_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guard = new ContactDeletionGuard;

        $this->assertSame(ContactDeletionGuard::MESSAGE, $guard->messageFor($contact));
        $this->assertTrue($guard->isBlocked($contact));
    }
}
