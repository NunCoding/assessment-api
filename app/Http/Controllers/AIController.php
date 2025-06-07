<?php

namespace App\Http\Controllers;

use App\Services\StudentSkillAnalysisService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected $skillService;

    public function __construct(StudentSkillAnalysisService $skillService)
    {
        $this->skillService = $skillService;
    }

    public function show($id, StudentSkillAnalysisService $gemini)
    {
        $weakSkills = $this->skillService->getWeakSkills($id);

        if ($weakSkills->isEmpty()) {
            return response()->json([
                'message' => 'No weak skills found for this student.',
                'weak_skills' => [],
                'recommendations' => null,
            ]);
        }

        // 1. Generate prompt
        $prompt = $this->skillService->generatePrompt($weakSkills);

        // 2. Get raw Gemini response
        $response = $gemini->getRecommendationsFromGemini($prompt);
        $rawText = $response['parts'][0]['text'] ?? '';

        // 3. Parse raw text into structured recommendation
        $structuredRecommendations = $gemini->parseGeminiRecommendations($rawText);

        return response()->json([
            'weak_skills' => $weakSkills,
            'recommendations' => $structuredRecommendations,
        ]);
    }
}
