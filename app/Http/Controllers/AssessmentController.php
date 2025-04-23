<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function store(StoreAssessmentRequest $request){
        $validated = $request->validated();

        if($request->hasFile('image')){
            $validated['image'] = $request->file('image')->store('uploads','public');
        }

        if (isset($validated['tags']) && is_string($validated['tags'])) {
            $validated['tags'] = json_decode($validated['tags'], true);
        }
        Assessment::create($validated);
        return response()->noContent();
    }

    // Fetch All Assessments
    public function index()
    {
        $assessments = Assessment::with(['category', 'questions'])->latest()->get();

        return response()->json([
            'data' => $assessments->map(function ($assessment) {
                return [
                    "id" => $assessment->id,
                    'name' => $assessment->title,
                    'description' => $assessment->description,
                    'image' => $assessment->image,
                    'category' => $assessment->category->name ?? null,
                    'questions' => $assessment->questions->count(),
                    'timeEstimate' => $assessment->time_estimate,
                ];
            })
        ]);
    }

    public function show($id){
        $assessment = Assessment::with(['questions' => function($query) {
            $query->with(['options' => function($query) {
                $query->orderBy('id');
            }]);
        }])->findOrFail($id);

        return response()->json([
            'id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'category' => $assessment->category->name,
            'questions' => $assessment->questions->map(function($question) {
                return [
                    'question' => $question->title,
                    'options' => $question->options->pluck('option_text'),
                    'correctAnswer' => $question->options->search(function($option) {
                        return $option->is_correct;
                    }),
                    'explanation' => $question->explanation
                ];
            })
        ]);
    }


    public function list(){
        $listAssessment = Assessment::select('id','title')->get();
        return response()->json($listAssessment);
    }


}
