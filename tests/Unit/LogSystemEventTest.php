<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Asset\Models\Asset;
use App\Domain\SystemLog\Models\SystemLog;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Domain\User\Models\User;
use App\Enums\System\SystemLogAction;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogSystemEventTest extends TestCase
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
        $schema->dropIfExists('assets');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Staff');
            $table->string('first_name')->default('S');
            $table->string('last_name')->default('T');
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->timestamps();
        });

        $schema->create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type')->default(1);
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        $schema->create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->unsignedTinyInteger('action');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function test_record_creates_system_log_for_asset(): void
    {
        $user = User::query()->create([
            'display_name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $profile = $this->mock(CurrentTenantProfile::class);
        $profile->shouldReceive('profile')->andReturn($user);

        $asset = Asset::query()->create([
            'type' => 1,
            'display_name' => 'Test Boat',
        ]);

        LogSystemEvent::record($asset, SystemLogAction::Created);

        $log = SystemLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($asset->getMorphClass(), $log->loggable_type);
        $this->assertSame($asset->id, $log->loggable_id);
        $this->assertSame(SystemLogAction::Created->value, $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->created_at);
    }

    public function test_asset_system_logs_relationship_returns_ordered_entries(): void
    {
        $asset = Asset::query()->create([
            'type' => 1,
            'display_name' => 'Test Boat',
        ]);

        SystemLog::query()->create([
            'loggable_type' => $asset->getMorphClass(),
            'loggable_id' => $asset->id,
            'action' => SystemLogAction::Created->value,
            'user_id' => null,
            'created_at' => now()->subHour(),
        ]);

        SystemLog::query()->create([
            'loggable_type' => $asset->getMorphClass(),
            'loggable_id' => $asset->id,
            'action' => SystemLogAction::Updated->value,
            'user_id' => null,
            'created_at' => now(),
        ]);

        $logs = $asset->systemLogs()->get();

        $this->assertCount(2, $logs);
        $this->assertSame(SystemLogAction::Updated->value, $logs->first()->action);
        $this->assertSame(SystemLogAction::Created->value, $logs->last()->action);
    }
}
