<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Communication\Actions\CreateCommunication;
use App\Domain\Communication\Actions\DeleteCommunication;
use App\Domain\Communication\Actions\UpdateCommunication;
use App\Domain\Communication\Models\Communication;
use App\Domain\Communication\Support\CommunicableTypeResolver;
use App\Enums\Communication\Channel;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\NextActionType;
use App\Enums\Communication\Outcome;
use App\Enums\Communication\Priority;
use App\Enums\Communication\Status;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommunicationController extends Controller
{
    public function __construct(
        private CreateCommunication $createCommunication,
        private UpdateCommunication $updateCommunication,
        private DeleteCommunication $deleteCommunication,
    ) {}

    /**
     * Paginated communications for a Lead, Customer, or Vendor (morph).
     */
    public function recorditems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $entityClass = CommunicableTypeResolver::toClass($validated['type']);
        if ($entityClass === null) {
            return response()->json([
                'message' => 'Invalid communicable type.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            CommunicableTypeResolver::findCommunicable($entityClass, (int) $validated['id']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Record not found.'], Response::HTTP_NOT_FOUND);
        }

        $perPage = 15;
        $communications = Communication::query()
            ->where('communicable_type', $entityClass)
            ->where('communicable_id', $validated['id'])
            ->with(['user:id,display_name'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'communications' => $communications,
            'communication_types' => CommunicationType::options(),
            'next_action_types' => NextActionType::options(),
            'outcome' => Outcome::options(),
            'channel' => Channel::options(),
            'priority' => Priority::options(),
            'status' => Status::options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'communicable_type' => ['required', 'string'],
            'communicable_id' => ['required', 'integer'],
            'communication_type_id' => ['required', 'integer'],
            'direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'needs_follow_up' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
            'status_id' => ['sometimes', 'integer'],
            'channel_id' => ['nullable', 'integer'],
            'priority_id' => ['sometimes', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'outcome_id' => ['nullable', 'integer'],
            'next_action_type_id' => ['nullable', 'integer'],
            'next_action_at' => ['nullable', 'date'],
            'date_contacted' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'calendar_id' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'string', 'max:255'],
        ]);

        $data['user_id'] = $user->id;

        $result = ($this->createCommunication)($data);

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not create communication.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Communication logged.',
            'record' => $result['record'],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'communicable_type' => ['required', 'string'],
            'communicable_id' => ['required', 'integer'],
            'communication_type_id' => ['sometimes', 'integer'],
            'direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'needs_follow_up' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
            'status_id' => ['sometimes', 'integer'],
            'channel_id' => ['nullable', 'integer'],
            'priority_id' => ['sometimes', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'outcome_id' => ['nullable', 'integer'],
            'next_action_type_id' => ['nullable', 'integer'],
            'next_action_at' => ['nullable', 'date'],
            'date_contacted' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'calendar_id' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'string', 'max:255'],
        ]);

        $entityClass = CommunicableTypeResolver::toClass($validated['communicable_type']);
        if ($entityClass === null) {
            return response()->json(['message' => 'Invalid communicable type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $communication = Communication::query()->findOrFail($validated['id']);
        if ($communication->communicable_type !== $entityClass
            || (int) $communication->communicable_id !== (int) $validated['communicable_id']) {
            return response()->json(['message' => 'Communication does not belong to this record.'], Response::HTTP_FORBIDDEN);
        }

        $payload = collect($validated)->except(['id', 'communicable_type', 'communicable_id'])->all();

        $result = ($this->updateCommunication)((int) $validated['id'], $payload);

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not update communication.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Communication updated.',
            'record' => $result['record'],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $result = ($this->deleteCommunication)((int) $validated['id']);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not delete communication.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => $result['message'] ?? 'Communication deleted successfully.',
        ]);
    }
}
