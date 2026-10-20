<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAttendanceRequest;
use App\Http\Requests\StoreClassSessionRequest;
use App\Models\ClassSession;
use App\Models\Course;
use App\Services\ClassSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    public function __construct(
        private readonly ClassSessionService $classSessionService,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        return response()->json([
            'sessions' => $this->classSessionService->listForCourse($course, $request->user()),
        ]);
    }

    public function store(StoreClassSessionRequest $request, Course $course): JsonResponse
    {
        $session = $this->classSessionService->create(
            $course,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'session' => $session,
        ], 201);
    }

    public function update(StoreClassSessionRequest $request, ClassSession $classSession): JsonResponse
    {
        $session = $this->classSessionService->update(
            $classSession,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'session' => $session,
        ]);
    }

    public function destroy(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->classSessionService->delete($classSession, $request->user());

        return response()->json([
            'message' => 'Sesión eliminada.',
        ]);
    }

    public function attendance(SaveAttendanceRequest $request, ClassSession $classSession): JsonResponse
    {
        $result = $this->classSessionService->saveAttendance(
            $classSession,
            $request->validated('attendances'),
            $request->user(),
        );

        return response()->json($result);
    }
}
