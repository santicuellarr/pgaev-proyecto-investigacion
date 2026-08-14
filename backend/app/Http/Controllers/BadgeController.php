<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function __construct(
        private readonly BadgeService $badgeService,
    ) {}

    public function myBadges(Request $request, Course $course): JsonResponse
    {
        $badges = $this->badgeService->getUserBadgesForCourse($course, $request->user());

        return response()->json([
            'badges' => $badges,
        ]);
    }
}
