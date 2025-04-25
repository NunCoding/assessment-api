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
        $perPage = $request->input('per_page',10);
        $page = $request->input('page',1);

        $query = Question::with('assessment.category');

        if ($request->category){
            $query->whereHas('assessment.category', fn($q) => $q->where('name', $request->category));
        }

        if ($request->difficulty) {
            $query->whereHas('assessment', fn($q) => $q->where('difficulty', $request->difficulty));
        }

        if ($request->assessment) {
            $query->whereHas('assessment', fn($q) => $q->where('title', 'like', '%'.$request->assessment.'%'));
        }

        $questions = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        return QuestionResource::collection($questions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'title' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'correctAnswer' => 'required|integer|min:0',
            'explanation' => 'nullable|string',
            'assessment_id' => 'required|exists:assessments,id'
        ]);

        if ($validator->fails()){
            return response()->json([
               'message' => 'Validation error',
               'error' => $validator->errors()
            ],422);
        }

        try {
            DB::beginTransaction();

            $question = Question::create([
                'assessment_id' => $request->assessment_id,
                'title' => $request->title,
                'explanation' => $request->explanation,
            ]);

            // Create options
            $options = [];
            foreach ($request->options as $index => $optionText){
                Option::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $index === $request->correctAnswer,
                ]);
            }

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
            ],201);

        }catch (\Exception $e){
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
        $validator = Validator::make($request->all(),[
            'title' => 'sometimes|required|string',
            'options' => 'sometimes|required|array|min:2',
            'options.*' => 'required_with:options|string',
            'correctAnswer' => 'sometimes|required|integer|min:0',
            'explanation' => 'nullable|string',
            'assessment_id' => 'sometimes|required|exists:assessments,id'
        ]);

        if ($validator->fails()){
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            // Find the question
            $question = Question::findOrFail($id);

            // Update question fields
            $question->update([
                'title' => $request->input('title', $question->title),
                'explanation' => $request->input('explanation', $question->explanation),
                'assessment_id' => $request->input('assessment_id', $question->assessment_id),
            ]);

            // Handle options update if provided
            if ($request->has('options')) {
                $newCorrectIndex = $request->correctAnswer;
                $existingOptionIds = [];

                foreach ($request->options as $index => $text) {
                    $option = Option::create([
                        'question_id' => $question->id,
                        'option_text' => $text,
                        'is_correct' => $index === $newCorrectIndex
                    ]);
                    $existingOptionIds[] = $option->id;
                }


                // Delete options that weren't included in the update
                Option::where('question_id', $question->id)
                    ->whereNotIn('id', $existingOptionIds)
                    ->delete();
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
                'data' => new QuestionResource($question->fresh(['options']))
            ], 200);

        }catch (\Exception $e){
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
