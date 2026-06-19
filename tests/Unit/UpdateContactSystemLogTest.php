<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Actions\UpdateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\SystemLog\Models\SystemLog;
use App\Enums\Entity\ContactStatus;
use App\Enums\System\SystemLogAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateContactSystemLogTest extends TestCase
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

        $schema->dropIfExists('system_logs');
        $schema->dropIfExists('contact_addresses');
        $schema->dropIfExists('contacts');

        $schema->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('type')->default('1');
            $table->string('status')->default('1');
            $table->unsignedTinyInteger('stage_id')->default(1);
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        $schema->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
        });

        $schema->create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->unsignedTinyInteger('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_label', 100)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_system_log_targets_updated_contact_when_status_is_active(): void
    {
        Contact::query()->create([
            'first_name' => 'Wrong',
            'last_name' => 'Contact',
            'type' => '1',
            'status' => '1',
            'stage_id' => 1,
        ]);

        Contact::query()->insert([
            'id' => 17,
            'first_name' => 'Target',
            'last_name' => 'Contact',
            'type' => '1',
            'status' => '1',
            'stage_id' => 1,
            'inactive' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(UpdateContact::class);
        $result = $action(17, [
            'first_name' => 'Updated',
            'status' => ContactStatus::Active->value,
        ]);

        $this->assertTrue($result['success']);

        $target = Contact::query()->findOrFail(17);
        $this->assertSame('Updated', $target->first_name);

        $other = Contact::query()->findOrFail(1);
        $this->assertSame('Wrong', $other->first_name);

        $log = SystemLog::query()->sole();
        $this->assertSame(Contact::class, $log->loggable_type);
        $this->assertSame(17, $log->loggable_id);
        $this->assertSame(SystemLogAction::Updated->value, $log->action);
    }
}
