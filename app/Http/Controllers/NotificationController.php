<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification as ModelsNotification;
use App\Models\DeviceToken; 
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
        $validated = $request->validate([
            'device_token' => 'required|string|max:255',
        ]);

        DeviceToken::updateOrCreate(
            ['device_token' => $validated['device_token']],
            ['user_id'      => $request->user()->id]
        );

        return response()->json([
            'status'  => 'success', 
            'message' => 'Device token registered successfully.'
        ], 200);
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
        $validated = $request->validate([
            'user_id'      => 'required|exists:users,id', // التأكد من وجود المستخدم
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'device_token' => 'nullable|string',
            'data'         => 'nullable|array'
        ]);

        if (!empty($validated['device_token'])) {
            DeviceToken::updateOrCreate(
                ['device_token' => $validated['device_token']],
                ['user_id'      => $validated['user_id']]
            );
        }

        $result = $this->notificationService->sendToUser(
            $validated['user_id'],
            $validated['title'],
            $validated['body'],
            $validated['data'] ?? []
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}