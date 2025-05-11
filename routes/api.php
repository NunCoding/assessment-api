<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserAssessmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

// assessment
    Route::get('/assessment/category',[AssessmentController::class,"getCategory"]);
    Route::get('/assessments/popular', [AssessmentController::class, 'topPopularAssessments']);
Route::middleware('auth:sanctum')->group(function (){
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::get('/assessments/list',[AssessmentController::class,'list']);
//    Route::get('/assessments/{id}', [AssessmentController::class, 'show']);
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessment/{id}/task',[AssessmentController::class,"show"]);
    Route::post('/user-assessment/submit',[UserAssessmentController::class,"submitResult"]);
    Route::get('/assessment/stats',[UserAssessmentController::class,'getAssessmentStats']);


    // question
    Route::get('/assessment/{assessmentId}/question',[QuestionController::class,'show']);
    Route::get('/questions/list',[QuestionController::class,'index']);
    Route::post('/questions',[QuestionController::class,'store']);
    Route::put('/questions/{id}',[QuestionController::class,'update']);
    Route::delete('/questions/{id}/delete',[QuestionController::class,'destroy']);

    // category
    Route::get('/category',[CategoryController::class,"index"]);

    // user
    Route::put('/user/{id}',[AuthController::class,'update']);
    Route::post('/users',[AuthController::class,'create']);
});

// upload
Route::post('/upload',[FileUploadController::class,'upload']);

// user
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// stats
Route::get('/statistics',[DashboardController::class,"getStatistics"]);
Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
Route::get('/dashboard/activity',[DashboardController::class,"getRecentActivities"]);
Route::get('/dashboard/popular-assessments',[DashboardController::class,'getAssessments']);
Route::get('/dashboard/users',[DashboardController::class,'getUsersOverview']);
Route::get('/dashboard/userPerformance',[DashboardController::class,"getUserPerformance"]);
Route::get('/analytics/weekly-assessments',[DashboardController::class,"getWeeklyAssessmentCompletions"]);
Route::get('/analytics/average-assessments',[DashboardController::class,"getAverageScoreByCategory"]);
Route::get('/analytics/user-register',[DashboardController::class,"getUserRegistrations"]);

// user profile
Route::get('/profile/skill-proficient/{userId}',[DashboardController::class,"getSkillProficiency"]);
Route::get('/profile/user-assessments/{userId}',[DashboardController::class,"getAssessmentSummary"]);
Route::get('/profile/user/{userId}',[DashboardController::class,"getUserProfile"]);
Route::get('/profile/user-activity/{userId}',[DashboardController::class,"getUserRecentActivities"]);



