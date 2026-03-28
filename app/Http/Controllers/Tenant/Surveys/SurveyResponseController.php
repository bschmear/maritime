<?php

namespace App\Http\Controllers\Tenant\Surveys;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SurveyResponseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function analyze(Request $request, $id)
    {
        $response = SurveyResponse::with('survey', 'team')->findOrFail($id);
        $team = $response->team;

        // Check AI plan usage
        if (!$team->canUseAi('survey_analysis')) {
            return response()->json(['error' => 'AI analysis limit reached'], 403);
        }

        $analysis = app(\App\Services\AiAnalysisService::class)
            ->analyzeSurvey($team, $response);

        // Optionally save the result
        $response->update(['ai_analysis' => $analysis]);

        return response()->json($analysis);
    }

}
