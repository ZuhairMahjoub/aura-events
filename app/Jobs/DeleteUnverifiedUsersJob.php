<?php

namespace App\Jobs;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteUnverifiedUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // تغيير النوع إلى string لأن الـ ULID عبارة عن نص
    public string $userId;

    // استقبال الـ ULID كنص
    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // البحث عن المستخدم باستخدام الـ ULID النصي
        $user = User::find($this->userId);

        if (!$user) {
            Log::info("Job completed: User with ULID {$this->userId} no longer exists.");
            return;
        }

        // التحقق من حالة التفعيل
        $isVerified = !is_null($user->phone_verified_at) 
                   || !is_null($user->email_verified_at);

        if ($isVerified) {
            Log::info("User with ULID {$user->id} verified within the timeframe. Skipping deletion.");
            return;
        }

        // الحذف النهائي إذا انتهت المهلة ولم يُفعّل
        $user->tokens()->delete();
        $user->forceDelete();

        Log::info("Unverified User with ULID {$this->userId} has been deleted successfully.");
    }
}