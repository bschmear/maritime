<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Domain\Score\Models\Score;
use App\Domain\Lead\Models\Lead;
use App\Domain\Contact\Models\Contact;
use App\Domain\Score\Actions\RecalculateScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScoreController extends Controller
{
    /**
     * Display a listing of scores.
     */
    public function index(Request $request)
    {
        $query = Score::query()
            // ->where('team_id', Auth::user()->team_id)
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
    public function store(Request $request)
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        // $subscription = $team->cachedActiveSubscription();

        // Feature gating
        // if ($subscription->level === 1) {
        //     return response()->json(['message' => 'Lead scoring is not available on your plan.'], 403);
        // }

        $validated = $request->validate([
            'scorable_type' => 'required|string',
            'scorable_id' => 'required|integer',
            'score_type' => 'required|string|in:manual,behavior',
            'score_value' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'meta' => 'nullable|array',
            'meta.breakdown' => 'nullable|array',
            'meta.reason' => 'nullable|string',
            'meta.stage' => 'nullable|string',
            'meta.model_version' => 'nullable|string',
            'meta.auto_generated' => 'nullable|boolean',
            'meta.confidence' => 'nullable|numeric|between:0,1',
            'meta.event_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:250',
        ]);

        $entityClass = $validated['scorable_type'];
        $entity = $entityClass::findOrFail($validated['scorable_id']);

        // Tier 2: allow only one current score per type per entity
        // if ($subscription->level === 2) {
        //     if ($validated['score_type'] !== 'manual') {
        //         return response()->json([
        //             'message' => 'Only manual scoring is available on your plan.'
        //         ], 403);
        //     }

        //     // Only allow ONE score total per entity (any type)
        //     $existing = Score::where('scorable_type', $entityClass)
        //         ->where('scorable_id', $entity->id)
        //         ->count();

        //     if ($existing >= 1) {
        //         return response()->json([
        //             'message' => 'Only one score allowed per record on your plan. Delete the existing score first or upgrade.'
        //         ], 403);
        //     }
        // }

        // Auto-calculate score_value from meta.breakdown if not provided
        $scoreValue = $validated['score_value'] ?? null;
        $meta = $validated['meta'] ?? [];

        if ($scoreValue === null && isset($meta['breakdown']) && is_array($meta['breakdown'])) {
            $scoreValue = $this->calculateScoreFromBreakdown($meta['breakdown']);
        }

        // Cap score at 100
        if ($scoreValue !== null) {
            $scoreValue = min($scoreValue, 100);
        }

        // Ensure default meta structure
        $meta = array_merge([
            'breakdown' => [],
            'reason' => '',
            'stage' => '',
            'model_version' => '1.0',
            'auto_generated' => false,
            'confidence' => null,
            'event_id' => null,
        ], $meta);

        // Mark previous scores of same type as not current before creating new one
        Score::where('scorable_type', $entityClass)
            ->where('scorable_id', $entity->id)
            ->update(['is_current' => false]);

        // Level 3: Enforce 5 historical limit (includes current)
        // if ($subscription->level === 3) {
            $totalScores = Score::where('scorable_type', $entityClass)
                ->where('scorable_id', $entity->id)
                ->where('score_type', $validated['score_type'])
                ->count();

            // If we already have 5, delete the oldest before creating the new one
            if ($totalScores >= 5) {
                Score::where('scorable_type', $entityClass)
                    ->where('scorable_id', $entity->id)
                    ->where('score_type', $validated['score_type'])
                    ->where('is_current', false)
                    ->orderBy('created_at', 'asc')
                    ->first()
                    ?->delete();
            }
        // }

        $score = Score::create([
            'user_id' => $user->id,
            'assigned_id' => $entity->assigned_id ?? null,
            'scorable_type' => $entityClass,
            'scorable_id' => $entity->id,
            'score_type' => $validated['score_type'],
            'score_value' => $scoreValue ?? 0,
            'weight' => $validated['weight'] ?? null,
            'meta' => $meta,
            'notes' => $validated['notes'] ?? null,
            'is_current' => true,
        ]);

        // Update cached latest score on the entity
        $this->updateLatestScoreCache($entity, $score);

        return response()->json($score->load('scorable'), 201);
    }

    /**
     * Calculate score from breakdown array.
     * Breakdown format: [['component' => 'name', 'value' => 10], ...]
     */
    protected function calculateScoreFromBreakdown(array $breakdown): float
    {
        $total = 0;

        foreach ($breakdown as $component) {
            if (isset($component['value']) && is_numeric($component['value'])) {
                $total += (float) $component['value'];
            }
        }

        // Cap at 100 and round to 2 decimal places
        return round(min($total, 100), 2);
    }

    /**
     * Display a single score.
     */
    public function show(string $id)
    {
        $score = Score::with('scorable')->findOrFail($id);

        // if ($score->team_id !== Auth::user()->team_id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        return response()->json($score);
    }

    /**
     * Update an existing score.
     */
    public function update(Request $request, string $id)
    {
        $score = Score::findOrFail($id);

        $user = auth()->user();
        $team = $user->currentTeam;

        if ($score->team_id !== $team->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'score_value' => 'sometimes|numeric',
            'weight' => 'nullable|numeric',
            'meta' => 'nullable|array',
            'notes' => 'nullable|string|max:250',
        ]);

        $score->update($validated);

        // Update cache if this is the current score
        if ($score->is_current) {
            $this->updateLatestScoreCache($score->scorable, $score);
        }

        return response()->json($score);
    }

    /**
     * Delete a score.
     */
    public function destroy(string $id)
    {
        $score = Score::findOrFail($id);
        $user = auth()->user();
        // $team = $user->currentTeam;

        // if ($score->team_id !== $team->id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $entity = $score->scorable;
        $wasCurrentScore = $score->is_current;

        $score->delete();

        // If we deleted the current score, update cache to next most recent score
        if ($wasCurrentScore && $entity) {
            $nextScore = Score::where('scorable_type', get_class($entity))
                ->where('scorable_id', $entity->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($nextScore) {
                // Mark the next most recent as current
                $nextScore->update(['is_current' => true]);
                $this->updateLatestScoreCache($entity, $nextScore);
            } else {
                // No scores left, clear the cache
                $this->updateLatestScoreCache($entity, null);
            }
        }

        return response()->json(['message' => 'Score deleted successfully.']);
    }

    /**
     * Calculate behavioral score for an entity.
     */
    public function calculate(Request $request)
    {
        $user = auth()->user();
        // $team = $user->currentTeam;
        // $subscription = $team->cachedActiveSubscription();

        // Feature gating
        // if ($subscription->level === 1) {
        //     return response()->json(['message' => 'Lead scoring is not available on your plan.'], 403);
        // }

        // if ($subscription->level <= 2) {
        //     return response()->json([
        //         'message' => 'Behavioral scoring is not available on your plan.'
        //     ], 403);
        // }

        $validated = $request->validate([
            'scorable_type' => 'required|string',
            'scorable_id' => 'required|integer',
            'update_current' => 'sometimes|boolean', // Whether to update current score or create new
        ]);

        $entityClass = $validated['scorable_type'];

        // Validate entity type
        if (!in_array($entityClass, [Lead::class, Contact::class])) {
            return response()->json(['message' => 'Invalid entity type'], 422);
        }

        $entity = $entityClass::findOrFail($validated['scorable_id']);

        // Check team access
        if ($entity->team_id !== $team->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updateCurrent = $validated['update_current'] ?? false;

        // Trigger score calculation
        $recalculator = app(RecalculateScore::class);
        $score = $recalculator->execute($entity, [
            'threshold' => 0, // Force recalculation
            'update_current' => $updateCurrent,
            'subscription_level' => $subscription->level,
        ]);

        if (!$score) {
            return response()->json(['message' => 'Failed to calculate score'], 500);
        }

        // Update cache for the calculated score
        $this->updateLatestScoreCache($entity, $score);

        return response()->json($score->load('scorable', 'user'), 201);
    }

    /**
     * Update the cached latest_score on the entity.
     */
    protected function updateLatestScoreCache($entity, $score = null)
    {
        if (!$entity) return;

        if ($score) {
            $entity->update([
                'latest_score_id' => $score->id,
                'latest_score' => $score->score_value,
            ]);
        } else {
            $entity->update([
                'latest_score_id' => null,
                'latest_score' => null,
            ]);
        }
    }
}
