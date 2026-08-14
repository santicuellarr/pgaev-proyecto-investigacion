<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\EnrollStudentRequest;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $courses = $this->courseService->getCoursesForUser($request->user());

        return response()->json([
            'courses' => $courses,
        ]);
    }

    public function store(CreateCourseRequest $request): JsonResponse
    {
        if (! in_array($request->user()->role, ['teacher', 'admin'], true)) {
            abort(403, 'Solo los docentes pueden crear cursos.');
        }

        $course = $this->courseService->createCourse(
            $request->validated(),
            $request->user()->id,
        );

        return response()->json([
            'course' => $course,
        ], 201);
    }

    public function enroll(EnrollStudentRequest $request): JsonResponse
    {
        if ($request->user()->role !== 'student') {
            abort(403, 'Solo los estudiantes pueden inscribirse a cursos.');
        }

        $enrollment = $this->courseService->enrollStudent(
            $request->validated('invite_code'),
            $request->user()->id,
        );

        return response()->json([
            'enrollment' => $enrollment->load('course'),
            'course' => $enrollment->course,
        ], 201);
    }
}
