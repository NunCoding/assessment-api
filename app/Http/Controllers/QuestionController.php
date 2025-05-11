<?php

namespace App\Http\Controllers;

use App\Http\Resources\QuestionResource;
use App\Models\ActivityLog;
use App\Models\Analytic;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\Option;


class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = Question::with('assessment');

        // Role-based access
        if ($user->role === 'instructor') {
            $query->whereHas('assessment', fn($q) => $q->where('user_id', $user->id));
        }

        // Filter by assessment ID
        if ($request->filled('id')) {
            $query->whereHas('assessment', fn($q) => $q->where('id', $request->id));
        }

        // Filter by difficulty
        if ($request->filled('difficulty')) {
            $query->whereHas('assessment', fn($q) => $q->where('difficulty', $request->difficulty));
        }

        // Filter by question title
        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        $questions = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        return QuestionResource::collection($questions);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'correctAnswer' => 'required|integer|min:0',
            'explanation' => 'nullable|string',
            'assessment_id' => 'required|exists:assessments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create question
            $question = Question::create([
                'assessment_id' => $request->assessment_id,
                'title' => $request->title,
                'explanation' => $request->explanation,
            ]);

            // Prepare options data
            $options = [];
            foreach ($request->options as $index => $optionText) {
                $options[] = [
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $index == $request->correctAnswer,
                ];
            }

            // Bulk insert options
            Option::insert($options);

            // Update analytics
            Analytic::firstOrCreate([])->increment('questions_created');

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'question_created',
                'description' => 'Added question to assessment',
                'metadata' => [
                    'assessment_id' => $request->assessment_id,
                    'question_id' => $question->id
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Question created successfully',
                'data' => [
                    'question_id' => $question->id,
                    'correct_answer_index' => $request->correctAnswer
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating question',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $assessmentId)
    {
        $questions = Question::with(['options' => function($query) {
            $query->orderBy('id');
        }])
            ->where('assessment_id', $assessmentId)
            ->get();

        return response()->json($questions);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string',
            'options' => 'sometimes|required|array|min:2',
            'options.*' => 'required|string',
            'correctAnswer' => 'required_with:options|integer|min:0',
            'explanation' => 'nullable|string',
            'assessment_id' => 'sometimes|required|exists:assessments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find the question
            $question = Question::findOrFail($id);

            // Update question fields - changed 'question' to 'title' to match your table
            $question->update([
                'title' => $request->input('title', $question->title),
                'explanation' => $request->input('explanation', $question->explanation),
                'assessment_id' => $request->input('assessment_id', $question->assessment_id),
            ]);

            // Handle options update if provided
            if ($request->has('options')) {

                // First delete all existing options for this question
                Option::where('question_id', $question->id)->delete();

                // Then create new options
                $newCorrectIndex = (int) $request->correctAnswer;

                foreach ($request->options as $index => $optionText) {
                    $isCorrect = (int) $index === $newCorrectIndex;

                    \Log::info("Creating option: {$optionText} | Index: {$index} | Correct Index: {$newCorrectIndex} | is_correct: " . ($isCorrect ? 'true' : 'false'));

                    Option::create([
                        'question_id' => $question->id,
                        'option_text' => $optionText,
                        'is_correct' => $isCorrect
                    ]);
                }


            }

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'question_updated',
                'description' => 'Updated question in assessment',
                'metadata' => [
                    'assessment_id' => $question->assessment_id,
                    'question_id' => $question->id
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Question updated successfully',
                'data' => [
                    'question' => $question,
                    'options' => $question->options,
                    'correct_answer' => $request->correctAnswer
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating question',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            // Find the question
            $question = Question::findOrFail($id);

            // Delete associated options first to maintain integrity
            $question->options()->delete();

            // Now delete the question
            $question->delete();

            // Log the deletion activity
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'question_deleted',
                'description' => 'Deleted question from assessment',
                'metadata' => [
                    'assessment_id' => $question->assessment_id,
                    'question_id' => $question->id
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Question deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error deleting question',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
