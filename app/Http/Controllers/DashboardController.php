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
        $data = Cache::remember('dashboard_statistics', 300, function () {
            $totalUsers = User::count();
            $totalAssessments = Assessment::count();
            $totalCategories = Category::count();

            $passedAssessments = UserAssessment::where('score', '>=', 70)->count();
            $totalAssessmentsTaken = UserAssessment::count();

            $satisfaction = $totalAssessmentsTaken > 0
                ? round(($passedAssessments / $totalAssessmentsTaken) * 100, 2)
                : 0;

            return [
                'total_users' => $totalUsers,
                'total_assessment' => $totalAssessments,
                'total_category' => $totalCategories,
                'satisfaction' => $satisfaction . '%',
            ];
        });

        return response()->json($data);
    }

    public function getStats()
    {
        $stats = Cache::remember('dashboard_stats', 300, function () {
            $now = Carbon::now();
            $lastMonth = $now->copy()->subMonth();

            // Current totals
            $totalUsers = User::count();
            $assessmentsTaken = UserAssessment::count();
            $questionsCreated = Question::count();

            // Last month's totals
            $usersLastMonth = User::where('created_at', '>=', $lastMonth)->count();
            $assessmentsLastMonth = UserAssessment::where('created_at', '>=', $lastMonth)->count();
            $questionsLastMonth = Question::where('created_at', '>=', $lastMonth)->count();

            // Calculate trends
            $userTrend = $this->calculateTrend($usersLastMonth, $totalUsers);
            $assessmentTrend = $this->calculateTrend($assessmentsLastMonth, $assessmentsTaken);
            $questionTrend = $this->calculateTrend($questionsLastMonth, $questionsCreated);

            // Average completion time (CURRENT)
            $avgCompletionSeconds = UserAssessment::whereNotNull('completion_time')->avg('completion_time') ?? 0;
            $minutes = floor($avgCompletionSeconds / 60);
            $seconds = floor($avgCompletionSeconds % 60);
            $avgCompletionFormatted = sprintf('%02d:%02d', $minutes, $seconds);

            // Average completion time (LAST MONTH)
            $avgCompletionLastMonth = UserAssessment::whereNotNull('completion_time')
                ->where('created_at', '>=', $lastMonth)
                ->avg('completion_time') ?? 0;

            // Calculate trend for average completion time
            $completionTimeTrend = $this->calculateTrend($avgCompletionLastMonth, $avgCompletionSeconds);

            // Return OBJECT (not array)
            return [
                'total_users' => [
                    'name' => 'Total Users',
                    'value' => number_format($totalUsers),
                    'trend' => $userTrend,
                ],
                'assessments_taken' => [
                    'name' => 'Assessments Taken',
                    'value' => number_format($assessmentsTaken),
                    'trend' => $assessmentTrend,
                ],
                'questions_created' => [
                    'name' => 'Questions Created',
                    'value' => number_format($questionsCreated),
                    'trend' => $questionTrend,
                ],
                'avg_completion_time' => [
                    'name' => 'Avg. Completion Time',
                    'value' => $avgCompletionFormatted,
                    'trend' => $completionTimeTrend,
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
        $user = User::with(['userAssessments'],['activityLogs'])
            ->get()
            ->map(function ($user) use ($totalAssessments) {
               $assessmentTaken = $user->userAssessments->count();
               $avgScore = $assessmentTaken > 0
                   ? round($user->userAssessments->avg('score'))
                   : 0;
                $completionRate = $totalAssessments > 0
                    ? round(($assessmentTaken / $totalAssessments) * 100)
                    : 0;

               $lastActivity = optional($user->activityLogs->sortByDesc('created-at')->first())->created_at;
               $lastActive = $lastActivity
                   ? Carbon::parse($lastActivity)->diffForHumans()
                   : 'N/A';

               return [
                 'name' => $user->name,
                 'email' => $user->email,
                 'assessment_token' => $assessmentTaken,
                 'avg_score' => $avgScore,
                 'completion_rate' => $completionRate,
                 'last_active' => $lastActive,
               ];
            });
        return response()->json($user);
    }


    /**
     * Helper function
     */
    private function calculateTrend($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
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
