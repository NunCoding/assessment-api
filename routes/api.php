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


    // question
    Route::get('/assessment/{assessmentId}/question',[QuestionController::class,'show']);
    Route::get('/questions/list',[QuestionController::class,'index']);
    Route::post('/questions',[QuestionController::class,'store']);
    Route::put('/questions/{id}',[QuestionController::class,'update']);
    Route::delete('/questions/{id}/delete',[QuestionController::class,'destroy']);

    // category
    Route::get('/category',[CategoryController::class,"index"]);
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



