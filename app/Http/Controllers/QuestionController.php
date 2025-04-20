<?php

namespace App\Http\Controllers;

use App\Models\Option;
use App\Models\Question;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($assessmentId)
    {
        $questions = Question::with(['options' => function($query) {
            $query->orderBy('id');
        }])
            ->where('assessment_id', $assessmentId)
            ->get();

        return response()->json($questions);
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

        if ($validator->false()){
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

            foreach ($request->options as $index => $optionText){
                Option::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $index === $request->correctAnswer,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Question created successfully',
                'data' => $question->load('options')
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
