<?php

namespace App\Listeners;

use App\Events\LevelUpEvent;
use App\Models\Course;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class CreateLevelUpNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(LevelUpEvent $event): void
    {
        $course = Course::find($event->courseId);
        $courseTitle = $course?->title ?? 'tu curso';

        $this->notificationService->createNotification(
            $event->userId,
            '¡Subiste de nivel!',
            sprintf(
                'Felicidades, alcanzaste el nivel %d en %s.',
                $event->newLevel,
                $courseTitle,
            ),
            Notification::TYPE_LEVEL,
        );

        Log::info('user.level_up', [
            'user_id' => $event->userId,
            'course_id' => $event->courseId,
            'new_level' => $event->newLevel,
        ]);
    }
}
