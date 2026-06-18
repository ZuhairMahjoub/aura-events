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
    public function __construct(
    ) {}

    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        try {
            $tokens = DeviceToken::where('user_id', $userId)->pluck('device_token')->toArray();

            if (empty($tokens)) {
                return ['success' => false, 'message' => 'لم يتم العثور على رموز الأجهزة.'];
            }

            // حفظ الإشعار في قاعدة البيانات
            ModelsNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
                'is_read' => false
            ]);

            // التأكد من أن قيم البيانات هي نصوص (لأن Firebase لا يقبل غير النصوص)
            $formattedData = array_map(fn($item) => is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : (string)$item, $data);

            // إنشاء رسالة الإشعار
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($formattedData);

            $report = $this->messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $this->handleInvalidTokens($report->failures());
            }

            return [
                'success' => true,
                'success_count' => $report->successes()->count(),
                'failure_count' => $report->failures()->count()
            ];

        } catch (\Exception $e) {
            Log::error("Firebase Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
        }
    }

    protected function handleInvalidTokens($failures): void
    {
        $invalidTokens = [];
        foreach ($failures as $failure) {
            if ($failure->error()->isInvalidRegistrationToken()) {
                $invalidTokens[] = $failure->target()->value();
            }
        }

        if (!empty($invalidTokens)) {
            DeviceToken::whereIn('device_token', $invalidTokens)->delete();
        }
    }
}