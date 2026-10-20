<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    /**
     * @return Collection<int, Notification>
     */
    public function listForUser(User $user): Collection
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(20)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function createNotification(int $userId, string $title, string $message, string $type = Notification::TYPE_GRADE): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function markAsRead(Notification $notification, User $user): Notification
    {
        if ($notification->user_id !== $user->id) {
            abort(403, 'No tienes permiso para modificar esta notificación.');
        }

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
            ]);
        }

        return $notification->fresh();
    }
}
