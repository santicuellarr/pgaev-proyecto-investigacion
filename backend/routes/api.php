<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ClassSessionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\ForumPostController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedemptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RewardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::post('/token/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);

    Route::middleware(['active', 'verified.when_required'])->group(function () {
        Route::middleware('admin')->group(function () {
            Route::get('/admin/overview', [AdminController::class, 'overview']);
            Route::get('/admin/users', [AdminController::class, 'users']);
            Route::patch('/admin/users/{user}/role', [AdminController::class, 'updateRole']);
            Route::patch('/admin/users/{user}/status', [AdminController::class, 'updateStatus']);
        });
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::get('/me/history', [HistoryController::class, 'me']);
        Route::get('/me/progress', [ProgressController::class, 'me']);
        Route::get('/courses/{course}/students/{user}/history', [HistoryController::class, 'forStudent']);

        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::post('/courses/enroll', [CourseController::class, 'enroll']);
        Route::get('/courses/{course}', [CourseController::class, 'show']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::patch('/courses/{course}/status', [CourseController::class, 'updateStatus']);

        Route::get('/courses/{course}/activities', [ActivityController::class, 'index']);
        Route::post('/courses/{course}/activities', [ActivityController::class, 'store']);
        Route::get('/courses/{course}/leaderboard', [LeaderboardController::class, 'index']);
        Route::get('/courses/{course}/rewards', [RewardController::class, 'index']);
        Route::post('/courses/{course}/rewards', [RewardController::class, 'store']);
        Route::put('/rewards/{reward}', [RewardController::class, 'update']);
        Route::delete('/rewards/{reward}', [RewardController::class, 'destroy']);
        Route::get('/redemptions/pending', [RedemptionController::class, 'pending']);
        Route::post('/redemptions/{redemption}/approve', [RedemptionController::class, 'approve']);
        Route::post('/redemptions/{redemption}/reject', [RedemptionController::class, 'reject']);
        Route::get('/courses/{course}/my-badges', [BadgeController::class, 'myBadges']);
        Route::get('/courses/{course}/badges', [BadgeController::class, 'index']);
        Route::post('/courses/{course}/badges', [BadgeController::class, 'store']);
        Route::put('/courses/{course}/badges/{badge}', [BadgeController::class, 'update']);
        Route::delete('/courses/{course}/badges/{badge}', [BadgeController::class, 'destroy']);
        Route::post('/badges/{badge}/award/{user}', [BadgeController::class, 'award']);
        Route::get('/teacher/overview', [ReportController::class, 'overview']);
        Route::get('/courses/{course}/analytics', [ReportController::class, 'analytics']);
        Route::get('/courses/{course}/analytics/export', [ReportController::class, 'exportCourseCsv']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/rewards/{reward}/redeem', [RewardController::class, 'redeem'])->middleware('throttle:redeem');
        Route::get('/courses/{course}/sessions', [ClassSessionController::class, 'index']);
        Route::post('/courses/{course}/sessions', [ClassSessionController::class, 'store']);
        Route::put('/sessions/{classSession}', [ClassSessionController::class, 'update']);
        Route::delete('/sessions/{classSession}', [ClassSessionController::class, 'destroy']);
        Route::put('/sessions/{classSession}/attendance', [ClassSessionController::class, 'attendance']);
        Route::get('/activities/{activity}/posts', [ForumPostController::class, 'index']);
        Route::post('/activities/{activity}/posts', [ForumPostController::class, 'store']);
        Route::get('/activities/{activity}', [ActivityController::class, 'show']);
        Route::get('/activities/{activity}/submissions', [ActivityController::class, 'submissions']);
        Route::put('/activities/{activity}', [ActivityController::class, 'update']);
        Route::patch('/activities/{activity}/publish', [ActivityController::class, 'publish']);
        Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
        Route::post('/activities/{activity}/submit', [ActivityController::class, 'submit'])->middleware('throttle:submit');
        Route::patch('/submissions/{submission}/grade', [ActivityController::class, 'grade']);
        Route::post('/submissions/{submission}/ai-feedback/regenerate', [ActivityController::class, 'regenerateAiFeedback']);
    });
});
