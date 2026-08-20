<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
// 💡 استيراد خدمة الإشعارات
use App\Services\FirebaseNotificationService;

class AdminListingController extends Controller
{
    protected FirebaseNotificationService $notificationService;

    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function approve($id): JsonResponse
    {
        // 💡 1. جلب الإعلان مع جميع العلاقات اللازمة (المزود، المتغيرات، الفريلانسرز، والتواريخ)
        $listing = Listing::with([
            'provider',
            'variants.packageFreelancers.freelancer',
            'variants.availabilities'
        ])->findOrFail($id);

        $listing->update([
            'moderation_status' => 'approved',
            'rejection_reason'  => null
        ]);

        // ── 💡 2. إرسال إشعار الموافقة الأساسي للشركة ──
        // (استخدام getTranslation آمن إذا كنت تستخدمين حزمة Spatie)
        $titleEn = is_array($listing->title) ? ($listing->title['en'] ?? current($listing->title)) : $listing->title;
        $companyUserId = $listing->provider->user_id;

        $this->notificationService->sendToUser(
            $companyUserId,
            'Listing Approved ✅',
            "Congratulations! Your listing '{$titleEn}' has been approved and is now live.",
            ['type' => 'listing_approved', 'listing_id' => $listing->id]
        );

        // ── 💡 3. إذا كان الإعلان عبارة عن "تنسيق/باقة"، نرسل إشعارات الحجز للفريلانسرز ──
        if ($listing->listing_type === 'package') {
            $variant = $listing->variants->first();

            if ($variant) {
                // جلب التواريخ وتنسيقها كنص لطباعتها في الإشعار
                $dates = $variant->availabilities->pluck('available_date')->map(function ($date) {
                    return $date->format('Y-m-d');
                })->toArray();
                
                $datesString = empty($dates) ? 'Open Dates' : implode(', ', $dates);
                $notifiedCount = 0;

                // الدوران على الفريلانسرز المرتبطين بالباقة
                foreach ($variant->packageFreelancers as $packageFreelancer) {
                    if ($packageFreelancer->freelancer) {
                        $freelancerUserId = $packageFreelancer->freelancer->user_id;

                        // إرسال الإشعار للفريلانسر
                        $this->notificationService->sendToUser(
                            $freelancerUserId,
                            'You Have Been Booked! 📅',
                            "You have been officially booked for the package '{$titleEn}' on the following dates: {$datesString}.",
                            ['type' => 'freelancer_booked', 'listing_id' => $listing->id]
                        );
                        $notifiedCount++;
                    }
                }

                // ── 💡 4. إشعار تأكيدي للشركة بأنه تم تبليغ الفريلانسرز ──
                if ($notifiedCount > 0) {
                    $this->notificationService->sendToUser(
                        $companyUserId,
                        'Freelancers Notified 📩',
                        "All {$notifiedCount} freelancer(s) in your package '{$titleEn}' have been notified and booked for the dates: {$datesString}.",
                        ['type' => 'company_freelancers_notified', 'listing_id' => $listing->id]
                    );
                }
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم قبول الإعلان بنجاح، وهو الآن نشط على المنصة.',
            'data'    => $listing
        ], 200);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000']
        ]);

        $listing = Listing::with('provider')->findOrFail($id);

        $listing->update([
            'moderation_status' => 'rejected',
            'rejection_reason'  => $request->rejection_reason
        ]);

        $titleEn = is_array($listing->title) ? ($listing->title['en'] ?? current($listing->title)) : $listing->title;
        $userId = $listing->provider->user_id;

        $this->notificationService->sendToUser(
            $userId,
            'Listing Action Required ⚠️',
            "Unfortunately, your listing '{$titleEn}' was rejected. Reason: {$request->rejection_reason}",
            ['type' => 'listing_rejected', 'listing_id' => $listing->id]
        );

        return response()->json([
            'status'  => true,
            'message' => 'تم رفض الإعلان بنجاح، وتم تسجيل سبب الرفض.',
            'data'    => $listing
        ], 200);
    }

    public function pendingList(): JsonResponse
    {
        $listings = Listing::with([
            'provider.user',
            'category',
            'district',
            'images',
            'variants.images',
            'variants.availabilities.slots',
            'variants.packageItems.includedVariant.listing',
            'variants.packageFreelancers.freelancer.user',
        ])->where('moderation_status', 'pending_approval')
            ->oldest()
            ->paginate(15);

        return response()->json(
            array_merge(
                ['status' => true],
                \App\Http\Resources\ListingResource::collection($listings)->response()->getData(true)
            ),
            200
        );
    }
}