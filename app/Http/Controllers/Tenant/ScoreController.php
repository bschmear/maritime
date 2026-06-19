<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Score\Actions\CalculateBehavioralScore;
use App\Domain\Score\Actions\CreateScore;
use App\Domain\Score\Actions\DeleteScore;
use App\Domain\Score\Actions\UpdateScore;
use App\Domain\Score\Models\Score;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScoreController extends Controller
{
    public function __construct(
        private CreateScore $createScore,
        private UpdateScore $updateScore,
        private DeleteScore $deleteScore,
        private CalculateBehavioralScore $calculateBehavioralScore,
    ) {}

    /**
     * Display a listing of scores.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Score::query()
            ->with('scorable');

        if ($request->has('scorable_type') && $request->has('scorable_id')) {
            $query->where('scorable_type', $request->scorable_type)
                ->where('scorable_id', $request->scorable_id);
        }

        return response()->json($query->paginate(25));
    }

    /**
     * Store a newly created score.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scorable_type' => ['required', 'string'],
            'scorable_id' => ['required', 'integer'],
            'score_type' => ['required', 'string', 'in:manual,behavior'],
            'score_value' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'meta' => ['nullable', 'array'],
            'meta.breakdown' => ['nullable', 'array'],
            'meta.reason' => ['nullable', 'string'],
            'meta.stage' => ['nullable', 'string'],
            'meta.model_version' => ['nullable', 'string'],
            'meta.auto_generated' => ['nullable', 'boolean'],
            'meta.confidence' => ['nullable', 'numeric', 'between:0,1'],
            'meta.event_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $validated['user_id'] = current_tenant_user_id();

        $result = ($this->createScore)($validated);

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not create score.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result['record'], Response::HTTP_CREATED);
    }

    /**
     * Display a single score.
     */
    public function show(string $id): JsonResponse
    {
        $score = Score::query()->with('scorable')->findOrFail($id);

        return response()->json($score);
    }

    /**
     * Update an existing score.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $result = ($this->updateScore)((int) $id, $request->all());

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not update score.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result['record']);
    }

    /**
     * Delete a score.
     */
    public function destroy(string $id): JsonResponse
    {
        $result = ($this->deleteScore)((int) $id);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not delete score.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => $result['message'] ?? 'Score deleted successfully.']);
    }

    /**
     * Calculate behavioral score for an entity.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scorable_type' => ['required', 'string'],
            'scorable_id' => ['required', 'integer'],
            'update_current' => ['sometimes', 'boolean'],
        ]);

        $result = ($this->calculateBehavioralScore)(
            $validated['scorable_type'],
            (int) $validated['scorable_id'],
            current_tenant_user_id(),
            (bool) ($validated['update_current'] ?? false),
        );

        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Could not calculate behavioral score.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result['record']);
    }
}
