<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * تحويل كائن المستخدم إلى مصفوفة بالبيانات المحددة
     */
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'is_profile_completed' => $this->is_profile_completed,
            'roles'                => $this->getRoleNames(),
            'email_verified'       => !is_null($this->email_verified_at),
            'phone_verified'       => !is_null($this->phone_verified_at),
        ];
    }

    /**
     * أعلى معايير الـ Clean Code: بناء استجابة النجاح المركزية عند التسجيل
     */
    public static function customResponse($user, string $token)
    {
        return (new self($user))
            ->additional([
                'status'  => 'success',
                'message' => 'تم إنشاء الحساب بنجاح. يرجى تفعيله.',
                'data'    => [
                    'requires_verification' => true,
                    'verification_token'    => $token,
                ]
            ]);
    }
}