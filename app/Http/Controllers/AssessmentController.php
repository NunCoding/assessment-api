<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function topPopularAssessments()
    {
        $assessments = Assessment::with('category')
            ->withCount('userAssessments')
            ->orderByDesc('user_assessments_count')
            ->take(3)
            ->get()
            ->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'category' => $assessment->category->name ?? null,
                    'image' => $assessment->image,
                    'users' => $this->formatNumber($assessment->user_assessments_count),
                    'difficulty' => $assessment->difficulty,
                    'timeEstimate' => $assessment->time_estimate,
                ];
            });

        return response()->json($assessments);
    }

    public function getCategory(){
        $assessments = DB::table('assessments')
            ->select(
                'assessments.id',
                'assessments.title',
                'assessments.description',
                'assessments.image',
                'assessments.rating',
                'assessments.difficulty',
                'assessments.time_estimate',
                'assessments.tags',
                DB::raw('(SELECT COUNT(*) FROM user_assessments WHERE user_assessments.assessment_id = assessments.id) as user_count')
            )
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($assessment) {
                // Helper function to format user count like "12.5k"
                $formatUserCount = function ($number) {
                    if ($number >= 1000) {
                        return number_format($number / 1000, 1) . 'k';
                    }
                    return (string) $number;
                };

                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'image' => $assessment->image ?? '',
                    'rating' => round($assessment->rating ?? 0, 1),
                    'difficulty' => $assessment->difficulty ?? 'Unknown',
                    'duration' => (int) $assessment->time_estimate,
                    'tags' => $assessment->tags ? array_map('trim', explode(',', $assessment->tags)) : [],
                    'users' => $formatUserCount($assessment->user_count),
                ];
            });

        return response()->json($assessments);
    }

    private function formatNumber($number)
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'k';
        }
        return (string) $number;
    }


}
