<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAssessment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStatistics()
    {

        $totalUsers = User::count();
        $totalAssessments = Assessment::count();
        $totalCategories = Category::count();

        $passedAssessments = UserAssessment::where('score', '>=', 70)->count();
        $totalAssessmentsTaken = UserAssessment::count();

        $satisfaction = $totalAssessmentsTaken > 0
            ? round(($passedAssessments / $totalAssessmentsTaken) * 100, 2)
            : 0;

        return response()->json([
            'total_users' => $totalUsers,
            'total_assessment' => $totalAssessments,
            'total_category' => $totalCategories,
            'satisfaction' => $satisfaction . '%',
        ]);

    }

    public function getStats()
    {
        $stats = Cache::remember('dashboard_stats', 100, function () {
            $currentMonthStart = Carbon::now()->startOfMonth();

            // All-time totals
            $totalUsers = User::count();
            $assessmentsTaken = UserAssessment::count();
            $questionsCreated = Question::count();

            // This month's totals
            $usersThisMonth = User::where('created_at', '>=', $currentMonthStart)->count();
            $assessmentsThisMonth = UserAssessment::where('created_at', '>=', $currentMonthStart)->count();
            $questionsThisMonth = Question::where('created_at', '>=', $currentMonthStart)->count();

            // Average completion times
            $avgCompletionAllTime = UserAssessment::whereNotNull('completion_time')->avg('completion_time') ?? 0;
            $avgCompletionFormatted = $this->formatCompletionTime($avgCompletionAllTime);

            $avgCompletionThisMonth = UserAssessment::whereNotNull('completion_time')
                ->where('created_at', '>=', $currentMonthStart)
                ->avg('completion_time') ?? 0;

            return [
                'total_users' => [
                    'name' => 'Total Users',
                    'value' => number_format($totalUsers),
                    'trend' => $this->calculatePercentageTrend($usersThisMonth, $totalUsers),
                ],
                'assessments_taken' => [
                    'name' => 'Assessments Taken',
                    'value' => number_format($assessmentsTaken),
                    'trend' => $this->calculatePercentageTrend($assessmentsThisMonth, $assessmentsTaken),
                ],
                'questions_created' => [
                    'name' => 'Questions Created',
                    'value' => number_format($questionsCreated),
                    'trend' => $this->calculatePercentageTrend($questionsThisMonth, $questionsCreated),
                ],
                'avg_completion_time' => [
                    'name' => 'Avg. Completion Time',
                    'value' => $avgCompletionFormatted,
                    'trend' => $this->calculateCompletionTimeTrend($avgCompletionThisMonth, $avgCompletionAllTime),
                ],
            ];
        });

        return response()->json($stats);
    }


    public function getRecentActivities()
    {
        $activities = Cache::remember('recent_activities', 300, function () {
            return ActivityLog::latest()
                ->take(10)
                ->get()
                ->map(function ($activity) {
                    return [
                        'title' => $this->generateActivityTitle($activity),
                        'time' => $activity->created_at->diffForHumans(),
                    ];
                })
                ->toArray();
        });

        return response()->json($activities);
    }

    public function getAssessments()
    {
        $assessments = Assessment::with('category')
            ->withCount('questions')
            ->withCount('userAssessments')
            ->get()
            ->map(function ($assessment) {
                $avgScore = DB::table('user_assessments')
                    ->where('assessment_id', $assessment->id)
                    ->avg('score');

                return [
                    'id' => $assessment->id,
                    'name' => $assessment->title,
                    'image' => $assessment->image,
                    'difficulty' => $assessment->difficulty,
                    'category' => $assessment->category->name ?? 'Unknown',
                    'completions' => $assessment->user_assessments_count,
                    'avg_score' => round($avgScore ?? 0),
                    'questions' => $assessment->questions_count,
                ];
            })
            ->sortByDesc('avg_score')
            ->values();

        return response()->json($assessments);
    }

    public function getUsersOverview(Request $request)
    {
        $search = $request->query('name');
        $userQuery = User::withCount('userAssessments')
            ->where('id','!=',1);

        if ($search) {
            $userQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%');
            });
        }

        $users = $userQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->through(function ($user){
               return [
                  'id' => $user->id,
                  'name' => $user->name,
                  'email' => $user->email,
                  'role' => ucfirst($user->role),
                  'status' => 'active',
                  'joined' => $user->created_at ? $user->created_at->format('M d, Y') : 'N/A',
               ] ;
            });
        return response()->json($users);
    }

    public function getUserPerformance()
    {
        $totalAssessments = Assessment::count();

        $paginatedUsers = User::where('id', '!=', 1)
            ->with(['userAssessments', 'activityLogs'])
            ->orderByDesc('created_at')
            ->paginate(10);

        $paginatedUsers->getCollection()->transform(function ($user) use ($totalAssessments) {
            $assessmentTaken = $user->userAssessments->count();

            $avgScore = $assessmentTaken > 0
                ? round($user->userAssessments->avg('score'))
                : 0;

            $completionRate = $totalAssessments > 0
                ? round(($assessmentTaken / $totalAssessments) * 100)
                : 0;

            $lastActivity = optional($user->activityLogs->sortByDesc('created_at')->first())->created_at;

            return [
                'name' => $user->name,
                'email' => $user->email,
                'assessment_taken' => $assessmentTaken,
                'avg_score' => $avgScore,
                'completion_rate' => $completionRate,
                'last_active' => $lastActivity
                    ? Carbon::parse($lastActivity)->diffForHumans()
                    : '0',
            ];
        });

        return response()->json($paginatedUsers);
    }

    public function getWeeklyAssessmentCompletions()
    {
        // Get start and end of the current week (Monday to Sunday)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Correct group by for PostgreSQL (repeat the same expression)
        $data = DB::table('user_assessments')
            ->selectRaw("EXTRACT(DOW FROM created_at)::int as day_of_week, COUNT(*) as total")
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->groupByRaw("EXTRACT(DOW FROM created_at)::int")
            ->orderByRaw("EXTRACT(DOW FROM created_at)::int")
            ->get();

        // PostgreSQL DOW: 0 = Sunday, ..., 6 = Saturday
        $weekMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            0 => 'Sunday',
        ];

        // Initialize full week with 0s
        $formattedData = array_fill_keys(array_values($weekMap), 0);

        foreach ($data as $item) {
            $dayName = $weekMap[$item->day_of_week];
            $formattedData[$dayName] = $item->total;
        }

        return response()->json([
            'labels' => array_keys($formattedData),
            'dataset' => array_values($formattedData),
        ]);
    }

    public function getAverageScoreByCategory()
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Fetch average scores grouped by category and day of week
        $results = DB::table('user_assessments')
            ->join('assessments', 'user_assessments.assessment_id', '=', 'assessments.id')
            ->join('categories', 'assessments.categories_id', '=', 'categories.id')
            ->selectRaw("
            categories.name AS category,
            EXTRACT(DOW FROM user_assessments.created_at)::int AS dow,
            ROUND(AVG(user_assessments.score), 2) AS avg_score
        ")
            ->whereBetween('user_assessments.created_at', [$startOfWeek, $endOfWeek])
            ->groupBy('categories.name', DB::raw('EXTRACT(DOW FROM user_assessments.created_at)::int'))
            ->orderBy('category')
            ->orderBy(DB::raw('EXTRACT(DOW FROM user_assessments.created_at)::int'))
            ->get();

        // Map DOW 0–6 to weekday names
        $dowMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            0 => 'Sunday',
        ];

        // Prepare series: category → [score for each day]
        $categories = [];
        $seriesData = [];

        foreach ($results as $row) {
            $day = $dowMap[$row->dow];
            $cat = $row->category;

            if (!isset($seriesData[$cat])) {
                $seriesData[$cat] = array_fill_keys(array_values($dowMap), 0);
            }

            $seriesData[$cat][$day] = $row->avg_score;
            $categories[$cat] = true;
        }

        // Prepare series array for ECharts
        $series = [];
        foreach ($seriesData as $category => $dayScores) {
            $series[] = [
                'name' => $category,
                'type' => 'bar',
                'stack' => 'total',
                'data' => array_values($dayScores)
            ];
        }

        return response()->json([
            'labels' => array_values($dowMap),
            'series' => $series
        ]);
    }

    public function getUserRegistrations()
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $results = DB::table('users')
            ->selectRaw("EXTRACT(DOW FROM created_at)::int AS dow, COUNT(*) AS registrations")
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->groupByRaw("EXTRACT(DOW FROM created_at)::int")
            ->orderByRaw("EXTRACT(DOW FROM created_at)::int")
            ->get();

        // PostgreSQL DOW: 0=Sunday, 1=Monday, ..., 6=Saturday
        $dowMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            0 => 'Sunday',
        ];

        $data = array_fill_keys(array_values($dowMap), 0);

        foreach ($results as $row) {
            $dayName = $dowMap[$row->dow];
            $data[$dayName] = $row->registrations;
        }

        return response()->json([
            'labels' => array_keys($data),
            'dataset' => array_values($data),
        ]);
    }


    public function getSkillProficiency($userId)
    {
        $results = DB::table('user_assessments')
            ->join('assessments', 'user_assessments.assessment_id', '=', 'assessments.id')
            ->join('categories', 'assessments.categories_id', '=', 'categories.id')
            ->where('user_assessments.user_id', $userId)
            ->select(
                'categories.name as name',
                DB::raw("
                CASE
                    WHEN AVG(user_assessments.score) >= 90 THEN 5
                    WHEN AVG(user_assessments.score) >= 75 THEN 4
                    WHEN AVG(user_assessments.score) >= 60 THEN 3
                    WHEN AVG(user_assessments.score) >= 45 THEN 2
                    ELSE 1
                END as level
            ")
            )
            ->groupBy('categories.id', 'categories.name')
            ->get();

        return response()->json($results);
    }

    public function getAssessmentSummary($userId)
    {
        $results = DB::table('user_assessments')
            ->join('assessments', 'user_assessments.assessment_id', '=', 'assessments.id')
            ->where('user_assessments.user_id', $userId)
            ->select(
                'assessments.title as name',
                'user_assessments.score',
                'user_assessments.completion_time as time_spent',
                DB::raw("TO_CHAR(user_assessments.created_at, 'FMMonth DD, YYYY') as completed_date")
            )
            ->get();

        return response()->json($results);
    }

    public function getUserProfile($userId)
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->select(
                'id',
                'name',
                'role',
                DB::raw("TO_CHAR(created_at, 'FMMonth DD, YYYY') as join_at")
            )
            ->first();

        $assessmentStats = DB::table('user_assessments')
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as completed_assessments, COALESCE(AVG(score), 0) as average_score')
            ->first();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'online' => true,
            'completed_assessments' => (int) $assessmentStats->completed_assessments,
            'average_score' => round($assessmentStats->average_score),
            'join_at' => $user->join_at,
        ]);
    }

    public function getUserRecentActivities($userId)
    {
        $activity = DB::table('activity_logs')
            ->where('user_id',$userId)
            ->orderBy('created_at','desc')
            ->limit(10)
            ->get();
        $result = $activity->map(function ($activity){
           return [
             'id' => $activity->id,
             'title' => match ($activity->activity_type){
               'assessment_completed' => 'Completed Assessment',
               'assessment_assigned' => 'New Assessment Assigned',
               default => 'Activity',
             },
             'time' => Carbon::parse($activity->created_at)->diffForHumans(),
             'description' => $activity->description,
           ];
        });

        return response()->json($result);
    }


    /**
     * Helper function
     */

    private function formatCompletionTime(int $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0;
        }

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }


    private function calculatePercentageTrend($currentMonthCount, $totalCount)
    {
        if ($totalCount == 0) {
            return '0';
        }

        $percentage = ($currentMonthCount / $totalCount) * 100;
        return round($percentage);
    }

    private function calculateCompletionTimeTrend($currentMonthAvg, $allTimeAvg)
    {
        if ($allTimeAvg == 0) {
            return '0';
        }

        $percentageChange = (($currentMonthAvg - $allTimeAvg) / $allTimeAvg) * 100;
        return round($percentageChange);
    }



    private function generateActivityTitle($activity)
    {
        $user = $activity->user_id ? User::find($activity->user_id) : null;
        $assessment = isset($activity->metadata['assessment_id'])
            ? Assessment::find($activity->metadata['assessment_id'])
            : null;

        switch ($activity->activity_type) {
            case 'user_registered':
                return "New user registered";

            case 'assessment_completed':
                if ($user && $assessment) {
                    return "{$user->name} completed {$assessment->title}";
                }
                return "Assessment completed";

            case 'question_added':
                if ($assessment) {
                    return "New question added to {$assessment->title}";
                }
                return "New question added";

            case 'assessment_created':
                if ($assessment) {
                    return "New assessment created: {$assessment->title}";
                }
                return "New assessment created";

            default:
                return $activity->description ?? 'Activity';
        }
    }
}
