<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Task\Actions\CreateTaskComment;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Domain\Task\Support\TaskCommentMentionParser;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TaskCommentController extends Controller
{
    public function __construct(
        private CreateTaskComment $createTaskComment,
    ) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        abort_unless(tenant_has_permission('task.view'), 403);

        $perPage = min(50, max(5, (int) $request->integer('per_page', 20)));

        $comments = TaskComment::query()
            ->where('task_id', $task->id)
            ->with([
                'user:id,display_name,first_name,last_name,email',
                'mentionedUsers:id,display_name,first_name,last_name,email',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'comments' => $comments->through(fn (TaskComment $comment) => $this->commentPayload($comment)),
        ]);
    }

    public function mentionableUsers(Request $request): JsonResponse
    {
        abort_unless(tenant_has_permission('task.view'), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 15);

        $query = User::query()
            ->select(['id', 'display_name', 'first_name', 'last_name', 'email'])
            ->orderBy('display_name')
            ->orderBy('id');

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(display_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        $users = $query->limit($limit)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'display_name' => $this->userDisplayName($user),
        ]);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        abort_unless(tenant_has_permission('task.edit'), 403);

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = ($this->createTaskComment)($task, $user, $request->all());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! ($result['success'] ?? false) || ! isset($result['comment'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Failed to create comment.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'comment' => $this->commentPayload($result['comment']),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function commentPayload(TaskComment $comment): array
    {
        $mentionLabels = [];
        foreach ($comment->mentionedUsers as $mentioned) {
            $mentionLabels[$mentioned->id] = $this->userDisplayName($mentioned);
        }

        return [
            'id' => $comment->id,
            'task_id' => $comment->task_id,
            'body' => $comment->body,
            'body_display' => TaskCommentMentionParser::displayBody($comment->body),
            'body_html' => TaskCommentMentionParser::bodyToHtml($comment->body, $mentionLabels),
            'created_at' => $comment->created_at?->toIso8601String(),
            'user' => [
                'id' => $comment->user?->id,
                'display_name' => $this->userDisplayName($comment->user),
            ],
            'mentions' => $comment->mentionedUsers->map(fn (User $u) => [
                'id' => $u->id,
                'display_name' => $this->userDisplayName($u),
            ])->values()->all(),
        ];
    }

    private function userDisplayName(?User $user): string
    {
        if ($user === null) {
            return 'Unknown';
        }

        if ($user->display_name) {
            return (string) $user->display_name;
        }

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $name !== '' ? $name : ((string) ($user->email ?? 'User'));
    }
}
