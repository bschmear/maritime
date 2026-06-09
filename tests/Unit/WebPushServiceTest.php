<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Notification\Models\PushSubscription;
use App\Domain\User\Models\User;
use App\Services\WebPushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebPushServiceTest extends TestCase
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
            'webpush.enabled' => false,
        ]);

        $schema = Schema::connection('tenant');
        $schema->dropIfExists('push_subscriptions');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aesgcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique('endpoint');
        });
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('push_subscriptions');
        $schema->dropIfExists('users');

        parent::tearDown();
    }

    public function test_send_to_user_is_noop_when_web_push_disabled(): void
    {
        $user = User::query()->create([
            'display_name' => 'Manager',
            'email' => 'manager@example.com',
        ]);

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/abc',
            'public_key' => 'key',
            'auth_token' => 'auth',
        ]);

        $result = (new WebPushService)->sendToUser(
            $user->id,
            'Title',
            'Body',
            '/workorders/1',
            'tag:1',
        );

        $this->assertSame(['sent' => 0, 'failed' => 0, 'removed' => 0], $result);
    }
}
