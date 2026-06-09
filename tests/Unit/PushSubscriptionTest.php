<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Notification\Models\PushSubscription;
use App\Domain\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
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
        $schema->dropIfExists('push_subscriptions');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
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

    public function test_push_subscription_can_be_created_for_user(): void
    {
        $user = User::query()->create([
            'display_name' => 'Manager One',
            'email' => 'manager@example.com',
        ]);

        $subscription = PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/abc',
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aesgcm',
        ]);

        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame('https://push.example.test/subscription/abc', $subscription->endpoint);
        $this->assertNotNull($subscription->toWebPushSubscription());
    }

    public function test_endpoint_is_unique_across_subscriptions(): void
    {
        $user = User::query()->create([
            'display_name' => 'Manager One',
            'email' => 'manager@example.com',
        ]);

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/abc',
            'public_key' => 'key-1',
            'auth_token' => 'auth-1',
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => 'https://push.example.test/subscription/abc'],
            [
                'user_id' => $user->id,
                'public_key' => 'key-2',
                'auth_token' => 'auth-2',
            ],
        );

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('key-2', PushSubscription::query()->first()->public_key);
    }
}
