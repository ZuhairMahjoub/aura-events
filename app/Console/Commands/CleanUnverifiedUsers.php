<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanUnverifiedUsers extends Command
{
    /**
     * الاسم البرمجي لتشغيل الأمر يدوياً
     *
     * @var string
     */
    protected $signature = 'auth:clean-unverified';

    /**
     * وصف بسيط للأمر
     *
     * @var string
     */
    protected $description = 'حذف الحسابات غير المفعلة (عبر الإيميل أو الهاتف) بعد مرور 10 دقائق من إنشائها وتنظيف صلاحياتها تلقائياً';

    /**
     * تنفيذ الأمر
     */
    public function handle()
    {
        $timeLimit = Carbon::now()->subMinutes(10);

        $unverifiedUsersQuery = User::where('created_at', '<=', $timeLimit)
                                    ->whereNull('email_verified_at')
                                    ->whereNull('phone_verified_at');

        $count = $unverifiedUsersQuery->count();

        if ($count > 0) {
            $users = $unverifiedUsersQuery->get();
            
            foreach ($users as $user) {
                $user->delete(); 
            }

            Log::info("Task Scheduler: تم تنظيف الحسابات المعلقة وحذف ({$count}) حساب مع أدواره بنجاح.");
            $this->info("تم بنجاح حذف {$count} حساب غير مفعل وتنظيف الأدوار التابعة له.");
        } else {
            $this->info("لا توجد حسابات معلقة لتنظيفها حالياً.");
        }
    }
}