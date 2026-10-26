<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoryIndexRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\HistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class HistoryController extends Controller
{
    public function __construct(
        private readonly HistoryService $historyService,
    ) {}

    public function me(HistoryIndexRequest $request): JsonResponse
    {
        $course = $request->filled('course_id')
            ? Course::findOrFail((int) $request->validated('course_id'))
            : null;

        return $this->respond(
            $request,
            $request->user(),
            $course,
        );
    }

    public function forStudent(HistoryIndexRequest $request, Course $course, User $user): JsonResponse
    {
        return $this->respond($request, $user, $course);
    }

    private function respond(HistoryIndexRequest $request, User $subject, ?Course $course): JsonResponse
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->validated('from'))->startOfDay()
            : null;

        $to = $request->filled('to')
            ? Carbon::parse($request->validated('to'))->endOfDay()
            : null;

        $paginator = $this->historyService->paginateForUser(
            $request->user(),
            $subject,
            $course,
            $request->validated('type'),
            $from,
            $to,
            (int) ($request->validated('page') ?? 1),
            $request->url(),
            $request->query(),
        );

        return response()->json([
            'history' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
