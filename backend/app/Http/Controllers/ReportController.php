<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json(
            $this->reportService->getTeacherOverview($request->user()),
        );
    }

    public function analytics(Request $request, Course $course): JsonResponse
    {
        return response()->json(
            $this->reportService->getCourseAnalytics($course, $request->user()),
        );
    }

    public function exportCourseCsv(Request $request, Course $course): StreamedResponse
    {
        return $this->reportService->exportCourseCsv(
            $course->id,
            $request->user(),
            $request->query('type'),
        );
    }
}
