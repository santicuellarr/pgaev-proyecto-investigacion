<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBadgeRequest;
use App\Models\Badge;
use App\Models\Course;
use App\Models\User;
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

    public function index(Request $request, Course $course): JsonResponse
    {
        return response()->json([
            'badges' => $this->badgeService->listForCourse($course, $request->user()),
        ]);
    }

    public function store(StoreBadgeRequest $request, Course $course): JsonResponse
    {
        $badge = $this->badgeService->createBadge(
            $request->validated(),
            $course,
            $request->user(),
        );

        return response()->json([
            'badge' => $badge,
        ], 201);
    }

    public function update(StoreBadgeRequest $request, Course $course, Badge $badge): JsonResponse
    {
        if ($badge->course_id && $badge->course_id !== $course->id) {
            abort(404);
        }

        $updated = $this->badgeService->updateBadge($badge, $request->validated(), $request->user());

        return response()->json([
            'badge' => $updated,
        ]);
    }

    public function destroy(Request $request, Course $course, Badge $badge): JsonResponse
    {
        if ($badge->course_id && $badge->course_id !== $course->id) {
            abort(404);
        }

        $this->badgeService->deleteBadge($badge, $request->user());

        return response()->json([
            'message' => 'Insignia eliminada.',
        ]);
    }

    public function award(Request $request, Badge $badge, User $user): JsonResponse
    {
        $awarded = $this->badgeService->awardManually($badge, $user, $request->user());

        return response()->json([
            'user_badge' => $awarded,
        ], 201);
    }
}
