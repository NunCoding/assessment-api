<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Analytic;
use App\Models\UserAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAssessmentController extends Controller
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
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'assessment_id' => 'required|exists:assessments,id',
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|exists:questions,id',
            'responses.*.selected_option_id' => 'nullable|exists:options,id',
            'completion_time' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            // Calculate score
            $correctCount = 0;
            $totalQuestions = Question::where('assessment_id', $validated['assessment_id'])->count();

            foreach ($validated['responses'] as $response) {
                $isCorrect = Option::find($response['selected_option_id'])->is_correct ?? false;
                if ($isCorrect) $correctCount++;

                Response::create([
                    'user_id' => $validated['user_id'],
                    'question_id' => $response['question_id'],
                    'selected_option_id' => $response['selected_option_id'],
                    'is_correct' => $isCorrect
                ]);
            }

            $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;

            $userAssessment = UserAssessment::create([
                'user_id' => $validated['user_id'],
                'assessment_id' => $validated['assessment_id'],
                'score' => $score,
                'completion_time' => $validated['completion_time']
            ]);

            // Update analytics
            $this->updateAnalytics($score, $validated['completion_time']);

            // Log activity
            ActivityLog::create([
                'user_id' => $validated['user_id'],
                'activity_type' => 'assessment_completed',
                'description' => "Completed assessment with score: {$score}%",
                'metadata' => [
                    'assessment_id' => $validated['assessment_id'],
                    'score' => $score,
                    'completion_time' => $validated['completion_time']
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Assessment submitted successfully',
                'score' => $score,
                'data' => $userAssessment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error submitting assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function submitResult(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'assessment_id' => 'required|exists:assessments,id',
            'score' => 'nullable|integer|min:0',
            'completion_time' => 'nullable|integer|min:0',
        ]);

        // Update or create to avoid duplicate entries
        $userAssessment = UserAssessment::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'assessment_id' => $validated['assessment_id'],
            ],
            [
                'score' => $validated['score'],
                'completion_time' => $validated['completion_time'],
            ]
        );

        if ($userAssessment)
        {
            ActivityLog::create([
                'user_id' => auth()->id() ?? $validated['user_id'], // fallback if no auth
                'activity_type' => 'assessment_submitted',
                'description' => 'User submitted assessment result.',
                'metadata' => [
                    'assessment_id' => $validated['assessment_id'],
                    'score' => $validated['score'],
                    'completion_time' => $validated['completion_time'],
                ]
            ]);
        }

        return response()->json([
            'message' => 'Assessment result submitted successfully.',
        ]);
    }

    public function getAssessmentStats()
    {
        $totalAssessment = DB::table('assessments')->count();
        $totalUser = DB::table('user_assessments')
            ->distinct('user_id')
            ->count('user_id');
        $avgScore = DB::table('user_assessments')->avg('score');

        return response()->json([
            'total_assessment' => $totalAssessment,
            'total_user' => $totalUser,
            'avg_score' => round($avgScore,2)
        ]);
    }

    protected function updateAnalytics($score, $completionTime)
    {
        $analytic = Analytic::first();

        if (!$analytic) {
            $analytic = Analytic::create();
        }

        $totalAssessments = UserAssessment::count();
        $avgScore = UserAssessment::avg('score');
        $avgTime = UserAssessment::avg('completion_time');

        $analytic->update([
            'assessments_taken' => $totalAssessments,
            'avg_score' => $avgScore,
            'avg_completion_time' => $avgTime
        ]);
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
}
