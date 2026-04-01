<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ReportEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/assessments', [AssessmentController::class, 'store']);
Route::get('/assessments/{submission}', [AssessmentController::class, 'show']);
Route::post('/assessments/{submission}/email-report', [ReportEmailController::class, 'send']);
