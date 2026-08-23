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
    // بدل حقن Messaging مباشرة، نحقنه lazily
    protected ?Messaging $messaging = null;

    public function __construct()
    {
        // لا شيء هنا — Firebase لا يُحمَّل إلا عند الاستخدام
    }

    protected function getMessaging(): ?Messaging
    {
        if ($this->messaging === null) {
            try {
                $this->messaging = app(Messaging::class);
            } catch (\Exception $e) {
                Log::warning('Firebase Messaging not available: ' . $e->getMessage());
                return null;
            }
        }
        return $this->messaging;
    }

    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        $messaging = $this->getMessaging();

        // إذا Firebase مش متاح، سجّل الإشعار في DB فقط بدون crash
        if ($messaging === null) {
            ModelsNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
                'is_read' => false
            ]);
            return ['success' => false, 'message' => 'Firebase not configured.'];
        }

        try {
            $tokens = DeviceToken::where('user_id', $userId)->pluck('device_token')->toArray();
            if (empty($tokens)) {
                return ['success' => false, 'message' => 'No tokens found.'];
            }

            ModelsNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
                'is_read' => false
            ]);

            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $report = $messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $this->handleInvalidTokens($report->failures());
            }

            return [
                'success'       => true,
                'success_count' => $report->successes()->count(),
                'failure_count' => $report->failures()->count()
            ];
        } catch (\Exception $e) {
            Log::error("Firebase Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    protected function handleInvalidTokens($failures): void
    {
        if (!is_iterable($failures)) return;

        $invalidTokens = [];
        foreach ($failures as $failure) {
            $invalidTokens[] = $failure->target()->value();
        }

        if (!empty($invalidTokens)) {
            DeviceToken::whereIn('device_token', $invalidTokens)->delete();
            Log::info("Firebase: Cleaned up " . count($invalidTokens) . " tokens.");
        }
    }
}

