<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\User\Models\User;
use App\Domain\UserFavorite\Models\UserFavorite;
use App\Http\Controllers\Tenant\UserFavoriteController;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserFavoriteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->string('route');
            $table->json('route_params')->nullable();
            $table->string('route_params_hash', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'route', 'route_params_hash']);
        });

        Route::get('/test-dashboard', fn () => 'ok')->name('dashboard');
    }

    public function test_store_creates_favorite_for_current_tenant_user(): void
    {
        $user = $this->seedUser('a@example.com');
        $this->actingAsTenantUser($user);

        $controller = new UserFavoriteController;
        $response = $controller->store(Request::create('/favorites', 'POST', [
            'label' => 'My Dashboard',
            'route' => 'dashboard',
            'route_params' => null,
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $user->id,
            'label' => 'My Dashboard',
            'route' => 'dashboard',
        ], 'tenant');
    }

    public function test_store_rejects_duplicate_route_for_same_user(): void
    {
        $user = $this->seedUser('dup@example.com');
        $this->actingAsTenantUser($user);

        UserFavorite::query()->create([
            'user_id' => $user->id,
            'label' => 'Existing',
            'route' => 'dashboard',
            'route_params' => null,
        ]);

        $controller = new UserFavoriteController;

        $this->expectException(ValidationException::class);
        $controller->store(Request::create('/favorites', 'POST', [
            'label' => 'Duplicate',
            'route' => 'dashboard',
        ]));
    }

    public function test_destroy_returns_403_for_another_users_favorite(): void
    {
        $owner = $this->seedUser('owner@example.com');
        $other = $this->seedUser('other@example.com');

        $favorite = UserFavorite::query()->create([
            'user_id' => $owner->id,
            'label' => 'Owner favorite',
            'route' => 'dashboard',
            'route_params' => null,
        ]);

        $this->actingAsTenantUser($other);

        $controller = new UserFavoriteController;

        $this->expectException(HttpException::class);
        $controller->destroy(Request::create('/favorites/'.$favorite->id, 'DELETE'), $favorite->id);
    }

    protected function seedUser(string $email): User
    {
        return User::query()->create([
            'display_name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
        ]);
    }

    protected function actingAsTenantUser(User $tenantUser): void
    {
        $mock = $this->mock(CurrentTenantProfile::class);
        $mock->shouldReceive('profile')->andReturn($tenantUser);
        $this->app->instance(CurrentTenantProfile::class, $mock);
    }
}
