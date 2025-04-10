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
        $assessments = Assessment::with('category')->get();
        return response()->json([
            'data' => $assessments->map(function ($assessment){
              return [
                "id" => $assessment->id,
                'name' => $assessment->title,
                'description' => $assessment->description,
                'category' => $assessment->category->name,
//                'questions' => $assessment->questions,
                'timeEstimate' => $assessment->time_estimate,
              ];
            })
        ]);
    }

    public function list(){
        $listAssessment = Assessment::select('id','title')->get();
        return response()->json($listAssessment);
    }
    // Fetch Single Assessment
    public function show($id)
    {
        $assessment = Assessment::findOrFail($id);
        return response()->json($assessment);
    }


}
