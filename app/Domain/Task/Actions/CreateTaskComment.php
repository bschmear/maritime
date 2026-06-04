<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Notification\Models\Notification;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Domain\Task\Support\TaskCommentMentionParser;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateTaskComment
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, comment?: TaskComment, message?: string}
     */
    public function __invoke(Task $task, User $author, array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'body' => ['required', 'string', 'max:10000'],
                'mentioned_user_ids' => ['sometimes', 'array'],
                'mentioned_user_ids.*' => ['integer', 'exists:users,id'],
            ])->validate();

            $body = trim((string) $validated['body']);
            if ($body === '') {
                throw ValidationException::withMessages([
                    'body' => ['Comment cannot be empty.'],
                ]);
            }

            $mentionedFromBody = TaskCommentMentionParser::extractUserIds($body);
            $mentionedFromRequest = array_map('intval', $validated['mentioned_user_ids'] ?? []);
            $mentionedIds = array_values(array_unique(array_merge($mentionedFromBody, $mentionedFromRequest)));
            $mentionedIds = array_values(array_filter(
                $mentionedIds,
                fn (int $id) => $id > 0 && $id !== (int) $author->id,
            ));

            if ($mentionedIds !== []) {
                $validIds = User::query()->whereIn('id', $mentionedIds)->pluck('id')->all();
                $mentionedIds = array_values(array_intersect($mentionedIds, array_map('intval', $validIds)));
            }

            $comment = DB::transaction(function () use ($task, $author, $body, $mentionedIds) {
                $comment = TaskComment::query()->create([
                    'task_id' => $task->id,
                    'user_id' => $author->id,
                    'body' => $body,
                ]);

                if ($mentionedIds !== []) {
                    $comment->mentionedUsers()->sync($mentionedIds);
                }

                return $comment;
            });

            $comment->load(['user:id,display_name,first_name,last_name,email', 'mentionedUsers:id,display_name,first_name,last_name,email']);

            $this->notifyMentionedUsers($task, $comment, $author, $mentionedIds);

            return [
                'success' => true,
                'comment' => $comment,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateTaskComment', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<int>  $mentionedIds
     */
    private function notifyMentionedUsers(Task $task, TaskComment $comment, User $author, array $mentionedIds): void
    {
        if ($mentionedIds === []) {
            return;
        }

        $authorName = $author->display_name
            ?: trim(($author->first_name ?? '').' '.($author->last_name ?? ''))
            ?: $author->email
            ?: 'Someone';

        $taskLabel = $task->display_name ?: "Task #{$task->id}";
        $snippet = mb_strlen($comment->body) > 120
            ? mb_substr($comment->body, 0, 117).'…'
            : $comment->body;

        foreach ($mentionedIds as $userId) {
            Notification::query()->create([
                'assigned_to_user_id' => $userId,
                'type' => 'task_comment_mention',
                'title' => 'You were mentioned on a task',
                'message' => "{$authorName} mentioned you on \"{$taskLabel}\": {$snippet}",
                'route' => 'tasks.show',
                'route_params' => ['task' => $task->id],
            ]);
        }
    }
}
