<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectRedemptionRequest;
use App\Models\Redemption;
use App\Services\RedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedemptionController extends Controller
{
    public function __construct(
        private readonly RedemptionService $redemptionService,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $redemptions = $this->redemptionService->pendingForTeacher($request->user());

        return response()->json([
            'redemptions' => $redemptions,
            'pending_count' => $redemptions->count(),
        ]);
    }

    public function approve(Request $request, Redemption $redemption): JsonResponse
    {
        $this->authorize('approve', $redemption);

        $updated = $this->redemptionService->approve($redemption, $request->user());

        return response()->json([
            'redemption' => $updated,
        ]);
    }

    public function reject(RejectRedemptionRequest $request, Redemption $redemption): JsonResponse
    {
        $this->authorize('reject', $redemption);

        $updated = $this->redemptionService->reject(
            $redemption,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'redemption' => $updated,
        ]);
    }
}
