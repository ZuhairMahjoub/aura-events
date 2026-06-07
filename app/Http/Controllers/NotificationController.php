<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification as ModelsNotification;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected FirebaseNotificationService $notificationService;

    
    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

   
    public function all(Request $request): JsonResponse
    {
        $notifications = ModelsNotification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    
    public function updateToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $request->token],
            ['user_id' => $request->user()->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully.'
        ]);
    }

   
    public function markAsRead($id, Request $request): JsonResponse
    {
        $notification = ModelsNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.'
        ]);
    }

   
    public function sendTestNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'title'   => 'required|string',
            'body'    => 'required|string',
            'data'    => 'nullable|array'
        ]);

        $result = $this->notificationService->sendToUser(
            $request->user_id,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}