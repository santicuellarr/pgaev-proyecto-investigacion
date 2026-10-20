<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRewardRequest;
use App\Http\Requests\RedeemRewardRequest;
use App\Models\Course;
use App\Models\Reward;
use App\Services\GamificationService;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    public function __construct(
        private readonly RewardService $rewardService,
        private readonly GamificationService $gamificationService,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        $rewards = $this->rewardService->getRewardsForCourse($course, $request->user());

        return response()->json([
            'rewards' => $rewards,
        ]);
    }

    public function store(CreateRewardRequest $request, Course $course): JsonResponse
    {
        $this->authorize('create', [Reward::class, $course]);

        $reward = $this->rewardService->createReward(
            $request->validated(),
            $course,
            $request->user(),
        );

        return response()->json([
            'reward' => $reward,
        ], 201);
    }

    public function update(CreateRewardRequest $request, Reward $reward): JsonResponse
    {
        $this->authorize('update', $reward);

        $updated = $this->rewardService->updateReward(
            $reward,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'reward' => $updated,
        ]);
    }

    public function destroy(Request $request, Reward $reward): JsonResponse
    {
        $this->authorize('delete', $reward);

        $this->rewardService->deleteReward($reward, $request->user());

        return response()->json([
            'message' => 'Recompensa eliminada.',
        ]);
    }

    public function redeem(RedeemRewardRequest $request, Reward $reward): JsonResponse
    {
        $this->authorize('redeem', $reward);

        $result = $this->gamificationService->redeemReward(
            $request->user()->id,
            $reward->id,
            $request->validated('idempotency_key'),
        );

        return response()->json([
            'redemption' => $result['redemption'],
        ], $result['was_created'] ? 201 : 200);
    }
}
