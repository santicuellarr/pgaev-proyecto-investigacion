<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\EnrollStudentRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Requests\UpdateCourseStatusRequest;
use App\Models\Course;
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
        $this->authorize('create', Course::class);

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
        $this->authorize('enroll', Course::class);

        $enrollment = $this->courseService->enrollStudent(
            $request->validated('invite_code'),
            $request->user()->id,
        );

        return response()->json([
            'enrollment' => $enrollment->load('course'),
            'course' => $enrollment->course,
        ], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return response()->json(
            $this->courseService->getCourseDetail($course, $request->user()),
        );
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $updated = $this->courseService->updateCourse(
            $course,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'course' => $updated,
        ]);
    }

    public function updateStatus(UpdateCourseStatusRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $updated = $this->courseService->updateStatus(
            $course,
            $request->validated('status'),
            $request->user(),
        );

        return response()->json([
            'course' => $updated,
        ]);
    }
}
