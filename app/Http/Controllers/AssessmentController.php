<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentController extends Controller
{

    public function store(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'categories_id' => 'required|exists:categories,id',
            'tags' => 'required|array',
            'time_estimate' => 'required|integer',
            'difficulty' => 'required|string',
            'image' => 'required|string',
        ]);

        // Create assessment
        $assessment = Assessment::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'categories_id' => $validated['categories_id'],
            'tags' => $validated['tags'] ?? [],
            'time_estimate' => $validated['time_estimate'] ?? 0,
            'difficulty' => $validated['difficulty'] ?? null,
            'expires_at' => now()->addDay(1),
            'user_id' => auth()->id(),
            'slug' => $user->role === 'instructor' ? Str::uuid() : null,
            'image' => $validated['image'],
        ]);

        return response()->json([
            'message' => 'Assessment created successfully.',
            'share_link' => config('app.frontend_url') . '/take-assessment/' . $assessment->slug,
        ]);
    }


    // Fetch All Assessments
    public function index()
    {
        $user = auth()->user();

        $query = Assessment::with(['category', 'questions', 'userAssessments'])
            ->latest();

        // Only show own assessments if the user is an instructor
        if ($user->role === 'instructor') {
            $query->where('user_id', $user->id);
        }

        $assessments = $query->get();

        return response()->json([
            'data' => $assessments->map(function ($assessment) use ($user) {
                $data = [
                    "id" => $assessment->id,
                    'name' => $assessment->title,
                    'description' => $assessment->description,
                    'image' => $assessment->image,
                    'category' => $assessment->category->name ?? null,
                    'categories_id' => $assessment->category->id ?? null,
                    'questions' => $assessment->questions->count(),
                    'difficulty' => $assessment->difficulty,
                    'tags' => $assessment->tags ?? [],
                    'total_taken' => $assessment->userAssessments->count(),
                    'time_estimate' => $assessment->time_estimate,
                ];

                if (in_array($user->role, ['instructor'])) {
                    $data['share_link'] = config('app.frontend_url') . '/take-assessment/' . $assessment->slug;
                }

                return $data;
            }),
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

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'categories_id' => 'required|exists:categories,id',
            'tags' => 'required|array',
            'time_estimate' => 'required|integer',
            'difficulty' => 'required|string',
            'image' => 'required|string',
        ]);

        $assessment = Assessment::where('id', $id)->firstOrFail();

        $assessment->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'categories_id' => $validated['categories_id'],
            'tags' => $validated['tags'],
            'time_estimate' => $validated['time_estimate'],
            'difficulty' => $validated['difficulty'],
            'image' => $validated['image'],
        ]);
        return response()->noContent();
    }


    public function list()
    {
        $user = auth()->user();

        $query = Assessment::query();

        if ($user->role === 'instructor') {
            // Only latest for instructors
            $assessment = $query->where('user_id', $user->id)
                ->latest()
                ->first();

            if (!$assessment) {
                return response()->json(['message' => 'No assessment found'], 404);
            }

            return response()->json([
                [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'difficulty' => $assessment->difficulty,
                ]
            ]);
        }

        // For admin
        $assessments = $query->latest()->get(['id', 'title', 'difficulty']);

        return response()->json($assessments->toArray());
    }

    public function assessmentBySlug($slug){
        $assessment = Assessment::with([
            'category',
            'questions.options' => function($query) {
                $query->orderBy('id');
            }
        ])->where('slug',$slug)->firstOrFail();

        // expire after 1 day
        if ($assessment->expires_at && now()->greaterThan($assessment->expires_at)){
            return response()->json([
                'message' => 'This assessment link has expired.'
            ],403);
        }

        return response()->json([
            'id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'category' => $assessment->category->name ?? null,
            'questions' => $assessment->questions->map(function ($question) {
                return [
                    'question' => $question->title,
                    'options' => $question->options->pluck('option_text'),
                    'correctAnswer' => $question->options->search(function ($option) {
                        return $option->is_correct;
                    }),
                    'explanation' => $question->explanation ?? null,
                ];
            }),
        ]);
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
            ->leftJoin('categories','assessments.categories_id','=','categories.id')
            ->select(
                'assessments.id',
                'assessments.title',
                'assessments.description',
                'assessments.image',
                'assessments.rating',
                'assessments.difficulty',
                'assessments.time_estimate',
                'assessments.tags',
                'assessments.slug',
                'categories.name as category_name',
                DB::raw('(SELECT COUNT(*) FROM user_assessments WHERE user_assessments.assessment_id = assessments.id) as user_count')
            )
            ->orderByDesc('assessments.created_at')
            ->get()
            ->map(function ($assessment) {
                // Helper function to format user count like "12.5k"
                $formatUserCount = function ($number) {
                    return (integer) $number;
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
                    'category' => $assessment->category_name ?? 'NA',
                    'users' => $formatUserCount($assessment->user_count),
                    'slug' => $assessment->slug,
                ];
            });

        return response()->json($assessments);
    }

    public function studentResult($instructorId)
    {
        $rawData = DB::table('user_assessments')
            ->join('users', 'user_assessments.user_id', '=', 'users.id')
            ->join('assessments', 'user_assessments.assessment_id', '=', 'assessments.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'assessments.title as assessment_name',
                'user_assessments.score',
                'user_assessments.completion_time as time_completed',
                'user_assessments.created_at as submit_at'
            )
            ->where('assessments.user_id', $instructorId)
            ->get();
        $data = $rawData->map(function ($item) {
            $item->grade = $this->getGrade($item->score);
            $item->submit_at = Carbon::parse($item->submit_at);
            return $item;
        });
        return response()->json($data);
    }

    // helper function
    private function formatNumber($number)
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'k';
        }
        return (string) $number;
    }
    function getGrade($score)
    {
        if ($score >= 95) return 'A+';
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C+';
        if ($score >= 50) return 'C';
        return 'F';
    }


}
