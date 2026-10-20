<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\PointLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForumService
{
    public const POINT_SOURCE = 'forum_participation';

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * @return Collection<int, ForumPost>
     */
    public function listPosts(Activity $activity, User $user): Collection
    {
        $activity->loadMissing('course');
        $this->ensureForumActivity($activity);
        $this->ensureUserCanAccessForum($activity, $user);

        return ForumPost::query()
            ->with('user:id,name,email')
            ->where('activity_id', $activity->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array{post: ForumPost, points_awarded: int}
     */
    public function createPost(Activity $activity, User $student, string $body, ?int $parentId = null): array
    {
        $activity->loadMissing('course');
        $this->ensureForumActivity($activity);

        if ($student->role !== 'student') {
            abort(403, 'Solo los estudiantes pueden publicar en el foro.');
        }

        $this->ensureStudentEnrolled($activity, $student);

        if (! $activity->is_published) {
            abort(403, 'Este foro aún no está publicado.');
        }

        if ($parentId) {
            $parent = ForumPost::query()
                ->where('id', $parentId)
                ->where('activity_id', $activity->id)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['La respuesta debe pertenecer a este foro.'],
                ]);
            }
        }

        return DB::transaction(function () use ($activity, $student, $body, $parentId) {
            $alreadyParticipated = ForumPost::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $student->id)
                ->exists();

            $post = ForumPost::create([
                'activity_id' => $activity->id,
                'user_id' => $student->id,
                'parent_id' => $parentId,
                'body' => $body,
            ]);

            $pointsAwarded = 0;

            if (! $alreadyParticipated) {
                $alreadyAwarded = PointLog::query()
                    ->where('user_id', $student->id)
                    ->where('course_id', $activity->course_id)
                    ->where('source_type', self::POINT_SOURCE)
                    ->where('source_id', $activity->id)
                    ->exists();

                if (! $alreadyAwarded && $activity->base_points > 0) {
                    $this->gamificationService->awardPoints(
                        $student->id,
                        $activity->course_id,
                        (int) $activity->base_points,
                        self::POINT_SOURCE,
                        $activity->id,
                    );
                    $pointsAwarded = (int) $activity->base_points;
                }
            }

            return [
                'post' => $post->load('user:id,name,email'),
                'points_awarded' => $pointsAwarded,
            ];
        });
    }

    private function ensureForumActivity(Activity $activity): void
    {
        if ($activity->type !== 'foro') {
            abort(422, 'Esta actividad no es un foro.');
        }
    }

    private function ensureUserCanAccessForum(Activity $activity, User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'teacher' && $activity->course->owner_id === $user->id) {
            return;
        }

        if ($user->role === 'student') {
            $this->ensureStudentEnrolled($activity, $user);

            return;
        }

        abort(403, 'No tienes acceso a este foro.');
    }

    private function ensureStudentEnrolled(Activity $activity, User $student): void
    {
        $isEnrolled = Enrollment::query()
            ->where('course_id', $activity->course_id)
            ->where('user_id', $student->id)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'Solo los estudiantes inscritos pueden participar en el foro.');
        }
    }
}
