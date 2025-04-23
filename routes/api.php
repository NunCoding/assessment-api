<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\QuestionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

// assessment
Route::middleware('auth:sanctum')->group(function (){
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::get('/assessments/list',[AssessmentController::class,'list']);
    Route::get('/assessments/{id}', [AssessmentController::class, 'show']);
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessment/{id}/task',[AssessmentController::class,"show"]);

    // question
    Route::get('/assessment/{assessmentId}/question',[QuestionController::class,'index']);
    Route::post('/questions',[QuestionController::class,'store']);

    // category
    Route::get('/category',[CategoryController::class,"index"]);
});

// upload
Route::post('/upload',[FileUploadController::class,'upload']);

// user
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


