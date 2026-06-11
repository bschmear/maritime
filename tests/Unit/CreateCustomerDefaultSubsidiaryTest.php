<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Customer\Actions\CreateCustomer;
use App\Domain\Customer\Models\Customer;
use App\Domain\Subsidiary\Models\Subsidiary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateCustomerDefaultSubsidiaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $schema = Schema::connection('tenant');

        $schema->dropIfExists('customer_profiles');
        $schema->dropIfExists('contact_addresses');
        $schema->dropIfExists('contacts');
        $schema->dropIfExists('subsidiaries');
        $schema->dropIfExists('users');
        $schema->dropIfExists('integrations');
        $schema->dropIfExists('system_logs');

        $schema->create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('integration_type')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $schema->create('subsidiaries', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        $schema->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('company')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        $schema->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        $schema->create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });

        $schema->create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->unsignedTinyInteger('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_create_customer_without_subsidiary_id_uses_default_subsidiary(): void
    {
        $subsidiary = Subsidiary::query()->create([
            'display_name' => 'Main Marina',
            'inactive' => false,
        ]);

        $result = app(CreateCustomer::class)([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
        ]);

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'unknown error');
        $this->assertNotNull($result['record']);
        $this->assertSame($subsidiary->id, $result['record']->subsidiary_id);
        $this->assertSame(1, Customer::query()->count());
    }
}
