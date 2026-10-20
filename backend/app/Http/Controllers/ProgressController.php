<?php

namespace App\Http\Controllers;

use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private readonly ProgressService $progressService,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $courseId = $request->query('course_id');

        return response()->json(
            $this->progressService->forStudent(
                $request->user(),
                $courseId !== null ? (int) $courseId : null,
            ),
        );
    }
}
