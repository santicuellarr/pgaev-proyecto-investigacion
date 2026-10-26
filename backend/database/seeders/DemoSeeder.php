<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\Badge;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserCourseStat;
use App\Services\ClassSessionService;
use App\Services\ForumService;
use App\Services\GamificationService;
use App\Services\OpenAIService;
use App\Services\RedemptionService;
use App\Services\SubmissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private const SEED = 2026;

    private const PASSWORD = 'password';

    /**
     * @var list<array{level_number: int, name: string, points_required: int}>
     */
    private const LEVELS = [
        ['level_number' => 1, 'name' => 'Novato', 'points_required' => 0],
        ['level_number' => 2, 'name' => 'Aprendiz', 'points_required' => 50],
        ['level_number' => 3, 'name' => 'Practicante', 'points_required' => 120],
        ['level_number' => 4, 'name' => 'Avanzado', 'points_required' => 200],
        ['level_number' => 5, 'name' => 'Experto', 'points_required' => 300],
        ['level_number' => 6, 'name' => 'Maestro', 'points_required' => 420],
    ];

    private SubmissionService $submissions;

    private ForumService $forums;

    private ClassSessionService $sessions;

    private GamificationService $gamification;

    private RedemptionService $redemptions;

    private Carbon $origin;

    public function run(): void
    {
        $this->seedFaker();
        $this->bindDemoAi();
        $this->origin = Carbon::now()->copy();

        $this->submissions = app(SubmissionService::class);
        $this->forums = app(ForumService::class);
        $this->sessions = app(ClassSessionService::class);
        $this->gamification = app(GamificationService::class);
        $this->redemptions = app(RedemptionService::class);

        $password = Hash::make(self::PASSWORD);

        try {
            $admin = $this->createAdmin($password);
            $teachers = $this->createTeachers($password);
            $students = $this->createStudents($password);
            $courses = $this->createCourses($teachers);
            $this->enrollStudents($courses, $students);

            foreach ($courses as $course) {
                $this->seedCourseCatalog($course);
                $this->seedCourseActivity($course, $students);
            }

            $this->seedRedemptions($courses, $students);
            $this->nudgeNearLevelUp($students[0], $courses[0]);
            $this->grantManualBadges($courses, $students);
            $this->stampStreaks($students);

            $this->command?->info('Demo PGAEV lista. Admin: '.$admin->email.' / '.self::PASSWORD);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function seedFaker(): void
    {
        config(['app.faker_locale' => 'es_CO']);
        mt_srand(self::SEED);
        fake()->unique(true);
        fake()->seed(self::SEED);
        fake('es_CO')->unique(true);
        fake('es_CO')->seed(self::SEED);
    }

    private function bindDemoAi(): void
    {
        $stub = new class extends OpenAIService
        {
            public function generateFeedback(string $submissionContent, string $activityTitle, ?int $submissionId = null): string
            {
                return '[Demo, IA desactivada] Retroalimentación de ejemplo sobre «'.$activityTitle.'»: la entrega cubre lo esencial; profundiza en la justificación y cita fuentes.';
            }
        };

        app()->instance(OpenAIService::class, $stub);
        app()->forgetInstance(SubmissionService::class);
    }

    private function createAdmin(string $password): User
    {
        return User::factory()->admin()->create([
            'name' => 'Admin PGAEV',
            'email' => 'admin@pgaev.edu.co',
            'password' => $password,
            'bio' => 'Administración institucional de la PGAEV.',
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function createTeachers(string $password): Collection
    {
        $profiles = [
            ['name' => 'Prof. Carlos Mendoza', 'email' => 'docente@pgaev.edu.co', 'bio' => 'Docente de ingeniería de software y bases de datos.'],
            ['name' => 'Prof. Laura Restrepo', 'email' => 'docente2@pgaev.edu.co', 'bio' => 'Docente de estructuras de datos y ética profesional.'],
            ['name' => 'Prof. Andrés Herrera', 'email' => 'docente3@pgaev.edu.co', 'bio' => 'Docente de apoyo; disponible para asignación de cursos.'],
        ];

        return collect($profiles)->map(fn (array $profile) => User::factory()->teacher()->create([
            ...$profile,
            'password' => $password,
        ]));
    }

    /**
     * @return Collection<int, User>
     */
    private function createStudents(string $password): Collection
    {
        $preset = [
            1 => 'Ana García',
            2 => 'Luis Ramírez',
            3 => 'María Torres',
        ];

        $students = collect();

        for ($i = 1; $i <= 30; $i++) {
            $students->push(User::factory()->create([
                'name' => $preset[$i] ?? fake('es_CO')->name(),
                'email' => "estudiante{$i}@pgaev.edu.co",
                'password' => $password,
                'bio' => $i <= 8 ? 'Estudiante con alta participación en el semestre.' : ($i >= 26 ? 'Participación baja en el semestre de demostración.' : null),
            ]));
        }

        return $students->values();
    }

    /**
     * @param  Collection<int, User>  $teachers
     * @return Collection<int, Course>
     */
    private function createCourses(Collection $teachers): Collection
    {
        $carlos = $teachers[0];
        $laura = $teachers[1];

        $definitions = [
            [
                'owner_id' => $carlos->id,
                'title' => 'Bases de Datos',
                'description' => 'Modelado relacional, SQL y transaccionalidad para el prototipo PGAEV.',
                'invite_code' => 'PGAEV1',
                'color' => '#0ea5e9',
            ],
            [
                'owner_id' => $carlos->id,
                'title' => 'Ingeniería de Software II',
                'description' => 'Arquitectura, calidad y gamificación aplicada al aula virtual.',
                'invite_code' => 'PGAEV2',
                'color' => '#6366f1',
            ],
            [
                'owner_id' => $laura->id,
                'title' => 'Estructuras de Datos',
                'description' => 'Listas, árboles, grafos y análisis de complejidad.',
                'invite_code' => 'PGAEV3',
                'color' => '#f59e0b',
            ],
            [
                'owner_id' => $laura->id,
                'title' => 'Ética Profesional',
                'description' => 'Responsabilidad profesional, privacidad y decisiones en sistemas educativos.',
                'invite_code' => 'PGAEV4',
                'color' => '#10b981',
            ],
        ];

        return collect($definitions)->map(fn (array $definition) => Course::factory()->create([
            ...$definition,
            'is_active' => true,
            'status' => Course::STATUS_PUBLISHED,
            'starts_at' => $this->origin->copy()->subWeeks(8),
            'ends_at' => $this->origin->copy()->addMonths(2),
        ]));
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, User>  $students
     */
    private function enrollStudents(Collection $courses, Collection $students): void
    {
        $slices = [
            range(0, 21),
            range(7, 29),
            array_merge(range(0, 11), range(19, 29)),
            range(4, 24),
        ];

        foreach ($courses as $courseIndex => $course) {
            foreach ($slices[$courseIndex] as $offset => $studentIndex) {
                Enrollment::factory()->create([
                    'user_id' => $students[$studentIndex]->id,
                    'course_id' => $course->id,
                    'status' => 'active',
                    'enrolled_at' => $this->origin->copy()->subWeeks(8)->addDays($offset),
                ]);
            }
        }
    }

    private function seedCourseCatalog(Course $course): void
    {
        foreach (self::LEVELS as $level) {
            Level::factory()->create([
                'course_id' => $course->id,
                ...$level,
            ]);
        }

        $badges = [
            [Badge::RULE_FIRST_SUBMISSION, 'Primer Envío', 'Realiza tu primer envío del curso.', '🥇', 1],
            [Badge::RULE_SUBMISSIONS_COUNT, '5 entregas', 'Completa 5 entregas en el curso.', '📘', 5],
            [Badge::RULE_POINTS_TOTAL, '200 pts', 'Alcanza 200 puntos en el curso.', '💎', 200],
            [Badge::RULE_LEVEL_REACHED, 'Nivel 3', 'Alcanza el nivel Practicante.', '🚀', 3],
            [Badge::RULE_MANUAL, 'Colaborador destacado', 'Otorgada por el docente por aportes excepcionales.', '🤝', null],
        ];

        foreach ($badges as [$rule, $name, $description, $icon, $value]) {
            Badge::factory()->create([
                'course_id' => $course->id,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'trigger_type' => $rule,
                'trigger_config' => ['event' => $rule],
                'rule_type' => $rule,
                'rule_value' => $value,
            ]);
        }

        $rewards = [
            ['Punto extra en quiz', 80, 12, false],
            ['Sticker digital del curso', 30, 25, false],
            ['Asesoría individual', 40, 8, true],
            ['Exención de un quiz', 120, 5, true],
        ];

        foreach ($rewards as [$name, $cost, $stock, $approval]) {
            Reward::factory()->create([
                'course_id' => $course->id,
                'name' => $name,
                'cost_points' => $cost,
                'stock' => $stock,
                'needs_approval' => $approval,
                'available_from' => $this->origin->copy()->subWeeks(8),
                'available_to' => $this->origin->copy()->addMonths(2),
            ]);
        }

        foreach ($this->activityBlueprints($course->title) as $blueprint) {
            Activity::factory()->create([
                'course_id' => $course->id,
                ...$blueprint,
            ]);
        }

        $sessionStarts = [
            $this->origin->copy()->subWeeks(7)->setTime(18, 0),
            $this->origin->copy()->subWeeks(5)->setTime(18, 0),
            $this->origin->copy()->subWeeks(3)->setTime(18, 0),
            $this->origin->copy()->subWeeks(1)->setTime(18, 0),
            $this->origin->copy()->addDays(4)->setTime(18, 0),
            $this->origin->copy()->addWeeks(2)->setTime(18, 0),
        ];

        foreach ($sessionStarts as $index => $startsAt) {
            ClassSession::factory()->create([
                'course_id' => $course->id,
                'title' => 'Encuentro sincrónico '.($index + 1).': '.$course->title,
                'starts_at' => $startsAt,
                'duration_min' => 90,
                'meeting_url' => 'https://meet.example.edu/'.strtolower($course->invite_code).'-'.($index + 1),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityBlueprints(string $courseTitle): array
    {
        $short = explode(' ', $courseTitle)[0];

        return [
            ['title' => "Quiz 1: fundamentos de {$short}", 'type' => 'quiz', 'base_points' => 50, 'max_attempts' => 2, 'due_at' => $this->origin->copy()->subWeeks(6)->setTime(23, 59), 'is_published' => true],
            ['title' => "Tarea 1: práctica de {$short}", 'type' => 'tarea', 'base_points' => 75, 'max_attempts' => 3, 'due_at' => $this->origin->copy()->subWeeks(4)->setTime(23, 59), 'is_published' => true],
            ['title' => "Evaluación parcial de {$short}", 'type' => 'evaluacion', 'base_points' => 80, 'max_attempts' => 1, 'due_at' => $this->origin->copy()->subWeeks(3)->setTime(23, 59), 'is_published' => true],
            ['title' => "Proyecto: caso aplicado de {$short}", 'type' => 'proyecto', 'base_points' => 100, 'max_attempts' => 1, 'due_at' => $this->origin->copy()->subWeeks(1)->setTime(23, 59), 'is_published' => true],
            ['title' => "Foro: debate de {$short}", 'type' => 'foro', 'base_points' => 15, 'max_attempts' => 1, 'due_at' => $this->origin->copy()->addDays(10)->setTime(23, 59), 'is_published' => true],
            ['title' => "Quiz 2: avance de {$short}", 'type' => 'quiz', 'base_points' => 40, 'max_attempts' => 2, 'due_at' => $this->origin->copy()->addDays(12)->setTime(23, 59), 'is_published' => true],
            ['title' => "Tarea 2: entrega futura de {$short}", 'type' => 'tarea', 'base_points' => 60, 'max_attempts' => 2, 'due_at' => $this->origin->copy()->addDays(20)->setTime(23, 59), 'is_published' => true],
            ['title' => "Borrador no publicado de {$short}", 'type' => 'tarea', 'base_points' => 50, 'max_attempts' => 2, 'due_at' => $this->origin->copy()->addDays(30)->setTime(23, 59), 'is_published' => false],
        ];
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function seedCourseActivity(Course $course, Collection $students): void
    {
        Carbon::setTestNow($this->origin);
        $teacher = $course->owner;
        $enrolled = $course->enrollments()->pluck('user_id');
        $courseStudents = $students->filter(fn (User $student) => $enrolled->contains($student->id))->values();

        $gradable = $course->activities()
            ->where('is_published', true)
            ->where('type', '!=', 'foro')
            ->where('due_at', '<', $this->origin)
            ->orderBy('due_at')
            ->get();

        foreach ($courseStudents as $student) {
            $profile = $this->profileFor($student);
            $targets = $this->pickActivities($gradable, $profile, $student->id);

            foreach ($targets as $index => $activity) {
                $this->submitAndMaybeGrade($activity, $student, $teacher, $profile, $index);
            }
        }

        $futureQuiz = $course->activities()
            ->where('is_published', true)
            ->where('type', 'quiz')
            ->where('due_at', '>', $this->origin)
            ->first();

        if ($futureQuiz) {
            foreach ($courseStudents->take(5) as $student) {
                if ($this->profileFor($student) === 'high') {
                    $this->submitAndMaybeGrade($futureQuiz, $student, $teacher, 'high', 0, false);
                }
            }
        }

        $this->seedForums($course, $courseStudents);
        $this->seedAttendance($course, $courseStudents, $teacher);
    }

    private function submitAndMaybeGrade(
        Activity $activity,
        User $student,
        User $teacher,
        string $profile,
        int $index,
        bool $gradeMost = true,
    ): void {
        $submitAt = $activity->due_at
            ? $activity->due_at->copy()->subDays(2)->subHours(($student->id + $index) % 12)
            : now()->subDays(3);

        if ($activity->due_at && $submitAt->greaterThanOrEqualTo($activity->due_at)) {
            $submitAt = $activity->due_at->copy()->subHour();
        }

        Carbon::setTestNow($submitAt);

        $result = $this->submissions->submitActivity(
            $activity->id,
            $student->id,
            fake('es_CO')->paragraphs(2, true),
            sprintf('demo-%d-%d', $activity->id, $student->id),
        );

        if (! $gradeMost) {
            return;
        }

        $leaveUngraded = $profile === 'medium' && $index === 1;

        if ($leaveUngraded) {
            return;
        }

        $score = match ($profile) {
            'high' => 82 + ($student->id % 14),
            'medium' => 68 + ($student->id % 12),
            default => 48 + ($student->id % 10),
        };

        Carbon::setTestNow($submitAt->copy()->addDay()->setTime(10, 0));

        $this->submissions->gradeSubmission(
            $result['submission']->fresh(),
            (float) min(100, $score),
            $teacher,
            'Comentario docente de demostración: revisa la rúbrica y refuerza las conclusiones.',
        );
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Activity>
     */
    private function pickActivities(Collection $activities, string $profile, int $userId): Collection
    {
        $ordered = $activities->values();

        return match ($profile) {
            'high' => $ordered,
            'medium' => $ordered->take(2 + ($userId % 2)),
            default => $ordered->take(1),
        };
    }

    /**
     * @param  Collection<int, User>  $courseStudents
     */
    private function seedForums(Course $course, Collection $courseStudents): void
    {
        $forum = $course->activities()->where('type', 'foro')->where('is_published', true)->first();

        if (! $forum) {
            return;
        }

        foreach ($courseStudents as $student) {
            $profile = $this->profileFor($student);
            $count = match ($profile) {
                'high' => 2 + ($student->id % 2),
                'medium' => 1,
                default => $student->id % 5 === 0 ? 1 : 0,
            };

            $parentId = null;

            for ($i = 0; $i < $count; $i++) {
                Carbon::setTestNow($this->origin->copy()->subWeeks(3)->addDays($i + ($student->id % 6)));
                $created = $this->forums->createPost(
                    $forum,
                    $student,
                    fake('es_CO')->paragraph(),
                    $i === 0 ? null : $parentId,
                );
                $parentId ??= $created['post']->id;
            }
        }
    }

    /**
     * @param  Collection<int, User>  $courseStudents
     */
    private function seedAttendance(Course $course, Collection $courseStudents, User $teacher): void
    {
        $sessions = $course->classSessions()->orderBy('starts_at')->get();

        foreach ($sessions as $session) {
            Carbon::setTestNow($session->starts_at->copy()->addHours(2));

            $rows = $courseStudents->map(function (User $student) use ($session) {
                $profile = $this->profileFor($student);
                $bucket = ($student->id + $session->id) % 10;

                $status = match ($profile) {
                    'high' => $bucket === 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT,
                    'medium' => $bucket <= 1 ? Attendance::STATUS_ABSENT : ($bucket === 2 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT),
                    default => $bucket <= 6 ? Attendance::STATUS_ABSENT : Attendance::STATUS_PRESENT,
                };

                return [
                    'user_id' => $student->id,
                    'status' => $status,
                ];
            })->all();

            $this->sessions->saveAttendance($session, $rows, $teacher);
        }
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, User>  $students
     */
    private function seedRedemptions(Collection $courses, Collection $students): void
    {
        Carbon::setTestNow($this->origin->copy()->subDays(2));

        foreach ($courses as $course) {
            $teacher = $course->owner;
            $instant = $course->id
                ? Reward::query()->where('course_id', $course->id)->where('needs_approval', false)->where('cost_points', 30)->first()
                : null;
            $approval = Reward::query()->where('course_id', $course->id)->where('needs_approval', true)->where('cost_points', 40)->first();

            $enrolledIds = $course->enrollments()->pluck('user_id');
            $rich = $students->filter(fn (User $student) => $enrolledIds->contains($student->id)
                && $this->availablePoints($student->id, $course->id) >= 40)->values();

            if ($approval && $rich->isNotEmpty()) {
                $pendingStudent = $course->id === $courses[0]->id
                    ? $students[1]
                    : $rich->first(fn (User $student) => $student->id !== $students[1]->id) ?? $rich[0];

                if ($enrolledIds->contains($pendingStudent->id) && $this->availablePoints($pendingStudent->id, $course->id) >= $approval->cost_points) {
                    $this->gamification->redeemReward(
                        $pendingStudent->id,
                        $approval->id,
                        "demo-pending-{$course->id}-{$pendingStudent->id}",
                    );
                }

                $toApprove = $rich->first(fn (User $student) => $student->id !== $pendingStudent->id);
                if ($toApprove && $this->availablePoints($toApprove->id, $course->id) >= $approval->cost_points) {
                    $approved = $this->gamification->redeemReward(
                        $toApprove->id,
                        $approval->id,
                        "demo-approved-{$course->id}-{$toApprove->id}",
                    );
                    $this->redemptions->approve($approved['redemption']->fresh(), $teacher);
                }

                $toReject = $rich->first(fn (User $student) => ! in_array($student->id, [$pendingStudent->id, $toApprove?->id], true));
                if ($toReject && $this->availablePoints($toReject->id, $course->id) >= $approval->cost_points) {
                    $rejected = $this->gamification->redeemReward(
                        $toReject->id,
                        $approval->id,
                        "demo-rejected-{$course->id}-{$toReject->id}",
                    );
                    $this->redemptions->reject(
                        $rejected['redemption']->fresh(),
                        $teacher,
                        'Cupo de asesoría insuficiente en la franja solicitada (demo).',
                    );
                }
            }

            if ($instant && $rich->isNotEmpty()) {
                foreach ($rich->reject(fn (User $student) => $student->id === $students[1]->id)->take(2) as $student) {
                    if ($this->availablePoints($student->id, $course->id) < $instant->cost_points) {
                        continue;
                    }

                    $this->gamification->redeemReward(
                        $student->id,
                        $instant->id,
                        "demo-instant-{$course->id}-{$student->id}",
                    );
                }
            }
        }
    }

    private function nudgeNearLevelUp(User $student, Course $course): void
    {
        $stats = UserCourseStat::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $stats) {
            return;
        }

        $next = Level::query()
            ->where('course_id', $course->id)
            ->where('level_number', '>', $stats->current_level)
            ->orderBy('level_number')
            ->first();

        if (! $next) {
            return;
        }

        $target = $next->points_required - 12;
        $delta = $target - (int) $stats->total_points;

        if ($delta <= 0) {
            return;
        }

        Carbon::setTestNow($this->origin->copy()->subDay());
        $this->gamification->awardPoints(
            $student->id,
            $course->id,
            $delta,
            'activity_submission',
            0,
        );
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, User>  $students
     */
    private function grantManualBadges(Collection $courses, Collection $students): void
    {
        foreach ($courses as $course) {
            $badge = Badge::query()
                ->where('course_id', $course->id)
                ->where('rule_type', Badge::RULE_MANUAL)
                ->first();

            if (! $badge) {
                continue;
            }

            $enrolledIds = $course->enrollments()->pluck('user_id');
            $collaborators = $students
                ->filter(fn (User $student) => $enrolledIds->contains($student->id) && $this->profileFor($student) === 'high')
                ->take(2);

            foreach ($collaborators as $student) {
                $this->gamification->grantBadge($student->id, $course->id, $badge);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function stampStreaks(Collection $students): void
    {
        foreach (UserCourseStat::query()->get() as $stat) {
            $profile = $this->profileFor($students->firstWhere('id', $stat->user_id) ?? $students[0]);
            $stat->update([
                'streak_days' => match ($profile) {
                    'high' => 6 + ($stat->user_id % 5),
                    'medium' => 2 + ($stat->user_id % 3),
                    default => $stat->user_id % 2,
                },
            ]);
        }
    }

    private function profileFor(User $student): string
    {
        if (preg_match('/estudiante(\d+)@/', $student->email, $matches) !== 1) {
            return 'medium';
        }

        $number = (int) $matches[1];

        if ($number <= 8) {
            return 'high';
        }

        if ($number >= 26) {
            return 'low';
        }

        return 'medium';
    }

    private function availablePoints(int $userId, int $courseId): int
    {
        return (int) UserCourseStat::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->value('total_points');
    }
}
