<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $this->notificationService->listForUser($user),
            'unread_count' => $this->notificationService->unreadCount($user),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $updatedNotification = $this->notificationService->markAsRead(
            $notification,
            $request->user(),
        );

        return response()->json([
            'notification' => $updatedNotification,
            'unread_count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }
}
