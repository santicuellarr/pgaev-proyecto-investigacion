<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $leaderboard = $this->leaderboardService->getCourseLeaderboard($course, $request->user());

        return response()->json([
            'leaderboard' => $leaderboard,
        ]);
    }
}
