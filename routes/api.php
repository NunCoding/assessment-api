<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AiRecommendationController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\MessageController;
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
    Route::put('/assessments/{id}', [AssessmentController::class, 'update']);
    Route::get('/assessment/{id}/task',[AssessmentController::class,"show"]);
    Route::post('/user-assessment/submit',[UserAssessmentController::class,"submitResult"]);

    // ai
    // routes/api.php
    Route::get('/recommendations/{id}/recommend', [AIController::class, 'show']);


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
    Route::get('/user/feedback/{userId}',[FeedbackController::class,'show']);
    Route::post('/users',[AuthController::class,'create']);
    Route::post('/user/feedback',[FeedbackController::class,'store']);

    // instructor
    Route::post('/instructor/assessments',[AssessmentController::class,'store']);
    Route::get('/take-assessment/{slug}', [AssessmentController::class, 'assessmentBySlug']);
    Route::get('/student/{instructorId}/result',[AssessmentController::class,'studentResult']);

    // student
    Route::post('/student/submitResult',[MessageController::class,'store']);
    Route::get('/student/messages/{id}',[MessageController::class,'show']);
});
Route::get('/recommendations', [AIController::class, 'index']);


// upload
Route::post('/upload',[FileUploadController::class,'upload']);

// user
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/feedback/user', [FeedbackController::class, 'index']);

// stats
Route::get('/assessment/stats',[UserAssessmentController::class,'getAssessmentStats']);
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



