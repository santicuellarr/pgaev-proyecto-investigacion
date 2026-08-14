<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RewardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::post('/courses/enroll', [CourseController::class, 'enroll']);

    Route::get('/courses/{course}/activities', [ActivityController::class, 'index']);
    Route::post('/courses/{course}/activities', [ActivityController::class, 'store']);
    Route::get('/courses/{course}/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/courses/{course}/rewards', [RewardController::class, 'index']);
    Route::post('/courses/{course}/rewards', [RewardController::class, 'store']);
    Route::get('/courses/{course}/my-badges', [BadgeController::class, 'myBadges']);
    Route::get('/courses/{course}/analytics/export', [ReportController::class, 'exportCourseCsv']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/rewards/{reward}/redeem', [RewardController::class, 'redeem']);
    Route::get('/activities/{activity}', [ActivityController::class, 'show']);
    Route::post('/activities/{activity}/submit', [ActivityController::class, 'submit']);
    Route::patch('/submissions/{submission}/grade', [ActivityController::class, 'grade']);
});
