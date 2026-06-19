<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Vendor\Models\Vendor;
use App\Support\QuickBooks\QuickBooksVendorContactLinker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuickBooksVendorContactLinkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('contact_vendor');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('vendors');

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company')->nullable();
            $table->string('quickbooks_customer_id')->nullable();
            $table->string('type')->default('1');
            $table->string('status')->default('1');
            $table->unsignedTinyInteger('stage_id')->default(1);
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        Schema::create('contact_vendor', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('portal_access')->default(false);
            $table->primary(['vendor_id', 'contact_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('contact_vendor');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('vendors');

        parent::tearDown();
    }

    public function test_resolve_contact_matches_by_email_first(): void
    {
        $contact = Contact::query()->create([
            'email' => 'ap@acme.test',
            'display_name' => 'Acme AP',
            'quickbooks_customer_id' => '100',
        ]);

        $resolved = QuickBooksVendorContactLinker::resolveContact([
            'display_name' => 'Different Name',
            'contact_email' => 'ap@acme.test',
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame($contact->id, $resolved->id);
    }

    public function test_resolve_contact_matches_imported_contact_by_company_name(): void
    {
        $contact = Contact::query()->create([
            'display_name' => 'Acme Supplies',
            'company' => 'Acme Supplies LLC',
            'quickbooks_customer_id' => '55',
        ]);

        $resolved = QuickBooksVendorContactLinker::resolveContact([
            'display_name' => 'Acme Supplies',
            'company_name' => 'Acme Supplies LLC',
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame($contact->id, $resolved->id);
    }

    public function test_link_sets_primary_contact_and_pivot(): void
    {
        $contact = Contact::query()->create([
            'email' => 'vendor@example.test',
            'display_name' => 'Vendor Contact',
        ]);

        $vendor = Vendor::query()->create(['display_name' => 'Acme']);

        QuickBooksVendorContactLinker::link($vendor, $contact);

        $vendor->refresh();
        $this->assertSame($contact->id, $vendor->primary_contact_id);
        $this->assertTrue(
            DB::table('contact_vendor')
                ->where('vendor_id', $vendor->id)
                ->where('contact_id', $contact->id)
                ->where('is_primary', true)
                ->exists()
        );
    }

    public function test_link_attaches_without_overwriting_existing_primary(): void
    {
        $primary = Contact::query()->create([
            'email' => 'primary@example.test',
            'display_name' => 'Primary',
        ]);
        $secondary = Contact::query()->create([
            'email' => 'secondary@example.test',
            'display_name' => 'Secondary',
        ]);

        $vendor = Vendor::query()->create([
            'display_name' => 'Acme',
            'primary_contact_id' => $primary->id,
        ]);

        DB::table('contact_vendor')->insert([
            'vendor_id' => $vendor->id,
            'contact_id' => $primary->id,
            'is_primary' => true,
            'portal_access' => false,
        ]);

        QuickBooksVendorContactLinker::link($vendor, $secondary);

        $vendor->refresh();
        $this->assertSame($primary->id, $vendor->primary_contact_id);
        $this->assertTrue(
            DB::table('contact_vendor')
                ->where('vendor_id', $vendor->id)
                ->where('contact_id', $secondary->id)
                ->where('is_primary', false)
                ->exists()
        );
    }
}
