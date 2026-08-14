<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateActivityRequest;
use App\Http\Requests\GradeSubmissionRequest;
use App\Http\Requests\SubmitActivityRequest;
use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Services\ActivityService;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly SubmissionService $submissionService,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $activities = $this->activityService->getActivitiesForCourse($course, $request->user());

        return response()->json([
            'activities' => $activities,
        ]);
    }

    public function store(CreateActivityRequest $request, Course $course): JsonResponse
    {
        $activity = $this->activityService->createActivity(
            $request->validated(),
            $course,
            $request->user(),
        );

        return response()->json([
            'activity' => $activity,
        ], 201);
    }

    public function show(Request $request, Activity $activity): JsonResponse
    {
        $user = $request->user();
        $activity = $this->activityService->getActivity($activity, $user);

        return response()->json([
            'activity' => $activity,
            'my_submission' => $this->activityService->getStudentLatestSubmission($activity, $user),
        ]);
    }

    public function submit(SubmitActivityRequest $request, Activity $activity): JsonResponse
    {
        if ($request->user()->role !== 'student') {
            abort(403, 'Solo los estudiantes pueden enviar actividades.');
        }

        $result = $this->submissionService->submitActivity(
            $activity->id,
            $request->user()->id,
            $request->validated('content'),
            $request->validated('idempotency_key'),
        );

        return response()->json([
            'submission' => $result['submission'],
        ], $result['was_created'] ? 201 : 200);
    }

    public function grade(GradeSubmissionRequest $request, ActivitySubmission $submission): JsonResponse
    {
        $gradedSubmission = $this->submissionService->gradeSubmission(
            $submission,
            (float) $request->validated('score'),
            $request->user(),
        );

        return response()->json([
            'submission' => $gradedSubmission,
        ]);
    }
}
