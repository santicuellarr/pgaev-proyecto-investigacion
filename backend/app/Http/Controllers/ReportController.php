<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function exportCourseCsv(Request $request, Course $course): StreamedResponse
    {
        return $this->reportService->exportCourseCsv($course->id, $request->user());
    }
}
