<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateActivityRequest;
use App\Http\Requests\GradeSubmissionRequest;
use App\Http\Requests\PublishActivityRequest;
use App\Http\Requests\SubmitActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
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
        $this->authorize('view', $course);

        $activities = $this->activityService->getActivitiesForCourse($course, $request->user());

        return response()->json([
            'activities' => $activities,
        ]);
    }

    public function store(CreateActivityRequest $request, Course $course): JsonResponse
    {
        $this->authorize('create', [Activity::class, $course]);

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
        $this->authorize('view', $activity);

        $user = $request->user();
        $activity = $this->activityService->getActivity($activity, $user);

        return response()->json([
            'activity' => $activity,
            'my_submission' => $this->activityService->getStudentLatestSubmission($activity, $user),
        ]);
    }

    public function submit(SubmitActivityRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('submit', $activity);

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
        $this->authorize('grade', $submission);

        $result = $this->submissionService->gradeSubmission(
            $submission,
            (float) $request->validated('score'),
            $request->user(),
            $request->validated('teacher_feedback'),
        );

        return response()->json([
            'submission' => $result['submission'],
            'points_awarded' => $result['points_awarded'],
        ]);
    }

    public function submissions(Request $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        $submissions = $this->activityService->listSubmissionsForTeacher($activity, $request->user());

        return response()->json([
            'submissions' => $submissions,
        ]);
    }

    public function regenerateAiFeedback(Request $request, ActivitySubmission $submission): JsonResponse
    {
        $this->authorize('grade', $submission);

        $updated = $this->submissionService->regenerateAiFeedback($submission, $request->user());

        return response()->json([
            'submission' => $updated,
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        $updated = $this->activityService->updateActivity(
            $activity,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'activity' => $updated,
        ]);
    }

    public function publish(PublishActivityRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        $updated = $this->activityService->publishActivity(
            $activity,
            $request->user(),
            $request->boolean('is_published', true),
        );

        return response()->json([
            'activity' => $updated,
        ]);
    }

    public function destroy(Request $request, Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        $this->activityService->deleteActivity($activity, $request->user());

        return response()->json([
            'message' => 'Actividad eliminada.',
        ]);
    }
}
