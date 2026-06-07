<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification as ModelsNotification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    protected Messaging $messaging;

    
    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        try {
            $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->toArray();

            if (empty($tokens)) {
                Log::warning("Firebase: No device tokens found for user ID: {$userId}");
                return [
                    'success' => false,
                    'message' => 'No device tokens found for this user.'
                ];
            }

            $dbNotification = ModelsNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
                'is_read' => false
            ]);

            $notification = Notification::create($title, $body);
            $formattedData = array_map('strval', $data);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($formattedData);   

            $report = $this->messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $invalidTokens = $report->failures()->targetSymbols();

                $this->handleInvalidTokens($invalidTokens);
            }

            Log::info("Firebase: Notification sent to user {$userId}. Successes: {$report->successes()->count()}, Failures: {$report->failures()->count()}");

            return [
                'success' => true,
                'message' => 'Notification processed successfully.',
                'db_id'   => $dbNotification->id,
                'success_count' => $report->successes()->count(),
                'failure_count' => $report->failures()->count()
            ];

        } catch (\Exception $e) {
            Log::error("Firebase: Critical failure for user {$userId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending notification.'
            ];
        }
    }

  
    protected function handleInvalidTokens(array $invalidTokens): void
    {
        if (!empty($invalidTokens)) {
            DeviceToken::whereIn('token', $invalidTokens)->delete();
            Log::info("Firebase: Cleaned up " . count($invalidTokens) . " invalid device tokens from database.");
        }
    }
}