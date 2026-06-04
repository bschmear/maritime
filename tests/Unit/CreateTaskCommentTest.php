<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Notification\Models\Notification;
use App\Domain\Task\Actions\CreateTaskComment;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Domain\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateTaskCommentTest extends TestCase
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

        $schema->dropIfExists('task_comment_mentions');
        $schema->dropIfExists('task_comments');
        $schema->dropIfExists('notifications');
        $schema->dropIfExists('tasks');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->unsignedTinyInteger('priority_id')->default(2);
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });

        $schema->create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        $schema->create('task_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['task_comment_id', 'user_id']);
            $table->timestamps();
        });

        $schema->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_to_user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('route');
            $table->json('route_params')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('task_comment_mentions');
        $schema->dropIfExists('task_comments');
        $schema->dropIfExists('notifications');
        $schema->dropIfExists('tasks');
        $schema->dropIfExists('users');

        parent::tearDown();
    }

    public function test_creates_comment_mentions_and_notification(): void
    {
        $author = User::query()->create(['display_name' => 'Author']);
        $mentioned = User::query()->create(['display_name' => 'Mentioned']);
        $task = Task::query()->create([
            'display_name' => 'Follow up',
            'status_id' => 1,
            'priority_id' => 2,
        ]);

        $action = new CreateTaskComment;
        $result = $action($task, $author, [
            'body' => 'Please check @[Mentioned](user:'.$mentioned->id.')',
        ]);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(TaskComment::class, $result['comment']);
        $this->assertDatabaseHas('task_comment_mentions', [
            'user_id' => $mentioned->id,
        ]);

        $notification = Notification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame($mentioned->id, $notification->assigned_to_user_id);
        $this->assertSame('task_comment_mention', $notification->type);
        $this->assertSame('tasks.show', $notification->route);
        $this->assertSame(['task' => $task->id], $notification->route_params);
    }

    public function test_does_not_notify_author_when_self_mentioned(): void
    {
        $author = User::query()->create(['display_name' => 'Author']);
        $task = Task::query()->create([
            'display_name' => 'Solo',
            'status_id' => 1,
            'priority_id' => 2,
        ]);

        $action = new CreateTaskComment;
        $action($task, $author, [
            'body' => 'Note @[Author](user:'.$author->id.')',
        ]);

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, \DB::connection('tenant')->table('task_comment_mentions')->count());
    }
}
